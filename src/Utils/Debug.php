<?php
/**
 * Klasa za debugiranje unutar WordPress/WooCommerce plugina.
 * Omogućava logiranje poruka s dodatnim informacijama o mjestu poziva,
 * uključujući naziv klase i funkcije. 
 *
 * Funkcionalnosti:
 * - Logiranje s opcionalnom dodatnom varijablom
 * - Prikaz pozivne klase i funkcije
 * - Rotacija logova ako datoteka postane prevelika
 * - Automatsko brisanje najstarijih arhiviranih logova
 * - Pregled i brisanje log datoteke
 */
/**
  * // Standardno (dubina 3)
  * Order_Notifier_Debug::log( 'Nova narudžba', [ 'order_id' => 123 ] );
  * 
  * // Ako želiš ići dublje u stack (npr. kada log poziva neka wrapper funkcija)ž
  * Order_Notifier_Debug::log( 'Duboko u call stacku', null, 4 );
  */

namespace OrderNotifier\Utils;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Debug {

    const LOG_FILE              = 'ON_debug.log';               // Glavni log
    const LOG_ROTATED_PREFIX    = 'ON_debug-archived';          // Prefix za rotirane logove
    const MAX_LOG_FILE_SIZE_MB  = 5;                            // Maksimalna veličina log datoteke (MB)
    const MAX_ROTATED_LOGS      = 10;                           // Maksimalan broj arhiviranih logova

    /**
     * Glavna metoda za logiranje poruke.
     *
     * @param mixed  $message  Poruka (string, array, objekt...).
     * @param mixed  $variable Dodatna varijabla za log.
     * @param int    $depth    Dubina za backtrace (default 3).
     */
    public static function log( $message, $variable = null, $depth = 3 ) {
        if ( ! defined( 'ORDER_NOTIFIER_DEBUG' ) || ! ORDER_NOTIFIER_DEBUG ) {
            return;
        }

        // Osiguraj da je $message string (ako nije, pretvori)
        if ( ! is_string( $message ) ) {
            $message = print_r( $message, true );
        }

        // Dodaj dodatnu varijablu ako postoji
        if ( $variable !== null ) {
            $message .= ' | ' . self::format_variable( $variable );
        }

        $log_message = self::prepare_log_message( $message, $depth );
        self::write_log( $log_message );
    }

    /**
     * Formatiranje varijabli za logiranje.
     * Podržava sve tipove (string, array, objekt, itd).
     *
     * @param mixed $variable
     * @return string
     */
    private static function format_variable( $variable ) {
        if ( is_array( $variable ) || is_object( $variable ) ) {
            return print_r( $variable, true );
        } else {
            return var_export( $variable, true );
        }
    }

    /**
     * Priprema log poruku s vremenskom oznakom i informacijom o mjestu poziva.
     *
     * @param string $message
     * @param int    $depth
     * @return string
     */
    private static function prepare_log_message( $message, $depth = 3 ) {
        $backtrace = self::get_backtrace( $depth );
        $class     = $backtrace['class'] ?? 'Globalna funkcija';
        $function  = $backtrace['function'] ?? 'Nepoznata funkcija';
        $time      = current_time( 'mysql' );

        return sprintf( "[%s] %s - pozvano iz klase %s, funkcija %s\n", $time, $message, $class, $function );
    }

