<?php
namespace HMP;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Upgrader {
	private const LOCK = 'hmp_db_upgrade_lock';
	private const ERROR = 'hmp_db_upgrade_error';

	public static function maybe_upgrade(): void {
		if ( version_compare( (string) get_option( 'hmp_db_version', '0' ), HMP_DB_VERSION, '<' ) ) self::run();
	}

	public static function run( bool $force = false ): bool {
		if ( ! $force && get_transient( self::LOCK ) ) return false;
		set_transient( self::LOCK, 1, 5 * MINUTE_IN_SECONDS );
		try {
			global $wpdb;
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			foreach ( self::schema() as $statement ) {
				$wpdb->last_error = '';
				dbDelta( $statement );
				if ( '' !== $wpdb->last_error ) throw new \RuntimeException( __( 'The database migration could not be completed.', 'hotmart-memberpress-pro' ) );
			}
			$check = self::verify_schema();
			if ( ! empty( $check['missing'] ) ) throw new \RuntimeException( __( 'Required database columns are still missing after the migration.', 'hotmart-memberpress-pro' ) );
			update_option( 'hmp_db_version', HMP_DB_VERSION );
			delete_option( self::ERROR );
			set_transient( 'hmp_db_upgrade_success', 1, 5 * MINUTE_IN_SECONDS );
			return true;
		} catch ( \Throwable $exception ) {
			update_option( self::ERROR, sanitize_text_field( $exception->getMessage() ), false );
			return false;
		} finally {
			delete_transient( self::LOCK );
		}
	}

	public static function last_error(): string { return (string) get_option( self::ERROR, '' ); }

	public static function verify_schema(): array {
		global $wpdb;
		$required = array(
			$wpdb->prefix . 'hmp_events' => array( 'error_code', 'source' ),
			$wpdb->prefix . 'hmp_activations' => array( 'grace_until', 'last_event', 'last_event_at' ),
		);
		$missing = array();
		foreach ( $required as $table => $columns ) {
			$rows = $wpdb->get_results( $wpdb->prepare( 'SHOW COLUMNS FROM `' . esc_sql( $table ) . '` WHERE Field IN (' . implode( ',', array_fill( 0, count( $columns ), '%s' ) ) . ')', $columns ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
			$found = wp_list_pluck( $rows ?: array(), 'Field' );
			foreach ( array_diff( $columns, $found ) as $column ) $missing[] = $table . '.' . $column;
		}
		return array( 'expected' => HMP_DB_VERSION, 'installed' => (string) get_option( 'hmp_db_version', '0' ), 'current' => empty( $missing ) && ! version_compare( (string) get_option( 'hmp_db_version', '0' ), HMP_DB_VERSION, '<' ), 'missing' => $missing );
	}

	public static function schema(): array {
		global $wpdb; $charset = $wpdb->get_charset_collate();
		return array(
			"CREATE TABLE {$wpdb->prefix}hmp_events (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				event_key varchar(64) NOT NULL,
				hotmart_event varchar(100) NOT NULL,
				transaction_code varchar(191) NULL,
				subscription_code varchar(191) NULL,
				buyer_email varchar(191) NULL,
				payload longtext NOT NULL,
				status varchar(30) NOT NULL,
				attempts smallint(5) unsigned NOT NULL DEFAULT 0,
				result_message text NULL,
				error_code varchar(100) NULL,
				source varchar(30) NOT NULL DEFAULT 'webhook',
				received_at datetime NOT NULL,
				processed_at datetime NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY event_key (event_key),
				KEY hotmart_event (hotmart_event),
				KEY transaction_code (transaction_code),
				KEY buyer_email (buyer_email),
				KEY status (status),
				KEY received_at (received_at)
			) $charset;",
			"CREATE TABLE {$wpdb->prefix}hmp_activations (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL,
				membership_id bigint(20) unsigned NOT NULL,
				memberpress_transaction_id bigint(20) unsigned NULL,
				memberpress_subscription_id bigint(20) unsigned NULL,
				hotmart_transaction varchar(191) NOT NULL,
				hotmart_subscription varchar(191) NULL,
				product_id varchar(191) NULL,
				offer_code varchar(191) NULL,
				plan_id varchar(191) NULL,
				status varchar(30) NOT NULL,
				starts_at datetime NULL,
				expires_at datetime NULL,
				revoked_at datetime NULL,
				revocation_reason varchar(191) NULL,
				manual_action varchar(30) NULL,
				manual_action_at datetime NULL,
				manual_action_result text NULL,
				grace_until datetime NULL,
				last_event varchar(100) NULL,
				last_event_at datetime NULL,
				source varchar(30) NOT NULL DEFAULT 'webhook',
				manual_admin_id bigint(20) unsigned NULL,
				manual_reason text NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY transaction_membership (hotmart_transaction,membership_id),
				KEY user_id (user_id),
				KEY membership_id (membership_id),
				KEY memberpress_transaction_id (memberpress_transaction_id),
				KEY hotmart_subscription (hotmart_subscription),
				KEY status (status)
			) $charset;",
			"CREATE TABLE {$wpdb->prefix}hmp_mappings (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				name varchar(191) NOT NULL,
				product_id varchar(191) NULL,
				offer_code varchar(191) NULL,
				plan_id varchar(191) NULL,
				membership_id bigint(20) unsigned NOT NULL,
				access_duration_value int(10) unsigned NULL,
				access_duration_unit varchar(20) NULL,
				grace_period_days int(10) unsigned NOT NULL DEFAULT 0,
				cancellation_policy varchar(30) NOT NULL DEFAULT 'period_end',
				refund_policy varchar(30) NOT NULL DEFAULT 'immediate',
				priority int(11) NOT NULL DEFAULT 10,
				active tinyint(1) NOT NULL DEFAULT 1,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY product_id (product_id),
				KEY offer_code (offer_code),
				KEY plan_id (plan_id),
				KEY membership_id (membership_id),
				KEY active (active)
			) $charset;",
		);
	}

	public static function register(): void {
		add_action( 'admin_post_hmp_run_migration', array( __CLASS__, 'manual' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
	}

	public static function manual(): void {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Not allowed.', 'hotmart-memberpress-pro' ) );
		check_admin_referer( 'hmp_run_migration' );
		$ok = self::run( true );
		wp_safe_redirect( add_query_arg( array( 'page' => 'hotmart-memberpress-pro-tools', 'hmp_notice' => $ok ? 'migration_complete' : 'migration_failed' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) return;
		if ( version_compare( (string) get_option( 'hmp_db_version', '0' ), HMP_DB_VERSION, '<' ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'Hotmart MemberPress Pro necesita actualizar su base de datos.', 'hotmart-memberpress-pro' ) . '</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="hmp_run_migration">';
			wp_nonce_field( 'hmp_run_migration' );
			submit_button( __( 'Ejecutar migración ahora', 'hotmart-memberpress-pro' ), 'secondary', 'submit', false );
			echo '</form><p></p></div>';
		} elseif ( get_transient( 'hmp_db_upgrade_success' ) ) {
			delete_transient( 'hmp_db_upgrade_success' );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'La base de datos de Hotmart MemberPress Pro se actualizó correctamente.', 'hotmart-memberpress-pro' ) . '</p></div>';
		}
	}
}
