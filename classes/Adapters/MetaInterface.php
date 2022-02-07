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
	 * Deletes meta data belonging to given occurrences.
	 *
	 * @param int[] $occurrence_ids - Array of occurrence IDs.
	 */
	public function DeleteByOccurrenceIds( $occurrence_ids );

	/**
	 * Load meta by name and occurrence id.
	 *
	 * @param string $meta_name - Meta name.
	 * @param int    $occurrence_id - Occurrence ID.
	 */
	public function LoadByNameAndOccurrenceId( $meta_name, $occurrence_id );
}
