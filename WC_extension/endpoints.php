<?php
/*
 * License: GPLv3, see LICENSE file in top directory
 * WC_extension/endpoints.php
 *
 * Implementation and registration with WordPress of
 * the various REST API endpoints provided by the
 * WooKitty plugin.
 *
 */

function wookitty_get_nonce() {
	wookitty_ssl_redirect();

	$token  = wp_get_session_token();
	$i      = wp_nonce_tick();
	$action = 'wp_rest';
	$uid    = 1;

	return substr( wp_hash( $i . '|' . $action . '|' . $uid . '|' . $token, 'nonce' ), -12, 10 );
}

function wookitty_get_team() {

	$team = 'WooKitty brought to you by: Duncan Gibson, Haven Hash and Weizho Lin';

	return $team;
}

// Make sure all $verify_keys are in $verify_array and meet type expectations
function wookitty_verify_keys( $verify_keys, $verify_array ) {
	$param = 'JSON parameter:';
	$sent  = 'was not sent as:';

	foreach ( $verify_keys as $verify_key => $verify_type ) {
		if ( array_key_exists( $verify_key, $verify_array ) ) {
			switch ( $verify_type ) {
				case 'int':
					if ( ! is_integer( $verify_array[ $verify_key ] ) ) {
						wookitty_log( "$param $verify_key $sent $verify_type" );
						return -1;
					}
					break;
				case 'int-null':
					if ( ! is_integer( $verify_array[ $verify_key ] ) &&
						! is_null( $verify_array[ $verify_key ] ) ) {
							wookitty_log( "$param $verify_key $sent $verify_type nor was it null" );
						return -1;
					}
					break;
				case 'array':
					if ( ! is_array( $verify_array[ $verify_key ] ) ) {
						wookitty_log( "$param $verify_key $sent $verify_type" );
						return -1;
					}
					break;
				case 'string':
					if ( ! is_string( $verify_array[ $verify_key ] ) ) {
						wookitty_log( "$param $verify_key $sent $verify_type" );
						return -1;
					}
					break;
				default:
					wookitty_log( "Unknown type for JSON parameter: $verify_key" );
					return -1;
			}
		} else {
			wookitty_log( "Required JSON parameter: $verify_key was not found." );
			return -1;
		}
	}

	return 0;
}

function wookitty_verify_dates( $start_date, $end_date ) {
	$start_sec = 0;
	$end_sec   = 0;

	if ( null !== $start_date ) {
		$start_sec = intval( $start_date );
	}

	if ( null !== $end_date ) {
		$end_sec = intval( $end_date );
	}

	// End cannot be before start, return error
	if ( $end_sec < $start_sec ) {
		wookitty_log( "Start date: $start_sec is after end date: $end_date" );
		return -1;
	}

	return 0;
}

