<?php
/**
 * Checkout URL helper.
 *
 * @package CommercePilot
 */

declare(strict_types=1);

namespace CommercePilot\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CheckoutService {

	public function __construct( private CartService $cart ) {}

	/**
	 * @return array{url:string}
	 */
	public function url( string $visitor_id ): array {
		$this->cart->bind_visitor( $visitor_id, true );

		return array(
			'url' => (string) wc_get_checkout_url(),
		);
	}
}
