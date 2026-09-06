<?php
/**
 * Privacy policy and exporter hooks.
 *
 * @package CommercePilot
 */

declare(strict_types=1);

namespace CommercePilot;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Privacy {

	public function register(): void {
		add_action( 'admin_init', array( $this, 'add_privacy_policy' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
	}

	public function add_privacy_policy(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		wp_add_privacy_policy_content(
			'CommercePilot',
			wp_kses_post(
				__( 'CommercePilot sends chat messages, a generated visitor ID, and a conversation ID to the CommercePilot Laravel API so the AI assistant can answer product questions. Product, cart, and checkout actions stay on this store through WooCommerce. Payment details are never sent to CommercePilot. Conversations are stored by the CommercePilot SaaS account, not in WordPress.', 'commercepilot' )
			)
		);
	}

	/**
	 * @param array<string, mixed> $exporters
	 * @return array<string, mixed>
	 */
	public function register_exporter( array $exporters ): array {
		$exporters['commercepilot'] = array(
			'exporter_friendly_name' => __( 'CommercePilot', 'commercepilot' ),
			'callback'               => array( $this, 'export' ),
		);
		return $exporters;
	}

	/**
	 * @param array<string, mixed> $erasers
	 * @return array<string, mixed>
	 */
	public function register_eraser( array $erasers ): array {
		$erasers['commercepilot'] = array(
			'eraser_friendly_name' => __( 'CommercePilot', 'commercepilot' ),
			'callback'             => array( $this, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * @return array{data:array<int, mixed>,done:bool}
	 */
	public function export( string $email, int $page = 1 ): array {
		unset( $email, $page );
		return array(
			'data' => array(),
			'done' => true,
		);
	}

	/**
	 * @return array{items_removed:bool,items_retained:bool,messages:array<int,string>,done:bool}
	 */
	public function erase( string $email, int $page = 1 ): array {
		unset( $email, $page );
		return array(
			'items_removed'  => false,
			'items_retained' => false,
			'messages'       => array( __( 'CommercePilot does not store customer personal data in WordPress. Conversation data is held by the CommercePilot SaaS account.', 'commercepilot' ) ),
			'done'           => true,
		);
	}
}
