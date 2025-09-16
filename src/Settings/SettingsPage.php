<?php

/*
 * Ova klasa upravlja svim postavkama, uključujući registraciju i prikaz u WooCommerce settings API. 
 * Ovdje ćeš moći koristiti već predloženi pristup s registracijom postavki.
 * 
 * This class manages all settings, including registration and display in the WooCommerce settings API.
 * Here, you will use the previously suggested approach for settings registration.
 * Version 2.0.0
 */
/**
 * SettingsSanitizerTrait metode:
 * @method array sanitize_settings(array $input, array $fields)
 * @method mixed sanitize_field(string $key, mixed $raw, array $args)
 * @method string sanitizeText(mixed $value, array $field)
 * @method float validateNumber(float $value, float $min, float $max)
 * @method mixed getDefault(array $field, mixed $fallback = null)
 * @method float|int sanitizeNumberWithMultiplier(mixed $value, array $field)
 */
namespace OrderNotifier\Settings;

use OrderNotifier\Utils\Debug;
use OrderNotifier\Helpers\StorageHelper;
use OrderNotifier\Utils\Constants;
use OrderNotifier\Settings\Traits\SettingsSanitizerTrait;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SettingsPage {
    use SettingsSanitizerTrait;

    public function add_settings_page() {
        Debug::log("Dodana stranica postavki");
        add_submenu_page(
            'woocommerce',
            __( 'Order Notifier Settings', Constants::ON_TEXT_DOMAIN ),
            __( 'Order Notifier', Constants::ON_TEXT_DOMAIN ),
            'manage_woocommerce',
            Constants::SETTINGS_PAGE_SLUG,
            [ $this, 'render_settings_page' ]
        );
    }
   
    public function render_settings_page() {
        Debug::log("Prikazana stranica postavki");
        ?>
        <div class="wrap">
            <h1 class="order-notifier-title"><?php _e( 'Order Notifier Settings', Constants::ON_TEXT_DOMAIN ); ?></h1>
            
            <div class="settings-container">
                <form method="post" action="options.php" class="settings-form">
                    <?php
                    settings_fields( Constants::ON_PLUGIN_SETTINGS . '_group' );
                    do_settings_sections( Constants::SETTINGS_PAGE_SLUG );
                    submit_button( __( 'Save Settings', Constants::ON_TEXT_DOMAIN ), 'primary', 'submit_settings', false );
                    ?>
                </form>
            </div>
    
            <?php if ( defined( 'ORDER_NOTIFIER_DEBUG' ) && ORDER_NOTIFIER_DEBUG ) : ?>
                <div class="debug-log-link">
                    <p>
                        <a href="<?php echo admin_url( 'admin.php?page=' . Constants::DEBUG_PAGE_SLUG); ?>" class="button">
                            <?php _e( 'Open Debug Log', Constants::ON_TEXT_DOMAIN ); ?>
                        </a>
                    </p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }    

    public static function get_option( $key, $default = null ) {
        $options = get_option( Constants::ON_PLUGIN_SETTINGS, [] );
        return $options[$key] ?? $default;
    }


    public static function get_default_settings(): void {
        $defaults = [];

        foreach ( self::get_settings_fields() as $key => $field ) {
            if ( isset( $field['default'] ) ) {
                $defaults[ $key ] = $field['default'];
            }
        }

        $existing = get_option( Constants::ON_PLUGIN_SETTINGS );

        if ( ! is_array( $existing ) ) {
            add_option( Constants::ON_PLUGIN_SETTINGS, $defaults );
        } else {
            // Spoji nove defaulte bez prepisivanja postojećih
            $merged = array_merge( $defaults, $existing );
            update_option( Constants::ON_PLUGIN_SETTINGS, $merged );
        }
    }


    public static function get_settings_fields(): array {
        $fields = [
            'scope' => [
                'label' => __( 'Notification Display', Constants::ON_TEXT_DOMAIN ),
                'type' => 'select',
                'options' => [
                    'orders_only' => __( 'Only on Order Page', Constants::ON_TEXT_DOMAIN ),
                    'everywhere'  => __( 'Everywhere in Admin', Constants::ON_TEXT_DOMAIN ),
                ],
                'default' => 'orders_only',
            ],
            'statuses' => [
                'label' => __( 'Order Statuses to Track', Constants::ON_TEXT_DOMAIN ),
                'type'  => 'multiselect',
                'options' => wc_get_order_statuses(),
                'default' => [ 'wc-processing' ],
            ],
            'custom_message' => [
                'label'   => __( 'Custom Message (Toastr)', Constants::ON_TEXT_DOMAIN ),
                'type'    => 'text',
                'default' => __( 'A new order has arrived!', Constants::ON_TEXT_DOMAIN ),
                'desc'    => __( 'This message will appear in the notification when a new order arrives.', Constants::ON_TEXT_DOMAIN ),
            ],
            'reload_table' => [
                'label' => __( 'Auto-refresh Orders Table', Constants::ON_TEXT_DOMAIN ),
                'type' => 'checkbox',
                'desc' => __( 'Automatically refresh the orders page when a new order arrives.', Constants::ON_TEXT_DOMAIN ),
                'default' => '',
            ],
            'allowed_roles' => [
                'label'   => __( 'Allowed User Roles', Constants::ON_TEXT_DOMAIN ),
                'type'    => 'multiselect',
                'options' => [
                    'administrator' => 'Administrator',
                    'shop_manager'  => 'Shop Manager',
                    'editor'        => 'Editor',
                    'author'        => 'Author',
                    'contributor'   => 'Contributor',
                    'subscriber'    => 'Subscriber',
                ],
                'default' => ['administrator', 'shop_manager'],
                'desc'    => __( 'Select roles allowed to receive order notifications.', Constants::ON_TEXT_DOMAIN ),
            ],
            'enable_ping' => [
                'label'   => __( 'Enable Ping', Constants::ON_TEXT_DOMAIN ),
                'type'    => 'checkbox',
                'default' => '',
                'desc'    => __( 'Send ping messages periodically to keep the connection alive.', Constants::ON_TEXT_DOMAIN ),
            ],
            'ping_interval' => [
                'label'   => __( 'Ping Interval (seconds)', Constants::ON_TEXT_DOMAIN ),
                'type'    => 'number',
                'default' => 15,
                'desc'    => __( 'How often to send ping messages (if enabled).', Constants::ON_TEXT_DOMAIN ),
            ],
            'enable_test_events' => [
                'label'   => __( 'Enable Test Events', Constants::ON_TEXT_DOMAIN ),
                'type'    => 'checkbox',
                'default' => '',
                'desc'    => __( 'Enable test messages to check if notifications are working.', Constants::ON_TEXT_DOMAIN ),
            ],
            'default_notification_type' => [
                'label'   => __( 'Default Notification Type', Constants::ON_TEXT_DOMAIN ),
                'type'    => 'select',
                'options' => [
                    'info'    => 'Info',
                    'success' => 'Success',
                    'warning' => 'Warning',
                    'error'   => 'Error',
                ],
                'default' => 'info',
            ],
            'default_notification_position' => [
                'label'   => __( 'Notification Position', Constants::ON_TEXT_DOMAIN ),
                'type'    => 'select',
                'options' => [
                    'top-center'    => 'Top Center',
                    'top-right'     => 'Top Right',
                    'top-left'      => 'Top Left',
                    'bottom-center' => 'Bottom Center',
                    'bottom-right'  => 'Bottom Right',
                    'bottom-left'   => 'Bottom Left',
                ],
                'default' => 'top-right',
            ],
            'default_notification_icon' => [
                'label'   => __( 'Notification Icon (name or URL)', Constants::ON_TEXT_DOMAIN ),
                'type'    => 'text',
                'default' => '',
                'desc'    => __( 'Optional icon name or full URL to image.', Constants::ON_TEXT_DOMAIN ),
            ],
            'default_notification_timeout' => [
                'label'   => __( 'Notification Timeout (s)', Constants::ON_TEXT_DOMAIN ),
                'type'    => 'number',
                'default' => 0,
                'desc'    => __( 'Time in seconds before notification disappears. 0 means no timeout.', Constants::ON_TEXT_DOMAIN ),
                'min'     => 0,
                'max'     => 60000,
                'multiplier' => 1000,
                'tooltip'    => 'Vrijednost se unosi u sekundama; u bazi se sprema u milisekundama.',
            ],
            'max_notifications' => [
                'label'   => __( 'Max Notifications Displayed', Constants::ON_TEXT_DOMAIN ),
                'type'    => 'number',
                'default' => 5,
                'desc'    => __( 'Maximum number of notifications shown simultaneously.', Constants::ON_TEXT_DOMAIN ),
                'min'     => 1,
                'max'     => 10,
            ],
        ];

        return apply_filters( Constants::ON_PLUGIN_SETTINGS_HOOK, $fields );
    }

    public function register_settings() {
        register_setting(
            Constants::ON_PLUGIN_SETTINGS . '_group',
            Constants::ON_PLUGIN_SETTINGS,
            [ 'sanitize_callback' => [ $this, 'sanitize' ] ]
        );

        add_settings_section(
            'wc_order_notifier_main',
            __( 'Notification Settings', Constants::ON_TEXT_DOMAIN ),
            '__return_null',
            Constants::SETTINGS_PAGE_SLUG
        );

        $fields = self::get_settings_fields();

        foreach ( $fields as $key => $args ) {
            add_settings_field(
                $key,
                $args['label'],
                [ $this, 'render_field' ],
                Constants::SETTINGS_PAGE_SLUG,
                'wc_order_notifier_main',
                [ 'key' => $key, 'args' => $args ]
            );
        }
    }

    /**
     * [Description for render_field]
     * 
     */
    /**
     * Renderira HTML input za pojedino polje, uz podršku za tooltip.
     *
     * @param array $field Polje s ključem i argumentima
     * 
     * @return [type]
     */
    public function render_field( $field ) { 
        $key  = $field['key'];
        $args = $field['args'];

        // Dohvati vrijednost
        $raw_value = StorageHelper::get_option( $field['key'], $field['default'] ?? null );
        $value     = $this->render_value_for_field( $key, $raw_value, $args );

        // Tooltip (ako postoji)
        $tooltip = $args['tooltip'] ?? '';

        switch ( $args['type'] ) {
            case 'number':
                printf(
                    '<input type="number" name="%1$s[%2$s]" value="%3$s" class="small-text" />',
                    esc_attr( Constants::ON_PLUGIN_SETTINGS ),
                    esc_attr( $key ),
                    esc_attr( $value )
                );
                break;

            case 'text':
                printf(
                    '<input type="text" name="%1$s[%2$s]" value="%3$s" class="regular-text" />',
                    esc_attr( Constants::ON_PLUGIN_SETTINGS ),
                    esc_attr( $key ),
                    esc_attr( $value )
                );
                break;
            
            case 'textarea':
                printf(
                    '<textarea name="%1$s[%2$s]" class="large-text" rows="5">%3$s</textarea>',
                    esc_attr( Constants::ON_PLUGIN_SETTINGS ),
                    esc_attr( $key ),
                    esc_textarea( $value )
                );
                break;
                
            case 'select':
                echo '<select name="' . esc_attr( Constants::ON_PLUGIN_SETTINGS ) . '[' . esc_attr( $key ) . ']">';
                foreach ( $args['options'] as $opt_key => $label ) {
                    printf(
                        '<option value="%s"%s>%s</option>',
                        esc_attr( $opt_key ),
                        selected( $value, $opt_key, false ),
                        esc_html( $label )
                    );
                }
                echo '</select>';
                break;

            case 'multiselect':
                echo '<select multiple class="order-notifier-select2" name="' . esc_attr( Constants::ON_PLUGIN_SETTINGS ) . '[' . esc_attr( $key ) . '][]">';
                foreach ( $args['options'] as $opt_key => $label ) {
                    printf(
                        '<option value="%s"%s>%s</option>',
                        esc_attr( $opt_key ),
                        in_array( $opt_key, (array) $value, true ) ? ' selected' : '',
                        esc_html( $label )
                    );
                }
                echo '</select>';
                break;           

            case 'checkbox':
                printf(
                    '<label class="order-notifier-switch">
                        <input type="checkbox" name="%1$s[%2$s]" value="1" %3$s>
                        <span class="order-notifier-slider"></span>
                    </label> %4$s',
                    esc_attr( Constants::ON_PLUGIN_SETTINGS ),
                    esc_attr( $key ),
                    checked( $value, '1', false ),
                    isset( $args['desc'] ) ? '<span class="switch-label">' . esc_html( $args['desc'] ) . '</span>' : ''
                );
                // za checkbox opis već ide pored → ne trebamo ispod
                return;
        }

        // Description ispod inputa (s tooltipom)
        if ( isset( $args['desc'] ) ) {
            echo '<p class="description"' .
                ( $tooltip ? ' title="' . esc_attr( $tooltip ) . '"' : '' ) .
                '>' . esc_html( $args['desc'] ) . '</p>';
        }
    }


    public function sanitize( $input ) {
        $fields = self::get_settings_fields();
        return $this->sanitize_settings( $input, $fields );
    }

    /**
     * Pomoćna funkcija koja konvertira vrijednost za prikaz u formi.
     * Ako polje ima multiplier, vrijednost se podijeli prije prikaza.
     */
    private function render_value_for_field( $key, $value, $args ) {
        // Ako polje ima multiplier, podijeli ga za prikaz
        if ( isset( $args['multiplier'] ) && is_numeric( $value ) ) {
            return $value / $args['multiplier'];
        }
        return $value;
    }

}


