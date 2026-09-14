<?php
/**
 * Options API settings store.
 *
 * @package CommercePilot
 */

declare(strict_types=1);

namespace CommercePilot;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Settings {

	public const OPTION_KEY = 'commercepilot_settings';

	/**
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'site_id'                 => '',
			'site_token'              => '',
			'site_secret'             => '',
			'api_url'                 => COMMERCEPILOT_API_URL,
			'enabled'                 => true,
			'assistant_name'          => 'CommercePilot',
			'welcome_message'         => 'Hi! I can help you find products and add them to your cart.',
			'language'                => 'en',
			'tone'                    => 'helpful',
			'product_search'          => true,
			'recommendations'         => true,
			'cart'                    => true,
			'checkout'                => true,
			'order_tracking'          => false,
			'position'                => 'right',
			'theme'                   => 'light',
			'primary_color'           => '#2563eb',
			'button_text'             => 'Chat',
			'avatar'                  => '',
			'avatar_id'               => 0,
			'avatar_in_messages'      => true,
			'show_status'             => true,
			'online_status'           => 'online',
			'status_text'             => '',
			'width'                   => 380,
			'delete_data_on_uninstall' => false,
		);
	}

	/**
	 * @return array<string, string>
	 */
	public static function status_choices(): array {
		return array(
			'online'  => __( 'Online', 'commercepilot' ),
			'away'    => __( 'Away', 'commercepilot' ),
			'offline' => __( 'Offline', 'commercepilot' ),
		);
	}

