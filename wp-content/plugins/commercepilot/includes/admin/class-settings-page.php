<?php
/**
 * Settings page.
 *
 * @package CommercePilot
 */

declare(strict_types=1);

namespace CommercePilot\Admin;

use CommercePilot\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SettingsPage {

	public function __construct( private Settings $settings ) {}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = $this->settings->all();
		include COMMERCEPILOT_PATH . 'admin/views/settings.php';
	}
}
