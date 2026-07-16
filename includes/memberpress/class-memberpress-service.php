<?php
namespace HMP\MemberPress;

use HMP\Repositories\Activation_Repository;
use HMP\Repositories\Mapping_Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MemberPress_Service {
	private Mapping_Repository $mappings;
	private Activation_Repository $activations;

	public function __construct( Mapping_Repository $mappings, Activation_Repository $activations ) {
		$this->mappings    = $mappings;
		$this->activations = $activations;
	}

	public function grant_access( array $payload ) {
		if ( ! class_exists( 'MeprTransaction' ) || ! class_exists( 'MeprProduct' ) ) {
			return new \WP_Error( 'hmp_memberpress_unavailable', __( 'MemberPress is not active or its API is unavailable.', 'hotmart-memberpress-pro' ) );
		}

		$mapping = $this->mappings->find_for_payload( $payload );
		if ( ! $mapping ) {
			return new \WP_Error( 'hmp_mapping_not_found', __( 'No active mapping matches this Hotmart purchase.', 'hotmart-memberpress-pro' ) );
		}

		$email = sanitize_email( (string) ( $payload['buyer_email'] ?? '' ) );
		if ( ! is_email( $email ) ) {
			return new \WP_Error( 'hmp_invalid_buyer_email', __( 'The purchase does not contain a valid buyer email.', 'hotmart-memberpress-pro' ) );
		}

		$transaction = sanitize_text_field( (string) ( $payload['transaction'] ?? '' ) );
		if ( '' === $transaction ) {
			return new \WP_Error( 'hmp_missing_transaction', __( 'The purchase does not contain a transaction code.', 'hotmart-memberpress-pro' ) );
		}

		$subscription = sanitize_text_field( (string) ( $payload['subscription'] ?? '' ) );
		if ( '' !== $subscription ) {
			$latest = $this->activations->find_latest_by_subscription( $subscription );
			if ( $latest && 'canceled' === $latest->status && $latest->hotmart_transaction !== $transaction ) {
				return new \WP_Error( 'hmp_subscription_canceled', __( 'This Hotmart subscription was canceled and cannot create future renewals.', 'hotmart-memberpress-pro' ) );
			}
		}

		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			$user = $this->create_user( $email, $payload );
			if ( is_wp_error( $user ) ) {
				return $user;
			}
		}

		$trans_num = $this->transaction_number( $transaction );
		$existing  = \MeprTransaction::get_one_by_trans_num( $trans_num );
		if ( $existing && ! empty( $existing->id ) ) {
			$this->store_activation( $user->ID, $mapping, $payload, (int) $existing->id, $existing->created_at ?? null, $existing->expires_at ?? null );
			return array(
				'status'                     => 'processed',
				'duplicate_transaction'      => true,
				'memberpress_transaction_id' => (int) $existing->id,
				'user_id'                    => (int) $user->ID,
				'membership_id'              => (int) $mapping->membership_id,
			);
		}

		$created_at = $this->mysql_date( $payload['approved_date'] ?? $payload['order_date'] ?? null ) ?: current_time( 'mysql', true );
		$expires_at = $this->expires_at( $mapping, $created_at );
		$amount     = is_numeric( $payload['price'] ?? null ) ? (float) $payload['price'] : 0.0;

		try {
			$txn             = new \MeprTransaction();
			$txn->user_id    = (int) $user->ID;
			$txn->product_id = (int) $mapping->membership_id;
			$txn->gateway    = \MeprTransaction::$manual_gateway_str;
			$txn->status     = \MeprTransaction::$complete_str;
			$txn->txn_type   = \MeprTransaction::$payment_str;
			$txn->trans_num  = $trans_num;
			$txn->amount     = $amount;
			$txn->total      = $amount;
			$txn->created_at = $created_at;
			$txn->expires_at = $expires_at;
			$txn_id          = (int) $txn->store( true );
		} catch ( \Throwable $exception ) {
			return new \WP_Error( 'hmp_memberpress_transaction_failed', __( 'MemberPress could not create the transaction.', 'hotmart-memberpress-pro' ) );
		}

		if ( $txn_id <= 0 ) {
			return new \WP_Error( 'hmp_memberpress_transaction_failed', __( 'MemberPress could not create the transaction.', 'hotmart-memberpress-pro' ) );
		}

		$activation = $this->store_activation( $user->ID, $mapping, $payload, $txn_id, $created_at, $expires_at );
		if ( is_wp_error( $activation ) ) {
			return $activation;
		}

		return array(
			'status'                     => 'processed',
			'duplicate_transaction'      => false,
			'memberpress_transaction_id' => $txn_id,
			'user_id'                    => (int) $user->ID,
			'membership_id'              => (int) $mapping->membership_id,
		);
	}

	private function create_user( string $email, array $payload ) {
		$base     = sanitize_user( (string) strtok( $email, '@' ), true );
		$base     = '' === $base ? 'hotmart_user' : $base;
		$username = $base;
		$suffix   = 1;
		while ( username_exists( $username ) ) {
			$username = $base . '_' . $suffix;
			++$suffix;
		}

		$user_id = wp_insert_user(
			array(
				'user_login' => $username,
				'user_email' => $email,
				'user_pass'  => wp_generate_password( 32, true, true ),
				'first_name' => sanitize_text_field( (string) ( $payload['buyer_first_name'] ?? '' ) ),
				'last_name'  => sanitize_text_field( (string) ( $payload['buyer_last_name'] ?? '' ) ),
				'role'       => get_option( 'default_role', 'subscriber' ),
			)
		);
		return is_wp_error( $user_id ) ? $user_id : get_user_by( 'id', $user_id );
	}

	private function transaction_number( string $transaction ): string {
		return 'hmp_' . substr( hash( 'sha256', $transaction ), 0, 40 );
	}

	private function expires_at( object $mapping, string $created_at ): ?string {
		$value = (int) ( $mapping->access_duration_value ?? 0 );
		$unit  = (string) ( $mapping->access_duration_unit ?? '' );
		if ( $value > 0 && in_array( $unit, array( 'days', 'weeks', 'months', 'years' ), true ) ) {
			$date = new \DateTimeImmutable( $created_at, new \DateTimeZone( 'UTC' ) );
			$date = $date->modify( sprintf( '+%d %s', $value, $unit ) );
			return $date ? $date->format( 'Y-m-d H:i:s' ) : null;
		}

		try {
			$product = new \MeprProduct( (int) $mapping->membership_id );
			$expires = $product->get_expires_at( strtotime( $created_at ), false );
			if ( empty( $expires ) || '0000-00-00 00:00:00' === $expires ) {
				return null;
			}
			return is_numeric( $expires ) ? gmdate( 'Y-m-d H:i:s', (int) $expires ) : (string) $expires;
		} catch ( \Throwable $exception ) {
			return null;
		}
	}

	private function mysql_date( $value ): ?string {
		if ( empty( $value ) ) {
			return null;
		}
		if ( is_numeric( $value ) ) {
			$timestamp = (int) $value;
			if ( $timestamp > 9999999999 ) {
				$timestamp = (int) floor( $timestamp / 1000 );
			}
		} else {
			$timestamp = strtotime( (string) $value );
		}
		return $timestamp ? gmdate( 'Y-m-d H:i:s', $timestamp ) : null;
	}

	private function store_activation( int $user_id, object $mapping, array $payload, int $txn_id, ?string $starts_at, ?string $expires_at ) {
		$transaction = (string) $payload['transaction'];
		$existing    = $this->activations->find_by_transaction( $transaction, (int) $mapping->membership_id );
		$data        = array(
			'memberpress_transaction_id' => $txn_id,
			'status'                     => 'active',
			'starts_at'                  => $starts_at,
			'expires_at'                 => $expires_at,
		);
		if ( $existing ) {
			return $this->activations->update_status( (int) $existing->id, 'active', $data );
		}

		return $this->activations->insert(
			array_merge(
				$data,
				array(
					'user_id'                     => $user_id,
					'membership_id'               => (int) $mapping->membership_id,
					'memberpress_subscription_id' => null,
					'hotmart_transaction'          => $transaction,
					'hotmart_subscription'         => $payload['subscription'] ?? null,
					'product_id'                   => $payload['product_id'] ?? null,
					'offer_code'                   => $payload['offer_code'] ?? null,
					'plan_id'                      => $payload['plan_id'] ?? null,
				)
			)
		);
	}
}
