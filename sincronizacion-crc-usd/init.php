<?php
/**
 * Sincronización CRC → USD para WooCommerce
 *
 * Permite definir precios base en colones (CRC) y sincronizar
 * automáticamente el precio de WooCommerce en dólares (USD)
 * según tipo de cambio del BCCR o manual.
 *
 * @package nalakalu-2025
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Solo cargar si WooCommerce está activo
if ( ! class_exists( 'WooCommerce' ) ) {
	return;
}

define( 'NLK_CRC_USD_PATH', __DIR__ );

require_once NLK_CRC_USD_PATH . '/class-nlk-exchange-rate.php';
require_once NLK_CRC_USD_PATH . '/class-nlk-product-meta.php';
require_once NLK_CRC_USD_PATH . '/class-nlk-price-sync.php';
require_once NLK_CRC_USD_PATH . '/class-nlk-admin-settings.php';
require_once NLK_CRC_USD_PATH . '/class-nlk-cron.php';

// Inicializar módulos
NLK_Exchange_Rate::init();
NLK_Product_Meta::init();
NLK_Price_Sync::init();
NLK_Admin_Settings::init();
NLK_Cron::init();
