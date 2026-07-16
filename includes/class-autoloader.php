<?php
namespace HMP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Autoloader {
	public static function register(): void {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	private static function autoload( string $class ): void {
		$prefix = __NAMESPACE__ . '\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$parts    = explode( '\\', $relative );
		$class    = array_pop( $parts );
		$parts    = array_map( 'strtolower', $parts );
		$filename = 'class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';
		$path     = HMP_PLUGIN_PATH . 'includes/' . ( $parts ? implode( '/', $parts ) . '/' : '' ) . $filename;

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
}
