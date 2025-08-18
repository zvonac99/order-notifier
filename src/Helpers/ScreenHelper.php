<?php
namespace OrderNotifier\Helpers;

use OrderNotifier\Utils\Debug;
use OrderNotifier\Helpers\StorageHelper;
use OrderNotifier\Utils\Constants;

/**
 * Helper klasa za rad s WordPress screen ID-om u admin sučelju.
 *
 * Koristi statički pristup za cacheiranje trenutnog screen ID-a
 */
class ScreenHelper {

    /**
     * Spremljeni screen ID (npr. 'edit-shop_order', 'dashboard' itd.)
     *
     * @var string|null
     */
    protected static ?string $screen_id = null;

    /**
     * Hvata trenutni screen ID (putem WordPress hooka 'current_screen').
     * Sprema ga u WC sessiju i transient kao fallback (hook get_new_order)
     *
     * @param \WP_Screen $screen
     */
    public static function capture_screen($screen): void {
        if (!($screen instanceof \WP_Screen)) {
            return;
        }

        // Dohvati kod za screen ID; ako ne postoji, postavi na 0 (SCREEN_NONE)
        $screen_code = Constants::SCREEN_CODES[$screen->id] ?? Constants::SCREEN_CODES[Constants::SCREEN_NONE];

        if ($screen_code !== null) {
            StorageHelper::set_session(Constants::SESSION_SCREEN_ID, $screen_code);
            StorageHelper::set_transient(Constants::SESSION_SCREEN_ID, $screen_code);
            Debug::log("Screen ID '{$screen->id}' spremljen kao kod: {$screen_code}.");
        } else {
            Debug::log("Nepoznat screen ID '{$screen->id}', nije spremljen.");
        }

        self::$screen_id = $screen->id;
    }


    /**
     * Dohvati screen ID, uključujući automatsku detekciju ako nije već spremljen.
     * Ako screen ID nije pronađen u sesiji, pokušava dohvatiti iz transienta kao fallback.
     *
     * @return string|null
     */

    public static function get_screen_id(): ?string {
        if (self::$screen_id !== null) {
            return self::$screen_id;
        }

        $screen_code = self::get_screen_code_from_session() ?? self::get_screen_code_from_transient();

        if ($screen_code !== null) {
            self::$screen_id = Constants::SCREEN_CODES_REVERSE[$screen_code] ?? null;

            if (self::$screen_id) {
                Debug::log("Screen ID dekodiran iz koda ({$screen_code}): " . self::$screen_id);
                return self::$screen_id;
            }
        }

        Debug::log('Upozorenje: screen_id nije moguće dekodirati iz sesije/transienta.');
        return null;
    }

    private static function get_screen_code_from_session(): ?int {
        $code = StorageHelper::get_session(Constants::SESSION_SCREEN_ID);
        return is_int($code) ? $code : null;
    }

    private static function get_screen_code_from_transient(): ?int {
        $code = StorageHelper::get_transient(Constants::SESSION_SCREEN_ID);
        if (is_int($code)) {
            return $code;
        }

        // Fallback: pokušaj iz sessije
        $code = StorageHelper::get_session(Constants::SESSION_SCREEN_ID);
        if (is_int($code)) {
            // Ponovno postavi u transient za sljedeće pozive
            StorageHelper::set_transient(Constants::SESSION_SCREEN_ID, $code);
            return $code;
        }

        return null;
    }


    /**
     * Provjera jesmo li na listi narudžbi.
     *
     * @return bool
     */
    public static function is_order_page_screen(): bool {
        return self::get_screen_id() === Constants::WC_ORDER_PAGE_SCREEN;
    }

    /**
     * Provjera jesmo li na WooCommerce dashboardu.
     *
     * @return bool
     */
    public static function is_woocommerce_dashboard(): bool {
        return self::get_screen_id() === Constants::WC_DASHBOARD_SCREEN;
    }

    /**
     * Provjera jesmo li na WordPress početnom dashboardu.
     *
     * @return bool
     */
    public static function is_wp_dashboard(): bool {
        return self::get_screen_id() === Constants::WP_DASHBOARD_SCREEN;
    }
}
