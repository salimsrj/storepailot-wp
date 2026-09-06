<?php
/**
 * Plugin deactivation.
 *
 * @package CommercePilot
 */

declare(strict_types=1);

namespace CommercePilot;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Deactivator {

	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'commercepilot_health_check' );
		delete_transient( 'commercepilot_usage' );
		delete_transient( 'commercepilot_site' );
		delete_transient( 'commercepilot_health' );
		flush_rewrite_rules();
	}
}
