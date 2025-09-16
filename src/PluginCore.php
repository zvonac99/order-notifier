<?php
/**
 * ============================================================================
 * WordPress Hookovi (Admin kontekst) — Redoslijed izvršavanja
 * ============================================================================
 * Ovo je referentna lista WordPress hookova korisnih za razumijevanje
 * redoslijeda izvođenja u admin sučelju. Koristi se za internu dokumentaciju.
 *
 * ┌────────────────────────────┐
 * │         GLOBALNO           │
 * └────────────────────────────┘
 * muplugins_loaded
 * registered_taxonomy
 * registered_post_type
 * plugins_loaded
 * sanitize_comment_cookies
 * setup_theme
 * load_textdomain            (default)
 * after_setup_theme
 * load_textdomain            (theme domain: npr. twentytwenty)
 * auth_cookie_valid
 * set_current_user
 * init
 * └─ widgets_init            (priority 1 @init)
 *    ├─ register_sidebar
 *    └─ wp_register_sidebar_widget
 * wp_default_scripts         (reference array)
 * wp_default_styles          (reference array)
 * admin_bar_init
 * add_admin_bar_menus
 * wp_loaded
 *
 * ┌────────────────────────────┐
 * │        ADMIN ONLY          │
 * └────────────────────────────┘
 * auth_cookie_valid
 * auth_redirect
 * _admin_menu                (unutarnji)
 * admin_menu
 * admin_init
 * current_screen
 * load-{$page_hook}
 * send_headers
 * pre_get_posts              (ref array)
 * posts_selection
 * wp                         (ref array)
 * admin_xml_ns               (2 puta)
 * admin_enqueue_scripts
 * admin_print_styles-{$hook_suffix}
 * admin_print_styles
 * admin_print_scripts-{$hook_suffix}
 * admin_print_scripts
 * wp_print_scripts
 * admin_head-{$hook_suffix}
 * admin_head
 * admin_menu                 (može se ponovno javiti)
 * in_admin_header
 * admin_notices
 * all_admin_notices
 * restrict_manage_posts
 * the_post                   (ref array)
 * pre_user_query             (ref array)
 * in_admin_footer
 * admin_footer
 * admin_bar_menu             (ref array)
 * wp_before_admin_bar_render
 * wp_after_admin_bar_render
 * admin_print_footer_scripts
 * admin_footer-{$hook_suffix}
 * shutdown
 * wp_dashboard_setup
 *
 * Reference:
 * https://developer.wordpress.org/apis/hooks/action-reference/
 */
/*
order-notifier.php
  └─ instancira klasu Plugin_Core i poziva run()

PluginCore::__construct()
  ├─ load_dependencies()
  ├─ register_classes()
  └─ register_hooks_from_class() → registrira sve WP hookove kroz loader

WordPress izvršava hookove u svojem redoslijedu

| Događaj                            | Izvršava se (callback)                               | Opis                                  	|
| ---------------------------------- | ---------------------------------------------------- | ----------------------------------------- |
| `plugins_loaded`                   | `Locale->load_plugin_textdomain()`                   | Učitavanje prijevoda                  	|
| `current_screen`                   | `ScreenHelper::'capture_screen'                      | Prikupljanje podataka o ekranu admina 	|
| `admin_menu`                       | `SettingsPage->add_settings_page()`                  | Dodavanje stranice postavki u adminu  	|
| `admin_menu`                       | `DebugPage->add_debug_log_page()`                    | Dodavanje debug stranice              	|
| `admin_init`                       | `SettingsPages->register_settings()`                 | Registracija postavki                 	|
| `admin_head`		    		     | `PluginBootstrapper::prepare_environment`			| Inicialna provjera prilikom prijave   	|
| `admin_footer`            		 | `PluginAsset->output_config_inline()`                | Učitavanje JS za notifikacije		    	|
| `admin_enqueue_scripts`            | `PluginAssets->enqueue_notification_sse_assets()`    | Učitavanje JS/CSS za admin i notifikacije	|
| `woocommerce_order_status_changed` | `OrderEventService::dispatch_new_order_event`        | Reakcija na promjenu statusa narudžbe 	|
| `woocommerce_new_order`            | `OrderEventService::get_new_order()`                 | Reakcija na novu narudžbu             	|
| `rest_api_init`                    | `SSE->register_rest_routes()`                        | Registracija SSE REST endpointa       	|
| `wp_logout`						 | `UserHelper::clear_user_context()`					| Po odjavi čisti korisnikov kontekst		|
*/

