<?php
/**
 * Manager: Alert Formatter Configuration Class
 *
 * Class file for alert configuration.
 *
 * @since 5.3.0
 * @package wsal
 */

declare(strict_types=1);

namespace WSAL\Helpers\Formatters;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\Helpers\Formatters\Alert_Formatter_Configuration' ) ) {
	/**
	 * Alert_Formatter_Configuration class.
	 *
	 * Manages various alert formatters and allows registration of custom alert formatters.
	 *
	 * @package wsal
	 */
	class Alert_Formatter_Configuration {

		/**
		 * Default (text) alert format configuration.
		 *
		 * @var array
		 *
		 * @since 5.3.0
		 */
		private static $default_configuration = array(
			'tags_allowed_in_message'   => '',
			'is_js_in_links_allowed'    => false,
			'highlight_start_tag'       => '',
			'highlight_end_tag'         => '',
			'supports_metadata'         => false,
			'supports_hyperlinks'       => false,
			'emphasis_start_tag'        => '',
			'emphasis_end_tag'          => '',
			'max_meta_value_length'     => 50,
			'end_of_line'               => ' ',
			'ellipses_sequence'         => '...',
			'use_html_markup_for_links' => true,
		);

		/**
		 * Default (HTML) configuration
		 *
		 * @var array
		 *
		 * @since 5.3.0
		 */
		private static $default_html_configuration = array(
			'tags_allowed_in_message'   => '<strong><br><a>',
			'is_js_in_links_allowed'    => true,
			'highlight_start_tag'       => '<strong>',
			'highlight_end_tag'         => '</strong>',
			'supports_metadata'         => true,
			'supports_hyperlinks'       => true,
			'emphasis_start_tag'        => '<i>',
			'emphasis_end_tag'          => '</i>',
			'max_meta_value_length'     => 50,
			'end_of_line'               => '<br />',
			'ellipses_sequence'         => '&hellip;',
			'use_html_markup_for_links' => true,
		);

		/**
		 * Returns the default configuration for the alert.
		 *
		 * @return array
		 *
		 * @since 5.3.0
		 */
		public static function get_default_configuration(): array {
			return self::$default_configuration;
		}

		/**
		 * Returns the default HTML configuration for the alert.
		 *
		 * @return array
		 *
		 * @since 5.3.0
		 */
		public static function get_default_html_configuration(): array {
			return self::$default_html_configuration;
		}

		/**
		 * Overrides the configurations with the provided one. By default non-HTML configuration is overriden, but HTML one could also be overridden by passing a parameter.
		 *
		 * @param array   $configuration - The configuration to merge with the default configuration (if no flag provided).
		 * @param boolean $from_html - Whether to use the HTML configuration.
		 * @param string  $key_to_set - Key name to set in the configuration.
		 * @param mixed   $value_to_set - Value to set in the configuration.
		 *
		 * @return array
		 *
		 * @since 5.3.0
		 */
		public static function set_configuration( array $configuration = array(), bool $from_html = false, $key_to_set = null, $value_to_set = null ): array {

			if ( isset( $configuration ) && ! empty( $configuration ) ) {
				if ( $from_html ) {
					return \array_merge( self::$default_html_configuration, $configuration );
				}
				return \array_merge( self::$default_configuration, $configuration );
			}

			if ( $key_to_set && $value_to_set ) {
				if ( $from_html ) {
					return \array_merge( self::$default_html_configuration, array( $key_to_set => $value_to_set ) );
				}
				return \array_merge( self::$default_configuration, array( $key_to_set => $value_to_set ) );
			}
		}
	}
}
