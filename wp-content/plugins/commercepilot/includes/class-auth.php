<?php
/**
 * REST permission callbacks.
 *
 * @package CommercePilot
 */

declare(strict_types=1);

namespace CommercePilot;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Auth {

	public function __construct(
		private Settings $settings,
		private Security $security
	) {}

	public function hmac( \WP_REST_Request $request ): bool|\WP_Error {
		if ( ! $this->settings->is_connected() ) {
			return new \WP_Error( 'commercepilot_not_connected', __( 'CommercePilot is not connected.', 'commercepilot' ), array( 'status' => 401 ) );
		}

		if ( ! $this->security->verify_hmac_request( $request, $this->settings ) ) {
			return new \WP_Error( 'commercepilot_invalid_signature', __( 'Invalid signature.', 'commercepilot' ), array( 'status' => 401 ) );
		}

		return true;
	}

	public function public_rest( \WP_REST_Request $request ): bool|\WP_Error {
		return $this->security->verify_public_rest( $request );
	}

	/**
	 * Same checks as public_rest, but on the higher-allowance polling bucket.
	 */
	public function public_poll( \WP_REST_Request $request ): bool|\WP_Error {
		return $this->security->verify_public_rest( $request, 'poll' );
	}

	public function admin(): bool {
		return Security::can_manage();
	}
}
