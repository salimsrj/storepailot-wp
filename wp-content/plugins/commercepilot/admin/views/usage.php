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

// Prefer the billing period reported by the service; fall back to the
// calendar month.
$period_start = ! empty( $usage['period_start'] ) ? strtotime( (string) $usage['period_start'] ) : false;
$period_end   = ! empty( $usage['period_end'] ) ? strtotime( (string) $usage['period_end'] ) : false;
$start        = wp_date( 'M j', $period_start ? $period_start : strtotime( 'first day of this month 00:00' ) );
$end          = wp_date( 'M j', $period_end ? $period_end : strtotime( 'last day of this month 23:59' ) );
?>
<div class="wrap cp-admin">
	<h1><?php esc_html_e( 'Usage', 'commercepilot' ); ?></h1>

	<?php if ( $error ) : ?>
		<div class="notice notice-warning"><p><?php echo esc_html( $error ); ?></p></div>
	<?php endif; ?>

	<div class="cp-card">
		<p class="cp-metric"><?php echo esc_html( number_format_i18n( $usage['used'] ) . ' / ' . number_format_i18n( $usage['limit'] ) ); ?></p>
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
