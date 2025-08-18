<?php

/*
 * Ova klasa upravlja svim postavkama, uključujući registraciju i prikaz u WooCommerce settings API. 
 * Ovdje ćeš moći koristiti već predloženi pristup s registracijom postavki.
 * 
 * This class manages all settings, including registration and display in the WooCommerce settings API.
 * Here, you will use the previously suggested approach for settings registration.
 * Version 2.0.0
 */
namespace OrderNotifier\Admin;
use OrderNotifier\Utils\Debug;
use OrderNotifier\Utils\Constants;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DebugPage {

    public function add_debug_log_page() {
        if ( defined( 'ORDER_NOTIFIER_DEBUG' ) && ORDER_NOTIFIER_DEBUG ) {
            Debug::log("Dodana debug stranica");
            add_submenu_page(
                Constants::ON_TEXT_DOMAIN,
                __( 'Debug Log', Constants::ON_TEXT_DOMAIN ),
                __( 'Debug Log', Constants::ON_TEXT_DOMAIN ),
                'manage_woocommerce',
                Constants::DEBUG_PAGE_SLUG,
                [ $this, 'render_debug_log_page' ]
            );
        }

    }    
    
    public function render_debug_log_page() {

        Debug::log( "Prikazana debug stranica" );
        $log_contents   = Debug::get_log_contents();
        $archived_logs  = Debug::get_archived_log_file_names();
        ?>
        <div class="wrap">
            <?php settings_errors( 'order_notifier_messages' ); ?>

            <h1><?php _e( 'Debug Log', Constants::ON_TEXT_DOMAIN ); ?></h1>

            <h2><?php _e( 'Archived Logs', Constants::ON_TEXT_DOMAIN ); ?></h2>
            <p>
                <label for="archived-log-select"><strong><?php _e( 'Select archived log:', Constants::ON_TEXT_DOMAIN ); ?></strong></label><br>
                <select id="archived-log-select">
                    <option value=""><?php _e( '-- Select log file --', Constants::ON_TEXT_DOMAIN ); ?></option>
                    <?php foreach ( $archived_logs as $log_file ) : ?>
                        <option value="<?php echo esc_attr( $log_file ); ?>"><?php echo esc_html( $log_file ); ?></option>
                    <?php endforeach; ?>
                </select>
                <button id="load-log" class="button"><?php _e( 'Load', Constants::ON_TEXT_DOMAIN ); ?></button>
                <button id="delete-log" class="button"><?php _e( 'Delete', Constants::ON_TEXT_DOMAIN ); ?></button>
            </p>

            <h2><?php _e( 'Current Log', Constants::ON_TEXT_DOMAIN ); ?></h2>
            
            <div id="log-output">
                <?php echo esc_html( $log_contents ); ?>
            </div>
            
            <div id="log-btn-grup">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page='. Constants::SETTINGS_PAGE_SLUG ) ); ?>" class="button"><?php esc_html_e( 'Back to settings', Constants::ON_TEXT_DOMAIN ); ?></a>
                <button id="delete-current-log" class="button button-secondary"><?php _e( 'Delete current log', Constants::ON_TEXT_DOMAIN ); ?></button>
                <button id="refresh-current-log" class="button"><?php _e( 'Refresh Log', Constants::ON_TEXT_DOMAIN ); ?></button>
            </div>


        </div>
        <?php
    }

    
    public static function ajax_delete_current_log() {
        check_ajax_referer( 'order_notifier_logs');

        if ( Debug::clear_log() ) {
            wp_send_json_success();
        } else {
            wp_send_json_error( [ 'message' => __('Failed to delete log.', Constants::ON_TEXT_DOMAIN ) ] );
        }
    }

    public static function ajax_get_current_log() {
        check_ajax_referer( 'order_notifier_logs' );

        $log_contents = Debug::get_log_contents();

        wp_send_json_success( [ 'log' => $log_contents ] );

    }


    public static function ajax_load_archived_log() {
        check_ajax_referer('order_notifier_logs');

        if ( empty( $_POST['file'] ) ) {
            wp_die( __( 'The log file name is missing.' , Constants::ON_TEXT_DOMAIN ));
        }

        $file = basename( sanitize_file_name( $_POST['file'] ) );
        $archived_files = Debug::get_archived_log_file_names();

        if ( ! in_array( $file, $archived_files, true ) ) {
            wp_die( __( 'The file does not exist or is not an archived log file.', Constants::ON_TEXT_DOMAIN ));
        }

        $path = dirname( Debug::get_log_file_path() ) . '/' . $file;

        if ( ! file_exists( $path ) ) {
            wp_die( __( 'The file does not exist.' , Constants::ON_TEXT_DOMAIN ));
        }

        echo file_get_contents( $path );
        wp_die();
    }


    public static function ajax_delete_archived_log() {
        check_ajax_referer('order_notifier_logs');

        if ( empty( $_POST['file'] ) ) {
            wp_send_json_error( __( 'The log file name is missing.' , Constants::ON_TEXT_DOMAIN ));
        }

        $file = basename( sanitize_file_name( $_POST['file'] ) );
        $path = dirname( Debug::get_log_file_path() ) . '/' . $file;

        if ( ! file_exists( $path ) ) {
            wp_send_json_error( __( 'The file does not exist.' , Constants::ON_TEXT_DOMAIN ));
        }

        if ( unlink( $path ) ) {
            wp_send_json_success();
        } else {
            wp_send_json_error( __( 'Delete failed.' , Constants::ON_TEXT_DOMAIN ));
        }
    }

}

