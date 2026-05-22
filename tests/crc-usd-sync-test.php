<?php

define( 'ABSPATH', __DIR__ );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['nlk_test_options'] = array(
	'nlk_crc_usd_modo'                => 'manual',
	'nlk_crc_usd_tipo_cambio_manual' => 500,
);
$GLOBALS['nlk_test_updated_meta']       = array();
$GLOBALS['nlk_test_updated_options']    = array();
$GLOBALS['nlk_test_scheduled_events']   = array();
$GLOBALS['nlk_test_unscheduled_events'] = array();
$GLOBALS['nlk_test_existing_schedule']  = false;
$GLOBALS['nlk_test_transients_deleted'] = array();

function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['nlk_test_options'] ) ? $GLOBALS['nlk_test_options'][ $key ] : $default;
}

function update_option( $key, $value ) {
	$GLOBALS['nlk_test_options'][ $key ] = $value;
	$GLOBALS['nlk_test_updated_options'][] = array( $key, $value );
	return true;
}

function get_post_meta( $post_id, $key, $single = false ) {
	if ( '_nlk_fixed_usd' === $key && 102 === (int) $post_id ) {
		return 'yes';
	}

	return '';
}

function update_post_meta( $post_id, $key, $value ) {
	$GLOBALS['nlk_test_updated_meta'][] = array(
		'post_id' => (int) $post_id,
		'key'     => $key,
		'value'   => $value,
	);
	return true;
}

function wc_delete_product_transients( $product_id ) {
	return true;
}

function current_time( $type ) {
	return '2026-05-22 12:00:00';
}

function wp_next_scheduled( $hook ) {
	return $GLOBALS['nlk_test_existing_schedule'];
}

function wp_schedule_event( $timestamp, $recurrence, $hook ) {
	$GLOBALS['nlk_test_scheduled_events'][] = array( $timestamp, $recurrence, $hook );
	return true;
}

function wp_unschedule_event( $timestamp, $hook ) {
	$GLOBALS['nlk_test_unscheduled_events'][] = array( $timestamp, $hook );
	return true;
}

function delete_transient( $key ) {
	$GLOBALS['nlk_test_transients_deleted'][] = $key;
	return true;
}

function add_action() {}
function add_filter() {}

class NLK_Test_WPDB {
	public $postmeta = 'wp_postmeta';
	public $posts = 'wp_posts';
	public $last_query = '';

	public function prepare( $query, ...$args ) {
		foreach ( $args as $arg ) {
			$query = preg_replace( '/%s/', "'" . addslashes( $arg ) . "'", $query, 1 );
		}
		return $query;
	}

	public function get_results( $query ) {
		$this->last_query = $query;

		if ( strpos( $query, 'AS usd_price' ) !== false ) {
			return array(
				(object) array( 'post_id' => 201, 'usd_price' => '50' ),
			);
		}

		return array(
			(object) array( 'post_id' => 101, 'meta_value' => '25000' ),
			(object) array( 'post_id' => 102, 'meta_value' => '18000' ),
		);
	}
}

function assert_true( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

require __DIR__ . '/../sincronizacion-crc-usd/class-nlk-exchange-rate.php';
require __DIR__ . '/../sincronizacion-crc-usd/class-nlk-product-meta.php';
require __DIR__ . '/../sincronizacion-crc-usd/class-nlk-price-sync.php';
require __DIR__ . '/../sincronizacion-crc-usd/class-nlk-cron.php';

global $wpdb;
$wpdb = new NLK_Test_WPDB();

$result = NLK_Price_Sync::sync_all_products();

assert_true( $result['success'] === true, 'Bulk sync should succeed with a configured manual rate.' );
assert_true( $result['updated'] === 1, 'Bulk sync should update only eligible products.' );
assert_true( strpos( $wpdb->last_query, "p.post_type IN ('product', 'product_variation')" ) !== false, 'Bulk sync must query only products and variations.' );
assert_true( strpos( $wpdb->last_query, 'INNER JOIN' ) !== false, 'Bulk sync must join posts before updating product price meta.' );

$updated_keys = array_values( array_unique( array_map( function( $row ) {
	return $row['key'];
}, $GLOBALS['nlk_test_updated_meta'] ) ) );
sort( $updated_keys );

assert_true( $updated_keys === array( '_price', '_regular_price' ), 'Bulk sync may only write WooCommerce price meta.' );
assert_true( count( $GLOBALS['nlk_test_updated_meta'] ) === 2, 'Eligible products should receive exactly _regular_price and _price updates.' );
assert_true( $GLOBALS['nlk_test_updated_meta'][0]['post_id'] === 101, 'Fixed USD products should not be updated.' );
assert_true( $GLOBALS['nlk_test_updated_meta'][0]['value'] === 50.0, 'CRC should convert to USD using the active manual rate.' );

NLK_Cron::maybe_schedule();
assert_true( $GLOBALS['nlk_test_scheduled_events'] === array(), 'Manual mode should not schedule recurring price sync.' );

assert_true( method_exists( 'NLK_Price_Sync', 'sync_all_after_manual_rate_change' ), 'Manual exchange rate changes should have an automatic sync hook.' );

$GLOBALS['nlk_test_updated_meta'] = array();
$GLOBALS['nlk_test_options']['nlk_crc_usd_tipo_cambio_manual'] = 625;
NLK_Price_Sync::sync_all_after_manual_rate_change( 500, 625, 'nlk_crc_usd_tipo_cambio_manual' );
assert_true( count( $GLOBALS['nlk_test_updated_meta'] ) === 2, 'Changing the manual exchange rate should sync eligible product prices.' );
assert_true( $GLOBALS['nlk_test_updated_meta'][0]['value'] === 40.0, 'Manual rate changes should sync using the new exchange rate.' );

$GLOBALS['nlk_test_updated_meta'] = array();
$backfill = NLK_Price_Sync::backfill_crc_from_usd();
assert_true( $backfill['success'] === false, 'Backfill from USD to CRC should be disabled to avoid changing non-price product data.' );
assert_true( $GLOBALS['nlk_test_updated_meta'] === array(), 'Backfill should not write product metadata.' );

echo "CRC/USD sync tests passed\n";
