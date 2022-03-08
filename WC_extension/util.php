<?php
/*
 * License: GPLv3, see LICENSE file in top directory
 * WC_extension/util.php
 *
 * Utility routines used throughout the plugin along
 * with security related implementations.
 */

// Insert log entries into WooKitty log table for administrative viewing
function wookitty_log( $log_msg ) { 
	global $wpdb;
	$db_wookitty_log_tbl = $wpdb->prefix . 'wookitty_logs';

	$wpdb->insert(
		$db_wookitty_log_tbl,
		array(
			'log_timestamp'     => current_time( 'mysql' ),
			'wookitty_log_mesg' => $log_msg,
		)
	);
}

// If non-SSL request is detected, redirect to SSL version.
function wookitty_ssl_redirect() {
	if ( is_ssl() ) {
		return;
	}

	if ( ! isset( $_SERVER['HTTP_HOST'] ) || ! isset( $_SERVER['REQUEST_URI'] ) ) {
		wp_die( esc_html__( 'Bad HTTP request parameters.' ), 400 );
	}

	$loc = 'https://' . esc_url_raw( $_SERVER['HTTP_HOST'] ) . esc_url_raw( $_SERVER['REQUEST_URI'] );
	// The HTTP 308 redirect is permanent and does not allow changing from POST to GET
	wp_redirect( $loc, 308, 'WK REST API' );
	exit;
}

// Make sure the caller provides an authenticated nonce and that it is for the REST API
function wookitty_rest_nonce_verify() {
	$nonce_set = isset( $_SERVER['HTTP_X_WP_NONCE'] );

	if ( ! $nonce_set ) {
		wp_die( esc_html__( 'Invalid nonce - HTTP X-WP-NONCE header not set.' ), 401 );
	}

	$nonce = $_SERVER['HTTP_X_WP_NONCE'];
	// If a nonce was provided then it was already verified by the REST engine,
	// but check again for our own clarity.
	$res = wp_verify_nonce( $nonce, 'wp_rest' );
	if ( false === $res ) {
		wp_die( esc_html__( 'Invalid REST nonce received.' ), 401 );
	}
}

// Only the configured user is allowed to make auth required WK REST API calls
function wookitty_rest_permissions_cb() {
	// TODO: Enforcement of whitelist for WSU Press IPs can take place here if desired.

	global $wpdb;
	$db_wookitty_settings_tbl = $wpdb->prefix . 'wookitty_settings';

	$res = $wpdb->get_results( $wpdb->prepare( 'SELECT rest_username FROM %s ;', $db_wookitty_settings_tbl ) );
	if ( $wpdb->last_error ) {
		wookitty_log( "Could not select rest_username! $wpdb->last_error" );
		return false;
	} elseif ( 1 !== count( $res ) ) {
		wookitty_log( 'Zero or multiple rest_usernames found!' );
		return false;
	}

	// Database enforced single row table
	$row       = $result[0];
	$conf_name = $row->rest_username;

	$user     = wp_get_current_user();
	$req_name = $user->user_login;

	if ( $conf_name === $req_name ) {
		return true;
	}

	return false;
}
