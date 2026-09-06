<?php
/**
 * Lightweight per-session / per-IP protection.
 *
 * @package CommercePilot
 */

declare(strict_types=1);

namespace CommercePilot;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RateLimiter {

	private const WINDOW = 60;

	/**
	 * Requests allowed per window, per bucket. Polling for human-agent replies
	 * is cheap and frequent, so it gets its own allowance and cannot starve a
	 * visitor's ability to actually send a message.
	 *
	 * @var array<string, int>
	 */
	private const LIMITS = array(
		'default' => 40,
		'poll'    => 120,
	);

	public function allow( \WP_REST_Request $request, string $bucket = 'default' ): bool {
		$limit   = self::LIMITS[ $bucket ] ?? self::LIMITS['default'];
		$visitor = sanitize_text_field( (string) ( $request->get_param( 'visitor_id' ) ?? '' ) );
		$ip      = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key     = 'commercepilot_rl_' . hash( 'sha256', $bucket . '|' . $visitor . '|' . $ip . '|' . wp_salt( 'nonce' ) );
		$count   = (int) get_transient( $key );

		if ( $count >= $limit ) {
			return false;
		}

		set_transient( $key, $count + 1, self::WINDOW );
		return true;
	}
}
