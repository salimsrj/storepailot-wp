<?php
/**
 * Standalone security tests (no WordPress bootstrap).
 *
 * @package CommercePilot
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

require_once dirname( __DIR__ ) . '/includes/class-security.php';

use CommercePilot\Security;

$failed = 0;

function cp_assert( bool $ok, string $message ): void {
	global $failed;
	if ( ! $ok ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		++$failed;
		return;
	}
	echo "OK: {$message}\n";
}

$secret    = 'test-site-secret';
$timestamp = (string) time();
$body      = '{"visitor_id":"11111111-1111-1111-1111-111111111111"}';
$signature = Security::sign( $timestamp, $body, $secret );

cp_assert( $signature === hash_hmac( 'sha256', $timestamp . '.' . $body, $secret ), 'HMAC matches Laravel algorithm' );
cp_assert( Security::verify_signature( $timestamp, $body, $secret, $signature ), 'valid signature accepted' );
cp_assert( ! Security::verify_signature( $timestamp, $body, $secret, 'deadbeef' ), 'invalid signature rejected' );
cp_assert( ! Security::verify_signature( (string) ( time() - 400 ), $body, $secret, Security::sign( (string) ( time() - 400 ), $body, $secret ) ), 'expired timestamp rejected' );
cp_assert( ! Security::verify_signature( 'abc', $body, $secret, $signature ), 'non-numeric timestamp rejected' );
// Request-bound signing: must match HmacSigner::signRequest() in Laravel.
$get_a = Security::sign_request( $timestamp, 'GET', '/api/v1/conversations', '', $secret );
$get_b = Security::sign_request( $timestamp, 'GET', '/api/v1/conversations/abc/messages', '', $secret );
$post  = Security::sign_request( $timestamp, 'POST', '/api/v1/conversations', '', $secret );

cp_assert(
	$get_a === hash_hmac( 'sha256', $timestamp . '.' . 'GET./api/v1/conversations.', $secret ),
	'request signature matches Laravel canonical form'
);
$ordered   = Security::sign_request( $timestamp, 'GET', '/api/v1/conversations/abc/messages?visitor_id=11111111-1111-1111-1111-111111111111&after_id=0', '', $secret );
$reordered = Security::sign_request( $timestamp, 'GET', '/api/v1/conversations/abc/messages?after_id=0&visitor_id=11111111-1111-1111-1111-111111111111', '', $secret );
cp_assert( $ordered === $reordered, 'query parameter order does not change the signature' );

cp_assert( $get_a !== $get_b, 'same-second GETs to different paths differ' );
cp_assert( $get_a !== $post, 'same path with a different method differs' );
cp_assert( $get_a !== Security::sign( $timestamp, '', $secret ), 'request signing differs from plain body signing' );

cp_assert( Security::is_uuid( '11111111-1111-1111-1111-111111111111' ), 'valid UUID accepted' );
cp_assert( ! Security::is_uuid( 'not-a-uuid' ), 'invalid UUID rejected' );
cp_assert( ! Security::is_uuid( '' ), 'empty UUID rejected' );

if ( $failed > 0 ) {
	exit( 1 );
}

echo "All security tests passed.\n";
