<?php
/**
 * Class: Abstract Connector Factory.
 *
 * Abstract class used for create the connector, only MySQL is implemented.
 *
 * @package wsal
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
 * @package wsal
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
	private static $connectors = array();

	/**
	 * Occurrence is installed.
	 *
	 * @since 3.4.1
	 *
	 * @var bool
	 */
	private static $is_installed;

	/**
	 * Enabled archive mode. It forces archive connector by default.
	 *
	 * @var bool
	 *
	 * @since 4.4.0
	 */
	private static $archive_mode = false;

	/**
	 * Returns the default WPDB connector that must be always used for some data, for example user sessions and
	 * also custom options table in the past.
	 */
	public static function get_default_connector() {
		return new WSAL_Connector_MySQLDB();
	}

	/**
	 * Returns a connector singleton
	 *
	 * @param string|array $config DB configuration array, db alias or empty to use default connection.
	 * @param bool         $reset  - True if reset.
	 *
	 * @return WSAL_Connector_ConnectorInterface
	 * @throws Freemius_Exception
	 */
	public static function get_connector( $config = null, $reset = false ) {
		$connection_config = null;
		if ( is_null( $config ) || empty( $config ) ) {
			if ( self::$archive_mode ) {
				// Force archive database if no config provided and archive mode is enabled.
				$plugin            = WpSecurityAuditLog::get_instance();
				$connection_name   = $plugin->get_global_setting( 'archive-connection' );
				$connection_config = self::load_connection_config( $connection_name );
			} else {
				// Default config - local or external, depending on plugin settings and licensing.
				$connection_config = self::get_config( $config );
			}
		} else {
			if ( is_string( $config ) ) {
				// String based config, can be used to retrieve local WP connection.
				if ( 'local' === $config ) {
					// This forces the WSAL_Connector_MySQLDB to return connection to local WP database.
					$connection_config = null;
				}
			} elseif ( is_array( $config ) ) {
				// Array config gets connection to whatever database configuration it holds.
				$connection_config = $config;
			}
		}

		$cache_key = 'default';
		if ( is_string( $config ) ) {
			$cache_key = $connection_config;
		} elseif ( is_array( $connection_config ) ) {
			$cache_key = $connection_config['name'];
		}

		// TO DO: Load connection config.
		if ( ! array_key_exists( $cache_key, self::$connectors ) || $reset ) {
			$connection_type = is_array( $connection_config ) && isset( $connection_config['type'] ) ? strtolower( $connection_config['type'] ) : '';
			switch ( $connection_type ) {
				// TO DO: Add other connectors.
				case 'mysql':
				default:
					// Use config.
					self::$connectors[ $cache_key ] = new WSAL_Connector_MySQLDB( $connection_config );
			}
		}

		return self::$connectors[ $cache_key ];
	}

	/**
	 * Get the adapter config stored in the DB
	 *
	 * @return array|null adapter config
	 * @throws Freemius_Exception
	 */
	public static function get_config() {
		$plugin          = WpSecurityAuditLog::get_instance();
		$connection_name = $plugin->get_global_setting( 'adapter-connection' );

		if ( function_exists( 'wsal_freemius' ) && ! apply_filters( 'wsal_disable_freemius_sdk', false ) ) {
			$is_not_paying = wsal_freemius()->is_not_paying();
		} else {
			$is_not_paying = ! WpSecurityAuditLog::is_premium_freemius();
		}

		if ( $connection_name && $is_not_paying ) {
			$connector = new WSAL_Connector_MySQLDB();

			if ( ! self::$is_installed ) {
				self::$is_installed = $connector->is_installed();
				$connector->install_all();
			}

			$connection_name = null;
		}

		if ( empty( $connection_name ) ) {
			return null;
		}

		return self::load_connection_config( $connection_name );
	}

	/**
	 * Loads connection config using its name.
	 *
	 * @param string $connection_name Connection name.
	 *
	 * @return array|null
	 * @since 4.4.0
	 */
	private static function load_connection_config( $connection_name ) {
		/*
		 * Reused code from the external DB module.
		 *
		 * @see WSAL_Ext_Common::get_connection()
		 */
		$plugin         = WpSecurityAuditLog::get_instance();
		$connection_raw = maybe_unserialize( $plugin->get_global_setting( 'connection-' . $connection_name ) );
		$connection     = ( $connection_raw instanceof stdClass ) ? json_decode( json_encode( $connection_raw ), true ) : $connection_raw; // phpcs:ignore
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
	public static function check_config( $config ) {
		// Only mysql supported at the moment.
		if ( array_key_exists( 'type', $config ) && 'mysql' === $config['type'] ) {
			try {
				$connector = new WSAL_Connector_MySQLDB( $config );
				return $connector->TestConnection();
			} catch ( Exception $e ) {
				return false;
			}
		}

		return false;
	}

	/**
	 * Enables archive mode.
	 *
	 * @since 4.4.0
	 */
	public static function enable_archive_mode() {
		self::$archive_mode = true;
	}

	/**
	 * Disables archive mode.
	 *
	 * @since 4.4.0
	 */
	public static function disable_archive_mode() {
		self::$archive_mode = false;
	}
}
