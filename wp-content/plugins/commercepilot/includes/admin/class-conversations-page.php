<?php
/**
 * Conversations inbox page.
 *
 * @package CommercePilot
 */

declare(strict_types=1);

namespace CommercePilot\Admin;

use CommercePilot\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ConversationsPage {

	public function __construct( private Settings $settings ) {}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$connected = $this->settings->is_connected();
		include COMMERCEPILOT_PATH . 'admin/views/conversations.php';
	}
}
