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
}
