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
			'error_code'       => null,
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

	public function update_status( int $id, string $status, string $message = '', ?string $error_code = null ) {
		global $wpdb;
		$data = array(
			'status'         => $status,
			'result_message' => $message,
			'error_code'     => $error_code,
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

	public function update( int $id, array $data ) {
		global $wpdb;
		$data['updated_at'] = current_time( 'mysql', true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->update( $this->table, $data, array( 'id' => $id ) );
		return false === $result
			? new \WP_Error( 'hmp_event_update_failed', __( 'Could not update the webhook event.', 'hotmart-memberpress-pro' ) )
			: true;
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

	public function query( array $args = array() ): array {
		global $wpdb;
		$args   = wp_parse_args(
			$args,
			array(
				'status' => '',
				'event'  => '',
				'search' => '',
				'limit'  => 50,
				'offset' => 0,
			)
		);
		$where  = array( '1=1' );
		$values = array();
		if ( $args['status'] ) {
			$where[]  = 'status = %s';
			$values[] = $args['status'];
		}
		if ( $args['event'] ) {
			$where[]  = 'hotmart_event = %s';
			$values[] = $args['event'];
		}
		if ( $args['search'] ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(buyer_email LIKE %s OR transaction_code LIKE %s)';
			$values[] = $like;
			$values[] = $like;
		}
		$values[] = max( 1, min( 200, (int) $args['limit'] ) );
		$values[] = max( 0, (int) $args['offset'] );
		$sql      = "SELECT * FROM {$this->table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY received_at DESC, id DESC LIMIT %d OFFSET %d';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
	}

	public function count( array $args = array() ): int {
		global $wpdb;
		$args   = wp_parse_args( $args, array( 'status' => '', 'event' => '', 'search' => '' ) );
		$where  = array( '1=1' );
		$values = array();
		if ( $args['status'] ) {
			$where[]  = 'status = %s';
			$values[] = $args['status'];
		}
		if ( $args['event'] ) {
			$where[]  = 'hotmart_event = %s';
			$values[] = $args['event'];
		}
		if ( $args['search'] ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(buyer_email LIKE %s OR transaction_code LIKE %s)';
			$values[] = $like;
			$values[] = $like;
		}
		$sql = "SELECT COUNT(*) FROM {$this->table} WHERE " . implode( ' AND ', $where );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) ( $values ? $wpdb->get_var( $wpdb->prepare( $sql, $values ) ) : $wpdb->get_var( $sql ) );
	}

	public function event_names(): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_col( "SELECT DISTINCT hotmart_event FROM {$this->table} WHERE hotmart_event <> '' ORDER BY hotmart_event ASC" );
	}

	public function retryable( int $limit = 20 ): array {
		global $wpdb;
		$codes = array( 'hmp_memberpress_unavailable', 'hmp_memberpress_transaction_failed', 'hmp_database_error' );
		$marks = implode( ',', array_fill( 0, count( $codes ), '%s' ) );
		$values = array_merge( $codes, array( min( 20, max( 1, $limit ) ) ) );
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE status='failed' AND attempts < 3 AND error_code IN ($marks) ORDER BY updated_at ASC LIMIT %d", $values ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}
