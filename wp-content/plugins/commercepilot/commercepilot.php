<?php
/**
 * Plugin Name: CommercePilot
 * Plugin URI: https://commercepilot.com
 * Description: AI Sales Assistant for WooCommerce. Connects your store to the CommercePilot SaaS backend.
 * Version: 1.1.0
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 * Author: CommercePilot
 * Author URI: https://commercepilot.com
 * Text Domain: commercepilot
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package CommercePilot
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'COMMERCEPILOT_VERSION', '1.1.0' );
define( 'COMMERCEPILOT_FILE', __FILE__ );
define( 'COMMERCEPILOT_PATH', plugin_dir_path( __FILE__ ) );
define( 'COMMERCEPILOT_URL', plugin_dir_url( __FILE__ ) );
define( 'COMMERCEPILOT_BASENAME', plugin_basename( __FILE__ ) );
define( 'COMMERCEPILOT_API_URL', 'http://storepailotadmin.test' );

if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'CommercePilot requires PHP 8.1 or higher.', 'commercepilot' );
			echo '</p></div>';
		}
	);
	return;
}

if ( file_exists( COMMERCEPILOT_PATH . 'vendor/autoload.php' ) ) {
	require_once COMMERCEPILOT_PATH . 'vendor/autoload.php';
}

require_once COMMERCEPILOT_PATH . 'includes/class-autoloader.php';
CommercePilot\Autoloader::register();

register_activation_hook( COMMERCEPILOT_FILE, array( CommercePilot\Activator::class, 'activate' ) );
register_deactivation_hook( COMMERCEPILOT_FILE, array( CommercePilot\Deactivator::class, 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function (): void {
		CommercePilot\Plugin::instance()->boot();
	}
);
