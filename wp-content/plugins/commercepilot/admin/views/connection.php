<?php
/**
 * Connection view.
 *
 * @package CommercePilot
 *
 * @var array<string, string> $status
 * @var string                $masked
 * @var array{enable_agent:bool,can_enable_agent:bool,subscription:?array<string,mixed>} $agent
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
} elseif ( isset( $_GET['agent'] ) && 'ok' === $_GET['agent'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$notice = __( 'Agent Mode updated.', 'commercepilot' );
}

$error = '';
if ( isset( $_GET['error'] ) || ( isset( $_GET['test'] ) && 'fail' === $_GET['test'] ) || ( isset( $_GET['rotate'] ) && 'fail' === $_GET['rotate'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$error = __( 'Connection failed. Check the API URL and credentials.', 'commercepilot' );
} elseif ( isset( $_GET['agent'] ) && 'fail' === $_GET['agent'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$error = __( 'Could not update Agent Mode. An active subscription is required to enable it.', 'commercepilot' );
}

$agent_enabled     = ! empty( $agent['enable_agent'] );
$can_enable_agent  = ! empty( $agent['can_enable_agent'] );
$agent_disabled    = ! $can_enable_agent && ! $agent_enabled;
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

	<?php if ( $this->settings->is_connected() ) : ?>
		<div class="cp-card cp-agent-mode">
			<h2><?php esc_html_e( 'Agent Mode', 'commercepilot' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Off (default): visitors can send direct messages for your team to answer. On: the AI agent replies automatically. Enabling Agent Mode requires an active subscription.', 'commercepilot' ); ?>
			</p>

			<?php if ( $agent_disabled ) : ?>
				<div class="notice notice-warning inline">
					<p>
						<?php esc_html_e( 'Choose a plan to unlock Agent Mode.', 'commercepilot' ); ?>
						<a href="<?php echo esc_url( COMMERCEPILOT_PLANS_URL ); ?>" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'View plans', 'commercepilot' ); ?>
						</a>
					</p>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'commercepilot_agent_mode' ); ?>
				<input type="hidden" name="action" value="commercepilot_agent_mode" />
				<label class="cp-switch" for="cp-agent-mode">
					<input
						type="checkbox"
						id="cp-agent-mode"
						name="agent_mode"
						value="1"
						<?php checked( $agent_enabled ); ?>
						<?php disabled( $agent_disabled ); ?>
					/>
					<span class="cp-switch__label">
						<?php echo $agent_enabled ? esc_html__( 'Agent Mode is on', 'commercepilot' ) : esc_html__( 'Agent Mode is off', 'commercepilot' ); ?>
					</span>
				</label>
				<?php
				submit_button(
					__( 'Save Agent Mode', 'commercepilot' ),
					'secondary',
					'submit',
					false,
					$agent_disabled ? array( 'disabled' => 'disabled' ) : array()
				);
				?>
			</form>
		</div>
	<?php endif; ?>

	<p><?php esc_html_e( 'Sign up to create a CommercePilot account, then paste the API URL, site ID, live token, and site secret here. Tokens are stored encrypted and never sent to the browser.', 'commercepilot' ); ?></p>

	<p class="cp-signup-actions">
		<a
			class="button button-primary"
			href="<?php echo esc_url( COMMERCEPILOT_SIGNUP_URL ); ?>"
			target="_blank"
			rel="noopener noreferrer"
		>
			<?php esc_html_e( 'Sign Up', 'commercepilot' ); ?>
		</a>
		<span class="description">
			<?php esc_html_e( 'Opens the CommercePilot signup page. After signup, copy your credentials into the form below.', 'commercepilot' ); ?>
		</span>
	</p>

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
