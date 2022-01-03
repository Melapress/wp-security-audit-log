<?php
/**
 * Meta Interface.
 *
 * Interface used by the Metadata.
 *
 * @package wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface used by the Metadata.
 *
 * @package wsal
 */
interface WSAL_Adapters_MetaInterface {

	/**
	 * Create a meta object
	 *
	 * @param array $occurrence_ids - Array of meta data.
	 *
	 * @return int ID of the new meta data
	 */
	public function DeleteByOccurrenceIds( $occurrence_ids );

	/**
	 * Load by name and occurrence id.
	 *
	 * @param string $meta_name - Meta name.
	 * @param int    $occurrence_id - Occurrence ID.
	 */
	public function LoadByNameAndOccurrenceId( $meta_name, $occurrence_id );
}
