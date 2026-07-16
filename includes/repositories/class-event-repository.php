<?php
namespace HMP\Repositories;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Event_Repository {
	private string $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'hmp_events';
	}

	public function insert( array $data ) {
		global $wpdb;
		$now      = current_time( 'mysql', true );
		$defaults = array(
			'transaction_code' => null,
			'subscription_code' => null,
			'buyer_email'      => null,
			'status'           => 'received',
			'attempts'         => 0,
			'result_message'   => null,
			'source'           => 'webhook',
			'received_at'      => $now,
			'processed_at'     => null,
			'created_at'       => $now,
			'updated_at'       => $now,
		);
		$data     = wp_parse_args( $data, $defaults );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->insert( $this->table, $data );
		if ( false === $result ) {
			return new \WP_Error( 'hmp_event_insert_failed', __( 'Could not store the webhook event.', 'hotmart-memberpress-pro' ) );
		}
		return (int) $wpdb->insert_id;
	}

	public function find_by_id( int $id ): ?object {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id ) );
	}

	public function find_by_event_key( string $event_key ): ?object {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE event_key = %s", $event_key ) );
	}

	public function exists( string $event_key ): bool {
		return null !== $this->find_by_event_key( $event_key );
	}

	public function update_status( int $id, string $status, string $message = '' ) {
		global $wpdb;
		$data = array(
			'status'         => $status,
			'result_message' => $message,
			'updated_at'     => current_time( 'mysql', true ),
		);
		if ( in_array( $status, array( 'processed', 'failed', 'ignored' ), true ) ) {
			$data['processed_at'] = current_time( 'mysql', true );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->update( $this->table, $data, array( 'id' => $id ) );
		return false === $result
			? new \WP_Error( 'hmp_event_update_failed', __( 'Could not update the webhook event.', 'hotmart-memberpress-pro' ) )
			: true;
	}

	public function increment_attempts( int $id ): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( "UPDATE {$this->table} SET attempts = attempts + 1, updated_at = %s WHERE id = %d", current_time( 'mysql', true ), $id ) );
	}

	public function counts_by_status(): array {
		global $wpdb;
		$counts = array_fill_keys( array( 'received', 'processed', 'failed', 'ignored' ), 0 );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( "SELECT status, COUNT(*) AS total FROM {$this->table} GROUP BY status" );
		foreach ( $rows as $row ) {
			$counts[ $row->status ] = (int) $row->total;
		}
		return $counts;
	}

	public function latest( int $limit = 10 ): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->table} ORDER BY received_at DESC, id DESC LIMIT %d", $limit ) );
	}
}
