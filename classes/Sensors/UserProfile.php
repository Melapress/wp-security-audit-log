<?php
/**
 * Sensor: User Profile
 *
 * User profile sensor file.
 *
 * @since 1.0.0
 * @package Wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * User Profiles sensor.
 *
 * 4000 New user was created on WordPress
 * 4001 User created another WordPress user
 * 4002 The role of a user was changed by another WordPress user
 * 4003 User has changed his or her password
 * 4004 User changed another user's password
 * 4005 User changed his or her email address
 * 4006 User changed another user's email address
 * 4007 User was deleted by another user
 * 4008 User granted Super Admin privileges
 * 4009 User revoked from Super Admin privileges
 * 4013 The forum role of a user was changed by another WordPress user
 * 4014 User opened the profile page of another user
 *
 * @package Wsal
 * @subpackage Sensors
 */
class WSAL_Sensors_UserProfile extends WSAL_AbstractSensor {

	/**
	 * List of super admins.
	 *
	 * @var array
	 */
	protected $old_superadmins;

	/**
	 * Listening to events using WP hooks.
	 */
	public function HookEvents() {
		add_action( 'profile_update', array( $this, 'event_user_updated' ), 10, 2 );
		add_action( 'set_user_role', array( $this, 'event_user_role_changed' ), 10, 3 );
		add_action( 'delete_user', array( $this, 'event_user_deleted' ) );
		add_action( 'wpmu_delete_user', array( $this, 'event_user_deleted' ) );
		add_action( 'edit_user_profile', array( $this, 'event_open_profile' ), 10, 1 );
		add_action( 'grant_super_admin', array( $this, 'get_super_admins' ) );
		add_action( 'revoke_super_admin', array( $this, 'get_super_admins' ) );
		add_action( 'granted_super_admin', array( $this, 'event_super_access_granted' ), 10, 1 );
		add_action( 'revoked_super_admin', array( $this, 'event_super_access_revoked' ), 10, 1 );
	}

	/**
	 * Method: Support for Ultimate Member email change
	 * alert.
	 *
	 * @param int     $user_id      - User ID.
	 * @param WP_User $old_userdata - Old WP_User object.
	 */
	public function event_user_updated( $user_id, $old_userdata ) {
		// Get new user data.
		$new_userdata = get_userdata( $user_id );

		if ( is_plugin_active( 'bbpress/bbpress.php' ) ) {
			// BBPress user roles.
			$bbpress_roles = array( 'bbp_spectator', 'bbp_moderator', 'bbp_participant', 'bbp_keymaster', 'bbp_blocked' );

			// Get bbpress user roles data.
			$old_bbpress_roles = array_intersect( $bbpress_roles, $old_userdata->roles );
			$new_bbpress_roles = array_intersect( $bbpress_roles, $new_userdata->roles );

			$old_bbpress_roles = array_map( array( $this, 'filter_role_names' ), $old_bbpress_roles );
			$new_bbpress_roles = array_map( array( $this, 'filter_role_names' ), $new_bbpress_roles );

			// Convert array to string.
			$old_bbpress_roles = is_array( $old_bbpress_roles ) ? implode( ', ', $old_bbpress_roles ) : '';
			$new_bbpress_roles = is_array( $new_bbpress_roles ) ? implode( ', ', $new_bbpress_roles ) : '';

			if ( $old_bbpress_roles !== $new_bbpress_roles ) {
				$current_user = wp_get_current_user();
				$this->plugin->alerts->Trigger(
					4013, array(
						'TargetUsername' => $new_userdata->user_login,
						'OldRole'        => $old_bbpress_roles,
						'NewRole'        => $new_bbpress_roles,
						'UserChanger'    => $current_user->user_login,
					)
				);
			}
		}

		// Alert if user password is changed.
		if ( $old_userdata->user_pass !== $new_userdata->user_pass ) {
			$event      = get_current_user_id() === $user_id ? 4003 : 4004;
			$user_roles = implode( ', ', array_map( array( $this, 'filter_role_names' ), $new_userdata->roles ) );
			$this->plugin->alerts->Trigger(
				$event, array(
					'TargetUserID'   => $user_id,
					'TargetUserData' => (object) array(
						'Username' => $new_userdata->user_login,
						'Roles'    => $user_roles,
					),
				)
			);
		}

		// Alert if user email is changed.
		if ( $old_userdata->user_email !== $new_userdata->user_email ) {
			$event = get_current_user_id() === $user_id ? 4005 : 4006;
			$this->plugin->alerts->Trigger(
				$event, array(
					'TargetUserID'   => $user_id,
					'TargetUsername' => $new_userdata->user_login,
					'OldEmail'       => $old_userdata->user_email,
					'NewEmail'       => $new_userdata->user_email,
				)
			);
		}

		// Alert if display name is changed.
		if ( $old_userdata->display_name !== $new_userdata->display_name ) {
			$this->plugin->alerts->Trigger(
				4020, array(
					'TargetUsername'  => $new_userdata->user_login,
					'old_displayname' => $old_userdata->display_name,
					'new_displayname' => $new_userdata->display_name,
				)
			);
		}
	}

