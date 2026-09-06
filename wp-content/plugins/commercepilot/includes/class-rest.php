<?php
/**
 * REST route registrar.
 *
 * @package CommercePilot
 */

declare(strict_types=1);

namespace CommercePilot;

use CommercePilot\REST\CartController;
use CommercePilot\REST\ChatController;
use CommercePilot\REST\ConversationController;
use CommercePilot\REST\HealthController;
use CommercePilot\REST\ProductController;
use CommercePilot\REST\SiteController;
use CommercePilot\WooCommerce\CartService;
use CommercePilot\WooCommerce\CheckoutService;
use CommercePilot\WooCommerce\ProductService;
use CommercePilot\WooCommerce\VariationService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Rest {

	public const NAMESPACE = 'commercepilot/v1';

	public function __construct(
		private Settings $settings,
		private ApiClient $api
	) {}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function routes(): void {
		$auth     = new Auth( $this->settings, new Security() );
		$products = new ProductService();
		$vars     = new VariationService( $products );
		$cart     = new CartService( $products, $vars );
		$checkout = new CheckoutService( $cart );
		$chat     = new ChatController( $this->settings, $this->api );
		$cart_c   = new CartController( $cart, $checkout );
		$product  = new ProductController( $products, $vars );
		$site     = new SiteController( $this->settings, new Connection( $this->settings, $this->api ), $this->api );
		$health   = new HealthController( $this->settings, $this->api );
		$conversations = new ConversationController( $this->settings, $this->api );

		$public = array( $auth, 'public_rest' );
		$poll   = array( $auth, 'public_poll' );
		$hmac   = array( $auth, 'hmac' );
		$admin  = array( $auth, 'admin' );

		register_rest_route(
			self::NAMESPACE,
			'/chat',
			array(
				'methods'             => 'POST',
				'callback'            => array( $chat, 'send' ),
				'permission_callback' => $public,
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/messages',
			array(
				'methods'             => 'GET',
				'callback'            => array( $chat, 'poll' ),
				'permission_callback' => $poll,
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/site',
			array(
				'methods'             => 'GET',
				'callback'            => array( $site, 'public_site' ),
				'permission_callback' => $public,
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/health',
			array(
				'methods'             => 'GET',
				'callback'            => array( $health, 'public_health' ),
				'permission_callback' => $public,
			)
		);

		foreach ( array( '/cart', '/cart/get' ) as $path ) {
			register_rest_route(
				self::NAMESPACE,
				$path,
				array(
					'methods'             => 'GET',
					'callback'            => array( $cart_c, 'get_cart' ),
					'permission_callback' => $this->public_or_hmac( $auth ),
				)
			);
		}

		register_rest_route(
			self::NAMESPACE,
			'/cart/items',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $cart_c, 'add_item' ),
					'permission_callback' => $hmac,
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $cart_c, 'remove_item' ),
					'permission_callback' => $hmac,
				),
				array(
					'methods'             => 'PATCH',
					'callback'            => array( $cart_c, 'update_item' ),
					'permission_callback' => $hmac,
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/cart/add',
			array(
				'methods'             => 'POST',
				'callback'            => array( $cart_c, 'add_item' ),
				'permission_callback' => $public,
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/cart/remove',
			array(
				'methods'             => 'POST',
				'callback'            => array( $cart_c, 'remove_item' ),
				'permission_callback' => $public,
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/cart/update',
			array(
				'methods'             => 'POST',
				'callback'            => array( $cart_c, 'update_item' ),
				'permission_callback' => $public,
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/checkout',
			array(
				'methods'             => 'GET',
				'callback'            => array( $cart_c, 'checkout' ),
				'permission_callback' => $this->public_or_hmac( $auth ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/products/search',
			array(
				'methods'             => 'POST',
				'callback'            => array( $product, 'search' ),
				'permission_callback' => $hmac,
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/products/(?P<id>\d+)/variations',
			array(
				'methods'             => 'GET',
				'callback'            => array( $product, 'variations' ),
				'permission_callback' => $hmac,
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/products/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $product, 'get_product' ),
				'permission_callback' => $hmac,
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/stock',
			array(
				'methods'             => 'POST',
				'callback'            => array( $product, 'stock' ),
				'permission_callback' => $hmac,
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/admin/connect',
			array(
				'methods'             => 'POST',
				'callback'            => array( $site, 'connect' ),
				'permission_callback' => $admin,
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/admin/disconnect',
			array(
				'methods'             => 'POST',
				'callback'            => array( $site, 'disconnect' ),
				'permission_callback' => $admin,
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/admin/test',
			array(
				'methods'             => 'POST',
				'callback'            => array( $site, 'test' ),
				'permission_callback' => $admin,
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/admin/rotate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $site, 'rotate' ),
				'permission_callback' => $admin,
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/admin/usage',
			array(
				'methods'             => 'GET',
				'callback'            => array( $site, 'usage' ),
				'permission_callback' => $admin,
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/admin/settings',
			array(
				'methods'             => 'POST',
				'callback'            => array( $site, 'save_settings' ),
				'permission_callback' => $admin,
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/admin/health',
			array(
				'methods'             => 'GET',
				'callback'            => array( $health, 'admin_health' ),
				'permission_callback' => $admin,
			)
		);

		$uuid_pattern = '(?P<uuid>[A-Za-z0-9\-]+)';

		register_rest_route(
			self::NAMESPACE,
			'/admin/conversations',
			array(
				'methods'             => 'GET',
				'callback'            => array( $conversations, 'index' ),
				'permission_callback' => $admin,
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/admin/conversations/' . $uuid_pattern,
			array(
				'methods'             => 'GET',
				'callback'            => array( $conversations, 'show' ),
				'permission_callback' => $admin,
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/admin/conversations/' . $uuid_pattern . '/messages',
			array(
				'methods'             => 'GET',
				'callback'            => array( $conversations, 'messages' ),
				'permission_callback' => $admin,
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/admin/conversations/' . $uuid_pattern . '/takeover',
			array(
				'methods'             => 'POST',
				'callback'            => array( $conversations, 'take_over' ),
				'permission_callback' => $admin,
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/admin/conversations/' . $uuid_pattern . '/release',
			array(
				'methods'             => 'POST',
				'callback'            => array( $conversations, 'release' ),
				'permission_callback' => $admin,
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/admin/conversations/' . $uuid_pattern . '/reply',
			array(
				'methods'             => 'POST',
				'callback'            => array( $conversations, 'reply' ),
				'permission_callback' => $admin,
			)
		);
	}

	/**
	 * Laravel HMAC or browser nonce, depending on headers.
	 */
	private function public_or_hmac( Auth $auth ): callable {
		return static function ( \WP_REST_Request $request ) use ( $auth ): bool|\WP_Error {
			$signature = (string) $request->get_header( 'x-commercepilot-signature' );
			return $signature !== '' ? $auth->hmac( $request ) : $auth->public_rest( $request );
		};
	}
}
