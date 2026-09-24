<?php
/**
 * Settings defaults and sanitization tests (no WordPress option API).
 *
 * @package CommercePilot
 */

declare(strict_types=1);

$failed = 0;

function cp_settings_assert( bool $ok, string $message ): void {
	global $failed;
	if ( ! $ok ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		++$failed;
		return;
	}
	echo "OK: {$message}\n";
}

if ( ! defined( 'COMMERCEPILOT_API_URL' ) ) {
	define( 'COMMERCEPILOT_API_URL', 'https://api.commercepilot.com' );
}

$defaults_file = file_get_contents( dirname( __DIR__ ) . '/includes/class-settings.php' );
cp_settings_assert( is_string( $defaults_file ) && str_contains( $defaults_file, 'site_token' ), 'settings store includes site_token' );
cp_settings_assert( str_contains( (string) $defaults_file, 'site_secret' ), 'settings store includes site_secret' );
cp_settings_assert( str_contains( (string) $defaults_file, 'api_url' ), 'settings store includes api_url' );
cp_settings_assert( str_contains( (string) $defaults_file, "'agent_mode'" ), 'settings defaults include agent_mode' );
cp_settings_assert( str_contains( (string) file_get_contents( dirname( __DIR__ ) . '/includes/class-connection.php' ), 'enable_agent' ), 'connection sync includes enable_agent' );
cp_settings_assert( ! str_contains( (string) file_get_contents( dirname( __DIR__ ) . '/includes/class-assets.php' ), 'site_token' ), 'assets do not localize site_token' );
cp_settings_assert( ! str_contains( (string) file_get_contents( dirname( __DIR__ ) . '/includes/class-assets.php' ), 'site_secret' ), 'assets do not localize site_secret' );
cp_settings_assert( ! str_contains( (string) file_get_contents( dirname( __DIR__ ) . '/public/assets/js/chatbot.js' ), 'openai' ), 'frontend JS does not mention OpenAI' );
cp_settings_assert( ! str_contains( (string) file_get_contents( dirname( __DIR__ ) . '/includes/class-api-client.php' ), 'api.openai.com' ), 'plugin does not call OpenAI' );

$main = (string) file_get_contents( dirname( __DIR__ ) . '/commercepilot.php' );
cp_settings_assert( str_contains( $main, 'Requires Plugins: woocommerce' ), 'WooCommerce declared as a required plugin' );

if ( $failed > 0 ) {
	exit( 1 );
}

echo "All settings tests passed.\n";
