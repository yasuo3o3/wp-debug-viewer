<?php
/**
 * Admin interface handler.
 *
 * @package wp-debug-viewer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Of_Wpdv_Admin
 */
class Of_Wpdv_Admin {
    /**
     * Plugin instance.
     *
     * @var Of_Wpdv_Plugin
     */
    private $plugin;

    /**
     * Constructor.
     *
     * @param Of_Wpdv_Plugin $plugin Plugin instance.
     */
    public function __construct( Of_Wpdv_Plugin $plugin ) {
        $this->plugin = $plugin;
        add_action( 'admin_post_of_wpdv_toggle_prod_override', array( $this, 'handle_toggle_production_override' ) );
        add_action( 'admin_post_of_wpdv_toggle_temp_logging', array( $this, 'handle_temp_logging_toggle' ) );
    }

    /**
     * Register admin menu (site context).
     *
     * @return void
     */
    public function register_menu() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        add_menu_page(
            __( 'WP Debug Viewer', 'wp-debug-viewer' ),
            __( 'WP Debug Viewer', 'wp-debug-viewer' ),
            'manage_options',
            'wp-debug-viewer',
            array( $this, 'render_page' ),
            'dashicons-editor-code',
            59
        );
    }

    /**
     * Register admin menu for network context.
     *
     * @return void
     */
    public function register_network_menu() {
        if ( ! is_multisite() ) {
            return;
        }
        if ( ! current_user_can( 'manage_network_options' ) ) {
            return;
        }

        add_menu_page(
            __( 'WP Debug Viewer', 'wp-debug-viewer' ),
            __( 'WP Debug Viewer', 'wp-debug-viewer' ),
            'manage_network_options',
            'wp-debug-viewer',
            array( $this, 'render_page' ),
            'dashicons-editor-code',
            59
        );
    }

    /**
     * Register plugin settings.
     *
     * @return void
     */
    public function register_settings() {
        register_setting(
            'of_wpdv_settings_group',
            Of_Wpdv_Settings::OPTION_NAME,
            array(
                'type'              => 'array',
                'sanitize_callback' => array( $this->plugin->get_settings_handler(), 'sanitize' ),
                'show_in_rest'      => false,
            )
        );

        add_settings_section(
            'of_wpdv_general',
            __( 'ビューアー設定', 'wp-debug-viewer' ),
            array( $this, 'render_general_section_intro' ),
            'of-wpdv-settings'
        );

        $this->add_number_field(
            'default_lines',
            __( '既定の表示行数', 'wp-debug-viewer' ),
            'of-wpdv-default-lines',
            10,
            1000,
            10,
            __( 'ログ初期表示で読み込む行数（最大1000行）。', 'wp-debug-viewer' )
        );

        $this->add_number_field(
            'default_minutes',
            __( '既定の表示分数', 'wp-debug-viewer' ),
            'of-wpdv-default-minutes',
            1,
            120,
            1,
            __( '「直近M分」モードで表示する既定の分数。', 'wp-debug-viewer' )
        );

        $this->add_number_field(
            'max_lines',
            __( '最大読み込み行数', 'wp-debug-viewer' ),
            'of-wpdv-max-lines',
            100,
            1000,
            50,
            __( '1回の読み込みで取得する最大行数。大きすぎるとパフォーマンスに影響します。', 'wp-debug-viewer' )
        );

        $this->add_number_field(
            'auto_refresh_interval',
            __( '自動更新間隔 (秒)', 'wp-debug-viewer' ),
            'of-wpdv-auto-refresh-interval',
            2,
            120,
            1,
            __( '自動更新タイマーの間隔（秒）。', 'wp-debug-viewer' )
        );

        add_settings_field(
            'of_wpdv_enable_download',
            __( 'ダウンロードを許可', 'wp-debug-viewer' ),
            array( $this, 'render_checkbox_field' ),
            'of-wpdv-settings',
            'of_wpdv_general',
            array(
                'key'         => 'enable_download',
                'label_for'   => 'of-wpdv-enable-download',
                'description' => __( 'ログのダウンロードボタンを表示します（5MB以下）。', 'wp-debug-viewer' ),
            )
        );

        if ( is_multisite() ) {
            add_settings_field(
                'of_wpdv_allow_site_actions',
                __( 'サイト管理者への操作許可', 'wp-debug-viewer' ),
                array( $this, 'render_checkbox_field' ),
                'of-wpdv-settings',
                'of_wpdv_general',
                array(
                    'key'         => 'allow_site_actions',
                    'label_for'   => 'of-wpdv-allow-site-actions',
                    'description' => __( '個別サイト管理画面からのクリア／ダウンロードを許可します。', 'wp-debug-viewer' ),
                )
            );
        }
    }

    /**
     * Helper to register number fields.
     *
     * @param string $key Setting key.
     * @param string $label Field label.
     * @param string $id HTML id.
     * @param int    $min Minimum value.
     * @param int    $max Maximum value.
     * @param int    $step Step value.
     * @param string $description Description.
     * @return void
     */
    private function add_number_field( $key, $label, $id, $min, $max, $step, $description ) {
        add_settings_field(
            'of_wpdv_' . $key,
            $label,
            array( $this, 'render_number_field' ),
            'of-wpdv-settings',
            'of_wpdv_general',
            array(
                'key'         => $key,
                'label_for'   => $id,
                'min'         => $min,
                'max'         => $max,
                'step'        => $step,
                'description' => $description,
            )
        );
    }

    /**
     * Enqueue assets for the admin page.
     *
     * @param string $hook Current admin hook.
     * @return void
     */
    public function enqueue_assets( $hook ) {
        if ( 'toplevel_page_wp-debug-viewer' !== $hook ) {
            return;
        }

        $is_network = is_network_admin();
        $permissions = $this->plugin->get_permissions( $is_network );

        wp_enqueue_style(
            'of-wpdv-admin',
            OF_WPDV_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            OF_WPDV_VERSION
        );

        wp_enqueue_script(
            'of-wpdv-admin',
            OF_WPDV_PLUGIN_URL . 'assets/js/admin.js',
            array( 'wp-api-fetch', 'wp-i18n', 'wp-element' ),
            OF_WPDV_VERSION,
            true
        );

        wp_localize_script(
            'of-wpdv-admin',
            'ofWpdvData',
            $this->prepare_localized_data( $permissions, $is_network )
        );
    }

    /**
     * Render the admin page.
     *
     * @return void
     */
    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_network_options' ) ) {
            wp_die( esc_html__( 'この操作を実行する権限がありません。', 'wp-debug-viewer' ) );
        }

        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'viewer';
        if ( ! in_array( $active_tab, array( 'viewer', 'settings' ), true ) ) {
            $active_tab = 'viewer';
        }

        $permissions = $this->plugin->get_permissions( is_network_admin() );

        echo '<div class="wrap of-wpdv-wrap">';
        echo '<h1>' . esc_html__( 'WP Debug Viewer', 'wp-debug-viewer' ) . '</h1>';
        $this->render_tabs( $active_tab );
        $this->maybe_render_notice();

        if ( 'settings' === $active_tab ) {
            $this->render_settings_tab( $permissions );
        } else {
            $this->render_viewer_tab( $permissions );
        }

        echo '</div>';
    }

    /**
     * Render navigation tabs.
     *
     * @param string $active Active tab.
     * @return void
     */
    private function render_tabs( $active ) {
        $tabs = array(
            'viewer'   => __( 'ログビューアー', 'wp-debug-viewer' ),
            'settings' => __( '設定', 'wp-debug-viewer' ),
        );

        $base_url = is_network_admin() ? network_admin_url( 'admin.php' ) : admin_url( 'admin.php' );

        echo '<nav class="nav-tab-wrapper of-wpdv-nav">';
        foreach ( $tabs as $slug => $label ) {
            $class = ( $slug === $active ) ? ' nav-tab nav-tab-active' : ' nav-tab';
            $href  = add_query_arg(
                array(
                    'page' => 'wp-debug-viewer',
                    'tab'  => $slug,
                ),
                $base_url
            );

            printf(
                '<a href="%1$s" class="%2$s">%3$s</a>',
                esc_url( $href ),
                esc_attr( $class ),
                esc_html( $label )
            );
        }
        echo '</nav>';
    }

    /**
     * Render viewer tab content.
     *
     * @param array $permissions Permissions context.
     * @return void
     */
    private function render_viewer_tab( array $permissions ) {
        $environment = isset( $permissions['environment'] ) ? $permissions['environment'] : 'production';
        $badge_slug  = sanitize_html_class( $environment );
        $badge_class = 'of-wpdv-badge of-wpdv-env-' . $badge_slug;
        $badge_label = strtoupper( $environment );
        $max_lines   = isset( $permissions['defaults']['max_lines'] ) ? (int) $permissions['defaults']['max_lines'] : 1000;

        echo '<section class="of-wpdv-viewer" aria-label="' . esc_attr__( 'デバッグログビューアー', 'wp-debug-viewer' ) . '">';
        echo '<header class="of-wpdv-toolbar">';
        echo '<div class="of-wpdv-environment">';
        printf( '<span class="%1$s">%2$s</span>', esc_attr( $badge_class ), esc_html( $badge_label ) );

        if ( ! empty( $permissions['override_active'] ) && ! empty( $permissions['override_expires'] ) ) {
            $expires = wp_date( 'Y/m/d H:i', (int) $permissions['override_expires'] );
            echo '<span class="of-wpdv-override-info">' . esc_html( sprintf( __( '一時許可中: %s まで', 'wp-debug-viewer' ), $expires ) ) . '</span>';
        }

        echo '</div>';

        echo '<div class="of-wpdv-mode-controls">';
        echo '<fieldset>';
        echo '<legend class="screen-reader-text">' . esc_html__( '表示モード', 'wp-debug-viewer' ) . '</legend>';
        echo '<label for="of-wpdv-mode-lines"><input type="radio" name="of-wpdv-mode" id="of-wpdv-mode-lines" value="lines" checked> ' . esc_html__( '直近N行', 'wp-debug-viewer' ) . '</label>';
        printf(
            '<input type="number" id="of-wpdv-lines" class="small-text" min="1" max="%1$s" step="10" value="%2$s">',
            esc_attr( $max_lines ),
            esc_attr( (int) $permissions['defaults']['lines'] )
        );
        echo '<label for="of-wpdv-mode-minutes"><input type="radio" name="of-wpdv-mode" id="of-wpdv-mode-minutes" value="minutes"> ' . esc_html__( '直近M分', 'wp-debug-viewer' ) . '</label>';
        printf(
            '<input type="number" id="of-wpdv-minutes" class="small-text" min="1" max="120" step="1" value="%1$s">',
            esc_attr( (int) $permissions['defaults']['minutes'] )
        );
        echo '</fieldset>';
        echo '</div>';

        echo '<div class="of-wpdv-actions">';
        echo '<button type="button" class="button" id="of-wpdv-refresh">' . esc_html__( '再読み込み', 'wp-debug-viewer' ) . '</button>';
        echo '<button type="button" class="button" id="of-wpdv-pause" data-paused="false">' . esc_html__( '一時停止', 'wp-debug-viewer' ) . '</button>';
        echo '<button type="button" class="button button-secondary" id="of-wpdv-clear"' . ( empty( $permissions['can_clear'] ) ? ' disabled' : '' ) . '>' . esc_html__( 'ログをクリア', 'wp-debug-viewer' ) . '</button>';
        echo '<button type="button" class="button" id="of-wpdv-download"' . ( empty( $permissions['can_download'] ) ? ' disabled' : '' ) . '>' . esc_html__( 'ダウンロード', 'wp-debug-viewer' ) . '</button>';
        echo '</div>';
        echo '</header>';

        echo '<div class="of-wpdv-status" id="of-wpdv-status" aria-live="polite"></div>';
        echo '<textarea id="of-wpdv-log" class="of-wpdv-log" rows="20" readonly="readonly"></textarea>';
        echo '<footer class="of-wpdv-footer">';
        echo '<div class="of-wpdv-stats" id="of-wpdv-stats"></div>';
        if ( ! empty( $permissions['reasons']['clear'] ) || ! empty( $permissions['reasons']['download'] ) ) {
            echo '<div class="of-wpdv-hints">';
            if ( ! empty( $permissions['reasons']['clear'] ) ) {
                echo '<p class="description">' . esc_html( $permissions['reasons']['clear'] ) . '</p>';
            }
            if ( ! empty( $permissions['reasons']['download'] ) ) {
                echo '<p class="description">' . esc_html( $permissions['reasons']['download'] ) . '</p>';
            }
            echo '</div>';
        }
        echo '</footer>';
        echo '<noscript><p>' . esc_html__( 'このビューアーを使用するには JavaScript を有効にしてください。', 'wp-debug-viewer' ) . '</p></noscript>';
        echo '</section>';
    }

    /**
     * Render settings tab.
     *
     * @param array $permissions Permissions context.
     * @return void
     */
    private function render_settings_tab( array $permissions ) {
        echo '<section class="of-wpdv-settings">';
        echo '<form action="' . esc_url( admin_url( 'options.php' ) ) . '" method="post">';
        settings_fields( 'of_wpdv_settings_group' );
        do_settings_sections( 'of-wpdv-settings' );
        submit_button();
        echo '</form>';

        if ( 'production' === $permissions['environment'] ) {
            $this->render_production_override_controls( $permissions );
        }

        $this->render_temp_logging_controls( $permissions );

        echo '</section>';
    }

    /**
     * Intro description for general section.
     *
     * @return void
     */
    public function render_general_section_intro() {
        echo '<p>' . esc_html__( 'ログビューアーの初期表示や自動更新設定を調整します。', 'wp-debug-viewer' ) . '</p>';
    }

    /**
     * Render a number field.
     *
     * @param array $args Field arguments.
     * @return void
     */
    public function render_number_field( array $args ) {
        $settings = $this->plugin->get_settings();
        $key   = $args['key'];
        $id    = $args['label_for'];
        $min   = isset( $args['min'] ) ? (int) $args['min'] : 0;
        $max   = isset( $args['max'] ) ? (int) $args['max'] : 0;
        $step  = isset( $args['step'] ) ? (int) $args['step'] : 1;
        $value = isset( $settings[ $key ] ) ? $settings[ $key ] : 0;

        printf(
            '<input type="number" id="%1$s" name="%2$s[%3$s]" value="%4$s" class="small-text" min="%5$s" max="%6$s" step="%7$s">',
            esc_attr( $id ),
            esc_attr( Of_Wpdv_Settings::OPTION_NAME ),
            esc_attr( $key ),
            esc_attr( $value ),
            esc_attr( $min ),
            esc_attr( $max ),
            esc_attr( $step )
        );

        if ( ! empty( $args['description'] ) ) {
            echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
        }
    }

    /**
     * Render a checkbox field.
     *
     * @param array $args Field arguments.
     * @return void
     */
    public function render_checkbox_field( array $args ) {
        $settings = $this->plugin->get_settings();
        $key = $args['key'];
        $id  = $args['label_for'];
        $checked = ! empty( $settings[ $key ] );

        printf(
            '<label><input type="checkbox" id="%1$s" name="%2$s[%3$s]" value="1" %4$s> %5$s</label>',
            esc_attr( $id ),
            esc_attr( Of_Wpdv_Settings::OPTION_NAME ),
            esc_attr( $key ),
            checked( $checked, true, false ),
            esc_html__( '有効にする', 'wp-debug-viewer' )
        );

        if ( ! empty( $args['description'] ) ) {
            echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
        }
    }

    /**
     * Render production override controls.
     *
     * @param array $permissions Permissions context.
     * @return void
     */
    private function render_production_override_controls( array $permissions ) {
        $override_active = ! empty( $permissions['override_active'] );
        $expires = ! empty( $permissions['override_expires'] ) ? wp_date( 'Y/m/d H:i', (int) $permissions['override_expires'] ) : '';

        echo '<div class="of-wpdv-card">';
        echo '<h2>' . esc_html__( '本番環境での一時許可', 'wp-debug-viewer' ) . '</h2>';
        if ( $override_active && $expires ) {
            echo '<p>' . esc_html( sprintf( __( '現在、一時許可が有効です（%s まで）。', 'wp-debug-viewer' ), $expires ) ) . '</p>';
        } else {
            echo '<p>' . esc_html__( '本番環境では既定でクリア／ダウンロードは無効です。必要な場合のみ15分間の一時許可を発行できます。', 'wp-debug-viewer' ) . '</p>';
        }

        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'of_wpdv_toggle_prod_override' );
        echo '<input type="hidden" name="action" value="of_wpdv_toggle_prod_override">';
        if ( $override_active ) {
            echo '<input type="hidden" name="state" value="disable">';
            submit_button( __( '一時許可を解除', 'wp-debug-viewer' ), 'secondary', 'submit', false );
        } else {
            echo '<input type="hidden" name="state" value="enable">';
            submit_button( __( '15分間許可を発行', 'wp-debug-viewer' ), 'primary', 'submit', false );
        }
        echo '</form>';
        echo '</div>';
    }

    /**
     * Render temporary logging controls.
     *
     * @param array $permissions Permissions context.
     * @return void
     */
    private function render_temp_logging_controls( array $permissions ) {
        $temp_logging_active = ! empty( $permissions['temp_logging_active'] );
        $expires = ! empty( $permissions['temp_logging_expires'] ) ? wp_date( 'Y/m/d H:i', (int) $permissions['temp_logging_expires'] ) : '';

        echo '<div class="of-wpdv-card">';
        echo '<h2>' . esc_html__( '一時的なログ出力', 'wp-debug-viewer' ) . '</h2>';
        if ( $temp_logging_active && $expires ) {
            echo '<p>' . esc_html( sprintf( __( '現在、一時ログ出力が有効です（%s まで）。', 'wp-debug-viewer' ), $expires ) ) . '</p>';
        } else {
            echo '<p>' . esc_html__( 'wp-configを変更せずに15分間だけログ出力を有効化できます。', 'wp-debug-viewer' ) . '</p>';
        }

        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'of_wpdv_toggle_temp_logging' );
        echo '<input type="hidden" name="action" value="of_wpdv_toggle_temp_logging">';
        if ( $temp_logging_active ) {
            echo '<input type="hidden" name="state" value="disable">';
            submit_button( __( '一時ログ出力を停止', 'wp-debug-viewer' ), 'secondary', 'submit', false );
        } else {
            echo '<input type="hidden" name="state" value="enable">';
            submit_button( __( '15分間ログ出力を有効化', 'wp-debug-viewer' ), 'primary', 'submit', false );
        }
        echo '</form>';
        echo '</div>';
    }

    /**
     * Handle production override toggle.
     *
     * @return void
     */
    public function handle_toggle_production_override() {
        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_network_options' ) ) {
            wp_die( esc_html__( 'この操作を実行する権限がありません。', 'wp-debug-viewer' ) );
        }

        check_admin_referer( 'of_wpdv_toggle_prod_override' );

        $state = isset( $_POST['state'] ) ? sanitize_key( $_POST['state'] ) : 'enable';
        $settings_handler = $this->plugin->get_settings_handler();

        if ( 'disable' === $state ) {
            $settings = $settings_handler->get_settings();
            $settings['production_temp_expiration'] = 0;
            update_option( Of_Wpdv_Settings::OPTION_NAME, $settings, false );
            $message = 'prod_disabled';
        } else {
            $timestamp = current_time( 'timestamp' ) + ( 15 * MINUTE_IN_SECONDS );
            $settings_handler->set_production_override_expiration( $timestamp );
            $message = 'prod_enabled';
        }

        $redirect = add_query_arg(
            array(
                'page'            => 'wp-debug-viewer',
                'tab'             => 'settings',
                'of_wpdv_message' => $message,
            ),
            is_network_admin() ? network_admin_url( 'admin.php' ) : admin_url( 'admin.php' )
        );

        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Display notice if message query present.
     *
     * @return void
     */
    private function maybe_render_notice() {
        $override_message = isset( $_GET['override_message'] ) ? sanitize_key( $_GET['override_message'] ) : '';
        $temp_logging_message = isset( $_GET['temp_logging_message'] ) ? sanitize_key( $_GET['temp_logging_message'] ) : '';
        $legacy_message = isset( $_GET['of_wpdv_message'] ) ? sanitize_key( $_GET['of_wpdv_message'] ) : '';

        $messages = array(
            'prod_enabled'         => __( '本番環境で15分間の一時許可を有効化しました。', 'wp-debug-viewer' ),
            'prod_disabled'        => __( '本番環境での一時許可を解除しました。', 'wp-debug-viewer' ),
            'override_enabled'     => __( '本番環境での一時許可を有効にしました。', 'wp-debug-viewer' ),
            'override_disabled'    => __( '本番環境での一時許可を解除しました。', 'wp-debug-viewer' ),
            'override_error'       => __( '操作に失敗しました。', 'wp-debug-viewer' ),
            'temp_logging_enabled' => __( '一時ログ出力を有効にしました（15分間）。', 'wp-debug-viewer' ),
            'temp_logging_disabled'=> __( '一時ログ出力を無効にしました。', 'wp-debug-viewer' ),
            'temp_logging_error'   => __( 'ログ出力設定の変更に失敗しました。', 'wp-debug-viewer' ),
        );

        $message_key = $override_message ?: $temp_logging_message ?: $legacy_message;

        if ( ! empty( $message_key ) && isset( $messages[ $message_key ] ) ) {
            printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $messages[ $message_key ] ) );
        }
    }

    /**
     * Prepare data for JavaScript localisation.
     *
     * @param array $permissions Permissions array.
     * @param bool  $is_network Whether network context.
     * @return array
     */
    private function prepare_localized_data( array $permissions, $is_network ) {
        $settings = $this->plugin->get_settings();
        $stats    = $this->plugin->get_log_reader()->get_stats();

        return array(
            'restUrl'   => 'wp-debug-viewer/v1/',
            'nonce'     => wp_create_nonce( 'wp_rest' ),
            'settings'  => array(
                'defaultLines'    => (int) $settings['default_lines'],
                'defaultMinutes'  => (int) $settings['default_minutes'],
                'maxLines'        => (int) $settings['max_lines'],
                'autoRefresh'     => (int) $settings['auto_refresh_interval'],
                'downloadEnabled' => (bool) $settings['enable_download'],
            ),
            'permissions' => $permissions,
            'stats'       => $stats,
            'environment' => array(
                'label' => strtoupper( $permissions['environment'] ),
                'slug'  => $permissions['environment'],
            ),
            'isNetwork' => (bool) $is_network,
            'strings'   => array(
                'refresh'       => __( '再読み込み', 'wp-debug-viewer' ),
                'pause'         => __( '一時停止', 'wp-debug-viewer' ),
                'resume'        => __( '再開', 'wp-debug-viewer' ),
                'cleared'       => __( 'ログをクリアしました。', 'wp-debug-viewer' ),
                'clearConfirm'  => __( '本当に debug.log をクリアしますか？', 'wp-debug-viewer' ),
                'downloadError' => __( 'ダウンロードに失敗しました。', 'wp-debug-viewer' ),
                'noData'        => __( '表示できるログがありません。', 'wp-debug-viewer' ),
                'fallbackNote'  => __( 'タイムスタンプが見つからなかったため行数モードで表示しています。', 'wp-debug-viewer' ),
                'statsLabel'    => __( 'ファイル情報', 'wp-debug-viewer' ),
                'sizeLabel'     => __( 'サイズ', 'wp-debug-viewer' ),
                'updatedLabel'  => __( '最終更新', 'wp-debug-viewer' ),
                'missingLog'    => __( 'debug.log が見つかりません。', 'wp-debug-viewer' ),
                'paused'        => __( '自動更新を一時停止しました。', 'wp-debug-viewer' ),
                'resumed'       => __( '自動更新を再開しました。', 'wp-debug-viewer' ),
            ),
        );
    }

    /**
     * Handle temporary logging toggle.
     *
     * @return void
     */
    public function handle_temp_logging_toggle() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'この操作を実行する権限がありません。', 'wp-debug-viewer' ) );
        }

        if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'of_wpdv_toggle_temp_logging' ) ) {
            wp_die( esc_html__( '無効なリクエストです。', 'wp-debug-viewer' ) );
        }

        $state = sanitize_key( $_POST['state'] );
        $settings = $this->plugin->get_settings();

        if ( 'enable' === $state ) {
            $success = $settings->enable_temp_logging();
            $message = $success ? 'temp_logging_enabled' : 'temp_logging_error';
        } else {
            $success = $settings->disable_temp_logging();
            $message = $success ? 'temp_logging_disabled' : 'temp_logging_error';
        }

        $redirect_url = add_query_arg(
            array(
                'page' => 'wp-debug-viewer',
                'tab'  => 'settings',
                'temp_logging_message' => $message,
            ),
            admin_url( 'admin.php' )
        );

        wp_safe_redirect( $redirect_url );
        exit;
    }
}
