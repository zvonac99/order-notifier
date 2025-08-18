<?php
/**
 * @package Order_Notifier
 * @subpackage Helpers
 * @since 2.0.0
 */

namespace OrderNotifier\Helpers;

use WP_User;
use OrderNotifier\Helpers\StorageHelper;
use OrderNotifier\Utils\Debug;
use OrderNotifier\Utils\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Klasa UserHelper
 *
 * Pruža pomoćne metode za rad s korisnicima i korisničkim kontekstom.
 *
 * @since 2.0.0
 */
class UserHelper {

	/**
	 * Dohvaća kontekst trenutno prijavljenog korisnika.
	 * Interno koristi funkciju get_user_context() s WP_User objektom trenutno prijavljenog korisnika.
	 *
	 * @return array{
	 *     user_id: int|null,     // ID korisnika ili null ako nije prijavljen
	 *     username: string,      // Korisničko ime ili 'guest'
	 *     role: string,          // Primarna uloga korisnika ili 'guest'
	 *     authorized: bool       // Je li korisnik u autoriziranim ulogama (Constants::USER_ROLES)
	 * }
	 */
	public static function get_current_user_context(): array {
		$user = wp_get_current_user();
		return self::get_user_context($user);
	}

	/**
	 * Dohvaća kontekst korisnika po korisničkom imenu (loginu).
	 * Interno koristi funkciju get_user_context() s WP_User objektom korisnika dohvaćenim preko korisničkog imena.
	 *
	 * @param string $username Korisničko ime (login)
	 * @return array{
	 *     user_id: int|null,
	 *     username: string,
	 *     role: string,
	 *     authorized: bool
	 * }
	 */
	public static function get_user_context_by_username(string $username): array {
		$user = get_user_by('login', $username);
		return self::get_user_context($user);
	}

	/**
	 * Dohvaća kontekst korisnika po njegovom ID-u.
	 * Interno koristi funkciju get_user_context() s WP_User objektom dohvaćenim preko ID-a.
	 *
	 * @param int $user_id ID korisnika
	 * @return array{
	 *     user_id: int|null,
	 *     username: string,
	 *     role: string,
	 *     authorized: bool
	 * }
	 */
	public static function get_user_context_by_id(int $user_id): array {
		$user = get_user_by('id', $user_id);
		return self::get_user_context($user);
	}

	/**
	 * Dohvaća kontekst korisnika po WP_User objektu.
	 * Autorizacija korisnika je zadana u postavkama plugina.
	 * Ako je korisnik nepostojeći ili nije prijavljen, vraća defaultne vrijednosti za 'guest'.
	 *
	 * @param \WP_User|null $user WP_User objekt korisnika ili null
	 * @return array{
	 *     user_id: int|null,
	 *     username: string,
	 *     role: string,
	 *     authorized: bool
	 * }
	 */
	public static function get_user_context(?\WP_User $user): array {
		if (!$user instanceof \WP_User || empty($user->ID)) {
			return [
				'user_id'    => null,
				'username'   => 'guest',
				'role'       => 'guest',
				'authorized' => false,
			];
		}
		$username = $user->user_login ?: 'guest';
		$role = !empty($user->roles) ? $user->roles[0] : 'guest';
		$allowed_roles = Constants::USER_ROLES; // 'administrator', 'shop_manager'

		$authorized = in_array($role, $allowed_roles, true);

		return [
			'user_id'    => (int) $user->ID,
			'username'   => $username,
			'role'       => $role,
			'authorized' => $authorized,
		];
	}

    /**
     * Očisti spremljeni kontekst korisnika.
     *
     * @return void
     */
    public static function clear_user_context(): void {
        // StorageHelper::delete_user_meta($user_id, Constants::USER_CONTEXT_KEY);
		// StorageHelper::set_session(Constants::SESSION_SCREEN_ID, 0);
		 StorageHelper::delete_transient(Constants::ON_TRANSIENT_HOOKS);
		StorageHelper::delete_transient(Constants::SESSION_SCREEN_ID);
		Debug::log("Korisnik odjavljen - screen ID izbrisan iz sesije i transienta (via wp_logout).");
    }

}
