<?php
/**
 * Uninstall CommercePilot.
 *
 * @package CommercePilot
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$settings = get_option( 'commercepilot_settings', array() );
if ( ! is_array( $settings ) || empty( $settings['delete_data_on_uninstall'] ) ) {
	return;
}

delete_option( 'commercepilot_settings' );
delete_option( 'commercepilot_version' );
delete_transient( 'commercepilot_usage' );
delete_transient( 'commercepilot_site' );
delete_transient( 'commercepilot_health' );
delete_transient( 'commercepilot_connection_status' );
