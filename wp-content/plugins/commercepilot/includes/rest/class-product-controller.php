<?php
/**
 * HMAC product endpoints consumed by Laravel tools.
 *
 * @package CommercePilot
 */

declare(strict_types=1);

namespace CommercePilot\REST;

use CommercePilot\WooCommerce\ProductService;
use CommercePilot\WooCommerce\VariationService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ProductController {

	public function __construct(
		private ProductService $products,
		private VariationService $variations
	) {}

	public function search( WP_REST_Request $request ): WP_REST_Response {
		$products = $this->products->search(
			array(
				'query'     => $request->get_param( 'query' ),
				'category'  => $request->get_param( 'category' ),
				'min_price' => $request->get_param( 'min_price' ),
				'max_price' => $request->get_param( 'max_price' ),
				'limit'     => $request->get_param( 'limit' ),
			)
		);

		return new WP_REST_Response( array( 'products' => $products ), 200 );
	}

	public function get_product( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$result = $this->products->get_product( absint( $request->get_param( 'id' ) ) );
		return is_wp_error( $result ) ? $result : new WP_REST_Response( $result, 200 );
	}

	public function variations( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$result = $this->variations->get_variations( absint( $request->get_param( 'id' ) ) );
		return is_wp_error( $result ) ? $result : new WP_REST_Response( array( 'variations' => $result ), 200 );
	}

	public function stock( WP_REST_Request $request ): WP_REST_Response {
		$variation = $request->get_param( 'variation_id' );
		return new WP_REST_Response(
			$this->products->stock(
				absint( $request->get_param( 'product_id' ) ),
				$variation ? absint( $variation ) : null
			),
			200
		);
	}
}
