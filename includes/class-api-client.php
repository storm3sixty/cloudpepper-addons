<?php
/**
 * Tradeprint API client.
 *
 * @package TradeprintConfigurator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API client.
 */
class TPCW_API_Client {

	/**
	 * Sandbox base URL.
	 */
	const SANDBOX_BASE_URL = 'https://sandbox.orders.tradeprint.io';

	/**
	 * Live base URL.
	 */
	const LIVE_BASE_URL = 'https://orders.tradeprint.io';

	/**
	 * Perform authenticated JSON request.
	 *
	 * @param string $method Method.
	 * @param string $path Path.
	 * @param array  $body Body.
	 *
	 * @return array|WP_Error
	 */
	public function request( $method, $path, $body = array() ) {
		$settings = get_option( TPCW_Loader::OPTION_KEY, array() );
		$token    = isset( $settings['api_bearer_token'] ) ? trim( (string) $settings['api_bearer_token'] ) : '';
		if ( '' === $token ) {
			return new WP_Error( 'tpcw_missing_token', __( 'Tradeprint bearer token is missing. Please configure plugin settings.', 'tradeprint-configurator' ) );
		}

		$base_url = 'yes' === ( isset( $settings['sandbox_enabled'] ) ? $settings['sandbox_enabled'] : 'no' ) ? self::SANDBOX_BASE_URL : self::LIVE_BASE_URL;
		$url      = untrailingslashit( $base_url ) . '/' . ltrim( (string) $path, '/' );
		$method   = strtoupper( sanitize_text_field( $method ) );

		$args = array(
			'method'  => $method,
			'timeout' => 30,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
			),
		);

		if ( in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			$args['body'] = wp_json_encode( is_array( $body ) ? $body : array() );
		}

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			TPCW_Logger::log( 'error', 'Tradeprint request failed', array(
				'path'   => $path,
				'method' => $method,
				'error'  => $response->get_error_message(),
			) );
			return new WP_Error( 'tpcw_http_error', $response->get_error_message() );
		}

		$status = wp_remote_retrieve_response_code( $response );
		$raw    = wp_remote_retrieve_body( $response );
		$data   = json_decode( (string) $raw, true );
		if ( '' !== (string) $raw && ! is_array( $data ) ) {
			TPCW_Logger::log( 'error', 'Tradeprint invalid JSON response', array(
				'path'   => $path,
				'method' => $method,
				'status' => $status,
			) );
			return new WP_Error( 'tpcw_invalid_json', __( 'Tradeprint API returned invalid JSON.', 'tradeprint-configurator' ) );
		}

		TPCW_Logger::log( 'info', 'Tradeprint API request', array(
			'path'    => $path,
			'method'  => $method,
			'status'  => $status,
			'success' => is_array( $data ) && isset( $data['success'] ) ? ( $data['success'] ? 'true' : 'false' ) : 'n/a',
		) );

		if ( $status < 200 || $status >= 300 ) {
			$message = is_array( $data ) && ! empty( $data['message'] ) ? sanitize_text_field( $data['message'] ) : __( 'Tradeprint API request failed.', 'tradeprint-configurator' );
			return new WP_Error( 'tpcw_api_error', $message, array( 'status' => $status, 'response' => $data ) );
		}

		if ( is_array( $data ) && isset( $data['success'] ) && false === $data['success'] ) {
			$message = ! empty( $data['message'] ) ? sanitize_text_field( $data['message'] ) : __( 'Tradeprint API returned unsuccessful response.', 'tradeprint-configurator' );
			return new WP_Error( 'tpcw_api_unsuccessful', $message, array( 'status' => $status, 'response' => $data ) );
		}

		return array(
			'status' => $status,
			'body'   => is_array( $data ) ? $data : array(),
		);
	}
}
