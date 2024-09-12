<?php
/**
 * Sensor: Multisite
 *
 * Multisite sensor class file.
 *
 * @since      4.6.0
 * @package    wsal
 * @subpackage sensors
 */

declare(strict_types=1);

namespace WSAL\WP_Sensors;

use WSAL\Helpers\WP_Helper;
use WSAL\MainWP\MainWP_Addon;
use WSAL\Controllers\Alert_Manager;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\WP_Sensors\WP_Multisite_Sensor' ) ) {
	/**
	 * Multisite sensor.
	 *
	 * 4010 Existing user added to a site
	 * 4011 User removed from site
	 * 5008 Activated theme on network
	 * 5009 Deactivated theme from network
	 * 7000 New site added on the network
	 * 7001 Existing site archived
	 * 7002 Archived site has been unarchived
	 * 7003 Deactivated site has been activated
	 * 7004 Site has been deactivated
	 * 7005 Existing site deleted from network
	 * 7012 Network registration option updated
	 *
	 * @package    wsal
	 * @subpackage sensors
	 */
	class WP_Multisite_Sensor {

		/**
		 * Allowed Themes.
		 *
		 * @var array
		 *
		 * @since 4.5.0
		 */
		private static $old_allowedthemes = null;

		/**
		 * Inits the main hooks
		 *
		 * @return void
		 *
		 * @since 4.5.0
		 */
		public static function init() {
			if ( WP_Helper::is_multisite() || MainWP_Addon::check_mainwp_plugin_active() ) {
				add_action( 'admin_init', array( __CLASS__, 'event_admin_init' ) );
				if ( current_user_can( 'switch_themes' ) ) {
					add_action( 'shutdown', array( __CLASS__, 'event_admin_shutdown' ) );
				}
				add_action( 'wp_insert_site', array( __CLASS__, 'event_new_blog' ), 10, 1 );
				add_action( 'archive_blog', array( __CLASS__, 'event_archive_blog' ) );
				add_action( 'unarchive_blog', array( __CLASS__, 'event_unarchive_blog' ) );
				add_action( 'activate_blog', array( __CLASS__, 'event_activate_blog' ) );
				add_action( 'deactivate_blog', array( __CLASS__, 'event_deactivate_blog' ) );
				add_action( 'wp_uninitialize_site', array( __CLASS__, 'event_delete_blog' ) );
				add_action( 'add_user_to_blog', array( __CLASS__, 'event_user_added_to_blog' ), 10, 3 );
				add_action( 'remove_user_from_blog', array( __CLASS__, 'event_user_removed_from_blog' ), 10, 2 );

				add_action( 'wpmu_upgrade_site', array( __CLASS__, 'event_site_upgraded' ), 10, 1 );

				add_action( 'update_site_option', array( __CLASS__, 'on_network_option_change' ), 10, 4 );
			}
		}

		/**
		 * Fires when the network registration option is updated.
		 *
		 * @param string $option     Name of the network option.
		 * @param mixed  $value      Current value of the network option.
		 * @param mixed  $old_value  Old value of the network option.
		 * @param int    $network_id ID of the network.
		 *
		 * @since 4.5.0
		 */
		public static function on_network_option_change( $option, $value, $old_value, $network_id ) {

			switch ( $option ) {
				case 'registration':
					// Array of potential values.
					$possible_values = array(
						'none' => __( 'disabled', 'wp-security-audit-log' ),
						'user' => __( 'user accounts only', 'wp-security-audit-log' ),
						'blog' => __( 'users can register new sites', 'wp-security-audit-log' ),
						'all'  => __( 'sites & users can be registered', 'wp-security-audit-log' ),
					);
					Alert_Manager::trigger_event(
						7012,
						array(
							'previous_setting' => ( isset( $possible_values[ $old_value ] ) ) ? $possible_values[ $old_value ] : $old_value,
							'new_setting'      => ( isset( $possible_values[ $value ] ) ) ? $possible_values[ $value ] : $value,
						)
					);
					break;

				case 'add_new_users':
					Alert_Manager::trigger_event(
						7007,
						array(
							'EventType' => ( ! $value ) ? 'disabled' : 'enabled',
						)
					);
					break;

				case 'upload_space_check_disabled':
					Alert_Manager::trigger_event(
						7008,
						array(
							'EventType' => ( $value ) ? 'disabled' : 'enabled',
						)
					);
					break;

				case 'blog_upload_space':
					Alert_Manager::trigger_event(
						7009,
						array(
							'old_value' => sanitize_text_field( $old_value ),
							'new_value' => sanitize_text_field( $value ),
						)
					);
					break;

				case 'upload_filetypes':
					Alert_Manager::trigger_event(
						7010,
						array(
							'old_value' => sanitize_text_field( $old_value ),
							'new_value' => sanitize_text_field( $value ),
						)
					);
					break;

				case 'fileupload_maxk':
					Alert_Manager::trigger_event(
						7011,
						array(
							'old_value' => sanitize_text_field( $old_value ),
							'new_value' => sanitize_text_field( $value ),
						)
					);
					break;
			}
		}

		/**
		 * Triggered when a user accesses the admin area.
		 *
		 * @since 4.5.0
		 */
		public static function event_admin_init() {
			self::$old_allowedthemes = array_keys( (array) get_site_option( 'allowedthemes' ) );
		}

		/**
		 * Activated/Deactivated theme on network.
		 *
		 * @since 4.5.0
		 */
		public static function event_admin_shutdown() {
			if ( is_null( self::$old_allowedthemes ) ) {
				return;
			}
			$new_allowedthemes = array_keys( (array) get_site_option( 'allowedthemes' ) );

			// Check for enabled themes.
			foreach ( $new_allowedthemes as $theme ) {
				if ( ! in_array( $theme, (array) self::$old_allowedthemes, true ) ) {
					$theme = wp_get_theme( $theme );
					Alert_Manager::trigger_event(
						5008,
						array(
							'Theme' => (object) array(
								'Name'                   => $theme->Name, // phpcs:ignore
								'ThemeURI'               => $theme->ThemeURI, // phpcs:ignore
								'Description'            => $theme->Description, // phpcs:ignore
								'Author'                 => $theme->Author, // phpcs:ignore
								'Version'                => $theme->Version, // phpcs:ignore
								'get_template_directory' => $theme->get_template_directory(),
							),
						)
					);
				}
			}

			// Check for disabled themes.
			foreach ( (array) self::$old_allowedthemes as $theme ) {
				if ( ! in_array( $theme, $new_allowedthemes, true ) ) {
					$theme = wp_get_theme( $theme );
					Alert_Manager::trigger_event(
						5009,
						array(
							'Theme' => (object) array(
								'Name'                   => $theme->Name, // phpcs:ignore
								'ThemeURI'               => $theme->ThemeURI, // phpcs:ignore
								'Description'            => $theme->Description, // phpcs:ignore
								'Author'                 => $theme->Author, // phpcs:ignore
								'Version'                => $theme->Version, // phpcs:ignore
								'get_template_directory' => $theme->get_template_directory(),
							),
						)
					);
				}
			}
		}

		/**
		 * New site added on the network.
		 *
		 * @param WP_Site $new_blog - New site object.
		 *
		 * @since 4.5.0
		 */
		public static function event_new_blog( $new_blog ) {
			$blog_id = $new_blog->blog_id;

			/*
			 * Site metadata nor options are not setup at this point so get_blog_option and get_home_url are not
			 * returning anything for the new blog.
			 *
			 * The following code to work out the correct URL for the new site was taken from ms-site.php (WP 5.7).
			 * @see https://github.com/WordPress/WordPress/blob/5.7/wp-includes/ms-site.php#L673
			 */
			$network = get_network( $new_blog->network_id );
			if ( ! $network ) {
				$network = get_network();
			}

			$home_scheme = 'http';
			if ( ! is_subdomain_install() ) {
				if ( 'https' === parse_url( get_home_url( $network->site_id ), PHP_URL_SCHEME ) ) {
					$home_scheme = 'https';
				}
			}

			if ( isset( $_POST['blog'] ) && isset( $_POST['blog']['title'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$blog_title = strip_tags( \sanitize_text_field( \wp_unslash( $_POST['blog']['title'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			} elseif ( isset( $_POST['target_title'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$blog_title = strip_tags( \sanitize_text_field( \wp_unslash( $_POST['target_title'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			} else {
				$blog_title = WP_Helper::get_blog_info( $new_blog->blog_id )['name'];
			}
			$blog_url   = untrailingslashit( $home_scheme . '://' . $new_blog->domain . $new_blog->path );

			Alert_Manager::trigger_event(
				7000,
				array(
					'BlogID'   => $blog_id,
					'SiteName' => $blog_title,
					'BlogURL'  => $blog_url,
				)
			);
		}

		/**
		 * Existing site archived.
		 *
		 * @param int $blog_id - Blog ID.
		 *
		 * @since 4.5.0
		 */
		public static function event_archive_blog( $blog_id ) {
			Alert_Manager::trigger_event(
				7001,
				array(
					'BlogID'   => $blog_id,
					'SiteName' => get_blog_option( $blog_id, 'blogname' ),
					'BlogURL'  => get_home_url( $blog_id ),
				)
			);
		}

		/**
		 * Archived site has been unarchived.
		 *
		 * @param int $blog_id - Blog ID.
		 *
		 * @since 4.5.0
		 */
		public static function event_unarchive_blog( $blog_id ) {
			Alert_Manager::trigger_event(
				7002,
				array(
					'BlogID'   => $blog_id,
					'SiteName' => get_blog_option( $blog_id, 'blogname' ),
					'BlogURL'  => get_home_url( $blog_id ),
				)
			);
		}

		/**
		 * Deactivated site has been activated.
		 *
		 * @param int $blog_id - Blog ID.
		 *
		 * @since 4.5.0
		 */
		public static function event_activate_blog( $blog_id ) {
			Alert_Manager::trigger_event(
				7003,
				array(
					'BlogID'   => $blog_id,
					'SiteName' => get_blog_option( $blog_id, 'blogname' ),
					'BlogURL'  => get_home_url( $blog_id ),
				)
			);
		}

		/**
		 * Site has been deactivated.
		 *
		 * @param int $blog_id - Blog ID.
		 *
		 * @since 4.5.0
		 */
		public static function event_deactivate_blog( $blog_id ) {
			Alert_Manager::trigger_event(
				7004,
				array(
					'BlogID'   => $blog_id,
					'SiteName' => get_blog_option( $blog_id, 'blogname' ),
					'BlogURL'  => get_home_url( $blog_id ),
				)
			);
		}

		/**
		 * Existing site deleted from network.
		 *
		 * @param WP_Site $deleted_blog - Deleted blog object.
		 *
		 * @since 4.5.0
		 */
		public static function event_delete_blog( $deleted_blog ) {
			$blog_id = $deleted_blog->blog_id;
			Alert_Manager::trigger_event(
				7005,
				array(
					'BlogID'   => $blog_id,
					'SiteName' => get_blog_option( $blog_id, 'blogname' ),
					'BlogURL'  => get_home_url( $blog_id ),
				)
			);
		}

		/**
		 * Existing user added to a site.
		 *
		 * @param int    $user_id - User ID.
		 * @param string $role - User role.
		 * @param int    $blog_id - Blog ID.
		 *
		 * @since 4.5.0
		 */
		public static function event_user_added_to_blog( $user_id, $role, $blog_id ) {
			$user = get_userdata( $user_id );
			Alert_Manager::trigger_event_if(
				4010,
				array(
					'TargetUserID'   => $user_id,
					'TargetUsername' => $user ? $user->user_login : false,
					'TargetUserRole' => $role,
					'BlogID'         => $blog_id,
					'SiteName'       => get_blog_option( $blog_id, 'blogname' ),
					'FirstName'      => $user ? $user->user_firstname : false,
					'LastName'       => $user ? $user->user_lastname : false,
					'EditUserLink'   => add_query_arg( 'user_id', $user_id, \network_admin_url( 'user-edit.php' ) ),
				),
				array( __CLASS__, 'must_not_contain_create_user' )
			);
		}

		/**
		 * User removed from site.
		 *
		 * @param int $user_id - User ID.
		 * @param int $blog_id - Blog ID.
		 *
		 * @since 4.5.0
		 */
		public static function event_user_removed_from_blog( $user_id, $blog_id ) {
			$user = get_userdata( $user_id );
			Alert_Manager::trigger_event_if(
				4011,
				array(
					'TargetUserID'   => $user_id,
					'TargetUsername' => $user->user_login,
					'TargetUserRole' => is_array( $user->roles ) ? implode( ', ', $user->roles ) : $user->roles,
					'BlogID'         => $blog_id,
					'SiteName'       => get_blog_option( $blog_id, 'blogname' ),
					'FirstName'      => $user ? $user->user_firstname : false,
					'LastName'       => $user ? $user->user_lastname : false,
					'EditUserLink'   => add_query_arg( 'user_id', $user_id, \network_admin_url( 'user-edit.php' ) ),
				),
				array( __CLASS__, 'must_not_contain_create_user' )
			);
		}

		/**
		 * New network user created.
		 *
		 * @return bool
		 *
		 * @since 4.5.0
		 */
		public static function must_not_contain_create_user() {
			return ! Alert_Manager::will_trigger( 4012 );
		}


		/**
		 * Existing site was upgraded.
		 *
		 * @param int $blog_id - Blog ID.
		 *
		 * @since 5.1.1
		 */
		public static function event_site_upgraded( $blog_id ) {
			Alert_Manager::trigger_event(
				7013,
				array(
					'BlogID'   => $blog_id,
					'SiteName' => get_blog_option( $blog_id, 'blogname' ),
					'BlogURL'  => get_home_url( $blog_id ),
					'NewVersion' => get_bloginfo( 'version' ),
				)
			);
		}
	}
}
