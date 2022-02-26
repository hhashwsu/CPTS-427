<?php
/*
 * License: GPLv3, see LICENSE file in top directory
 * WC_extension/sql-init.php
 *
 * On plugin load check to see if WooKitty tables
 * exist in the database or not. If a table exists
 * then leave it alone and if not then create it.
 *
 */
function wookitty_orders_sql_init() {
	global $wpdb;
	$db_wookitty_orders_tbl = $wpdb->prefix . 'wookitty_orders';       // WooKitty Order details (sync status and details)
	$db_wookitty_logs_tbl = $wpdb->prefix . 'wookitty_logs';           // Table to store actions performed by the plugin
	$db_wookitty_settings_tbl = $wpdb->prefix . 'wookitty_settings';   // Configuration details related to WooKitty

	// upgrade.php gives us dbDelta()
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

	// We do not know the database details of the WSU Press server so do not specify
	// charsets or engine types, generic SQL is fine for our purposes.
	// N.B.: This gives us persistance and no errors on reload, but for an upgrade with table changes we will
	// likely need to employ add_option() with test_db_version to do the ALTER statements.
	$sql = 'CREATE TABLE IF NOT EXISTS ' . $db_wookitty_orders_tbl . ' (wc_order_id INT PRIMARY KEY, wookitty_sync_status VARCHAR(15), ' .
		'wookitty_sync_time TIMESTAMP, wookitty_sync_details VARCHAR(60), cats_customer_num INT, cats_invoice_num INT);';
	dbDelta( $sql );

	$sql = 'CREATE TABLE IF NOT EXISTS ' . $db_wookitty_logs_tbl . ' (log_timestamp TIMESTAMP, wookitty_log_mesg VARCHAR(256),' .
		'log_source VARCHAR(32));';
	dbDelta( $sql );

	$res = $wpdb->get_results( $wpdb->prepare( 'SHOW TABLES LIKE %s ;', $wpdb->esc_like( $db_wookitty_settings_tbl ) ) );

	$sql = 'CREATE TABLE IF NOT EXISTS' . $db_wookitty_settings_tbl . '(single_row INT NOT NULL PRIMARY KEY CHECK (single_row = 1),' .
		'cats_autosync BOOLEAN DEFAULT 0, rest_username VARCHAR(256) , system_error BOOLEAN, error_mesg VARCHAR(256));';
	dbDelta( $sql );

	// Populate the one and only settings row
	if ( 0 == count( $res ) ) {
		$res = $wpdb->get_results( $wpdb->prepare(
			'INSERT INTO %s (single_row, cats_autosync, rest_username, system_error,  error_mesg) VALUES (%d, %d, %s, %d, %s);',
			$db_wookitty_settings_tbl,
			1,
			0,
			'wsupress_restuser',
			0,
			''
			)
		);
		wookitty_log( 'WooKitty SQL tables created and initialized.' );
	}

	wookitty_log( 'WooKitty plugin activated.' );
}
