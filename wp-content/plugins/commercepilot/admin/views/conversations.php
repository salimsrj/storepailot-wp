<?php
/**
 * Conversations inbox view. The panes are populated by conversations.js.
 *
 * @package CommercePilot
 *
 * @var bool $connected
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap cp-admin cp-inbox-wrap">
	<h1><?php esc_html_e( 'Conversations', 'commercepilot' ); ?></h1>

	<?php if ( ! $connected ) : ?>
		<div class="notice notice-warning">
			<p>
				<?php esc_html_e( 'Connect CommercePilot to view chat history.', 'commercepilot' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=commercepilot-connection' ) ); ?>"><?php esc_html_e( 'Go to Connection', 'commercepilot' ); ?></a>
			</p>
		</div>
	<?php else : ?>
		<div class="cp-inbox" id="cp-inbox">
			<div class="cp-inbox__list">
				<div class="cp-inbox__list-head">
					<label class="screen-reader-text" for="cp-inbox-filter"><?php esc_html_e( 'Filter conversations', 'commercepilot' ); ?></label>
					<select id="cp-inbox-filter">
						<option value=""><?php esc_html_e( 'All conversations', 'commercepilot' ); ?></option>
						<option value="human"><?php esc_html_e( 'Taken over', 'commercepilot' ); ?></option>
						<option value="ai"><?php esc_html_e( 'Handled by AI', 'commercepilot' ); ?></option>
					</select>
					<button type="button" class="button" id="cp-inbox-refresh"><?php esc_html_e( 'Refresh', 'commercepilot' ); ?></button>
				</div>
				<ul class="cp-inbox__items" id="cp-inbox-items">
					<li class="cp-inbox__empty"><?php esc_html_e( 'Loading…', 'commercepilot' ); ?></li>
				</ul>
			</div>

			<div class="cp-inbox__thread" id="cp-inbox-thread">
				<div class="cp-inbox__placeholder"><?php esc_html_e( 'Select a conversation to view its history.', 'commercepilot' ); ?></div>
			</div>
		</div>
	<?php endif; ?>
</div>
