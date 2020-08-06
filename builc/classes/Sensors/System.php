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
 * 6007 User requests non-existing pages (404 Error Pages)
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

	const SCHEDULED_HOOK_LOG_FILE_PRUDING = 'wsal_log_files_pruning';
	/**
	 * 404 User Transient.
	 *
	 * WordPress will prefix the name with "_transient_"
	 * or "_transient_timeout_" in the options table.
	 */
	const TRANSIENT_404 = 'wsal-404-attempts';

	/**
	 * Listening to events using WP hooks.
	 */
	public function HookEvents() {
		add_action( 'wsal_prune', array( $this, 'EventPruneEvents' ), 10, 2 );
		add_action( 'admin_init', array( $this, 'EventAdminInit' ) );

		add_action( 'automatic_updates_complete', array( $this, 'WPUpdate' ), 10, 1 );

		add_filter( 'template_redirect', array( $this, 'Event404' ) );

		// get the logging location.
		$custom_logging_path_base = $this->plugin->settings()->get_working_dir_path( '404s' );
		if ( ! is_wp_error( $custom_logging_path_base ) ) {
			if ( ! file_exists( $custom_logging_path_base . 'index.php' ) ) {
				// make an empty index.php in the directory.
				@file_put_contents( $custom_logging_path_base . 'index.php', '<?php // Silence is golden' );
			}

			// Directory for logged in users log files.
			$user_upload_path = $custom_logging_path_base . 'users' . DIRECTORY_SEPARATOR;
			$this->remove_sub_directories( $user_upload_path ); // Remove it.

			// Directory for visitor log files.
			$visitor_upload_path = $custom_logging_path_base . 'visitors' . DIRECTORY_SEPARATOR;
			$this->remove_sub_directories( $visitor_upload_path ); // Remove it.
		}

		if ( ! wp_next_scheduled( self::SCHEDULED_HOOK_LOG_FILE_PRUDING ) ) {
			wp_schedule_event( time(), 'daily', self::SCHEDULED_HOOK_LOG_FILE_PRUDING );
		}

		// Cron Job 404 log files pruning.
		add_action( self::SCHEDULED_HOOK_LOG_FILE_PRUDING, array( $this, 'LogFilesPruning' ) );
		// whitelist options.
		add_action( 'whitelist_options', array( $this, 'EventOptions' ), 10, 1 );

		// Update admin email alert.
		add_action( 'update_option_admin_email', array( $this, 'admin_email_changed' ), 10, 3 );
	}

	/**
	 * Check if failed login directory exists then delete all
	 * files within this directory and remove the directory itself.
	 *
	 * @param string $sub_dir - Subdirectory.
	 */
	public function remove_sub_directories( $sub_dir ) {
		// Check if subdirectory exists.
		if ( is_dir( $sub_dir ) ) {
			// Get all files inside failed logins folder.
			$files = glob( $sub_dir . '*' );

			if ( ! empty( $files ) ) {
				// Unlink each file.
				foreach ( $files as $file ) {
					// Check if valid file.
					if ( is_file( $file ) ) {
						// Delete the file.
						unlink( $file );
					}
				}
			}
			// Remove the directory.
			rmdir( $sub_dir );
		}
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
	 * 404 limit count.
	 *
	 * @return integer limit
	 */
	protected function Get404LogLimit() {
		return $this->plugin->settings()->Get404LogLimit();
	}

	/**
	 * Expiration of the transient saved in the WP database.
	 *
	 * @return integer Time until expiration in seconds from now
	 */
	protected function Get404Expiration() {
		return 24 * 60 * 60;
	}

	/**
	 * Check 404 limit.
	 *
	 * @param integer $site_id - Blog ID.
	 * @param string  $username - Username.
	 * @param string  $ip - IP address.
	 * @return boolean passed limit true|false
	 */
	protected function IsPast404Limit( $site_id, $username, $ip ) {
		$get_fn = $this->IsMultisite() ? 'get_site_transient' : 'get_transient';
		$data   = $get_fn( self::TRANSIENT_404 );
		return ( false !== $data ) && isset( $data[ $site_id . ':' . $username . ':' . $ip ] ) && ( $data[ $site_id . ':' . $username . ':' . $ip ] > $this->Get404LogLimit() );
	}

	/**
	 * Increment 404 limit.
	 *
	 * @param integer $site_id - Blog ID.
	 * @param string  $username - Username.
	 * @param string  $ip - IP address.
	 */
	protected function Increment404( $site_id, $username, $ip ) {
		$get_fn = $this->IsMultisite() ? 'get_site_transient' : 'get_transient';
		$set_fn = $this->IsMultisite() ? 'set_site_transient' : 'set_transient';

		$data = $get_fn( self::TRANSIENT_404 );
		if ( ! $data ) {
			$data = array();
		}
		if ( ! isset( $data[ $site_id . ':' . $username . ':' . $ip ] ) ) {
			$data[ $site_id . ':' . $username . ':' . $ip ] = 1;
		}
		$data[ $site_id . ':' . $username . ':' . $ip ]++;
		$set_fn( self::TRANSIENT_404, $data, $this->Get404Expiration() );
	}

	/**
	 * Event 404 Not found.
	 */
	public function Event404() {
		$attempts = 1;

		global $wp_query;
		if ( ! $wp_query->is_404 ) {
			return;
		}
		$msg = 'times';

		list( $y, $m, $d ) = explode( '-', date( 'Y-m-d' ) );

		$site_id = ( function_exists( 'get_current_blog_id' ) ? get_current_blog_id() : 0 );
		$ip      = $this->plugin->settings()->GetMainClientIP();

		if ( ! is_user_logged_in() ) {
			$username = 'Website Visitor';
		} else {
			$username = wp_get_current_user()->user_login;
		}

		// Request URL.
		$request_uri = filter_input( INPUT_SERVER, 'REQUEST_URI', FILTER_SANITIZE_STRING );
		if ( ! empty( $request_uri ) ) {
			$url_404 = home_url() . $request_uri;
		} elseif ( isset( $_SERVER['REQUEST_URI'] ) && ! empty( $_SERVER['REQUEST_URI'] ) ) {
			$url_404 = home_url() . sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		}

		// Remove forward slash from the URL.
		$url_404 = untrailingslashit( $url_404 );

		// Check for excluded 404 URls.
		if ( $this->is_excluded_url( $url_404 ) ) {
			return;
		}

		if ( 'Website Visitor' !== $username ) {
			// Check if the alert is disabled from the "Enable/Disable Alerts" section.
			if ( ! $this->plugin->alerts->IsEnabled( 6007 ) ) {
				return;
			}

			if ( $this->IsPast404Limit( $site_id, $username, $ip ) ) {
				return;
			}

			$obj_occurrence = new WSAL_Models_Occurrence();

			$occ = $obj_occurrence->CheckAlert404(
				array(
					$ip,
					$username,
					6007,
					$site_id,
					mktime( 0, 0, 0, $m, $d, $y ),
					mktime( 0, 0, 0, $m, $d + 1, $y ) - 1,
				)
			);

			$occ = count( $occ ) ? $occ[0] : null;
			if ( ! empty( $occ ) ) {
				// Update existing record.
				$this->Increment404( $site_id, $username, $ip );
				$new = ( (int) $occ->GetMetaValue( 'Attempts', 0 ) ) + 1;

				if ( $new > $this->Get404LogLimit() ) {
					$new  = 'more than ' . $this->Get404LogLimit();
					$msg .= ' This could possible be a scan, therefore keep an eye on the activity from this IP Address';
				}

				$link_file = $this->WriteLog( $new, $ip, $username, true, $url_404 );

				$occ->UpdateMetaValue( 'Attempts', $new );
				$occ->UpdateMetaValue( 'Username', $username );
				$occ->UpdateMetaValue( 'Msg', $msg );
				$occ->UpdateMetaValue( 'URL', $url_404 );
				if ( ! empty( $link_file ) ) {
					$occ->UpdateMetaValue( 'LinkFile', $link_file );
				}
				$occ->created_on = null;
				$occ->Save();
			} else {
				$link_file = $this->WriteLog( 1, $ip, $username, true, $url_404 );
				// Create a new record.
				$fields = array(
					'Attempts' => 1,
					'Username' => $username,
					'Msg'      => $msg,
					'URL'      => $url_404,
				);
				if ( ! empty( $link_file ) ) {
					$fields['LinkFile'] = $link_file;
				}
				$this->plugin->alerts->Trigger( 6007, $fields );
			}
		}
	}

	/**
	 * Method: Return true if URL is excluded otherwise false.
	 *
	 * @param string $url - 404 URL.
	 * @return boolean
	 * @since 3.2.2
	 */
	public function is_excluded_url( $url ) {
		if ( empty( $url ) ) {
			return false;
		}

		if ( in_array( $url, $this->plugin->settings()->get_excluded_urls() ) ) {
			return true;
		}
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
		if ( ! current_user_can( 'manage_options' ) && isset( $post_array['_wpnonce'] ) && ! wp_verify_nonce( $post_array['_wpnonce'], 'update' ) ) {
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

		// Registeration Option.
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
		if ( $is_network_settings && ! empty( $post_array['admin_email'] ) ) {
			$old = get_site_option( 'admin_email' );
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

		// Permalinks changed.
		if ( $is_permalink_page && ! empty( $post_array['permalink_structure'] ) ) {
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
		if ( isset( $get_array['action'] ) && 'do-core-upgrade' === $get_array['action'] && isset( $post_array['version'] ) ) {
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

				// Get `site_content` option.
				$site_content = $this->plugin->GetGlobalSetting( 'site_content' );

				// Check if the option is instance of stdClass.
				if ( $site_content instanceof stdClass ) {
					$site_content->skip_core = true; // Set skip core to true to skip file alerts after a core update.
					$this->plugin->SetGlobalSetting( 'site_content', $site_content ); // Save the option.
				}
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

			// Get `site_content` option.
			$site_content = $this->plugin->GetGlobalSetting( 'site_content' );

			// Check if the option is instance of stdClass.
			if ( $site_content instanceof stdClass ) {
				$site_content->skip_core = true; // Set skip core to true to skip file alerts after a core update.
				$this->plugin->SetGlobalSetting( 'site_content', $site_content ); // Save the option.
			}
		}
	}

	/**
	 * Purge log files older than one month.
	 */
	public function LogFilesPruning() {
		if ( $this->plugin->GetGlobalBooleanSetting( 'purge-404-log', false ) ) {
			$this->Prune404FilesByPrefix( '6007' );
		}

		if ( $this->plugin->GetGlobalBooleanSetting( 'purge-visitor-404-log', false ) ) {
			$this->Prune404FilesByPrefix( '6023' );
		}
	}

	/**
	 * Deletes files in 404s log folder that start with a specific prefix.
	 *
	 * @param string $prefix
	 * @since 4.1.3
	 */
	private function Prune404FilesByPrefix( $prefix ) {
		//  prevent deleting all files by accident when the prefix is empty or not what we expect
		if ( ! is_string( $prefix ) || empty( $prefix ) ) {
			return;
		}

		$custom_logging_path = $this->plugin->settings()->get_working_dir_path( '404s', true );
		if ( is_dir( $custom_logging_path ) ) {
			if ( $handle = opendir( $custom_logging_path ) ) {
				while ( false !== ( $entry = readdir( $handle ) ) ) {
					if ( '.' != $entry && '..' != $entry ) {
						if ( strpos( $entry, $prefix ) && file_exists( $custom_logging_path . $entry ) ) {
							$modified = filemtime( $custom_logging_path . $entry );
							if ( $modified < strtotime( '-4 weeks' ) ) {
								// Delete file.
								unlink( $custom_logging_path . $entry );
							}
						}
					}
				}
				closedir( $handle );
			}
		}
	}

	/**
	 * Events from 6008 to 6018.
	 *
	 * @param array $whitelist - White list options.
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

			$old_status = (int) get_option( 'comment_whitelist', 0 );
			$new_status = isset( $post_array['comment_whitelist'] ) ? 1 : 0;

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

			$old_value = get_option( 'blacklist_keys', 0 );
			$new_value = isset( $post_array['blacklist_keys'] ) ? $post_array['blacklist_keys'] : 0;
			if ( $old_value !== $new_value ) {
				$this->plugin->alerts->Trigger( 6018, array() );
			}
		}
		return $whitelist;
	}

	/**
	 * Write Log.
	 *
	 * Write a new line on 404 log file.
	 * Folder: {plugin working folder}/404s/
	 *
	 * @param int $attempts - Number of attempt.
	 * @param string $ip - IP address.
	 * @param string $username - Username.
	 * @param bool $logged_in - True if logged in.
	 * @param string $url - 404 URL.
	 *
	 * @return string|null
	 */
	private function WriteLog( $attempts, $ip, $username = '', $logged_in = true, $url = null ) {
		$name_file = null;
		if ( $logged_in && $this->plugin->GetGlobalBooleanSetting( 'log-404', false ) ) {
			// Get option to log referrer.
			$log_referrer = $this->plugin->GetGlobalBooleanSetting( 'log-404-referrer' );

			// Check localhost.
			if ( '127.0.0.1' == $ip || '::1' == $ip ) {
				$ip = 'localhost';
			}

			if ( $log_referrer ) {
				// Get the referer.
				$referrer = filter_input( INPUT_SERVER, 'HTTP_REFERER', FILTER_SANITIZE_STRING );
				if ( empty( $referrer ) && isset( $_SERVER['HTTP_REFERER'] ) && ! empty( $_SERVER['HTTP_REFERER'] ) ) {
					$referrer = sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
				}

				// Data to write.
				$data = '';

				// Append IP if it exists.
				$data = ( $ip ) ? $ip . ',' : '';

				// Create/Append to the log file.
				$data = $data . 'Request URL ' . $url . ',Referer ' . $referrer . ',';
			} else {
				// Data to write.
				$data = '';

				// Append IP if it exists.
				$data = ( $ip ) ? $ip . ',' : '';

				// Create/Append to the log file.
				$data = $data . 'Request URL ' . $url . ',';
			}

			if ( ! is_user_logged_in() ) {
				$username = '';
			} else {
				$username = $username . '_';
			}

			// get the custom logging path from settings.
			$custom_logging_path = $this->plugin->settings()->get_working_dir_path( '404s' );
			if ( ! is_wp_error( $custom_logging_path ) ) {
				if ( ! file_exists( $custom_logging_path . 'index.php' ) ) {
					// make an empty index.php in the directory.
					@file_put_contents( $custom_logging_path . 'index.php', '<?php // Silence is golden' );
				}

				// Check directory.
				if ( $this->CheckDirectory( $custom_logging_path ) ) {
					$filename  = '6007_' . date( 'Ymd' ) . '.log';
					$fp        = $custom_logging_path . $filename;
					$custom_logging_url  = $this->plugin->settings()->get_working_dir_url( '404s' );
					$name_file = $custom_logging_url . $filename;
					if ( ! $file = fopen( $fp, 'a' ) ) {
						$i           = 1;
						$file_opened = false;
						do {
							$fp2 = substr( $fp, 0, - 4 ) . '_' . $i . '.log';
							if ( ! file_exists( $fp2 ) ) {
								if ( $file = fopen( $fp2, 'a' ) ) {
									$file_opened = true;
									$name_file   = $custom_logging_url . substr( $name_file, 0, - 4 ) . '_' . $i . '.log';
								}
							} else {
								$latest_filename = $this->GetLastModified( $custom_logging_path, $filename );
								$fp_last         = $custom_logging_path . $latest_filename;
								if ( $file = fopen( $fp_last, 'a' ) ) {
									$file_opened = true;
									$name_file   = $custom_logging_url . $latest_filename;
								}
							}
							$i ++;
						} while ( ! $file_opened );
					}
					fwrite( $file, sprintf( "%s\n", $data ) );
					fclose( $file );
				}
			}
		}
		return $name_file;
	}

	/**
	 * Get the latest file modified.
	 *
	 * @param string $uploads_dir_path - Uploads directory path.
	 * @param string $filename - File name.
	 * @return string $latest_filename - File name.
	 */
	private function GetLastModified( $uploads_dir_path, $filename ) {
		$filename        = substr( $filename, 0, -4 );
		$latest_mtime    = 0;
		$latest_filename = '';
		if ( $handle = opendir( $uploads_dir_path ) ) {
			while ( false !== ( $entry = readdir( $handle ) ) ) {
				if ( '.' != $entry && '..' != $entry ) {
					$entry = strip_tags( $entry ); // Strip HTML Tags.
					$entry = preg_replace( '/[\r\n\t ]+/', ' ', $entry ); // Remove Break/Tabs/Return Carriage.
					$entry = preg_replace( '/[\"\*\/\:\<\>\?\'\|]+/', ' ', $entry ); // Remove Illegal Chars for folder and filename.
					if ( preg_match( '/^' . $filename . '/i', $entry ) > 0 ) {
						if ( filemtime( $uploads_dir_path . $entry ) > $latest_mtime ) {
							$latest_mtime    = filemtime( $uploads_dir_path . $entry );
							$latest_filename = $entry;
						}
					}
				}
			}
			closedir( $handle );
		}
		return $latest_filename;
	}
}
