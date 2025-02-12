<?php
/**
 * Manager: Alert Formatter Factory Class
 *
 * Class file for alert formatter factory.
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

if ( ! class_exists( '\WSAL\Helpers\Formatters\Formatter_Factory' ) ) {
	/**
	 * Formatter_Factory class.
	 *
	 * Manages various alert formatters and allows registration of custom alert formatters.
	 *
	 * @package wsal
	 */
	class Formatter_Factory {

		/**
		 * Alert formatter configuration.
		 *
		 * @var array
		 */
		private static $configurations = array();

		/**
		 * Checks and returns configuration for the given formatter name. Calls a filter if one is not present in the defined configuration. Falls back to the default configuration if none is found.
		 *
		 * @param string $context - The name of the formatter configuration context to return.
		 *
		 * @return array
		 *
		 * @since 5.3.0
		 */
		public static function get_configuration( string $context ): array {
			if ( array_key_exists( $context, self::$configurations ) ) {
				return self::$configurations[ $context ];
			} else {
				self::$configurations = apply_filters(
					'wsal_alert_formatters',
					array(
						'default'          => Alert_Formatter_Configuration::get_default_html_configuration(),
						'dashboard-widget' => Alert_Formatter_Configuration::set_configuration(
							array(
								'js_in_links_allowed' => false,
								'supports_metadata'   => false,
								'supports_hyperlinks' => false,
							)
						),
					)
				);
				if ( array_key_exists( $context, self::$configurations ) ) {
					return self::$configurations[ $context ];
				}
			}

			return Alert_Formatter_Configuration::get_default_html_configuration();
		}
	}
}