function wookitty_query_orders() {
	wookitty_ssl_redirect();
	wookitty_rest_nonce_verify();

	global $wpdb;
	global $wp_filesystem;
	if ( empty( $wp_filesystem ) ) {
		require_once ABSPATH . '/wp-admin/includes/file.php';
		WP_Filesystem();
	}

	// Get pending order ID's from WooKitty table
	$pending                = 'PENDING';
	$db_wookitty_orders_tbl = $wpdb->prefix . 'wookitty_orders';
	$db_wc_items_tbl        = $wpdb->prefix . 'woocommerce_order_items';           // Get order_id from order_item_id
	$db_wc_itemmeta_tbl     = $wpdb->prefix . 'woocommerce_order_itemmeta';     // Line Item details
	$db_wp_postmeta_tbl     = $wpdb->prefix . 'postmeta';                       // Street address info

	$pending_orders = $wpdb->get_results(
		$wpdb->prepare(
			'SELECT wc_order_id FROM %s WHERE wookitty_sync_status = %s;',
			$db_wookitty_orders_tbl,
			$pending
		)
	);

	// No pending orders, nothing to be acked nor transmitted to client
	if ( 0 === count( $pending_orders ) ) {
		return '';
	}

	// Accept orders being acknowledged via the 'known' ID's
	// offered in the POST request.
	$raw_post_data = $wp_filesystem->get_contents( 'php://input' );
	$req_params    = json_decode( $raw_post_data, true );

	$start_date = $req_params['start_date'];
	$end_date   = $req_params['end_date'];

	$wk_req_keys = array(
		'start_date' => 'int-null',
		'end_date'   => 'int-null',
		'known'      => 'array',
	);
	if ( wookitty_verify_keys( $wk_req_keys, $req_params ) ) {
		// HTTP 400 Bad Request
		wp_die( esc_html( __( 'Missing or invalid request parameters.' ) ), 400 );
	}

	if ( wookitty_verify_dates( $start_date, $end_date ) ) {
		// HTTP 400 Bad Request
		wp_die( esc_html( __( 'The start date must come before the end date.' ) ), 400 );
	}

	// Array of JSON objects of returned WC order IDs with associated info
	$known_orders = $req_params['known'];
	$order_keys   = array(
		'id'                => 'int',
		'status'            => 'string',
		'cats_customer_num' => 'int-null',
		'cats_invoice_num'  => 'int-null',
	);

	// Match up currently pending orders with incoming known (acks)
	foreach ( $known_orders as $known_order ) {
		$update = null;

		if ( wookitty_verify_keys( $order_keys, $known_order ) ) {
			if ( ( ! array_key_exists( 'id', $known_order ) ) ||
				( ! is_integer( $known_order['id'] ) ) ) {
				wookitty_log( 'Problematic known order with bad ID, skipping.' );

				continue;
			}

			// ID in the known order is valid at least
			$ko = $known_order['id'];

			// This known (ack) order has an issue, but a valid ID that was pending. Set to error.
			foreach ( $pending_orders as $pending_order ) {
				$po = $pending_order->wc_order_id;
				if ( $ko === $po ) {
					$update = $wpdb->get_results(
						$wpdb->prepare(
							'UPDATE %s SET wookitty_sync_status = %s, ' .
								'wookitty_sync_time=now(), wookitty_sync_details ' .
								'= %s WHERE wc_order_id = %d;',
							$db_wookitty_orders_tbl,
							'ERROR',
							'BAD/MISSING ACK PARAMETERS',
							$ko
						)
					);

					if ( $wpdb->last_error ) {
						wookitty_log( "Failed to update bad order: $ko due to: $wpdb->last_error" );
					} else {
						wookitty_log( "Problematic known order: $ko, set to error." );
					}

					break;
				}
			}

			if ( is_null( $update ) ) {
				wookitty_log( "known order with ID: $ko is malformed and NOT pending." );
			}

			// On to the next known order
			continue;
		}

		foreach ( $pending_orders as $pending_order ) {
			$ko = $known_order['id'];
			$po = $pending_order->wc_order_id;
			if ( $ko === $po ) {
				$status = strtoupper( $known_order['status'] );
				// If not synchronized then error
				if ( 'SYNCHRONIZED' !== $status ) {
					$status = 'ERROR';
				}

				$cats_customer_num = $known_order['cats_cust_num'];
				$cats_invoice_num  = $known_order['cats_invoice_num'];

				$update = $wpdb->get_results(
					$wpdb->prepare(
						'UPDATE %s SET wookitty_sync_status = %s, ' .
							'wookitty_sync_time=now(), wookitty_sync_details = %s, ' .
							'cats_customer_num = %d, ' .
							'cats_invoice_num = %d WHERE wc_order_id = %d;',
						$db_wookitty_orders_tbl,
						$status,
						'',
						$cats_customer_num,
						$cats_invoice_num,
						$ko
					)
				);
				if ( $wpdb->last_error ) {
					wookitty_log( "Failed to update PENDING order $po: $wpdb->last_error" );
				} else {
					wookitty_log( "WooCommerce order: $po successfully updated from Cats PENDING to SYNCHRONIZED." );
				}

				break;
			}
		}
	}

	$range = '';
	if ( ! is_null( $end_date ) ) {
		$range = "AND wookitty_sync_time BETWEEN FROM_UNIXTIME( $start_date ) AND FROM_UIXTIME( $end_date )";
	}

	// Clean re-SELECT as some pending entries may have been known (ack'd above)
	$results = $wpdb->get_results(
		$wpdb->prepare(
			'SELECT wc_order_id FROM %s WHERE wookitty_sync_status = %s ' . $range . ';', // phpcs:ignore
			$db_wookitty_orders_tbl,
			$pending
		)
	);

	$orders = array();
	foreach ( $results as $res ) {
		$order = array();

		// XXX: Could trim down requested columns, but safer forward compatability-wise to send everything
		$addr_info = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %1s WHERE post_id = %d;',
				$db_wp_postmeta_tbl,
				$res->wc_order_id
			)
		);
		if ( $wpdb->last_error ) {
			wookitty_log( "Could not get address info for order $res->wc_order_id: $wpdb->last_error" );
		} else {
			wookitty_log( "Sending address info for order: $res->wc_order_id" );
		}

		// Pending order deleted from WC before synchronization, move to error.
		if ( empty( $addr_info ) ) {
			$update = $wpdb->get_results(
				$wpdb->prepare(
					'UPDATE %1s SET wookitty_sync_status = %s, ' .
					'wookitty_sync_time=now(), wookitty_sync_details = %s WHERE wc_order_id = %d;',
					'ERROR',
					'No associated address info.',
					$db_wookitty_settings_tbl,
					$res->wc_order_id
				)
			);
			if ( $wpdb->last_error ) {
				wookitty_log( "Failed to update bad order: $res->wc_order_id due to: $wpdb->last_error" );
			} else {
				wookitty_log( "No WC address information for: $res->wc_order_id, set to error." );
			}

			continue;
		}

		$order['addr_info'] = $addr_info;

		// Get more specific Line Item IDs out of the order
		$li_lids = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT order_item_id FROM %1s WHERE order_id = %d;',
				$db_wc_items_tbl,
				$res->wc_order_id
			)
		);
		// Order with 0 Line Items does not seem good, abort?
		if ( 0 === count( $li_ids ) ) {
			wookitty_log( "Order: $res->wc_order_id does not have any associated Line Items!" );
		}

		// Sub-array of details for each line item
		$li_entries = array();
		foreach ( $li_ids as $li ) {
			$li_info = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %1s WHERE order_item_id = %d',
					$db_wc_itemmeta_tbl,
					$li->order_item_id
				)
			);

			$li_entries[] = $li_info;
		}

		$order['line_items'] = $li_entries;
		$orders[]            = $order;
	}

	return $orders;
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'wookitty/v1',
			'/nonce',
			array(
				'methods'             => 'GET',
				'callback'            => 'wookitty_get_nonce',
				'permission_callback' => '__return_true',
			)
		);
	}
);

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'wookitty/v1',
			'/team',
			array(
				'methods'             => 'GET',
				'callback'            => 'wookitty_get_team',
				'permission_callback' => '__return_true',
			)
		);
	}
);

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'wookitty/v1',
			'/query_orders',
			array(
				'methods'             => 'POST',
				'callback'            => 'wookitty_query_orders',
				'permission_callback' => 'wookitty_rest_permissions_cb',
			)
		);
	}
);
