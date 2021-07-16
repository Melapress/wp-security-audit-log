<?php
/**
 * Interface: TrackerInterface
 *
 * Helper class used for tracking various bits of network wide data.
 *
 * @since   3.5.2
 * @package wsal
 */

namespace WSAL\Multisite\NetworkWide;

/**
 * The interface that ensures network wide data tracking is handled with a
 * consistent public interface.
 *
 * @since 3.5.2
 */
interface TrackerInterface {

	/**
	 * The entrypoint to setup of the tracking.
	 *
	 * NOTE: Check for is_multisite() before calling setup.
	 *
	 * @method setup
	 * @since  3.5.2
	 * @return void
	 */
	public function setup();

	/**
	 * Tests if the actions need run to store update this sites and the network
	 * sites cached options for CPTs.
	 *
	 * Returns true or false based on the current sites option value being
	 * present and considered valid.
	 *
	 * @method conditions
	 * @since  3.5.2
	 * @return bool
	 */
	public function conditions();

	/**
	 * The actions that are used to track CPT registration and store the list
	 * at a later point.
	 *
	 * @method actions
	 * @since  3.5.2
	 * @return void
	 */
	public function actions();

	/**
	 * Return a list of data about a specific requested site, or the network
	 * wide list of data otherwise. Empty array if neither exist.
	 *
	 * @method get_network_data_list
	 * @since  3.5.2
	 * @param  integer $site_id if a specific site is there. This is technically nullable type but for back compat isn't.
	 * @return array
	 */
	public static function get_network_data_list( $site_id = 0 );

	/**
	 * Should setup the $data property to hold it's data. In format of a
	 * single dimentional array.
	 *
	 * @method generate_data
	 * @since  3.5.2
	 * @return void
	 */
	public function generate_data();
}
