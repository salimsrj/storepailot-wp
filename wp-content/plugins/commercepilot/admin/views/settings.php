<?php
/**
 * Settings view.
 *
 * @package CommercePilot
 *
 * @var array<string, mixed> $settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap cp-admin">
	<h1><?php esc_html_e( 'Settings', 'commercepilot' ); ?></h1>
	<?php if ( isset( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success"><p><?php esc_html_e( 'Settings saved.', 'commercepilot' ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'commercepilot_save_settings' ); ?>
		<input type="hidden" name="action" value="commercepilot_save_settings" />

		<h2><?php esc_html_e( 'General', 'commercepilot' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><label for="cp-enabled"><?php esc_html_e( 'Enable chatbot', 'commercepilot' ); ?></label></th>
				<td><input type="checkbox" id="cp-enabled" name="enabled" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?> /></td>
			</tr>
			<tr>
				<th><label for="cp-name"><?php esc_html_e( 'Assistant name', 'commercepilot' ); ?></label></th>
				<td><input class="regular-text" type="text" id="cp-name" name="assistant_name" value="<?php echo esc_attr( (string) $settings['assistant_name'] ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="cp-welcome"><?php esc_html_e( 'Welcome message', 'commercepilot' ); ?></label></th>
				<td><textarea class="large-text" id="cp-welcome" name="welcome_message" rows="3"><?php echo esc_textarea( (string) $settings['welcome_message'] ); ?></textarea></td>
			</tr>
			<tr>
				<th><label for="cp-language"><?php esc_html_e( 'Language', 'commercepilot' ); ?></label></th>
				<td><input class="small-text" type="text" id="cp-language" name="language" value="<?php echo esc_attr( (string) $settings['language'] ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="cp-tone"><?php esc_html_e( 'Tone', 'commercepilot' ); ?></label></th>
				<td><input class="regular-text" type="text" id="cp-tone" name="tone" value="<?php echo esc_attr( (string) $settings['tone'] ); ?>" /></td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Features', 'commercepilot' ); ?></h2>
		<table class="form-table">
			<?php foreach ( array( 'product_search' => __( 'Product search', 'commercepilot' ), 'recommendations' => __( 'Recommendations', 'commercepilot' ), 'cart' => __( 'Add to cart', 'commercepilot' ), 'checkout' => __( 'Checkout', 'commercepilot' ), 'order_tracking' => __( 'Order tracking (reserved)', 'commercepilot' ) ) as $key => $label ) : ?>
				<tr>
					<th><?php echo esc_html( $label ); ?></th>
					<td><input type="checkbox" name="<?php echo esc_attr( $key ); ?>" value="1" <?php checked( ! empty( $settings[ $key ] ) ); ?> /></td>
				</tr>
			<?php endforeach; ?>
		</table>

		<h2><?php esc_html_e( 'Appearance', 'commercepilot' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><label for="cp-position"><?php esc_html_e( 'Position', 'commercepilot' ); ?></label></th>
				<td>
					<select id="cp-position" name="position">
						<option value="right" <?php selected( $settings['position'], 'right' ); ?>><?php esc_html_e( 'Right', 'commercepilot' ); ?></option>
						<option value="left" <?php selected( $settings['position'], 'left' ); ?>><?php esc_html_e( 'Left', 'commercepilot' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="cp-theme"><?php esc_html_e( 'Theme', 'commercepilot' ); ?></label></th>
				<td>
					<select id="cp-theme" name="theme">
						<option value="light" <?php selected( $settings['theme'], 'light' ); ?>><?php esc_html_e( 'Light', 'commercepilot' ); ?></option>
						<option value="dark" <?php selected( $settings['theme'], 'dark' ); ?>><?php esc_html_e( 'Dark', 'commercepilot' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="cp-color"><?php esc_html_e( 'Primary color', 'commercepilot' ); ?></label></th>
				<td><input type="color" id="cp-color" name="primary_color" value="<?php echo esc_attr( (string) $settings['primary_color'] ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="cp-button"><?php esc_html_e( 'Button text', 'commercepilot' ); ?></label></th>
				<td><input class="regular-text" type="text" id="cp-button" name="button_text" value="<?php echo esc_attr( (string) $settings['button_text'] ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="cp-avatar"><?php esc_html_e( 'Profile picture', 'commercepilot' ); ?></label></th>
				<td>
					<div class="cp-avatar-field">
						<div class="cp-avatar-preview" id="cp-avatar-preview">
							<?php if ( ! empty( $settings['avatar'] ) ) : ?>
								<img src="<?php echo esc_url( (string) $settings['avatar'] ); ?>" alt="" />
							<?php else : ?>
								<span class="cp-avatar-empty"><?php esc_html_e( 'No image', 'commercepilot' ); ?></span>
							<?php endif; ?>
						</div>
						<div class="cp-avatar-controls">
							<p>
								<button type="button" class="button" id="cp-avatar-select"><?php esc_html_e( 'Select image', 'commercepilot' ); ?></button>
								<button type="button" class="button button-link-delete" id="cp-avatar-remove" <?php disabled( empty( $settings['avatar'] ) ); ?>><?php esc_html_e( 'Remove', 'commercepilot' ); ?></button>
							</p>
							<input type="hidden" id="cp-avatar-id" name="avatar_id" value="<?php echo esc_attr( (string) $settings['avatar_id'] ); ?>" />
							<input class="regular-text" type="url" id="cp-avatar" name="avatar" value="<?php echo esc_attr( (string) $settings['avatar'] ); ?>" placeholder="https://" />
							<p class="description"><?php esc_html_e( 'Pick an image from the media library, or paste an image URL. Shown in the chat header and next to assistant replies.', 'commercepilot' ); ?></p>
						</div>
					</div>
				</td>
			</tr>
			<tr>
				<th><label for="cp-avatar-messages"><?php esc_html_e( 'Show picture on replies', 'commercepilot' ); ?></label></th>
				<td>
					<input type="checkbox" id="cp-avatar-messages" name="avatar_in_messages" value="1" <?php checked( ! empty( $settings['avatar_in_messages'] ) ); ?> />
					<span class="description"><?php esc_html_e( 'Display the profile picture beside each assistant message.', 'commercepilot' ); ?></span>
				</td>
			</tr>
			<tr>
				<th><label for="cp-width"><?php esc_html_e( 'Width', 'commercepilot' ); ?></label></th>
				<td><input type="number" id="cp-width" name="width" min="300" max="560" value="<?php echo esc_attr( (string) $settings['width'] ); ?>" /></td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Availability', 'commercepilot' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><label for="cp-show-status"><?php esc_html_e( 'Show status', 'commercepilot' ); ?></label></th>
				<td>
					<input type="checkbox" id="cp-show-status" name="show_status" value="1" <?php checked( ! empty( $settings['show_status'] ) ); ?> />
					<span class="description"><?php esc_html_e( 'Display an availability indicator in the chat header.', 'commercepilot' ); ?></span>
				</td>
			</tr>
			<tr>
				<th><label for="cp-online-status"><?php esc_html_e( 'Online status', 'commercepilot' ); ?></label></th>
				<td>
					<select id="cp-online-status" name="online_status">
						<?php foreach ( \CommercePilot\Settings::status_choices() as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['online_status'], $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="cp-status-text"><?php esc_html_e( 'Status label', 'commercepilot' ); ?></label></th>
				<td>
					<input class="regular-text" type="text" id="cp-status-text" name="status_text" value="<?php echo esc_attr( (string) $settings['status_text'] ); ?>" placeholder="<?php esc_attr_e( 'Typically replies in a few minutes', 'commercepilot' ); ?>" />
					<p class="description"><?php esc_html_e( 'Optional. Overrides the default label next to the status dot.', 'commercepilot' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Privacy', 'commercepilot' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Delete CommercePilot data on uninstall', 'commercepilot' ); ?></th>
				<td><input type="checkbox" name="delete_data_on_uninstall" value="1" <?php checked( ! empty( $settings['delete_data_on_uninstall'] ) ); ?> /></td>
			</tr>
		</table>

		<?php submit_button( __( 'Save settings', 'commercepilot' ) ); ?>
	</form>
</div>
