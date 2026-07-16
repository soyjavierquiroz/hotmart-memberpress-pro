<?php
namespace HMP\Webhook;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Payload_Normalizer {
	public function normalize( array $payload ): array {
		return array(
			'event_id'         => $this->first( $payload, array( 'id', 'event_id', 'event.id' ) ),
			'event'            => strtoupper( (string) $this->first( $payload, array( 'event', 'event.name', 'type' ) ) ),
			'event_date'       => $this->first( $payload, array( 'creation_date', 'event_date', 'date', 'event.date' ) ),
			'transaction'      => $this->first( $payload, array( 'data.purchase.transaction', 'purchase.transaction', 'transaction' ) ),
			'subscription'     => $this->first( $payload, array( 'data.subscription.subscriber.code', 'data.subscription.code', 'subscription.code', 'subscription' ) ),
			'buyer_email'      => sanitize_email( (string) $this->first( $payload, array( 'data.buyer.email', 'buyer.email', 'email' ) ) ),
			'buyer_first_name' => sanitize_text_field( (string) $this->first( $payload, array( 'data.buyer.first_name', 'buyer.first_name', 'buyer.name' ) ) ),
			'buyer_last_name'  => sanitize_text_field( (string) $this->first( $payload, array( 'data.buyer.last_name', 'buyer.last_name' ) ) ),
			'product_id'       => $this->first( $payload, array( 'data.product.id', 'product.id', 'product_id' ) ),
			'product_name'     => $this->first( $payload, array( 'data.product.name', 'product.name', 'product_name' ) ),
			'offer_code'       => $this->first( $payload, array( 'data.purchase.offer.code', 'data.offer.code', 'purchase.offer.code', 'offer.code', 'offer_code' ) ),
			'plan_id'          => $this->first( $payload, array( 'data.subscription.plan.id', 'data.plan.id', 'subscription.plan.id', 'plan.id', 'plan_id' ) ),
			'purchase_status'  => $this->first( $payload, array( 'data.purchase.status', 'purchase.status', 'status' ) ),
			'order_date'       => $this->first( $payload, array( 'data.purchase.order_date', 'purchase.order_date', 'order_date' ) ),
			'approved_date'    => $this->first( $payload, array( 'data.purchase.approved_date', 'purchase.approved_date', 'approved_date' ) ),
			'next_charge_date' => $this->first( $payload, array( 'data.subscription.date_next_charge', 'data.subscription.next_charge_date', 'subscription.next_charge_date', 'next_charge_date' ) ),
			'price'            => $this->first( $payload, array( 'data.purchase.price.value', 'data.purchase.full_price.value', 'purchase.price.value', 'price.value', 'price' ) ),
			'currency'         => $this->first( $payload, array( 'data.purchase.price.currency_value', 'data.purchase.full_price.currency_value', 'purchase.price.currency_value', 'currency' ) ),
		);
	}

	private function first( array $payload, array $paths ) {
		foreach ( $paths as $path ) {
			$value = $payload;
			foreach ( explode( '.', $path ) as $segment ) {
				if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
					$value = null;
					break;
				}
				$value = $value[ $segment ];
			}
			if ( null !== $value && '' !== $value ) {
				return is_scalar( $value ) ? $value : null;
			}
		}
		return null;
	}
}
