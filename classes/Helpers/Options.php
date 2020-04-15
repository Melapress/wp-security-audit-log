<?php
/**
 * Class: WSAL\Models\Options
 *
 * Option Model gets and sets the options from the main WP options table.
 *
 * @since   4.0.2
 * @package Wsal
 */

namespace WSAL\Helpers;

/**
 * WordPress options are always loaded from the default WordPress database.
 *
 * NOTE: there is primiarily a wrapper class around core functions and it has
 * no cache layer here as wp has an internal options cache of it's own.
 *
 * @since 4.0.2
 */
class Options {

	/**
	 * Instance of the main plugin class.
	 *
	 * @since 4.0.2
	 * @var   WpSecurityAuditLog
	 */
	private $plugin;

	/**
	 * Prefix used when setting/getting options.
	 *
	 * @since 4.0.2
	 * @var   @var string
	 */
	public $prefix;

	/**
	 * Sets up this class with the main plugin instance and a prefix.
	 *
	 * @method __construct
	 * @since  4.0.2
	 * @param  WpSecurityAuditLog $plugin The main plugin class instance.
	 * @param  string             $prefix A prefix to use when setting/getting.
	 */
	public function __construct( $plugin, $prefix = '' ) {
		// the main plugin file incase we need to get data from it.
		$this->plugin = $plugin;
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
	 * First attempts to get it from the class cache, then looks in the WP
	 * options table. If it gets fetched then store it in the cache.
	 *
	 * @method get_option_value
	 * @since  4.0.2
	 * @param  string $option_name option name we want to get a value for.
	 * @param  mixed  $default     a default value to use when one doesn't exist.
	 * @return mixed
	 */
	public function get_option_value( $option_name = '', $default = null ) {
		// bail early if no option name was requested.
		if ( empty( $option_name ) || ! is_string( $option_name ) ) {
			return;
		}
		return \get_option( $this->prefix . $option_name, $default );
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
	 */
	public function set_option_value( $option_name = '', $value = null, $autoload = true ) {
		// bail early if no option name or value was passed.
		if ( empty( $option_name ) || null === $value ) {
			return;
		}
		return \update_option( $this->prefix . $option_name, $value, $autoload );
	}

	/**
	 * Deletes an option from the WP options table.
	 *
	 * NOTE: This is just a strait wrapper around the core function.
	 *
	 * @method delete_option
	 * @since  4.0.2
	 * @param  string $option_name Name of the option to delete.
	 * @return bool
	 */
	public function delete_option( $option_name = '' ) {
		return \delete_option( $option_name );
	}

}
