<?php
/**
 * Plugin Name:       Hotmart MemberPress Pro
 * Plugin URI:        https://github.com/soyjavierquiroz/hotmart-memberpress-pro
 * Description:       Securely connects Hotmart webhooks with MemberPress memberships.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            Javier Quiroz
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       hotmart-memberpress-pro
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HMP_VERSION', '0.1.0' );
define( 'HMP_PLUGIN_FILE', __FILE__ );
define( 'HMP_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'HMP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once HMP_PLUGIN_PATH . 'includes/class-autoloader.php';

\HMP\Autoloader::register();

register_activation_hook( HMP_PLUGIN_FILE, array( '\HMP\Activator', 'activate' ) );
register_deactivation_hook( HMP_PLUGIN_FILE, array( '\HMP\Activator', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		load_plugin_textdomain( 'hotmart-memberpress-pro', false, dirname( plugin_basename( HMP_PLUGIN_FILE ) ) . '/languages' );
		\HMP\Plugin::instance()->run();
	}
);

add_action(
	'admin_notices',
	static function () {
		if ( current_user_can( 'manage_options' ) && ! class_exists( 'MeprTransaction' ) ) {
			echo '<div class="notice notice-warning"><p>';
			esc_html_e( 'Hotmart MemberPress Pro is active, but MemberPress is not available. Webhook events that require MemberPress will be stored as failed until MemberPress is activated.', 'hotmart-memberpress-pro' );
			echo '</p></div>';
		}
	}
);
