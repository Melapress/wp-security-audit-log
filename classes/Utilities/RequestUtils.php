<?php

/**
 * Utility class for handling request inputs.
 *
 * @package Wsal
 * @since 4.1.4
 */
class WSAL_Utilities_RequestUtils {
	public static function get_filtered_request_data() {
		$result = [];

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
}