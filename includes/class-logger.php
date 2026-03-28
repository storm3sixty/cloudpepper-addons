<?php
/**
 * Lightweight plugin logger.
 *
 * @package TradeprintConfigurator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Logger utility.
 */
class TPCW_Logger {

	/**
	 * Option key.
	 */
	const OPTION_KEY = 'tpcw_logs';

	/**
	 * Max entries.
	 */
	const MAX_ENTRIES = 200;

	/**
	 * Write a log entry when debug logging is enabled.
	 *
	 * @param string $level Level.
	 * @param string $message Message.
	 * @param array  $context Safe context.
	 *
	 * @return void
	 */
	public static function log( $level, $message, $context = array() ) {
		$settings = get_option( TPCW_Loader::OPTION_KEY, array() );
		if ( 'yes' !== ( isset( $settings['enable_debug_logging'] ) ? $settings['enable_debug_logging'] : 'no' ) ) {
			return;
		}

		$entries = self::get_logs();
		$entries[] = array(
			'timestamp' => current_time( 'mysql' ),
			'level'     => sanitize_key( $level ),
			'message'   => sanitize_text_field( $message ),
			'context'   => self::sanitize_context( $context ),
		);

		if ( count( $entries ) > self::MAX_ENTRIES ) {
			$entries = array_slice( $entries, -1 * self::MAX_ENTRIES );
		}

		update_option( self::OPTION_KEY, $entries, false );
	}

	/**
	 * Return all logs.
	 *
	 * @return array
	 */
	public static function get_logs() {
		$entries = get_option( self::OPTION_KEY, array() );
		return is_array( $entries ) ? $entries : array();
	}

	/**
	 * Clear logs.
	 *
	 * @return void
	 */
	public static function clear_logs() {
		update_option( self::OPTION_KEY, array(), false );
	}

	/**
	 * Sanitize context recursively.
	 *
	 * @param mixed $context Context.
	 *
	 * @return mixed
	 */
	private static function sanitize_context( $context ) {
		if ( is_array( $context ) ) {
			$clean = array();
			foreach ( $context as $key => $value ) {
				$clean_key          = is_string( $key ) ? sanitize_key( $key ) : $key;
				$clean[ $clean_key ] = self::sanitize_context( $value );
			}
			return $clean;
		}

		if ( is_scalar( $context ) || null === $context ) {
			return sanitize_text_field( (string) $context );
		}

		return '';
	}
}
