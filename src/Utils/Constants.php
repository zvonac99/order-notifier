<?php
namespace OrderNotifier\Utils;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Centralna klasa za definiranje konstantnih vrijednosti plugina
 */
class Constants {

    /** 
     * Uloge korisnika koje koriste eventi ili prava pristupa
     * @var string[]
     */
    public const USER_ROLES                     = ['administrator', 'shop_manager'];

    /** Text domain */
    public const ON_TEXT_DOMAIN                 = 'order-notifier';

    // Slug stranice postavki
    public const SETTINGS_PAGE_SLUG             = 'order-notifier-settings';
    // Slug stranice debug loga
    public const DEBUG_PAGE_SLUG                = 'order-notifier-debug';

    /** ---------------------
     *  REST API
     * ---------------------- */
    public const REST_NONCE_ACTION              = 'wp_rest';
    public const REST_NAMESPACE                 = 'order-notifier/v1';
    public const REST_ROUTE                     = 'stream';

    /** ---------------------
     *  Option i meta ključevi
     * ---------------------- */
    public const ON_PLUGIN_INSTALLED            = 'order_notifier_installed_at';
    // Settings option polje
    public const ON_PLUGIN_SETTINGS             = 'order_notifier_settings';
    // Settings filter
    public const ON_PLUGIN_SETTINGS_HOOK        = 'order_notifier_settings_fields';
    public const ON_PLUGIN_VERSION              = 'order_notifier_version';
    public const ON_PLUGIN_ACTIVATED            = 'order_notifier_activated';
    // Options ključ za registrirane akcije i filtere
    public const ON_OPTION_HOOKS                = 'order_notifier_registered_hooks';
    // Meta key
    public const ON_USER_CONTEXT_KEY            = 'order_notifier_user_context';

    // Mapa kodova za screen Id
    public const SCREEN_CODES                   = [
                                                    self::SCREEN_NONE            => 0,
                                                    self::WC_ORDER_PAGE_SCREEN   => 1,
                                                    self::WC_DASHBOARD_SCREEN    => 2,
                                                    self::WP_DASHBOARD_SCREEN    => 3,
                                                ];

    public const SCREEN_CODES_REVERSE           = [
                                                    0 => self::SCREEN_NONE,
                                                    1 => self::WC_ORDER_PAGE_SCREEN,
                                                    2 => self::WC_DASHBOARD_SCREEN,
                                                    3 => self::WP_DASHBOARD_SCREEN,
                                                ];

    /** ---------------------
     *  WooCommerce screen ID-evi
     * ---------------------- */
    public const WC_ORDER_PAGE_SCREEN           = 'woocommerce_page_wc-orders';
    public const WC_DASHBOARD_SCREEN            = 'woocommerce_page_wc-admin';

    /** ---------------------
     *  WordPress screen ID-evi
     * ---------------------- */
    public const WP_DASHBOARD_SCREEN            = 'dashboard';

    /**
     * Screen Id za odjavu
     */
    public const SCREEN_NONE                    = 'none';

    /** ---------------------
     *  Transient ključevi
     * ---------------------- */
    public const GENERAL_TRANSIENT              = 'order_notifier_transient';
    public const TRANSIENT_NEW_ORDERS           = 'order_sse_new';
    public const TRANSIENT_STATUS_ORDERS        = 'order_sse_status';
    public const TRANSIENT_ACTIVE_COUNT         = 'order_sse_active_count';
    // Transient ključ za spremanje izvršenih akcija/filtera
    public const ON_TRANSIENT_HOOKS             = 'on_executed_hooks';

    /** ---------------------
     *  Session ključevi
     * ---------------------- */
    public const SESSION_SCREEN_ID              = 'on_ctx'; // Kod za prepoznavanje prikazanog WP/WC screena

}
