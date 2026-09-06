<?php
/**
 * Usage page.
 *
 * @package CommercePilot
 */

declare(strict_types=1);

namespace CommercePilot\Admin;

use CommercePilot\ApiClient;
use CommercePilot\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class UsagePage {

	public function __construct(
		private Settings $settings,
		private ?ApiClient $api
	) {}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$usage = array(
			'used'      => 0,
			'limit'     => 0,
			'remaining' => 0,
		);
		$error = '';

		if ( ! $this->settings->is_connected() ) {
			$error = __( 'Connect CommercePilot to view usage.', 'commercepilot' );
		} elseif ( $this->api ) {
			$raw = $this->api->usage();
			if ( is_wp_error( $raw ) ) {
				$error = __( 'Usage is temporarily unavailable.', 'commercepilot' );
			} else {
				$data  = is_array( $raw['data'] ?? null ) ? $raw['data'] : $raw;
				$usage = array(
					'used'      => absint( $data['used'] ?? 0 ),
					'limit'     => absint( $data['limit'] ?? 0 ),
					'remaining' => absint( $data['remaining'] ?? 0 ),
				);
			}
		}

		include COMMERCEPILOT_PATH . 'admin/views/usage.php';
	}
}
