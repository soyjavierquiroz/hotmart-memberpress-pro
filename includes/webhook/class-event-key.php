<?php
namespace HMP\Webhook;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Event_Key {
	public function generate( array $normalized ): string {
		if ( ! empty( $normalized['event_id'] ) ) {
			return hash( 'sha256', 'hotmart-event:' . (string) $normalized['event_id'] );
		}
		$parts = array(
			(string) ( $normalized['event'] ?? '' ),
			(string) ( $normalized['transaction'] ?? '' ),
			(string) ( $normalized['subscription'] ?? '' ),
			(string) ( $normalized['event_date'] ?? $normalized['approved_date'] ?? $normalized['order_date'] ?? '' ),
		);
		return hash( 'sha256', implode( '|', $parts ) );
	}
}
