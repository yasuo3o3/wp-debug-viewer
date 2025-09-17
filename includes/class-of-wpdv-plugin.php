<?php
/**
 * Core plugin bootstrap.
 *
 * @package wp-debug-viewer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once OF_WPDV_PLUGIN_DIR . 'includes/class-of-wpdv-settings.php';
require_once OF_WPDV_PLUGIN_DIR . 'includes/class-of-wpdv-log-reader.php';
require_once OF_WPDV_PLUGIN_DIR . 'includes/class-of-wpdv-admin.php';
require_once OF_WPDV_PLUGIN_DIR . 'includes/class-of-wpdv-rest-controller.php';

/**
 * Class Of_Wpdv_Plugin
 */
class Of_Wpdv_Plugin {
    /**
     * Singleton instance.
     *
     * @var Of_Wpdv_Plugin|null
     */
    private static $instance = null;

    /**
     * Settings handler.
     *
     * @var Of_Wpdv_Settings
     */
    private $settings;

    /**
     * Log reader.
     *
     * @var Of_Wpdv_Log_Reader
     */
    private $log_reader;

    /**
     * Admin handler.
     *
     * @var Of_Wpdv_Admin
     */
    private $admin;

    /**
     * REST controller.
     *
     * @var Of_Wpdv_Rest_Controller
     */
    private $rest_controller;

    /**
     * Get singleton instance.
     *
     * @return Of_Wpdv_Plugin
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->settings        = new Of_Wpdv_Settings();
        $this->log_reader      = new Of_Wpdv_Log_Reader();
        $this->rest_controller = new Of_Wpdv_Rest_Controller( $this );
        $this->admin           = new Of_Wpdv_Admin( $this );
    }

    /**
     * Initialise plugin hooks.
     *
     * @return void
     */
    public function init() {
        add_action( 'rest_api_init', array( $this->rest_controller, 'register_routes' ) );
        add_action( 'admin_menu', array( $this->admin, 'register_menu' ) );
        add_action( 'network_admin_menu', array( $this->admin, 'register_network_menu' ) );
        add_action( 'admin_init', array( $this->admin, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this->admin, 'enqueue_assets' ) );
    }

    /**
     * Retrieve settings handler.
     *
     * @return Of_Wpdv_Settings
     */
    public function get_settings_handler() {
        return $this->settings;
    }

    /**
     * Retrieve log reader.
     *
     * @return Of_Wpdv_Log_Reader
     */
    public function get_log_reader() {
        return $this->log_reader;
    }

    /**
     * Retrieve aggregated settings array.
     *
     * @return array
     */
    public function get_settings() {
        return $this->settings->get_settings();
    }

    /**
     * Load plugin textdomain.
     *
     * @return void
     */
    public function load_textdomain() {
        load_plugin_textdomain( 'wp-debug-viewer', false, dirname( plugin_basename( OF_WPDV_PLUGIN_FILE ) ) . '/languages/' );
    }

    /**
     * Determine permissions for current user/context.
     *
     * @param bool $network_context Whether this is a network admin context.
     * @return array
     */
    public function get_permissions( $network_context = false ) {
        $settings      = $this->get_settings();
        $environment   = wp_get_environment_type();
        $is_production = ( 'production' === $environment );
        $override_active = $this->settings->is_production_override_active();
        $allow_mutation  = ! $is_production || $override_active;

        $can_clear    = $allow_mutation;
        $can_download = $allow_mutation && ! empty( $settings['enable_download'] );

        $download_globally_disabled = empty( $settings['enable_download'] );
        $reasons = array(
            'clear'    => '',
            'download' => '',
        );

        if ( $is_production && ! $override_active ) {
            $can_clear    = false;
            $can_download = false;
            $reasons['clear']    = __( '本番環境では既定でクリアは無効です。設定から15分間の一時許可を発行できます。', 'wp-debug-viewer' );
            $reasons['download'] = __( '本番環境では既定でダウンロードは無効です。', 'wp-debug-viewer' );
        }

        if ( $download_globally_disabled ) {
            $can_download        = false;
            $reasons['download'] = __( '設定でダウンロード機能が無効化されています。', 'wp-debug-viewer' );
        }

        if ( is_multisite() && ! $network_context ) {
            if ( empty( $settings['allow_site_actions'] ) ) {
                $can_clear    = false;
                $can_download = false;
                $reasons['clear']    = __( 'ネットワーク管理者のみがログをクリアできます。', 'wp-debug-viewer' );
                $reasons['download'] = __( 'ネットワーク管理者のみがログをダウンロードできます。', 'wp-debug-viewer' );
            }
        }

        return array(
            'environment'                => $environment,
            'is_production'              => $is_production,
            'override_active'            => $override_active,
            'override_expires'           => $override_active ? (int) $settings['production_temp_expiration'] : 0,
            'can_view'                   => true,
            'can_clear'                  => $can_clear,
            'can_download'               => $can_download,
            'reasons'                    => $reasons,
            'defaults'                   => array(
                'lines'    => (int) $settings['default_lines'],
                'minutes'  => (int) $settings['default_minutes'],
                'max_lines'=> (int) $settings['max_lines'],
            ),
            'auto_refresh_interval'      => (int) $settings['auto_refresh_interval'],
            'download_enabled_setting'   => (bool) $settings['enable_download'],
            'allow_site_actions_setting' => (bool) $settings['allow_site_actions'],
        );
    }
}
