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
	 * @param object $query - Query object.
	 */
	public function Execute( $query );

	/**
	 * Count query.
	 *
	 * @param object $query - Query object.
	 */
	public function Count( $query );

	/**
	 * Query for deleting records.
	 *
	 * @param object $query - Query object.
	 */
	public function Delete( $query );

	/**
	 * Checks if the adapter is successfully connected.
	 * @return bool True if the adapter is connected. False otherwise.
	 * @since 4.3.2
	 */
	public function IsConnected();
}
