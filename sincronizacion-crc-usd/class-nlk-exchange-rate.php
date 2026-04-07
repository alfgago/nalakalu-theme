<?php
/**
 * Consulta tipo de cambio del BCCR con cache en transients.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NLK_Exchange_Rate {

	/** Indicador BCCR: tipo de cambio venta del dólar */
	const INDICATOR_VENTA  = '318';
	const INDICATOR_COMPRA = '317';

	/** Transient keys */
	const TRANSIENT_KEY          = 'nlk_crc_usd_bccr_rate';
	const TRANSIENT_FALLBACK_KEY = 'nlk_crc_usd_bccr_rate_fallback';

	/** Duración del cache: 12 horas */
	const CACHE_EXPIRATION = 12 * HOUR_IN_SECONDS;

	/** Fallback: 30 días */
	const FALLBACK_EXPIRATION = 30 * DAY_IN_SECONDS;

	public static function init() {
		// Nada que hookear por ahora; se usa bajo demanda.
	}

	/**
	 * Obtiene el tipo de cambio activo según la configuración.
	 *
	 * @return float Tipo de cambio venta (CRC por 1 USD). 0 si no disponible.
	 */
	public static function get_active_rate() {
		$modo = get_option( 'nlk_crc_usd_modo', 'manual' );

		if ( $modo === 'auto' ) {
			$rate = self::fetch_bccr_venta();
			if ( $rate > 0 ) {
				update_option( 'nlk_crc_usd_ultimo_tc', $rate );
				update_option( 'nlk_crc_usd_ultima_actualizacion_tc', current_time( 'mysql' ) );
				return $rate;
			}
			// Fallback al manual si la API falla
			return floatval( get_option( 'nlk_crc_usd_tipo_cambio_manual', 0 ) );
		}

		// Modo manual
		return floatval( get_option( 'nlk_crc_usd_tipo_cambio_manual', 0 ) );
	}

	/**
	 * Consulta el tipo de cambio de VENTA del BCCR para hoy.
	 * Usa transient para no bombardear la API.
	 *
	 * @return float Tipo de cambio venta. 0 si falla.
	 */
	public static function fetch_bccr_venta() {
		$cached = get_transient( self::TRANSIENT_KEY );
		if ( $cached !== false ) {
			return floatval( $cached );
		}

		$date = current_time( 'd/m/Y' );
		$rate = self::call_bccr_api( self::INDICATOR_VENTA, $date );

		if ( $rate > 0 ) {
			set_transient( self::TRANSIENT_KEY, $rate, self::CACHE_EXPIRATION );
			set_transient( self::TRANSIENT_FALLBACK_KEY, $rate, self::FALLBACK_EXPIRATION );
			return $rate;
		}

		// Intentar fallback
		$fallback = get_transient( self::TRANSIENT_FALLBACK_KEY );
		if ( $fallback !== false ) {
			return floatval( $fallback );
		}

		return 0;
	}

	/**
	 * Obtiene tipo de cambio de COMPRA del BCCR (informativo).
	 *
	 * @return float
	 */
	public static function fetch_bccr_compra() {
		$cache_key = 'nlk_crc_usd_bccr_compra';
		$cached    = get_transient( $cache_key );
		if ( $cached !== false ) {
			return floatval( $cached );
		}

		$date = current_time( 'd/m/Y' );
		$rate = self::call_bccr_api( self::INDICATOR_COMPRA, $date );

		if ( $rate > 0 ) {
			set_transient( $cache_key, $rate, self::CACHE_EXPIRATION );
		}

		return $rate;
	}

	/**
	 * Fuerza refrescar el tipo de cambio borrando transients.
	 *
	 * @return float Nuevo tipo de cambio venta.
	 */
	public static function force_refresh() {
		delete_transient( self::TRANSIENT_KEY );
		delete_transient( 'nlk_crc_usd_bccr_compra' );
		return self::fetch_bccr_venta();
	}

	/**
	 * Llama al webservice del BCCR.
	 *
	 * @param string $indicator Código del indicador (317=compra, 318=venta).
	 * @param string $date      Fecha en formato dd/mm/YYYY.
	 * @return float Valor del indicador. 0 si falla.
	 */
	private static function call_bccr_api( $indicator, $date ) {
		$response = wp_remote_post(
			'https://gee.bccr.fi.cr/Indicadores/Suscripciones/WS/wsindicadoreseconomicos.asmx/ObtenerIndicadoresEconomicos',
			array(
				'body'    => array(
					'Indicador'         => $indicator,
					'FechaInicio'       => $date,
					'FechaFinal'        => $date,
					'SubNiveles'        => 'S',
					'Nombre'            => 'Nalakalu',
					'CorreoElectronico' => 'info@nalakalu.com',
					'Token'             => 'BE26A0TU1L',
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return 0;
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			return 0;
		}

		$xml = @simplexml_load_string( $body );
		if ( $xml === false ) {
			return 0;
		}

		$values = $xml->xpath( '//NUM_VALOR' );
		if ( empty( $values ) ) {
			return 0;
		}

		return (float) str_replace( ',', '.', (string) $values[0] );
	}
}
