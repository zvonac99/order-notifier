<?php

namespace OrderNotifier\SSE;

use EliasHaeussler\SSE\Stream\SelfEmittingEventStream;
use EliasHaeussler\SSE\Event\Event; // Važno za DataEvent
use EliasHaeussler\SSE\Exception\StreamIsActive;
use EliasHaeussler\SSE\Exception\StreamIsClosed;

use OrderNotifier\SSE\DataEvent;
use OrderNotifier\SSE\Factory\RealEventFactory;
use OrderNotifier\SSE\Factory\SystemEventFactory;
use OrderNotifier\SSE\Factory\TestEventFactory;

use OrderNotifier\Utils\Debug;
use OrderNotifier\Helpers\StorageHelper;
use OrderNotifier\Utils\Constants;

// WordPress klase za REST API
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class SseCore
 *
 * Centralna klasa za upravljanje SSE (Server-Sent Events) streamom.
 * Registrira REST endpoint, provjerava korisničke ovlasti i emitira
 * stvarne, testne i sistemske događaje.
 *
 * @package EliasHaeussler\SSE
 */
class SseCore {

    /**
     * Interval između generiranja testnih poruka (sekunde).
     */
    private const TEST_STREAM = 45;

    /**
     * Default trajanje streama (sekunde).
     */
    private const DEFAULT_STREAM_LIFETIME = 300;

    /**
     * Default interval provjere novih događaja (milisekunde).
     */
    private const DEFAULT_CHECK_INTERVAL_MS = 2000;

    /**
     * Default uključivanje ping događaja.
     */
    private const DEFAULT_ENABLE_STREAM_PING = false;

    /**
     * Default interval ping događaja (sekunde).
     */
    private const DEFAULT_STREAM_PING = 15;
    
    /**
     * Fallback zaštitni interval ping događaja (sekunde).
     */
    private const FALLBACK_STREAM_PING = 90;

    /**
     * Default uključivanje testnih događaja.
     */
    private const DEFAULT_ENABLE_TEST_EVENTS = false;

    /**
     * SseCore constructor.
     * Inicijalizira SSE klasu i logira učitavanje.
     */
    public function __construct() {
        Debug::log("SSE klasa učitana.");
    }

    /**
     * Registrira REST endpoint za SSE stream.
     * Endpoint koristi 'register_stream_and_exit' callback
     * i 'check_sse_permission' za provjeru ovlasti korisnika.
     */
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

    /**
     * Provjerava ovlasti korisnika za pristup SSE streamu.
     *
     * @param WP_REST_Request $request
     * @return bool|WP_Error True ako korisnik ima prava, WP_Error inače
     */
    public function check_sse_permission(WP_REST_Request $request): bool|WP_Error {
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
     * REST callback koji pokreće SSE stream i odmah završava izvršavanje.
     * Ne vraća WP_REST_Response jer koristi direktan output SSE.
     */
    /* public function register_stream_and_exit(): void {
        Debug::log('Registriran stream');
        $this->start_stream();
        exit;
    } */
    public function register_stream_and_exit(): void {
        // --- Isključi gzip i output buffering ---
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');          // Apache mod_deflate
        }
        // @ini_set('zlib.output_compression', '0');    // PHP zlib output compression
        @ini_set('zlib.output_compression', '1');    // PHP zlib output compression
        @ini_set('zlib.output_compression', '9');    // PHP zlib output compression
        // Očisti sve output buffere
        /* while (ob_get_level() > 0) {
            ob_end_clean();
        } */

        // Makni eventualni Content-Encoding header ako je već postavljen
        header_remove('Content-Encoding');

        // Headeri za SSE
        header('Content-Type: text/event-stream; charset=UTF-8');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // za reverse proxy / nginx

        // --- Pokreni stream ---
        $this->start_stream();

        // Sigurno odmah završi skriptu
        exit;
    }


    /**
     * Dohvati konfiguraciju streama iz postavki.
     *
     * @return array Konfiguracija s ključevima:
     *               - stream_lifetime
     *               - check_interval_us
     *               - enable_ping
     *               - stream_ping
     *               - enable_test_events
     */
    private function get_config(): array {
        return [
            'stream_lifetime'    => self::DEFAULT_STREAM_LIFETIME,
            'check_interval_us'  => self::DEFAULT_CHECK_INTERVAL_MS * 1000,
            'enable_ping'        => StorageHelper::is_option_enabled('enable_ping'),
            'stream_ping'        => StorageHelper::get_option('ping_interval', self::DEFAULT_STREAM_PING),
            'enable_test_events' => StorageHelper::is_option_enabled('enable_test_events'),
        ];
    }

    /**
     * Pokreće SSE stream i glavnu petlju za emitiranje događaja.
     *
     * Emitira:
     * - stvarne događaje iz RealEventFactory (one-shot, stream se zatvara)
     * - testne događaje iz TestEventFactory
     * - ping događaje iz SystemEventFactory (ako je omogućeno)
     */
    private function start_stream(): void {
        $config = $this->get_config();

        Debug::log('Pokrenut stream:', ['Trajanje' => $config['stream_lifetime']]);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $stream = SelfEmittingEventStream::create(null, self::DEFAULT_CHECK_INTERVAL_MS);
        $stream->open();

        $startTime = time();
        $lastPingTime = $startTime;
        $lastTestTime = 0;

        $quickPingsSent = 0;          // <-- novi brojač burst pinga
        $quickPingInterval = 1;       // 1 sekunda između početnih pingova

        while (true) {
            if (time() - $startTime > $config['stream_lifetime']) {
                $stream->close('timeout');
                Debug::log('Stream zatvoren');
                break;
            }

            // --- Stvarni event ---
            $event = RealEventFactory::fetchNext();
            if ($event instanceof DataEvent) {
                $stream->sendEvent($event);
                Debug::log("Event {$event->getData()['uid']} poslan, stream se zatvara (one-shot)");
                $stream->close('done');
                break;
            }

            // --- Testni event ---
            $testevent = TestEventFactory::create();
            if ($config['enable_test_events'] && (time() - $lastTestTime) >= self::TEST_STREAM) {
                $stream->sendEvent($testevent);
                $lastTestTime = time();
                break;
            }

            // --- Ping logika ---
            $pingevent = SystemEventFactory::createPing();

            if ($quickPingsSent < 3) {
                // Pošalji prvih 3 pinga odmah u 1s razmaku
                if ((time() - $lastPingTime) >= $quickPingInterval) {
                    $stream->sendEvent($pingevent);
                    $lastPingTime = time();
                    $quickPingsSent++;
                    Debug::log("Poslan brzi ping ({$quickPingsSent}/3)");
                }
            } elseif ($config['enable_ping'] && (time() - $lastPingTime) >= $config['stream_ping']) {
                $stream->sendEvent($pingevent);
                $lastPingTime = time();
                Debug::log("Poslan ping prema intervalu iz konfiguracije: {$config['stream_ping']}");
            } elseif (!$config['enable_ping'] && (time() - $lastPingTime) >= self::FALLBACK_STREAM_PING) {
                $stream->sendEvent($pingevent);
                $lastPingTime = time();
                Debug::log("Poslan fallback keep-alive ping");
            }

            usleep($config['check_interval_us']);
        }
    }

}