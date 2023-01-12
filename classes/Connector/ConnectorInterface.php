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
	public function get_adapter( $class_name );

	/**
	 * Get the connection.
	 *
	 * @return wpdb
	 */
	public function get_connection();

	/**
	 * Close the connection.
	 */
	public function close_connection();

	/**
	 * Checks if the necessary tables are available
	 *
	 * @return bool true|false
	 */
	public function is_installed();

	/**
	 * Install all.
	 *
	 * @param bool $is_external_database True if this is external database.
	 */
	public function install_all( $is_external_database = false );

	/**
	 * Installs single database table based on its adapter class name.
	 *
	 * @param string $class_name           Adapter class name.
	 * @param bool   $is_external_database True if this is external database.
	 *
	 * @since 4.1.4.1
	 */
	public function install_single( $class_name, $is_external_database = false );

	/**
	 * Uninstall all.
	 */
	public function uninstall_all();

	/**
	 * Run any query.
	 *
	 * @param string $query Databse query to execute.
	 *
	 * @return mixed
	 * @since 4.4.0
	 */
	public function query( $query );
}
