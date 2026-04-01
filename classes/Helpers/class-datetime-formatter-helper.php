<?php
/**
 * Class: DateTime formatter Helper.
 *
 * Helper class used for extraction / loading classes.
 *
 * @package wsal
 *
 * @since 4.5.0
 */

declare(strict_types=1);

namespace WSAL\Helpers;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\Helpers\DateTime_Formatter_Helper' ) ) {

	/**
	 * Date and Time Utility Class
	 *
	 * @since 4.5.0
	 */
	class DateTime_Formatter_Helper {
		/**
		 * Regular expression for matching the milliseconds part of datetime string.
		 *
		 * @var string
		 *
		 * @since 4.5.0
		 */
		private static $am_pm_lookup_pattern = '/\.\d+((\&nbsp;|\ )([AP]M))?/i';

		/**
		 * GMT Offset
		 *
		 * @var string
		 *
		 * @since 4.5.0
		 */
		private static $gmt_offset_sec = 0;

		/**
		 * Date format.
		 *
		 * @var string
		 *
		 * @since 4.5.0
		 */
		private static $date_format;

		/**
		 * Time format.
		 *
		 * @var string
		 *
		 * @since 4.5.0
		 */
		private static $time_format;

		/**
		 * Datetime format.
		 *
		 * @var string
		 *
		 * @since 4.5.0
		 */
		private static $datetime_format;

		/**
		 * Datetime format without linebreaks.
		 *
		 * @var string
		 *
		 * @since 4.5.0
		 */
		private static $datetime_format_no_linebreaks;

		/**
		 * If true, show milliseconds.
		 *
		 * @var bool
		 *
		 * @since 4.5.0
		 */
		private static $show_milliseconds = null;

		/**
		 * Call this method to get singleton
		 *
		 * @since 4.5.0
		 */
		public static function init() {

			$timezone = Settings_Helper::get_timezone();

			/**
			 * Transform timezone values.
			 *
			 * @since 3.2.3
			 */
			if ( '0' === $timezone ) {
				$timezone = 'utc';
			} elseif ( '1' === $timezone ) {
				$timezone = 'wp';
			}

			if ( 'utc' === $timezone ) {
				self::$gmt_offset_sec = date( 'Z' ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
			} else {
				self::$gmt_offset_sec = get_option( 'gmt_offset' ) * HOUR_IN_SECONDS;
			}

			self::$show_milliseconds             = Settings_Helper::get_show_milliseconds();
			self::$date_format                   = Settings_Helper::get_date_format();
			self::$time_format                   = self::enrich_time_format( Settings_Helper::get_time_format() );
			self::$datetime_format               = Settings_Helper::get_datetime_format();
			self::$datetime_format_no_linebreaks = Settings_Helper::get_datetime_format( false );
		}

		/**
		 * Remove milliseconds from formatted datetime string.
		 *
		 * @param string $formatted_datetime Formatted datetime string.
		 *
		 * @return string
		 *
		 * @since 4.2.0
		 */
		public static function remove_milliseconds( $formatted_datetime ) {
			return preg_replace( self::$am_pm_lookup_pattern, ' $3', $formatted_datetime );
		}

		/**
		 * Formats date time based on various requirements.
		 *
		 * @param float  $timestamp              Timestamp.
		 * @param string $type                   Output type.
		 * @param bool   $do_timezone_offset     If true, timezone offset is applied to the timestamp.
		 * @param bool   $line_break             If true, line-break characters are included.
		 * @param bool   $use_nb_space_for_am_pm If true, non-breakable space is included before AM/PM part.
		 * @param bool   $translated             If true, the result is translated.
		 *
		 * @return string
		 *
		 * @since 4.5.0
		 */
		public static function get_formatted_date_time( $timestamp, $type = 'datetime', $do_timezone_offset = true, $line_break = false, $use_nb_space_for_am_pm = true, $translated = true ) {

			if ( null === self::$show_milliseconds ) {
				self::init();
			}

			$result = '';
			$format = null;
			switch ( $type ) {
				case 'datetime':
					$format = $line_break ? self::$datetime_format : self::$datetime_format_no_linebreaks;
					if ( ! $use_nb_space_for_am_pm ) {
						$format = preg_replace( '/&\\\n\\\b\\\s\\\p;/', ' ', $format );
					}
					break;
				case 'date':
					$format = self::$date_format;
					break;
				case 'time':
					$format = self::$time_format;
					break;
				default:
					return $result;
			}

			if ( null === $format ) {
				return $result;
			}

			// Timezone adjustment, preserve fractional part for milliseconds before casting to int.
			$adjusted_float              = $do_timezone_offset ? $timestamp + self::$gmt_offset_sec : $timestamp;
			$milliseconds                = substr( number_format( fmod( (float) $adjusted_float, 1 ), 3 ), 2 );
			$timezone_adjusted_timestamp = (int) $adjusted_float;

			// Milliseconds in format (this is probably not necessary, but we keep it just to be 100% sure).
			if ( ! self::$show_milliseconds ) {
				// Remove the milliseconds placeholder from format string.
				$format = str_replace( '.$$$', '', $format );
			}

			// Date formatting.
			$result = $translated ? date_i18n( $format, $timezone_adjusted_timestamp ) : date( $format, $timezone_adjusted_timestamp ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date

			// Milliseconds value.
			if ( self::$show_milliseconds ) {
				$result = str_replace( '$$$', $milliseconds, $result );
			}

			return $result;
		}

		/**
		 * Enriches a time format string with seconds and optionally milliseconds.
		 *
		 * Works with standard PHP date() format characters:
		 * g — 12-hour hour without leading zero (1–12)
		 * i — minutes with leading zero (00–59)
		 * s — seconds with leading zero (00–59)
		 * A — uppercase AM/PM
		 *
		 * @param string $time_format - The raw WordPress time format string.
		 *
		 * @return string - The enriched time format string. Example: 'g:i A' becomes 'g:i:s A'.
		 *
		 * @since 5.6.2
		 */
		private static function enrich_time_format( string $time_format ): string {
			$suffix = '';

			// Append seconds if the format does not already include them.
			if ( false === stripos( $time_format, 's' ) ) {
				$suffix .= ':s';
			}

			// Append milliseconds placeholder so it can be replaced with the real value later.
			if ( self::$show_milliseconds ) {
				$suffix .= '.$$$';
			}

			// Nothing to enrich — return the original format unchanged.
			if ( '' === $suffix ) {
				return $time_format;
			}

			// Insert suffix before AM/PM indicator, or append at end.
			$enriched = preg_replace( '/(?i)(\s+A)/', $suffix . '$1', $time_format, 1, $count );

			return $count ? $enriched : $time_format . $suffix;
		}

		/**
		 * Returns the offset of the timezone
		 *
		 * @return int
		 *
		 * @since 4.5.0
		 */
		public static function get_time_zone_offset() {
			if ( null === self::$show_milliseconds ) {
				self::init();
			}

			return self::$gmt_offset_sec;
		}
	}
}
