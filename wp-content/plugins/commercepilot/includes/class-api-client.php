<?php
/**
 * Server-side Laravel API client.
 *
 * @package CommercePilot
 */

declare(strict_types=1);

namespace CommercePilot;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ApiClient {

	public function __construct( private Settings $settings ) {}

	/**
	 * @param array<string, mixed> $body
	 * @return array<string, mixed>|\WP_Error
	 */
	public function chat( array $body ): array|\WP_Error {
		return $this->request( 'POST', '/api/v1/chat', $body, array( 'auth' => true ) );
	}

	/**
	 * Conversation history and handover. All of these are HMAC signed because
	 * only the WordPress server may reach them.
	 *
	 * @param array<string, mixed> $args
	 * @return array<string, mixed>|\WP_Error
	 */
	public function conversations( array $args = array() ): array|\WP_Error {
		return $this->request( 'GET', '/api/v1/conversations' . self::query( $args ), array(), array( 'auth' => true, 'hmac' => true ) );
	}

	/**
	 * @param array<string, mixed> $args
	 * @return array<string, mixed>|\WP_Error
	 */
	public function conversation( string $uuid, array $args = array() ): array|\WP_Error {
		return $this->request( 'GET', '/api/v1/conversations/' . rawurlencode( $uuid ) . self::query( $args ), array(), array( 'auth' => true, 'hmac' => true ) );
	}

	/**
	 * @param array<string, mixed> $args
	 * @return array<string, mixed>|\WP_Error
	 */
	public function conversation_messages( string $uuid, array $args = array() ): array|\WP_Error {
		return $this->request( 'GET', '/api/v1/conversations/' . rawurlencode( $uuid ) . '/messages' . self::query( $args ), array(), array( 'auth' => true, 'hmac' => true ) );
	}

	/**
	 * @return array<string, mixed>|\WP_Error
	 */
	public function take_over( string $uuid, string $agent = '' ): array|\WP_Error {
		$body = $agent !== '' ? array( 'agent' => $agent ) : array();
		return $this->request( 'POST', '/api/v1/conversations/' . rawurlencode( $uuid ) . '/takeover', $body, array( 'auth' => true, 'hmac' => true ) );
	}

	/**
	 * @return array<string, mixed>|\WP_Error
	 */
	public function release( string $uuid ): array|\WP_Error {
		return $this->request( 'POST', '/api/v1/conversations/' . rawurlencode( $uuid ) . '/release', array(), array( 'auth' => true, 'hmac' => true ) );
	}

	/**
	 * @return array<string, mixed>|\WP_Error
	 */
	public function send_agent_message( string $uuid, string $content, string $agent = '' ): array|\WP_Error {
		$body = array( 'content' => $content );
		if ( $agent !== '' ) {
			$body['agent'] = $agent;
		}

		return $this->request( 'POST', '/api/v1/conversations/' . rawurlencode( $uuid ) . '/messages', $body, array( 'auth' => true, 'hmac' => true ) );
	}

	/**
	 * @param array<string, mixed> $args
	 */
	private static function query( array $args ): string {
		$args = array_filter(
			$args,
			static fn ( $value ): bool => $value !== null && $value !== ''
		);

		return $args === array() ? '' : '?' . http_build_query( $args );
	}

