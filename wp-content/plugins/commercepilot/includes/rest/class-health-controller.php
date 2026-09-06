<?php
/**
 * Health diagnostics.
 *
 * @package CommercePilot
 */

declare(strict_types=1);

namespace CommercePilot\REST;

use CommercePilot\ApiClient;
use CommercePilot\Settings;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class HealthController {

	public function __construct(
		private Settings $settings,
		private ApiClient $api
	) {}

	public function public_health(): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'wordpress'   => true,
				'woocommerce' => class_exists( 'WooCommerce' ),
				'plugin'      => COMMERCEPILOT_VERSION,
				'configured'  => $this->settings->is_connected(),
				'chatbot'     => (bool) $this->settings->get( 'enabled' ) && $this->settings->is_connected(),
			),
			200
		);
	}

	public function admin_health(): WP_REST_Response {
		$woocommerce = class_exists( 'WooCommerce' );
		$configured  = $this->settings->is_connected();
		$laravel     = false;
		$auth        = false;

		if ( $configured ) {
			$site = $this->api->site();
			if ( ! is_wp_error( $site ) ) {
				$laravel = true;
				$auth    = true;
			} else {
				$ping    = $this->api->ping();
				$laravel = ! is_wp_error( $ping );
			}
		}

		return new WP_REST_Response(
			array(
				'wordpress'   => true,
				'woocommerce' => $woocommerce,
				'plugin'      => COMMERCEPILOT_VERSION,
				'configured'  => $configured,
				'api'         => $laravel,
				'auth'        => $auth,
				'chatbot'     => (bool) $this->settings->get( 'enabled' ) && $configured && $auth,
			),
			200
		);
	}
}
