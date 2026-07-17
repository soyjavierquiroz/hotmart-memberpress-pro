<?php
namespace HMP;

use HMP\Events\Event_Processor;
use HMP\MemberPress\Revocation_Service;
use HMP\Repositories\Activation_Repository;
use HMP\Repositories\Event_Repository;
use HMP\Webhook\Payload_Normalizer;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Lifecycle {
	public const GRACE_HOOK = 'hmp_process_grace_expirations';
	public const RETRY_HOOK = 'hmp_retry_failed_events';
	private static ?Event_Repository $events = null;
	private static ?Activation_Repository $activations = null;
	private static ?Event_Processor $processor = null;
	private static ?Revocation_Service $revocations = null;

	public static function register( Event_Repository $events, Activation_Repository $activations, Event_Processor $processor, Revocation_Service $revocations ): void {
		self::$events = $events; self::$activations = $activations; self::$processor = $processor; self::$revocations = $revocations;
		add_filter( 'cron_schedules', array( __CLASS__, 'schedules' ) );
		add_action( self::GRACE_HOOK, array( __CLASS__, 'process_grace' ) );
		add_action( self::RETRY_HOOK, array( __CLASS__, 'retry_failed' ) );
		self::schedule();
	}

	public static function schedules( array $schedules ): array {
		$schedules['hmp_fifteen_minutes'] = array( 'interval' => 15 * MINUTE_IN_SECONDS, 'display' => 'Every 15 minutes' );
		return $schedules;
	}

	public static function schedule(): void {
		add_filter( 'cron_schedules', array( __CLASS__, 'schedules' ) );
		if ( ! wp_next_scheduled( self::GRACE_HOOK ) ) wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::GRACE_HOOK );
		if ( ! wp_next_scheduled( self::RETRY_HOOK ) ) wp_schedule_event( time() + 15 * MINUTE_IN_SECONDS, 'hmp_fifteen_minutes', self::RETRY_HOOK );
	}

	public static function unschedule(): void { wp_clear_scheduled_hook( self::GRACE_HOOK ); wp_clear_scheduled_hook( self::RETRY_HOOK ); }

	public static function process_grace(): int {
		if ( ! self::$activations || ! self::$revocations ) return 0;
		$count = 0;
		foreach ( self::$activations->find_grace_expired( 100 ) as $row ) {
			$result = self::$revocations->expire_grace( (int) $row->id );
			if ( ! is_wp_error( $result ) ) ++$count;
		}
		return $count;
	}

	public static function retry_failed(): int {
		if ( ! self::$events || ! self::$processor ) return 0;
		$count = 0; $normalizer = new Payload_Normalizer();
		foreach ( self::$events->retryable( 20 ) as $event ) {
			$payload = json_decode( $event->payload, true );
			if ( is_array( $payload ) ) { self::$processor->process( (int) $event->id, $normalizer->normalize( $payload ) ); ++$count; }
		}
		return $count;
	}
}
