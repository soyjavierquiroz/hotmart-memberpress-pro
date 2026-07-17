<?php
namespace HMP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Activator {
	public static function activate(): void {
		Upgrader::run( true );
		Settings::add_defaults();
		Cleanup::schedule();
		Lifecycle::schedule();
	}

	public static function maybe_upgrade(): void {
		Upgrader::maybe_upgrade();
	}

	public static function deactivate(): void {
		Cleanup::unschedule();
		Lifecycle::unschedule();
	}
}
