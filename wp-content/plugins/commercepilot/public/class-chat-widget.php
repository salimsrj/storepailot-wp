<?php
/**
 * Storefront chat widget.
 *
 * @package CommercePilot
 */

declare(strict_types=1);

namespace CommercePilot\Frontend;

use CommercePilot\Assets;
use CommercePilot\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ChatWidget {

	public function __construct(
		private Settings $settings,
		private Assets $assets
	) {}

	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this->assets, 'enqueue_public' ) );
		add_action( 'wp_footer', array( $this, 'render' ) );
	}

	public function render(): void {
		if ( is_admin() || ! $this->settings->get( 'enabled' ) || ! $this->settings->is_connected() ) {
			return;
		}

		$config = $this->settings->public_config();
		$width  = (int) $config['width'];
		$color  = (string) $config['primary_color'];
		?>
		<div
			id="cp-chatbot"
			class="cp-chatbot cp-chatbot--<?php echo esc_attr( (string) $config['position'] ); ?> cp-chatbot--<?php echo esc_attr( (string) $config['theme'] ); ?>"
			style="--cp-primary: <?php echo esc_attr( $color ); ?>; --cp-width: <?php echo esc_attr( (string) $width ); ?>px;"
			hidden
		></div>
		<?php
	}
}
