<?php
/**
 * Account view.
 *
 * @package CommercePilot
 *
 * @var array<string, mixed> $site
 * @var string               $upgrade_url
 * @var \CommercePilot\Settings $this->settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap cp-admin">
	<h1><?php esc_html_e( 'Account', 'commercepilot' ); ?></h1>

	<div class="cp-card">
		<p><?php esc_html_e( 'Plans, billing, and upgrades are managed in CommercePilot. This plugin does not calculate pricing.', 'commercepilot' ); ?></p>
		<?php if ( ! empty( $site['name'] ) ) : ?>
			<p><?php echo esc_html( (string) $site['name'] ); ?> — <?php echo esc_html( (string) ( $site['status'] ?? '' ) ); ?></p>
		<?php endif; ?>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( $upgrade_url ); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Upgrade Now', 'commercepilot' ); ?>
			</a>
		</p>
	</div>
</div>
