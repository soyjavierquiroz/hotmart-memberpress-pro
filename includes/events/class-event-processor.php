<?php
namespace HMP\Events;

use HMP\MemberPress\MemberPress_Service;
use HMP\Repositories\Event_Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Event_Processor {
	private Event_Repository $events;
	private MemberPress_Service $memberpress;

	public function __construct( Event_Repository $events, MemberPress_Service $memberpress ) {
		$this->events      = $events;
		$this->memberpress = $memberpress;
	}

	public function process( int $event_id, array $payload ) {
		$this->events->increment_attempts( $event_id );
		$event = (string) ( $payload['event'] ?? '' );

		if ( ! in_array( $event, array( 'PURCHASE_APPROVED', 'PURCHASE_COMPLETE' ), true ) ) {
			$message = sprintf(
				/* translators: %s: Hotmart event name. */
				__( 'Event %s is not implemented in this version.', 'hotmart-memberpress-pro' ),
				$event
			);
			$this->events->update_status( $event_id, 'ignored', $message );
			return array( 'status' => 'ignored', 'message' => $message );
		}

		$result = $this->memberpress->grant_access( $payload );
		if ( is_wp_error( $result ) ) {
			$this->events->update_status( $event_id, 'failed', $result->get_error_message() );
			return $result;
		}

		$this->events->update_status( $event_id, 'processed', __( 'MemberPress access granted.', 'hotmart-memberpress-pro' ) );
		return $result;
	}
}
