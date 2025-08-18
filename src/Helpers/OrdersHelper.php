<?php
/**
 * OrdersHelper
 *
 * Klasa za dohvat i obradu WooCommerce narudžbi.
 * https://usersinsights.com/wc-get-orders/
 */
namespace OrderNotifier\Helpers;

use WC_Order;
use OrderNotifier\Utils\Debug;

if (!defined('ABSPATH')) {
    exit;
}

class OrdersHelper {

    /**
     * Glavna metoda za dohvat narudžbi.
     *
     * @param array $args Argumenti za wc_get_orders().
     * @return array Lista narudžbi ili ID-eva, ovisno o 'return' parametru.
     */
    public static function query_orders(array $args): array {
        if (!isset($args['limit'])) {
            $args['limit'] = -1;
        }

        Debug::log('OrdersHelper::query_orders() args:', $args);

        return wc_get_orders($args);
    }

    /**
     * Helper za izgradnju date_created filtera.
     *
     * @param string|null $after Početni datum.
     * @param string|null $before Završni datum.
     * @return string|null Filter string (npr. '>2024-06-01' ili '2024-06-01...2024-06-10')
     */
    public static function build_date_filter(?string $after = null, ?string $before = null): ?string {
        if ($after && $before) {
            return "$after...$before";
        }
        if ($after) {
            return ">$after";
        }
        if ($before) {
            return "<$before";
        }
        return null;
    }

    /**
     * Broji sve aktivne (neprocesirane ili neuspjele) narudžbe.
     * @param array $statuses
     * @return int Vraća ukupan broj aktivnih narudžbi.
     */
    public static function get_active_order_count(array $statuses = ['pending', 'processing', 'on-hold', 'failed']): int {
        $orders = self::query_orders([
            'status'  => $statuses,
            'return' => 'ids',
        ]);

        return count($orders);
    }

    /**
     * Vraća sve narudžbe sa statusima (default: processing i completed) koje su novije (ID veći) od $last_id.
     * @param array $statuses
     * @param int $last_id
     * @return array
     */
    public static function get_new_orders(array $statuses = ['processing', 'completed'], int $last_id): array {
        $orders = self::query_orders([
            'status'  => $statuses,
            'orderby' => 'date',
            'order'   => 'DESC',
            'limit'   => 10,
            'return'  => 'ids',
        ]);

        return array_filter($orders, fn(int $id): bool => $id > $last_id);
    }

    /**
     * Vraća order_id i ime kupca. Ako je with_status = true, uključuje i status.
     * @param int $order
     * @param bool $with_status
     * @return array
     */
    public static function get_minimal_order_data(WC_Order|int $order, bool $with_status = false): ?array {
        if (is_numeric($order)) {
            $order = wc_get_order($order);
        }

        if (!$order instanceof WC_Order) {
            return null;
        }

        $data = [
            'order_id'     => $order->get_id(),
            'billing_name' => $order->get_formatted_billing_full_name(),
        ];

        if ($with_status) {
            $data['status'] = $order->get_status();
        }

        return $data;
    }

    /**
     * Detaljan prikaz narudžbe: ID, datum, status, ime i ukupna cijena.
     * @param int $order
     * @return array
     */
    public static function get_full_order_data(WC_Order|int $order): ?array {
        if (is_numeric($order)) {
            $order = wc_get_order($order);
        }

        if (!$order instanceof WC_Order) {
            return null;
        }

        return [
            'id'           => $order->get_id(),
            'date'         => $order->get_date_created()?->date('Y-m-d H:i:s'),
            'total'        => $order->get_total(),
            'status'       => $order->get_status(),
            'billing_name' => $order->get_formatted_billing_full_name(),
        ];
    }

    /**
     * Dohvati posljednju narudžbu po datumu s navedenim statusima, opcionalno filtrirano po datumu (after_date).
     * @param array $statuses
     * @param string|null $after_date Datum u formatu 'Y-m-d H:i:s'. Ako nije postavljen, dohvaća sve.
     * @return int ID zadnje pronađene narudžbe ili 0 ako ne postoji.
     */
    public static function get_last_order_id(array $statuses = ['completed'], ?string $after_date = null): int {
        if (is_array($after_date)) {
            Debug::log('UPOZORENJE: $after_date je array, resetiram na current_time()', $after_date);
            $after_date = current_time('mysql');
        }

        $date_filter = $after_date ? self::build_date_filter($after_date) : null;

        $orders = self::query_orders([
            'status'       => $statuses,
            'orderby'      => 'date',
            'order'        => 'DESC',
            'limit'        => 1,
            'return'       => 'ids',
            'date_created' => $date_filter,
        ]);

        return !empty($orders) ? (int) $orders[0] : 0;
    }

    /**
     * Dohvati ID prve narudžbe od zadanog datuma nadalje.
     *
     * @param string|null $after_date Datum u formatu 'Y-m-d H:i:s'. Ako nije postavljen, dohvaća sve.
     * @return int ID prve pronađene narudžbe ili 0 ako ne postoji.
     */
    public static function get_first_order_id(?string $after_date = null): int {
        $orders = self::query_orders([
            'orderby'      => 'date',
            'order'        => 'ASC',
            'limit'        => 1,
            'return'       => 'ids',
            'date_created' => self::build_date_filter($after_date),
        ]);

        Debug::log('Dohvaćena prva narudžba od zadanog datuma.', ['after_date' => $after_date]);

        return !empty($orders) ? (int) $orders[0] : 0;
    }

}
