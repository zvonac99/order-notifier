<?php
namespace OrderNotifier\Helpers;
use OrderNotifier\Utils\Debug;
use OrderNotifier\Utils\Constants;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Helper klasa za rad s kolačićima, sesijama i WordPress transijentima.
 */
class StorageHelper {

    /* ======================== USER META ======================== */

    /**
     * Postavi meta vrijednost za korisnika.
     *
     * @param int    $user_id
     * @param string $key
     * @param mixed  $value
     * @param bool   $unique 
     */
    public static function set_user_meta(int $user_id, string $key, $value, bool $unique = false): void {
        if ($unique) {
            add_user_meta($user_id, $key, maybe_serialize($value), true);
        } else {
            update_user_meta($user_id, $key, maybe_serialize($value));
        }
    }


    /**
     * Dohvati user meta vrijednost.
     *
     * @param int    $user_id
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public static function get_user_meta(int $user_id, string $key, $default = null) {
        $value = get_user_meta($user_id, $key, true);
        return $value !== '' ? maybe_unserialize($value) : $default;
    }

    /**
     * Obriši user meta.
     *
     * @param int    $user_id
     * @param string $key
     */
    public static function delete_user_meta(int $user_id, string $key): void {
        delete_user_meta($user_id, $key);
    }

    public static function cleanup_stale_user_meta(string $key, int $max_age_in_seconds): void {
        $users = get_users(['fields' => ['ID']]);
        $now = time();

        foreach ($users as $user) {
            $value = self::get_user_meta($user->ID, $key);
            if (is_array($value) && isset($value['timestamp']) && ($now - $value['timestamp']) > $max_age_in_seconds) {
                self::delete_user_meta($user->ID, $key);
                Debug::log("Obrisan zastarjeli meta podatak $key za korisnika {$user->ID}");
            }
        }
    }

    /* ======================== PLUGIN OPTIONS ======================== */

    /**
     * Dohvati vrijednost iz plugin opcija.
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public static function get_option(string $key, $default = null) {
        $options = get_option(Constants::ON_PLUGIN_SETTINGS, []);
        return $options[$key] ?? $default;
    }

    /**
     * Dohvati više vrijednosti iz opcija.
     *
     * @param array $keys Ključevi i default vrijednosti u formatu ['key' => default].
     * @return array
     */
    public static function get_options(array $keys): array {
        $options = get_option(Constants::ON_PLUGIN_SETTINGS, []);
        $result = [];
        foreach ($keys as $key => $default) {
            $result[$key] = $options[$key] ?? $default;
        }
        return $result;
    }


    /**
     * Postavi vrijednost u plugin opcije.
     *
     * @param string $key
     * @param mixed  $value
     * @return void
     */
    public static function set_option(string $key, $value): void {
        $options = get_option(Constants::ON_PLUGIN_SETTINGS, []);
        $options[$key] = $value;
        update_option(Constants::ON_PLUGIN_SETTINGS, $options);
    }

    /**
     * Dohvati bool vrijednost (checkbox friendly).
     *
     * @param string $key
     * @return bool
     */
    public static function is_option_enabled(string $key): bool {
        return self::get_option($key) === '1';
    }

    /* ======================== COOKIE ======================== */

    /**
     * Postavi kolačić.
     *
     * @param string $key
     * @param mixed  $value
     * @param int    $ttl   Trajanje u sekundama (default: 3600)
     * @param string $path  Putanja kolačića (default: '/')
     */
    public static function set_cookie( string $key, $value, int $ttl = 3600, string $path = '/' ): void {
        if ( ! headers_sent() ) {
            setcookie( $key, maybe_serialize( $value ), time() + $ttl, $path, COOKIE_DOMAIN );
            $_COOKIE[$key] = maybe_serialize( $value ); // Lokalno zrcalo
        }
    }


    /**
     * Dohvati vrijednost kolačića.
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public static function get_cookie( string $key, $default = null ) {
        return isset( $_COOKIE[$key] ) ? maybe_unserialize( $_COOKIE[$key] ) : $default;
    }

    /**
     * Obriši kolačić.
     *
     * @param string $key
     */
    public static function delete_cookie( string $key ): void {
        if ( ! headers_sent() ) {
            setcookie( $key, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN );
            unset( $_COOKIE[$key] );
        }
    }

    /* ======================== COOKIE (JSON BUNDLE) ======================== */

