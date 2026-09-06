<?php
/**
 * WooCommerce product queries.
 *
 * @package CommercePilot
 */

declare(strict_types=1);

namespace CommercePilot\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ProductService {

	/**
	 * @param array<string, mixed> $filters
	 * @return array<int, array<string, mixed>>
	 */
	public function search( array $filters ): array {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return array();
		}

		$limit = min( 10, max( 1, absint( $filters['limit'] ?? 8 ) ) );
		$args  = array(
			'post_type'           => 'product',
			'post_status'         => 'publish',
			'posts_per_page'      => $limit,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'fields'              => 'ids',
		);

		$query = sanitize_text_field( (string) ( $filters['query'] ?? $filters['keyword'] ?? '' ) );
		if ( $query !== '' ) {
			$args['s'] = $query;
		}

		$category = sanitize_title( (string) ( $filters['category'] ?? '' ) );
		if ( $category !== '' ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'slug',
					'terms'    => array( $category ),
				),
			);
		}

		$meta = array();
		if ( isset( $filters['min_price'] ) && is_numeric( $filters['min_price'] ) ) {
			$meta[] = array(
				'key'     => '_price',
				'value'   => (float) $filters['min_price'],
				'compare' => '>=',
				'type'    => 'NUMERIC',
			);
		}
		if ( isset( $filters['max_price'] ) && is_numeric( $filters['max_price'] ) ) {
			$meta[] = array(
				'key'     => '_price',
				'value'   => (float) $filters['max_price'],
				'compare' => '<=',
				'type'    => 'NUMERIC',
			);
		}
		if ( $meta !== array() ) {
			$args['meta_query'] = $meta;
		}

		$ids = get_posts( $args );
		$out = array();

		foreach ( $ids as $id ) {
			$product = wc_get_product( (int) $id );
			if ( $product instanceof \WC_Product ) {
				$normalized = $this->normalize( $product );
				if ( $normalized ) {
					$out[] = $normalized;
				}
			}
		}

		return $out;
	}

	/**
	 * @return array<string, mixed>|\WP_Error
	 */
	public function get_product( int $product_id ): array|\WP_Error {
		$product = $this->public_product( $product_id );
		if ( is_wp_error( $product ) ) {
			return $product;
		}

		$normalized = $this->normalize( $product );
		return $normalized ?: new \WP_Error( 'commercepilot_product_unavailable', __( 'Product not available.', 'commercepilot' ), array( 'status' => 404 ) );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function stock( int $product_id, ?int $variation_id = null ): array {
		$target_id = $variation_id ?: $product_id;
		$product   = wc_get_product( $target_id );

		if ( ! $product instanceof \WC_Product ) {
			return array(
				'in_stock' => false,
				'quantity' => 0,
			);
		}

		$parent = $variation_id ? wc_get_product( $product_id ) : $product;
		if ( $parent instanceof \WC_Product && $parent->get_status() !== 'publish' ) {
			return array(
				'in_stock' => false,
				'quantity' => 0,
			);
		}

		$qty = $product->get_stock_quantity();

		return array(
			'in_stock' => $product->is_in_stock(),
			'quantity' => is_null( $qty ) ? null : (int) $qty,
		);
	}

	public function public_product( int $product_id ): \WC_Product|\WP_Error {
		$product = wc_get_product( $product_id );
		if ( ! $product instanceof \WC_Product || $product->get_status() !== 'publish' ) {
			return new \WP_Error( 'commercepilot_product_unavailable', __( 'Product not available.', 'commercepilot' ), array( 'status' => 404 ) );
		}

		return $product;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function normalize( \WC_Product $product ): ?array {
		if ( $product->get_status() !== 'publish' ) {
			return null;
		}

		$image_id = $product->get_image_id();
		$image    = $image_id ? wp_get_attachment_image_url( (int) $image_id, 'woocommerce_thumbnail' ) : '';
		$terms    = get_the_terms( $product->get_id(), 'product_cat' );
		$cats     = array();

		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				$cats[] = $term->name;
			}
		}

		return array(
			'id'                => $product->get_id(),
			'name'              => $product->get_name(),
			'price'             => wc_format_decimal( $product->get_price(), 2 ),
			'currency'          => get_woocommerce_currency(),
			'stock_status'      => $product->is_in_stock() ? 'instock' : 'outofstock',
			'image'             => $image ? $image : null,
			'url'               => $product->get_permalink(),
			'short_description' => wp_strip_all_tags( (string) $product->get_short_description() ),
			'categories'        => $cats,
			'has_variations'    => $product->is_type( 'variable' ),
		);
	}
}
