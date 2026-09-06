<?php
/**
 * Encryption, HMAC, and request validation.
 *
 * @package CommercePilot
 */

declare(strict_types=1);

namespace CommercePilot;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Security {

	public const HMAC_TOLERANCE = 300;

	public static function encrypt( string $value ): string {
		if ( $value === '' ) {
			return '';
		}

		$key = self::key();
		$iv  = random_bytes( 16 );
		$raw = openssl_encrypt( $value, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

		if ( false === $raw ) {
			return '';
		}

		return base64_encode( $iv . $raw );
	}

	public static function decrypt( string $value ): string {
		if ( $value === '' ) {
			return '';
		}

		$decoded = base64_decode( $value, true );
		if ( false === $decoded || strlen( $decoded ) < 17 ) {
			return '';
		}

		$iv  = substr( $decoded, 0, 16 );
		$raw = substr( $decoded, 16 );
		$out = openssl_decrypt( $raw, 'AES-256-CBC', self::key(), OPENSSL_RAW_DATA, $iv );

		return false === $out ? '' : $out;
	}

	public static function sign( string $timestamp, string $body, string $secret ): string {
		return hash_hmac( 'sha256', $timestamp . '.' . $body, $secret );
	}

	/**
	 * Binds an outbound signature to the request it was minted for.
	 *
	 * Every GET has an empty body, so without the method and path in the signed
	 * payload two GETs in the same second produce an identical digest: the
	 * second one trips Laravel's replay protection. Must stay in step with
	 * HmacSigner::signRequest() in the Laravel backend.
	 */
	public static function sign_request( string $timestamp, string $method, string $path, string $body, string $secret ): string {
		return self::sign( $timestamp, strtoupper( $method ) . '.' . self::canonicalize_path( $path ) . '.' . $body, $secret );
	}

	/**
	 * Sort query parameters so WordPress and Laravel sign the same string
	 * even when HTTP clients reorder ?a=&b=.
	 */
	public static function canonicalize_path( string $path ): string {
		$parts = explode( '?', $path, 2 );
		$base  = $parts[0];

		if ( ! isset( $parts[1] ) || $parts[1] === '' ) {
			return $base;
		}

		parse_str( $parts[1], $params );
		if ( $params === array() ) {
			return $base;
		}

		ksort( $params );

		return $base . '?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
	}

	public static function verify_signature( string $timestamp, string $body, string $secret, string $signature ): bool {
		if ( $secret === '' || $signature === '' || ! ctype_digit( $timestamp ) ) {
			return false;
		}

		$age = abs( time() - (int) $timestamp );
		if ( $age > self::HMAC_TOLERANCE ) {
			return false;
		}

		return hash_equals( self::sign( $timestamp, $body, $secret ), $signature );
	}

	public function verify_hmac_request( \WP_REST_Request $request, Settings $settings ): bool {
		$secret    = $settings->site_secret();
		$timestamp = (string) $request->get_header( 'x-commercepilot-timestamp' );
		$signature = (string) $request->get_header( 'x-commercepilot-signature' );
		$body      = strtoupper( $request->get_method() ) === 'GET' ? '' : (string) $request->get_body();

		if ( ! self::verify_signature( $timestamp, $body, $secret, $signature ) ) {
			return false;
		}

		$replay_key = 'commercepilot_hmac_' . hash( 'sha256', $settings->get( 'site_id' ) . $signature );
		if ( get_transient( $replay_key ) ) {
			return false;
		}

		set_transient( $replay_key, 1, self::HMAC_TOLERANCE );
		return true;
	}

	public function verify_public_rest( \WP_REST_Request $request, string $bucket = 'default' ): bool|\WP_Error {
		$nonce = (string) $request->get_header( 'x-wp-nonce' );
		if ( $nonce === '' || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new \WP_Error( 'commercepilot_invalid_nonce', __( 'Invalid request.', 'commercepilot' ), array( 'status' => 403 ) );
		}

		$limiter = new RateLimiter();
		if ( ! $limiter->allow( $request, $bucket ) ) {
			return new \WP_Error( 'commercepilot_rate_limited', __( 'Too many requests. Please try again later.', 'commercepilot' ), array( 'status' => 429 ) );
		}

		return true;
	}

	public static function is_uuid( string $value ): bool {
		return (bool) preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value );
	}

	public static function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	private static function key(): string {
		return hash( 'sha256', wp_salt( 'auth' ) . wp_salt( 'secure_auth' ), true );
	}
}
