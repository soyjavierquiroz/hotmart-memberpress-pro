<?php
namespace HMP\Rest;

use HMP\Events\Event_Processor;
use HMP\Repositories\Event_Repository;
use HMP\Settings;
use HMP\Webhook\Authenticator;
use HMP\Webhook\Event_Key;
use HMP\Webhook\Payload_Normalizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Webhook_Controller {
	private Event_Repository $events;
	private Authenticator $authenticator;
	private Payload_Normalizer $normalizer;
	private Event_Key $event_key;
	private Event_Processor $processor;

	public function __construct( Event_Repository $events, Authenticator $authenticator, Payload_Normalizer $normalizer, Event_Key $event_key, Event_Processor $processor ) {
		$this->events        = $events;
		$this->authenticator = $authenticator;
		$this->normalizer    = $normalizer;
		$this->event_key     = $event_key;
		$this->processor     = $processor;
	}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			'hmp/v1',
			'/webhook',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function handle( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! Settings::get( 'hmp_webhook_enabled' ) ) {
			return $this->response( array( 'success' => false, 'code' => 'webhook_disabled', 'message' => __( 'Webhook processing is disabled.', 'hotmart-memberpress-pro' ) ), 503 );
		}

		$auth = $this->authenticator->authenticate( $request );
		if ( is_wp_error( $auth ) ) {
			return $this->response( array( 'success' => false, 'code' => $auth->get_error_code(), 'message' => $auth->get_error_message() ), 401 );
		}

		$body = $request->get_body();
		$data = json_decode( $body, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
			return $this->response( array( 'success' => false, 'code' => 'invalid_json', 'message' => __( 'The request body must contain valid JSON.', 'hotmart-memberpress-pro' ) ), 400 );
		}

		$normalized = $this->normalizer->normalize( $data );
		if ( empty( $normalized['event'] ) ) {
			return $this->response( array( 'success' => false, 'code' => 'missing_event', 'message' => __( 'The Hotmart event name is required.', 'hotmart-memberpress-pro' ) ), 400 );
		}

		$key = $this->event_key->generate( $normalized );
		if ( $this->events->exists( $key ) ) {
			return $this->response( array( 'success' => true, 'accepted' => true, 'duplicate' => true, 'event_key' => $key ), 200 );
		}

		$encoded = wp_json_encode( $data );
		if ( false === $encoded ) {
			return $this->response( array( 'success' => false, 'code' => 'payload_encoding_failed', 'message' => __( 'The payload could not be encoded.', 'hotmart-memberpress-pro' ) ), 500 );
		}

		$event_id = $this->events->insert(
			array(
				'event_key'         => $key,
				'hotmart_event'     => $normalized['event'],
				'transaction_code'  => $normalized['transaction'],
				'subscription_code' => $normalized['subscription'],
				'buyer_email'       => $normalized['buyer_email'],
				'payload'           => $encoded,
			)
		);
		if ( is_wp_error( $event_id ) ) {
			if ( $this->events->exists( $key ) ) {
				return $this->response( array( 'success' => true, 'accepted' => true, 'duplicate' => true, 'event_key' => $key ), 200 );
			}
			return $this->response( array( 'success' => false, 'code' => 'event_storage_failed', 'message' => __( 'The event could not be stored.', 'hotmart-memberpress-pro' ) ), 500 );
		}

		try {
			$result = $this->processor->process( $event_id, $normalized );
			if ( is_wp_error( $result ) ) {
				return $this->response( array( 'success' => true, 'accepted' => true, 'duplicate' => false, 'event_id' => $event_id, 'status' => 'failed' ), 200 );
			}
			return $this->response( array( 'success' => true, 'accepted' => true, 'duplicate' => false, 'event_id' => $event_id, 'status' => $result['status'] ?? 'processed' ), 200 );
		} catch ( \Throwable $exception ) {
			$this->events->update_status( $event_id, 'failed', __( 'An internal processing error occurred.', 'hotmart-memberpress-pro' ) );
			return $this->response( array( 'success' => true, 'accepted' => true, 'duplicate' => false, 'event_id' => $event_id, 'status' => 'failed' ), 200 );
		}
	}

	private function response( array $data, int $status ): \WP_REST_Response {
		return new \WP_REST_Response( $data, $status );
	}
}
