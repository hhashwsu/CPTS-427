<?php
if ( isset( $_POST['action'] ) ) {
	/*$nonce_name = $_POST['action'] . '_nonce'
	if ( ! isset($_POST[ $nonce_name ] ) ) {
		echo 'nonce_name is NOT set: ' . $nonce_name;
	} else {
		echo 'nonce_name is set: ' . $nonce_name;

	$nonce_val = $_POST[ $nonce_name ];*/
	if ( wp_verify_nonce( $_POST['wookitty_config_nonce'], 'wookitty_config' ) ) {
		echo 'We VERIFIED';
	} else {
		echo 'We did NOT verify.';
	}
	switch ( $_POST['action'] ) {
		case 'settings':
			$msg = 'WooKitty Settings Updated.';
			break;
                case 'bulk_upload':
                        $msg = 'Bulk upload processed.';
                        break;
                case 'label_print':
                        $msg = 'Label Printing activated.';
                        break;
        }
}

function wookitty_orders_sql_init() {
	global $wpdb;
	$db_wookitty_orders_tbl   = $wpdb->prefix . 'wookitty_orders';     // WooKitty Order details (sync status and details)
	$db_wookitty_logs_tbl     = $wpdb->prefix . 'wookitty_logs';       // Table to store actions performed by the plugin
	$db_wookitty_settings_tbl = $wpdb->prefix . 'wookitty_settings';   // Configuration details related to WooKitty

	// upgrade.php gives us dbDelta()
	require_once ABSPATH . 'wp-admin/includes/upgrade.php'; 

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
	if ( 0 === count( $res ) ) {
		$res = $wpdb->get_results(
			$wpdb->prepare(
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
