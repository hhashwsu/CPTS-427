<?php
/*
 * License: GPLv3, see LICENSE file in top directory
 * WC_extension/settings.php
 *
 * Centralized WebUI integrated into WordPress
 * for settings related to WooKitty plugin with a
 * Log Viewer for seeing activity related to
 * plugin and client daemon.
 *
 */

// XXX: Passes code compliance checks with php7.3, but not with php7.4
if ( 1 ) { // empty( $_POST['action'] ) ) {
	// if ( wp_verify_nonce( $_POST['wookitty_config_nonce'], 'wookitty_config' ) ) {
	//	echo 'We VERIFIED';
	//} else {
	//	echo 'We did NOT verify.';
	//}
	//switch ( $_POST['action'] ) {
	switch ( 'settings' ) {
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

if ( 1 ) { // empty( $_GET['action'] ) ) {
	// switch ( $_GET['action'] ) {
	switch ( 'logs_50' ) {
		case 'logs_50':
			$msg = 'Show last 50 logs.';
			break;
		case 'logs_500':
			$msg = 'Show last 500 logs.';
			break;
		case 'logs_dl':
			$msg = 'Download all logs.';
			break;
	}
}

function wookitty_get_setting( $setting ) {
	global $wpdb;
	$db_wookitty_settings_tbl = $wpdb->prefix . 'wookitty_settings';

	$res = $wpdb->get_results( $wpdb->prepare( 'SELECT %1s FROM %1s ;', $setting, $db_wookitty_settings_tbl ) );
	if ( 1 !== count( $res ) ) {
		wookitty_log( "Failed to retrieve: $setting from $db_wookitty_settings_tbl" );
		return '';
	}

	return $res[0]->$setting;
}

function wookitty_get_logs() {
	global $wpdb;
	$logs                 = 50;
	$db_wookitty_logs_tbl = $wpdb->prefix . 'wookitty_logs';

	//if ( $_GET && array_key_exists( $_GET, 'logs' ) ) {
	if ( null !== @$_GET['logs'] ) {
		$logs = 20; // $logs = $_GET['logs'];
	}

	$res = $wpdb->get_results(
		$wpdb->prepare(
			'SELECT * FROM %1s ORDER BY log_timestamp DESC LIMIT %d;',
			$db_wookitty_logs_tbl,
			$logs
		)
	);
	if ( $wpdb->last_error ) {
		wookitty_log( "Failed to retrieve WooKitty logs: $wpdb->last_error" );
		return '';
	}

	$html_str = '';
	for ( $i = 0; $i < $logs; $i++ ) {
		$row      = $res[ $i ];
		$html_str = $html_str . '<tr><td>' . esc_html( $row->log_timestamp ) . '</td><td style="max-width: 320px;">' .
		esc_html( $row->wookitty_log_mesg ) . '</td><td>' . esc_html( $row->log_source ) . '</td></tr>';
	}

	return $html_str;
}

$allowed_html = array(
	'tr'    => array(),
	'td'    => array(
		'style' => array(),
	),
	'input' => array(
		'type'  => array(),
		'name'  => array(),
		'value' => array(),
		'id'    => array(),
	),
);

	// XXX: wp_nonce_field() cannot simply be called from within wp_kses() or
	// it breaks the filtering, but it works if done in steps like this.
	$wookitty_config_nonce_html = wp_nonce_field( 'wookitty_config', 'wookitty_config_nonce' );
	//$bulk_upload_nonce_html = wp_nonce_field( 'bulk_upload', 'bulk_upload_nonce' );
	//$label_print_nonce_html = wp_nonce_field( 'label_print', 'label_print_nonce' );

	echo '<form action="" method="post" id="settings_form">' .
	wp_kses( $wookitty_config_nonce_html, $allowed_html ) .
	'</form><form action="" method="post" id="bulk_upload_form">' .
	wp_kses( $wookitty_confi_nonce_html, $allowed_html ) .
	'</form><form action="" method="post" id="label_print_form">' .
	wp_kses( $wookitty_config_nonce_html, $allowed_html ) .
	'</form><form action="?page=wookitty&logs=50" method="get" id="logs_50_form">' .
	'</form><form action="?page=wookitty&logs=500" method="get" id="logs_500_form">' .
	'</form><form action="?page=wookitty&action=logs_dl" method="get" id="logs_dl_form">' .
	'</form>' .
	'<table>' .
	'<tr><td colspan="3"><h1>Settings</h1></td></tr>' .
	'<tr style="width:75%">' .
	'<td colspan="2" style="width:75%">Automatic Synchronization of incoming orders with The Cats Pajamas: </td>' .
	'<td><input type="checkbox" name="autosync_checkbox" form="settings_form" value="autosync" ';
if ( 0 !== wookitty_get_setting( 'cats_autosync' ) ) {
	echo 'checked';
}
	echo '></td><td></td></tr>' .
	'<td colspan="2">WordPress username allowed to use WooKitty REST API: </td>' .
	'<td><input type="textbox" name="rest_username_input" form="settings_form" value="' .
	esc_html( wookitty_get_setting( 'rest_username' ) ) . '"></td><td></td></tr>' .
	'<tr><td colspan="2"><input type="hidden" name="action" form="settings_form" value="settings"></td>' .
	'<td><input type="submit" form="settings_form" value="Update"></td></tr>' .

	'<tr><td colspan="3"><hr color="black" /></td></tr>' .
	'<tr><td colspan="3"><h1>Bulk Upload</h1></td></tr>' .
	'<tr><td colspan="2">Select a file: <input type="file" name="upload_file" form="bulk_upload_form"></td>' .
	'<td><input type="submit" form="bulk_upload_form" value="Upload File"></td></tr>' .
	'<tr><td colspan="3"><input type="hidden" name="action" form="bulk_upload_form" value="bulk_upload"></td></tr>' .

	'<tr><td colspan="3"><hr color="black" /></td></tr>' .
	'<tr><td colspan="3"><h1>Label Printing</h1></td></tr>' .
	'<tr><td>First Name: <input type="textbox" name="first_name" form="label_print_form"></td>' .
	'<td>Last Name: <input type="textbox" name="last_name" form="label_print_form" maxlength="21" size="21">' .
	'</td><td>Cats Customer #:<input type="textbox" name="cats_cust_num" form="label_print_form"' .
	'maxlength="8" size="8"></td></tr>' .
	'<tr><td>WooCommerce Order: <input type="textbox" name="wc_order_id" form="label_print_form" maxlength="10" size="10"></td>' .
	'<td>E-mail: <input type="textbox" name="email" form="label_print_form" maxlength="25" size="25"></td>' .
	'<td><input type="submit" form="label_print_form" value="Lookup Customer"></td></tr>' .
	'<tr><td colspan="3"><input type="hidden" name="action" form="label_print_form" value="label_print"></td></tr>' .

	'<tr><td colspan="3"><hr color="black" /></td></tr>' .
	'<tr><td colspan="3"><h1>WooKitty Logs</h1></td></tr>' .
	'<tr><th style="text-align:left">Timestamp</th><th style="text-align:left; word-wrap: break-word; max-width: 513px;">Message</th><th style="text-align:left">Source</th></tr>' .
	wp_kses( wookitty_get_logs(), $allowed_html ) .
	'<tr><td><input type="submit" value="Last 50" form="logs_50_form"></td>' .
	'<td><input type="submit" value="Last 500" form="logs_500_form"></td>' .
	'<td><input type="submit" value="Download All Logs" form="logs_dl_form"></td></tr>' .
	'<tr><td colspan="3"><input type="hidden" name="action" form="logs_50_form" value="logs_50"></td></tr>' .
	'<tr><td colspan="3"><input type="hidden" name="logs" form="logs_50_form" value="50"></td></tr>' .
	'<tr><td colspan="3"><input type="hidden" name="page" form="logs_50_form" value="wookitty"></td></tr>' .
	'<tr><td colspan="3"><input type="hidden" name="action" form="logs_500_form" value="logs_500"></td></tr>' .
	'<tr><td colspan="3"><input type="hidden" name="logs" form="logs_500_form" value="500"></td></tr>' .
	'<tr><td colspan="3"><input type="hidden" name="page" form="logs_500_form" value="wookitty"></td></tr>' .
	'<tr><td colspan="3"><input type="hidden" name="action" form="logs_dl_form" value="logs_dl"></td></tr>' .
	'<tr><td colspan="3"><input type="hidden" name="page" form="logs_dl_form" value="wookitty"></td></tr>' .
	'</table>';

	// XXX Temp debugging
	echo "$msg"; // phpcs:ignore
	//print_r( $_POST ); // phpcs:ignore
