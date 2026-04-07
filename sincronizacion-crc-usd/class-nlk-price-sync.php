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
		add_action( 'wp_ajax_nlk_crc_usd_backfill', array( __CLASS__, 'ajax_backfill' ) );
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
		// Respetar flag de precio USD fijo
		if ( get_post_meta( $product_id, NLK_Product_Meta::FIXED_USD_KEY, true ) === 'yes' ) {
			return;
		}

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

		// NUNCA escribir precio USD en 0 — proteger el precio existente
		if ( $precio_usd <= 0 ) {
			return;
		}

		// Actualizar campos estándar de WooCommerce
		update_post_meta( $product_id, '_regular_price', $precio_usd );
		update_post_meta( $product_id, '_price', $precio_usd );

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

			// Respetar flag de precio USD fijo
			if ( get_post_meta( $product_id, NLK_Product_Meta::FIXED_USD_KEY, true ) === 'yes' ) {
				$skipped++;
				continue;
			}

			$precio_usd = self::convert_crc_to_usd( $precio_crc, $tc );

			// NUNCA escribir precio USD en 0 — proteger el precio existente
			if ( $precio_usd <= 0 ) {
				$skipped++;
				continue;
			}

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

	/**
	 * Carga inicial: calcula _precio_crc a partir de precios USD existentes.
	 *
	 * Solo afecta productos que tienen _price pero NO tienen _precio_crc.
	 * Fórmula inversa: CRC = USD × tipo_cambio
	 *
	 * @return array Resultado con conteo.
	 */
	public static function backfill_crc_from_usd() {
		$tc = NLK_Exchange_Rate::get_active_rate();

		if ( $tc <= 0 ) {
			return array(
				'success' => false,
				'message' => 'No hay tipo de cambio configurado. Defínalo antes de ejecutar la carga inicial.',
				'filled'  => 0,
				'skipped' => 0,
			);
		}

		global $wpdb;

		// Productos/variaciones que tienen _price pero NO tienen _precio_crc (o es 0/vacío)
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.post_id, pm.meta_value AS usd_price
				 FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key = '_price'
				   AND pm.meta_value > 0
				   AND p.post_type IN ('product', 'product_variation')
				   AND p.post_status IN ('publish', 'draft', 'private')
				   AND pm.post_id NOT IN (
				       SELECT post_id FROM {$wpdb->postmeta}
				       WHERE meta_key = %s AND meta_value > 0
				   )",
				NLK_Product_Meta::META_KEY
			)
		);

		$filled  = 0;
		$skipped = 0;

		foreach ( $results as $row ) {
			$product_id = intval( $row->post_id );
			$usd_price  = floatval( $row->usd_price );

			if ( $usd_price <= 0 ) {
				$skipped++;
				continue;
			}

			// Saltar productos marcados como USD fijo
			if ( get_post_meta( $product_id, NLK_Product_Meta::FIXED_USD_KEY, true ) === 'yes' ) {
				$skipped++;
				continue;
			}

			$precio_crc = round( $usd_price * $tc, 2 );
			update_post_meta( $product_id, NLK_Product_Meta::META_KEY, $precio_crc );

			$filled++;
		}

		return array(
			'success' => true,
			'message' => sprintf(
				'Carga inicial completada. %d productos rellenados con precio CRC (T/C: ₡%s), %d omitidos.',
				$filled,
				number_format( $tc, 2 ),
				$skipped
			),
			'filled'  => $filled,
			'skipped' => $skipped,
			'tc_used' => $tc,
		);
	}

	/**
	 * Handler AJAX para backfill.
	 */
	public static function ajax_backfill() {
		check_ajax_referer( 'nlk_crc_usd_sync', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'No tiene permisos suficientes.' ) );
		}

		$result = self::backfill_crc_from_usd();
		wp_send_json( $result );
	}
}
