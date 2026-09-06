<?php
/**
 * Public site config and admin connection endpoints.
 *
 * @package CommercePilot
 */

declare(strict_types=1);

namespace CommercePilot\REST;

use CommercePilot\ApiClient;
use CommercePilot\Connection;
use CommercePilot\Settings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SiteController {

	public function __construct(
		private Settings $settings,
		private Connection $connection,
		private ApiClient $api
	) {}

	public function public_site(): WP_REST_Response {
		return new WP_REST_Response( $this->settings->public_config(), 200 );
	}

	public function connect( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! wp_verify_nonce( (string) $request->get_header( 'x-wp-nonce' ), 'wp_rest' ) ) {
			return new WP_Error( 'commercepilot_invalid_nonce', __( 'Invalid request.', 'commercepilot' ), array( 'status' => 403 ) );
		}

		$result = $this->connection->connect(
			(string) $request->get_param( 'api_url' ),
			(string) $request->get_param( 'site_id' ),
			(string) $request->get_param( 'site_token' ),
			(string) $request->get_param( 'site_secret' )
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( array( 'status' => 'connected' ), 200 );
	}

	public function disconnect( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! wp_verify_nonce( (string) $request->get_header( 'x-wp-nonce' ), 'wp_rest' ) ) {
			return new WP_Error( 'commercepilot_invalid_nonce', __( 'Invalid request.', 'commercepilot' ), array( 'status' => 403 ) );
		}

		$this->connection->disconnect();
		return new WP_REST_Response( array( 'status' => 'disconnected' ), 200 );
	}

	public function test( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! wp_verify_nonce( (string) $request->get_header( 'x-wp-nonce' ), 'wp_rest' ) ) {
			return new WP_Error( 'commercepilot_invalid_nonce', __( 'Invalid request.', 'commercepilot' ), array( 'status' => 403 ) );
		}

		$result = $this->connection->test();
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( array( 'status' => 'connected' ), 200 );
	}

	public function rotate( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! wp_verify_nonce( (string) $request->get_header( 'x-wp-nonce' ), 'wp_rest' ) ) {
			return new WP_Error( 'commercepilot_invalid_nonce', __( 'Invalid request.', 'commercepilot' ), array( 'status' => 403 ) );
		}

		$result = $this->connection->rotate();
		return is_wp_error( $result ) ? $result : new WP_REST_Response( $result, 200 );
	}

	public function usage(): WP_REST_Response|WP_Error {
		$result = $this->api->usage();
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$data = is_array( $result['data'] ?? null ) ? $result['data'] : $result;
		return new WP_REST_Response(
			array(
				'used'      => absint( $data['used'] ?? 0 ),
				'limit'     => absint( $data['limit'] ?? 0 ),
				'remaining' => absint( $data['remaining'] ?? 0 ),
			),
			200
		);
	}

	public function save_settings( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! wp_verify_nonce( (string) $request->get_header( 'x-wp-nonce' ), 'wp_rest' ) ) {
			return new WP_Error( 'commercepilot_invalid_nonce', __( 'Invalid request.', 'commercepilot' ), array( 'status' => 403 ) );
		}

		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		unset( $params['site_token'], $params['site_secret'] );
		$this->settings->update( $params );

		if ( $this->settings->is_connected() ) {
			$this->connection->sync_assistant_settings();
		}

		return new WP_REST_Response( array( 'saved' => true ), 200 );
	}

	public function mask_site_id(): string {
		$id = (string) $this->settings->get( 'site_id' );
		if ( $id === '' ) {
			return '';
		}
		return strlen( $id ) <= 8 ? $id : substr( $id, 0, 4 ) . '…' . substr( $id, -4 );
	}
}
