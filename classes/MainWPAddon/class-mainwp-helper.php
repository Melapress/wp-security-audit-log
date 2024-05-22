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

use WSAL\Helpers\Logger;
use WSAL\MainWP\MainWP_Addon;
use WSAL\Helpers\Settings_Helper;
use WSAL\Controllers\Alert_Manager;
use WSAL\Entities\Archive\Delete_Records;
use WSAL\Entities\MainWP_Server_Users;

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

		public const SET_SITE_ID_NUMBER  = 9999;
		public const ROLES_SETTINGS_NAME = 'mainwp_roles';

		public const CRON_JOBS = array(
			'wsal_extract_child_data' => array(
				'time' => 'hourly',
				'hook' => array( __CLASS__, 'retrieve_events_manually' ),
				'args' => array(),
			),
		);

		/**
		 * Inner class cache for all of the sites (as objects).
		 *
		 * @var array
		 *
		 * @since 5.0.0
		 */
		private static $sites = array();

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

			\add_filter( 'wsal_get_user_image', array( __CLASS__, 'get_user_image' ), 10, 2 );
			\add_filter( 'wsal_get_user_html', array( __CLASS__, 'get_user_html' ), 10, 2 );
			\add_filter( 'wsal_get_user_details', array( __CLASS__, 'get_user_details' ), 10, 2 );
			\add_filter( 'wsal_users_search_query', array( __CLASS__, 'add_users' ), 10, 2 );
			\add_filter( 'wsal_main_view_site_id', array( __CLASS__, 'correct_site_id' ), 10 );

			\add_filter( 'wsal_cron_hooks', array( __CLASS__, 'add_cron_hook' ), 10 );
		}

		/**
		 * If there is a site_id parameter, it probably comes from mainwp extension, so lets pass that
		 *
		 * @param int|bool $site_id - The current site id.
		 *
		 * @return int|bool
		 *
		 * @since 5.0.0
		 */
		public static function correct_site_id( $site_id ) {
			global $pagenow;
			$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : false;
			if ( 'admin.php' !== $pagenow ) {
				return;
			}

			if ( in_array( $page, array( 'wsal-auditlog', 'Extensions-Wp-Security-Audit-Log-Premium', 'Extensions-Wp-Security-Audit-Log' ), true ) ) { // Page is admin.php, now check auditlog page.
				if ( isset( $_REQUEST['wsal-cbid'] ) ) {
					$site_id = \sanitize_text_field( \wp_unslash( $_REQUEST['wsal-cbid'] ) );
				}

				return $site_id; // Return if the current page is not auditlog's.
			}

			return $site_id;
		}

		/**
		 * Attaches the site selector in the top navigation of the log list view.
		 *
		 * @return void
		 *
		 * @since 5.0.0
		 */
		public static function show_top_navigation() {
			global $pagenow;

			// Only run the function on audit log custom page.
			$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : false;
			if ( 'admin.php' !== $pagenow ) {
				return;
			}

			$wsal_child_sites = MainWP_Addon::get_wsal_child_sites();
			if ( is_array( $wsal_child_sites ) && count( $wsal_child_sites ) > 0 ) {
				$current_site = MainWP_Settings::get_view_site_id();

				$mainwp_child_sites = MainWP_Settings::get_mwp_child_sites();
				?>
				<div class="mwp-ssa mwp-ssa-top" style="display: inline-block;">
					<select class="mwp-ssas">
						<option value="-1"><?php esc_html_e( 'All Sites', 'wp-security-audit-log' ); ?></option>
						<option value="0" <?php selected( (int) $current_site, 0 ); ?>><?php esc_html_e( 'MainWP Dashboard', 'wp-security-audit-log' ); ?></option>
						<?php
						if ( is_array( $wsal_child_sites ) ) {
							foreach ( $wsal_child_sites as $site_id => $site_data ) {
								$key = array_search( $site_id, array_column( $mainwp_child_sites, 'id' ), false );
								if ( false !== $key ) {
									// $key += self::SET_SITE_ID_NUMBER;
									?>
									<option value="<?php echo esc_attr( $mainwp_child_sites[ $key ]['id'] + self::SET_SITE_ID_NUMBER ); ?>"
										<?php selected( (int) $mainwp_child_sites[ $key ]['id'] + self::SET_SITE_ID_NUMBER, $current_site ); ?>>
										<?php echo esc_html( $mainwp_child_sites[ $key ]['name'] ) . ' (' . esc_html( $mainwp_child_sites[ $key ]['url'] ) . ')'; ?>
									</option>
									<?php
								}
							}
						}
						?>
					</select>
					<input type="button" class="button" id="mwpal-wsal-manual-retrieve" value="<?php esc_html_e( 'Retrieve Activity Logs Now', 'wp-security-audit-log' ); ?>" />
				</div>
				<script>
					jQuery( document ).ready( function() {
					/**
					 * Site events switch handler.
					 */
					jQuery( '.mwp-ssas' ).on( 'change', function() {
						var value = jQuery( this ).val();
						jQuery( '#mwpal-site-id' ).val( value );
						jQuery( '#wsal-cbid' ).val( value );

						jQuery( '#audit-log-viewer' ).submit();
					});
					
					/**
					 * Retrieve Logs Manually
					 */
					jQuery( '#mwpal-wsal-manual-retrieve' ).click( function() {
						var { __ } = wp.i18n;
						const retrieveBtn = jQuery( this );
						retrieveBtn.attr( 'disabled', true );
						retrieveBtn.val( __( 'Retrieving Logs...', 'wp-security-audit-log' ), );

						jQuery.post( window['ajaxurl'], {
							action: 'retrieve_events_manually',
							nonce: '<?php echo esc_attr( wp_create_nonce( 'wsal-notifications-script-nonce' ) ); ?>',
						}, function() {
							location.reload();
						}).fail(function(xhr, status, error) {
							alert(xhr.responseJSON.data[0].message);
						});
					});
				} );
				</script>
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
				Delete_Records::delete( array(), 0, array( 'site_id = %s ' => intval( ( (int) $site_id + self::SET_SITE_ID_NUMBER ) ) ) );
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

				foreach ( $mwp_sites as $site_id => $site ) {
					self::sync_site( (int) $site_id );
				}
			}
		}

		/**
		 * Syncs individual sites with the WSAL
		 *
		 * @param integer $site_id - The ID of the site to sync with.
		 *
		 * @return void|\WP_Error
		 *
		 * @since 5.0.0
		 */
		public static function sync_site( int $site_id ) {
			// Get MainWP child sites.
			$mwp_sites = MainWP_Settings::get_mwp_child_sites();

			$key = array_search( $site_id, array_column( $mwp_sites, 'id' ), false );

			if ( false !== $key && isset( $mwp_sites[ $key ] ) ) {
				// $trigger_retrieving = true; // Event 7711.
				// $trigger_ready      = true; // Event 7712.
				$server_ip = MainWP_Settings::get_server_ip(); // Get server IP.

				$site_data = array();

				// Delete events by site id.
				// self::delete_site_events( $site_id );.

				// Fetch events by site id.
				$site_data[ $site_id ] = self::fetch_site_events( $site_id );

				if ( ! isset( $site_data[ $site_id ] ) || isset( $site_data[ $site_id ]['error'] ) ) {
					\error_log( 'WSAL - MainWP response: ' . $site_data[ $site_id ]['error'] );
					Logger::log( $site_data[ $site_id ]['error'] );
					return new \WP_Error( 'MainWP_error', $site_data[ $site_id ]['error'] );
				}

				if ( ( isset( $site_data[ $site_id ]['events'] ) || isset( $site_data[ $site_id ]['incompatible__skipped'] ) ) ) {
					// Extension is ready after retrieving.
					Alert_Manager::trigger_event(
						7712,
						array(
							'mainwp_dash' => true,
							'Username'    => 'System',
							'ClientIP'    => ! empty( $server_ip ) ? $server_ip : false,
						)
					);

				}

				if ( \is_array( $site_data ) && ! empty( $site_data ) ) {
					$key = \array_key_first( $site_data );
					if ( isset( $site_data[ $key ]['users'] ) ) {
						$error = new \WP_Error( '001', \esc_html__( 'Client site is using older version of the plugin - please update all client sites', 'wp-security-audit-log' ), 'Some information' );
							\wp_send_json_error( $error, 400 );
							exit;
					}
				}
				// Set child site events.
				self::set_site_events( $site_data );

				$roles = self::fetch_site_roles( $site_id );

				self::set_collected_roles( (array) $roles );
			}
		}

		/**
		 * Stores the collected roles in the database.
		 *
		 * @param array $roles - The array of roles to be stored in the database - it compares that to what we currently have, and stores the differences, the tricky part is the translation - it stores the roles inner keys (which is used by WP and us) and updates the translation (if one is present). Meaning that administrator for instance will be stored once but with the latest tranlation from the latest site parsed - customers have to keep that in mind.
		 *
		 * @return void
		 *
		 * @since 5.0.0
		 */
		public static function set_collected_roles( array $roles ) {
			$stored_roles = Settings_Helper::get_option_value( self::ROLES_SETTINGS_NAME, array() );

			if ( empty( $stored_roles ) ) {
				Settings_Helper::set_option_value( self::ROLES_SETTINGS_NAME, $roles );
			} else {
				Settings_Helper::set_option_value( self::ROLES_SETTINGS_NAME, \array_merge( $stored_roles, $roles ) );
			}
		}

		/**
		 * Returns the stored roles (the ones collected from the mainwp child sites).
		 *
		 * @return array
		 *
		 * @since 5.0.0
		 */
		public static function get_collected_roles(): array {
			return (array) Settings_Helper::get_option_value( self::ROLES_SETTINGS_NAME, array() );
		}

		/**
		 * Returns the most recent event stored for the given site.
		 *
		 * @param integer $site_id - The ID of the site to retrieve the most recent event.
		 * @param bool    $first - Flag which tells in which direction we should go extracting the data - if true, the oldest event is extracted.
		 *
		 * @return array
		 *
		 * @since 5.0.0
		 */
		public static function get_latest_event_by_siteid( int $site_id, bool $first = false ): array {
			add_filter(
				'wsal_alter_site_id',
				( function () use ( $site_id ) {
					return ( (int) $site_id + self::SET_SITE_ID_NUMBER );
				} ),
				10
			);

			$event = (array) Alert_Manager::get_latest_events( 1, false, $first );

			return $event;
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

				if ( $site_id > 0 ) {
					$site_info                  = MainWP_Settings::get_mwp_child_site_by_id( $site_id );
					$post_data['site_url']      = $site_info['url'];
					$post_data['inner_site_id'] = $site_id;

					$last_event = self::get_latest_event_by_siteid( $site_id );

					$first_event = array();

					if ( isset( $last_event ) && ! empty( $last_event ) && isset( $last_event[0] ) && isset( $last_event[0]['created_on'] ) ) {
						$post_data['newer_than'] = $last_event[0]['created_on'];

						$first_event = self::get_latest_event_by_siteid( $site_id, true );

					}

					// Call to child sites to fetch WSAL events.
					$sites_data = MainWP_Addon::make_api_call( $site_id, 'get_events', $post_data );

					$older_sites_data = array();

					if ( isset( $first_event ) && ! empty( $first_event ) && isset( $first_event[0] ) && isset( $first_event[0]['created_on'] ) ) {
						unset( $post_data['newer_than'] );
						$post_data['older_than'] = $first_event[0]['created_on'];
						$older_sites_data        = MainWP_Addon::make_api_call( $site_id, 'get_events', $post_data );
					}

					if ( ! empty( $older_sites_data ) ) {
						$sites_data = \array_merge( $sites_data, $older_sites_data );
					}
				}
			}

			return $sites_data;
		}

		/**
		 * Collects all of the roles assigned to the child site.
		 *
		 * @param integer $site_id - The ID of the site to fetch data from.
		 *
		 * @return array
		 *
		 * @since 5.0.0
		 */
		public static function fetch_site_roles( int $site_id = 0 ): array {
			$sites_roles = array();

			$post_data = array();
			if ( $site_id ) {

				if ( $site_id > 0 ) {
					$site_info                  = MainWP_Settings::get_mwp_child_site_by_id( $site_id );
					$post_data['site_url']      = $site_info['url'];
					$post_data['inner_site_id'] = $site_id;

					// Call to child sites to fetch site's roles.
					$sites_roles = MainWP_Addon::make_api_call( $site_id, 'wsal_get_roles', $post_data );
				}
			}

			return $sites_roles;
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
					if ( is_array( $site_data ) && ! empty( $site_data ) ) {
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
						} else {
							self::log_events( $site_data, $site_id );
						}
					} elseif ( empty( $site_data ) || ! isset( $site_data['events'] ) ) {
						continue;
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
				\add_filter(
					'wsal_database_site_id_value',
					( function () use ( $site_id ) {
						return ( (int) $site_id + self::SET_SITE_ID_NUMBER );
					} ),
					10,
					2
				);

				\add_filter(
					'wsal_database_timestamp_value',
					( function () use ( $event ) {
						return $event['created_on'];
					} ),
					10,
					2
				);
				// if ( null === $event['meta_data']['CurrentUserID'] ) {
				// $event['meta_data']['CurrentUserID'] = 0;
				// }

				if ( isset( $event['meta_data']['UserData'] ) && isset( $event['meta_data']['UserData']['ID'] ) ) {
					$saved_result = MainWP_Server_Users::save_user( $event['meta_data']['UserData'], (int) $site_id + self::SET_SITE_ID_NUMBER );
					if ( isset( $event['user_id'] ) && ! empty( $event['user_id'] ) ) {
						$event['user_id']                    = $saved_result['inner_id'];
						$event['meta_data']['CurrentUserID'] = $saved_result['inner_id'];
					}
				}

				Alert_Manager::log( $event['alert_id'], $event['meta_data'] );
			}
		}

		/**
		 * Extracts the user avatar if that call comes from external site
		 *
		 * @param string $image - Currently extracted image.
		 * @param array  $item_data - Array with all the item data.
		 *
		 * @return string
		 *
		 * @since 5.0.0
		 */
		public static function get_user_image( $image, $item_data ) {
			if ( 0 < ( (int) $item_data['site_id'] - self::SET_SITE_ID_NUMBER ) ) {
				if ( isset( $item_data['object'] ) && isset( $item_data['meta_values']['UserData']['ID'] ) ) {
					if ( isset( $item_data['meta_values'] ) && isset( $item_data['meta_values']['UserData'] ) && isset( $item_data['meta_values']['UserData']['user_email'] ) ) {
						$user_email = $item_data['meta_values']['UserData']['user_email'];
						$image      = \get_avatar( $user_email, 32 );
					}
				}
			}

			return $image;
		}

		/**
		 * Builds user HTML string for the data passed
		 *
		 * @param string $uhtml - Currently built user HTML string.
		 * @param array  $item_data - Array with the data from the event.
		 *
		 * @return string
		 *
		 * @since 5.0.0
		 */
		public static function get_user_html( $uhtml, $item_data ) {
			if ( 0 < ( (int) $item_data['site_id'] - self::SET_SITE_ID_NUMBER ) ) {
				if ( isset( $item_data['object'] ) && isset( $item_data['meta_values']['UserData']['ID'] ) ) {

					if ( isset( $item_data['meta_values'] ) && isset( $item_data['meta_values']['UserData'] ) && isset( $item_data['meta_values']['UserData']['ID'] ) ) {
						$mwp_sites = MainWP_Settings::get_mwp_child_sites();

						$site_index = array_search( ( $item_data['site_id'] - self::SET_SITE_ID_NUMBER ), array_column( $mwp_sites, 'id' ), false );

						if ( false !== $site_index && isset( $mwp_sites[ $site_index ] ) ) {
							$site_url = $mwp_sites[ $site_index ]['url'];
							$user_url = \add_query_arg( 'user_id', $item_data['meta_values']['UserData']['ID'], trailingslashit( $site_url ) . 'wp-admin/user-edit.php' );

							$tooltip = self::get_tooltip_user_content( $item_data['meta_values']['UserData'] );

							$uhtml = '<a class="tooltip" data-darktooltip="' . esc_attr( $tooltip ) . '" data-user="' . $item_data['meta_values']['UserData']['display_name'] . '" href="' . $user_url . '" target="_blank">' . esc_html( self::get_display_label( $item_data['meta_values']['UserData'] ) ) . '</a>';
						}
					}
				}
			}

			return $uhtml;
		}

		/**
		 * Builds user details array for the data passed
		 *
		 * @param array|string|bool $user - The extracted user data.
		 * @param array             $item_data - Array with the data from the event.
		 *
		 * @return array|bool
		 *
		 * @since 5.0.0
		 */
		public static function get_user_details( $user, array $item_data ) {
			$site_id = $item_data['site_id'];

			// Check if site id is integer - if not that means that data had been normalized and there should be 'inner_site_id' column present - use that instead.
			if ( false === filter_var( $site_id, FILTER_VALIDATE_INT ) ) {
				if ( isset( $item_data['inner_site_id'] ) ) {
					$site_id = $item_data['inner_site_id'];
				} else {
					return $user;
				}
			}

			if ( 0 < ( (int) $site_id - self::SET_SITE_ID_NUMBER ) ) {
				if ( isset( $item_data['object'] ) && isset( $item_data['meta_values']['UserData']['ID'] ) ) {

					if ( isset( $item_data['meta_values'] ) && isset( $item_data['meta_values']['UserData'] ) && isset( $item_data['meta_values']['UserData']['ID'] ) ) {
						$mwp_sites = MainWP_Settings::get_mwp_child_sites();

						$site_index = array_search( ( $site_id - self::SET_SITE_ID_NUMBER ), array_column( $mwp_sites, 'id' ), false );

						if ( false !== $site_index && isset( $mwp_sites[ $site_index ] ) ) {
							$site_url = $mwp_sites[ $site_index ]['url'];
							$user_url = add_query_arg( 'user_id', $item_data['meta_values']['UserData']['ID'], trailingslashit( $site_url ) . 'wp-admin/user-edit.php' );

							$user = array(
								'username'       => $item_data['meta_values']['UserData']['display_name'],
								'firstname'      => $item_data['meta_values']['UserData']['first_name'],
								'lastname'       => $item_data['meta_values']['UserData']['last_name'],
								'display_name'   => $item_data['meta_values']['UserData']['display_name'],
								'user_email'     => $item_data['meta_values']['UserData']['user_email'],
								'nicename'       => $item_data['meta_values']['UserData']['user_nicename'],
								'first_and_last' => $item_data['meta_values']['UserData']['first_name'] . ' ' . $item_data['meta_values']['UserData']['last_name'],
							);
						}
					}
				}
			}

			return $user;
		}

		/**
		 * Collects users information from the dedicated MainWP table with users and if there are any users - merges them in the currently collected ones
		 *
		 * @param array  $users - Users collected so far.
		 * @param string $data - Search string for users collecting.
		 *
		 * @return array
		 *
		 * @since 5.0.0
		 */
		public static function add_users( $users, $data ): array {

			$main_wp_users = MainWP_Server_Users::load_array(
				' `ID` = %d OR' .
				' `user_login` = %s OR' .
				' `first_name` = %s OR' .
				' `last_name` = %s OR' .
				' `display_name` = %s OR' .
				' `user_email` = %s OR' .
				' `user_nicename` = %s',
				array_fill(
					0,
					7,
					( ( is_array( $data ) ? reset( $data ) : $data ) )
				)
			);

			if ( is_array( $main_wp_users ) && ! empty( $main_wp_users ) ) {
				$users = array_merge( $users, $main_wp_users );
			}

			return $users;
		}

		/**
		 * This function collects and returns all the enable sites as an array of sites object (mockups is the closest thing to say)
		 * Point of this is to have as much as possible, WP compatibility with the core sites.
		 *
		 * What this do is to extract all global MainWP sites, then all sites with enabled WSAL, and then prepare the properties of
		 * every one of them as an object.
		 * Each object contains the id (not the real one used by MainWP, but our internal one (as we are prepared to work on multisite
		 * with MainWP enabled), and the name of the site - the MainWP doesn't store one se we are using the URL of the site twice.
		 *
		 * @return array
		 *
		 * @since 5.0.0
		 */
		public static function get_all_sites_array(): array {
			if ( empty( self::$sites ) ) {

				$object            = new \stdClass();
				$object->blogname  = esc_html__( 'MainWP Dashboard', 'wp-security-audit-log' );
				$object->siteurl   = esc_attr( \esc_url( \network_admin_url( 'admin.php?page=mainwp_tab' ) ) );
				$object->blog_id   = 0;
				$object->site_name = esc_html__( 'MainWP Dashboard', 'wp-security-audit-log' );

				self::$sites[0] = $object;

				$wsal_child_sites   = MainWP_Addon::get_wsal_child_sites();
				$mainwp_child_sites = MainWP_Settings::get_mwp_child_sites();
				if ( is_array( $wsal_child_sites ) && count( $wsal_child_sites ) > 0 ) {
					foreach ( $wsal_child_sites as $site_id => $site_data ) {
						$key = array_search( $site_id, array_column( $mainwp_child_sites, 'id' ), false );
						if ( false !== $key ) {
							$object            = new \stdClass();
							$object->blogname  = $mainwp_child_sites[ $key ]['name'];
							$object->siteurl   = $mainwp_child_sites[ $key ]['url'];
							$object->blog_id   = $mainwp_child_sites[ $key ]['id'] + self::SET_SITE_ID_NUMBER;
							$object->site_name = $mainwp_child_sites[ $key ]['name'] . ' (' . esc_html( $mainwp_child_sites[ $key ]['url'] ) . ')';

							self::$sites[ $site_id + self::SET_SITE_ID_NUMBER ] = $object;
						}
					}
				}
			}

			return self::$sites;
		}

		/**
		 * Searches users by given field list. Returns array of found users as WP compatible objects.
		 *
		 * @param array $fields - The field list to search in.
		 * @param array $search - The search term.
		 * @param bool  $add_site - Should the site name be added to the user name data? Default - true.
		 *
		 * @return array
		 *
		 * @since 5.0.0
		 */
		public static function find_users_by( array $fields, array $search, bool $add_site = true ): array {

			$users = array();

			if ( empty( $fields ) ) {
				$fields = array( 'inner_id' );
			} else {
				foreach ( $fields as $key => $field ) {
					if ( ! isset( MainWP_Server_Users::get_fields()[ $field ] ) ) {
						unset( $fields[ $key ] );
					}

					if ( 'ID' === $field ) {
						$fields[ $key ] = 'inner_id';
					}
				}
			}

			if ( ! empty( $fields ) ) {
				$sql_string = '';

				$vals = array();

				foreach ( $search as $string ) {
					$sql_string .= ' ( ';
					foreach ( $fields as $key => $field ) {
						$sql_string .= ' `' . $field . '` LIKE %s OR';
						$vals[]      = $string;
					}

					$sql_string = rtrim( $sql_string, 'OR' ) . ' ) OR';
				}

				$sql_string = rtrim( $sql_string, 'OR' );

				$found_users = MainWP_Server_Users::load_array(
					$sql_string,
					$vals
				);

				foreach ( $found_users as $user ) {
					$object                = new \stdClass();
					$object->ID            = $user['inner_id'];
					$object->user_login    = $user['user_login'] . ( ( $add_site ) ? ' (' . self::get_all_sites_array()[ $user['site_id'] ]->blogname . ')' : '' );
					$object->first_name    = $user['first_name'];
					$object->last_name     = $user['last_name'];
					$object->display_name  = $user['display_name'] . ( ( $add_site ) ? ' (' . self::get_all_sites_array()[ $user['site_id'] ]->blogname . ')' : '' );
					$object->user_email    = $user['user_email'];
					$object->user_nicename = $user['user_nicename'];
					$users[]               = $object;
				}
			}

			return $users;
		}

		/**
		 * Adds cron for extracting child sites data.
		 *
		 * @param array $hooks - Array with current hooks definitions.
		 *
		 * @return array
		 *
		 * @since 5.0.0
		 */
		public static function add_cron_hook( $hooks ): array {
			$hooks = array_merge( $hooks, self::CRON_JOBS );

			return $hooks;
		}

		/**
		 * Builds display label for user extracted from the item data
		 *
		 * @param array $user_data - Array with all the user data collected from event.
		 *
		 * @return string
		 *
		 * @since 5.0.0
		 */
		private static function get_display_label( array $user_data ): string {
			$user_label_setting = Settings_Helper::get_option_value( 'type_username', 'display_name' );

			if ( 'display_name' === $user_label_setting && ! empty( $user_data['display_name'] ) ) {
				return $user_data['display_name'];
			}

			if ( 'first_last_name' === $user_label_setting && ( ! empty( $user_data['first_name'] ) || ! empty( $user_data['last_name'] ) ) ) {
				return trim(
					implode(
						' ',
						array(
							$user_data['first_name'],
							$user_data['last_name'],
						)
					)
				);
			}

			if ( ! isset( $user_data['username'] ) ) {
				return 'Unknown user';
			}

			return $user_data['username'];
		}

		/**
		 * Builds user tooltip to show on mouse over the username
		 *
		 * @param array $user_data - Array with all the user data collected from event.
		 *
		 * @return string
		 *
		 * @since 5.0.0
		 */
		private static function get_tooltip_user_content( array $user_data ) {

			$tooltip  = '<strong>' . esc_attr__( 'Username: ', 'wp-security-audit-log' ) . '</strong>' . $user_data['display_name'] . '</br>';
			$tooltip .= ( ! empty( $user_data['first_name'] ) ) ? '<strong>' . esc_attr__( 'First name: ', 'wp-security-audit-log' ) . '</strong>' . $user_data['first_name'] . '</br>' : '';
			$tooltip .= ( ! empty( $user_data['last_name'] ) ) ? '<strong>' . esc_attr__( 'Last Name: ', 'wp-security-audit-log' ) . '</strong>' . $user_data['last_name'] . '</br>' : '';
			$tooltip .= '<strong>' . esc_attr__( 'Email: ', 'wp-security-audit-log' ) . '</strong>' . $user_data['user_email'] . '</br>';
			$tooltip .= '<strong>' . esc_attr__( 'Nickname: ', 'wp-security-audit-log' ) . '</strong>' . $user_data['user_nicename'] . '</br></br>';

			/**
			 * WSAL Filter: `wsal_additional_user_tooltip_content'
			 *
			 * Allows 3rd parties to append HTML to the user tooltip content in audit log viewer.
			 *
			 * @since 4.4.0
			 *
			 * @param string $content Blank string to append to.
			 * @param object  $user  - User data array.
			 */
			$additional_content = apply_filters( 'wsal_additional_user_tooltip_content', '', $user_data );

			$tooltip .= $additional_content;

			return $tooltip;
		}
	}
}
