<?php
/**
 * Plugin Name: WP Debug Viewer
 * Plugin URI: https://netservice.jp/
 * Description: 管理画面から debug.log を安全に閲覧・管理するツール。
 * Version: 0.01
 * Author: Netservice
 * Author URI: https://netservice.jp/
 * License: GPLv2 or later
 * Text Domain: wp-debug-viewer
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * @package wp-debug-viewer
 */

defined( 'ABSPATH' ) || exit;

define( 'OF_WPDV_VERSION', '0.01' );
define( 'OF_WPDV_PLUGIN_FILE', __FILE__ );
define( 'OF_WPDV_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'OF_WPDV_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once OF_WPDV_PLUGIN_DIR . 'includes/class-of-wpdv-plugin.php';

Of_Wpdv_Plugin::instance()->init();