    /**
     * Dohvaća određeni sloj iz backtrace niza.
     *
     * @param int $depth
     * @return array
     */
    private static function get_backtrace( $depth = 3 ) {
        // Dohvati $depth + 1 jer je trenutna metoda jedan sloj "viška"
        $backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, $depth + 1 );
        return $backtrace[ $depth ] ?? [];
    }

    /**
     * Osigurava da direktorij za log postoji.
     */
    private static function ensure_log_directory_exists() {
        $log_file = self::get_log_file_path();
        $log_dir  = dirname( $log_file );

        if ( ! file_exists( $log_dir ) ) {
            mkdir( $log_dir, 0755, true );
        }
    }

    /**
     * Upisuje poruku u log datoteku (s rotacijom ako je prevelika).
     * @param string $log_message
     */
    private static function write_log( $log_message ) {
        self::ensure_log_directory_exists();
        $log_file = self::get_log_file_path();

        // Rotacija ako je datoteka prevelika
        if ( file_exists( $log_file ) && filesize( $log_file ) > self::MAX_LOG_FILE_SIZE_MB * 1024 * 1024 ) {
            self::rotate_log( $log_file );
            self::cleanup_old_rotated_logs(); // Čisti višak starih logova
        }

        file_put_contents( $log_file, $log_message, FILE_APPEND );
    }

    /**
     * Rotira trenutnu log datoteku u arhivu s timestampom.
     */
    private static function rotate_log( $log_file ) {
        $timestamp = date( 'Y-m-d_H-i-s' );
        $new_name  = dirname( $log_file ) . '/' . self::LOG_ROTATED_PREFIX . '-' . $timestamp . '.log';
        rename( $log_file, $new_name );
    }

    /**
     * Briše stare rotirane logove ako ih ima više od dopuštenog broja.
     */
    private static function cleanup_old_rotated_logs() {
        $log_dir = self::get_log_dir();
        $pattern = $log_dir . self::LOG_ROTATED_PREFIX . '-*.log';

        $logs = glob( $pattern );
        if ( $logs && count( $logs ) > self::MAX_ROTATED_LOGS ) {
            usort( $logs, function( $a, $b ) {
                return filemtime( $a ) <=> filemtime( $b );
            });

            $logs_to_delete = array_slice( $logs, 0, count( $logs ) - self::MAX_ROTATED_LOGS );
            foreach ( $logs_to_delete as $old_log ) {
                @unlink( $old_log );
            }
        }
    }

   /**
     * Vraća punu putanju log datoteke.
     *
     * @return string
     */
    public static function get_log_file_path() {
        return self::get_log_dir() . self::LOG_FILE;
    }

    /**
     * Vraća direktorij u kojem se nalaze sve log datoteke.
     *
     * @return string
     */
    public static function get_log_dir() {
        $upload_dir = wp_upload_dir();
        return trailingslashit( $upload_dir['basedir'] );
    }

    /**
     * Dohvaća nazive svih arhiviranih (rotiranih) log datoteka.
     *
     * Arhivirane log datoteke očekuju se u istom direktoriju kao i glavna log datoteka,
     * te imaju naziv u formatu: LOG_ROTATED_PREFIX-YYYYMMDD-HHMMSS.log.
     *
     * @return string[] Popis imena datoteka (bez pune putanje) arhiviranih logova.
     */
    public static function get_archived_log_file_names() {
        $log_dir = self::get_log_dir();
        $pattern = $log_dir . '/' . self::LOG_ROTATED_PREFIX . '-*.log';

        $files = glob( $pattern );
        if ( ! $files ) {
            return [];
        }

        return array_map( 'basename', $files );
    }



    /**
     * Vraća sadržaj trenutnog loga.
     * 
     * @return string
     */
     public static function get_log_contents() {
        $log_file = self::get_log_file_path();
        return file_exists( $log_file ) ? file_get_contents( $log_file ) : '';
    }

    /**
     * Briše trenutnu log datoteku.
     */
    public static function clear_log() {
        $log_file = self::get_log_file_path();
        if ( file_exists( $log_file ) ) {
           return unlink( $log_file );
        }
        return false;
    }

    /**
     * Briše sve log datoteke, uključujući glavni log i arhivirane logove.
     *
     * Korisno prilikom deinstalacije plugina.
     *
     * @return void
     */
    public static function delete_all_logs() {
        // Obriši glavni log
        $log_file = self::get_log_file_path();
        if ( file_exists( $log_file ) ) {
            @unlink( $log_file );
        }

        // Obriši sve arhivirane logove
        $archived_logs = glob( self::get_log_dir() . self::LOG_ROTATED_PREFIX . '-*.log' );
        if ( $archived_logs ) {
            foreach ( $archived_logs as $log ) {
                @unlink( $log );
            }
        }
    }
}