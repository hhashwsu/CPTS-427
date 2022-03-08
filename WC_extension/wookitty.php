<?php
/*
 * License: GPLv3, see LICENSE file in top directory
 * WC_extension/WooKitty.php
 *
 * WooKitty WordPress/WooCommerce plugin meta-information,
 * UI elements and includes for other plugin functionality.
 *
 */

/**
 * @package WooKitty
 * @version 0.0.5
 */
/*
Plugin Name: WSU Press WooKitty Extension
Description: Provides Admin interface and REST API endpoints for synchronizing WooCommerce orders with The Cats Pajamas back-end.
Author: Team WooKitty
*/

require_once plugin_dir_path( __FILE__ ) . '/util.php';
require_once plugin_dir_path( __FILE__ ) . '/endpoints.php';
require_once plugin_dir_path( __FILE__ ) . '/sql-init.php';
require_once plugin_dir_path( __FILE__ ) . '/cats-sync-action.php';

add_filter( 'manage_edit-shop_order_columns', 'wookitty_custom_shop_order_column', 20 );
function wookitty_custom_shop_order_column( $columns ) {
	$reordered_columns = array();

	// Inserting columns to a specific location
	foreach ( $columns as $key => $column ) {
		$reordered_columns[ $key ] = $column;
		if ( 'order_status' === $key ) {
			// Inserting after "Status" column
			$reordered_columns['cats-sync'] = __( 'Cats Sync', 'theme_domain' );
			$reordered_columns['sync-time'] = __( 'Sync Time', 'theme_domain' );
		}
	}
	return $reordered_columns;
}

// Adding custom fields meta data for each new column (example)
add_action( 'manage_shop_order_posts_custom_column', 'wookitty_custom_orders_list_column_content', 20, 2 );
function wookitty_custom_orders_list_column_content( $column, $post_id ) {
	// upgrade.php gives us dbDelta()
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	global $wpdb;
	$db_wookitty_orders_tbl = $wpdb->prefix . 'wookitty_orders';

	$results = $wpdb->get_results(
		$wpdb->prepare(
			'SELECT * FROM %s WHERE wc_order_id = %s',
			$db_wookitty_orders_tbl,
			$post_id
		)
	);

	// If no WK order entry then red for unsynched, if pending then orange,
	// else green for success. Even in the sync-detail case we still want
	// to check the status for the color so always run the query.
	$color   = 'red';
	$status  = 'Not Synchronized';
	$details = 'N/A';

	// More than one row should not be possible as wc_order_id is a unique key
	// Number of results should always be 0 or 1
	if ( 0 < count( $results ) ) {
		$row       = $results[0];
		$status    = $row->wookitty_sync_status;
		$sync_time = $row->wookitty_sync_time;

		if ( 'PENDING' === $status ) {
			$color = 'orange';
		} elseif ( 'SYNCHRONIZED' === $status ) {
			$color = 'green';
		} // else leave as red for ERROR states
	}

	switch ( $column ) {
		case 'cats-sync':
			echo '<small><em><b><font color="' . esc_attr( $color ) . '">' . esc_html( $status ) . '</font></b></em></small>';
			break;

		case 'sync-time':
			echo '<small><em><b><font color="' . esc_attr( $color ) . '">' . esc_html( $sync_time ) . '</font></b></em></small>';
			break;
	}
}

function add_extension_register_wookitty_panel() {
	if ( ! function_exists( 'wc_admin_connect_page' ) ) {
		return;
	}

	wc_admin_register_page(
		array(
			'id'       => 'wookitty-admin-panel',
			'title'    => 'WooKitty',
			'parent'   => 'woocommerce',
			'path'     => '/wk',
			'nav_args' => array(
				'order'  => 10,
				'parent' => 'woocommerce',
			),
		)
	);
}
add_action( 'admin_menu', 'add_extension_register_wookitty_panel' );

register_activation_hook( __FILE__, 'wookitty_orders_sql_init' );
