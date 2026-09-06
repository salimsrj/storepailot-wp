<?php
/**
 * Dashboard view.
 *
 * @package CommercePilot
 *
 * @var array<string, mixed> $status
 * @var array<string, int>   $usage
 * @var array<string, mixed> $site
 * @var array<string, mixed> $health
 * @var \CommercePilot\Settings $this->settings is not available; settings passed via $this in class.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = $this->settings;
?>
<div class="wrap cp-admin">
	<h1><?php esc_html_e( 'CommercePilot', 'commercepilot' ); ?></h1>

	<div class="cp-admin__grid">
		<div class="cp-card">
			<h2><?php esc_html_e( 'Connection', 'commercepilot' ); ?></h2>
			<p class="cp-status cp-status--<?php echo esc_attr( (string) $status['status'] ); ?>">
				<?php echo esc_html( (string) $status['label'] ); ?>
			</p>
			<p><?php echo esc_html( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ); ?></p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=commercepilot-connection' ) ); ?>">
					<?php echo $settings->is_connected() ? esc_html__( 'Configure', 'commercepilot' ) : esc_html__( 'Connect CommercePilot', 'commercepilot' ); ?>
				</a>
			</p>
		</div>

		<div class="cp-card">
			<h2><?php esc_html_e( 'Usage', 'commercepilot' ); ?></h2>
			<p class="cp-metric">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: used, 2: limit */
						__( '%1$d / %2$d', 'commercepilot' ),
						$usage['used'],
						$usage['limit']
					)
				);
				?>
			</p>
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d remaining messages */
						__( 'Remaining: %d', 'commercepilot' ),
						$usage['remaining']
					)
				);
				?>
			</p>
			<p>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=commercepilot-usage' ) ); ?>"><?php esc_html_e( 'View Usage', 'commercepilot' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=commercepilot-account' ) ); ?>"><?php esc_html_e( 'Upgrade', 'commercepilot' ); ?></a>
			</p>
		</div>

		<div class="cp-card">
			<h2><?php esc_html_e( 'Status', 'commercepilot' ); ?></h2>
			<ul class="cp-health">
				<li><?php esc_html_e( 'WordPress', 'commercepilot' ); ?>: <?php echo ! empty( $health['wordpress'] ) ? '✓' : '✕'; ?></li>
				<li><?php esc_html_e( 'WooCommerce', 'commercepilot' ); ?>: <?php echo ! empty( $health['woocommerce'] ) ? '✓' : '✕'; ?></li>
				<li><?php esc_html_e( 'CommercePilot', 'commercepilot' ); ?>: <?php echo ! empty( $health['configured'] ) ? '✓' : '✕'; ?></li>
				<li><?php esc_html_e( 'API', 'commercepilot' ); ?>: <?php echo ! empty( $health['api'] ) ? '✓' : '✕'; ?></li>
				<li><?php esc_html_e( 'Chatbot', 'commercepilot' ); ?>: <?php echo $settings->get( 'enabled' ) ? esc_html__( 'Enabled', 'commercepilot' ) : esc_html__( 'Disabled', 'commercepilot' ); ?></li>
				<li><?php esc_html_e( 'Plugin version', 'commercepilot' ); ?>: <?php echo esc_html( COMMERCEPILOT_VERSION ); ?></li>
			</ul>
		</div>
	</div>
</div>
