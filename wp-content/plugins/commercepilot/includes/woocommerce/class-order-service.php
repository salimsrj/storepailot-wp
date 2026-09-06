<?php
/**
 * Reserved for V2 order tracking. V1 does not create or expose orders.
 *
 * @package CommercePilot
 */

declare(strict_types=1);

namespace CommercePilot\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class OrderService {

	/**
	 * Intentionally unused in V1.
	 *
	 * @return array<int, mixed>
	 */
	public function get_customer_orders( int $user_id ): array {
		unset( $user_id );
		return array();
	}
}
