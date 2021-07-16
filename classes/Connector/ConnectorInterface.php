<?php
/**
 * Class: Connection Interface
 *
 * Interface used by the WSAL_Connector.
 *
 * @package wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface used by the WSAL_Connector.
 *
 * @package wsal
 */
interface WSAL_Connector_ConnectorInterface {

	/**
	 * Gets the adapter.
	 *
	 * @param string $class_name - Class name.
	 *
	 * @return WSAL_Adapters_ActiveRecordInterface
	 */
	public function getAdapter( $class_name );

	/**
	 * Get the connection.
	 *
	 * @return wpdb
	 */
	public function getConnection();

	/**
	 * Close the connection.
	 */
	public function closeConnection();

	/**
	 * Is installed?
	 */
	public function isInstalled();

	/**
	 * Install all.
	 *
	 * @param bool $is_external_database
	 */
	public function installAll( $is_external_database = false );

	/**
	 * Install single.
	 *
	 * @param $class_name
	 * @param bool $is_external_database
	 *
	 * @since 4.1.4.1
	 */
	public function installSingle( $class_name, $is_external_database = false );

	/**
	 * Uninstall all.
	 */
	public function uninstallAll();
}
