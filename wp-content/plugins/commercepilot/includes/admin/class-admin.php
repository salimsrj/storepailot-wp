<?php
/**
 * Admin menu.
 *
 * @package CommercePilot
 */

declare(strict_types=1);

namespace CommercePilot\Admin;

use CommercePilot\ApiClient;
use CommercePilot\Assets;
use CommercePilot\Connection;
use CommercePilot\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin {

	public function __construct(
		private Settings $settings,
		private ?ApiClient $api = null,
		private ?Connection $connection = null
	) {}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_menu_badge' ) );
		add_action( 'admin_enqueue_scripts', array( new Assets( $this->settings ), 'enqueue_admin' ) );
		add_action( 'admin_post_commercepilot_save_settings', array( $this, 'save_settings' ) );
		add_action( 'admin_post_commercepilot_connect', array( $this, 'save_connection' ) );
		add_action( 'admin_post_commercepilot_agent_mode', array( $this, 'save_agent_mode' ) );
		add_action( 'admin_post_commercepilot_disconnect', array( $this, 'disconnect' ) );
		add_action( 'admin_post_commercepilot_test', array( $this, 'test_connection' ) );
		add_action( 'admin_post_commercepilot_rotate', array( $this, 'rotate' ) );
	}

	public function menu(): void {
		$waiting = $this->waiting_count();
		$badge   = $this->menu_badge( $waiting );

		add_menu_page(
			__( 'CommercePilot', 'commercepilot' ),
			__( 'CommercePilot', 'commercepilot' ) . $badge,
			'manage_options',
			'commercepilot',
			array( $this, 'dashboard' ),
			'dashicons-format-chat',
			56
		);

		add_submenu_page( 'commercepilot', __( 'Dashboard', 'commercepilot' ), __( 'Dashboard', 'commercepilot' ), 'manage_options', 'commercepilot', array( $this, 'dashboard' ) );
		add_submenu_page(
			'commercepilot',
			__( 'Conversations', 'commercepilot' ),
			__( 'Conversations', 'commercepilot' ) . $badge,
			'manage_options',
			'commercepilot-conversations',
			array( $this, 'conversations_page' )
		);
		add_submenu_page( 'commercepilot', __( 'Settings', 'commercepilot' ), __( 'Settings', 'commercepilot' ), 'manage_options', 'commercepilot-settings', array( $this, 'settings_page' ) );
		add_submenu_page( 'commercepilot', __( 'Connection', 'commercepilot' ), __( 'Connection', 'commercepilot' ), 'manage_options', 'commercepilot-connection', array( $this, 'connection_page' ) );
		add_submenu_page( 'commercepilot', __( 'Usage', 'commercepilot' ), __( 'Usage', 'commercepilot' ), 'manage_options', 'commercepilot-usage', array( $this, 'usage_page' ) );
		add_submenu_page( 'commercepilot', __( 'Account', 'commercepilot' ), __( 'Account', 'commercepilot' ), 'manage_options', 'commercepilot-account', array( $this, 'account_page' ) );
	}

	/**
	 * Keep the sidebar badge fresh on every admin screen while connected.
	 */
	public function enqueue_menu_badge( string $hook ): void {
		if ( ! current_user_can( 'manage_options' ) || ! $this->settings->is_connected() ) {
			return;
		}

		wp_enqueue_style(
			'commercepilot-menu-badge',
			COMMERCEPILOT_URL . 'admin/assets/css/menu-badge.css',
			array(),
			(string) @filemtime( COMMERCEPILOT_PATH . 'admin/assets/css/menu-badge.css' ) ?: COMMERCEPILOT_VERSION
		);
		wp_enqueue_script(
			'commercepilot-menu-badge',
			COMMERCEPILOT_URL . 'admin/assets/js/menu-badge.js',
			array(),
			(string) @filemtime( COMMERCEPILOT_PATH . 'admin/assets/js/menu-badge.js' ) ?: COMMERCEPILOT_VERSION,
			true
		);
		wp_localize_script(
			'commercepilot-menu-badge',
			'commercePilotMenuBadge',
			array(
				'restUrl'      => esc_url_raw( rest_url( 'commercepilot/v1/' ) ),
				'nonce'        => wp_create_nonce( 'wp_rest' ),
				'waitingCount' => $this->waiting_count(),
				'intervalMs'   => 30000,
			)
		);
	}

	private function waiting_count(): int {
		if ( ! $this->api || ! $this->settings->is_connected() ) {
			return 0;
		}

		$cached = get_transient( 'commercepilot_waiting_count' );
		if ( is_numeric( $cached ) ) {
			return max( 0, (int) $cached );
		}

		$result = $this->api->conversations_waiting_count();
		if ( is_wp_error( $result ) ) {
			return 0;
		}

		$data  = is_array( $result['data'] ?? null ) ? $result['data'] : $result;
		$count = absint( $data['waiting_count'] ?? 0 );
		set_transient( 'commercepilot_waiting_count', $count, 30 );

		return $count;
	}

	private function menu_badge( int $count ): string {
		if ( $count < 1 ) {
			return '';
		}

		return sprintf(
			' <span class="awaiting-mod count-%1$d"><span class="pending-count">%2$s</span></span>',
			$count,
			esc_html( number_format_i18n( $count ) )
		);
	}

	public function dashboard(): void {
		( new Dashboard( $this->settings, $this->api, $this->connection ) )->render();
	}

	public function conversations_page(): void {
		( new ConversationsPage( $this->settings ) )->render();
	}

	public function settings_page(): void {
		( new SettingsPage( $this->settings ) )->render();
	}

	public function connection_page(): void {
		( new ConnectionPage( $this->settings, $this->connection ) )->render();
	}

	public function usage_page(): void {
		( new UsagePage( $this->settings, $this->api ) )->render();
	}

	public function account_page(): void {
		( new AccountPage( $this->settings, $this->api ) )->render();
	}

	public function save_settings(): void {
		$this->guard();
		check_admin_referer( 'commercepilot_save_settings' );

		$this->settings->update(
			array(
				'enabled'          => ! empty( $_POST['enabled'] ),
				'assistant_name'   => sanitize_text_field( wp_unslash( (string) ( $_POST['assistant_name'] ?? '' ) ) ),
				'welcome_message'  => sanitize_textarea_field( wp_unslash( (string) ( $_POST['welcome_message'] ?? '' ) ) ),
				'language'         => sanitize_text_field( wp_unslash( (string) ( $_POST['language'] ?? 'en' ) ) ),
				'tone'             => sanitize_text_field( wp_unslash( (string) ( $_POST['tone'] ?? 'helpful' ) ) ),
				'product_search'   => ! empty( $_POST['product_search'] ),
				'recommendations'  => ! empty( $_POST['recommendations'] ),
				'cart'             => ! empty( $_POST['cart'] ),
				'checkout'         => ! empty( $_POST['checkout'] ),
				'order_tracking'   => ! empty( $_POST['order_tracking'] ),
				'position'         => sanitize_text_field( wp_unslash( (string) ( $_POST['position'] ?? 'right' ) ) ),
				'theme'            => sanitize_text_field( wp_unslash( (string) ( $_POST['theme'] ?? 'light' ) ) ),
				'primary_color'    => sanitize_hex_color( wp_unslash( (string) ( $_POST['primary_color'] ?? '#2563eb' ) ) ) ?: '#2563eb',
				'button_text'      => sanitize_text_field( wp_unslash( (string) ( $_POST['button_text'] ?? '' ) ) ),
				'avatar'           => esc_url_raw( wp_unslash( (string) ( $_POST['avatar'] ?? '' ) ) ),
				'avatar_id'        => absint( $_POST['avatar_id'] ?? 0 ),
				'avatar_in_messages' => ! empty( $_POST['avatar_in_messages'] ),
				'show_status'      => ! empty( $_POST['show_status'] ),
				'online_status'    => sanitize_text_field( wp_unslash( (string) ( $_POST['online_status'] ?? 'online' ) ) ),
				'status_text'      => sanitize_text_field( wp_unslash( (string) ( $_POST['status_text'] ?? '' ) ) ),
				'width'            => absint( $_POST['width'] ?? 380 ),
				'delete_data_on_uninstall' => ! empty( $_POST['delete_data_on_uninstall'] ),
			)
		);

		if ( $this->connection && $this->settings->is_connected() ) {
			$this->connection->sync_assistant_settings();
		}

		wp_safe_redirect( admin_url( 'admin.php?page=commercepilot-settings&updated=1' ) );
		exit;
	}

	public function save_connection(): void {
		$this->guard();
		check_admin_referer( 'commercepilot_connect' );

		if ( ! $this->connection ) {
			wp_safe_redirect( admin_url( 'admin.php?page=commercepilot-connection&error=1' ) );
			exit;
		}

		$token  = sanitize_text_field( wp_unslash( (string) ( $_POST['site_token'] ?? '' ) ) );
		$secret = sanitize_text_field( wp_unslash( (string) ( $_POST['site_secret'] ?? '' ) ) );
		$result = $this->connection->connect(
			sanitize_text_field( wp_unslash( (string) ( $_POST['api_url'] ?? '' ) ) ),
			sanitize_text_field( wp_unslash( (string) ( $_POST['site_id'] ?? '' ) ) ),
			$token !== '' ? $token : $this->settings->site_token(),
			$secret !== '' ? $secret : $this->settings->site_secret()
		);

		$query = is_wp_error( $result ) ? 'error=1' : 'connected=1';
		wp_safe_redirect( admin_url( 'admin.php?page=commercepilot-connection&' . $query ) );
		exit;
	}

	public function save_agent_mode(): void {
		$this->guard();
		check_admin_referer( 'commercepilot_agent_mode' );

		if ( ! $this->connection || ! $this->settings->is_connected() ) {
			wp_safe_redirect( admin_url( 'admin.php?page=commercepilot-connection&error=1' ) );
			exit;
		}

		$enabled = isset( $_POST['agent_mode'] ) && (string) wp_unslash( $_POST['agent_mode'] ) === '1';
		$result  = $this->connection->set_agent_mode( $enabled );

		$query = is_wp_error( $result ) ? 'agent=fail' : 'agent=ok';
		wp_safe_redirect( admin_url( 'admin.php?page=commercepilot-connection&' . $query ) );
		exit;
	}

	public function disconnect(): void {
		$this->guard();
		check_admin_referer( 'commercepilot_disconnect' );
		$this->connection?->disconnect();
		wp_safe_redirect( admin_url( 'admin.php?page=commercepilot-connection&disconnected=1' ) );
		exit;
	}

	public function test_connection(): void {
		$this->guard();
		check_admin_referer( 'commercepilot_test' );
		$result = $this->connection ? $this->connection->test() : new \WP_Error( 'missing', '' );
		$query  = is_wp_error( $result ) ? 'test=fail' : 'test=ok';
		wp_safe_redirect( admin_url( 'admin.php?page=commercepilot-connection&' . $query ) );
		exit;
	}

	public function rotate(): void {
		$this->guard();
		check_admin_referer( 'commercepilot_rotate' );
		$result = $this->connection ? $this->connection->rotate() : new \WP_Error( 'missing', '' );
		$query  = is_wp_error( $result ) ? 'rotate=fail' : 'rotate=ok';
		wp_safe_redirect( admin_url( 'admin.php?page=commercepilot-connection&' . $query ) );
		exit;
	}

	private function guard(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'commercepilot' ) );
		}
	}
}
