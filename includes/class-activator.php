<?php
namespace HMP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Activator {
	public static function activate(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();

		$sql = array(
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

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}

		update_option( 'hmp_db_version', HMP_VERSION );
		Settings::add_defaults();
		Cleanup::schedule();
	}

	public static function deactivate(): void {
		Cleanup::unschedule();
	}
}
