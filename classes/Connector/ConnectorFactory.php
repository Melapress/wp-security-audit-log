<?php
/**
 * Class: Abstract Connector Factory.
 *
 * Abstract class used for create the connector, only MySQL is implemented.
 *
 * @package Wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WSAL_Connector_ConnectorFactory.
 *
 * Abstract class used for create the connector, only MySQL is implemented.
 *
 * @todo Add other adapters.
 * @package Wsal
 */
abstract class WSAL_Connector_ConnectorFactory {

	/**
	 * Adapter.
	 *
	 * @var string
	 */
	public static $adapter;
	/**
	 * Connector.
	 *
	 * @var array
	 */
	private static $connector;
	/**
	 * Occurrence is installed.
	 *
	 * @since 3.4.1
	 *
	 * @var bool
	 */
	private static $is_installed;

	/**
	 * Returns the a default WPDB connector that must be always used for some data, for example user sessions and
	 * also custom options table in the past.
	 */
	public static function GetDefaultConnector() {
		return new WSAL_Connector_MySQLDB();
	}

	/**
	 * Returns a connector singleton
	 *
	 * @param array $config - Connection config.
	 * @param bool $reset - True if reset.
	 *
	 * @return WSAL_Connector_ConnectorInterface
	 * @throws Freemius_Exception
	 */
	public static function GetConnector( $config = null, $reset = false ) {
		if ( ! empty( $config ) ) {
			$connection_config = $config;
		} else {
			$connection_config = self::GetConfig();
		}

		// TO DO: Load connection config.
		if ( null == self::$connector || ! empty( $config ) || $reset ) {
			switch ( strtolower( isset( $connection_config['type'] ) ? $connection_config['type'] : '' ) ) {
				// TO DO: Add other connectors.
				case 'mysql':
				default:
					// Use config.
					self::$connector = new WSAL_Connector_MySQLDB( $connection_config );
			}
		}

		return self::$connector;
	}

	/**
	 * Get the adapter config stored in the DB
	 *
	 * @return array|null adapter config
	 * @throws Freemius_Exception
	 */
	public static function GetConfig() {
		$plugin          = WpSecurityAuditLog::GetInstance();
		$connection_name = $plugin->GetGlobalSetting( 'adapter-connection' );

		if ( function_exists( 'wsal_freemius' ) && ! apply_filters( 'wsal_disable_freemius_sdk', false ) ) {
			$is_not_paying = wsal_freemius()->is_not_paying();
		} else {
			$is_not_paying = ! WpSecurityAuditLog::is_premium_freemius();
		}

		if ( $connection_name && $is_not_paying ) {
			$connector = new WSAL_Connector_MySQLDB();

			if ( ! self::$is_installed ) {
				self::$is_installed = $connector->isInstalled();
				$connector->installAll();
			}

			$connection_name = null;
		}

		if ( empty( $connection_name ) ) {
			return null;
		}

		/*
		 * Reused code from the external DB module.
		 *
		 * @see WSAL_Ext_Common::get_connection()
		 */
		$connection_raw = maybe_unserialize( $plugin->GetGlobalSetting( 'connection-' . $connection_name ) );
		$connection     = ( $connection_raw instanceof stdClass ) ? json_decode( json_encode( $connection_raw ), true ) : $connection_raw;
		if ( ! is_array( $connection ) || empty( $connection ) ) {
			return null;
		}

		return $connection;
	}

	/**
	 * Check the adapter config with a test connection.
	 *
	 * @param array $config Configuration data.
	 *
	 * @return boolean true|false
	 */
	public static function CheckConfig( $config ) {
		//  only mysql supported at the moment
		if ( array_key_exists( 'type', $config ) && 'mysql' === $config['type'] ) {
			try {
				$test = new WSAL_Connector_MySQLDB( $config );
				return $test->TestConnection();
			} catch ( Exception $e ) {
				return false;
			}
		}

		return false;
	}
}
