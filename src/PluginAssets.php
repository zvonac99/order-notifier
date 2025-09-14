<?php
/**
 * Klasa PluginAssets upravlja registracijom i učitavanjem
 * JavaScript i CSS datoteka za Order Notifier plugin.
 * Također omogućuje emitiranje podataka prema JavaScriptu, uključujući
 * konfiguraciju REST endpointa i nonce vrijednosti, nužnih za sigurnu komunikaciju.
 */

namespace OrderNotifier;

use OrderNotifier\Helpers\ScreenHelper;
use OrderNotifier\Utils\Debug;
use OrderNotifier\Utils\Constants;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PluginAssets {

    /**
     * Glavna metoda koja određuje hoće li se skripte i stilovi učitati.
     * Provodi se provjera konteksta (samo admin), te session screen ID-a i postavki plugina.
     */
    public function enqueue_notifier_script_assets( ) {
        // Uvijek učitaj za plugin postavke
        $this->enque_settings_assets();

        // Ovdje eventualno zadrži logiku za WC narudžbe
        $options = get_option(Constants::ON_PLUGIN_SETTINGS, []);
        $scope   = $options['scope'] ?? 'orders_only';
        Debug::log("Provjera gdje Učitavam skripte za notifikacije (scope={$scope})");

        if (
            $scope === 'everywhere' ||
            ( $scope === 'orders_only' && ScreenHelper::is_order_page_screen() )
        ) {
            $this->enqueue_order_scripts();
            $this->enqueue_order_styles();
            Debug::log("Učitavam skripte za notifikacije (scope={$scope})");
        }
    }

    protected function enque_settings_assets () {
        // Uvijek učitaj za plugin postavke
        $this->enqueue_settings_scripts();
        $this->enqueue_settings_styles();
        
        wp_add_inline_script(
            'select2-js',
            'jQuery(document).ready(function($){ $(".order-notifier-select2").select2(); });'
        );

        Debug::log("Učitavam uvijek skripte za postavke/debug stranice");
    }
    
    protected function enqueue_settings_scripts() {
        wp_enqueue_script(
            'order-notifier-settings-js',
            ORDER_NOTIFIER_URL . 'assets/js/order-notifier-settings.js',
            ['jquery'],
            null,
            true
        );
        // Lokalno učitavanje select2 skripte
        wp_enqueue_script(
            'select2-js',
            ORDER_NOTIFIER_URL . 'assets/select2/js/select2.js',
            ['jquery'],
            null,
            true
        );

        wp_localize_script('order-notifier-settings-js', 'notifierData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('order_notifier_logs')
        ]);

    }
    
    protected function enqueue_settings_styles() {
        wp_enqueue_style(
            'order-notifier-settings-css',
            ORDER_NOTIFIER_URL . 'assets/css/order-notifier-settings.css',
            [],
            null
        );
        wp_enqueue_style(
            'select2-css',
             ORDER_NOTIFIER_URL . 'assets/select2/css/select2.css',
            [],
            null
        );
    }
    

    /**
     * Ispisuje inline JavaScript s konfiguracijskim podacima (REST endpoint + nonce),
     * i postavlja ih u `globalThis.OrderNotifierData`.
     *
     * ❗ Zašto se koristi ovako:
     * `wp_add_inline_script()` nije pouzdan za `type="module"` skripte u WP 6.5+,
     * jer inline skripte ne dobiju uvijek prioritet nad ESM importima.
     * Ovim pristupom, konfiguracija je garantirano dostupna *prije* nego što je importaju moduli.
     */
    public function output_config_inline(): void {
        $config_data = [
            'endpoint' => rest_url( Constants::REST_NAMESPACE . '/' . Constants::REST_ROUTE ),
            'nonce'    => wp_create_nonce( 'wp_rest' ),
        ];

        $script = sprintf(
            'globalThis.OrderNotifierData = %s;',
            wp_json_encode( $config_data )
        );

        echo wp_print_inline_script_tag( $script, [ 'type' => 'text/javascript' ] );
    }

    // ================================
    // 2. Učitavanje JavaScript modula i stilova
    // ================================

    /**
     * Registrira i učitava sve potrebne ESM (JavaScript module) skripte.
     * Koristi `wp_register_script_module()` i `wp_enqueue_script_module()`.
     * Modul `custom.js` koristi helper module:
     * - `BroadcastChannelHandler`
     * - `storage-utils`
     * - `config` (koji exporta globalnu `OrderNotifierData`)
     */
    protected function enqueue_order_scripts() {
        // Učitavanje notifiera (klasični JS, nije modul)
        wp_enqueue_script(
            'notifier',
            ORDER_NOTIFIER_URL . 'assets/notifier/js/notifier.js',
            [],
            null,
            true
        );

        // Registracija helper modula
        wp_register_script_module(
            '@order-notifier/config',
            ORDER_NOTIFIER_URL . 'assets/js/OrderNotifierConfig.js'
        );

        wp_register_script_module(
            '@order-notifier/BroadcastChannelHandler',
            ORDER_NOTIFIER_URL . 'assets/js/BroadcastChannelHandler.js'
        );

        wp_register_script_module(
            '@order-notifier/storage-utils',
            ORDER_NOTIFIER_URL . 'assets/js/storage-utils.js'
        );

        // Glavni ESM modul koji koristi sve prethodne kao dependency
        wp_enqueue_script_module(
            'order-notifier-main',
            ORDER_NOTIFIER_URL . 'assets/js/custom.js',
            [
                ['id' => '@order-notifier/BroadcastChannelHandler', 'import' => 'static'],
                // ['id' => '@order-notifier/storage-utils', 'import' => 'static'],
                ['id' => '@order-notifier/config', 'import' => 'static'],
            ]
        );

        Debug::log("Učitani moduli i skripte za SSE i notifikacije");
    }

    /**
     * Registrira i učitava potrebne CSS datoteke za prikaz notifikacija i oznaka.
     */
    protected function enqueue_order_styles() {
        wp_enqueue_style(
            'notifier',
            ORDER_NOTIFIER_URL . 'assets/notifier/css/notifier.css',
            [],
            null
        );

        wp_enqueue_style(
            'order-notifier',
            ORDER_NOTIFIER_URL . 'assets/css/order-notifier.css',
            [],
            null
        );

        Debug::log("Notifikacijski Hook radi učitani stilovi");
    }
}
