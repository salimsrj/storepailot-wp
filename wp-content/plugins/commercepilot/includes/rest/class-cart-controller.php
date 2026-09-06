<?php
/**
 * Cart REST endpoints for Laravel HMAC and the browser widget.
 *
 * @package CommercePilot
 */

declare(strict_types=1);

namespace CommercePilot\REST;

use CommercePilot\Visitor;
use CommercePilot\WooCommerce\CartService;
use CommercePilot\WooCommerce\CheckoutService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CartController {

	public function __construct(
		private CartService $cart,
		private CheckoutService $checkout
	) {}

	public function get_cart( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$visitor = Visitor::from_request( $request );
		if ( is_wp_error( $visitor ) ) {
			return $visitor;
		}

		return new WP_REST_Response( $this->cart->get_cart( $visitor ), 200 );
	}

	public function add_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$visitor = Visitor::from_request( $request );
		if ( is_wp_error( $visitor ) ) {
			return $visitor;
		}

		$result = $this->cart->add_item(
			$visitor,
			array(
				'product_id'   => $request->get_param( 'product_id' ),
				'quantity'     => $request->get_param( 'quantity' ) ?? 1,
				'variation_id' => $request->get_param( 'variation_id' ),
			)
		);

		return is_wp_error( $result ) ? $result : new WP_REST_Response( $result, 200 );
	}

	public function remove_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$visitor = Visitor::from_request( $request );
		if ( is_wp_error( $visitor ) ) {
			return $visitor;
		}

		$result = $this->cart->remove_item( $visitor, (string) $request->get_param( 'item_key' ) );
		return is_wp_error( $result ) ? $result : new WP_REST_Response( $result, 200 );
	}

	public function update_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$visitor = Visitor::from_request( $request );
		if ( is_wp_error( $visitor ) ) {
			return $visitor;
		}

		$result = $this->cart->update_item(
			$visitor,
			(string) $request->get_param( 'item_key' ),
			absint( $request->get_param( 'quantity' ) )
		);

		return is_wp_error( $result ) ? $result : new WP_REST_Response( $result, 200 );
	}

	public function checkout( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$visitor = Visitor::from_request( $request );
		if ( is_wp_error( $visitor ) ) {
			return $visitor;
		}

		return new WP_REST_Response( $this->checkout->url( $visitor ), 200 );
	}
}
