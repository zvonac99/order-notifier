<?php
/**
 * @package Order_Notifier
 * @subpackage Activation
 * @since 2.0.0
 */
namespace OrderNotifier;
use OrderNotifier\Utils\Debug;
use OrderNotifier\Admin\SettingsPage;
use OrderNotifier\Helpers\StorageHelper;
use OrderNotifier\Utils\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Klasa Lifeycle
 *
 * Rukuje aktivacijom, deaktivacijom, deinstalacijom i nadogradnjom plugina.
 *
 * @since 2.0.0
 */
class Lifecycle {

	/**
	 * Aktivacija plugina.
	 *
	 * Postavlja osnovne opcije i inicijalne podatke.
	 *
	 * @return void
	 */
	public static function activate(): void {
		update_option( Constants::ON_PLUGIN_ACTIVATED, true );
		update_option( Constants::ON_PLUGIN_INSTALLED, current_time( 'mysql' ) );
		update_option( Constants::ON_PLUGIN_VERSION, ORDER_NOTIFIER_VERSION );
		SettingsPage::get_default_settings();
		Debug::log( 'Plugin aktiviran.' );
	}

	/**
	 * Deaktivacija plugina.
	 *
	 * Briše privremene opcije ako je potrebno.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		delete_option( Constants::ON_PLUGIN_ACTIVATED );
		Debug::log( 'Plugin deaktiviran.' );
	}

	/**
	 * Deinstalacija plugina.
	 *
	 * Uklanja sve opcije i podatke koje je plugin stvorio.
	 *
	 * @return void
	 */
	public static function uninstall(): void {
		delete_option( Constants::ON_PLUGIN_ACTIVATED );
		delete_option( Constants::ON_PLUGIN_INSTALLED);
		delete_option( Constants::ON_PLUGIN_VERSION );
		delete_option( Constants::ON_PLUGIN_SETTINGS );

		// Briši meta podatke za administratore i shop managere
		self::delete_meta_for_roles(Constants::ON_USER_CONTEXT_KEY, ['administrator', 'shop_manager']);

		// Očisti sessione i transiente ako postoje
		StorageHelper::delete_session(Constants::SESSION_SCREEN_ID);
		StorageHelper::delete_transient(Constants::SESSION_SCREEN_ID);

		// Očisti eventualne logove
		Debug::delete_all_logs();
	}


	/**
	 * Nadogradnja plugina.
	 *
	 * Provjerava verziju i izvršava potrebne migracije.
	 *
	 * @return void
	 */
	public static function maybe_upgrade(): void {
		$current_version = get_option( Constants::ON_PLUGIN_VERSION );

		if ( $current_version !== ORDER_NOTIFIER_VERSION ) {
			// Ovdje bi išli migracijski koraci ako je potrebno
			update_option( Constants::ON_PLUGIN_VERSION, ORDER_NOTIFIER_VERSION );
			Debug::log( "Plugin nadograđen s verzije {$current_version} na " . ORDER_NOTIFIER_VERSION );
		}
	}

	/**
	 * Briše meta podatke za sve korisnike s određenim ulogama.
	 *
	 * @param string $meta_key Meta ključ koji treba obrisati
	 * @param array $roles Popis uloga za koje se briše meta (npr. ['administrator', 'shop_manager'])
	 */
	private static function delete_meta_for_roles(string $meta_key, array $roles): void {
		$args = [
			'role__in' => $roles,
			'fields'   => 'ID', // samo ID-ove da bude brže
			'number'   => -1,   // svi korisnici
		];
		$users = get_users($args);

		foreach ($users as $user_id) {
			delete_user_meta($user_id, $meta_key);
		}
	}

}
