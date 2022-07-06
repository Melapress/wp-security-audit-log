<?php
/**
 * Class: Helper responsible about multiple steps usually used as a batch.
 *
 * Helper class used for validating data.
 *
 * @package wsal
 */

declare(strict_types=1);

namespace WSAL\Helpers;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\Helpers\PHP_Helper' ) ) {
	/**
	 * Responsible for data validation
	 */
	class PHP_Helper {

        /**
         * Use this method if you need to split string by comma, remove spaces (leading and trailing), remove empty elements from the array and.
         *
         * @param string $string - The string to split.
         *
         * @return array
         *
         * @since      4.4.2.1
         */
        public static function string_to_array( string $string ): array {
			if ( empty( $string ) ) {
				return array();
			}

            $split_string = explode( ',', $string );

            // Remove the whitespaces.
            $split_string = array_map( 'trim', $split_string );

            // Removes empty elements.
            $split_string = array_filter( $split_string );

            if ( ! is_array( $split_string ) ) {
                return array();
            }

            return $split_string;
        }
    }
}