	/**
	 * @return array<string, mixed>|\WP_Error
	 */
	public function site(): array|\WP_Error {
		$cached = get_transient( 'commercepilot_site' );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$result = $this->request( 'GET', '/api/v1/site', array(), array( 'auth' => true ) );
		if ( ! is_wp_error( $result ) ) {
			set_transient( 'commercepilot_site', $result, 5 * MINUTE_IN_SECONDS );
		}

		return $result;
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return array<string, mixed>|\WP_Error
	 */
	public function update_site( array $payload ): array|\WP_Error {
		delete_transient( 'commercepilot_site' );
		return $this->request( 'PATCH', '/api/v1/site', $payload, array( 'auth' => true ) );
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return array<string, mixed>|\WP_Error
	 */
	public function update_site_settings( array $payload ): array|\WP_Error {
		return $this->request( 'PATCH', '/api/v1/site/settings', $payload, array( 'auth' => true ) );
	}

	/**
	 * @return array<string, mixed>|\WP_Error
	 */
	public function usage(): array|\WP_Error {
		$cached = get_transient( 'commercepilot_usage' );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$result = $this->request( 'GET', '/api/v1/usage', array(), array( 'auth' => true ) );
		if ( ! is_wp_error( $result ) ) {
			set_transient( 'commercepilot_usage', $result, MINUTE_IN_SECONDS );
		}

		return $result;
	}

	/**
	 * @return array<string, mixed>|\WP_Error
	 */
	public function rotate_token(): array|\WP_Error {
		return $this->request( 'POST', '/api/v1/site/token/rotate', array(), array( 'auth' => true, 'hmac' => true ) );
	}

	/**
	 * @return array<string, mixed>|\WP_Error
	 */
	public function ping(): array|\WP_Error {
		return $this->request( 'GET', '/up', array(), array( 'auth' => false, 'timeout' => 8 ) );
	}

	/**
	 * @param array<string, mixed> $body
	 * @param array<string, mixed> $options
	 * @return array<string, mixed>|\WP_Error
	 */
	public function request( string $method, string $path, array $body = array(), array $options = array() ): array|\WP_Error {
		$auth    = (bool) ( $options['auth'] ?? false );
		$hmac    = (bool) ( $options['hmac'] ?? false );
		$timeout = (int) ( $options['timeout'] ?? 30 );
		$url     = $this->settings->api_url() . $path;
		$json    = $method !== 'GET' && $body !== array() ? wp_json_encode( $body ) : '';
		$raw     = is_string( $json ) ? $json : '';

		$headers = array(
			'Accept'       => 'application/json',
			'Content-Type' => 'application/json',
		);

		if ( $auth ) {
			$token = $this->settings->site_token();
			if ( $token === '' ) {
				return new \WP_Error( 'commercepilot_not_connected', __( 'CommercePilot is not connected.', 'commercepilot' ) );
			}
			$headers['Authorization'] = 'Bearer ' . $token;
		}

		if ( $hmac ) {
			$timestamp = (string) time();
			$headers['X-CommercePilot-Timestamp'] = $timestamp;
			$headers['X-CommercePilot-Signature'] = Security::sign_request( $timestamp, $method, $path, $raw, $this->settings->site_secret() );
		}

		$args = array(
			'method'      => $method,
			'timeout'     => $timeout,
			'redirection' => 0,
			'headers'     => $headers,
			'sslverify'   => true,
		);

		if ( $method !== 'GET' && $raw !== '' ) {
			$args['body'] = $raw;
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			Logger::error( 'Laravel request failed', array( 'path' => $path, 'code' => $response->get_error_code() ) );
			return new \WP_Error( 'commercepilot_unavailable', __( 'Sorry, the assistant is temporarily unavailable. Please try again.', 'commercepilot' ), array( 'status' => 503 ) );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$parsed = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( $status === 402 && is_array( $parsed ) ) {
			return $this->usage_limit_error( $parsed );
		}

		if ( $status < 200 || $status >= 300 ) {
			Logger::warning( 'Laravel returned an error status', array( 'path' => $path, 'status' => $status ) );
			$code    = is_array( $parsed ) ? (string) ( $parsed['error']['code'] ?? '' ) : '';
			$message = is_array( $parsed ) ? (string) ( $parsed['error']['message'] ?? '' ) : '';
			if ( $code === 'invalid_site_token' || $code === 'missing_site_token' ) {
				return new \WP_Error( 'commercepilot_expired', __( 'Connection expired. Please reconnect CommercePilot.', 'commercepilot' ), array( 'status' => 401 ) );
			}
			if ( $code === 'ai_quota_exceeded' ) {
				return new \WP_Error(
					'ai_quota_exceeded',
					__( 'The OpenAI quota for this CommercePilot account is exhausted. Add billing or a new API key in Laravel AI settings.', 'commercepilot' ),
					array( 'status' => 429 )
				);
			}
			if ( $code === 'ai_rate_limited' || $status === 429 ) {
				return new \WP_Error(
					'ai_rate_limited',
					__( 'The assistant is busy right now. Please try again in a moment.', 'commercepilot' ),
					array( 'status' => 429 )
				);
			}
			if ( $status === 404 ) {
				return new \WP_Error(
					'commercepilot_unavailable',
					__( 'CommercePilot API URL is not reachable. Check Connection settings.', 'commercepilot' ),
					array( 'status' => 404 )
				);
			}
			if ( $code !== '' && $message !== '' ) {
				return new \WP_Error( $code, $message, array( 'status' => $status ?: 502 ) );
			}
			return new \WP_Error( 'commercepilot_unavailable', __( 'Sorry, the assistant is temporarily unavailable. Please try again.', 'commercepilot' ), array( 'status' => $status ?: 502 ) );
		}

		if ( ! is_array( $parsed ) ) {
			Logger::warning( 'Malformed Laravel JSON', array( 'path' => $path ) );
			return new \WP_Error( 'commercepilot_invalid_response', __( 'Sorry, the assistant is temporarily unavailable. Please try again.', 'commercepilot' ), array( 'status' => 502 ) );
		}

		return $parsed;
	}

	/**
	 * @param array<string, mixed> $parsed
	 */
	private function usage_limit_error( array $parsed ): \WP_Error {
		$error = is_array( $parsed['error'] ?? null ) ? $parsed['error'] : array();
		return new \WP_Error(
			'usage_limit_reached',
			sanitize_text_field( (string) ( $error['message'] ?? __( 'Your free monthly messages are finished.', 'commercepilot' ) ) ),
			array(
				'status'           => 402,
				'upgrade_required' => true,
				'upgrade_url'      => esc_url_raw( (string) ( $error['upgrade_url'] ?? '' ) ),
			)
		);
	}
}
