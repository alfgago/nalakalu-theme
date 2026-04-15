<?php
/**
 * WooCommerce CSV batch importer bootstrap.
 *
 * @package nalakalu-2025
 */

if (! defined('ABSPATH')) {
	exit;
}

if (! class_exists('WooCommerce')) {
	return;
}

define('NLK_WOO_IMPORT_PATH', __DIR__);

require_once NLK_WOO_IMPORT_PATH . '/class-nlk-woo-csv-import.php';

NLK_Woo_CSV_Import::init();
