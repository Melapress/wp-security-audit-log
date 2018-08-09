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
	 * Connector.
	 *
	 * @var array
	 */
	public static $connector;

	/**
	 * Default Connector.
	 *
	 * @var bool
	 */
	public static $defaultConnector;

	/**
	 * Adapter.
	 *
	 * @var string
	 */
	public static $adapter;

	/**
	 * Returns the a default WPDB connector for saving options
	 */
	public static function GetDefaultConnector() {
		return new WSAL_Connector_MySQLDB();
	}

	/**
	 * Returns a connector singleton
	 *
	 * @param array $config - Connection config.
	 * @param bool  $reset - True if reset.
	 * @return WSAL_Connector_ConnectorInterface
	 */
	public static function GetConnector( $config = null, $reset = false ) {
		if ( ! empty( $config ) ) {
			$connection_config = $config;
		} else {
			$connection_config = self::GetConfig();
		}

		// TO DO: Load connection config.
		if ( null == self::$connector || ! empty( $config ) || $reset ) {
			switch ( strtolower( $connection_config['type'] ) ) {
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
	 */
	public static function GetConfig() {
		$conf = new WSAL_Settings( WpSecurityAuditLog::GetInstance() );
		$type = $conf->GetAdapterConfig( 'adapter-type' );
		if ( empty( $type ) ) {
			return null;
		} else {
			return array(
				'type'        => $conf->GetAdapterConfig( 'adapter-type' ),
				'user'        => $conf->GetAdapterConfig( 'adapter-user' ),
				'password'    => $conf->GetAdapterConfig( 'adapter-password' ),
				'name'        => $conf->GetAdapterConfig( 'adapter-name' ),
				'hostname'    => $conf->GetAdapterConfig( 'adapter-hostname' ),
				'base_prefix' => $conf->GetAdapterConfig( 'adapter-base-prefix' ),
				'is_ssl'      => $conf->GetAdapterConfig( 'adapter-ssl' ),
				'is_cc'       => $conf->GetAdapterConfig( 'adapter-client-certificate' ),
				'ssl_ca'      => $conf->GetAdapterConfig( 'adapter-ssl-ca' ),
				'ssl_cert'    => $conf->GetAdapterConfig( 'adapter-ssl-cert' ),
				'ssl_key'     => $conf->GetAdapterConfig( 'adapter-ssl-key' ),
			);
		}
	}

	/**
	 * Check the adapter config with a test connection.
	 *
	 * @param string $type - Adapter type.
	 * @param string $user - Adapter user.
	 * @param string $password - Adapter password.
	 * @param string $name - Adapter name.
	 * @param string $hostname - Adapter hostname.
	 * @param string $base_prefix - Adapter base_prefix.
	 * @param bool   $is_ssl - Set if connection is SSL encrypted.
	 * @param bool   $is_cc - Set if connection has client certificates.
	 * @param string $ssl_ca - Certificate Authority.
	 * @param string $ssl_cert - Client Certificate.
	 * @param string $ssl_key - Client Key.
	 * @return boolean true|false
	 */
	public static function CheckConfig( $type, $user, $password, $name, $hostname, $base_prefix, $is_ssl, $is_cc, $ssl_ca, $ssl_cert, $ssl_key ) {
		$result = false;
		$config = self::GetConfigArray( $type, $user, $password, $name, $hostname, $base_prefix, $is_ssl, $is_cc, $ssl_ca, $ssl_cert, $ssl_key );
		switch ( strtolower( $type ) ) {
			// TO DO: Add other connectors.
			case 'mysql':
			default:
				$test   = new WSAL_Connector_MySQLDB( $config );
				$result = $test->TestConnection();
		}
		return $result;
	}

	/**
	 * Create array config.
	 *
	 * @param string $type - Adapter type.
	 * @param string $user - Adapter user.
	 * @param string $password - Adapter password.
	 * @param string $name - Adapter name.
	 * @param string $hostname - Adapter hostname.
	 * @param string $base_prefix - Adapter base_prefix.
	 * @param bool   $is_ssl - Set if connection is SSL encrypted.
	 * @param bool   $is_cc - Set if connection has client certificates.
	 * @param string $ssl_ca - Certificate Authority.
	 * @param string $ssl_cert - Client Certificate.
	 * @param string $ssl_key - Client Key.
	 * @return array config
	 */
	public static function GetConfigArray( $type, $user, $password, $name, $hostname, $base_prefix, $is_ssl, $is_cc, $ssl_ca, $ssl_cert, $ssl_key ) {
		return array(
			'type'        => $type,
			'user'        => $user,
			'password'    => $password,
			'name'        => $name,
			'hostname'    => $hostname,
			'base_prefix' => $base_prefix,
			'is_ssl'      => $is_ssl,
			'is_cc'       => $is_cc,
			'ssl_ca'      => $ssl_ca,
			'ssl_cert'    => $ssl_cert,
			'ssl_key'     => $ssl_key,
		);
	}
}
