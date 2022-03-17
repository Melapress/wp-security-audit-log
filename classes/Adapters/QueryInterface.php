<?php
/**
 * Query Interface.
 *
 * Interface used by the Query.
 *
 * @package wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface used by the Query.
 *
 * @package wsal
 */
interface WSAL_Adapters_QueryInterface {

	/**
	 * Execute query and return data as $ar_cls objects.
	 *
	 * @param WSAL_Models_Query $query - Query object.
	 */
	public function execute_query( $query );

	/**
	 * Count query.
	 *
	 * @param WSAL_Models_Query $query - Query object.
	 */
	public function count( $query );

	/**
	 * Query for deleting records.
	 *
	 * @param WSAL_Models_Query $query - Query object.
	 */
	public function delete( $query );

	/**
	 * Checks if the adapter is successfully connected.
	 *
	 * @return bool True if the adapter is connected. False otherwise.
	 * @since 4.3.2
	 */
	public function is_connected();

	/**
	 * Deprecated placeholder function.
	 *
	 * @param WSAL_Models_Query $query - Query object.
	 *
	 * @see        WSAL_Adapters_QueryInterface::execute_query()
	 *
	 * @deprecated 4.4.1 Replaced by function execute_query.
	 */
	public function Execute( $query );
}
