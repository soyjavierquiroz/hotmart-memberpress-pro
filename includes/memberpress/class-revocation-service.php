<?php
namespace HMP\MemberPress;

use HMP\Repositories\Activation_Repository;
use HMP\Repositories\Mapping_Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Revocation_Service {
	private Activation_Repository $activations;
	private Mapping_Repository $mappings;

	public function __construct( Activation_Repository $activations, Mapping_Repository $mappings ) {
		$this->activations = $activations;
		$this->mappings    = $mappings;
	}

	public function handle_event( string $event, array $payload ) {
		$activation = $this->locate_activation( $payload );
		if ( is_wp_error( $activation ) ) {
			return $activation;
		}

		if ( 'SUBSCRIPTION_CANCELLATION' === $event ) {
			return $this->cancel_at_period_end( $activation, $event );
		}

		$mapping = $this->mappings->find_for_payload( $payload );
		if ( 'PURCHASE_REFUNDED' === $event && $mapping && 'period_end' === $mapping->refund_policy ) {
			return $this->cancel_at_period_end( $activation, $event );
		}
		if ( in_array( $event, array( 'PURCHASE_CANCELED', 'PURCHASE_CANCELLED' ), true ) && $mapping && 'period_end' === $mapping->cancellation_policy ) {
			return $this->cancel_at_period_end( $activation, $event );
		}

		$refund = in_array( $event, array( 'PURCHASE_REFUNDED', 'PURCHASE_CHARGEBACK' ), true );
		return $this->revoke( $activation, $event, $refund );
	}

	public function start_grace( array $payload, string $event ) {
		$subscription = sanitize_text_field( (string) ( $payload['subscription'] ?? '' ) );
		$activation = $subscription ? $this->activations->find_latest_by_subscription( $subscription ) : $this->locate_activation( $payload );
		if ( ! $activation || is_wp_error( $activation ) ) {
			return array( 'status' => 'processed', 'activation_missing' => true );
		}
		if ( ! in_array( $activation->status, array( 'active', 'grace' ), true ) ) {
			return array( 'status' => $activation->status, 'unchanged' => true );
		}
		$mapping = $this->mappings->find_for_payload( $payload );
		$days = $mapping ? (int) $mapping->grace_period_days : (int) \HMP\Settings::get( 'hmp_default_grace_period_days' );
		$now = time();
		$from_expiry = $activation->expires_at ? strtotime( $activation->expires_at ) + DAY_IN_SECONDS * $days : 0;
		$grace_until = gmdate( 'Y-m-d H:i:s', max( $from_expiry, $now + DAY_IN_SECONDS * $days ) );
		$result = $this->activations->update_status( (int) $activation->id, 'grace', array( 'grace_until' => $grace_until, 'last_event' => $event, 'last_event_at' => current_time( 'mysql', true ) ) );
		return is_wp_error( $result ) ? $result : array( 'status' => 'grace', 'activation_id' => (int) $activation->id );
	}

	public function refund_requested( array $payload, string $event ) {
		$activation = $this->locate_activation( $payload );
		if ( is_wp_error( $activation ) ) {
			return $activation;
		}
		$result = $this->activations->update_status( (int) $activation->id, (string) $activation->status, array( 'last_event' => $event, 'last_event_at' => current_time( 'mysql', true ) ) );
		return is_wp_error( $result ) ? $result : array( 'status' => $activation->status, 'activation_id' => (int) $activation->id );
	}

	public function expire_grace( int $activation_id ) {
		$activation = $this->activations->find_by_id( $activation_id );
		if ( ! $activation || 'grace' !== $activation->status ) {
			return array( 'status' => $activation ? $activation->status : 'missing', 'unchanged' => true );
		}
		return $this->revoke( $activation, 'grace_period_expired', false );
	}

	public function revoke_by_id( int $activation_id, string $reason = 'manual' ) {
		$activation = $this->activations->find_by_id( $activation_id );
		if ( ! $activation ) {
			return new \WP_Error( 'hmp_activation_not_found', __( 'The activation could not be found.', 'hotmart-memberpress-pro' ) );
		}
		return $this->revoke( $activation, $reason, false );
	}

	public function reactivate_by_id( int $activation_id ) {
		$activation = $this->activations->find_by_id( $activation_id );
		if ( ! $activation ) {
			return new \WP_Error( 'hmp_activation_not_found', __( 'The activation could not be found.', 'hotmart-memberpress-pro' ) );
		}
		if ( 'active' === $activation->status ) {
			return array( 'status' => 'active', 'activation_id' => (int) $activation->id, 'unchanged' => true );
		}

		$transaction = $this->memberpress_transaction( $activation );
		if ( is_wp_error( $transaction ) ) {
			return $transaction;
		}
		if ( $transaction ) {
			$transaction->status     = \MeprTransaction::$complete_str;
			$transaction->expires_at = $activation->expires_at ?: null;
			$transaction->store( true );
		}

		$result = $this->activations->update_status(
			(int) $activation->id,
			'active',
			array(
				'revoked_at'        => null,
				'revocation_reason' => null,
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return array( 'status' => 'active', 'activation_id' => (int) $activation->id, 'unchanged' => false );
	}

	private function locate_activation( array $payload ) {
		$transaction  = sanitize_text_field( (string) ( $payload['transaction'] ?? '' ) );
		$subscription = sanitize_text_field( (string) ( $payload['subscription'] ?? '' ) );
		$candidates   = $this->activations->find_candidates( $transaction, '' !== $transaction ? '' : $subscription );
		if ( empty( $candidates ) ) {
			return new \WP_Error( 'hmp_activation_not_found', __( 'No activation matches the affected Hotmart purchase.', 'hotmart-memberpress-pro' ) );
		}

		$matched = array_values(
			array_filter(
				$candidates,
				static function ( $activation ) use ( $payload, $transaction ) {
					if ( '' !== $transaction && $activation->hotmart_transaction !== $transaction ) {
						return false;
					}
					foreach ( array( 'product_id', 'offer_code', 'plan_id' ) as $field ) {
						$incoming = (string) ( $payload[ $field ] ?? '' );
						$stored   = (string) ( $activation->{$field} ?? '' );
						if ( '' !== $incoming && '' !== $stored && $incoming !== $stored ) {
							return false;
						}
					}
					return true;
				}
			)
		);

		if ( '' === $transaction && '' !== $subscription && ! empty( $matched ) ) {
			usort( $matched, static function ( $a, $b ) { return strcmp( (string) ( $b->expires_at ?: $b->starts_at ?: $b->id ), (string) ( $a->expires_at ?: $a->starts_at ?: $a->id ) ); } );
			return $matched[0];
		}
		if ( 1 !== count( $matched ) ) {
			return new \WP_Error( 'hmp_activation_ambiguous', __( 'The affected activation could not be identified uniquely.', 'hotmart-memberpress-pro' ) );
		}
		return $matched[0];
	}

	private function revoke( object $activation, string $reason, bool $refund ) {
		if ( 'revoked' === $activation->status ) {
			return array( 'status' => 'revoked', 'activation_id' => (int) $activation->id, 'unchanged' => true );
		}
		if ( ! in_array( $activation->status, array( 'active', 'grace', 'canceled' ), true ) ) {
			return new \WP_Error( 'hmp_activation_not_active', __( 'The affected activation is not active.', 'hotmart-memberpress-pro' ) );
		}

		$transaction = $this->memberpress_transaction( $activation );
		if ( is_wp_error( $transaction ) ) {
			return $transaction;
		}
		if ( $transaction ) {
			if ( $refund ) {
				$transaction->status = \MeprTransaction::$refunded_str;
			}
			$transaction->expires_at = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );
			$transaction->store( true );
		}

		$result = $this->activations->update_status(
			(int) $activation->id,
			'revoked',
			array(
				'revoked_at'        => current_time( 'mysql', true ),
				'revocation_reason' => sanitize_text_field( $reason ),
				'grace_until'       => null,
				'last_event'        => sanitize_text_field( $reason ),
				'last_event_at'     => current_time( 'mysql', true ),
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return array( 'status' => 'revoked', 'activation_id' => (int) $activation->id, 'unchanged' => false );
	}

	private function cancel_at_period_end( object $activation, string $reason ) {
		if ( 'canceled' === $activation->status ) {
			return array( 'status' => 'canceled', 'activation_id' => (int) $activation->id, 'unchanged' => true );
		}
		if ( 'active' !== $activation->status ) {
			return new \WP_Error( 'hmp_activation_not_active', __( 'The affected activation is not active.', 'hotmart-memberpress-pro' ) );
		}

		$result = $this->activations->update_status(
			(int) $activation->id,
			'canceled',
			array( 'revocation_reason' => sanitize_text_field( $reason ), 'last_event' => sanitize_text_field( $reason ), 'last_event_at' => current_time( 'mysql', true ) )
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return array( 'status' => 'canceled', 'activation_id' => (int) $activation->id, 'unchanged' => false );
	}

	private function memberpress_transaction( object $activation ) {
		if ( empty( $activation->memberpress_transaction_id ) ) {
			return null;
		}
		if ( ! class_exists( 'MeprTransaction' ) ) {
			return new \WP_Error( 'hmp_memberpress_unavailable', __( 'MemberPress is not active or its API is unavailable.', 'hotmart-memberpress-pro' ) );
		}
		try {
			$transaction = new \MeprTransaction( (int) $activation->memberpress_transaction_id );
			return empty( $transaction->id ) ? new \WP_Error( 'hmp_memberpress_transaction_not_found', __( 'The related MemberPress transaction could not be found.', 'hotmart-memberpress-pro' ) ) : $transaction;
		} catch ( \Throwable $exception ) {
			return new \WP_Error( 'hmp_memberpress_transaction_failed', __( 'The related MemberPress transaction could not be loaded.', 'hotmart-memberpress-pro' ) );
		}
	}
}
