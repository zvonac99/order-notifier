<?php
namespace OrderNotifier\i18n;

 class Locale {

    /**
     * Load the plugin text domain for translation.
     *
     * @since 2.0.0
     */
    public static function load_plugin_textdomain() {
        load_plugin_textdomain(
            'order-notifier',
            false,
            dirname( plugin_basename( __FILE__ ) ) . '/languages'
        );
    }
}

