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
	 * Fuerza refrescar con debug completo para diagnóstico.
	 *
	 * @return array { rate: float, debug: string[] }
	 */
	public static function force_refresh_debug() {
		delete_transient( self::TRANSIENT_KEY );
		delete_transient( 'nlk_crc_usd_bccr_compra' );

		$date = current_time( 'd/m/Y' );
		$result = self::call_bccr_api_debug( self::INDICATOR_VENTA, $date );

		if ( $result['rate'] > 0 ) {
			set_transient( self::TRANSIENT_KEY, $result['rate'], self::CACHE_EXPIRATION );
			set_transient( self::TRANSIENT_FALLBACK_KEY, $result['rate'], self::FALLBACK_EXPIRATION );
		}

		return $result;
	}

	/**
	 * Llama al webservice del BCCR.
	 *
	 * @param string $indicator Código del indicador (317=compra, 318=venta).
	 * @param string $date      Fecha en formato dd/mm/YYYY.
	 * @return float Valor del indicador. 0 si falla.
	 */
	private static function call_bccr_api( $indicator, $date ) {
		$result = self::call_bccr_api_debug( $indicator, $date );
		return $result['rate'];
	}

	/**
	 * Llama al webservice del BCCR con info de debug.
	 *
	 * @param string $indicator Código del indicador.
	 * @param string $date      Fecha en formato dd/mm/YYYY.
	 * @return array { rate: float, debug: string[] }
	 */
	private static function call_bccr_api_debug( $indicator, $date ) {
		$debug = array();
		$url   = 'https://gee.bccr.fi.cr/Indicadores/Suscripciones/WS/wsindicadoreseconomicos.asmx/ObtenerIndicadoresEconomicos';
		$body  = array(
			'Indicador'         => $indicator,
			'FechaInicio'       => $date,
			'FechaFinal'        => $date,
			'SubNiveles'        => 'S',
			'Nombre'            => 'Nalakalu',
			'CorreoElectronico' => 'info@nalakalu.com',
			'Token'             => 'BE26A0TU1L',
		);

		$debug[] = sprintf( 'URL: %s', $url );
		$debug[] = sprintf( 'Indicador: %s (%s)', $indicator, $indicator === '318' ? 'venta' : 'compra' );
		$debug[] = sprintf( 'Fecha: %s', $date );
		$debug[] = sprintf( 'Params: %s', wp_json_encode( $body ) );

		$response = wp_remote_post( $url, array(
			'body'    => $body,
			'timeout' => 15,
		) );

		if ( is_wp_error( $response ) ) {
			$debug[] = sprintf( 'ERROR wp_remote_post: %s', $response->get_error_message() );
			return array( 'rate' => 0, 'debug' => $debug );
		}

		$http_code = wp_remote_retrieve_response_code( $response );
		$debug[] = sprintf( 'HTTP status: %d', $http_code );

		if ( $http_code !== 200 ) {
			$debug[] = sprintf( 'Response body (primeros 500 chars): %s', substr( wp_remote_retrieve_body( $response ), 0, 500 ) );
			return array( 'rate' => 0, 'debug' => $debug );
		}

		$response_body = wp_remote_retrieve_body( $response );
		$debug[] = sprintf( 'Response length: %d bytes', strlen( $response_body ) );

		if ( empty( $response_body ) ) {
			$debug[] = 'ERROR: Body vacío';
			return array( 'rate' => 0, 'debug' => $debug );
		}

		$debug[] = sprintf( 'Response body (primeros 1000 chars): %s', substr( $response_body, 0, 1000 ) );

		$xml = @simplexml_load_string( $response_body );
		if ( $xml === false ) {
			$debug[] = 'ERROR: No se pudo parsear XML';
			$xml_errors = libxml_get_errors();
			foreach ( $xml_errors as $err ) {
				$debug[] = sprintf( 'XML error: %s (línea %d)', trim( $err->message ), $err->line );
			}
			libxml_clear_errors();
			return array( 'rate' => 0, 'debug' => $debug );
		}

		$debug[] = 'XML parseado OK';

		$values = $xml->xpath( '//NUM_VALOR' );
		if ( empty( $values ) ) {
			$debug[] = 'ERROR: No se encontró //NUM_VALOR en el XML';
			// Mostrar todos los nodos para diagnóstico
			$debug[] = sprintf( 'Nodos raíz: %s', implode( ', ', array_keys( (array) $xml ) ) );
			return array( 'rate' => 0, 'debug' => $debug );
		}

		$raw_value = (string) $values[0];
		$rate      = (float) str_replace( ',', '.', $raw_value );
		$debug[]   = sprintf( 'NUM_VALOR raw: "%s" → float: %s', $raw_value, $rate );

		return array( 'rate' => $rate, 'debug' => $debug );
	}
}