	public function ensure_defaults(): void {
		$current = get_option( self::OPTION_KEY, null );
		if ( ! is_array( $current ) ) {
			add_option( self::OPTION_KEY, self::defaults(), '', false );
			return;
		}

		update_option( self::OPTION_KEY, array_merge( self::defaults(), $current ), false );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function all(): array {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return array_merge( self::defaults(), $stored );
	}

	public function get( string $key, mixed $default = null ): mixed {
		$settings = $this->all();
		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
	}

	/**
	 * @param array<string, mixed> $values
	 */
	public function update( array $values ): void {
		$merged = array_merge( $this->all(), $this->sanitize( $values ) );
		update_option( self::OPTION_KEY, $merged, false );
		delete_transient( 'commercepilot_usage' );
		delete_transient( 'commercepilot_site' );
		delete_transient( 'commercepilot_health' );
	}

	public function api_url(): string {
		$url = (string) $this->get( 'api_url', COMMERCEPILOT_API_URL );
		$url = untrailingslashit( esc_url_raw( $url ) );
		return $url !== '' ? $url : COMMERCEPILOT_API_URL;
	}

	public function is_connected(): bool {
		return $this->get( 'site_id' ) !== '' && $this->site_token() !== '' && $this->site_secret() !== '';
	}

	public function site_token(): string {
		return Security::decrypt( (string) $this->get( 'site_token', '' ) );
	}

	public function site_secret(): string {
		return Security::decrypt( (string) $this->get( 'site_secret', '' ) );
	}

	public function store_credentials( string $site_id, string $token, string $secret ): void {
		$this->update(
			array(
				'site_id'     => $site_id,
				'site_token'  => Security::encrypt( $token ),
				'site_secret' => Security::encrypt( $secret ),
			)
		);
	}

	public function clear_credentials(): void {
		$this->update(
			array(
				'site_id'     => '',
				'site_token'  => '',
				'site_secret' => '',
			)
		);
	}

	/**
	 * Public widget configuration. Never includes secrets.
	 *
	 * @return array<string, mixed>
	 */
	public function public_config(): array {
		return array(
			'enabled'         => (bool) $this->get( 'enabled' ) && $this->is_connected(),
			'assistant_name'  => (string) $this->get( 'assistant_name' ),
			'welcome_message' => (string) $this->get( 'welcome_message' ),
			'language'        => (string) $this->get( 'language' ),
			'position'        => (string) $this->get( 'position' ),
			'theme'           => (string) $this->get( 'theme' ),
			'primary_color'   => (string) $this->get( 'primary_color' ),
			'button_text'     => (string) $this->get( 'button_text' ),
			'avatar'          => (string) $this->get( 'avatar' ),
			'avatar_in_messages' => (bool) $this->get( 'avatar_in_messages' ),
			'show_status'     => (bool) $this->get( 'show_status' ),
			'online_status'   => (string) $this->get( 'online_status' ),
			'status_text'     => (string) $this->get( 'status_text' ),
			'width'           => (int) $this->get( 'width' ),
			'features'        => array(
				'product_search'  => (bool) $this->get( 'product_search' ),
				'recommendations' => (bool) $this->get( 'recommendations' ),
				'cart'            => (bool) $this->get( 'cart' ),
				'checkout'        => (bool) $this->get( 'checkout' ),
			),
		);
	}

	/**
	 * @param array<string, mixed> $values
	 * @return array<string, mixed>
	 */
	public function sanitize( array $values ): array {
		$clean = array();

		if ( isset( $values['site_id'] ) ) {
			$clean['site_id'] = sanitize_text_field( (string) $values['site_id'] );
		}
		if ( isset( $values['site_token'] ) ) {
			$clean['site_token'] = (string) $values['site_token'];
		}
		if ( isset( $values['site_secret'] ) ) {
			$clean['site_secret'] = (string) $values['site_secret'];
		}
		if ( isset( $values['api_url'] ) ) {
			$clean['api_url'] = untrailingslashit( esc_url_raw( (string) $values['api_url'] ) );
		}
		if ( isset( $values['enabled'] ) ) {
			$clean['enabled'] = (bool) $values['enabled'];
		}
		if ( isset( $values['assistant_name'] ) ) {
			$clean['assistant_name'] = sanitize_text_field( (string) $values['assistant_name'] );
		}
		if ( isset( $values['welcome_message'] ) ) {
			$clean['welcome_message'] = sanitize_textarea_field( (string) $values['welcome_message'] );
		}
		if ( isset( $values['language'] ) ) {
			$clean['language'] = sanitize_text_field( (string) $values['language'] );
		}
		if ( isset( $values['tone'] ) ) {
			$clean['tone'] = sanitize_text_field( (string) $values['tone'] );
		}
		foreach ( array( 'product_search', 'recommendations', 'cart', 'checkout', 'order_tracking', 'avatar_in_messages', 'show_status', 'delete_data_on_uninstall' ) as $flag ) {
			if ( isset( $values[ $flag ] ) ) {
				$clean[ $flag ] = (bool) $values[ $flag ];
			}
		}
		if ( isset( $values['position'] ) ) {
			$clean['position'] = in_array( $values['position'], array( 'left', 'right' ), true ) ? $values['position'] : 'right';
		}
		if ( isset( $values['theme'] ) ) {
			$clean['theme'] = in_array( $values['theme'], array( 'light', 'dark' ), true ) ? $values['theme'] : 'light';
		}
		if ( isset( $values['primary_color'] ) ) {
			$color = sanitize_hex_color( (string) $values['primary_color'] );
			$clean['primary_color'] = $color ? $color : '#2563eb';
		}
		if ( isset( $values['button_text'] ) ) {
			$clean['button_text'] = sanitize_text_field( (string) $values['button_text'] );
		}
		if ( isset( $values['avatar_id'] ) ) {
			$attachment_id = absint( $values['avatar_id'] );
			if ( $attachment_id > 0 && wp_attachment_is_image( $attachment_id ) ) {
				$url                    = wp_get_attachment_image_url( $attachment_id, 'thumbnail' );
				$clean['avatar_id']     = $attachment_id;
				$clean['avatar']        = $url ? esc_url_raw( $url ) : '';
			} else {
				$clean['avatar_id'] = 0;
			}
		}
		if ( isset( $values['avatar'] ) && ! isset( $clean['avatar'] ) ) {
			$clean['avatar'] = esc_url_raw( (string) $values['avatar'] );
		}
		if ( isset( $values['online_status'] ) ) {
			$status                  = (string) $values['online_status'];
			$clean['online_status']  = array_key_exists( $status, self::status_choices() ) ? $status : 'online';
		}
		if ( isset( $values['status_text'] ) ) {
			$clean['status_text'] = sanitize_text_field( (string) $values['status_text'] );
		}
		if ( isset( $values['width'] ) ) {
			$clean['width'] = min( 560, max( 300, absint( $values['width'] ) ) );
		}

		return $clean;
	}
}
