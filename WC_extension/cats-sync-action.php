<?php
/*
 * License: GPLv3, see LICENSE file in top directory
 * WC_extension/cats-sync-action.php
 *
 * UI addition and implmentation of the 'Sync with Cats'
 * bulk action drop down option on the WooCommerce orders
 * page.
 *
 */
// Additional entry in Bulk Actions drop-down on Orders page
add_filter( 'bulk_actions-edit-shop_order', 'wookitty_sync_with_cats' );
function wookitty_sync_with_cats( $bulk_actions ) {
	$bulk_actions['sync-with-cats'] = __( 'Sync with Cats', 'txtdomain' );
	return $bulk_actions;
}

// Make the action from selected orders
add_filter( 'handle_bulk_actions-edit-shop_order', 'wookitty_sync_with_cats_handle_bulk_action_edit_shop_order', 10, 3 );
function wookitty_sync_with_cats_handle_bulk_action_edit_shop_order( $redirect_to, $action, $post_ids ) {
	if ( ( 'sync-with-cats' !== $action ) || ( empty( $post_ids ) ) ) {
		return $redirect_to; // Got here without selecting this action or no orders selected, return.
	}

	global $wpdb;
	$pending                = 'PENDING';
	$processed_ids          = array();
	$pend_ids               = array();
	$no_pend_ids            = array();
	$db_wookitty_orders_tbl = $wpdb->prefix . 'wookitty_orders';

	// Do not allow already sychronized orders to be put back into pending
	// and do not update the timestamp on already pending WK orders.
	foreach ( $post_ids as $post_id ) {
		$results = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT wc_order_id, wookitty_sync_status FROM %1s WHERE wc_order_id = %d;',
				$db_wookitty_orders_tbl,
				$post_id
			)
		);
		// Does not exist in WK orders table so add it as pending
		if ( empty( $results ) ) {
			$pend_ids[] = $post_id;
		} else {
			if ( 'ERROR' === $results[0]->wookitty_sync_status ) {
				$results = $wpdb->get_results(
					$wpdb->prepare(
						'UPDATE %1s SET wookitty_sync_status = %s, ' .
						'wookitty_sync_time=now() WHERE wc_order_id = %d;',
						$db_wookitty_orders_tbl,
						$pending,
						$post_id
					)
				);
				if ( $wpdb->last_error ) {
					wookitty_log( "Failed update ERROR order: $post_id to PENDING: $wpdb->last_error" );
				} else {
					wookitty_log( "Succesfully updated: $post_id from ERROR to PENDING." );
					$processed_ids[] = $post_id;
				}

				continue;   // Next selected order
			}

			$no_pend_ids[] = $post_id;
		}
	}

	foreach ( $pend_ids as $post_id ) {
		// Create new PENDING entries in WK orders table
		$results = $wpdb->get_results(
			$wpdb->prepare(
				'INSERT INTO $1s (wc_order_id, wookitty_sync_time, wookitty_sync_status, ' .
				'wookitty_sync_details) VALUES (%d, now(), %s, %s);',
				$db_wookitty_orders_tbl,
				$post_id,
				$pending,
				0,
				0
			)
		);
		if ( $wpdb->last_error ) {
			wookitty_log( "Failed to create new PENDING order $post_id: $wpdb->last_error" );
		} else {
			wookitty_log( "Succesfully created new PENDING order: $post_id" );
			$processed_ids[] = $post_id;
		}
	}

	// Success if all selected orders were created as PENDING entries in WK orders table,
	// warning if some were created and some were skipped, and error if all were skipped.
	$notice_lvl = 'success';
	if ( empty( $processed_ids ) && ! empty( $no_pend_ids ) ) {
		$notice_lvl = 'error';
	} elseif ( ! empty( $processed_ids ) && ! empty( $no_pend_ids ) ) {
		$notice_lvl = 'warning';
	}

	// Add stats to query string to present a message to the user
	$redirect_to = add_query_arg(
		array(
			'sync_with_cats'  => '1',
			'notice_lvl'      => $notice_lvl,
			'processed_count' => count( $processed_ids ),
			'processed_ids'   => implode( ',', $processed_ids ),
			'no_pend_count'   => count( $no_pend_ids ),
			'no_pend_ids'     => implode( ',', $no_pend_ids ),
		),
		$redirect_to
	);
	return $redirect_to;
}

// Display notice regarding Sync with Cats bulk action
add_action( 'admin_notices', 'sync_with_cats_bulk_action_admin_notice' );
function sync_with_cats_bulk_action_admin_notice() {
	global $pagenow;

	if ( 'edit.php' === $pagenow && isset( $_GET['post_type'] )
		&& 'shop_order' === $_GET['post_type'] &&
		isset( $_GET['sync_with_cats'] ) ) {

		$notice_lvl      = $_GET['notice_lvl'];
		$processed_count = $_GET['processed_count'];
		$processed_ids   = $_GET['processed_ids'];
		$no_pend_count   = $_GET['no_pend_count'];
		$no_pend_ids     = $_GET['no_pend_ids'];

		$skipped_ids_msg = '';
		if ( 'success' !== $notice_lvl ) {
			$skipped_ids_msg = '</br>' . $no_pend_count .
				' order(s) <b>NOT</b> processed for Cats synchronization ( ' . $no_pend_ids . ' )';
		}

		printf(
			'<div class="notice notice-' . esc_attr( $notice_lvl ) . ' fade is-dismissible"><p>' .
			esc_html( $processed_count ) . ' order(s) processed for Cats synchronization ( ' . esc_html( $processed_ids ) . ' )' .
			esc_html( $skipped_ids_msg ) .
			'</p></div>'
		);
	}
}
