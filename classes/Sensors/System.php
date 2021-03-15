<?php
/**
 * Sensor: System Activity
 *
 * System activity sensor class file.
 *
 * @since 1.0.0
 * @package Wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * System Activity sensor.
 *
 * 6000 Events automatically pruned by system
 * 6001 Option Anyone Can Register in WordPress settings changed
 * 6002 New User Default Role changed
 * 6003 WordPress Administrator Notification email changed
 * 6004 WordPress was updated
 * 6005 User changes the WordPress Permalinks
 * 8009 User changed forum's role
 * 8010 User changed option of a forum
 * 8012 User changed time to disallow post editing
 * 8013 User changed the forum setting posting throttle time
 * 1006 User logged out all other sessions with the same username
 * 6004 WordPress was updated
 * 6008 Enabled/Disabled the option Discourage search engines from indexing this site
 * 6009 Enabled/Disabled comments on all the website
 * 6010 Enabled/Disabled the option Comment author must fill out name and email
 * 6011 Enabled/Disabled the option Users must be logged in and registered to comment
 * 6012 Enabled/Disabled the option to automatically close comments
 * 6013 Changed the value of the option Automatically close comments
 * 6014 Enabled/Disabled the option for comments to be manually approved
 * 6015 Enabled/Disabled the option for an author to have previously approved comments for the comments to appear
 * 6016 Changed the number of links that a comment must have to be held in the queue
 * 6017 Modified the list of keywords for comments moderation
 * 6018 Modified the list of keywords for comments blacklisting
 *
 * @package Wsal
 * @subpackage Sensors
 */
class WSAL_Sensors_System extends WSAL_AbstractSensor {

	/**
	 * Listening to events using WP hooks.
	 */
	public function HookEvents() {
		add_action( 'wsal_prune', array( $this, 'EventPruneEvents' ), 10, 2 );
		add_action( 'admin_init', array( $this, 'EventAdminInit' ) );
		add_action( 'automatic_updates_complete', array( $this, 'WPUpdate' ), 10, 1 );

		// whitelist options.
		add_action( 'allowed_options', array( $this, 'EventOptions' ), 10, 1 );

		// Update admin email alert.
		add_action( 'update_option_admin_email', array( $this, 'admin_email_changed' ), 10, 3 );
	}

	/**
	 * Alert: Admin email changed.
	 *
	 * @param mixed  $old_value - The old option value.
	 * @param mixed  $new_value - The new option value.
	 * @param string $option    - Option name.
	 * @since 3.0.0
	 */
	public function admin_email_changed( $old_value, $new_value, $option ) {
		// Check if the option is not empty and is admin_email.
		if ( ! empty( $old_value ) && ! empty( $new_value )
			&& ! empty( $option ) && 'admin_email' === $option ) {
			if ( $old_value != $new_value ) {
				$this->plugin->alerts->Trigger(
					6003,
					array(
						'OldEmail'      => $old_value,
						'NewEmail'      => $new_value,
						'CurrentUserID' => wp_get_current_user()->ID,
					)
				);
			}
		}
	}

	/**
	 * Method: Prune events function.
	 *
	 * @param int    $count The number of deleted events.
	 * @param string $query Query that selected events for deletion.
	 */
	public function EventPruneEvents( $count, $query ) {
		$this->plugin->alerts->Trigger(
			6000,
			array(
				'EventCount' => $count,
				'PruneQuery' => $query,
			)
		);
	}

