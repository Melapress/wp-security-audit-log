<?php
/**
 * Utility Class: Date and time formatter.
 *
 * Singleton utility class used for formatting date and time strings.
 *
 * @package wsal
 * @since 4.2.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Date and Time Utility Class
 *
 * Singleton utility class used for formatting date and time strings.
 *
 * @since 4.2.0
 */
class WSAL_Utilities_DateTimeFormatter {

	/**
	 * Regular expression for matching the milliseconds part of datetime string.
	 *
	 * @var string
	 */
	private static $am_pm_lookup_pattern = '/\.\d+((\&nbsp;|\ )([AP]M))?/i';

	/**
	 * GMT Offset
	 *
	 * @var string
	 */
	private $gmt_offset_sec = 0;

	/**
	 * Date format.
	 *
	 * @var string
	 */
	private $date_format;

	/**
	 * Time format.
	 *
	 * @var string
	 */
	private $time_format;

	/**
	 * Datetime format.
	 *
	 * @var string
	 */
	private $datetime_format;

	/**
	 * Datetime format without linebreaks.
	 *
	 * @var string
	 */
	private $datetime_format_no_linebreaks;

	/**
	 * If true, show milliseconds.
	 *
	 * @var bool
	 */
	private $show_milliseconds;

	/**
	 * Make constructor private, so nobody can call "new Class".
	 */
	private function __construct() {
	}

	/**
	 * Call this method to get singleton
	 */
	public static function instance() {
		static $instance = false;
		if ( false === $instance ) {
			// Late static binding (PHP 5.3+).
			$instance = new static();

			$plugin   = WpSecurityAuditLog::get_instance();
			$timezone = $plugin->settings()->get_timezone();

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
				$instance->gmt_offset_sec = date( 'Z' ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
			} else {
				$instance->gmt_offset_sec = get_option( 'gmt_offset' ) * HOUR_IN_SECONDS;
			}

			$instance->show_milliseconds             = $plugin->settings()->get_show_milliseconds();
			$instance->date_format                   = $plugin->settings()->get_date_format();
			$instance->time_format                   = $plugin->settings()->get_time_format();
			$instance->datetime_format               = $plugin->settings()->get_datetime_format();
			$instance->datetime_format_no_linebreaks = $plugin->settings()->get_datetime_format( false );

		}

		return $instance;
	}

	/**
	 * Remove milliseconds from formatted datetime string.
	 *
	 * @param string $formatted_datetime Formatted datetime string.
	 *
	 * @return string
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
	 */
	public function get_formatted_date_time( $timestamp, $type = 'datetime', $do_timezone_offset = true, $line_break = false, $use_nb_space_for_am_pm = true, $translated = true ) {
		$result = '';
		$format = null;
		switch ( $type ) {
			case 'datetime':
				$format = $line_break ? $this->datetime_format : $this->datetime_format_no_linebreaks;
				if ( ! $use_nb_space_for_am_pm ) {
					$format = preg_replace( '/&\\\n\\\b\\\s\\\p;/', ' ', $format );
				}
				break;
			case 'date':
				$format = $this->date_format;
				break;
			case 'time':
				$format = $this->time_format;
				break;
			default:
				return $result;
		}

		if ( null === $format ) {
			return $result;
		}

		// Timezone adjustment.
		$timezone_adjusted_timestamp = $do_timezone_offset ? $timestamp + $this->gmt_offset_sec : $timestamp;

		// Milliseconds in format (this is probably not necessary, but we keep it just to be 100% sure).
		if ( ! $this->show_milliseconds ) {
			// Remove the milliseconds placeholder from format string.
			$format = str_replace( '.$$$', '', $format );
		}

		// Date formatting.
		$result = $translated ? date_i18n( $format, $timezone_adjusted_timestamp ) : date( $format, $timezone_adjusted_timestamp ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date

		// Milliseconds value.
		if ( $this->show_milliseconds ) {
			$result = str_replace(
				'$$$',
				substr( number_format( fmod( $timezone_adjusted_timestamp, 1 ), 3 ), 2 ),
				$result
			);
		}

		return $result;
	}
}
