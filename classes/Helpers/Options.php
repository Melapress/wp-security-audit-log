<?php
/**
 * Class: WSAL\Models\Options
 *
 * Option Model gets and sets the options from the main WP options table.
 *
 * @since   4.0.2
 * @package wsal
 */

namespace WSAL\Helpers;

/**
 * WordPress options are always loaded from the default WordPress database.
 *
 * NOTE: there is primarily a wrapper class around core functions and it has
 * no cache layer here as wp has an internal options cache of it's own.
 *
 * @since 4.0.2
 */
class Options {

	/**
	 * Prefix used when setting/getting options.
	 *
	 * @since 4.0.2
	 * @var string
	 */
	public $prefix;

	/**
	 * Sets up this class with the main plugin instance and a prefix.
	 *
	 * @param string $prefix A prefix to use when setting/getting.
	 *
	 * @since  4.0.2
	 */
	public function __construct( $prefix = '' ) {
		// sets the prefix used when getting all options through this class.
		$this->set_prefix( $prefix );
	}

	/**
	 * Setter to allow changing prefixes when class isn't fetching own options.
	 *
	 * @method set_prefix
	 * @since  4.0.2
	 * @param  string $prefix The prefix string to use when fetching.
	 */
	public function set_prefix( $prefix = '' ) {
		$this->prefix = ( is_string( $prefix ) ) ? $prefix : '';
	}

	/**
	 * Gets the value of an option.
	 *
	 * @method get_option_value
	 * @since  4.0.2
	 * @param  string $option_name option name we want to get a value for.
	 * @param  mixed  $default     a default value to use when one doesn't exist.
	 * @return mixed
	 *
	 * @deprecated latest - Use \WSAL\Helpers\Settings_Helper::get_option_value()
	 */
	public function get_option_value( $option_name = '', $default = null ) {
		_deprecated_function( __FUNCTION__, '4.4.3', '\WSAL\Helpers\Settings_Helper::get_option_value()' );
		return \WSAL\Helpers\Settings_Helper::get_option_value( $option_name, $default );
	}

	/**
	 * Sets the value of an option.
	 *
	 * @method set_option_value
	 * @since  4.0.2
	 * @param  string $option_name The name of option to save.
	 * @param  mixed  $value       A value to store under the option name.
	 * @param  bool   $autoload    Whether or not to autoload this option.
	 * @return bool Whether or not the option was updated.
	 *
	 * @deprecated 4.4.3 - Use \WSAL\Helpers\Settings_Helper::set_option_value()
	 */
	public function set_option_value( $option_name = '', $value = null, $autoload = false ) {
		_deprecated_function( __FUNCTION__, '4.4.3', '\WSAL\Helpers\Settings_Helper::set_option_value()' );
		return \WSAL\Helpers\Settings_Helper::set_option_value( $option_name, $value, $autoload );
	}

	/**
	 * Deletes a plugin option from the WP options table.
	 *
	 * Hanled option name with and without the prefix for backwards compatibility.
	 *
	 * @since  4.0.2
	 * @param  string $option_name Name of the option to delete.
	 * @return bool
	 *
	 * @deprecated 4.4.3 - Use \WSAL\Helpers\Settings_Helper::delete_option_value()
	 */
	public function delete_option( $option_name = '' ) {
		_deprecated_function( __FUNCTION__, '4.4.3', '\WSAL\Helpers\Settings_Helper::delete_option_value()' );
		return \WSAL\Helpers\Settings_Helper::delete_option_value( $option_name );
	}

	/**
	 * Static function for retrieving an option value statically.
	 *
	 * WARNING!
	 * ========
	 * This should be used only when absolutely necessary. For example in very early stages of WordPress application
	 * lifecycle before the whole plugin is loaded. At the time of writing this function, only frontend events settings
	 * was needed to be treated this way.
	 *
	 * In all other cases function \WpSecurityAuditLog::GetGlobalSetting() should be used instead.
	 *
	 * @see \WpSecurityAuditLog::get_global_setting()
	 * @since  4.1.3
	 * @param  string $option_name Option name we want to get a value for including necessary plugin prefix.
	 * @param  mixed  $default     a default value to use when one doesn't exist.
	 * @return mixed
	 *
	 * @deprecated latest - use \WSAL\Helpers\Settings_Helper::get_option_value()
	 */
	public static function get_option_value_ignore_prefix( $option_name = '', $default = null ) {
		_deprecated_function( __FUNCTION__, '4.4.3', '\WSAL\Helpers\Settings_Helper::get_option_value()' );
		return \WSAL\Helpers\Settings_Helper::get_option_value( $option_name, $default );
	}

	/**
	 * Static function for saving an option value statically.
	 *
	 * WARNING!
	 * ========
	 * This should be used only when absolutely necessary. For example in very early stages of WordPress application
	 * lifecycle before the whole plugin is loaded. At the time of writing this function, only frontend events settings
	 * was needed to be treated this way.
	 *
	 * In all other cases function \WpSecurityAuditLog::SetGlobalSetting() should be used instead.
	 *
	 * @param string $option_name Option name we want to get a value for including necessary plugin prefix.
	 * @param mixed  $value       A value to store under the option name.
	 * @param bool   $autoload    Whether to autoload this option.
	 *
	 * @return mixed
	 * @see    \WpSecurityAuditLog::set_global_setting()
	 * @since  4.1.3
	 *
	 * @deprecated 4.4.3 - Use \WSAL\Helpers\Settings_Helper::set_option_value()
	 */
	public static function set_option_value_ignore_prefix( $option_name = '', $value = null, $autoload = false ) {
		_deprecated_function( __FUNCTION__, '4.4.3', '\WSAL\Helpers\Settings_Helper::set_option_value()' );
		return \WSAL\Helpers\Settings_Helper::set_option_value( $option_name, $value, $autoload );
	}

	/**
	 * Converts a string (e.g. 'yes' or 'no') to a bool.
	 *
	 * @since 4.1.3
	 * @param string $string String to convert.
	 * @return bool
	 */
	public static function string_to_bool( $string ) {
		return is_bool( $string ) ? $string : ( 'yes' === $string || 1 === $string || 'true' === $string || '1' === $string || 'on' === $string || 'enable' === $string );
	}

	/**
	 * Converts a bool to a 'yes' or 'no'.
	 *
	 * @since 4.1.3
	 * @param bool $bool String to convert.
	 * @return string
	 */
	public static function bool_to_string( $bool ) {
		if ( ! is_bool( $bool ) ) {
			$bool = self::string_to_bool( $bool );
		}
		return true === $bool ? 'yes' : 'no';
	}

}
