<?php
/**
 * Account / upgrade page.
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

final class AccountPage {

	public function __construct(
		private Settings $settings,
		private ?ApiClient $api
	) {}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$site        = array();
		$upgrade_url = 'https://app.commercepilot.com/upgrade';

		if ( $this->api && $this->settings->is_connected() ) {
			$raw = $this->api->site();
			if ( ! is_wp_error( $raw ) ) {
				$site = is_array( $raw['data'] ?? null ) ? $raw['data'] : $raw;
			}
		}

		include COMMERCEPILOT_PATH . 'admin/views/account.php';
	}
}
