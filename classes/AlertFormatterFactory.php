<?php
/**
 * Manager: Alert Formatter Factory Class
 *
 * Class file for alert formatter factory.
 *
 * @since 4.2.1
 * @package Wsal
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
 * @package Wsal
 */
class WSAL_AlertFormatterFactory {

	/**
	 * @var array WSAL_AlertFormatter[]
	 */
	private static $formatter_instances = [];

	/**
	 * @var array
	 */
	private static $formatter_stack = [];

	public static function bootstrap() {
		//  let extensions register custom alert formatters
		$formatters = apply_filters( 'wsal_alert_formatters', [
			//  no need to supply 'file' elements as the default formatter is loaded by the plugin core
			[
				'context' => 'default',
				'class'   => 'WSAL_AlertFormatter'
			],
			[
				'context' => 'dashboard-widget',
				'class'   => 'WSAL_DashboardWidgetFormatter'
			]
		] );

		if ( ! empty( $formatters ) ) {
			foreach ( $formatters as $formatter ) {
				if ( ! array_key_exists( 'context', $formatter ) ) {
					continue;
				}
				self::$formatter_stack[ $formatter['context'] ] = $formatter;
			}
		}
	}

	/**
	 * @param string $context
	 *
	 * @return WSAL_AlertFormatter
	 */
	public static function getFormatter( $context = 'default' ) {
		try {
			//  @todo we could allow late formatter registration using a filter here to improve performance in some cases
			//  (for example SMS formatter would only be registered if the 'sms' context will be used to display alert message)
			if ( array_key_exists( $context, self::$formatter_instances ) ) {
				return self::$formatter_instances[ $context ];
			}

			if ( array_key_exists( $context, self::$formatter_stack ) ) {
				$formatter = self::createFormatter( self::$formatter_stack[ $context ] );
				if ( null !== $formatter ) {
					self::$formatter_instances[ $context ] = $formatter;

					return self::$formatter_instances[ $context ];
				}
			}
		} catch ( Exception $exception ) {
			return new WSAL_AlertFormatter();
		}

		return new WSAL_AlertFormatter();
	}

	private static function createFormatter( $formatter_def ) {

		if ( ! array_key_exists( 'class', $formatter_def ) ) {
			return null;
		}

		//  load the file if provided
		if ( array_key_exists( 'file', $formatter_def )
		     && ! empty( $formatter_def['file'] )
		     && file_exists( $formatter_def['file'] ) ) {
			require_once $formatter_def['file'];
		}

		try {
			if ( class_exists( $formatter_def['class'] ) ) {
				return new $formatter_def['class']( WpSecurityAuditLog::GetInstance() );
			}
		} catch ( Exception $exception ) {
			return null;
		}

		return null;
	}
}
