<?php
namespace OrderNotifier\Service;

use OrderNotifier\Helpers\UserHelper;
use OrderNotifier\Helpers\StorageHelper;
use OrderNotifier\Helpers\OrdersHelper;
use OrderNotifier\Utils\Constants;
use OrderNotifier\Utils\Debug;
use OrderNotifier\Service\OrderEventService;

if (!defined('ABSPATH')) {
    exit;
}

class PluginBootstrapper
{
    /**
     * Glavna funkcija – provjerava nove narudžbe i šalje odgovarajuću obavijest.
     */
    public static function prepare_environment(): void {
        Debug::log('Hook za pripremu okruženja');

        $ctx = UserHelper::get_current_user_context();
        $meta_context = self::get_order_id_from_user_meta($ctx['user_id'], Constants::ON_USER_CONTEXT_KEY);

        $meta_order_id = $meta_context['last_seen_order_id'] ?? null;
        $current_order_id = OrdersHelper::get_last_order_id(['processing', 'on-hold']);

        Debug::log("Uspoređujemo podatke iz meta tablice: ({$meta_order_id}) i narudžbu: ({$current_order_id})");

        if (!$current_order_id) {
            Debug::log("Nema dostupnih narudžbi.");
            return;
        }


        // Ako nemamo spremljen meta_order_id (prva narudžba uopće)
        if ($meta_order_id === null) {
            self::process_new_order($ctx['user_id'], $current_order_id, $meta_order_id);
            OrderEventService::dispatch_welcome_order_event($current_order_id);
            Debug::log('Nova narudžba i dobrodošlica');
        }
        // Ako imamo spremljen meta_order_id ali je drugačiji (znači nove narudžbe su stigle)
        elseif ($meta_order_id !== $current_order_id) {
            $new_orders = OrdersHelper::get_new_orders(['processing', 'on-hold'], $meta_order_id);
            $new_count  = count($new_orders);

            self::process_new_order($ctx['user_id'], $current_order_id, $meta_order_id);

            if ($new_count > 1) {
                OrderEventService::dispatch_multiple_orders_event($new_count);
                Debug::log("Više novih narudžbi ({$new_count})");
            } else {
                OrderEventService::dispatch_new_order_event($current_order_id);
                Debug::log('Nova narudžba');
            }
        }
        else {
            Debug::log("ID u meta tablici i narudžba su isti ({$meta_order_id}) – nema promjena.");
        }
    }

    protected static function process_new_order(int $user_id, int $order_id, ?int $old_order_id = null): void {
        // Pohrani novi ID narudžbe u meta podatke
        $context = [
            'last_seen_order_id' => $order_id
        ];
        StorageHelper::set_user_meta($user_id, Constants::ON_USER_CONTEXT_KEY, $context);

        if ($old_order_id !== null) {
            Debug::log("Postoji nova narudžba. Stari ID: {$old_order_id}, Novi: {$order_id}");
        } else {
            Debug::log("Novi korisnik – postavljamo meta podatak i šaljemo event za narudžbu: {$order_id}");
        }
    }

    /**
     * Dohvati order ID iz user meta zapisa.
     *
     * @param int $user_id
     * @param string $key
     * @return int|null
     */
    protected static function get_order_id_from_user_meta(int $user_id, string $key): ?array {
        $value = StorageHelper::get_user_meta($user_id, $key);
        return is_array($value) ? $value : null;
    }

    /**
     * Dohvati zadnji viđeni order_id iz user meta.
     */
    protected static function get_last_seen_order_id(int $user_id): ?int
    {
        $value = StorageHelper::get_user_meta($user_id, Constants::ON_USER_CONTEXT_KEY);
        return is_array($value) && isset($value['last_seen_order_id'])
            ? (int) $value['last_seen_order_id']
            : null;
    }
}