	/**
	 * Triggered when a user accesses the admin area.
	 */
	public function EventAdminInit() {
		// Filter global arrays for security.
		$post_array   = filter_input_array( INPUT_POST );
		$get_array    = filter_input_array( INPUT_GET );
		$server_array = filter_input_array( INPUT_SERVER );

		// Destroy all the session of the same user from user profile page.
		if ( isset( $post_array['action'] ) && ( 'destroy-sessions' == $post_array['action'] ) && isset( $post_array['user_id'] ) ) {
			$this->plugin->alerts->Trigger(
				1006,
				array(
					'TargetUserID' => $post_array['user_id'],
				)
			);
		}

		// Make sure user can actually modify target options.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$actype = '';
		if ( ! empty( $server_array['SCRIPT_NAME'] ) ) {
			$actype = basename( $server_array['SCRIPT_NAME'], '.php' );
		}

		$is_option_page      = 'options' === $actype;
		$is_network_settings = 'settings' === $actype;
		$is_permalink_page   = 'options-permalink' === $actype;

		// WordPress URL changed.
		if ( $is_option_page
			&& wp_verify_nonce( $post_array['_wpnonce'], 'general-options' )
			&& ! empty( $post_array['siteurl'] ) ) {
			$old_siteurl = get_option( 'siteurl' );
			$new_siteurl = isset( $post_array['siteurl'] ) ? $post_array['siteurl'] : '';
			if ( $old_siteurl !== $new_siteurl ) {
				$this->plugin->alerts->Trigger(
					6024,
					array(
						'old_url'       => $old_siteurl,
						'new_url'       => $new_siteurl,
						'CurrentUserID' => wp_get_current_user()->ID,
					)
				);
			}
		}

		// Site URL changed.
		if ( $is_option_page
			&& wp_verify_nonce( $post_array['_wpnonce'], 'general-options' )
			&& ! empty( $post_array['home'] ) ) {
			$old_url = get_option( 'home' );
			$new_url = isset( $post_array['home'] ) ? $post_array['home'] : '';
			if ( $old_url !== $new_url ) {
				$this->plugin->alerts->Trigger(
					6025,
					array(
						'old_url'       => $old_url,
						'new_url'       => $new_url,
						'CurrentUserID' => wp_get_current_user()->ID,
					)
				);
			}
		}

		if ( isset( $post_array['option_page'] ) && 'reading' === $post_array['option_page'] && isset( $post_array['show_on_front'] )
			&& wp_verify_nonce( $post_array['_wpnonce'], 'reading-options' ) ) {
			$old_homepage = ( 'posts' === get_site_option( 'show_on_front' ) ) ? __( 'latest posts', 'wp-security-audit-log' ) :__( 'static page', 'wp-security-audit-log' );
			$new_homepage = ( 'posts' === $post_array['show_on_front'] ) ? __( 'latest posts', 'wp-security-audit-log' ) :__( 'static page', 'wp-security-audit-log' );
			if ( $old_homepage != $new_homepage ) {
				$this->plugin->alerts->Trigger(
					6035,
					array(
						'old_homepage' => $old_homepage,
						'new_homepage' => $new_homepage,
					)
				);
			}
		}

		if ( isset( $post_array['option_page'] ) && 'reading' === $post_array['option_page'] && isset( $post_array['page_on_front'] )
			&& wp_verify_nonce( $post_array['_wpnonce'], 'reading-options' ) ) {
			$old_frontpage = get_the_title( get_site_option( 'page_on_front' ) ) ;
			$new_frontpage = get_the_title( $post_array[ 'page_on_front' ] );
			if ( $old_frontpage != $new_frontpage ) {
				$this->plugin->alerts->Trigger(
					6036,
					array(
						'old_page' => $old_frontpage,
						'new_page' => $new_frontpage,
					)
				);
			}
		}

		if ( isset( $post_array['option_page'] ) && 'reading' === $post_array['option_page'] && isset( $post_array['page_for_posts'] )
			&& wp_verify_nonce( $post_array['_wpnonce'], 'reading-options' ) ) {
			$old_postspage = get_the_title( get_site_option( 'page_for_posts' ) );
			$new_postspage = get_the_title( $post_array[ 'page_for_posts' ] );
			if ( $old_postspage != $new_postspage ) {
				$this->plugin->alerts->Trigger(
					6037,
					array(
						'old_page' => $old_postspage,
						'new_page' => $new_postspage,
					)
				);
			}
		}

		//  check timezone change
		if ( $is_option_page && wp_verify_nonce( $post_array['_wpnonce'], 'general-options' ) && ! empty( $post_array['timezone_string'] ) ) {
			$this->check_timezone_change( $post_array );
		}

		//  check date format change
		if ( $is_option_page && wp_verify_nonce( $post_array['_wpnonce'], 'general-options' ) && ! empty( $post_array['date_format'] ) ) {
			$old_date_format = get_option( 'date_format' );
			$new_date_format = ( '\c\u\s\t\o\m' === $post_array['date_format'] ) ? $post_array['date_format_custom'] : $post_array['date_format'];
			if ( $old_date_format !== $new_date_format ) {
				$this->plugin->alerts->Trigger(
					6041,
					array(
						'old_date_format' => $old_date_format,
						'new_date_format' => $new_date_format,
						'CurrentUserID'   => wp_get_current_user()->ID,
					)
				);
			}
		}

		//  check time format change
		if ( $is_option_page && wp_verify_nonce( $post_array['_wpnonce'], 'general-options' ) && ! empty( $post_array['time_format'] ) ) {
			$old_time_format =  get_option( 'time_format' );
			$new_time_format = ( '\c\u\s\t\o\m' === $post_array['time_format'] ) ? $post_array['time_format_custom'] : $post_array['time_format'];
			if ( $old_time_format !== $new_time_format ) {
				$this->plugin->alerts->Trigger(
					6042,
					array(
						'old_time_format' => $old_time_format,
						'new_time_format' => $new_time_format,
						'CurrentUserID'   => wp_get_current_user()->ID,
					)
				);
			}
		}

		// Registration Option.
		if ( $is_option_page && wp_verify_nonce( $post_array['_wpnonce'], 'general-options' ) && ( get_option( 'users_can_register' ) xor isset( $post_array['users_can_register'] ) ) ) {
			$old = get_option( 'users_can_register' ) ? 'enabled' : 'disabled';
			$new = isset( $post_array['users_can_register'] ) ? 'enabled' : 'disabled';

			if ( $old !== $new ) {
				$this->plugin->alerts->Trigger(
					6001,
					array(
						'EventType'     => $new,
						'CurrentUserID' => wp_get_current_user()->ID,
					)
				);
			}
		}

		// Default Role option.
		if ( $is_option_page && wp_verify_nonce( $post_array['_wpnonce'], 'general-options' ) && ! empty( $post_array['default_role'] ) ) {
			$old = get_option( 'default_role' );
			$new = trim( $post_array['default_role'] );
			if ( $old != $new ) {
				$this->plugin->alerts->Trigger(
					6002,
					array(
						'OldRole'       => $old,
						'NewRole'       => $new,
						'CurrentUserID' => wp_get_current_user()->ID,
					)
				);
			}
		}

		// Admin Email Option.
		if ( $is_option_page && wp_verify_nonce( $post_array['_wpnonce'], 'general-options' ) && ! empty( $post_array['admin_email'] ) ) {
			$old = get_option( 'admin_email' );
			$new = trim( $post_array['admin_email'] );
			if ( $old != $new ) {
				$this->plugin->alerts->Trigger(
					6003,
					array(
						'OldEmail'      => $old,
						'NewEmail'      => $new,
						'CurrentUserID' => wp_get_current_user()->ID,
					)
				);
			}
		}

		// Admin Email of Network.
		if ( $is_network_settings && ! empty( $post_array['new_admin_email'] ) && wp_verify_nonce( $post_array['_wpnonce'], 'siteoptions' ) ) {
			$old = get_site_option( 'admin_email' );
			$new = trim( $post_array['new_admin_email'] );
			if ( $old != $new ) {
				$this->plugin->alerts->Trigger(
					6003,
					array(
						'OldEmail'      => $old,
						'NewEmail'      => $new,
						'CurrentUserID' => wp_get_current_user()->ID,
					)
				);
			}
		}

		// Permalinks changed.
		if ( $is_permalink_page && ! empty( $post_array['permalink_structure'] )
		     && wp_verify_nonce( $post_array['_wpnonce'], 'update-permalink' )) {
			$old = get_option( 'permalink_structure' );
			$new = trim( $post_array['permalink_structure'] );
			if ( $old != $new ) {
				$this->plugin->alerts->Trigger(
					6005,
					array(
						'OldPattern'    => $old,
						'NewPattern'    => $new,
						'CurrentUserID' => wp_get_current_user()->ID,
					)
				);
			}
		}

		// Core Update.
		if ( isset( $get_array['action'] ) && 'do-core-upgrade' === $get_array['action'] && isset( $post_array['version'] )
			&& wp_verify_nonce( $post_array['_wpnonce'], 'upgrade-core' )) {
			$old_version = get_bloginfo( 'version' );
			$new_version = $post_array['version'];
			if ( $old_version != $new_version ) {
				$this->plugin->alerts->Trigger(
					6004,
					array(
						'OldVersion' => $old_version,
						'NewVersion' => $new_version,
					)
				);
			}
		}

		// Enable core updates.
		if ( isset( $get_array['action'] ) && 'core-major-auto-updates-settings' === $get_array['action'] && isset( $get_array['value'] )
			&& wp_verify_nonce( $get_array['_wpnonce'], 'core-major-auto-updates-nonce' ) ) {
			$status     = ( 'enable' === $get_array['value'] ) ? __( 'automatically update to all new versions of WordPress', 'wp-security-audit-log' ) : __( 'automatically update maintenance and security releases only', 'wp-security-audit-log' );
			$this->plugin->alerts->Trigger(
				6044,
				array(
					'updates_status' => $status,
				)
			);
		}

		// Site Language changed.
		if ( $is_option_page
			&& wp_verify_nonce( $post_array['_wpnonce'], 'general-options' )
			&& isset( $post_array['WPLANG'] ) ) {
			// Is there a better way to turn the language into a "nice name"?
			require_once ABSPATH . 'wp-admin/includes/translation-install.php';
			$available_translations = wp_get_available_translations();

			// When English (United States) is selected, the WPLANG post entry is empty so lets account for this.
			$wplang_setting = get_option( 'WPLANG' );
			$previous_value = ( ! empty( $wplang_setting ) ) ? $wplang_setting : 'en-US';
			$new_value      = ( ! empty( $post_array['WPLANG'] ) ) ? $post_array['WPLANG'] : 'en-US';

			// Now lets turn these into a nice, native name - the same as shown to the user when choosing a language.
			$previous_value = ( isset( $available_translations[$previous_value] ) ) ? $available_translations[$previous_value]['native_name'] : 'English (United States)';
			$new_value      = ( isset( $available_translations[$new_value] ) ) ? $available_translations[$new_value]['native_name'] : 'English (United States)';

			if ( $previous_value !== $new_value ) {
				$this->plugin->alerts->Trigger(
					6045,
					array(
						'previous_value' => $previous_value,
						'new_value'      => $new_value,
					)
				);
			}
		}
	}

