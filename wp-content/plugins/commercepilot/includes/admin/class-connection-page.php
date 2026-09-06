<?php
/**
 * Connection page.
 *
 * @package CommercePilot
 */

declare(strict_types=1);

namespace CommercePilot\Admin;

use CommercePilot\Connection;
use CommercePilot\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ConnectionPage {

	public function __construct(
		private Settings $settings,
		private ?Connection $connection
	) {}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$status  = $this->connection ? $this->connection->status() : array( 'status' => 'disconnected', 'label' => __( 'Not Connected', 'commercepilot' ) );
		$site_id = (string) $this->settings->get( 'site_id' );
		$masked  = $site_id === '' ? '' : ( strlen( $site_id ) <= 8 ? $site_id : substr( $site_id, 0, 4 ) . '…' . substr( $site_id, -4 ) );
		include COMMERCEPILOT_PATH . 'admin/views/connection.php';
	}
}
