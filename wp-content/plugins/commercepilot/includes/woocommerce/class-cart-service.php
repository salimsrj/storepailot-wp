<?php
/**
 * WooCommerce cart operations bound to a visitor session.
 *
 * Cart session architecture:
 * - Each visitor UUID maps to a deterministic WooCommerce guest session key: t_{hmac}.
 * - Browser REST calls and Laravel HMAC calls load the same woocommerce_sessions row.
 * - When the request comes from the storefront (cookies present), the WC session cookie
 *   is aligned to that key so checkout uses the same cart.
 *
 * @package CommercePilot
 */

declare(strict_types=1);

namespace CommercePilot\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CartService {

	public function __construct(
		private ProductService $products,
		private VariationService $variations
	) {}

	/**
	 * @return array<string, mixed>
	 */
	public function get_cart( string $visitor_id ): array {
		$this->bind_visitor( $visitor_id, true );
		return $this->snapshot();
	}

	/**
	 * @param array<string, mixed> $item
	 * @return array<string, mixed>|\WP_Error
	 */
	public function add_item( string $visitor_id, array $item ): array|\WP_Error {
		$this->bind_visitor( $visitor_id, true );

		$product_id   = absint( $item['product_id'] ?? 0 );
		$variation_id = isset( $item['variation_id'] ) ? absint( $item['variation_id'] ) : 0;
		$quantity     = absint( $item['quantity'] ?? 1 );

		if ( $product_id < 1 ) {
			return new \WP_Error( 'commercepilot_invalid_product', __( 'Invalid product.', 'commercepilot' ), array( 'status' => 400 ) );
		}
		if ( $quantity < 1 || $quantity > 20 ) {
			return new \WP_Error( 'commercepilot_invalid_quantity', __( 'Quantity must be between 1 and 20.', 'commercepilot' ), array( 'status' => 400 ) );
		}

		$product = $this->products->public_product( $product_id );
		if ( is_wp_error( $product ) ) {
			return $product;
		}

		if ( ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			return new \WP_Error( 'commercepilot_out_of_stock', __( 'This product is not available to purchase.', 'commercepilot' ), array( 'status' => 400 ) );
		}

		$resolved = $this->variations->resolve( $product_id, $variation_id ?: null );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}

		$variation_id = $resolved;
		$attributes   = array();
		if ( $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( $variation instanceof \WC_Product_Variation ) {
				$attributes = $variation->get_variation_attributes();
			}
		}

		$key = WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $attributes );
		if ( ! $key ) {
			return new \WP_Error( 'commercepilot_cart_failed', __( 'Could not add that item to the cart.', 'commercepilot' ), array( 'status' => 400 ) );
		}

		WC()->cart->calculate_totals();
		$this->persist();
		return $this->snapshot();
	}

	/**
	 * @return array<string, mixed>|\WP_Error
	 */
	public function remove_item( string $visitor_id, string $item_key ): array|\WP_Error {
		$this->bind_visitor( $visitor_id, true );
		$item_key = sanitize_text_field( $item_key );

		if ( $item_key === '' || ! WC()->cart->remove_cart_item( $item_key ) ) {
			return new \WP_Error( 'commercepilot_cart_item_missing', __( 'Cart item not found.', 'commercepilot' ), array( 'status' => 404 ) );
		}

		WC()->cart->calculate_totals();
		$this->persist();
		return $this->snapshot();
	}

	/**
	 * @return array<string, mixed>|\WP_Error
	 */
	public function update_item( string $visitor_id, string $item_key, int $quantity ): array|\WP_Error {
		$this->bind_visitor( $visitor_id, true );
		$item_key = sanitize_text_field( $item_key );

		if ( $quantity < 1 || $quantity > 20 ) {
			return new \WP_Error( 'commercepilot_invalid_quantity', __( 'Quantity must be between 1 and 20.', 'commercepilot' ), array( 'status' => 400 ) );
		}

		if ( $item_key === '' || ! WC()->cart->set_quantity( $item_key, $quantity, true ) ) {
			return new \WP_Error( 'commercepilot_cart_item_missing', __( 'Cart item not found.', 'commercepilot' ), array( 'status' => 404 ) );
		}

		WC()->cart->calculate_totals();
		$this->persist();
		return $this->snapshot();
	}

	public function bind_visitor( string $visitor_id, bool $set_cookie = false ): void {
		$this->ensure_cart();

		$session = WC()->session;
		if ( ! $session instanceof \WC_Session_Handler ) {
			return;
		}

		$customer_id = $this->customer_id( $visitor_id );
		$this->set_session_customer_id( $session, $customer_id );
		$session->set_session_expiration();

		$data = $session->get_session( $customer_id, array() );
		if ( is_array( $data ) && $data !== array() ) {
			foreach ( $data as $key => $value ) {
				$session->set( (string) $key, maybe_unserialize( $value ) );
			}
		}

		if ( $set_cookie && ! headers_sent() ) {
			$session->set_customer_session_cookie( true );
		}

		if ( WC()->cart ) {
			WC()->cart->get_cart_from_session();
		}
	}

	public function customer_id( string $visitor_id ): string {
		return 't_' . substr( hash_hmac( 'sha256', strtolower( $visitor_id ), wp_salt( 'auth' ) ), 0, 30 );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function snapshot(): array {
		$cart  = WC()->cart;
		$items = array();

		if ( $cart ) {
			foreach ( $cart->get_cart() as $key => $item ) {
				$product = $item['data'] ?? null;
				$image   = '';
				if ( $product instanceof \WC_Product ) {
					$image_id = $product->get_image_id();
					$image    = $image_id ? wp_get_attachment_image_url( (int) $image_id, 'woocommerce_thumbnail' ) : '';
				}

				$items[] = array(
					'key'          => (string) $key,
					'product_id'   => (int) ( $item['product_id'] ?? 0 ),
					'variation_id' => (int) ( $item['variation_id'] ?? 0 ),
					'name'         => $product instanceof \WC_Product ? $product->get_name() : '',
					'quantity'     => (int) ( $item['quantity'] ?? 0 ),
					'price'        => wc_format_decimal( $item['line_subtotal'] ?? 0, 2 ),
					'line_total'   => wc_format_decimal( $item['line_total'] ?? 0, 2 ),
					'image'        => $image ? $image : null,
				);
			}
			$cart->calculate_totals();
		}

		return array(
			'items'      => $items,
			'subtotal'   => $cart ? wc_format_decimal( $cart->get_subtotal(), 2 ) : '0.00',
			'total'      => $cart ? wc_format_decimal( $cart->get_total( 'edit' ), 2 ) : '0.00',
			'currency'   => get_woocommerce_currency(),
			'item_count' => $cart ? (int) $cart->get_cart_contents_count() : 0,
		);
	}

	private function persist(): void {
		if ( WC()->session instanceof \WC_Session_Handler ) {
			WC()->session->save_data();
		}
	}

	private function ensure_cart(): void {
		if ( ! function_exists( 'WC' ) || ! WC() ) {
			return;
		}

		if ( null === WC()->session ) {
			WC()->initialize_session();
		}
		if ( null === WC()->cart ) {
			wc_load_cart();
		}
	}

	private function set_session_customer_id( \WC_Session_Handler $session, string $customer_id ): void {
		$reflection = new \ReflectionClass( $session );
		if ( $reflection->hasProperty( '_customer_id' ) ) {
			$property = $reflection->getProperty( '_customer_id' );
			$property->setAccessible( true );
			$property->setValue( $session, $customer_id );
		}
	}
}
