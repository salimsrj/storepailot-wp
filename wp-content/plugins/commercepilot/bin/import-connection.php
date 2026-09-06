<?php
/**
 * Import Laravel site credentials into WordPress options.
 *
 * Usage: php import-connection.php /path/to/creds.json
 *
 * @package CommercePilot
 */

declare(strict_types=1);

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

$path = $argv[1] ?? '';
if ( $path === '' || ! is_readable( $path ) ) {
	fwrite( STDERR, "missing credentials file\n" );
	exit( 1 );
}

$payload = json_decode( (string) file_get_contents( $path ), true );
@unlink( $path );

if ( ! is_array( $payload ) || empty( $payload['id'] ) || empty( $payload['token'] ) || empty( $payload['secret'] ) ) {
	fwrite( STDERR, "invalid credentials file\n" );
	exit( 1 );
}

require_once dirname( __DIR__, 4 ) . '/wp-load.php';
require_once dirname( __DIR__ ) . '/includes/class-autoloader.php';

CommercePilot\Autoloader::register();

$settings = new CommercePilot\Settings();
$settings->ensure_defaults();
$settings->update(
	array(
		'api_url' => rtrim( (string) ( $payload['api_url'] ?? 'http://storepailotadmin.test' ), '/' ),
		'enabled' => true,
	)
);
$settings->store_credentials(
	(string) $payload['id'],
	(string) $payload['token'],
	(string) $payload['secret']
);

echo $settings->is_connected() ? "connected\n" : "failed\n";
exit( $settings->is_connected() ? 0 : 1 );
