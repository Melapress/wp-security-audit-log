<?php
/**
 * Utility class for handling request inputs.
 *
 * @package wsal
 * @since 4.1.4
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Utility class for handling request inputs.
 *
 * @package wsal
 * @since 4.1.4
 */
class WSAL_Utilities_RequestUtils {

	/**
	 * Filters request data.
	 *
	 * @return array Filtered request data.
	 */
	public static function get_filtered_request_data() {
		$result = array();

		$get_data = filter_input_array( INPUT_GET );
		if ( is_array( $get_data ) ) {
			$result = array_merge( $result, $get_data );
		}

		$post_data = filter_input_array( INPUT_POST );
		if ( is_array( $post_data ) ) {
			$result = array_merge( $result, $post_data );
		}

		return $result;
	}

	/**
	 * Simple check for validating a URL, it must start with http:// or https://.
	 * and pass FILTER_VALIDATE_URL validation.
	 *
	 * @param string $url to check.
	 *
	 * @return bool
	 *
	 * @since 4.2.1
	 */
	public static function is_valid_url( $url ) {
		// Must start with http:// or https://.
		if ( 0 !== strpos( $url, 'http://' ) && 0 !== strpos( $url, 'https://' ) ) {
			return false;
		}

		// Must pass validation.
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if the float is IPv4 instead.
	 *
	 * @param float $ip_address - Number to check.
	 *
	 * @return bool result validation
	 */
	public static function is_ip_address( $ip_address ) {
		return filter_var( $ip_address, FILTER_VALIDATE_IP ) !== false;
	}
}
