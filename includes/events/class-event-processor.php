<?php
namespace HMP\Events;

use HMP\MemberPress\MemberPress_Service;
use HMP\MemberPress\Revocation_Service;
use HMP\Repositories\Event_Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Event_Processor {
	private Event_Repository $events;
	private MemberPress_Service $memberpress;
	private Revocation_Service $revocations;

	public function __construct( Event_Repository $events, MemberPress_Service $memberpress, Revocation_Service $revocations ) {
		$this->events      = $events;
		$this->memberpress = $memberpress;
		$this->revocations = $revocations;
	}

	public function process( int $event_id, array $payload ) {
		$this->events->increment_attempts( $event_id );
		$event = (string) ( $payload['event'] ?? '' );

		$grant_events = array( 'PURCHASE_APPROVED', 'PURCHASE_COMPLETE' );
		$revoke_events = array(
			'PURCHASE_REFUNDED',
			'PURCHASE_CHARGEBACK',
			'PURCHASE_CANCELED',
			'PURCHASE_CANCELLED',
			'PURCHASE_EXPIRED',
			'SUBSCRIPTION_CANCELLATION',
			'SUBSCRIPTION_CANCELED',
			'SUBSCRIPTION_CANCELLED',
		);
		if ( 'PURCHASE_DELAYED' === $event ) {
			$result = $this->revocations->payment_delayed( $payload, $event );
			return $this->finish( $event_id, $result, __( 'Delayed payment recorded without revoking access.', 'hotmart-memberpress-pro' ) );
		}
		if ( 'PURCHASE_OVERDUE' === $event ) {
			$result = $this->revocations->start_grace( $payload, $event );
			return $this->finish( $event_id, $result, __( 'Subscription placed in grace period.', 'hotmart-memberpress-pro' ) );
		}
		if ( 'PURCHASE_REFUND_REQUESTED' === $event ) {
			$result = $this->revocations->refund_requested( $payload, $event );
			return $this->finish( $event_id, $result, __( 'Refund request recorded without revoking access.', 'hotmart-memberpress-pro' ) );
		}

		if ( ! in_array( $event, array_merge( $grant_events, $revoke_events ), true ) ) {
			$message = sprintf(
				/* translators: %s: Hotmart event name. */
				__( 'Event %s is not implemented in this version.', 'hotmart-memberpress-pro' ),
				$event
			);
			$this->events->update_status( $event_id, 'ignored', $message );
			return array( 'status' => 'ignored', 'message' => $message );
		}

		$result = in_array( $event, $grant_events, true )
			? $this->memberpress->grant_access( $payload )
			: $this->revocations->handle_event( $event, $payload );
		if ( is_wp_error( $result ) ) {
			$this->events->update_status( $event_id, 'failed', $result->get_error_message(), $result->get_error_code() );
			return $result;
		}

		$message = in_array( $event, $grant_events, true )
			? __( 'MemberPress access granted.', 'hotmart-memberpress-pro' )
			: __( 'Membership lifecycle updated.', 'hotmart-memberpress-pro' );
		$this->events->update_status( $event_id, 'processed', $message );
		return $result;
	}

	private function finish( int $event_id, $result, string $message ) {
		if ( is_wp_error( $result ) ) {
			$this->events->update_status( $event_id, 'failed', $result->get_error_message(), $result->get_error_code() );
			return $result;
		}
		$this->events->update_status( $event_id, 'processed', $message );
		return $result;
	}
}
