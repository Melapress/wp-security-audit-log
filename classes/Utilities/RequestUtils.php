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
}