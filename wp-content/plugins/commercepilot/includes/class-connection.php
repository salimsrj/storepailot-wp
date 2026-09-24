<?php
/**
 * Laravel site connection and credential management.
 *
 * @package CommercePilot
 */

declare(strict_types=1);

namespace CommercePilot;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Connection {

	public function __construct(
		private Settings $settings,
		private ApiClient $api
	) {}

	/**
	 * @return array{status:string,label:string}
	 */
	public function status(): array {
		if ( ! $this->settings->is_connected() ) {
			return array(
				'status' => 'disconnected',
				'label'  => __( 'Not Connected', 'commercepilot' ),
			);
		}

		$health = get_transient( 'commercepilot_connection_status' );
		if ( is_array( $health ) && isset( $health['status'] ) ) {
			return $health;
		}

		$site = $this->api->site();
		if ( is_wp_error( $site ) ) {
			$result = array(
				'status' => $site->get_error_code() === 'commercepilot_expired' ? 'expired' : 'error',
				'label'  => $site->get_error_code() === 'commercepilot_expired'
					? __( 'Connection expired', 'commercepilot' )
					: __( 'Connection failed', 'commercepilot' ),
			);
		} else {
			$result = array(
				'status' => 'connected',
				'label'  => __( 'Connected', 'commercepilot' ),
			);
		}

		set_transient( 'commercepilot_connection_status', $result, 2 * MINUTE_IN_SECONDS );
		return $result;
	}

	/**
	 * @return array<string, mixed>|\WP_Error
	 */
	public function connect( string $api_url, string $site_id, string $token, string $secret ): array|\WP_Error {
		if ( $site_id === '' || $token === '' || $secret === '' ) {
			return new \WP_Error( 'commercepilot_missing_credentials', __( 'Site ID, token, and secret are required.', 'commercepilot' ) );
		}

		$this->settings->update( array( 'api_url' => $api_url ) );
		$this->settings->store_credentials( $site_id, $token, $secret );
		delete_transient( 'commercepilot_connection_status' );

		$site = $this->api->site();
		if ( is_wp_error( $site ) ) {
			$this->settings->clear_credentials();
			return $site;
		}

		$this->sync_store_meta();
		$this->sync_assistant_settings();
		$this->refresh_agent_capability();
		return $site;
	}

	public function disconnect(): void {
		$this->settings->clear_credentials();
		delete_transient( 'commercepilot_connection_status' );
	}

	/**
	 * @return array<string, mixed>|\WP_Error
	 */
	public function test(): array|\WP_Error {
		delete_transient( 'commercepilot_site' );
		delete_transient( 'commercepilot_connection_status' );
		return $this->api->site();
	}

	/**
	 * @return array<string, mixed>|\WP_Error
	 */
	public function rotate(): array|\WP_Error {
		$result = $this->api->rotate_token();
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$token = (string) ( $result['token'] ?? '' );
		if ( $token === '' || ! str_starts_with( $token, 'cp_live_' ) ) {
			return new \WP_Error( 'commercepilot_invalid_response', __( 'Could not rotate credentials.', 'commercepilot' ) );
		}

		$this->settings->store_credentials( (string) $this->settings->get( 'site_id' ), $token, $this->settings->site_secret() );
		return array( 'ok' => true );
	}

	public function sync_store_meta(): void {
		$payload = array(
			'name'                => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'plugin_version'      => COMMERCEPILOT_VERSION,
			'wordpress_version'   => get_bloginfo( 'version' ),
			'woocommerce_version' => defined( 'WC_VERSION' ) ? WC_VERSION : '',
		);

		$result = $this->api->update_site( $payload );
		if ( is_wp_error( $result ) ) {
			Logger::warning( 'Failed to sync store metadata', array( 'code' => $result->get_error_code() ) );
		}
	}

	public function sync_assistant_settings(): void {
		$payload = array(
			'assistant_name'          => (string) $this->settings->get( 'assistant_name' ),
			'welcome_message'         => (string) $this->settings->get( 'welcome_message' ),
			'language'                => (string) $this->settings->get( 'language' ),
			'tone'                    => (string) $this->settings->get( 'tone' ),
			'enable_product_search'   => (bool) $this->settings->get( 'product_search' ),
			'enable_recommendations'  => (bool) $this->settings->get( 'recommendations' ),
			'enable_cart'             => (bool) $this->settings->get( 'cart' ),
			'enable_checkout'         => (bool) $this->settings->get( 'checkout' ),
			'enable_order_tracking'   => (bool) $this->settings->get( 'order_tracking' ),
			'enable_agent'            => (bool) $this->settings->get( 'agent_mode' ),
		);

		$result = $this->api->update_site_settings( $payload );
		if ( is_wp_error( $result ) ) {
			Logger::warning( 'Failed to sync assistant settings', array( 'code' => $result->get_error_code() ) );
		}
	}

	/**
	 * Pull Agent Mode / subscription flags from Laravel into local settings.
	 *
	 * @return array{enable_agent:bool,can_enable_agent:bool,subscription:?array<string,mixed>}
	 */
	public function refresh_agent_capability(): array {
		$defaults = array(
			'enable_agent'     => (bool) $this->settings->get( 'agent_mode' ),
			'can_enable_agent' => false,
			'subscription'     => null,
		);

		$site = $this->api->site();
		if ( is_wp_error( $site ) ) {
			return $defaults;
		}

		$data = is_array( $site['data'] ?? null ) ? $site['data'] : $site;
		$enable_agent = (bool) ( $data['enable_agent'] ?? false );
		$can_enable   = (bool) ( $data['can_enable_agent'] ?? false );

		$this->settings->update(
			array(
				'agent_mode' => $enable_agent,
			)
		);

		return array(
			'enable_agent'     => $enable_agent,
			'can_enable_agent' => $can_enable,
			'subscription'     => is_array( $data['subscription'] ?? null ) ? $data['subscription'] : null,
		);
	}

	/**
	 * Persist Agent Mode locally and sync to Laravel.
	 *
	 * @return true|\WP_Error
	 */
	public function set_agent_mode( bool $enabled ): true|\WP_Error {
		$capability = $this->refresh_agent_capability();

		if ( $enabled && ! $capability['can_enable_agent'] ) {
			return new \WP_Error(
				'commercepilot_agent_subscription_required',
				__( 'An active CommercePilot subscription is required to enable Agent Mode.', 'commercepilot' )
			);
		}

		$this->settings->update( array( 'agent_mode' => $enabled ) );
		$this->sync_assistant_settings();

		$synced = $this->refresh_agent_capability();
		if ( $enabled && ! $synced['enable_agent'] ) {
			return new \WP_Error(
				'commercepilot_agent_sync_failed',
				__( 'Could not enable Agent Mode. Check your subscription and try again.', 'commercepilot' )
			);
		}

		return true;
	}
}
