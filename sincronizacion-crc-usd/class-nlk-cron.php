<?php
/**
 * Manejo de WP-Cron para actualización programada de precios CRC x USD.
 *
 * Registra un evento cron que ejecuta la sincronización masiva
 * según la frecuencia configurada en el admin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NLK_Cron {

	const HOOK_NAME = 'nlk_crc_usd_sync_cron';

	public static function init() {
		// Registrar intervalo personalizado "weekly"
		add_filter( 'cron_schedules', array( __CLASS__, 'add_weekly_schedule' ) );

		// Ejecutar sincronización cuando el cron dispara
		add_action( self::HOOK_NAME, array( __CLASS__, 'run_sync' ) );

		// Re-programar cron cuando se guardan las opciones
		add_action( 'update_option_nlk_crc_usd_frecuencia', array( __CLASS__, 'reschedule' ) );
		add_action( 'update_option_nlk_crc_usd_modo', array( __CLASS__, 'reschedule' ) );

		// Programar en activación del tema (si no existe)
		add_action( 'after_setup_theme', array( __CLASS__, 'maybe_schedule' ) );
	}

	/**
	 * Agrega intervalo semanal si no existe.
	 */
	public static function add_weekly_schedule( $schedules ) {
		if ( ! isset( $schedules['weekly'] ) ) {
			$schedules['weekly'] = array(
				'interval' => 7 * DAY_IN_SECONDS,
				'display'  => 'Una vez por semana',
			);
		}
		return $schedules;
	}

	/**
	 * Programa el cron si no está programado.
	 */
	public static function maybe_schedule() {
		if ( get_option( 'nlk_crc_usd_modo', 'manual' ) !== 'auto' ) {
			self::unschedule();
			return;
		}

		if ( ! wp_next_scheduled( self::HOOK_NAME ) ) {
			$frecuencia = get_option( 'nlk_crc_usd_frecuencia', 'daily' );
			wp_schedule_event( time(), $frecuencia, self::HOOK_NAME );
		}
	}

	/**
	 * Re-programa el cron al cambiar la frecuencia.
	 */
	public static function reschedule() {
		// Limpiar evento existente
		$timestamp = wp_next_scheduled( self::HOOK_NAME );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::HOOK_NAME );
		}

		if ( get_option( 'nlk_crc_usd_modo', 'manual' ) !== 'auto' ) {
			return;
		}

		// Re-programar con nueva frecuencia
		$frecuencia = get_option( 'nlk_crc_usd_frecuencia', 'daily' );
		wp_schedule_event( time(), $frecuencia, self::HOOK_NAME );
	}

	/**
	 * Ejecuta la sincronización masiva (callback del cron).
	 */
	public static function run_sync() {
		$result = NLK_Price_Sync::sync_all_products();

		// Log básico
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf(
				'[NLK CRC→USD Cron] %s | TC: %s | Actualizados: %d | Omitidos: %d',
				$result['success'] ? 'OK' : 'ERROR',
				$result['tc_used'],
				$result['updated'],
				$result['skipped']
			) );
		}
	}

	/**
	 * Limpia el cron al desactivar el tema.
	 */
	public static function unschedule() {
		$timestamp = wp_next_scheduled( self::HOOK_NAME );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::HOOK_NAME );
		}
	}
}
