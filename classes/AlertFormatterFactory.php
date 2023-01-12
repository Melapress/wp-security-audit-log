<?php
/**
 * Manager: Alert Formatter Factory Class
 *
 * Class file for alert formatter factory.
 *
 * @since 4.2.1
 * @package wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WSAL_AlertFormatterFactory class.
 *
 * Manages various alert formatters and allows registration of custom alert formatters.
 *
 * @package wsal
 */
class WSAL_AlertFormatterFactory {

	/**
	 * Alert formatter instances.
	 *
	 * @var WSAL_AlertFormatter[]
	 */
	private static $formatter_instances = array();

	/**
	 * Alert formatter configuration.
	 *
	 * @var WSAL_AlertFormatterConfiguration[]
	 */
	private static $configurations = array();

	/**
	 * Boostraps the factory.
	 */
	public static function bootstrap() {

		$html_configuration             = WSAL_AlertFormatterConfiguration::build_html_configuration();
		$dashboard_widget_configuration = ( clone $html_configuration )
			->set_is_js_in_links_allowed( false )
			->set_supports_metadata( false )
			->set_supports_hyperlinks( false );

		// Let extensions register custom alert formatters.
		$formatters = apply_filters(
			'wsal_alert_formatters',
			array(
				'default'          => $html_configuration,
				'dashboard-widget' => $dashboard_widget_configuration,
			)
		);

		if ( ! empty( $formatters ) ) {
			foreach ( $formatters as $context => $formatter_configuration ) {
				self::$configurations[ $context ] = $formatter_configuration;
			}
		}
	}

	/**
	 * Gets alert formatter for given context.
	 *
	 * @param string $context Context.
	 *
	 * @return WSAL_AlertFormatter
	 */
	public static function get_formatter( $context = 'default' ) {
		try {
			// @todo we could allow late formatter registration using a filter here to improve performance in some cases
			// (for example SMS formatter would only be registered if the 'sms' context will be used to display alert message)
			if ( array_key_exists( $context, self::$formatter_instances ) ) {
				return self::$formatter_instances[ $context ];
			}

			if ( array_key_exists( $context, self::$configurations ) ) {
				$formatter = new WSAL_AlertFormatter( WpSecurityAuditLog::get_instance(), self::$configurations[ $context ] );

				self::$formatter_instances[ $context ] = $formatter;

				return self::$formatter_instances[ $context ];
			}
		} catch ( Exception $exception ) {
			return self::create_default_formatter();
		}

		return self::create_default_formatter();
	}

	/**
	 * Creates default formatter (HTML).
	 *
	 * @return WSAL_AlertFormatter Default formatter using full-featured HTML configuration.
	 * @since 4.3.0
	 */
	private static function create_default_formatter() {
		return new WSAL_AlertFormatter( WpSecurityAuditLog::get_instance(), WSAL_AlertFormatterConfiguration::build_html_configuration() );
	}
}