	/**
	 * Triggered when a user role is changed.
	 *
	 * @param int    $user_id   - User ID of the user.
	 * @param string $new_role  - New role.
	 * @param array  $old_roles - Array of old roles.
	 */
	public function event_user_role_changed( $user_id, $new_role, $old_roles ) {
		$user = get_userdata( $user_id );

		// If BBPress plugin is active then check for user roles change.
		if ( is_plugin_active( 'bbpress/bbpress.php' ) ) {
			// BBPress user roles.
			$bbpress_roles = array( 'bbp_spectator', 'bbp_moderator', 'bbp_participant', 'bbp_keymaster', 'bbp_blocked' );

			// Set WP roles.
			$old_roles = array_diff( $old_roles, $bbpress_roles );
			$new_roles = array_diff( $user->roles, $bbpress_roles );
			$old_roles = array_map( array( $this, 'filter_role_names' ), $old_roles );
			$new_roles = array_map( array( $this, 'filter_role_names' ), $new_roles );
		} else {
			$old_roles = array_map( array( $this, 'filter_role_names' ), $old_roles );
			$new_roles = array_map( array( $this, 'filter_role_names' ), $user->roles );
		}

		// Get roles.
		$old_roles = is_array( $old_roles ) ? implode( ', ', $old_roles ) : '';
		$new_roles = is_array( $new_roles ) ? implode( ', ', $new_roles ) : '';

		// Alert if roles are changed.
		if ( $old_roles !== $new_roles ) {
			$this->plugin->alerts->TriggerIf(
				4002,
				array(
					'TargetUserID'   => $user_id,
					'TargetUsername' => $user->user_login,
					'OldRole'        => $old_roles,
					'NewRole'        => $new_roles,
					'multisite_text' => $this->plugin->IsMultisite() ? get_current_blog_id() : false,
				),
				array( $this, 'MustNotContainUserChanges' )
			);
		}
	}

	/**
	 * Triggered when a user is deleted.
	 *
	 * @param int $user_id - User ID of the registered user.
	 */
	public function event_user_deleted( $user_id ) {
		$user = get_userdata( $user_id );
		$role = is_array( $user->roles ) ? implode( ', ', $user->roles ) : $user->roles;
		$this->plugin->alerts->TriggerIf(
			4007, array(
				'TargetUserID'   => $user_id,
				'TargetUserData' => (object) array(
					'Username'  => $user->user_login,
					'FirstName' => $user->user_firstname,
					'LastName'  => $user->user_lastname,
					'Email'     => $user->user_email,
					'Roles'     => $role ? $role : 'none',
				),
			), array( $this, 'MustNotContainCreateUser' )
		);
	}

	/**
	 * Triggered when a user profile is opened.
	 *
	 * @param object $user - Instance of WP_User.
	 */
	public function event_open_profile( $user ) {
		if ( ! $user ) {
			return;
		}
		$current_user = wp_get_current_user();
		$updated      = isset( $_GET['updated'] ); // @codingStandardsIgnoreLine
		if ( $current_user && ( $user->ID !== $current_user->ID ) && ! $updated ) {
			$this->plugin->alerts->Trigger(
				4014, array(
					'UserChanger'    => $current_user->user_login,
					'TargetUsername' => $user->user_login,
				)
			);
		}
	}

	/**
	 * Triggered when a user accesses the admin area.
	 */
	public function get_super_admins() {
		$this->old_superadmins = $this->IsMultisite() ? get_super_admins() : null;
	}

	/**
	 * Super Admin Enabled.
	 *
	 * Triggers when a user is granted super admin access.
	 *
	 * @since 3.4
	 *
	 * @param int $user_id - ID of the user that was granted Super Admin privileges.
	 */
	public function event_super_access_granted( $user_id ) {
		$user = get_userdata( $user_id );
		if ( $user && ! in_array( $user->user_login, $this->old_superadmins, true ) ) {
			$this->plugin->alerts->Trigger(
				4008, array(
					'TargetUserID'   => $user_id,
					'TargetUsername' => $user->user_login,
				)
			);
		}
	}

	/**
	 * Super Admin Disabled.
	 *
	 * Triggers when a user is revoked super admin access.
	 *
	 * @since 3.4
	 *
	 * @param int $user_id - ID of the user that was revoked Super Admin privileges.
	 */
	public function event_super_access_revoked( $user_id ) {
		$user = get_userdata( $user_id );
		if ( $user && in_array( $user->user_login, $this->old_superadmins, true ) ) {
			$this->plugin->alerts->Trigger(
				4009, array(
					'TargetUserID'   => $user_id,
					'TargetUsername' => $user->user_login,
				)
			);
		}
	}

	/**
	 * Remove BBPress Prefix from User Role.
	 *
	 * @since 3.4
	 *
	 * @param string $user_role - User role.
	 * @return string
	 */
	public function filter_role_names( $user_role ) {
		global $wp_roles;
		return isset( $wp_roles->role_names[ $user_role ] ) ? $wp_roles->role_names[ $user_role ] : false;
	}

	/**
	 * Must Not Contain Create User.
	 *
	 * @param WSAL_AlertManager $mgr - Instance of WSAL_AlertManager.
	 */
	public function MustNotContainCreateUser( WSAL_AlertManager $mgr ) {
		return ! $mgr->WillTrigger( 4012 );
	}

	/**
	 * Must Not Contain User Changes.
	 *
	 * @param WSAL_AlertManager $mgr - Instance of WSAL_AlertManager.
	 */
	public function MustNotContainUserChanges( WSAL_AlertManager $mgr ) {
		return ! (
			$mgr->WillOrHasTriggered( 4010 )
			|| $mgr->WillOrHasTriggered( 4011 )
			|| $mgr->WillOrHasTriggered( 4012 )
			|| $mgr->WillOrHasTriggered( 4000 )
			|| $mgr->WillOrHasTriggered( 4001 )
		);
	}
}
