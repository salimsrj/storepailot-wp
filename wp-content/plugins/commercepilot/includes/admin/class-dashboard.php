<?php
/**
 * Dashboard page.
 *
 * @package CommercePilot
 */

declare(strict_types=1);

namespace CommercePilot\Admin;

use CommercePilot\ApiClient;
use CommercePilot\Connection;
use CommercePilot\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Dashboard {

	public function __construct(
		private Settings $settings,
		private ?ApiClient $api,
		private ?Connection $connection
	) {}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$status  = $this->connection ? $this->connection->status() : array( 'status' => 'disconnected', 'label' => __( 'Not Connected', 'commercepilot' ) );
		$usage   = array( 'used' => 0, 'limit' => 0, 'remaining' => 0 );
		$site    = array();
		$health  = array(
			'wordpress'   => true,
			'woocommerce' => class_exists( 'WooCommerce' ),
			'plugin'      => COMMERCEPILOT_VERSION,
			'configured'  => $this->settings->is_connected(),
			'api'         => false,
			'auth'        => false,
			'chatbot'     => false,
		);

		if ( $this->api && $this->settings->is_connected() ) {
			$raw = $this->api->usage();
			if ( ! is_wp_error( $raw ) ) {
				$data  = is_array( $raw['data'] ?? null ) ? $raw['data'] : $raw;
				$usage = array(
					'used'      => absint( $data['used'] ?? 0 ),
					'limit'     => absint( $data['limit'] ?? 0 ),
					'remaining' => absint( $data['remaining'] ?? 0 ),
				);
			}
			$site_raw = $this->api->site();
			if ( ! is_wp_error( $site_raw ) ) {
				$site            = is_array( $site_raw['data'] ?? null ) ? $site_raw['data'] : $site_raw;
				$health['api']   = true;
				$health['auth']  = true;
				$health['chatbot'] = (bool) $this->settings->get( 'enabled' );
			}
		}

		include COMMERCEPILOT_PATH . 'admin/views/dashboard.php';
	}
}
