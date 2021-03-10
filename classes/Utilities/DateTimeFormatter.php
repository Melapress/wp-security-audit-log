<?php
/**
 * Utility Class: Date and time formatter.
 *
 * Singleton utility class used for formatting date and time strings.
 *
 * @package Wsal
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
	 * @var string Regular expression for matching the milliseconds part of datetime string.
	 */
	private static $am_pm_lookup_pattern = '/\.\d+((\&nbsp;|\ )([AP]M))?/i';

	/**
	 * GMT Offset
	 *
	 * @var string
	 */
	private $gmt_offset_sec = 0;

	private $date_format;

	private $time_format;

	private $datetime_format;

	private $datetime_format_no_linebreaks;

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
		if ( $instance === false ) {
			// Late static binding (PHP 5.3+)
			$instance = new static();

			$plugin   = WpSecurityAuditLog::GetInstance();
			$timezone = $plugin->settings()->GetTimezone();

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
				$instance->gmt_offset_sec = date( 'Z' );
			} else {
				$instance->gmt_offset_sec = get_option( 'gmt_offset' ) * HOUR_IN_SECONDS;
			}

			$instance->show_milliseconds             = $plugin->settings()->get_show_milliseconds();
			$instance->date_format                   = $plugin->settings()->GetDateFormat();
			$instance->time_format                   = $plugin->settings()->GetTimeFormat();
			$instance->datetime_format               = $plugin->settings()->GetDatetimeFormat();
			$instance->datetime_format_no_linebreaks = $plugin->settings()->GetDatetimeFormat( false );

		}

		return $instance;
	}

	/**
	 * Remove milliseconds from formatted datetime string.
	 *
	 * @param string $formattedDatetime Formatted datetime string
	 *
	 * @return string
	 * @since 4.2.0
	 */
	public static function removeMilliseconds( $formattedDatetime ) {
		return preg_replace( self::$am_pm_lookup_pattern, ' $3', $formattedDatetime );
	}

	public function getFormattedDateTime( $timestamp, $type = 'datetime', $do_timezone_offset = true, $line_break = false, $use_nb_space_for_am_pm = true, $translated = true ) {
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

		//  timezone adjustment
		$timezone_adjusted_timestamp = $do_timezone_offset ? $timestamp + $this->gmt_offset_sec : $timestamp;

		//  milliseconds in format (this is probably not necessary, but we keep it just to be 100% sure)
		if ( ! $this->show_milliseconds ) {
			// remove the milliseconds placeholder from format string.
			$format = str_replace( '.$$$', '', $format );
		}

		//  date formatting
		$result = $translated ? date_i18n( $format, $timezone_adjusted_timestamp ) : date( $format, $timezone_adjusted_timestamp );

		//  milliseconds value
		if ( $this->show_milliseconds ) {
			$result = str_replace(
				'$$$',
				substr( number_format( fmod( $timezone_adjusted_timestamp, 1 ), 3 ), 2 ),
				$result
			);
		}

		return $result;

	}

	/**
	 * Make clone magic method private, so nobody can clone instance.
	 */
	private function __clone() {
	}

	/**
	 * Make sleep magic method private, so nobody can serialize instance.
	 */
	private function __sleep() {
	}

	/**
	 * Make wakeup magic method private, so nobody can unserialize instance.
	 */
	private function __wakeup() {
	}
}
