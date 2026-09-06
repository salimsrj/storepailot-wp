<?php
/**
 * Usage view.
 *
 * @package CommercePilot
 *
 * @var array<string, int> $usage
 * @var string             $error
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$percent = $usage['limit'] > 0 ? min( 100, (int) round( ( $usage['used'] / $usage['limit'] ) * 100 ) ) : 0;
$start   = gmdate( 'M j' );
$end     = gmdate( 'M j', strtotime( 'last day of this month' ) );
?>
<div class="wrap cp-admin">
	<h1><?php esc_html_e( 'Usage', 'commercepilot' ); ?></h1>

	<?php if ( $error ) : ?>
		<div class="notice notice-warning"><p><?php echo esc_html( $error ); ?></p></div>
	<?php endif; ?>

	<div class="cp-card">
		<p class="cp-metric"><?php echo esc_html( sprintf( '%d / %d', $usage['used'], $usage['limit'] ) ); ?></p>
		<p><?php esc_html_e( 'messages used', 'commercepilot' ); ?></p>
		<div class="cp-progress"><span style="width: <?php echo esc_attr( (string) $percent ); ?>%"></span></div>
		<p>
			<?php
			echo esc_html(
				sprintf(
					/* translators: %d remaining */
					__( '%d messages remaining', 'commercepilot' ),
					$usage['remaining']
				)
			);
			?>
		</p>
		<p>
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: period start, 2: period end */
					__( 'Period: %1$s – %2$s', 'commercepilot' ),
					$start,
					$end
				)
			);
			?>
		</p>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=commercepilot-account' ) ); ?>"><?php esc_html_e( 'Upgrade', 'commercepilot' ); ?></a>
		</p>
	</div>
</div>
