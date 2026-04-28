<?php
/**
 * Sensor: User Profile
 *
 * User Profile sensor class file.
 *
 * @since     4.6.0
 * @package   wsal
 * @subpackage sensors
 */

declare(strict_types=1);

namespace WSAL\WP_Sensors;

use WSAL\Helpers\WP_Helper;
use WSAL\Helpers\User_Helper;
use WSAL\Controllers\Alert_Manager;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\WP_Sensors\WP_User_Profile_Sensor' ) ) {
	/**
	 * User Profiles sensor.
	 *
	 * 4001 User created another WordPress user
	 * 4002 The role of a user was changed by another WordPress user
	 * 4003 User has changed his or her password
	 * 4004 User changed another user's password
	 * 4005 User changed his or her email address
	 * 4006 User changed another user's email address
	 * 4007 User was deleted by another user
	 * 4008 User granted Super Admin privileges
	 * 4009 User revoked from Super Admin privileges
	 * 4014 User opened the profile page of another user
	 *
	 * @package    wsal
	 * @subpackage sensors
	 */
	class WP_User_Profile_Sensor {

		/**
		 * List of super admins.
		 *
		 * @var array
		 *
		 * @since 4.5.0
		 */
		private static $old_superadmins;

		/**
		 * Per-request store of recorded user role changes.
		 *
		 * Keyed by user id. Each entry has 'added' and 'removed' arrays populated
		 * by the add_user_role / remove_user_role hooks.
		 *
		 * @var array
		 *
		 * @since 5.6.3
		 */
		private static $pending_role_changes = array();

		/**
		 * Inits the main hooks
		 *
		 * @return void
		 *
		 * @since 4.5.0
		 */
		public static function early_init() {
			add_action( 'profile_update', array( __CLASS__, 'event_user_updated' ), 10, 2 );
			add_action( 'delete_user', array( __CLASS__, 'event_user_deleted' ) );
			add_action( 'wpmu_delete_user', array( __CLASS__, 'event_user_deleted' ) );
			add_action( 'edit_user_profile', array( __CLASS__, 'event_open_profile' ), 10, 1 );
			add_action( 'grant_super_admin', array( __CLASS__, 'get_super_admins' ) );
			add_action( 'revoke_super_admin', array( __CLASS__, 'get_super_admins' ) );
			add_action( 'granted_super_admin', array( __CLASS__, 'event_super_access_granted' ), 10, 1 );
			add_action( 'revoked_super_admin', array( __CLASS__, 'event_super_access_revoked' ), 10, 1 );
			add_action( 'update_user_meta', array( __CLASS__, 'event_application_password_added' ), 10, 4 );
			add_action( 'retrieve_password', array( __CLASS__, 'event_password_reset_link_sent' ), 10, 1 );

			add_action( 'add_user_role', array( __CLASS__, 'record_role_added' ), 10, 2 );
			add_action( 'remove_user_role', array( __CLASS__, 'record_role_removed' ), 10, 2 );
			add_action( 'wp_update_user', array( __CLASS__, 'process_role_changes_for_updated_user' ), 10, 1 );

			add_action( 'admin_page_access_denied', array( __CLASS__, 'event_admin_page_access_denied' ), 10 );

			// We hook into action 'user_register' because it is part of the function 'wp_insert_user'.
			add_action( 'user_register', array( __CLASS__, 'on_user_register' ), 10, 1 );
		}

		/**
		 * Fires when trying to access admin page was denied.
		 *
		 * @return void
		 *
		 * @since 5.4.2
		 */
		public static function event_admin_page_access_denied() {
			$admin_page = '';

			$admin_page = \sanitize_text_field( \wp_unslash( $_GET['page'] ?? '' ) );

			// Get the current admin page file.
			$pagenow = $GLOBALS['pagenow'] ?? '';

			// Construct full page path.
			$full_page = $pagenow;
			if ( $admin_page ) {
				$full_page .= '?page=' . $admin_page;
			}

			Alert_Manager::trigger_event(
				1011,
				array(

					'URL' => $full_page,
				)
			);
		}

		/**
		 * Captures addition of application passwords.
		 *
		 * @param int    $meta_id ID of the metadata entry to update.
		 * @param int    $user_id ID of the user metadata is for.
		 * @param string $meta_key Metadata key.
		 * @param mixed  $_meta_value Metadata value. Serialized if non-scalar.
		 *
		 * @since 4.5.0
		 */
		public static function event_application_password_added( $meta_id, $user_id, $meta_key, $_meta_value ) {

			// Filter global arrays for security.
			$server_array = filter_input_array( INPUT_SERVER );
			if ( ! isset( $server_array['HTTP_REFERER'] ) || ! isset( $server_array['REQUEST_URI'] ) ) {
				return;
			}

			// Check the page which is performing this change.
			$referer_check = pathinfo( \sanitize_text_field( \wp_unslash( $server_array['HTTP_REFERER'] ) ) );
			$referer_check = $referer_check['filename'];
			$referer_check = ( strpos( $referer_check, '.' ) !== false ) ? strstr( $referer_check, '.', true ) : $referer_check;

			$is_correct_referer_and_action = false;

			if ( 'profile' === $referer_check || 'user-edit' === $referer_check ) {
				$is_correct_referer_and_action = true;
			}

			// Ensure we are dealing with the correct request.
			if ( $is_correct_referer_and_action && strpos( $server_array['REQUEST_URI'], '/wp/v2/users/' . $user_id . '/application-passwords' ) !== false ) {

				$old_value = get_user_meta( $user_id, '_application_passwords', true );

				$current_user       = get_user_by( 'id', $user_id );
				$current_userdata   = get_userdata( $user_id );
				$current_user_roles = implode(
					', ',
					array_map(
						array(
							__CLASS__,
							'filter_role_names',
						),
						$current_userdata->roles
					)
				);
				$event_id           = ( 'user-edit' === $referer_check ) ? 4026 : 4025;

				// Note, firstname and lastname fields are purposefully spaces to avoid NULL.
				if ( isset( $_POST['name'] ) ) {
					Alert_Manager::trigger_event(
						$event_id,
						array(
							'roles'         => $current_user_roles,
							'login'         => $current_user->user_login,
							'firstname'     => ( empty( $current_user->user_firstname ) ) ? ' ' : $current_user->user_firstname,
							'lastname'      => ( empty( $current_user->user_lastname ) ) ? ' ' : $current_user->user_lastname,
							'CurrentUserID' => $current_user->ID,
							'friendly_name' => \sanitize_text_field( \wp_unslash( $_POST['name'] ) ),
							'EventType'     => 'added',
						)
					);
				} elseif ( ! empty( $old_value ) && count( $old_value ) > 1 && empty( $_meta_value ) ) {
					// Check if all have been removed.
					$event_id = ( 'user-edit' === $referer_check ) ? 4028 : 4027;

					// Note, firstname and lastname fields are purposefully spaces to avoid NULL.
					Alert_Manager::trigger_event(
						$event_id,
						array(
							'roles'         => $current_user_roles,
							'login'         => $current_user->user_login,
							'firstname'     => ( empty( $current_user->user_firstname ) ) ? ' ' : $current_user->user_firstname,
							'lastname'      => ( empty( $current_user->user_lastname ) ) ? ' ' : $current_user->user_lastname,
							'CurrentUserID' => $current_user->ID,
							'EventType'     => 'revoked',
						)
					);
				} elseif ( count( $_meta_value ) < count( $old_value ) ) {
					// Check the item that was removed.
					$revoked_pw      = array_diff( array_map( 'serialize', $old_value ), array_map( 'serialize', $_meta_value ) );
					$revoked_pw      = array_values( array_map( 'unserialize', $revoked_pw ) );
					$revoked_pw_name = $revoked_pw[0]['name'];
					$event_id        = ( 'user-edit' === $referer_check ) ? 4026 : 4025;

					// Note, firstname and lastname fields are purposefully spaces to avoid NULL.
					Alert_Manager::trigger_event(
						$event_id,
						array(
							'roles'         => $current_user_roles,
							'login'         => $current_user->user_login,
							'firstname'     => ( empty( $current_user->user_firstname ) ) ? ' ' : $current_user->user_firstname,
							'lastname'      => ( empty( $current_user->user_lastname ) ) ? ' ' : $current_user->user_lastname,
							'CurrentUserID' => $current_user->ID,
							'friendly_name' => \sanitize_text_field( $revoked_pw_name ),
							'EventType'     => 'revoked',
						)
					);
				}
			}
		}

		/**
		 * Method: Support for Ultimate Member email change
		 * alert.
		 *
		 * @param int      $user_id - User ID.
		 * @param \WP_User $old_userdata - Old WP_User object.
		 *
		 * @since 4.5.0
		 */
		public static function event_user_updated( $user_id, $old_userdata ) {
			// Get new user data.
			$new_userdata = \get_userdata( $user_id );

			// Alert if user password is changed.
			if ( $old_userdata->user_pass !== $new_userdata->user_pass ) {
				$event      = \get_current_user_id() === $user_id ? 4003 : 4004;
				$user_roles = implode( ', ', array_map( array( __CLASS__, 'filter_role_names' ), $new_userdata->roles ) );
				Alert_Manager::trigger_event(
					$event,
					array(
						'TargetUserID'   => $user_id,
						'TargetUserData' => (object) array(
							'Username'  => $new_userdata->user_login,
							'Roles'     => $user_roles,
							'FirstName' => $new_userdata->user_firstname,
							'LastName'  => $new_userdata->user_lastname,
						),
						'EditUserLink'   => add_query_arg( 'user_id', $user_id, \network_admin_url( 'user-edit.php' ) ),
					)
				);
			}

			// Alert if user email is changed.
			if ( $old_userdata->user_email !== $new_userdata->user_email ) {
				$event      = get_current_user_id() === $user_id ? 4005 : 4006;
				$user_roles = implode( ', ', array_map( array( __CLASS__, 'filter_role_names' ), $new_userdata->roles ) );
				Alert_Manager::trigger_event(
					$event,
					array(
						'TargetUserID'   => $user_id,
						'TargetUsername' => $new_userdata->user_login,
						'OldEmail'       => $old_userdata->user_email,
						'NewEmail'       => $new_userdata->user_email,
						'Roles'          => $user_roles,
						'FirstName'      => $new_userdata->user_firstname,
						'LastName'       => $new_userdata->user_lastname,
						'EditUserLink'   => add_query_arg( 'user_id', $user_id, \network_admin_url( 'user-edit.php' ) ),
						'TargetUserData' => (object) array(
							'Username'  => $new_userdata->user_login,
							'FirstName' => $new_userdata->user_firstname,
							'LastName'  => $new_userdata->user_lastname,
							'Email'     => $new_userdata->user_email,
							'Roles'     => $user_roles ? $user_roles : 'none',
						),
					)
				);
			}

			// Alert if display name is changed.
			if ( $old_userdata->display_name !== $new_userdata->display_name ) {
				$user_roles = implode( ', ', array_map( array( __CLASS__, 'filter_role_names' ), $new_userdata->roles ) );
				Alert_Manager::trigger_event(
					4020,
					array(
						'TargetUsername'  => $new_userdata->user_login,
						'old_displayname' => $old_userdata->display_name,
						'new_displayname' => $new_userdata->display_name,
						'Roles'           => $user_roles,
						'FirstName'       => $new_userdata->user_firstname,
						'LastName'        => $new_userdata->user_lastname,
						'EditUserLink'    => add_query_arg( 'user_id', $user_id, \network_admin_url( 'user-edit.php' ) ),
						'TargetUserData'  => (object) array(
							'Username'  => $new_userdata->user_login,
							'FirstName' => $new_userdata->user_firstname,
							'LastName'  => $new_userdata->user_lastname,
							'Email'     => $new_userdata->user_email,
							'Roles'     => $user_roles ? $user_roles : 'none',
						),
					)
				);
			}

			// Alert if website URL is changed.
			if ( $old_userdata->user_url !== $new_userdata->user_url ) {
				$user_roles = implode( ', ', array_map( array( __CLASS__, 'filter_role_names' ), $new_userdata->roles ) );
				Alert_Manager::trigger_event(
					4021,
					array(
						'TargetUsername' => $new_userdata->user_login,
						'old_url'        => $old_userdata->user_url,
						'new_url'        => $new_userdata->user_url,
						'Roles'          => $user_roles,
						'FirstName'      => $new_userdata->user_firstname,
						'LastName'       => $new_userdata->user_lastname,
						'EditUserLink'   => \add_query_arg( 'user_id', $user_id, \network_admin_url( 'user-edit.php' ) ),
						'TargetUserData' => (object) array(
							'Username'  => $new_userdata->user_login,
							'FirstName' => $new_userdata->user_firstname,
							'LastName'  => $new_userdata->user_lastname,
							'Email'     => $new_userdata->user_email,
							'Roles'     => $user_roles ? $user_roles : 'none',
						),
					)
				);
			}
		}

		/**
		 * Records an added user role in the pending role changes property.
		 *
		 * @param int    $user_id - ID of the user gaining the role.
		 * @param string $role    - Slug of the role being added.
		 *
		 * @return void
		 *
		 * @since 5.6.3
		 */
		public static function record_role_added( $user_id, $role ) {
			self::init_pending_role_change_prop( $user_id );
			self::$pending_role_changes[ $user_id ]['added'][] = $role;
		}

		/**
		 * Records a removed user role in the pending role changes property.
		 *
		 * @param int    $user_id - ID of the user losing the role.
		 * @param string $role    - Slug of the role being removed.
		 *
		 * @return void
		 *
		 * @since 5.6.3
		 */
		public static function record_role_removed( $user_id, $role ) {
			self::init_pending_role_change_prop( $user_id );
			self::$pending_role_changes[ $user_id ]['removed'][] = $role;
		}

		/**
		 * Initialises the pending role change property with the correct structure if it doesn't already exist.
		 *
		 * @param int $user_id - User id to initialise the bucket for.
		 *
		 * @return void
		 *
		 * @since 5.6.3
		 */
		private static function init_pending_role_change_prop( $user_id ) {
			if ( ! isset( self::$pending_role_changes[ $user_id ] ) ) {
				self::$pending_role_changes[ $user_id ] = array(
					'added'   => array(),
					'removed' => array(),
				);
			}
		}

		/**
		 * Process the changed user roles in the request, and fire event 4002 if necessary.
		 *
		 * Calculates the final role changes for the updated user, then fires event 4002 once with
		 * the roles before and after the update. Skips when no actual role change was recorded for the user.
		 *
		 * @param int $user_id - Updated user id.
		 *
		 * @return void
		 *
		 * @since 5.6.3
		 */
		public static function process_role_changes_for_updated_user( $user_id ) {
			// Return early if we don't have pending role changes for this user.
			if ( ! isset( self::$pending_role_changes[ $user_id ] ) ) {
				return;
			}

			$changes = self::$pending_role_changes[ $user_id ];

			$user = \get_userdata( $user_id );

			// If user is not valid, empty the pending changes and return early.
			if ( ! ( $user instanceof \WP_User ) ) {
				unset( self::$pending_role_changes[ $user_id ] );
				return;
			}

			// Get the real roles set after updating the user.
			$final_roles = array_values( (array) $user->roles );

			// Count how many times each role was added.
			$add_counts = array_count_values( $changes['added'] );

			// Count how many times each role was removed.
			$remove_counts = array_count_values( $changes['removed'] );

			// Get a list of all roles that were affected by add/remove operations in this request.
			$affected_roles = array_unique( array_merge( array_keys( $add_counts ), array_keys( $remove_counts ) ) );

			$added   = array();
			$removed = array();

			// Process each role affected by add/remove operations in this request.
			foreach ( $affected_roles as $role ) {
				$toggles = ( $add_counts[ $role ] ?? 0 ) + ( $remove_counts[ $role ] ?? 0 );

				/**
				 * If the number of roles switch (add and remove) are even, it means the role is back to its original state.
				 * E.g. add, remove, add, remove = 4. Role is still removed.
				 * E.g. add, remove, add = 3. Role is added.
				 *
				 * This works because WP and this method will never count here same state in a row.
				 */
				if ( 0 === $toggles % 2 ) {
					continue;
				}

				// Check if this role is present in the final roles.
				if ( in_array( $role, $final_roles, true ) ) {
					// If yes, then this role was added.
					$added[] = $role;
				} else {
					// If not, then this role was removed.
					$removed[] = $role;
				}
			}

			// If we have any added or removed roles, we need to trigger the role change event.
			if ( ! empty( $added ) || ! empty( $removed ) ) {
				/**
				 * Deduct initial roles before saving. Use final roles and check what was actually added and removed.
				 */
				$initial_roles = array_values(
					array_unique(
						array_merge(
							array_diff( $final_roles, $added ),
							$removed
						)
					)
				);

				self::trigger_role_change_event( $user, $initial_roles, $final_roles, $added, $removed );
			}

			// Reset the pending changes for this user.
			unset( self::$pending_role_changes[ $user_id ] );
		}

		/**
		 * Fires event 4002 with the final role change details.
		 *
		 * @param \WP_User $user          - The affected user.
		 * @param array    $initial_roles - Role slugs the user had before the change.
		 * @param array    $final_roles   - Role slugs the user has after the change.
		 * @param array    $added_roles   - Role slugs added during this request.
		 * @param array    $removed_roles - Role slugs removed during this request.
		 *
		 * @return void
		 *
		 * @since 5.6.3
		 */
		private static function trigger_role_change_event( $user, $initial_roles, $final_roles, $added_roles, $removed_roles ) {
			$no_role_string = \esc_html__( 'no role', 'wp-security-audit-log' );

			$old_label = self::format_role_list( $initial_roles, $no_role_string );
			$new_label = self::format_role_list( $final_roles, $no_role_string );

			if ( $old_label === $new_label ) {
				return;
			}

			Alert_Manager::trigger_event_if(
				4002,
				array(
					'TargetUserID'   => $user->ID,
					'TargetUsername' => $user->user_login,
					'OldRole'        => $old_label,
					'NewRole'        => $new_label,
					'AddedRoles'     => self::format_role_list( $added_roles, '' ),
					'RemovedRoles'   => self::format_role_list( $removed_roles, '' ),
					'FirstName'      => $user->user_firstname,
					'LastName'       => $user->user_lastname,
					'EditUserLink'   => \add_query_arg( 'user_id', $user->ID, \network_admin_url( 'user-edit.php' ) ),
					'TargetUserData' => (object) array(
						'Username'  => $user->user_login,
						'FirstName' => $user->user_firstname,
						'LastName'  => $user->user_lastname,
						'Email'     => $user->user_email,
						'Roles'     => $new_label ? $new_label : 'none',
					),
					'multisite_text' => WP_Helper::is_multisite() ? \get_current_blog_id() : false,
				),
				array( __CLASS__, 'must_not_contain_user_changes' )
			);
		}

		/**
		 * Formats an array of role slugs into a human-readable comma-separated list.
		 *
		 * @param array  $role_slugs    - Role slugs to format.
		 * @param string $empty_fallback - String to return when the list is empty.
		 *
		 * @return string $label - Comma-separated role labels or the fallback.
		 *
		 * @since 5.6.3
		 */
		private static function format_role_list( $role_slugs, $empty_fallback ) {
			if ( empty( $role_slugs ) ) {
				return $empty_fallback;
			}

			$labels = array_map( array( __CLASS__, 'filter_role_names' ), $role_slugs );

			return implode( ', ', $labels );
		}

		/**
		 * Triggered when a user is deleted.
		 *
		 * @param int $user_id - User ID of the registered user.
		 *
		 * @since 4.5.0
		 */
		public static function event_user_deleted( $user_id ) {
			$user = \get_userdata( $user_id );
			$role = is_array( $user->roles ) ? implode( ', ', $user->roles ) : $user->roles;
			Alert_Manager::trigger_event_if(
				4007,
				array(
					'TargetUserID'   => $user_id,
					'TargetUserData' => (object) array(
						'Username'  => $user->user_login,
						'FirstName' => $user->user_firstname,
						'LastName'  => $user->user_lastname,
						'Email'     => $user->user_email,
						'Roles'     => $role ? $role : 'none',
					),
				),
				array( __CLASS__, 'must_not_contain_create_user' )
			);
		}

		/**
		 * Triggered when a user profile is opened.
		 *
		 * @param object $user - Instance of WP_User.
		 *
		 * @since 4.5.0
		 */
		public static function event_open_profile( $user ) {
			if ( ! $user ) {
				return;
			}

			$current_user = User_Helper::get_current_user();
			$updated      = isset( $_GET['updated'] ); // phpcs:ignore
			if ( $current_user && ( $user->ID !== $current_user->ID ) && ! $updated ) {
				$user_roles = implode( ', ', array_map( array( __CLASS__, 'filter_role_names' ), $user->roles ) );
				Alert_Manager::trigger_event(
					4014,
					array(
						'UserChanger'    => $current_user->user_login,
						'TargetUsername' => $user->user_login,
						'FirstName'      => $user->user_firstname,
						'LastName'       => $user->user_lastname,
						'Roles'          => $user_roles,
						'EditUserLink'   => \add_query_arg( 'user_id', $user->ID, \network_admin_url( 'user-edit.php' ) ),
						'TargetUserData' => (object) array(
							'Username'  => $user->user_login,
							'FirstName' => $user->user_firstname,
							'LastName'  => $user->user_lastname,
							'Email'     => $user->user_email,
							'Roles'     => $user_roles ? $user_roles : 'none',
						),
					)
				);
			}
		}

		/**
		 * Triggered when a user accesses the admin area.
		 *
		 * @since 4.5.0
		 */
		public static function get_super_admins() {
			self::$old_superadmins = WP_Helper::is_multisite() ? get_super_admins() : null;
		}

		/**
		 * Super Admin Enabled.
		 *
		 * Triggers when a user is granted super admin access.
		 *
		 * @param int $user_id - ID of the user that was granted Super Admin privileges.
		 *
		 * @since 4.5.0
		 */
		public static function event_super_access_granted( $user_id ) {
			$user = get_userdata( $user_id );
			if ( $user && ! in_array( $user->user_login, self::$old_superadmins, true ) ) {
				$user_roles = implode( ', ', array_map( array( __CLASS__, 'filter_role_names' ), $user->roles ) );
				Alert_Manager::trigger_event(
					4008,
					array(
						'TargetUserID'   => $user_id,
						'TargetUsername' => $user->user_login,
						'Roles'          => is_array( $user->roles ) ? implode( ', ', $user->roles ) : $user->roles,
						'FirstName'      => $user->user_firstname,
						'LastName'       => $user->user_lastname,
						'EditUserLink'   => add_query_arg( 'user_id', $user_id, \network_admin_url( 'user-edit.php' ) ),
						'TargetUserData' => (object) array(
							'Username'  => $user->user_login,
							'FirstName' => $user->user_firstname,
							'LastName'  => $user->user_lastname,
							'Email'     => $user->user_email,
							'Roles'     => $user_roles ? $user_roles : 'none',
						),
					)
				);
			}
		}

		/**
		 * Super Admin Disabled.
		 *
		 * Triggers when a user is revoked super admin access.
		 *
		 * @param int $user_id - ID of the user that was revoked Super Admin privileges.
		 *
		 * @since 4.5.0
		 */
		public static function event_super_access_revoked( $user_id ) {
			$user = get_userdata( $user_id );
			if ( $user && in_array( $user->user_login, self::$old_superadmins, true ) ) {
				$user_roles = implode( ', ', array_map( array( __CLASS__, 'filter_role_names' ), $user->roles ) );
				Alert_Manager::trigger_event(
					4009,
					array(
						'TargetUserID'   => $user_id,
						'TargetUsername' => $user->user_login,
						'Roles'          => is_array( $user->roles ) ? implode( ', ', $user->roles ) : $user->roles,
						'FirstName'      => $user->user_firstname,
						'LastName'       => $user->user_lastname,
						'EditUserLink'   => add_query_arg( 'user_id', $user_id, \network_admin_url( 'user-edit.php' ) ),
						'TargetUserData' => (object) array(
							'Username'  => $user->user_login,
							'FirstName' => $user->user_firstname,
							'LastName'  => $user->user_lastname,
							'Email'     => $user->user_email,
							'Roles'     => $user_roles ? $user_roles : 'none',
						),
					)
				);
			}
		}

		/**
		 * Trigger event when admin sends a password reset link.
		 *
		 * @param string $user_login User's login name.
		 *
		 * @since 4.5.0
		 */
		public static function event_password_reset_link_sent( $user_login ) {
			$user = get_user_by( 'login', $user_login );
			if ( $user instanceof \WP_User ) {
				$userdata   = get_userdata( $user->ID );
				$user_roles = implode(
					', ',
					array_map(
						array(
							__CLASS__,
							'filter_role_names',
						),
						$userdata->roles
					)
				);

				Alert_Manager::trigger_event(
					4029,
					array(
						'roles'          => $user_roles,
						'login'          => $user->user_login,
						'firstname'      => ( empty( $user->user_firstname ) ) ? ' ' : $user->user_firstname,
						'lastname'       => ( empty( $user->user_lastname ) ) ? ' ' : $user->user_lastname,
						'EventType'      => 'submitted',
						'TargetUserID'   => $user->ID,
						'TargetUserData' => (object) array(
							'Username'  => $user->user_login,
							'FirstName' => $user->user_firstname,
							'LastName'  => $user->user_lastname,
							'Email'     => $user->user_email,
							'Roles'     => $user_roles ? $user_roles : 'none',
						),
					)
				);
			}
		}

		/**
		 * Remove BBPress Prefix from User Role.
		 *
		 * @param string $user_role - User role.
		 *
		 * @return string
		 *
		 * @since 4.5.0
		 */
		public static function filter_role_names( $user_role ) {
			global $wp_roles;

			return isset( $wp_roles->role_names[ $user_role ] ) ? $wp_roles->role_names[ $user_role ] : false;
		}

		/**
		 * Must Not Contain Create User.
		 *
		 * @since 4.5.0
		 */
		public static function must_not_contain_create_user() {
			return ! Alert_Manager::will_trigger( 4012 );
		}

		/**
		 * Must Not Contain User Changes.
		 *
		 * @since 4.5.0
		 */
		public static function must_not_contain_user_changes() {
			return ! (
			Alert_Manager::will_or_has_triggered( 4010 )
			|| Alert_Manager::will_or_has_triggered( 4011 )
			|| Alert_Manager::will_or_has_triggered( 4012 )
			|| Alert_Manager::will_or_has_triggered( 4000 )
			|| Alert_Manager::will_or_has_triggered( 4001 )
			);
		}

		/**
		 * When a user is created (by any means other than direct database insert), action 'user_register' is fired because
		 * it is part of the function 'wp_insert_user'. We can assume one of the following events if the current session is
		 * logged in end event 4000 is not triggered from elsewhere (it is also hooked into action 'user_register').
		 *
		 * - 4001 User created another WordPress user
		 * - 4012 New network user created
		 *
		 * @param int $user_id - User ID of the registered user.
		 *
		 * @since 4.5.0
		 */
		public static function on_user_register( $user_id ) {
			if ( ! \is_user_logged_in() ) {
				// We bail if the user is not logged in. That is no longer user creation, but a user registration.
				return;
			}

			$user = \get_userdata( $user_id );
			if ( ! ( $user instanceof \WP_User ) ) {
				// Bail if the user is not valid for some reason.
				return;
			}

			$new_user_data = array(
				'Username'  => $user->user_login,
				'Email'     => $user->user_email,
				'FirstName' => ! empty( $user->user_firstname ) ? $user->user_firstname : '',
				'LastName'  => ! empty( $user->user_lastname ) ? $user->user_lastname : '',
				'Roles'     => is_array( $user->roles ) ? implode( ', ', $user->roles ) : $user->roles,
			);

			$event_code = WP_Helper::is_multisite() ? 4012 : 4001;

			$event_data = array(
				'NewUserID'    => $user_id,
				'NewUserData'  => (object) $new_user_data,
				'EditUserLink' => \add_query_arg( 'user_id', $user_id, \network_admin_url( 'user-edit.php' ) ),
			);

			Alert_Manager::trigger_event( $event_code, $event_data, WP_Helper::is_multisite() );
		}
	}
}
