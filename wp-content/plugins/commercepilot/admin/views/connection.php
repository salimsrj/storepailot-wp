<?php
/**
 * Connection view.
 *
 * @package CommercePilot
 *
 * @var array<string, string> $status
 * @var string                $masked
 * @var \CommercePilot\Settings $this->settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$notice = '';
if ( isset( $_GET['connected'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$notice = __( 'Connected to CommercePilot.', 'commercepilot' );
} elseif ( isset( $_GET['disconnected'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$notice = __( 'Disconnected.', 'commercepilot' );
} elseif ( isset( $_GET['test'] ) && 'ok' === $_GET['test'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$notice = __( 'Connected ✓', 'commercepilot' );
} elseif ( isset( $_GET['rotate'] ) && 'ok' === $_GET['rotate'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$notice = __( 'Credentials rotated.', 'commercepilot' );
}

$error = '';
if ( isset( $_GET['error'] ) || ( isset( $_GET['test'] ) && 'fail' === $_GET['test'] ) || ( isset( $_GET['rotate'] ) && 'fail' === $_GET['rotate'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$error = __( 'Connection failed. Check the API URL and credentials.', 'commercepilot' );
}
?>
<div class="wrap cp-admin">
	<h1><?php esc_html_e( 'Connection', 'commercepilot' ); ?></h1>

	<?php if ( $notice ) : ?>
		<div class="notice notice-success"><p><?php echo esc_html( $notice ); ?></p></div>
	<?php endif; ?>
	<?php if ( $error ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
	<?php endif; ?>

	<div class="cp-card">
		<p class="cp-status cp-status--<?php echo esc_attr( $status['status'] ); ?>"><?php echo esc_html( $status['label'] ); ?></p>
		<?php if ( $masked ) : ?>
			<p><?php esc_html_e( 'Site ID', 'commercepilot' ); ?>: <code><?php echo esc_html( $masked ); ?></code></p>
		<?php endif; ?>
	</div>

	<p><?php esc_html_e( 'Create a site in the CommercePilot admin, then paste the site ID, live token, and site secret here. Tokens are stored encrypted and never sent to the browser.', 'commercepilot' ); ?></p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'commercepilot_connect' ); ?>
		<input type="hidden" name="action" value="commercepilot_connect" />
		<table class="form-table">
			<tr>
				<th><label for="cp-api"><?php esc_html_e( 'API URL', 'commercepilot' ); ?></label></th>
				<td><input class="regular-text" type="url" id="cp-api" name="api_url" value="<?php echo esc_attr( $this->settings->api_url() ); ?>" required /></td>
			</tr>
			<tr>
				<th><label for="cp-site-id"><?php esc_html_e( 'Site ID', 'commercepilot' ); ?></label></th>
				<td><input class="regular-text" type="text" id="cp-site-id" name="site_id" value="<?php echo esc_attr( (string) $this->settings->get( 'site_id' ) ); ?>" required /></td>
			</tr>
			<tr>
				<th><label for="cp-token"><?php esc_html_e( 'Site token', 'commercepilot' ); ?></label></th>
				<td><input class="regular-text" type="password" id="cp-token" name="site_token" autocomplete="off" placeholder="<?php echo $this->settings->site_token() ? esc_attr__( 'Stored securely — enter to replace', 'commercepilot' ) : ''; ?>" /></td>
			</tr>
			<tr>
				<th><label for="cp-secret"><?php esc_html_e( 'Site secret', 'commercepilot' ); ?></label></th>
				<td><input class="regular-text" type="password" id="cp-secret" name="site_secret" autocomplete="off" placeholder="<?php echo $this->settings->site_secret() ? esc_attr__( 'Stored securely — enter to replace', 'commercepilot' ) : ''; ?>" /></td>
			</tr>
		</table>
		<?php submit_button( $this->settings->is_connected() ? __( 'Reconnect', 'commercepilot' ) : __( 'Connect CommercePilot', 'commercepilot' ) ); ?>
	</form>

	<?php if ( $this->settings->is_connected() ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cp-inline-form">
			<?php wp_nonce_field( 'commercepilot_test' ); ?>
			<input type="hidden" name="action" value="commercepilot_test" />
			<?php submit_button( __( 'Test Connection', 'commercepilot' ), 'secondary', 'submit', false ); ?>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cp-inline-form">
			<?php wp_nonce_field( 'commercepilot_rotate' ); ?>
			<input type="hidden" name="action" value="commercepilot_rotate" />
			<?php submit_button( __( 'Rotate credentials', 'commercepilot' ), 'secondary', 'submit', false ); ?>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cp-inline-form">
			<?php wp_nonce_field( 'commercepilot_disconnect' ); ?>
			<input type="hidden" name="action" value="commercepilot_disconnect" />
			<?php submit_button( __( 'Disconnect', 'commercepilot' ), 'delete', 'submit', false ); ?>
		</form>
	<?php endif; ?>
</div>
