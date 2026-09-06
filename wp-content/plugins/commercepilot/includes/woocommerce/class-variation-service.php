<?php
/**
 * Variable product resolution.
 *
 * @package CommercePilot
 */

declare(strict_types=1);

namespace CommercePilot\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class VariationService {

	public function __construct( private ProductService $products ) {}

	/**
	 * @return array<int, array<string, mixed>>|\WP_Error
	 */
	public function get_variations( int $product_id ): array|\WP_Error {
		$product = $this->products->public_product( $product_id );
		if ( is_wp_error( $product ) ) {
			return $product;
		}

		if ( ! $product->is_type( 'variable' ) || ! $product instanceof \WC_Product_Variable ) {
			return array();
		}

		$out = array();
		foreach ( $product->get_available_variations( 'objects' ) as $variation ) {
			if ( ! $variation instanceof \WC_Product_Variation || ! $variation->is_purchasable() ) {
				continue;
			}

			$out[] = array(
				'id'           => $variation->get_id(),
				'name'         => $variation->get_name(),
				'price'        => wc_format_decimal( $variation->get_price(), 2 ),
				'stock_status' => $variation->is_in_stock() ? 'instock' : 'outofstock',
				'attributes'   => $variation->get_variation_attributes(),
			);
		}

		return $out;
	}

	/**
	 * @param array<string, string> $attributes
	 */
	public function resolve( int $product_id, ?int $variation_id, array $attributes = array() ): int|\WP_Error {
		$product = $this->products->public_product( $product_id );
		if ( is_wp_error( $product ) ) {
			return $product;
		}

		if ( ! $product->is_type( 'variable' ) ) {
			return 0;
		}

		if ( $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( ! $variation instanceof \WC_Product_Variation || (int) $variation->get_parent_id() !== $product_id ) {
				return new \WP_Error( 'commercepilot_invalid_variation', __( 'That variation is not available.', 'commercepilot' ), array( 'status' => 400 ) );
			}
			return $variation_id;
		}

		if ( $attributes === array() ) {
			return new \WP_Error( 'commercepilot_variation_required', __( 'Please choose a product variation (for example size or color).', 'commercepilot' ), array( 'status' => 400 ) );
		}

		$data_store = \WC_Data_Store::load( 'product' );
		$found      = $data_store->find_matching_product_variation( $product, $attributes );
		if ( ! $found ) {
			return new \WP_Error( 'commercepilot_variation_required', __( 'Please choose a product variation (for example size or color).', 'commercepilot' ), array( 'status' => 400 ) );
		}

		return (int) $found;
	}
}
