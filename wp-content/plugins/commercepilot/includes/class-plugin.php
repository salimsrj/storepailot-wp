<?php
/**
 * Plugin bootstrap.
 *
 * @package CommercePilot
 */

declare(strict_types=1);

namespace CommercePilot;

use CommercePilot\Admin\Admin;
use CommercePilot\Frontend\ChatWidget;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {

	private static ?self $instance = null;

	private Settings $settings;
	private Loader $loader;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		$this->settings = new Settings();
		$this->loader   = new Loader();
	}

	public function boot(): void {
		load_plugin_textdomain( 'commercepilot', false, dirname( COMMERCEPILOT_BASENAME ) . '/languages' );

		if ( ! $this->woocommerce_available() ) {
			add_action( 'admin_notices', array( $this, 'missing_woocommerce_notice' ) );
			if ( is_admin() ) {
				( new Admin( $this->settings ) )->register();
			}
			return;
		}

		$this->settings->ensure_defaults();

		$api        = new ApiClient( $this->settings );
		$connection = new Connection( $this->settings, $api );
		$rest       = new Rest( $this->settings, $api );
		$assets     = new Assets( $this->settings );
		$privacy    = new Privacy();

		$this->loader->add_action( 'init', $assets, 'register' );
		$rest->register();
		$privacy->register();

		if ( is_admin() ) {
			( new Admin( $this->settings, $api, $connection ) )->register();
		}

		( new ChatWidget( $this->settings, $assets ) )->register();

		$this->loader->run();
		do_action( 'commercepilot_booted', $this );
	}

	public function settings(): Settings {
		return $this->settings;
	}

	public function missing_woocommerce_notice(): void {
		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'CommercePilot requires WooCommerce to be installed and active.', 'commercepilot' );
		echo '</p></div>';
	}

	private function woocommerce_available(): bool {
		return class_exists( 'WooCommerce' );
	}
}