	/**
	 * WordPress auto core update.
	 *
	 * @param array $automatic - Automatic update array.
	 */
	public function WPUpdate( $automatic ) {
		if ( isset( $automatic['core'][0] ) ) {
			$obj         = $automatic['core'][0];
			$old_version = get_bloginfo( 'version' );
			$this->plugin->alerts->Trigger(
				6004,
				array(
					'OldVersion' => $old_version,
					'NewVersion' => $obj->item->version . ' (auto update)',
				)
			);
		}
	}

	/**
	 * Events from 6008 to 6018.
	 *
	 * @param array $whitelist - White list options.
	 *
	 * @return array|null
	 */
	public function EventOptions( $whitelist = null ) {
		// Filter global arrays for security.
		$post_array = filter_input_array( INPUT_POST );

		if ( isset( $post_array['option_page'] ) && 'reading' === $post_array['option_page'] ) {
			$old_status = (int) get_option( 'blog_public', 1 );
			$new_status = isset( $post_array['blog_public'] ) ? 0 : 1;

			if ( $old_status !== $new_status ) {
				$this->plugin->alerts->Trigger(
					6008,
					array( 'EventType' => ( 0 === $new_status ) ? 'enabled' : 'disabled' )
				);
			}
		}

		if ( isset( $post_array['option_page'] ) && 'discussion' === $post_array['option_page'] ) {
			$old_status = get_option( 'default_comment_status', 'closed' );
			$new_status = isset( $post_array['default_comment_status'] ) ? 'open' : 'closed';

			if ( $old_status !== $new_status ) {
				$this->plugin->alerts->Trigger(
					6009,
					array( 'EventType' => ( 'open' === $new_status ) ? 'enabled' : 'disabled' )
				);
			}

			$old_status = (int) get_option( 'require_name_email', 0 );
			$new_status = isset( $post_array['require_name_email'] ) ? 1 : 0;

			if ( $old_status !== $new_status ) {
				$this->plugin->alerts->Trigger(
					6010,
					array( 'EventType' => ( 1 === $new_status ) ? 'enabled' : 'disabled' )
				);
			}

			$old_status = (int) get_option( 'comment_registration', 0 );
			$new_status = isset( $post_array['comment_registration'] ) ? 1 : 0;

			if ( $old_status !== $new_status ) {
				$this->plugin->alerts->Trigger(
					6011,
					array( 'EventType' => ( 1 === $new_status ) ? 'enabled' : 'disabled' )
				);
			}

			$old_status = (int) get_option( 'close_comments_for_old_posts', 0 );
			$new_status = isset( $post_array['close_comments_for_old_posts'] ) ? 1 : 0;

			if ( $old_status !== $new_status ) {
				$value = isset( $post_array['close_comments_days_old'] ) ? $post_array['close_comments_days_old'] : 0;
				$this->plugin->alerts->Trigger(
					6012,
					array(
						'EventType' => ( 1 === $new_status ) ? 'enabled' : 'disabled',
						'Value'     => $value,
					)
				);
			}

			$old_value = get_option( 'close_comments_days_old', 0 );
			$new_value = isset( $post_array['close_comments_days_old'] ) ? $post_array['close_comments_days_old'] : 0;
			if ( $old_value !== $new_value ) {
				$this->plugin->alerts->Trigger(
					6013,
					array(
						'OldValue' => $old_value,
						'NewValue' => $new_value,
					)
				);
			}

			$old_status = (int) get_option( 'comment_moderation', 0 );
			$new_status = isset( $post_array['comment_moderation'] ) ? 1 : 0;

			if ( $old_status !== $new_status ) {
				$this->plugin->alerts->Trigger(
					6014,
					array( 'EventType' => ( 1 === $new_status ) ? 'enabled' : 'disabled' )
				);
			}

			//  comment_whitelist option was renamed to comment_previously_approved in WordPress 5.5.0
			$comment_whitelist_option_name = version_compare( get_bloginfo( 'version' ), '5.5.0', '<' ) ? 'comment_whitelist' : 'comment_previously_approved';
			$old_status = (int) get_option( $comment_whitelist_option_name, 0 );
			$new_status = isset( $post_array[ $comment_whitelist_option_name ] ) ? 1 : 0;

			if ( $old_status !== $new_status ) {
				$this->plugin->alerts->Trigger(
					6015,
					array( 'EventType' => ( 1 === $new_status ) ? 'enabled' : 'disabled' )
				);
			}

			$old_value = get_option( 'comment_max_links', 0 );
			$new_value = isset( $post_array['comment_max_links'] ) ? $post_array['comment_max_links'] : 0;
			if ( $old_value !== $new_value ) {
				$this->plugin->alerts->Trigger(
					6016,
					array(
						'OldValue' => $old_value,
						'NewValue' => $new_value,
					)
				);
			}

			$old_value = get_option( 'moderation_keys', 0 );
			$new_value = isset( $post_array['moderation_keys'] ) ? $post_array['moderation_keys'] : 0;
			if ( $old_value !== $new_value ) {
				$this->plugin->alerts->Trigger( 6017, array() );
			}

			//  blacklist_keys option was renamed to disallowed_keys in WordPress 5.5.0
			$blacklist_keys_option_name = version_compare( get_bloginfo( 'version' ), '5.5.0', '<' ) ? 'blacklist_keys' : 'disallowed_keys';
			$old_value = get_option( $blacklist_keys_option_name, 0 );
			$new_value = isset( $post_array[ $blacklist_keys_option_name ] ) ? $post_array[ $blacklist_keys_option_name ] : 0;
			if ( $old_value !== $new_value ) {
				$this->plugin->alerts->Trigger( 6018, array() );
			}
		}
		return $whitelist;
	}