namespace OrderNotifier;

use OrderNotifier\HooksLoader;
use OrderNotifier\PluginAssets;
use OrderNotifier\Settings\SettingsPage;
use OrderNotifier\Settings\DebugPage;
use OrderNotifier\Helpers\ScreenHelper;
use OrderNotifier\Helpers\UserHelper;
use OrderNotifier\i18n\Locale;
use OrderNotifier\SSE\SseCore;
use OrderNotifier\Service\PluginBootstrapper;
use OrderNotifier\Service\OrderEventService;
use OrderNotifier\Utils\Debug;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PluginCore {
	/**
	 * Instanca loader klase za registraciju hookova.
	 *
	 * @var OrderNotifier\HooksLoader
	 */
	private $loader;
	private $pluginassets;
	private $settingspage;
	private $debugpage;
	private $sse;


	/**
	 * Konstruktor.
	 */
	public function __construct() {
		$this->register_classes();
		$this->register_hooks_for_all();
		Debug::log("Inicijalizirane klase i hookovi za sve");

		$context = UserHelper::get_current_user_context();
		 if ( $context['authorized'] ) {
			$this->register_hooks_from_class_once();
			$this->register_hooks_from_class();
			Debug::log("Plugin inicijalizira hookove za prijavljenog korisnika: {$context['username']} (uloga: {$context['role']})");
		 } else {
			Debug::log("Plugin neće inicijalizirati hookove — korisnik nije autoriziran (uloga: {$context['role']})");
		}
	}

	private function register_classes() {
		$this->loader       = new HooksLoader();
		$this->sse          = new SseCore();
		$this->pluginassets = new PluginAssets();
		$this->settingspage = new SettingsPage();
		$this->debugpage    = new DebugPage();

		Debug::log("Inicijalizirane sve klase");
	}


	private function register_hooks_for_all() {
		// SSE REST route
		$this->loader->add_action('rest_api_init', $this->sse, 'register_rest_routes',10,1);

		$this->loader->add_action('woocommerce_new_order', OrderEventService::class, 'dispatch_new_order_event');
		$this->loader->add_action('woocommerce_order_status_changed', OrderEventService::class, 'get_status_order');
		$this->loader->add_action('current_screen', ScreenHelper::class, 'capture_screen',11,1);
		// Admin CSS/JS (za notifikacije i stranice)
		$this->loader->add_action('admin_footer', $this->pluginassets, 'output_config_inline');
		$this->loader->add_action('admin_enqueue_scripts', $this->pluginassets, 'enqueue_notifier_script_assets');
	}

	private function register_hooks_from_class_once() {
		
		// Lokalizacija
		$this->loader->add_action_once('plugins_loaded', Locale::class, 'load_plugin_textdomain');
		
		$this->loader->add_action_once('admin_head', PluginBootstrapper::class, 'prepare_environment');

		Debug::log("Registrirani hookovi koji se registriraju samo jednom)");

	}

	private function register_hooks_from_class() {

		// Stranica postavki
		$this->loader->add_action('admin_menu', $this->settingspage, 'add_settings_page');
		$this->loader->add_action('admin_init', $this->settingspage, 'register_settings');

		// Debug stranica
		$this->loader->add_action('admin_menu', $this->debugpage, 'add_debug_log_page', 20, 1);
		$this->loader->add_action('wp_ajax_get_current_log', $this->debugpage, 'ajax_get_current_log');
		$this->loader->add_action('wp_ajax_delete_current_log', $this->debugpage, 'ajax_delete_current_log');
		$this->loader->add_action('wp_ajax_load_archived_log', $this->debugpage, 'ajax_load_archived_log');
		$this->loader->add_action('wp_ajax_delete_archived_log', $this->debugpage, 'ajax_delete_archived_log');
		
		$this->loader->add_action('wp_logout', UserHelper::class, 'clear_user_context'); // 'wp_logout', 'clear_auth_cookie'
        Debug::log("Registrirani svi ostali hookovi");
		
	}

	/**
	 * Pokreće loader da se svi hookovi učitaju.
	 */
	public function run() {
		Debug::log("Pokrenut loader");
		$this->loader->run();
	}
	

}
