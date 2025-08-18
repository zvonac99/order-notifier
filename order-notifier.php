<?php
/**
 * Plugin Name:       Order Notifier
 * Requires Plugins:  woocommerce
 * Plugin URI:        https://github/zvonac99/order-notifier
 * Description:       Notifikacije o novim narudžbama za WooCommerce.
 * Version:           2.0
 * Author:            zvonac99
 * License:           GPL2
 * Text Domain:       order-notifier
 * Domain Path:       /languages
 */

 if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

 // Definicija root direktorija plugina
if ( ! defined('ORDER_NOTIFIER_DIR') ) {
    define('ORDER_NOTIFIER_DIR', plugin_dir_path(__FILE__));
}

 // Definicija root URL-a plugina
if ( ! defined('ORDER_NOTIFIER_URL') ) {
    define('ORDER_NOTIFIER_URL', plugin_dir_url(__FILE__));
}

// Verzija plugina
define( 'ORDER_NOTIFIER_VERSION', '2.0' );

// Debug mod, konstanta definira dali će se log pratiti ili ne.
if ( ! defined( 'ORDER_NOTIFIER_DEBUG' ) ) {
	define( 'ORDER_NOTIFIER_DEBUG', true );
}

// Učitavamo Debug klasu
require_once ORDER_NOTIFIER_DIR . 'src/Utils/Debug.php';

// Učitaj autoloader
require_once ORDER_NOTIFIER_DIR . 'src/Autoloader.php';

$autoloader = new \OrderNotifier\Autoloader();
$autoloader->addNamespace('OrderNotifier', ORDER_NOTIFIER_DIR . 'src');
$autoloader->addNamespace('EliasHaeussler\\SSE', ORDER_NOTIFIER_DIR . 'includes/EliasHaeussler/SSE');
$autoloader->register();

use OrderNotifier\PluginCore;
use OrderNotifier\Lifecycle;
use OrderNotifier\Utils\Debug;

// Provjera je li WooCommerce aktivan
function do_is_woocommerce_active() {
	return in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) );
}

// Admin obavijest (nije potrebna u ovom slučaju)
function order_notifier_woocommerce_missing_notice() {
	echo '<div class="error"><p><strong>' . order_notifier_wc_missing_message() . '</strong></p></div>';
}

// Poruka ako WooCommerce nije aktivan
function order_notifier_wc_missing_message() {
	\OrderNotifier\i18n\Locale::load_plugin_textdomain();
	return esc_html__( 'Order Notifier requires WooCommerce to be installed and active.', 'order-notifier' );
}

// Deklaracija podrške za HPOS
add_action( 'before_woocommerce_init', function() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

// Aktivacija plugina
function activate_order_notifier() {
	if ( ! do_is_woocommerce_active() ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die(
			order_notifier_wc_missing_message(),
			esc_html__( 'WooCommerce is required for activation.', 'order-notifier' ),
			[ 'back_link' => true ]
		);
	}

	Lifecycle::activate();
	Debug::log("Plugin aktiviran");
}

// Deaktivacija plugina
function deactivate_order_notifier() {
	Lifecycle::deactivate();
	Debug::log("Plugin deaktiviran");
}

// Deinstalacija plugina
function uninstall_order_notifier() {
	Lifecycle::uninstall();
}

register_activation_hook( __FILE__, 'activate_order_notifier' );
register_deactivation_hook( __FILE__, 'deactivate_order_notifier' );
register_uninstall_hook( __FILE__, 'uninstall_order_notifier' );

// Nadogradnja plugina
add_action( 'admin_init', function () {
	Lifecycle::maybe_upgrade();
} );


// Pokreni plugin
add_action( 'plugins_loaded', 'run_order_notifier_lazy', 10 );

function run_order_notifier_lazy() {
	if ( do_is_woocommerce_active() ) {
		(new PluginCore())->run();
	}
}
