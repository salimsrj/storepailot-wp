<?php
/**
 * PSR-style autoloader for CommercePilot class-*.php files.
 *
 * @package CommercePilot
 */

declare(strict_types=1);

namespace CommercePilot;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Autoloader {

	public static function register(): void {
		spl_autoload_register( array( self::class, 'load' ) );
	}

	public static function load( string $class ): void {
		$prefix = 'CommercePilot\\';

		if ( ! str_starts_with( $class, $prefix ) ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$parts    = explode( '\\', $relative );
		$name     = array_pop( $parts );
		$file     = 'class-' . strtolower( (string) preg_replace( '/(?<!^)[A-Z]/', '-$0', $name ) ) . '.php';

		if ( array( 'Frontend' ) === $parts ) {
			$path = COMMERCEPILOT_PATH . 'public/' . $file;
		} else {
			$subdir = strtolower( implode( '/', $parts ) );
			$path   = COMMERCEPILOT_PATH . 'includes/' . ( $subdir ? $subdir . '/' : '' ) . $file;
		}

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
}
