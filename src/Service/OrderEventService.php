<?php
namespace OrderNotifier\Service;

use OrderNotifier\Helpers\StorageHelper;
use OrderNotifier\Helpers\UserHelper;
use OrderNotifier\Helpers\OrdersHelper;
use OrderNotifier\Helpers\ScreenHelper;
use OrderNotifier\Utils\Debug;
use OrderNotifier\Utils\Constants;

if (!defined('ABSPATH')) {
    exit;
}

class OrderEventService {
    /**
     * Priprema i sprema SSE događaj za novu narudžbu u json buffer.
     * Ako je korisnik prijavljen, događaj ide odmah (SSE loop), u suprotnom se sprema u buffer.
     *
     * @param int   $order_id  ID nove narudžbe.
     * @param array $meta_keys Dodatni meta podaci koje treba uključiti u event (opcionalno).
     * @return void
     */
    public static function dispatch_new_order_event(int $order_id, array $meta_keys = []): void {
        $order_data = OrdersHelper::get_minimal_order_data($order_id, false);

        if (empty($order_data)) {
            Debug::log("Nema podataka za order_id: $order_id");
            return;
        }

        // Provjera trenutnog screena — koristi se za reload logiku
        $reload = false;
        
        if (StorageHelper::is_option_enabled('reload_table')){
            $reload = ScreenHelper::is_order_page_screen();
        }

        $payload = self::prepare_payload([
            'title'   => "Nova narudžba #{$order_data['order_id']}",
            'message' => "Primljena je narudžba od {$order_data['billing_name']}.",
        ]);

        $event_meta = [
            'event_type' => 'message',
            'timestamp'  => time(),
            'order_id'   => $order_id,
            'reload'     => $reload,
        ];

        if (!empty($meta_keys)) {
            $event_meta = array_merge($event_meta, $meta_keys);
        }

        self::store_order_event($event_meta, $payload);

        Debug::log("Poslan event 'new-order' za narudžbu: $order_id");
    }


    /**
     * Sprema pojedini događaj u centralni JSON buffer (SSE sustav).
     *
     * @param array $event_meta Meta podaci o događaju, npr.:
     *                          NAPOMENA: trenutno koristimo za sve evente, event_type' "message" zbog js logike
     *                          - 'event_type' => string (npr. 'new-order')
     *                          - 'order_id'   => int (opcionalno, za UID)
     *                          - dodatni ključevI po potrebi
     * @param array $payload    Podaci za prikaz, čisti prikazni payload eventa
     *                          npr. ['title' => ..., 'message' => ..., 'type' => ...]
     * @return void
     */
    public static function store_order_event(array $event_meta, array $payload): void {
        if (empty($payload)) {
            Debug::log('Prazan payload');
            return;
        }
        Debug::log('Šaljem event u buffer');
        self::send_event_via_buffer($payload, $event_meta);

        if (isset($event_meta['order_id'])) {
            self::cleanup_old_order_events();
            Debug::log('Pozivamo funkciju za čišćenje starih eventa');
        }
    }

    /**
     * Priprema payload za event sa standardnim poljima za prikaz poruke.
     *
     * @param array $params Ulazni parametri payloada, mogu sadržavati:
     *                      - 'title'    => string  (naslov poruke)
     *                      - 'message'  => string  (tekst poruke)
     *                      - 'type'     => string  (npr. 'info', 'warning', 'error', 'success')
     *                      - 'position' => string  (npr. 'top-right', 'bottom-left', itd.)
     *                      - 'timeout'  => int     (vrijeme trajanja u ms ili s)
     *                      - 'icon'     => string  (putanja ili ime ikone)
     * @return array Struktuirani payload spreman za event.
     */
    public static function prepare_payload(array $params): array {
        $defaults = StorageHelper::get_options([
            'default_notification_type'     => 'info',
            'default_notification_position' => 'top-right',
            'default_notification_timeout'  => 0,
            'default_notification_icon'     => '',
        ]);

        return [
            'title'    => $params['title']    ?? '',
            'message'  => $params['message']  ?? '',
            'type'     => $params['type']     ?? $defaults['default_notification_type'],
            'position' => $params['position'] ?? $defaults['default_notification_position'],
            'timeout'  => $params['timeout']  ?? $defaults['default_notification_timeout'],
            'icon'     => $params['icon']     ?? $defaults['default_notification_icon'],
        ];
    }

