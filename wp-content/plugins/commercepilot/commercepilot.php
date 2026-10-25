<?php
/**
 * Plugin Name: CommercePilot
 * Plugin URI: https://commercepilot.com
 * Description: AI sales assistant for WooCommerce. Answers shopper questions, recommends products, and adds them to the cart.
 * Version: 1.2.0
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 * Author: CommercePilot
 * Author URI: https://commercepilot.com
 * Text Domain: commercepilot
 * Domain Path: /languages
 * WC requires at least: 8.0
 * WC tested up to: 11.1
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package CommercePilot
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'COMMERCEPILOT_VERSION', '1.2.0' );
define( 'COMMERCEPILOT_FILE', __FILE__ );
define( 'COMMERCEPILOT_PATH', plugin_dir_path( __FILE__ ) );
define( 'COMMERCEPILOT_URL', plugin_dir_url( __FILE__ ) );
define( 'COMMERCEPILOT_BASENAME', plugin_basename( __FILE__ ) );

// Service endpoints. Define any of these in wp-config.php to point the plugin
// at a staging or local backend.
if ( ! defined( 'COMMERCEPILOT_API_URL' ) ) {
	define( 'COMMERCEPILOT_API_URL', 'https://api.commercepilot.com' );
}
if ( ! defined( 'COMMERCEPILOT_SIGNUP_URL' ) ) {
	define( 'COMMERCEPILOT_SIGNUP_URL', 'https://app.commercepilot.com/register' );
}
if ( ! defined( 'COMMERCEPILOT_PLANS_URL' ) ) {
	define( 'COMMERCEPILOT_PLANS_URL', 'https://app.commercepilot.com/dashboard/plans' );
}

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

// Declare compatibility with WooCommerce High-Performance Order Storage. The
// plugin never reads or writes orders, so it is safe with either storage.
add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', COMMERCEPILOT_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', COMMERCEPILOT_FILE, true );
		}
	}
);

register_activation_hook( COMMERCEPILOT_FILE, array( CommercePilot\Activator::class, 'activate' ) );
register_deactivation_hook( COMMERCEPILOT_FILE, array( CommercePilot\Deactivator::class, 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function (): void {
		CommercePilot\Plugin::instance()->boot();
	}
);
