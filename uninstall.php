<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! defined( 'HMP_REMOVE_DATA_ON_UNINSTALL' ) || true !== HMP_REMOVE_DATA_ON_UNINSTALL ) {
	return;
}

global $wpdb;

$tables = array(
	$wpdb->prefix . 'hmp_events',
	$wpdb->prefix . 'hmp_activations',
	$wpdb->prefix . 'hmp_mappings',
);
foreach ( $tables as $table ) {
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

$options = array(
	'hmp_db_version',
	'hmp_hottok',
	'hmp_webhook_enabled',
	'hmp_payload_retention_days',
	'hmp_default_grace_period_days',
	'hmp_debug_mode',
);
foreach ( $options as $option ) {
	delete_option( $option );
}
wp_clear_scheduled_hook( 'hmp_daily_cleanup' );
