<?php
/**
 * Script and style registration.
 *
 * @package CommercePilot
 */

declare(strict_types=1);

namespace CommercePilot;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Assets {

	public function __construct( private Settings $settings ) {}

	public function register(): void {
		wp_register_style(
			'commercepilot-admin',
			COMMERCEPILOT_URL . 'admin/assets/css/admin.css',
			array( 'wp-admin' ),
			self::version( 'admin/assets/css/admin.css' )
		);
		wp_register_script(
			'commercepilot-admin',
			COMMERCEPILOT_URL . 'admin/assets/js/admin.js',
			array(),
			self::version( 'admin/assets/js/admin.js' ),
			true
		);
		wp_register_script(
			'commercepilot-inbox',
			COMMERCEPILOT_URL . 'admin/assets/js/conversations.js',
			array(),
			self::version( 'admin/assets/js/conversations.js' ),
			true
		);
		wp_register_style(
			'commercepilot-chatbot',
			COMMERCEPILOT_URL . 'public/assets/css/chatbot.css',
			array(),
			self::version( 'public/assets/css/chatbot.css' )
		);
		wp_register_script(
			'commercepilot-chatbot',
			COMMERCEPILOT_URL . 'public/assets/js/chatbot.js',
			array(),
			self::version( 'public/assets/js/chatbot.js' ),
			true
		);
	}

	/**
	 * Plugin version suffixed with the file mtime so edited assets bust browser caches.
	 */
	private static function version( string $relative_path ): string {
		$mtime = @filemtime( COMMERCEPILOT_PATH . $relative_path );

		return $mtime ? COMMERCEPILOT_VERSION . '.' . $mtime : COMMERCEPILOT_VERSION;
	}

	public function enqueue_admin( string $hook ): void {
		if ( ! str_contains( $hook, 'commercepilot' ) ) {
			return;
		}

		if ( str_contains( $hook, 'commercepilot-settings' ) ) {
			wp_enqueue_media();
		}

		wp_enqueue_style( 'commercepilot-admin' );
		wp_enqueue_script( 'commercepilot-admin' );
		wp_localize_script(
			'commercepilot-admin',
			'commercePilotAdmin',
			array(
				'restUrl' => esc_url_raw( rest_url( 'commercepilot/v1/' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'i18n'    => array(
					'selectImage' => __( 'Select profile picture', 'commercepilot' ),
					'useImage'    => __( 'Use this image', 'commercepilot' ),
					'noImage'     => __( 'No image', 'commercepilot' ),
				),
			)
		);

		if ( str_contains( $hook, 'commercepilot-conversations' ) ) {
			$this->enqueue_inbox();
		}
	}

	private function enqueue_inbox(): void {
		wp_enqueue_script( 'commercepilot-inbox' );
		wp_localize_script(
			'commercepilot-inbox',
			'commercePilotInbox',
			array(
				'restUrl'   => esc_url_raw( rest_url( 'commercepilot/v1/' ) ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'agentMode' => (bool) $this->settings->get( 'agent_mode' ),
				'i18n'      => array(
					'loading'          => __( 'Loading…', 'commercepilot' ),
					'failed'           => __( 'Request failed. Please try again.', 'commercepilot' ),
					'noConversations'  => __( 'No conversations yet.', 'commercepilot' ),
					'noMessages'       => __( 'No messages yet.', 'commercepilot' ),
					'visitor'          => __( 'Visitor', 'commercepilot' ),
					'assistant'        => __( 'AI assistant', 'commercepilot' ),
					'human'            => __( 'You', 'commercepilot' ),
					'waiting'          => __( 'Waiting', 'commercepilot' ),
					'takeOver'         => __( 'Take over', 'commercepilot' ),
					'release'          => __( 'Give back to AI', 'commercepilot' ),
					'modeHuman'        => __( 'You are handling this chat. The AI is off.', 'commercepilot' ),
					'modeAi'           => __( 'The AI is answering this chat.', 'commercepilot' ),
					'modeDirect'       => __( 'Direct messaging. Reply to the visitor here.', 'commercepilot' ),
					'replyPlaceholder' => __( 'Write a reply…', 'commercepilot' ),
					'takeOverFirst'    => __( 'Take over this chat to reply manually.', 'commercepilot' ),
					'send'             => __( 'Send', 'commercepilot' ),
					'shareProduct'     => __( 'Share product', 'commercepilot' ),
					'searchProducts'   => __( 'Search products…', 'commercepilot' ),
					'noProducts'       => __( 'No products found.', 'commercepilot' ),
					'shareSelected'    => __( 'Share selected', 'commercepilot' ),
					'cancel'           => __( 'Cancel', 'commercepilot' ),
					'selectProduct'    => __( 'Select at least one product.', 'commercepilot' ),
					'maxProducts'      => __( 'You can share up to 5 products at once.', 'commercepilot' ),
				),
			)
		);
	}

	public function enqueue_public(): void {
		if ( is_admin() || ! $this->settings->get( 'enabled' ) || ! $this->settings->is_connected() ) {
			return;
		}

		wp_enqueue_style( 'commercepilot-chatbot' );
		wp_enqueue_script( 'commercepilot-chatbot' );
		wp_localize_script(
			'commercepilot-chatbot',
			'commercePilot',
			array(
				'restUrl'  => esc_url_raw( rest_url( 'commercepilot/v1/' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'config'   => $this->settings->public_config(),
				'iconUrl'  => esc_url_raw( COMMERCEPILOT_URL . 'public/assets/images/chat-toggle.png?v=' . self::version( 'public/assets/images/chat-toggle.png' ) ),
				'i18n'     => array(
					'unavailable' => __( 'Sorry, the assistant is temporarily unavailable. Please try again.', 'commercepilot' ),
					'upgrade'     => __( 'Your free monthly messages are finished.', 'commercepilot' ),
					'upgradeCta'  => __( 'Upgrade Plan', 'commercepilot' ),
					'retry'       => __( 'Retry', 'commercepilot' ),
					'send'        => __( 'Send', 'commercepilot' ),
					'open'        => __( 'Open chat', 'commercepilot' ),
					'close'       => __( 'Close chat', 'commercepilot' ),
					'addToCart'   => __( 'Add to cart', 'commercepilot' ),
					'checkout'    => __( 'Checkout', 'commercepilot' ),
					'view'        => __( 'View product', 'commercepilot' ),
					'chooseOption'=> __( 'Choose an option', 'commercepilot' ),
					'selectVariation' => __( 'Select a variation', 'commercepilot' ),
					'noVariations'=> __( 'No purchasable variations are available.', 'commercepilot' ),
					'addedToCart' => __( 'Added to cart.', 'commercepilot' ),
					'avatarAlt'   => __( 'Assistant profile picture', 'commercepilot' ),
					'humanMode'   => __( 'You are now chatting with our team. Replies may take a moment.', 'commercepilot' ),
					'statusOnline'  => __( 'Online', 'commercepilot' ),
					'statusAway'    => __( 'Away', 'commercepilot' ),
					'statusOffline' => __( 'Offline', 'commercepilot' ),
					'muteSound'     => __( 'Mute message sounds', 'commercepilot' ),
					'unmuteSound'   => __( 'Unmute message sounds', 'commercepilot' ),
				),
			)
		);
	}
}
