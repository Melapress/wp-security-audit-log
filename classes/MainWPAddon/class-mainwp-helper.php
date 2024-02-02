<?php
/**
 * MainWP server helper addon.
 *
 * @package    wsal
 * @subpackage mainwp
 * @copyright  2024 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 *
 * @since 5.0.0
 */

namespace WSAL\MainWP;

use WSAL\MainWP\MainWP_Addon;
use WSAL\Controllers\Alert_Manager;
use WSAL\Entities\Archive\Delete_Records;
use WSAL\MainWPExtension\Alert;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Migration class
 */
if ( ! class_exists( '\WSAL\MainWP\MainWP_Helper' ) ) {

	/**
	 * Responsible for all the mainWP helper functionalities.
	 *
	 * @package WSAL\MainWP
	 *
	 * @since 5.0.0
	 */
	class MainWP_Helper {

		/**
		 * Inits the class and sets the hooks.
		 *
		 * @return void
		 *
		 * @since 5.0.0
		 */
		public static function init() {
			\add_action( 'wsal_list_view_top_navigation', array( __CLASS__, 'show_top_navigation' ) );
			\add_action( 'wp_ajax_retrieve_events_manually', array( __CLASS__, 'retrieve_events_manually' ) );
		}

		/**
		 * Attaches the site selector in the top navigation of the log list view.
		 *
		 * @return void
		 *
		 * @since 5.0.0
		 */
		public static function show_top_navigation() {
			$wsal_child_sites = MainWP_Addon::get_wsal_child_sites();
			if ( is_array( $wsal_child_sites ) && count( $wsal_child_sites ) > 0 ) {
				$current_site = MainWP_Settings::get_view_site_id();

				$mainwp_child_sites = MainWP_Settings::get_mwp_child_sites();
				?>
				<div class="mwp-ssa mwp-ssa-top">
					<select class="mwp-ssas">
						<option value="0"><?php esc_html_e( 'All Sites', 'mwp-al-ext' ); ?></option>
						<option value="dashboard" <?php selected( $current_site, 'dashboard' ); ?>><?php esc_html_e( 'MainWP Dashboard', 'mwp-al-ext' ); ?></option>
						<?php
						if ( is_array( $wsal_child_sites ) ) {
							foreach ( $wsal_child_sites as $site_id => $site_data ) {
								$key = array_search( $site_id, array_column( $mainwp_child_sites, 'id' ), false );
								if ( false !== $key ) {
									?>
									<option value="<?php echo esc_attr( $mainwp_child_sites[ $key ]['id'] ); ?>"
										<?php selected( (int) $mainwp_child_sites[ $key ]['id'], $current_site ); ?>>
										<?php echo esc_html( $mainwp_child_sites[ $key ]['name'] ) . ' (' . esc_html( $mainwp_child_sites[ $key ]['url'] ) . ')'; ?>
									</option>
									<?php
								}
							}
						}
						?>
					</select>
					<input type="button" class="button" id="mwpal-wsal-manual-retrieve" value="<?php esc_html_e( 'Retrieve Activity Logs Now', 'mwp-al-ext' ); ?>" />
				</div>
				<?php
			}
		}

		/**
		 * Delete Events from Child Sites.
		 *
		 * @since 5.0.0
		 *
		 * @param integer $site_id - Child site ID.
		 */
		public static function delete_site_events( $site_id = 0 ) {
			if ( $site_id ) {
				// Delete events by site id.
				Delete_Records::delete( array(), 0, array( 'site_id = %s ' => intval( $site_id ) ) );
			}
		}

		/**
		 * Retrieve Events Manually.
		 *
		 * @since 5.0.0
		 */
		public static function retrieve_events_manually() {
			// Get MainWP sites.
			$mwp_sites = MainWP_Addon::get_wsal_child_sites();

			if ( ! empty( $mwp_sites ) ) {
				$trigger_retrieving = true; // Event 7711.
				$trigger_ready      = true; // Event 7712.
				$server_ip          = MainWP_Settings::get_server_ip(); // Get server IP.

				foreach ( $mwp_sites as $site_id => $site ) {
					// Delete events by site id.
					self::delete_site_events( $site_id );

					// Fetch events by site id.
					$sites_data[ $site_id ] = self::fetch_site_events( $site_id, $trigger_retrieving );

					// Set $trigger_retrieving to false to avoid logging 7711 multiple times.
					$trigger_retrieving = false;

					if ( $trigger_ready && ( isset( $sites_data[ $site_id ]['events'] ) || isset( $sites_data[ $site_id ]['incompatible__skipped'] ) ) ) {
						// Extension is ready after retrieving.
						Alert_Manager::trigger_event(
							7712,
							array(
								'mainwp_dash' => true,
								'Username'    => 'System',
								'ClientIP'    => ! empty( $server_ip ) ? $server_ip : false,
							)
						);
						$trigger_ready = false;
					}
				}
				// Set child site events.
				self::set_site_events( $sites_data );
			}
		}

		/**
		 * Fetch Events from Child Sites.
		 *
		 * @since 5.0.0
		 *
		 * @param integer $site_id            - Child site id.
		 * @param bool    $trigger_retrieving - True if trigger retrieve events alert.
		 * @param array   $post_data          - MainWP post data.
		 *
		 * @return array
		 */
		public static function fetch_site_events( $site_id = 0, $trigger_retrieving = true, $post_data = array() ) {
			$sites_data = array();

			if ( $site_id ) {

				// Get server IP.
				$server_ip = MainWP_Settings::get_server_ip();

				if ( $trigger_retrieving ) {
					// Extension has started retrieving.
					Alert_Manager::trigger_event(
						7711,
						array(
							'mainwp_dash' => true,
							'Username'    => 'System',
							'ClientIP'    => ! empty( $server_ip ) ? $server_ip : false,
						)
					);
				}

				// Post data for child sites.
				if ( empty( $post_data ) ) {
					$post_data = array(
						'events_count' => MainWP_Settings::get_child_site_events(),
					);
				}

				// Call to child sites to fetch WSAL events.
				$sites_data = MainWP_Addon::make_api_call( $site_id, 'get_events', $post_data );

			}

			return $sites_data;
		}

		/**
		 * Save Events from Child Sites.
		 *
		 * @since 5.0.0
		 *
		 * @param array $sites_data - Sites data.
		 */
		public static function set_site_events( $sites_data = array() ) {
			if ( ! empty( $sites_data ) && is_array( $sites_data ) ) {
				// Get MainWP child sites.
				$mwp_sites = MainWP_Addon::get_wsal_child_sites();

				// Get server IP.
				$server_ip = MainWP_Settings::get_server_ip();

				foreach ( $sites_data as $site_id => $site_data ) {
					// If $site_data doesn't have the keys we expected then it failed to retrieve logs.
					if ( ! empty( $site_data ) && ! ( isset( $site_data['events'] ) && isset( $site_data['users'] ) ) ) {
						// Search for the site data.
						$key = array_search( $site_id, array_column( $mwp_sites, 'id' ), false );

						if ( false !== $key && isset( $mwp_sites[ $key ] ) ) {
							// Extension is unable to retrieve events.
							Alert_Manager::trigger_event(
								7710,
								array(
									'friendly_name' => $mwp_sites[ $key ]['name'],
									'site_url'      => $mwp_sites[ $key ]['url'],
									'site_id'       => $mwp_sites[ $key ]['id'],
									'mainwp_dash'   => true,
									'Username'      => 'System',
									'ClientIP'      => ! empty( $server_ip ) ? $server_ip : false,
								)
							);
						}
					} elseif ( empty( $site_data ) || ! isset( $site_data['events'] ) ) {
						continue;
					}

					if ( isset( $site_data['events'] ) ) {
						self::log_events( $site_data['events'], $site_id );
					}

					if ( isset( $site_data['users'] ) ) {
						self::save_child_site_users( $site_id, $site_data['users'] );
					}
				}
			}
		}

		/**
		 * Log events in the database.
		 *
		 * @param array   $events  – Activity Log Events.
		 * @param integer $site_id – Site ID according to MainWP.
		 *
		 * @return void
		 *
		 * @since 5.0.0
		 */
		public static function log_events( $events, $site_id ) {
			if ( empty( $events ) || ! is_array( $events ) ) {
				return;
			}

			foreach ( $events as $event ) {
				add_filter(
					'wsal_database_site_id_value',
					( function () use ( $site_id ) {
						return $site_id;
					} ),
					10,
					2
				);

				add_filter(
					'wsal_database_timestamp_value',
					( function () use ( $event ) {
						return $event['created_on'];
					} ),
					10,
					2
				);
                if ( null === $event['meta_data']['CurrentUserID'] ) {
                    $event['meta_data']['CurrentUserID'] = 0;
                }
				Alert_Manager::log( $event['alert_id'], $event['meta_data'] );
			}
		}

		/**
		 * Save child site users.
		 *
		 * @param integer $site_id - Site id.
		 * @param array   $users   - Array of site users.
		 *
		 * @since 5.0.0
		 */
		public static function save_child_site_users( $site_id, $users ) {
			// Get stored site users.
			$child_site_users = MainWP_Settings::get_option_value( 'wsal-child-users', array() );

			// Set the users.
			$child_site_users[ $site_id ] = $users;

			// Save them.
			MainWP_Settings::set_option_value( 'wsal-child-users', $child_site_users );
		}

		/**
		 * Get child site users.
		 *
		 * @return array
		 *
		 * @since 5.0.0
		 */
		public static function get_child_site_users() {
			return MainWP_Settings::get_option_value( 'wsal-child-users', array() );
		}
	}
}
