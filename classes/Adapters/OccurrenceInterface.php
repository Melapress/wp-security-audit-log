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
	 * Get Meta.
	 *
	 * @param object $occurence - Instance of occurence object.
	 */
	public function GetMeta( $occurence );

	/**
	 * Loads a meta item given its name.
	 *
	 * @param object $occurence - Instance of occurence object.
	 * @param string $name - Meta name.
	 */
	public function GetNamedMeta( $occurence, $name );

	/**
	 * Returns the first meta value from a given set of names.
	 * Useful when you have a mix of items that could provide
	 * a particular detail.
	 *
	 * @param object $occurrence - Instance of occurrence object.
	 * @param array  $names - List of Meta names.
	 */
	public function GetFirstNamedMeta( $occurrence, $names );

	/**
	 * Gets occurrences of the same type by IP and Username within specified time frame.
	 *
	 * @param array $args - Arguments.
	 */
	public function CheckKnownUsers( $args = array() );

	/**
	 * Gets occurrences of the same type by IP within specified time frame.
	 *
	 * @param array $args - Arguments.
	 */
	public function CheckUnKnownUsers( $args = array() );
}
