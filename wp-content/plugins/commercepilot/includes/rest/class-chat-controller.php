<?php
/**
 * Browser chat proxy to Laravel.
 *
 * @package CommercePilot
 */

declare(strict_types=1);

namespace CommercePilot\REST;

use CommercePilot\ApiClient;
use CommercePilot\Security;
use CommercePilot\Settings;
use CommercePilot\Visitor;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ChatController {

	public function __construct(
		private Settings $settings,
		private ApiClient $api
	) {}

	public function send( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! $this->settings->is_connected() || ! $this->settings->get( 'enabled' ) ) {
			return new WP_Error( 'commercepilot_disabled', __( 'The assistant is not available.', 'commercepilot' ), array( 'status' => 503 ) );
		}

		$visitor = Visitor::from_request( $request );
		if ( is_wp_error( $visitor ) ) {
			return $visitor;
		}

		$message = sanitize_text_field( (string) $request->get_param( 'message' ) );
		if ( $message === '' || strlen( $message ) > 4000 ) {
			return new WP_Error( 'commercepilot_invalid_message', __( 'Please enter a valid message.', 'commercepilot' ), array( 'status' => 400 ) );
		}

		$conversation = sanitize_text_field( (string) ( $request->get_param( 'conversation_id' ) ?? '' ) );
		if ( $conversation !== '' && ! Security::is_uuid( $conversation ) ) {
			return new WP_Error( 'commercepilot_invalid_conversation', __( 'Invalid conversation.', 'commercepilot' ), array( 'status' => 400 ) );
		}

		$payload = array(
			'visitor_id' => $visitor,
			'message'    => $message,
		);
		if ( $conversation !== '' ) {
			$payload['conversation_id'] = $conversation;
		}

		$result = $this->api->chat( $payload );
		if ( is_wp_error( $result ) ) {
			return $this->error_response( $result );
		}

		$data = is_array( $result['data'] ?? null ) ? $result['data'] : $result;
		return new WP_REST_Response( $this->sanitize_chat( $data ), 200 );
	}

	/**
	 * Returns messages added to a conversation since the caller's cursor. Used
	 * by the widget to receive replies typed by a human agent in wp-admin.
	 */
	public function poll( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! $this->settings->is_connected() || ! $this->settings->get( 'enabled' ) ) {
			return new WP_Error( 'commercepilot_disabled', __( 'The assistant is not available.', 'commercepilot' ), array( 'status' => 503 ) );
		}

		$visitor = Visitor::from_request( $request );
		if ( is_wp_error( $visitor ) ) {
			return $visitor;
		}

		$conversation = sanitize_text_field( (string) ( $request->get_param( 'conversation_id' ) ?? '' ) );
		if ( ! Security::is_uuid( $conversation ) ) {
			return new WP_Error( 'commercepilot_invalid_conversation', __( 'Invalid conversation.', 'commercepilot' ), array( 'status' => 400 ) );
		}

		// Laravel re-checks that this visitor owns the conversation, so a
		// guessed conversation id cannot expose someone else's thread.
		$args = array(
			'visitor_id' => $visitor,
		);
		$after_id = absint( $request->get_param( 'after_id' ) ?? 0 );
		if ( $after_id > 0 ) {
			$args['after_id'] = $after_id;
		}

		$result = $this->api->conversation_messages( $conversation, $args );

		if ( is_wp_error( $result ) ) {
			return $this->error_response( $result );
		}

		$data = is_array( $result['data'] ?? null ) ? $result['data'] : $result;

		return new WP_REST_Response(
			array(
				'conversation_id' => sanitize_text_field( (string) ( $data['conversation_id'] ?? '' ) ),
				'mode'            => self::mode( $data['mode'] ?? '' ),
				'messages'        => self::sanitize_messages( $data['messages'] ?? array() ),
			),
			200
		);
	}

	/**
	 * @param mixed $messages
	 * @return list<array<string, mixed>>
	 */
	private static function sanitize_messages( mixed $messages ): array {
		$clean = array();

		foreach ( (array) $messages as $message ) {
			if ( ! is_array( $message ) ) {
				continue;
			}
			// Tool and system turns are internal bookkeeping, never shown.
			if ( ! in_array( $message['role'] ?? '', array( 'user', 'assistant' ), true ) ) {
				continue;
			}
			$clean[] = self::sanitize_message( $message );
		}

		return $clean;
	}

	/**
	 * @param array<string, mixed> $message
	 * @return array<string, mixed>
	 */
	private static function sanitize_message( array $message ): array {
		return array(
			'id'          => absint( $message['id'] ?? 0 ),
			'role'        => ( $message['role'] ?? '' ) === 'user' ? 'user' : 'assistant',
			'content'     => sanitize_textarea_field( (string) ( $message['content'] ?? '' ) ),
			'author'      => ( $message['author'] ?? '' ) === 'human' ? 'human' : 'ai',
			'author_name' => sanitize_text_field( (string) ( $message['author_name'] ?? '' ) ),
		);
	}

	private static function mode( mixed $mode ): string {
		return $mode === 'human' ? 'human' : 'ai';
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>
	 */
	private function sanitize_chat( array $data ): array {
		$message = is_array( $data['message'] ?? null ) ? $data['message'] : array();
		$usage   = is_array( $data['usage'] ?? null ) ? $data['usage'] : array();
		$products = array();

		foreach ( (array) ( $data['products'] ?? array() ) as $product ) {
			if ( ! is_array( $product ) ) {
				continue;
			}
			$products[] = array(
				'id'           => absint( $product['id'] ?? 0 ),
				'name'         => sanitize_text_field( (string) ( $product['name'] ?? '' ) ),
				'price'        => sanitize_text_field( (string) ( $product['price'] ?? '' ) ),
				'currency'     => sanitize_text_field( (string) ( $product['currency'] ?? '' ) ),
				'image'        => esc_url_raw( (string) ( $product['image'] ?? '' ) ),
				'url'          => esc_url_raw( (string) ( $product['url'] ?? '' ) ),
				'stock_status' => sanitize_text_field( (string) ( $product['stock_status'] ?? '' ) ),
			);
		}

		// In human mode Laravel returns no assistant message: the agent replies
		// later and the widget picks it up by polling.
		$has_content = trim( (string) ( $message['content'] ?? '' ) ) !== '';

		return array(
			'conversation_id' => sanitize_text_field( (string) ( $data['conversation_id'] ?? '' ) ),
			'mode'            => self::mode( $data['mode'] ?? '' ),
			'message'         => $has_content ? self::sanitize_message( $message ) : null,
			'products'        => $products,
			'usage'           => array(
				'used'      => absint( $usage['used'] ?? 0 ),
				'limit'     => absint( $usage['limit'] ?? 0 ),
				'remaining' => absint( $usage['remaining'] ?? 0 ),
			),
		);
	}

	private function error_response( WP_Error $error ): WP_REST_Response {
		$data   = $error->get_error_data();
		$status = is_array( $data ) ? absint( $data['status'] ?? 503 ) : 503;
		$body   = array(
			'error' => array(
				'code'    => $error->get_error_code(),
				'message' => $error->get_error_message(),
			),
		);

		if ( is_array( $data ) && ! empty( $data['upgrade_required'] ) ) {
			$body['error']['upgrade_required'] = true;
			$body['error']['upgrade_url']      = esc_url_raw( (string) ( $data['upgrade_url'] ?? '' ) );
		}

		return new WP_REST_Response( $body, $status ?: 503 );
	}
}