    /**
     * Dohvati sve podatke iz JSON kolačića (plugin_ui_state).
     *
     * @param string $cookie_name
     * @return array
     */
    public static function get_cookie_bundle(string $cookie_name = 'plugin_ui_state'): array {
    $raw = $_COOKIE[$cookie_name] ?? '{}';
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

    /**
     * Dohvati jednu vrijednost iz JSON kolačića.
     *
     * @param string $key
     * @param mixed  $default
     * @param string $cookie_name
     * @return mixed
     */
    public static function get_cookie_bundle_value(string $key, $default = null, string $cookie_name = 'plugin_ui_state') {
        $data = self::get_cookie_bundle($cookie_name);
        return $data[$key] ?? $default;
    }

    /**
     * Postavi vrijednost u JSON kolačić (plugin_ui_state).
     *
     * @param string $key
     * @param mixed  $value
     * @param int    $ttl
     * @param string $path
     * @param string $cookie_name
     */
    public static function set_cookie_bundle_value(string $key, 
                                                $value, 
                                                int $ttl = 3600, 
                                                string $path = '/', 
                                                string $cookie_name = 'plugin_ui_state'): void 
    {
        $data = self::get_cookie_bundle($cookie_name);
        $data[$key] = $value;

        if (!headers_sent()) {
            $json = json_encode($data, JSON_UNESCAPED_UNICODE);
            setcookie($cookie_name, $json, time() + $ttl, $path, COOKIE_DOMAIN);
            $_COOKIE[$cookie_name] = $json;
        }
    }


    /**
     * Obriši ključ iz JSON kolačića.
     *
     * @param string $key
     * @param string $cookie_name
     */
    public static function delete_cookie_bundle_value(string $key, string $cookie_name = 'plugin_ui_state'): void {
        $data = self::get_cookie_bundle($cookie_name);
        unset($data[$key]);

        if (!headers_sent()) {
            $json = json_encode($data, JSON_UNESCAPED_UNICODE);
            setcookie($cookie_name, $json, time() + 3600, '/', COOKIE_DOMAIN);
            $_COOKIE[$cookie_name] = $json;
        }
    }

    /**
     * Obriši cijeli JSON kolačić.
     * @param string $cookie_name
     */
    public static function delete_cookie_bundle(string $cookie_name = 'plugin_ui_state'): void {
        if (!headers_sent()) {
            setcookie($cookie_name, '', time() - 3600, '/', COOKIE_DOMAIN);
            unset($_COOKIE[$cookie_name]);
        }
    }


    /* ======================== SESSION REPLACEMENT ======================== */
    
    /**
     * Postavi vrijednost u "session".
     * Ako postoji WooCommerce, koristi WC()->session.
     * Inače koristi kolačiće.
     *
     * @param string   $key
     * @param mixed    $value
     * @param int|null $ttl  Trajanje u sekundama (TTL) – koristi se samo za kolačiće.
     */
    public static function set_session(string $key, $value, ?int $ttl = null): void {
        if ( function_exists('WC') && WC()->session ) {
            WC()->session->set($key, $value);
        } else {
            // Fallback na cookie ako nema WC session
            setcookie($key, maybe_serialize($value), [
                'expires'  => $ttl ? time() + $ttl : 0,
                'path'     => COOKIEPATH,
                'domain'   => COOKIE_DOMAIN,
                'secure'   => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            // Za trenutni zahtjev (da ne treba refreš stranice)
            $_COOKIE[$key] = maybe_serialize($value);
        }
    }

    /**
     * Dohvati vrijednost iz "sessiona".
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public static function get_session(string $key, $default = null) {
        if ( function_exists('WC') && WC()->session ) {
            return WC()->session->get($key, $default);
        }

        if ( isset($_COOKIE[$key]) ) {
            return maybe_unserialize($_COOKIE[$key]);
        }

        return $default;
    }

    /**
     * Obriši ključ iz "sessiona".
     *
     * @param string $key
     */
    public static function delete_session(string $key): void {
        if ( function_exists('WC') && WC()->session ) {
            WC()->session->__unset($key);
        } else {
            setcookie($key, '', [
                'expires'  => time() - 3600,
                'path'     => COOKIEPATH,
                'domain'   => COOKIE_DOMAIN,
                'secure'   => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            unset($_COOKIE[$key]);
        }
    }


    
    /* ======================== TRANSIENT ======================== */

    /**
     * Postavi transient.
     *
     * @param string $key
     * @param mixed  $value
     * @param int    $ttl
     */
    public static function set_transient( string $key, $value, int $ttl = 3600 ): void {
        set_transient( $key, $value, $ttl );
    }

    /**
     * Dohvati transient.
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public static function get_transient( string $key, $default = null ) {
        $value = get_transient( $key );
        return false === $value ? $default : $value;
    }

    /**
     * Obriši transient.
     *
     * @param string $key
     */
    public static function delete_transient( string $key ): void {
        delete_transient( $key );
    }


    /* ======================== JSON BUFFER ======================== */
    /**
     * Dohvati putanju do JSON datoteke.
     * 
     * @param string|null $filename Naziv JSON datoteke (ako nije defaultni 'sse-buffer.json')
     * @return string
     */
    private static function get_json_path(?string $filename = null): string {
        $upload_dir = wp_upload_dir();
        $dir = trailingslashit($upload_dir['basedir']) . 'order-notifier';

        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }

        $file = $filename ?? 'sse-buffer.json';
        return trailingslashit($dir) . $file;
    }
    
    /**
     * Dohvati podatke iz JSON datoteke.
     *
     * @param string|null $filename Naziv JSON datoteke (ako nije defaultni 'sse-buffer.json')
     * @return array|null
     */
    public static function get_json_buffer(?string $filename = null): ?array {
        $path = self::get_json_path($filename);

        if (!file_exists($path)) {
            return null;
        }

        $data = json_decode(file_get_contents($path), true);
        return is_array($data) ? $data : null;
    }



    /**
     * Spremi podatke u JSON datoteku.
     *
     * @param array $data
     * @param string|null $filename Naziv JSON datoteke (ako nije defaultni 'sse-buffer.json')
     * @return bool
     */
    public static function set_json_buffer(array $data, ?string $filename = null): bool {
        $path = self::get_json_path($filename);
        return (bool) file_put_contents($path, json_encode($data), LOCK_EX);
    }


    /**
     * Očisti sadržaj JSON buffer datoteke.
     * @param string|null $filename Naziv JSON datoteke (ako nije defaultni 'sse-buffer.json')
     */
    public static function reset_json_buffer(?string $filename = null): void {
        $path = self::get_json_path($filename);
        file_put_contents($path, '{}', LOCK_EX);
    }


    /**
     * Označi event u JSON bufferu kao procesiran za zadanu korisničku ulogu.
     *
     * @param string $event_uid Jedinstveni ID eventa (event_uid)
     * @param string $role      Uloga korisnika (npr. 'admin', 'shop_manager')
     * @param string|null $filename Naziv JSON datoteke (ako nije defaultni 'sse-buffer.json')
     */
    public static function mark_event_processed_for_role(string $event_uid, string $role, ?string $filename = null): void {
        $data = self::get_json_buffer($filename);

        if (!is_array($data) || empty($data['events'])) {
            return;
        }

        foreach ($data['events'] as &$event) {
            if (isset($event['event_uid']) && $event['event_uid'] === $event_uid) {
                if (!isset($event['is_processed']) || !is_array($event['is_processed'])) {
                    $event['is_processed'] = [];
                }
                $event['is_processed'][$role] = true;
                break;
            }
        }

        self::set_json_buffer($data, $filename);
    }


    /**
     * Očisti obrađene događaje iz JSON buffera starije od zadanog broja dana.
     * 
     * Brišu se samo događaji koji su stariji od $days i za koje su svi korisnici 
     * označili da su ih obradili (u polju 'is_processed').
     * Ako događaj nema polje 'is_processed', također se briše ako je stariji od $days.
     * 
     * @param string|null $filename Naziv JSON datoteke (ako nije defaultni 'sse-buffer.json')
     * 
     * @return void
     */
    public static function cleanup_processed_events(?string $filename = null): void {
        $buffer = self::get_json_buffer($filename);

        if (!is_array($buffer) || empty($buffer['events'])) {
            return;
        }

        $now = time();
        $cutoff = $now - 14 * 24 * 3600; // 14 dana

        $buffer['events'] = array_filter($buffer['events'], function ($event) use ($cutoff) {
            if (isset($event['timestamp']) && $event['timestamp'] < $cutoff) {
                if (isset($event['is_processed']) && is_array($event['is_processed'])) {
                    foreach ($event['is_processed'] as $processed) {
                        if (!$processed) {
                            return true;
                        }
                    }
                    return false;
                }
                return false;
            }
            return true;
        });

        self::set_json_buffer($buffer, $filename);
        Debug::log("Cleanup: uklonjeni stari obrađeni event-i stariji od 14 dana.");
    }


}
