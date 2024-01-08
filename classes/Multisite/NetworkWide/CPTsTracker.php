<?php
/**
 * Class: NetworkMultisiteTracker
 *
 * Helper class used for tracking various bits of network wide data.
 *
 * @since   3.5.2
 * @package wsal
 */

namespace WSAL\Multisite\NetworkWide;

/**
 * Tracks what CPTs exist across the network.
 *
 * @since 3.5.2
 */
final class CPTsTracker extends AbstractTracker {

	/**
	 * Options key used to this data.
	 *
	 * @since 3.5.2
	 * @var   string
	 */
	const STORAGE_KEY = 'wsal_networkwide_tracker_cpts';

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
	public function conditions() {
		$conditions_met           = false;
		$local_post_types_wrapper = get_option( self::STORAGE_KEY );
		if (
			! $local_post_types_wrapper ||
			! is_array( $local_post_types_wrapper ) ||
			! isset( $local_post_types_wrapper['timestamp'] ) ||
			(int) $local_post_types_wrapper['timestamp'] + $this->ttl < time()
		) {
			$conditions_met = true;
		}
		return $conditions_met;
	}

	/**
	 * The actions that are used to track CPT registration and store the list
	 * at a later point.
	 *
	 * @method actions
	 * @since  3.5.2
	 * @return void
	 */
	public function actions() {
		add_action( 'wp_loaded', array( $this, 'generate_data' ) );
		add_action( 'wp_loaded', array( $this, 'update_storage_site' ) );
		add_action( 'wp_loaded', array( $this, 'update_storage_network' ) );
	}

	/**
	 * Gets this sites registered post types and stores them in the $data
	 * property for saving at a later point.
	 *
	 * @method generate_data
	 * @since  3.5.2
	 * @return void
	 */
	public function generate_data() {
		$this->data = $this->get_registered_post_types();
	}

	/**
	 * Gets a list of post types registered on this site.
	 *
	 * @method get_registered_post_types
	 * @since  3.5.2
	 * @return array
	 */
	private function get_registered_post_types() {
		$post_types = get_post_types( array(), 'names' );
		$post_types = array_diff( $post_types, array( 'attachment', 'revision', 'nav_menu_item', 'customize_changeset', 'custom_css', 'oembed_cache', 'user_request', 'wp_block' ) );
		$data       = array();
		foreach ( $post_types as $post_type ) {
			$data[] = $post_type;
		}
		return $data;
	}

	/**
	 * Method to store this site data locally to the site.
	 *
	 * Stores the data in an array containing a timestamp for freshness
	 * invalidation at on later checks or updates.
	 *
	 * @method update_storage_site
	 * @since  3.5.2
	 * @return bool
	 */
	public function update_storage_site() {
		$local_data = array(
			'timestamp' => time(),
			'data'      => $this->data,
		);
		return update_option( self::STORAGE_KEY, $local_data );
	}

	/**
	 * Method to store this sites local data as part of the global network wide
	 * data store. This should merge the data rather than overwrite in most
	 * cases.
	 *
	 * @method update_storage_network
	 * @since  3.5.2
	 * @return bool
	 */
	public function update_storage_network() {
		// get any network stored data.
		$network_data    = get_network_option( null, self::STORAGE_KEY );
		$current_blog_id = get_current_blog_id();
		$data_updated    = false;

		if ( false === $network_data ) {
			$network_data         = array();
			$network_data['site'] = array();
		}
		if (
			! isset( $network_data['site'][ $current_blog_id ] )
			|| ( isset( $network_data['site'][ $current_blog_id ] ) && $network_data['site'][ get_current_blog_id() ] !== $this->data )
		) {
			$network_data['site'][ $current_blog_id ] = $this->data;
			// if the network doesn't have data for this site or the data it
			// has is differs then perform the update.
			$network_wide_list = array();
			foreach ( $network_data['site'] as $list ) {
				// loop through each item in a site and add uniques to a list.
				foreach ( $list as $item ) {
					if ( ! in_array( $item, $network_wide_list, true ) ) {
						$network_wide_list[] = $item;
					}
				}
			}
			// save the data on the network with the latest list and the current
			// sites data updated in it.
			$network_data['list'] = $network_wide_list;
			// update the site data on the network.
			$data_updated = update_network_option( null, self::STORAGE_KEY, $network_data );
		}
		return $data_updated;
	}

	/**
	 * Return a list of data about a specific requested site, or the network
	 * wide list of data otherwise. Empty array if neither exist.
	 *
	 * @method get_network_data_list
	 * @since  3.5.2
	 * @param  integer $site_id if a specific site is there. This is technically nullable type but for back compat isn't.
	 * @return array
	 */
	public static function get_network_data_list( $site_id = 0 ) {
		$network_data = get_network_option( null, self::STORAGE_KEY );
		// get the site list requested otherwise get the network list.
		$list = ( 0 !== $site_id && isset( $network_data['site'][ $site_id ] ) ) ? $network_data['site'][ $site_id ] : $network_data['list'];
		return ( ! empty( $list ) ) ? $list : array();
	}
}
