<?php
namespace HMP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Cleanup {
	public const HOOK = 'hmp_daily_cleanup';

	public static function register(): void {
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			self::schedule();
		}
	}

	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK );
		}
	}

	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::HOOK );
	}

	public static function run(): void {
		global $wpdb;
		$days = max( 1, (int) Settings::get( 'hmp_payload_retention_days' ) );
		$date = gmdate( 'Y-m-d H:i:s', time() - ( DAY_IN_SECONDS * $days ) );
		$table = $wpdb->prefix . 'hmp_events';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE status IN ('processed','ignored') AND received_at < %s",
				$date
			)
		);
	}
}
