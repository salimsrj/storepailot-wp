<?php
/**
 * Plugin activation.
 *
 * @package CommercePilot
 */

declare(strict_types=1);

namespace CommercePilot;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Activator {

	public static function activate(): void {
		if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
			deactivate_plugins( COMMERCEPILOT_BASENAME );
			wp_die( esc_html__( 'CommercePilot requires PHP 8.1 or higher.', 'commercepilot' ) );
		}

		global $wp_version;
		if ( isset( $wp_version ) && version_compare( (string) $wp_version, '6.0', '<' ) ) {
			deactivate_plugins( COMMERCEPILOT_BASENAME );
			wp_die( esc_html__( 'CommercePilot requires WordPress 6.0 or higher.', 'commercepilot' ) );
		}

		if ( ! self::woocommerce_active() ) {
			deactivate_plugins( COMMERCEPILOT_BASENAME );
			wp_die( esc_html__( 'CommercePilot requires WooCommerce to be installed and active.', 'commercepilot' ) );
		}

		$settings = new Settings();
		$settings->ensure_defaults();

		update_option( 'commercepilot_version', COMMERCEPILOT_VERSION, false );
		flush_rewrite_rules();
	}

	private static function woocommerce_active(): bool {
		return class_exists( 'WooCommerce' ) || in_array( 'woocommerce/woocommerce.php', (array) get_option( 'active_plugins', array() ), true );
	}
}
