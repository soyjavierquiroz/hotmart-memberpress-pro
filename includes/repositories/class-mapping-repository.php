<?php
namespace HMP\Repositories;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mapping_Repository {
	private string $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'hmp_mappings';
	}

	public function insert( array $data ) {
		global $wpdb;
		$now  = current_time( 'mysql', true );
		$data = wp_parse_args( $data, array( 'created_at' => $now, 'updated_at' => $now ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->insert( $this->table, $data );
		if ( false === $result ) {
			return new \WP_Error( 'hmp_mapping_insert_failed', __( 'Could not store the mapping.', 'hotmart-memberpress-pro' ) );
		}
		return (int) $wpdb->insert_id;
	}

	public function find_by_id( int $id ): ?object {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id ) );
	}

	public function find_for_payload( array $payload ): ?object {
		global $wpdb;
		$plan    = (string) ( $payload['plan_id'] ?? '' );
		$offer   = (string) ( $payload['offer_code'] ?? '' );
		$product = (string) ( $payload['product_id'] ?? '' );

		$queries = array();
		if ( '' !== $plan && '' !== $offer && '' !== $product ) {
			$queries[] = $wpdb->prepare( '(plan_id = %s AND offer_code = %s AND product_id = %s)', $plan, $offer, $product );
		}
		if ( '' !== $plan ) {
			$queries[] = $wpdb->prepare( '(plan_id = %s AND (offer_code IS NULL OR offer_code = \'\') AND (product_id IS NULL OR product_id = \'\'))', $plan );
		}
		if ( '' !== $offer ) {
			$queries[] = $wpdb->prepare( '(offer_code = %s AND (plan_id IS NULL OR plan_id = \'\') AND (product_id IS NULL OR product_id = \'\'))', $offer );
		}
		if ( '' !== $product ) {
			$queries[] = $wpdb->prepare( '(product_id = %s AND (plan_id IS NULL OR plan_id = \'\') AND (offer_code IS NULL OR offer_code = \'\'))', $product );
		}
		if ( empty( $queries ) ) {
			return null;
		}

		$order = implode( ' ', array_map( static fn( $i ) => "WHEN {$queries[$i]} THEN " . ( $i + 1 ), array_keys( $queries ) ) );
		$where = implode( ' OR ', $queries );
		$sql   = "SELECT * FROM {$this->table} WHERE active = 1 AND ({$where}) ORDER BY CASE {$order} ELSE 99 END ASC, priority ASC, id ASC LIMIT 1";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row( $sql );
	}

	public function find_by_transaction( string $transaction ): ?object {
		return null;
	}

	public function exists( int $id ): bool {
		return null !== $this->find_by_id( $id );
	}

	public function update_status( int $id, string $status ) {
		global $wpdb;
		$active = 'active' === $status ? 1 : 0;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->update( $this->table, array( 'active' => $active, 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => $id ) );
		return false === $result
			? new \WP_Error( 'hmp_mapping_update_failed', __( 'Could not update the mapping.', 'hotmart-memberpress-pro' ) )
			: true;
	}
}
