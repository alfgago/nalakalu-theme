<?php
/**
 * Campo personalizado de precio en colones (CRC) en productos WooCommerce.
 *
 * Agrega un campo meta '_precio_crc' en la pestaña General del producto.
 * Al guardar, convierte automáticamente a USD y actualiza el precio de WC.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NLK_Product_Meta {

	const META_KEY       = '_precio_crc';
	const FIXED_USD_KEY  = '_nlk_fixed_usd';

	public static function init() {
		// Campo en producto simple
		add_action( 'woocommerce_product_options_pricing', array( __CLASS__, 'render_crc_field' ) );
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save_crc_field' ) );

		// Campo en variaciones
		add_action( 'woocommerce_variation_options_pricing', array( __CLASS__, 'render_crc_field_variation' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( __CLASS__, 'save_crc_field_variation' ), 10, 2 );

		// Columna en listado de productos
		add_filter( 'manage_edit-product_columns', array( __CLASS__, 'add_crc_column' ) );
		add_action( 'manage_product_posts_custom_column', array( __CLASS__, 'render_crc_column' ), 10, 2 );
	}

	/**
	 * Renderiza el campo CRC en la pestaña General del producto simple.
	 */
	public static function render_crc_field() {
		$tc = NLK_Exchange_Rate::get_active_rate();
		$tc_display = $tc > 0 ? number_format( $tc, 2 ) : 'No configurado';

		echo '<div class="options_group">';

		woocommerce_wp_text_input( array(
			'id'                => self::META_KEY,
			'label'             => 'Precio en Colones (₡)',
			'description'       => sprintf( 'T/C venta activo: ₡%s por $1 USD', esc_html( $tc_display ) ),
			'desc_tip'          => true,
			'type'              => 'number',
			'custom_attributes' => array(
				'step' => '0.01',
				'min'  => '0',
			),
		) );

		woocommerce_wp_checkbox( array(
			'id'          => self::FIXED_USD_KEY,
			'label'       => 'Precio USD fijo',
			'description' => 'No sincronizar desde CRC — mantener el precio USD tal como está.',
		) );

		echo '</div>';
	}

	/**
	 * Guarda el precio CRC y sincroniza a USD al guardar producto simple.
	 */
	public static function save_crc_field( $post_id ) {
		if ( ! isset( $_POST[ self::META_KEY ] ) ) {
			return;
		}

		$precio_crc = floatval( sanitize_text_field( wp_unslash( $_POST[ self::META_KEY ] ) ) );
		update_post_meta( $post_id, self::META_KEY, $precio_crc );

		// Guardar checkbox de precio USD fijo
		$fixed = isset( $_POST[ self::FIXED_USD_KEY ] ) ? 'yes' : 'no';
		update_post_meta( $post_id, self::FIXED_USD_KEY, $fixed );

		// Solo sincronizar si no está marcado como USD fijo
		if ( $fixed !== 'yes' && $precio_crc > 0 ) {
			NLK_Price_Sync::sync_single_product( $post_id, $precio_crc );
		}
	}

	/**
	 * Renderiza el campo CRC en variaciones.
	 */
	public static function render_crc_field_variation( $loop, $variation_data, $variation ) {
		$value = get_post_meta( $variation->ID, self::META_KEY, true );
		$fixed = get_post_meta( $variation->ID, self::FIXED_USD_KEY, true );
		?>
		<div class="variable_pricing">
			<p class="form-row form-row-first">
				<label><?php esc_html_e( 'Precio en Colones (₡)', 'nalakalu' ); ?></label>
				<input type="number"
					name="<?php echo esc_attr( self::META_KEY . '_variation[' . $loop . ']' ); ?>"
					value="<?php echo esc_attr( $value ); ?>"
					step="0.01" min="0"
					class="short" />
			</p>
			<p class="form-row form-row-last">
				<label>
					<input type="checkbox"
						name="<?php echo esc_attr( self::FIXED_USD_KEY . '_variation[' . $loop . ']' ); ?>"
						value="yes" <?php checked( $fixed, 'yes' ); ?> />
					<?php esc_html_e( 'Precio USD fijo', 'nalakalu' ); ?>
				</label>
			</p>
		</div>
		<?php
	}

	/**
	 * Guarda el precio CRC de una variación y sincroniza a USD.
	 */
	public static function save_crc_field_variation( $variation_id, $loop ) {
		$field_name = self::META_KEY . '_variation';
		if ( ! isset( $_POST[ $field_name ][ $loop ] ) ) {
			return;
		}

		$precio_crc = floatval( sanitize_text_field( wp_unslash( $_POST[ $field_name ][ $loop ] ) ) );
		update_post_meta( $variation_id, self::META_KEY, $precio_crc );

		// Guardar checkbox de precio USD fijo para variación
		$fixed_field = self::FIXED_USD_KEY . '_variation';
		$fixed = isset( $_POST[ $fixed_field ][ $loop ] ) ? 'yes' : 'no';
		update_post_meta( $variation_id, self::FIXED_USD_KEY, $fixed );

		if ( $fixed !== 'yes' && $precio_crc > 0 ) {
			NLK_Price_Sync::sync_single_product( $variation_id, $precio_crc );
		}
	}

	/**
	 * Agrega columna "Precio CRC" al listado de productos.
	 */
	public static function add_crc_column( $columns ) {
		$new_columns = array();
		foreach ( $columns as $key => $label ) {
			$new_columns[ $key ] = $label;
			if ( $key === 'price' ) {
				$new_columns['precio_crc'] = 'Precio CRC';
			}
		}
		return $new_columns;
	}

	/**
	 * Renderiza el valor de la columna CRC.
	 */
	public static function render_crc_column( $column, $post_id ) {
		if ( $column !== 'precio_crc' ) {
			return;
		}

		$fixed = get_post_meta( $post_id, self::FIXED_USD_KEY, true );
		if ( $fixed === 'yes' ) {
			echo '<span title="Este producto usa precio USD fijo">USD fijo</span>';
			return;
		}

		$precio = get_post_meta( $post_id, self::META_KEY, true );
		if ( $precio ) {
			echo '₡' . esc_html( number_format( floatval( $precio ), 2 ) );
		} else {
			echo '—';
		}
	}
}
