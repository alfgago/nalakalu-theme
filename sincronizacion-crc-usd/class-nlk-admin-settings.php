<?php
/**
 * Página de configuración administrativa para sincronización CRC → USD.
 *
 * Se agrega como submenú de WooCommerce.
 * Permite: configurar modo (auto/manual), tipo de cambio manual,
 * frecuencia del cron, ver logs, y ejecutar sincronización manual.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NLK_Admin_Settings {

	const PAGE_SLUG = 'nlk-crc-usd';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_actions' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
	}

	/**
	 * Agrega la página bajo el menú de WooCommerce.
	 */
	public static function add_menu_page() {
		add_submenu_page(
			'woocommerce',
			'Sincronización CRC → USD',
			'CRC → USD',
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Registra las opciones de configuración.
	 */
	public static function register_settings() {
		register_setting( 'nlk_crc_usd_settings', 'nlk_crc_usd_modo', array(
			'type'              => 'string',
			'default'           => 'manual',
			'sanitize_callback' => function( $val ) {
				return in_array( $val, array( 'auto', 'manual' ), true ) ? $val : 'manual';
			},
		) );

		register_setting( 'nlk_crc_usd_settings', 'nlk_crc_usd_tipo_cambio_manual', array(
			'type'              => 'number',
			'default'           => 0,
			'sanitize_callback' => function( $val ) {
				return max( 0, floatval( $val ) );
			},
		) );

		register_setting( 'nlk_crc_usd_settings', 'nlk_crc_usd_frecuencia', array(
			'type'              => 'string',
			'default'           => 'daily',
			'sanitize_callback' => function( $val ) {
				$valid = array( 'hourly', 'twicedaily', 'daily', 'weekly' );
				return in_array( $val, $valid, true ) ? $val : 'daily';
			},
		) );
	}

	/**
	 * Maneja acciones manuales (refrescar TC, sincronizar todo).
	 */
	public static function handle_actions() {
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== self::PAGE_SLUG ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// Refrescar tipo de cambio desde BCCR
		if ( isset( $_GET['nlk_action'] ) && $_GET['nlk_action'] === 'refresh_tc' ) {
			check_admin_referer( 'nlk_crc_usd_refresh_tc' );
			$rate = NLK_Exchange_Rate::force_refresh();
			if ( $rate > 0 ) {
				add_settings_error( 'nlk_crc_usd', 'tc_refreshed',
					sprintf( 'Tipo de cambio actualizado: ₡%s por $1 USD', number_format( $rate, 2 ) ),
					'success'
				);
			} else {
				add_settings_error( 'nlk_crc_usd', 'tc_error',
					'No se pudo obtener el tipo de cambio del BCCR. Verifique la conexión.',
					'error'
				);
			}
		}

		// Sincronización masiva
		if ( isset( $_GET['nlk_action'] ) && $_GET['nlk_action'] === 'sync_all' ) {
			check_admin_referer( 'nlk_crc_usd_sync_all' );
			$result = NLK_Price_Sync::sync_all_products();
			if ( $result['success'] ) {
				add_settings_error( 'nlk_crc_usd', 'sync_ok', $result['message'], 'success' );
			} else {
				add_settings_error( 'nlk_crc_usd', 'sync_error', $result['message'], 'error' );
			}
		}
	}

	/**
	 * Enqueue scripts solo en nuestra página.
	 */
	public static function enqueue_scripts( $hook ) {
		if ( strpos( $hook, self::PAGE_SLUG ) === false ) {
			return;
		}

		wp_enqueue_script(
			'nlk-crc-usd-admin',
			get_template_directory_uri() . '/sincronizacion-crc-usd/admin.js',
			array( 'jquery' ),
			filemtime( NLK_CRC_USD_PATH . '/admin.js' ),
			true
		);

		wp_localize_script( 'nlk-crc-usd-admin', 'nlkCrcUsd', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'nlk_crc_usd_sync' ),
		) );
	}

	/**
	 * Renderiza la página de configuración.
	 */
	public static function render_page() {
		$modo            = get_option( 'nlk_crc_usd_modo', 'manual' );
		$tc_manual       = get_option( 'nlk_crc_usd_tipo_cambio_manual', '' );
		$frecuencia      = get_option( 'nlk_crc_usd_frecuencia', 'daily' );
		$ultimo_tc       = get_option( 'nlk_crc_usd_ultimo_tc', '' );
		$ultima_act_tc   = get_option( 'nlk_crc_usd_ultima_actualizacion_tc', 'Nunca' );
		$ultima_sync     = get_option( 'nlk_crc_usd_ultima_sincronizacion', 'Nunca' );
		$productos_act   = get_option( 'nlk_crc_usd_productos_actualizados', 0 );

		// Intentar obtener TC actual del BCCR para mostrar
		$tc_bccr_venta  = NLK_Exchange_Rate::fetch_bccr_venta();
		$tc_bccr_compra = NLK_Exchange_Rate::fetch_bccr_compra();

		$refresh_url = wp_nonce_url(
			admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&nlk_action=refresh_tc' ),
			'nlk_crc_usd_refresh_tc'
		);

		$sync_url = wp_nonce_url(
			admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&nlk_action=sync_all' ),
			'nlk_crc_usd_sync_all'
		);

		?>
		<div class="wrap">
			<h1>Sincronización CRC → USD</h1>

			<?php settings_errors( 'nlk_crc_usd' ); ?>

			<!-- Panel informativo -->
			<div class="card" style="max-width:700px;margin-bottom:20px;padding:15px 20px;">
				<h2 style="margin-top:0;">Estado actual</h2>
				<table class="widefat striped" style="max-width:600px;">
					<tbody>
						<tr>
							<th>Tipo de cambio BCCR (venta)</th>
							<td><?php echo $tc_bccr_venta > 0 ? '₡' . esc_html( number_format( $tc_bccr_venta, 2 ) ) : '<em>No disponible</em>'; ?></td>
						</tr>
						<tr>
							<th>Tipo de cambio BCCR (compra)</th>
							<td><?php echo $tc_bccr_compra > 0 ? '₡' . esc_html( number_format( $tc_bccr_compra, 2 ) ) : '<em>No disponible</em>'; ?></td>
						</tr>
						<tr>
							<th>Tipo de cambio activo</th>
							<td><strong><?php echo $ultimo_tc ? '₡' . esc_html( number_format( floatval( $ultimo_tc ), 2 ) ) : 'No configurado'; ?></strong></td>
						</tr>
						<tr>
							<th>Modo</th>
							<td><?php echo $modo === 'auto' ? 'Automático (BCCR)' : 'Manual'; ?></td>
						</tr>
						<tr>
							<th>Última actualización de T/C</th>
							<td><?php echo esc_html( $ultima_act_tc ); ?></td>
						</tr>
						<tr>
							<th>Última sincronización masiva</th>
							<td><?php echo esc_html( $ultima_sync ); ?></td>
						</tr>
						<tr>
							<th>Productos actualizados (última vez)</th>
							<td><?php echo esc_html( $productos_act ); ?></td>
						</tr>
					</tbody>
				</table>

				<p style="margin-top:15px;">
					<a href="<?php echo esc_url( $refresh_url ); ?>" class="button">
						Refrescar T/C desde BCCR
					</a>
					<a href="<?php echo esc_url( $sync_url ); ?>" class="button button-primary" style="margin-left:10px;"
					   onclick="return confirm('¿Actualizar todos los precios USD con el tipo de cambio activo?');">
						Sincronizar todos los productos
					</a>
				</p>
			</div>

			<!-- Formulario de configuración -->
			<form method="post" action="options.php">
				<?php settings_fields( 'nlk_crc_usd_settings' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Modo de tipo de cambio</th>
						<td>
							<fieldset>
								<label>
									<input type="radio" name="nlk_crc_usd_modo" value="manual" <?php checked( $modo, 'manual' ); ?> />
									<strong>Manual</strong> — Definir tipo de cambio fijo manualmente
								</label>
								<br/>
								<label>
									<input type="radio" name="nlk_crc_usd_modo" value="auto" <?php checked( $modo, 'auto' ); ?> />
									<strong>Automático</strong> — Obtener del Banco Central de Costa Rica
								</label>
								<p class="description">
									En modo automático, si la API del BCCR no responde, se usará el tipo de cambio manual como respaldo.
								</p>
							</fieldset>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="nlk_crc_usd_tipo_cambio_manual">Tipo de cambio manual (₡ por $1)</label>
						</th>
						<td>
							<input type="number" id="nlk_crc_usd_tipo_cambio_manual"
								   name="nlk_crc_usd_tipo_cambio_manual"
								   value="<?php echo esc_attr( $tc_manual ); ?>"
								   step="0.01" min="0" class="regular-text" />
							<p class="description">
								Ej: 530.50 significa que $1 USD = ₡530.50. Se usa en modo manual o como respaldo del modo automático.
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">Frecuencia de actualización automática</th>
						<td>
							<select name="nlk_crc_usd_frecuencia">
								<option value="hourly" <?php selected( $frecuencia, 'hourly' ); ?>>Cada hora</option>
								<option value="twicedaily" <?php selected( $frecuencia, 'twicedaily' ); ?>>Dos veces al día</option>
								<option value="daily" <?php selected( $frecuencia, 'daily' ); ?>>Diaria</option>
								<option value="weekly" <?php selected( $frecuencia, 'weekly' ); ?>>Semanal</option>
							</select>
							<p class="description">
								Con qué frecuencia el cron actualizará automáticamente los precios USD de todos los productos.
							</p>
						</td>
					</tr>
				</table>

				<?php submit_button( 'Guardar configuración' ); ?>
			</form>

			<!-- Simulador rápido -->
			<div class="card" style="max-width:700px;margin-top:20px;padding:15px 20px;">
				<h2 style="margin-top:0;">Simulador de conversión</h2>
				<p>
					<label for="nlk-sim-crc"><strong>Precio en colones:</strong></label><br/>
					<input type="number" id="nlk-sim-crc" step="0.01" min="0" style="width:200px;" placeholder="Ej: 25000" />
					<button type="button" class="button" id="nlk-sim-btn">Calcular</button>
				</p>
				<p id="nlk-sim-result" style="font-size:1.1em;"></p>
			</div>
		</div>
		<?php
	}
}
