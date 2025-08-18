<?php

namespace EliasHaeussler\SSE;

use EliasHaeussler\SSE\Stream\SelfEmittingEventStream;
use EliasHaeussler\SSE\Event\Event; // Važno za DataEvent
use EliasHaeussler\SSE\Exception\StreamIsActive;
use EliasHaeussler\SSE\Exception\StreamIsClosed;
use EliasHaeussler\SSE\DataEvent;

use OrderNotifier\Utils\Debug;
use OrderNotifier\Helpers\StorageHelper;
use OrderNotifier\Utils\Constants;
use OrderNotifier\Helpers\UserHelper;

// WordPress klase za REST API
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SseCore {

    private const TEST_STREAM = 45;
    // Defaultne vrijednosti ako postavke nisu podešene
    private const DEFAULT_STREAM_LIFETIME = 300; // sekunde
    private const DEFAULT_CHECK_INTERVAL_MS = 2000; // u milisekundama
    private const DEFAULT_ENABLE_STREAM_PING = false;
    private const DEFAULT_STREAM_PING = 15; // sekunde
    private const DEFAULT_ENABLE_TEST_EVENTS = false;

    public function __construct() {
        Debug::log("SSE klasa učitana.");
    }

    public function register_rest_routes() {
        register_rest_route(
            Constants::REST_NAMESPACE,
            '/' . Constants::REST_ROUTE,
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'register_stream_and_exit'],
                'permission_callback' => [$this, 'check_sse_permission'],
            ]
        );
        Debug::log("Registriran SSE stream endpoint u SSE klasi.");
    }

    public function check_sse_permission( WP_REST_Request $request ): bool|WP_Error {
        // Vaša postojeća logika provjere permisija
        if ( current_user_can( 'manage_woocommerce' ) ) {
            Debug::log('Korisnik ima prava');
            return true;
        }
        return new WP_Error(
            'rest_forbidden_sse',
            esc_html__( 'Niste ovlašteni za pristup ovom SSE streamu.', 'order-notifier' ),
            ['status' => rest_authorization_required_code()]
        );
    }


    /**
     * Dohvati konfiguraciju iz postavki.
     * Konverzija intervala u mikrosekunde za usleep.
     */
    private function get_config(): array {
        return [
            'stream_lifetime'    => self::DEFAULT_STREAM_LIFETIME,
            'check_interval_us'  => self::DEFAULT_CHECK_INTERVAL_MS * 1000,
            'enable_ping'        => StorageHelper::is_option_enabled('enable_ping'),
            'stream_ping'        => StorageHelper::get_option('stream_ping', self::DEFAULT_STREAM_PING),
            'enable_test_events' => StorageHelper::is_option_enabled('enable_test_events'),
        ];
    }

    /**
     * Početna REST callback funkcija koja pokreće stream i odmah završava izvršavanje.
     */
    public function register_stream_and_exit(): void {
        Debug::log('Registriran stream');
        $this->start_stream();
        exit; // Ne vraća WP_REST_Response jer je SSE stream
    }

    /**
     * Pokreće SSE stream i petlju koja provjerava i emitira poruke.
     */
    private function start_stream(): void {
        $config = $this->get_config();

        Debug::log('Pokrenut stream:', ['Trajanje' => $config['stream_lifetime']]);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // $stream = SelfEmittingEventStream::create(null, 2000);
        $stream = SelfEmittingEventStream::create(null, self::DEFAULT_CHECK_INTERVAL_MS);
        $stream->open();

        $startTime = time();
        $lastPingTime = $startTime;
        $lastTestTime = 0;

        while (true) {
            if (time() - $startTime > $config['stream_lifetime']) {
                $stream->close('timeout');
                Debug::log('Stream zatvoren');
                break;
            }

            $message = $this->get_next_event_message();

            if ($message && isset($message['event_type'])) {
                $event = new DataEvent('event', $message);
                $stream->sendEvent($event);

                $this->mark_event_as_processed($message['uid']);
                Debug::log('Event poslan, event označen kao dovršen, zatvaramo stream');
                $stream->close('timeout');
                break;
            }

            // Testni event ako je omogućen
            if ($config['enable_test_events'] && (time() - $lastTestTime) >= self::TEST_STREAM) {
                $this->send_test_event($stream);
                $lastTestTime = time();
                $stream->close('timeout');
                break;
            }

            // Ping event (samo ako je omogućeno u postavkama)
            if ($config['enable_ping'] && (time() - $lastPingTime) >= $config['stream_ping']) {
                $this->send_ping($stream);
                $lastPingTime = time();
            }

            usleep($config['check_interval_us']);
        }
    }


    /**
     * Dohvati sljedeću poruku iz JSON buffer datoteke.
     *
     * Očekivan format događaja:
     * [
     *   'timestamp'    => 1753488156,            // Unix timestamp kada je događaj zabilježen
     *   'uid'          => '6d97b9dccf60...',     // Jedinstveni identifikator događaja
     *   'is_processed' => [                      // Status obrade po korisničkim ulogama
     *       'administrator' => true,
     *       'shop_manager'  => false
     *   ],
     *   'event_type'   => 'message',             // Tip događaja (npr. 'message', 'new-order' itd.)
     *   'order_id'     => 42363,                 // ID povezane narudžbe (ako postoji)
     *   'reload'       => true,                  // Signalizira frontend aplikaciji da treba reloadati stranicu
     *   'payload' => [                           // Dodatni podaci koji se prenose uz događaj
     *       'title'    => 'Nova narudžba #42363',
     *       'message'  => 'Primljena je narudžba od Test Test.',
     *       'type'     => 'info',                // Tip poruke (npr. 'info', 'success', 'error')
     *       'position' => 'top',                 // Pozicija prikaza obavijesti
     *       'timeout'  => 0,                     // Trajanje prikaza u milisekundama (0 = trajno)
     *       'icon'     => ''                     // Ikona (ako je definirana)
     *   ]
     * ]
     */

    private function get_next_event_message(): ?array {
        $buffer = StorageHelper::get_json_buffer();
        // Debug::log('Buffer:', $buffer);
        if (!is_array($buffer) || empty($buffer['events'])) {
            return null;
        }

        
        $ctx = UserHelper::get_current_user_context();
        // $role = $ctx['role'];
        if (!$ctx['authorized']) {
            Debug::log('Događaj ne šaljemo - neautorizirani korisnik.', $ctx);
            return null;
        }


        foreach ($buffer['events'] as $event) {
            if (
                is_array($event) &&
                isset($event['uid']) &&
                /* is_array($event['is_processed']) &&
                array_key_exists($role, $event['is_processed']) && */
                isset($event['is_processed']) &&
                $event['is_processed'] === false
            ) {
                if (!isset($event['timestamp'])) {
                    $event['timestamp'] = time();
                }

                Debug::log("Sljedeći neobrađeni događaj:", $event);
                return $event;
            }
        }
        Debug::log('Nema novog eventa');
        return null;
    }


    public static function mark_event_as_processed(string $event_uid): void {
        // Dohvati trenutni buffer sa svim eventima
        $buffer = StorageHelper::get_json_buffer();

        // Ako buffer nije niz, inicijaliziraj ga praznim događajima
        if (!is_array($buffer)) {
            $buffer = [
                'events' => [],
            ];
        }

        // Dohvati ulogu trenutno prijavljenog korisnika
        $ctx = UserHelper::get_current_user_context();
        // $role = $ctx['role'];

        $found = false;

        // Prođi kroz sve evente u bufferu i pronađi onaj s traženim UID
        foreach ($buffer['events'] as &$event) {
            if (isset($event['uid']) && $event['uid'] === $event_uid) {
                // Ako već postoji ključ za tog korisnika u is_processed, postavi na true
                // Ako ne postoji, dodaj i postavi na true
                // $event['is_processed'][$role] = true;
                $event['is_processed'] = true;
                $found = true;
                break;
            }
        }
        unset($event); // za svaki slučaj odspoji referencu

        // Spremi promijenjeni buffer natrag
        StorageHelper::set_json_buffer($buffer);

        // Ako događaj nije pronađen, logiraj upozorenje
        if (!$found) {
            Debug::log("Nije pronađen događaj za UID: $event_uid", ['buffer' => $buffer]);
        }
    }


    /**
     * Šalje ping radi održavanja veze.
     */
    private function send_ping(SelfEmittingEventStream $stream): void {
        $ping = DataEvent::createSystem('ping', ['timestamp' => time()]);
        $stream->sendEvent($ping);
        Debug::log('Ping poslan za održavanje veze.');
    }


    /**
     * Pomoćna funkcija koja šalje testnu poruku iz get_test_message().
     */
    private function send_test_event(SelfEmittingEventStream $stream): void {
        $message = $this->get_test_message();
        $event = new DataEvent('event', $message);
        $stream->sendEvent($event);
        Debug::log('Testni event poslan unutar stream petlje:', $message);
    }

    /**
     * Generira testnu poruku za SSE simulaciju.
     *
     * @return array
     */
    private function get_test_message(): array {
        $defaults = StorageHelper::get_options([
            'default_notification_type'     => 'info',
            'default_notification_position' => 'top-right',
            'default_notification_timeout'  => 0,
            'default_notification_icon'     => '',
        ]);


        $order_id = random_int(10000, 99999);
        $names = ['Demo Demic', 'Ivana Testic', 'Marko Proba', 'Ana Streamovic'];
        $random_name = $names[array_rand($names)];

        return [
            'timestamp'     => time(),
            'event_type'    => 'message',
            'order_id'      => $order_id,
            'reload'        => false,
            'payload'       => [
                'title'     => "Nova narudžba #{$order_id}",
                'message'   => "Primljena je narudžba od {$random_name}.",
                'type'     => $params['type']     ?? $defaults['default_notification_type'],
                'position' => $params['position'] ?? $defaults['default_notification_position'],
                'timeout'  => $params['timeout']  ?? $defaults['default_notification_timeout'],
                'icon'     => $params['icon']     ?? $defaults['default_notification_icon'],
            ],
        ];
    }

}