<?php
/**
 * Admin inbox proxy: conversation history and human handover.
 *
 * @package CommercePilot
 */

declare(strict_types=1);

namespace CommercePilot\REST;

use CommercePilot\ApiClient;
use CommercePilot\Security;
use CommercePilot\Settings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ConversationController {

	public function __construct(
		private Settings $settings,
		private ApiClient $api
	) {}

	public function index( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$guard = $this->guard( $request );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$mode = (string) ( $request->get_param( 'mode' ) ?? '' );

		$result = $this->api->conversations(
			array(
				'per_page' => min( 100, max( 1, absint( $request->get_param( 'per_page' ) ?? 25 ) ) ),
				'mode'     => in_array( $mode, array( 'ai', 'human' ), true ) ? $mode : '',
			)
		);

		if ( is_wp_error( $result ) ) {
			return $this->error_response( $result );
		}

		$items = array();
		foreach ( (array) ( $result['data'] ?? array() ) as $item ) {
			if ( is_array( $item ) ) {
				$items[] = $this->sanitize_conversation( $item );
			}
		}

		return new WP_REST_Response(
			array(
				'conversations' => $items,
				'total'         => absint( $result['meta']['total'] ?? count( $items ) ),
			),
			200
		);
	}

	public function show( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$guard = $this->guard( $request );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$uuid = $this->uuid( $request );
		if ( is_wp_error( $uuid ) ) {
			return $uuid;
		}

		$result = $this->api->conversation( $uuid );
		if ( is_wp_error( $result ) ) {
			return $this->error_response( $result );
		}

		$data = is_array( $result['data'] ?? null ) ? $result['data'] : $result;
		return new WP_REST_Response( $this->sanitize_conversation( $data ), 200 );
	}

	public function messages( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$guard = $this->guard( $request );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$uuid = $this->uuid( $request );
		if ( is_wp_error( $uuid ) ) {
			return $uuid;
		}

		$result = $this->api->conversation_messages(
			$uuid,
			array( 'after_id' => absint( $request->get_param( 'after_id' ) ?? 0 ) )
		);

		if ( is_wp_error( $result ) ) {
			return $this->error_response( $result );
		}

		$data = is_array( $result['data'] ?? null ) ? $result['data'] : $result;

		return new WP_REST_Response(
			array(
				'conversation_id' => sanitize_text_field( (string) ( $data['conversation_id'] ?? '' ) ),
				'mode'            => $this->mode( $data['mode'] ?? '' ),
				'messages'        => $this->sanitize_messages( $data['messages'] ?? array() ),
			),
			200
		);
	}

	public function take_over( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$guard = $this->guard( $request );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$uuid = $this->uuid( $request );
		if ( is_wp_error( $uuid ) ) {
			return $uuid;
		}

		$result = $this->api->take_over( $uuid, $this->agent_name() );
		if ( is_wp_error( $result ) ) {
			return $this->error_response( $result );
		}

		$data = is_array( $result['data'] ?? null ) ? $result['data'] : $result;
		return new WP_REST_Response( $this->sanitize_conversation( $data ), 200 );
	}

	public function release( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$guard = $this->guard( $request );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$uuid = $this->uuid( $request );
		if ( is_wp_error( $uuid ) ) {
			return $uuid;
		}

		$result = $this->api->release( $uuid );
		if ( is_wp_error( $result ) ) {
			return $this->error_response( $result );
		}

		$data = is_array( $result['data'] ?? null ) ? $result['data'] : $result;
		return new WP_REST_Response( $this->sanitize_conversation( $data ), 200 );
	}

	public function reply( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$guard = $this->guard( $request );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$uuid = $this->uuid( $request );
		if ( is_wp_error( $uuid ) ) {
			return $uuid;
		}

		$content = sanitize_textarea_field( (string) ( $request->get_param( 'content' ) ?? '' ) );
		if ( $content === '' || strlen( $content ) > 4000 ) {
			return new WP_Error( 'commercepilot_invalid_message', __( 'Please enter a valid reply.', 'commercepilot' ), array( 'status' => 400 ) );
		}

		$result = $this->api->send_agent_message( $uuid, $content, $this->agent_name() );
		if ( is_wp_error( $result ) ) {
			return $this->error_response( $result );
		}

		$data = is_array( $result['data'] ?? null ) ? $result['data'] : $result;
		return new WP_REST_Response( $this->sanitize_message( $data ), 201 );
	}

	private function guard( WP_REST_Request $request ): true|WP_Error {
		if ( ! wp_verify_nonce( (string) $request->get_header( 'x-wp-nonce' ), 'wp_rest' ) ) {
			return new WP_Error( 'commercepilot_invalid_nonce', __( 'Invalid request.', 'commercepilot' ), array( 'status' => 403 ) );
		}

		if ( ! $this->settings->is_connected() ) {
			return new WP_Error( 'commercepilot_not_connected', __( 'CommercePilot is not connected.', 'commercepilot' ), array( 'status' => 503 ) );
		}

		return true;
	}

	private function uuid( WP_REST_Request $request ): string|WP_Error {
		$uuid = sanitize_text_field( (string) ( $request->get_param( 'uuid' ) ?? '' ) );
		if ( ! Security::is_uuid( $uuid ) ) {
			return new WP_Error( 'commercepilot_invalid_conversation', __( 'Invalid conversation.', 'commercepilot' ), array( 'status' => 400 ) );
		}

		return $uuid;
	}

	private function agent_name(): string {
		$user = wp_get_current_user();
		$name = $user instanceof \WP_User ? (string) $user->display_name : '';

		return sanitize_text_field( $name );
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>
	 */
	private function sanitize_conversation( array $data ): array {
		$last = is_array( $data['last_message'] ?? null ) ? $data['last_message'] : array();

		$conversation = array(
			'id'              => sanitize_text_field( (string) ( $data['id'] ?? '' ) ),
			'status'          => sanitize_text_field( (string) ( $data['status'] ?? '' ) ),
			'mode'            => $this->mode( $data['mode'] ?? '' ),
			'handover_by'     => sanitize_text_field( (string) ( $data['handover_by'] ?? '' ) ),
			'visitor_id'      => sanitize_text_field( (string) ( $data['visitor_id'] ?? '' ) ),
			'message_count'   => absint( $data['message_count'] ?? 0 ),
			'last_message_at' => sanitize_text_field( (string) ( $data['last_message_at'] ?? '' ) ),
			'last_message'    => $last === array() ? null : array(
				'role'    => sanitize_text_field( (string) ( $last['role'] ?? '' ) ),
				'preview' => sanitize_text_field( (string) ( $last['preview'] ?? '' ) ),
			),
		);

		if ( isset( $data['messages'] ) ) {
			$conversation['messages'] = $this->sanitize_messages( $data['messages'] );
		}

		return $conversation;
	}

	/**
	 * @param mixed $messages
	 * @return list<array<string, mixed>>
	 */
	private function sanitize_messages( mixed $messages ): array {
		$clean = array();

		foreach ( (array) $messages as $message ) {
			if ( ! is_array( $message ) ) {
				continue;
			}
			// Tool and system turns are internal bookkeeping, never shown.
			if ( ! in_array( $message['role'] ?? '', array( 'user', 'assistant' ), true ) ) {
				continue;
			}
			$clean[] = $this->sanitize_message( $message );
		}

		return $clean;
	}

	/**
	 * @param array<string, mixed> $message
	 * @return array<string, mixed>
	 */
	private function sanitize_message( array $message ): array {
		return array(
			'id'          => absint( $message['id'] ?? 0 ),
			'role'        => ( $message['role'] ?? '' ) === 'user' ? 'user' : 'assistant',
			'content'     => sanitize_textarea_field( (string) ( $message['content'] ?? '' ) ),
			'author'      => ( $message['author'] ?? '' ) === 'human' ? 'human' : 'ai',
			'author_name' => sanitize_text_field( (string) ( $message['author_name'] ?? '' ) ),
			'created_at'  => sanitize_text_field( (string) ( $message['created_at'] ?? '' ) ),
		);
	}

	private function mode( mixed $mode ): string {
		return $mode === 'human' ? 'human' : 'ai';
	}

	private function error_response( WP_Error $error ): WP_REST_Response {
		$data   = $error->get_error_data();
		$status = is_array( $data ) ? absint( $data['status'] ?? 502 ) : 502;

		return new WP_REST_Response(
			array(
				'error' => array(
					'code'    => $error->get_error_code(),
					'message' => $error->get_error_message(),
				),
			),
			$status ?: 502
		);
	}
}