	/**
	 * Checks if the timezone settings have changed. Logs an events if it did.
	 *
	 * @param array $post_array Sanitized input array.
	 *
	 * @since 4.2.0
	 */
	private function check_timezone_change( $post_array ) {
		$old_timezone_string = get_option( 'timezone_string' );
		$new_timezone_string = isset( $post_array['timezone_string'] ) ? $post_array['timezone_string'] : '';

		//  backup of the labels as we might change them below when dealing with UTC offset definitions
		$old_timezone_label = $old_timezone_string;
		$new_timezone_label = $new_timezone_string;
		if ( strlen( $old_timezone_string ) === 0 ) {
			//  the old timezone string can be empty if the time zone was configured using UTC offset selection
			//  rather than using a country/city selection
			$old_timezone_string = $old_timezone_label = wp_timezone_string();
			if ( 'UTC' === $old_timezone_string ) {
				$old_timezone_string = '+00:00';
			}

			//  adjusts label to show UTC offset consistently
			$old_timezone_label = 'UTC' . $old_timezone_label;
		}

		//  new timezone can be defined as UTC offset

		//  there is one UTC option that doesn't contain the offset, we need to tweak the value for further processing
		if ( 'UTC' === $new_timezone_string ) {
			$new_timezone_string = 'UTC+0';
		}

		if ( preg_match( '/UTC([+\-][0-9\.]+)/', $new_timezone_string, $matches ) ) {
			$hours_decimal = floatval( $matches[1] );

			//  the new timezone is also set using UTC offset, it needs to be converted to the same format used
			//  by wp_timezone_string
			$sign                = $hours_decimal < 0 ? '-' : '+';
			$abs_hours           = abs( $hours_decimal );
			$abs_mins            = abs( $hours_decimal * 60 % 60 );
			$new_timezone_string = sprintf( '%s%02d:%02d', $sign, floor( $abs_hours ), $abs_mins );

			//  adjusts label to show UTC offset consistently
			$new_timezone_label = 'UTC' . $new_timezone_string;
		}

		if ( $old_timezone_string !== $new_timezone_string ) {
			$this->plugin->alerts->Trigger(
				6040,
				array(
					'old_timezone'  => $old_timezone_label,
					'new_timezone'  => $new_timezone_label,
					'CurrentUserID' => wp_get_current_user()->ID,
				)
			);
		}
	}
}
