<?php
/**
 * Plugin Name: WC Order Notifier
 * Description: Prikazuje obavijesti u adminu kada stignu nove narudžbe.
 * Version: 1.5.0
 * Author: Tvoj Shop Dev Team
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WC_Order_Notifier {

    const OPTION_KEY = 'wc_order_notifier_options';

    public function __construct() {
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_check_new_orders', [ $this, 'check_new_orders' ] );
        add_filter( 'woocommerce_settings_tabs_array', [ $this, 'add_settings_tab' ], 50 );
        add_action( 'woocommerce_settings_tabs_order_notifier', [ $this, 'settings_tab' ] );
        add_action( 'woocommerce_update_options_order_notifier', [ $this, 'update_settings' ] );
    }

    public function enqueue_assets() {
        if ( ! is_admin() ) return;

        $screen      = get_current_screen();
        $scope       = $this->get_option( 'scope', 'orders_only' );
        $is_orders   = $screen && $screen->id === 'edit-shop_order';

        $installed_at = get_option('order_notifier_installed_at');
        $installed_at_timestamp = strtotime($installed_at);

        // Dohvaćanje prve valjane narudžbe
        $args = [
            'limit'        => 1,
            'orderby'      => 'date',
            'order'        => 'ASC',
            'date_created' => date('Y-m-d H:i:s', $installed_at_timestamp),
            'return'       => 'ids',
        ];

        $query = new WC_Order_Query($args);
        $orders = $query->get_orders();
        $first_order_id = !empty($orders) ? $orders[0] : 0;

        if ( $scope === 'everywhere' || ( $scope === 'orders_only' && $is_orders ) ) {
            wp_enqueue_script( 'toastr-js', 'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js', [], null, true );
            wp_enqueue_style( 'toastr-css', 'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css' );

            wp_enqueue_script( 'order-notifier', plugin_dir_url( __FILE__ ) . 'js/order-notifier.js', [ 'jquery' ], null, true );
            wp_enqueue_style( 'order-notifier-style', plugin_dir_url( __FILE__ ) . 'css/order-notifier.css', [], null );

            wp_localize_script( 'order-notifier', 'OrderNotifierData', [
                'ajax_url'         => admin_url( 'admin-ajax.php' ),
                'interval'         => $this->get_option( 'interval', 30 ),
                'statuses'         => $this->get_option( 'statuses', [ 'processing' ] ),
                'reload_table'     => $this->get_option( 'reload_table', 'no' ),
                'adaptive_interval'=> $this->get_option( 'adaptive_interval', 'no' ),
                'adaptive_attempts' => $this->get_option( 'adaptive_attempts', 5 ),
                'adaptive_step'    => $this->get_option( 'adaptive_step', 60 ),
                'nonce' => wp_create_nonce( 'check_new_orders_nonce' ),
                'current_user_id'  => get_current_user_id(),
                'user_hash'        => substr( hash_hmac( 'sha256', get_current_user_id(), LOGGED_IN_SALT ), 0, 12 ),
                'first_order_id'  => $first_order_id,
            ]);
        }
    }

    public function check_new_orders() {
        if ( ! check_ajax_referer( 'check_new_orders_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'error' => 'Invalid nonce' ] );
            return;
        }
    
        $last_check = sanitize_text_field( $_POST['last_check'] ?? '' );
        $statuses = array_map( 'sanitize_text_field', (array) ($_POST['statuses'] ?? [ 'processing' ]) );
    
        // Provjera transijenta
        $transient_key = 'wc_new_orders_' . sanitize_key( implode('_', $statuses) );
        $cached_orders = get_transient($transient_key);
    
        if ($cached_orders !== false) {
            $orders = $cached_orders;
        } else {
            $args = [
                'limit' => 1,
                'orderby' => 'date',
                'order' => 'DESC',
                'status' => $statuses,
            ];
    
            $orders = wc_get_orders( $args );
            if ( ! empty( $orders ) ) {
                $order = $orders[0];
                $orders = [
                    'id' => $order->get_id(),
                    'date' => $order->get_date_created()->date('c'),
                ];
            }
    
            set_transient($transient_key, $orders, 5 * MINUTE_IN_SECONDS);
        }
    
        if ( ! empty( $orders ) && isset($orders['date']) && isset($orders['id']) ) {
            $latest_time = $orders['date'];
            $latest_id = $orders['id'];
    
            $new_order = true;
    
            if ( ! empty( $last_check ) ) {
                $last_check_time = strtotime( $last_check );
                $latest_order_time = strtotime( $latest_time );
    
                $new_order = $latest_order_time > $last_check_time;
            }
    
            wp_send_json_success([
                'new_order' => $new_order,
                'latest_time' => $latest_time,
                'latest_id' => $latest_id,
            ]);
        }
    
        wp_send_json_success([ 'new_order' => false ]);
    }
    

    public function add_settings_tab( $tabs ) {
        $tabs['order_notifier'] = __( 'Order Notifier', 'wc-order-notifier' );
        return $tabs;
    }

    public function settings_tab() {
        woocommerce_admin_fields( $this->get_settings_fields() );
    }

    public function update_settings() {
        woocommerce_update_options( $this->get_settings_fields() );
    }

    private function get_settings_fields() {
        return [
            [
                'name' => __( 'Postavke notifikacija', 'wc-order-notifier' ),
                'type' => 'title',
                'id'   => 'wc_order_notifier_section_title'
            ],
            [
                'name' => __( 'Interval (sekundi)', 'wc-order-notifier' ),
                'type' => 'number',
                'id'   => self::OPTION_KEY . '[interval]',
                'default' => 30
            ],
            [
                'name' => __( 'Statusi za praćenje', 'wc-order-notifier' ),
                'type' => 'multiselect',
                'class' => 'wc-enhanced-select',
                'id'   => self::OPTION_KEY . '[statuses]',
                'options' => wc_get_order_statuses(),
                'default' => [ 'wc-processing' ]
            ],
            [
                'name'    => __( 'Prikaz notifikacija', 'wc-order-notifier' ),
                'type'    => 'select',
                'id'      => self::OPTION_KEY . '[scope]',
                'options' => [
                    'orders_only' => __( 'Samo na stranici narudžbi', 'wc-order-notifier' ),
                    'everywhere'  => __( 'Svugdje u adminu', 'wc-order-notifier' ),
                ],
                'default' => 'orders_only',
            ],
            [
                'name'    => __( 'Auto-refresh stranice narudžbi', 'wc-order-notifier' ),
                'type'    => 'checkbox',
                'id'      => self::OPTION_KEY . '[reload_table]',
                'desc'    => __( 'Automatski osvježi stranicu narudžbi kada stigne nova narudžba (samo na stranici narudžbi)', 'wc-order-notifier' ),
                'default' => ''
            ],
            [
                'name'    => __( 'Omogući adaptivni interval', 'wc-order-notifier' ),
                'type'    => 'checkbox',
                'id'      => self::OPTION_KEY . '[adaptive_interval]',
                'desc'    => __( 'Automatski povećava razmak između provjera ako nema novih narudžbi.', 'wc-order-notifier' ),
                'default' => ''
            ],
            [
                'name' => __( 'Broj pokušaja prije povećanja intervala', 'wc-order-notifier' ),
                'type' => 'number',
                'id'   => self::OPTION_KEY . '[adaptive_attempts]',
                'default' => 5,
                'desc' => __( 'Koliko puta treba provjeriti bez nove narudžbe prije povećanja intervala.', 'wc-order-notifier' )
            ],            
            [
                'name' => __( 'Korak povećanja (sekundi)', 'wc-order-notifier' ),
                'type' => 'number',
                'id'   => self::OPTION_KEY . '[adaptive_step]',
                'default' => 60,
                'desc' => __( 'Koliko sekundi dodati svakih X neuspješnih provjera.', 'wc-order-notifier' )
            ],
            [
                'type' => 'sectionend',
                'id'   => 'wc_order_notifier_section_end'
            ]           
        ];
    }

    private function get_option( $key, $default = '' ) {
        $options = get_option( self::OPTION_KEY, [] );
        return $options[$key] ?? $default;
    }
    
}

register_activation_hook(__FILE__, 'order_notifier_activate');

function order_notifier_activate() {
    if (!get_option('order_notifier_installed_at')) {
        update_option('order_notifier_installed_at', current_time('mysql'));
    }
}

new WC_Order_Notifier();
