<?php
/**
 * Occurrence Interface.
 *
 * Interface used by the Occurrence.
 *
 * @package wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface used by the Occurrence.
 *
 * @package wsal
 */
interface WSAL_Adapters_OccurrenceInterface {

	/**
	 * Returns all metadata related to this event.
	 *
	 * @param WSAL_Models_Occurrence $occurrence - Occurrence model instance.
	 *
	 * @return WSAL_Models_Meta[]
	 * @see WSAL_Adapters_MySQL_ActiveRecord::LoadArray()
	 */
	public function GetMultiMeta( $occurrence );

	/**
	 * Loads a meta item given its name.
	 *
	 * @param object $occurrence - Occurrence model instance.
	 * @param string $name - Meta name.
	 *
	 * @return WSAL_Models_Meta The meta item, be sure to check if it was loaded successfully.
	 * @see WSAL_Adapters_MySQL_ActiveRecord::Load()
	 */
	public function GetNamedMeta( $occurrence, $name );

	/**
	 * Returns the first meta value from a given set of names.
	 * Useful when you have a mix of items that could provide
	 * a particular detail.
	 *
	 * @param object $occurrence - Occurrence model instance.
	 * @param array $names - List of meta names.
	 *
	 * @return WSAL_Models_Meta The first meta item that exists.
	 */
	public function GetFirstNamedMeta( $occurrence, $names );

	/**
	 * Gets occurrences of the same type by IP and Username within specified time frame.
	 *
	 * @param array $args - User arguments.
	 *
	 * @return WSAL_Models_Occurrence[]
	 */
	public function CheckKnownUsers( $args = array() );

	/**
	 * Gets occurrences of the same type by IP within specified time frame.
	 *
	 * @param array $args - User arguments.
	 *
	 * @return WSAL_Models_Occurrence[]
	 */
	public function CheckUnknownUsers( $args = array() );

	/**
	 * Gets occurrence by Post_id.
	 *
	 * @param int $post_id - Post ID.
	 *
	 * @return WSAL_Models_Occurrence[]
	 */
	public function GetByPostID( $post_id );

	/**
	 * Gets occurrences of the alert 1003.
	 *
	 * @param array $args - User arguments.
	 *
	 * @return WSAL_Models_Occurrence[]
	 */
	public function check_alert_1003( $args = array() );

	/**
	 * Retrieves occurrences that have metadata that needs to be migrated to the occurrences table. This relates to the
	 * database schema change done in version 4.4.0.
	 *
	 * @param int $limit
	 *
	 * @return WSAL_Models_Occurrence[]
	 * @since 4.4.0
	 */
	public function get_all_with_meta_to_migrate( $limit );
}
