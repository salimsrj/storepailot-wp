<?php
/**
 * Visitor identity helpers.
 *
 * @package CommercePilot
 */

declare(strict_types=1);

namespace CommercePilot;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Visitor {

	public static function validate( string $visitor_id ): bool {
		return Security::is_uuid( $visitor_id );
	}

	public static function from_request( \WP_REST_Request $request ): string|\WP_Error {
		$value = sanitize_text_field( (string) ( $request->get_param( 'visitor_id' ) ?? '' ) );
		if ( ! self::validate( $value ) ) {
			return new \WP_Error( 'commercepilot_invalid_visitor', __( 'Invalid visitor.', 'commercepilot' ), array( 'status' => 400 ) );
		}

		return $value;
	}
}
