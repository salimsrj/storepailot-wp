<?php
/**
 * Safe logger. Never writes secrets or payment data.
 *
 * @package CommercePilot
 */

declare(strict_types=1);

namespace CommercePilot;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Logger {

	public static function error( string $message, array $context = array() ): void {
		self::write( 'error', $message, $context );
	}

	public static function warning( string $message, array $context = array() ): void {
		self::write( 'warning', $message, $context );
	}

	public static function info( string $message, array $context = array() ): void {
		self::write( 'info', $message, $context );
	}

	/**
	 * @param array<string, mixed> $context
	 */
	private static function write( string $level, string $message, array $context ): void {
		$safe = self::redact( $context );

		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->log( $level, $message . ' ' . wp_json_encode( $safe ), array( 'source' => 'commercepilot' ) );
			return;
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'CommercePilot[' . $level . ']: ' . $message . ' ' . wp_json_encode( $safe ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * @param array<string, mixed> $context
	 * @return array<string, mixed>
	 */
	private static function redact( array $context ): array {
		$blocked = array( 'token', 'secret', 'password', 'authorization', 'site_token', 'site_secret', 'card', 'cvv' );
		$out     = array();

		foreach ( $context as $key => $value ) {
			$lower = strtolower( (string) $key );
			foreach ( $blocked as $needle ) {
				if ( str_contains( $lower, $needle ) ) {
					$out[ $key ] = '[redacted]';
					continue 2;
				}
			}
			$out[ $key ] = is_scalar( $value ) ? $value : gettype( $value );
		}

		return $out;
	}
}
