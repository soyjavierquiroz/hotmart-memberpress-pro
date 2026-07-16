<?php
namespace HMP\Repositories;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Activation_Repository {
	private string $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'hmp_activations';
	}

	public function insert( array $data ) {
		global $wpdb;
		$now  = current_time( 'mysql', true );
		$data = wp_parse_args( $data, array( 'created_at' => $now, 'updated_at' => $now ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->insert( $this->table, $data );
		if ( false === $result ) {
			return new \WP_Error( 'hmp_activation_insert_failed', __( 'Could not store the activation.', 'hotmart-memberpress-pro' ) );
		}
		return (int) $wpdb->insert_id;
	}

	public function find_by_id( int $id ): ?object {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id ) );
	}

	public function find_by_transaction( string $transaction, ?int $membership_id = null ): ?object {
		global $wpdb;
		if ( null !== $membership_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE hotmart_transaction = %s AND membership_id = %d", $transaction, $membership_id ) );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE hotmart_transaction = %s ORDER BY id DESC LIMIT 1", $transaction ) );
	}

	public function find_candidates( string $transaction = '', string $subscription = '' ): array {
		global $wpdb;
		$where  = array();
		$values = array();
		if ( '' !== $transaction ) {
			$where[]  = 'hotmart_transaction = %s';
			$values[] = $transaction;
		}
		if ( '' !== $subscription ) {
			$where[]  = 'hotmart_subscription = %s';
			$values[] = $subscription;
		}
		if ( empty( $where ) ) {
			return array();
		}
		$sql = "SELECT * FROM {$this->table} WHERE (" . implode( ' OR ', $where ) . ') ORDER BY id DESC';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
	}

	public function find_latest_by_subscription( string $subscription ): ?object {
		global $wpdb;
		if ( '' === $subscription ) {
			return null;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE hotmart_subscription = %s ORDER BY id DESC LIMIT 1", $subscription ) );
	}

	public function exists( string $transaction, int $membership_id ): bool {
		return null !== $this->find_by_transaction( $transaction, $membership_id );
	}

	public function update_status( int $id, string $status, array $extra = array() ) {
		global $wpdb;
		$data = array_merge( $extra, array( 'status' => $status, 'updated_at' => current_time( 'mysql', true ) ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->update( $this->table, $data, array( 'id' => $id ) );
		return false === $result
			? new \WP_Error( 'hmp_activation_update_failed', __( 'Could not update the activation.', 'hotmart-memberpress-pro' ) )
			: true;
	}

	public function query( array $args = array() ): array {
		global $wpdb;
		$args   = wp_parse_args( $args, array( 'search' => '', 'status' => '', 'limit' => 50, 'offset' => 0 ) );
		$where  = array( '1=1' );
		$values = array();
		if ( $args['status'] ) {
			$where[]  = 'status = %s';
			$values[] = $args['status'];
		}
		if ( $args['search'] ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(hotmart_transaction LIKE %s OR hotmart_subscription LIKE %s)';
			$values[] = $like;
			$values[] = $like;
		}
		$values[] = max( 1, min( 200, (int) $args['limit'] ) );
		$values[] = max( 0, (int) $args['offset'] );
		$sql      = "SELECT * FROM {$this->table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
	}

	public function count( array $args = array() ): int {
		global $wpdb;
		$args   = wp_parse_args( $args, array( 'search' => '', 'status' => '' ) );
		$where  = array( '1=1' );
		$values = array();
		if ( $args['status'] ) {
			$where[]  = 'status = %s';
			$values[] = $args['status'];
		}
		if ( $args['search'] ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(hotmart_transaction LIKE %s OR hotmart_subscription LIKE %s)';
			$values[] = $like;
			$values[] = $like;
		}
		$sql = "SELECT COUNT(*) FROM {$this->table} WHERE " . implode( ' AND ', $where );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) ( $values ? $wpdb->get_var( $wpdb->prepare( $sql, $values ) ) : $wpdb->get_var( $sql ) );
	}
}
