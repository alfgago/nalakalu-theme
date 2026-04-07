<?php
/**
 * Lógica de conversión CRC → USD y actualización masiva de precios.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NLK_Price_Sync {

	public static function init() {
		// AJAX: actualización masiva manual desde admin
		add_action( 'wp_ajax_nlk_crc_usd_sync_all', array( __CLASS__, 'ajax_sync_all' ) );
	}

	/**
	 * Convierte precio CRC a USD.
	 *
	 * @param float $precio_crc Precio en colones.
	 * @param float $tipo_cambio Tipo de cambio (CRC por 1 USD).
	 * @return float Precio en USD redondeado a 2 decimales.
	 */
	public static function convert_crc_to_usd( $precio_crc, $tipo_cambio ) {
		if ( $tipo_cambio <= 0 ) {
			return 0;
		}
		return round( $precio_crc / $tipo_cambio, 2 );
	}

	/**
	 * Sincroniza un producto individual: actualiza _regular_price y _price en USD.
	 *
	 * @param int   $product_id ID del producto o variación.
	 * @param float $precio_crc Precio en colones. Si 0, lo lee del meta.
	 */
	public static function sync_single_product( $product_id, $precio_crc = 0 ) {
		if ( $precio_crc <= 0 ) {
			$precio_crc = floatval( get_post_meta( $product_id, NLK_Product_Meta::META_KEY, true ) );
		}

		if ( $precio_crc <= 0 ) {
			return;
		}

		$tc = NLK_Exchange_Rate::get_active_rate();
		if ( $tc <= 0 ) {
			return;
		}

		$precio_usd = self::convert_crc_to_usd( $precio_crc, $tc );

		// Actualizar campos estándar de WooCommerce
		update_post_meta( $product_id, '_regular_price', $precio_usd );
		update_post_meta( $product_id, '_price', $precio_usd );

		// Si el producto tiene precio de oferta (sale), no lo tocamos.
		// El _price de WC ya se maneja: si hay _sale_price activo, WC usa ese.
		// Nosotros solo actualizamos _regular_price y _price base.

		// Limpiar cache de WooCommerce para este producto
		$product = wc_get_product( $product_id );
		if ( $product ) {
			wc_delete_product_transients( $product_id );

			// Si es variación, también limpiar el padre
			if ( $product->is_type( 'variation' ) ) {
				$parent_id = $product->get_parent_id();
				if ( $parent_id ) {
					wc_delete_product_transients( $parent_id );
				}
			}
		}
	}

	/**
	 * Actualización masiva de todos los productos que tengan _precio_crc.
	 *
	 * @return array Resultado con conteo de productos actualizados y errores.
	 */
	public static function sync_all_products() {
		$tc = NLK_Exchange_Rate::get_active_rate();

		if ( $tc <= 0 ) {
			return array(
				'success'  => false,
				'message'  => 'No hay tipo de cambio configurado.',
				'updated'  => 0,
				'skipped'  => 0,
				'tc_used'  => 0,
			);
		}

		global $wpdb;

		// Obtener todos los post IDs que tienen _precio_crc > 0
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, meta_value FROM {$wpdb->postmeta}
				 WHERE meta_key = %s AND meta_value > 0",
				NLK_Product_Meta::META_KEY
			)
		);

		$updated = 0;
		$skipped = 0;

		foreach ( $results as $row ) {
			$precio_crc = floatval( $row->meta_value );
			$product_id = intval( $row->post_id );

			if ( $precio_crc <= 0 ) {
				$skipped++;
				continue;
			}

			$precio_usd = self::convert_crc_to_usd( $precio_crc, $tc );

			update_post_meta( $product_id, '_regular_price', $precio_usd );
			update_post_meta( $product_id, '_price', $precio_usd );
			wc_delete_product_transients( $product_id );

			$updated++;
		}

		// Registrar última sincronización
		update_option( 'nlk_crc_usd_ultima_sincronizacion', current_time( 'mysql' ) );
		update_option( 'nlk_crc_usd_ultimo_tc', $tc );
		update_option( 'nlk_crc_usd_ultima_actualizacion_tc', current_time( 'mysql' ) );
		update_option( 'nlk_crc_usd_productos_actualizados', $updated );

		return array(
			'success' => true,
			'message' => sprintf( 'Sincronización completada. %d productos actualizados, %d omitidos.', $updated, $skipped ),
			'updated' => $updated,
			'skipped' => $skipped,
			'tc_used' => $tc,
		);
	}

	/**
	 * Handler AJAX para sincronización masiva manual.
	 */
	public static function ajax_sync_all() {
		check_ajax_referer( 'nlk_crc_usd_sync', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'No tiene permisos suficientes.' ) );
		}

		$result = self::sync_all_products();
		wp_send_json( $result );
	}
}