    /**
     * Sprema jedan SSE događaj u centralizirani JSON buffer.
     *
     * @param array $payload    Prikazni podaci eventa (payload)
     * @param array $event_meta Meta podaci eventa
     * @return void
     */
    protected static function send_event_via_buffer(array $payload, array $event_meta): void {
        Debug::log('Pozivam funkciju get_buffer..');
        $buffer = self::get_or_initialize_buffer();
        Debug::log('Pozivam funkciju prepare_event..');
        $event = self::prepare_event($payload, $event_meta);

        // Ovaj dio treba provjeriti dali radi Factory istu stvar
        Debug::log('Pozivam funkciju find existing event..');
        $existing_event = self::find_existing_event($event, $buffer['events']);
       
        if ($existing_event !== null) {
            if (!empty($existing_event['is_processed'])) {
                // eventualno cleanup
            }
            Debug::log('Postojeći event, izlazimo..');
            return; // Ne dodajemo duplikat
        }

        $buffer['events'][] = $event;
        StorageHelper::set_json_buffer($buffer);

        Debug::log("Događaj '{$event_meta['event_type']}' dodan u JSON buffer", $event);
    }

    /**
     * Dohvaća ili inicijalizira JSON buffer u kojem se spremaju događaji.
     *
     * @return array Trenutni buffer s eventima
     */
    private static function get_or_initialize_buffer(): array {
       
        $buffer = StorageHelper::get_json_buffer();
        // Debug::log('get_json_buffer', $buffer);
        if (!is_array($buffer)) {
            $buffer = [
                'events' => [],
            ];
        }
        Debug::log('Inicijaliziran JSON buffer.');

        return $buffer;
    }

    /**
     * Priprema kompletan event sa meta podacima i payloadom.
     *
     * @param array  $payload    Prikazni podaci eventa
     * @param array  $event_meta Meta podaci eventa
     * @return array Strukturirani event spreman za spremanje
     */
    private static function prepare_event(array $payload, array $event_meta): array {
        $event = [];

        $event['timestamp'] = time();

        if (isset($event_meta['order_id']) && isset($event_meta['event_type'])) {
            $event['uid'] = sha1($event_meta['event_type'] . $event_meta['order_id']);
        } else {
            $event['uid'] = sha1(json_encode($event_meta) . uniqid('', true));
        }

        $event['is_processed'] = false;

        // Spoji $event_meta direktno u glavni event niz
        $event = array_merge($event, $event_meta);

        $event['payload'] = $payload;

        Debug::log('Pripremljeni event', $event);

        return $event;
    }


    /**
     * Provjerava postoji li isti event u bufferu prema jedinstvenom uid-u.
     *
     * @param array $new_event      Novi event koji provjeravamo
     * @param array $existing_events Lista postojećih eventa u bufferu
     * @return array|null Pronađeni event ili null ako ne postoji
     */
    private static function find_existing_event(array $new_event, array $existing_events): ?array {
        Debug::log('Funkcija find existing event..');
        foreach ($existing_events as $event) {
            if (
                isset($event['uid']) &&
                $event['uid'] === $new_event['uid']
            ) {
                return $event;
            }
        }
        return null;
    }

    /** Ovu funkciju treba provjeriti dali treba ostati u ovom obliku
     * Briše sve prethodne 'new-order' događaje i ostavlja samo zadnji.
     *
     * @return void
     */

    public static function cleanup_old_order_events(): void {
        $buffer = StorageHelper::get_json_buffer();
        if (!is_array($buffer) || empty($buffer['events'])) {
            return;
        }

        // Filtriraj sve evente koji imaju order_id
        $order_events = array_filter($buffer['events'], function ($event) {
            return isset($event['order_id']);
        });

        if (count($order_events) === 0) {
            // Nema niti jedne narudžbe – obriši sve
            $buffer['events'] = [];
            StorageHelper::set_json_buffer($buffer);
            return;
        }

        // Pronađi najnoviji event narudžbe (po timestampu)
        usort($order_events, function ($a, $b) {
            return $b['timestamp'] <=> $a['timestamp'];
        });
        $latest_order_event = reset($order_events);

        // Postavi samo taj jedan event u buffer
        $buffer['events'] = [$latest_order_event];

        StorageHelper::set_json_buffer($buffer);
    }


    public static function dispatch_welcome_order_event(int $order_id): void {
        $order_data = OrdersHelper::get_minimal_order_data($order_id, false);
        $payload = self::prepare_payload([
            'title'   => "Dobrodošli! Nova narudžba #{$order_data['order_id']}",
            'message' => "Primljena prva narudžba od {$order_data['billing_name']}.",
            'type'    => 'success',
        ]);
        self::store_order_event([
            'event_type' => 'message',
            'timestamp'  => time(),
            'order_id'   => $order_id,
        ], $payload);
    }

    public static function dispatch_multiple_orders_event(int $new_orders_count): void {
        $payload = self::prepare_payload([
            'title'   => "Imate {$new_orders_count} novih narudžbi",
            'message' => "Provjerite listu narudžbi kako biste vidjeli detalje.",
            'type'    => 'info',
        ]);
        self::store_order_event([
            'event_type' => 'message',
            'timestamp'  => time(),
        ], $payload);
    }

     /**
     * Metoda iz stare verzije, možeš dopuniti po potrebi.
     */
    public static function get_status_order($order_id): void {
        // Ovdje staviš kod za obradu promjene statusa narudžbe
        Debug::log("Promjena statusa narudžbe ID: $order_id");
    }

}
