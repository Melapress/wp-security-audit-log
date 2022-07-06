<?php
/**
 * Class: Validator Helper.
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

if ( ! class_exists( '\WSAL\Helpers\Validator' ) ) {
	/**
	 * Responsible for data validation
	 */
	class Validator {

		/**
		 * Validates mirror tags against rule sets
		 * Note: no short-returns here as this could be used to collect all the problems for give tag
		 *
		 * @param string $string - The string to validate.
		 *
		 * @return boolean - true - tag is valid, false - tag is not valid.
		 *
		 * @since      4.4.2.1
		 */
		public static function validate_mirror_tag( string $string ): bool {

			$valid = true;

			if ( ! self::starts_alpha_numeric( $string ) ) {
				$valid = false;
			}

			if ( ! self::is_string_less_or_equal( $string, 64 ) ) {
				$valid = false;
			}

			if ( ! self::is_string_contains_only( $string ) ) {
				$valid = false;
			}

			return $valid;

		}

		/**
		 * Checks if given string starts with a letter or number.
		 *
		 * @param string $string - The string to check.
		 *
		 * @return boolean
		 *
		 * @since      4.4.2.1
		 */
		private static function starts_alpha_numeric( string $string ): bool {
			if ( preg_match( '/^[a-zA-Z0-9]/', $string ) ) {
				return true;
			}

			return false;
		}

		/**
		 * Checks if string has less or equal number of characters.
		 *
		 * @param string  $string - The string to check.
		 * @param integer $size - The size of the string to check for.
		 *
		 * @return boolean
		 *
		 * @since      4.4.2.1
		 */
		private static function is_string_less_or_equal( string $string, int $size ): bool {
			if ( strlen( $string ) <= $size ) {
				return true;
			}

			return false;
		}

		/**
		 * Check if string contains only specific characters
		 *
		 * @param string $string - The string to check.
		 *
		 * @return boolean
		 *
		 * @since      4.4.2.1
		 */
		private static function is_string_contains_only( string $string ): bool {
			if ( preg_match( '/[a-z\d_-]*/i', $string ) ) {
				return true;
			}
			return false;
		}
	}
}
