<?php
/**
 * MainWP settings for addon.
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

use WSAL\Helpers\Validator;
use WSAL\Helpers\WP_Helper;
use WSAL\Helpers\Settings_Helper;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Migration class
 */
if ( ! class_exists( '\WSAL\MainWP\MainWP_Settings' ) ) {

	/**
	 * Put all you migration methods here
	 *
	 * @package WSAL\MainWP
	 *
	 * @since 5.0.0
	 */
	class MainWP_Settings {

		public const ACTIVE_EXTENSION = 'activity-extension-activated';

		public const MWPAL_OPT_PREFIX = 'mwpal-';

		/**
		 * Gets the value of an option.
		 *
		 * @method get_option_value
		 *
		 * @param string $option_name option name we want to get a value for.
		 * @param mixed  $default     a default value to use when one doesn't exist.
		 *
		 * @return mixed
		 *
		 * @since 5.0.0
		 */
		public static function get_option_value( $option_name = '', $default = null ) {
			// bail early if no option name was requested.
			if ( empty( $option_name ) || ! is_string( $option_name ) ) {
				return;
			}

			$actual_option_name = self::convert_name_prefix_if_needed( $option_name );

			if ( 0 !== strpos( $option_name, WSAL_PREFIX ) ) {
				$actual_option_name = WSAL_PREFIX . $option_name;
			}

			return self::get_option_value_internal( $actual_option_name, $default );
		}

		/**
		 * Sets the value of an option.
		 *
		 * @method set_option_value
		 *
		 * @param string $option_name The name of option to save.
		 * @param mixed  $value       A value to store under the option name.
		 * @param bool   $autoload    Whether or not to autoload this option.
		 *
		 * @return bool Whether or not the option w\as updated.
		 *
		 * @since 5.0.0
		 */
		public static function set_option_value( $option_name = '', $value = null, $autoload = false ) {
			// bail early if no option name or value w\as passed.
			if ( empty( $option_name ) || null === $value ) {
				return;
			}

			$actual_option_name = self::convert_name_prefix_if_needed( $option_name );

			return self::set_option_value_internal( $actual_option_name, $value, $autoload );
		}

		/**
		 * Deletes option value.
		 *
		 * @param string $option_name - The name of the option / setting which needs to be deleted.
		 *
		 * @return mixed
		 *
		 * @since 5.0.0
		 */
		public static function delete_option_value( string $option_name = '' ) {
			$prefixed_name = self::convert_name_prefix_if_needed( $option_name );

			if ( WP_Helper::is_multisite() ) {
				\switch_to_blog( \get_main_network_id() );
			}

			$result = \delete_option( $prefixed_name );

			if ( WP_Helper::is_multisite() ) {
				\restore_current_blog();
			}

			return $result;
		}

		/**
		 * Checks if extension is activated or not.
		 *
		 * @param string $default - Default value to return if option doesn't exist.
		 *
		 * @return string
		 *
		 * @since 5.0.0
		 */
		public static function is_extension_activated( $default = 'no' ) {
			return self::get_option_value( self::ACTIVE_EXTENSION, $default );
		}

		/**
		 * Updates extension activated option.
		 *
		 * @param string $value - Value of the option.
		 *
		 * @return boolean
		 *
		 * @since 5.0.0
		 */
		public static function set_extension_activated( $value ) {
			return self::set_option_value( self::ACTIVE_EXTENSION, $value );
		}

		/**
		 * Determines datetime format to be displayed in any UI in the plugin (logs in administration, emails, reports,
		 * notifications etc.).
		 *
		 * Note: Format returned by this function is not compatible with JavaScript date and time picker widgets. Use
		 * functions get_time_format and get_date_format for those.
		 *
		 * @param boolean $line_break - True if line break otherwise false.
		 *
		 * @return string
		 */
		public function get_date_time_format( $line_break = true, $use_nb_space_for_am_pm = true ) {
			$result = $this->get_date_format();

			$result .= $line_break ? '<\b\r>' : ' ';

			$time_format    = $this->get_time_format();
			$has_am_pm      = false;
			$am_pm_fraction = false;
			$am_pm_pattern  = '/(?i)(\s+A)/';
			if ( preg_match( $am_pm_pattern, $time_format, $am_pm_matches ) ) {
				$has_am_pm      = true;
				$am_pm_fraction = $am_pm_matches[0];
				$time_format    = preg_replace( $am_pm_pattern, '', $time_format );
			}

			// Check if the time format does not have seconds.
			if ( stripos( $time_format, 's' ) === false ) {
				$time_format .= ':s'; // Add seconds to time format.
			}

			$time_format .= '.$$$'; // Add milliseconds to time format.

			if ( $has_am_pm ) {
				$time_format .= preg_replace( '/\s/', $use_nb_space_for_am_pm ? '&\n\b\s\p;' : ' ', $am_pm_fraction );
			}

			$result .= $time_format;

			return $result;
		}

		/**
		 * Date format based on WordPress date settings. It can be optionally sanitized to get format compatible with
		 * JavaScript date and time picker widgets.
		 *
		 * Note: This function must not be used to display actual date and time values anywhere. For that use function get_date_time_format.
		 *
		 * @param bool $sanitized If true, the format is sanitized for use with JavaScript date and time picker widgets.
		 *
		 * @return string
		 */
		public function get_date_format( $sanitized = false ) {
			if ( $sanitized ) {
				return 'Y-m-d';
			}

			return get_option( 'date_format' );
		}

		/**
		 * Time format based on WordPress date settings. It can be optionally sanitized to get format compatible with
		 * JavaScript date and time picker widgets.
		 *
		 * Note: This function must not be used to display actual date and time values anywhere. For that use function get_date_time_format.
		 *
		 * @param bool $sanitize If true, the format is sanitized for use with JavaScript date and time picker widgets.
		 *
		 * @return string
		 */
		public function get_time_format( $sanitize = false ) {
			$result = get_option( 'time_format' );
			if ( $sanitize ) {
				$search  = array( 'a', 'A', 'T', ' ' );
				$replace = array( '', '', '', '' );
				$result  = str_replace( $search, $replace, $result );
			}
			return $result;
		}

		/**
		 * Get MainWP Child Sites.
		 *
		 * @return array
		 */
		public static function get_mwp_child_sites() {
			return apply_filters( 'mainwp_getsites', WSAL_BASE_FILE_NAME, MainWP_Addon::get_child_key(), null );
		}

		/**
		 * Get MainWP child site by site ID.
		 *
		 * @param int $site_id Site ID.
		 *
		 * @return array|null
		 */
		public static function get_mwp_child_site_by_id( $site_id ) {
			// Get MainWP child sites.
			$mwp_sites = self::get_mwp_child_sites();

			// Search for the site data.
			$key = array_search( $site_id, array_column( $mwp_sites, 'id' ), false );

			if ( false !== $key && isset( $mwp_sites[ $key ] ) ) {
				return $mwp_sites[ $key ];
			}

			return null;
		}

		/**
		 * Set Alert Views Per Page.
		 *
		 * @param int $newvalue – New value.
		 */
		public function set_view_per_page( $newvalue ) {
			$perpage = max( $newvalue, 1 );
			$this->update_option( 'items-per-page', $perpage );
		}

		/**
		 * Get Alert Views Per Page.
		 *
		 * @return int
		 */
		public function get_view_per_page() {
			return (int) $this->get_option( 'items-per-page', 10 );
		}

		/**
		 * Return Site ID.
		 *
		 * @return integer
		 *
		 * @since 5.0.0
		 */
		public static function get_view_site_id() {
			// @codingStandardsIgnoreStart
			$site_id = isset( $_REQUEST['mwpal-site-id'] ) ? sanitize_text_field( $_REQUEST['mwpal-site-id'] ) : 0; // Site ID.
			// @codingStandardsIgnoreEnd

			if ( 'dashboard' !== $site_id ) {
				return (int) $site_id;
			}

			return $site_id;
		}

		/**
		 * Method: Get number of hours since last logged alert.
		 *
		 * @return mixed – False if $created_on is empty | Number of hours otherwise.
		 *
		 * @param float $created_on – Timestamp of last logged alert.
		 */
		public function get_hours_since_last_alert( $created_on ) {
			// If $created_on is empty, then return.
			if ( empty( $created_on ) ) {
				return false;
			}

			// Last alert date.
			$created_date = new \DateTime( date( 'Y-m-d H:i:s', (int) $created_on ) );

			// Current date.
			$current_date = new \DateTime( 'NOW' );

			// Calculate time difference.
			$time_diff = $current_date->diff( $created_date );
			$diff_days = $time_diff->d; // Difference in number of days.
			$diff_hrs  = $time_diff->h; // Difference in number of hours.
			$total_hrs = ( $diff_days * 24 ) + $diff_hrs; // Total number of hours.

			// Return difference in hours.
			return $total_hrs;
		}

		/**
		 * Return audit log columns.
		 *
		 * @return array
		 */
		public function get_columns() {
			$columns = array(
				'site'       => '1',
				'alert_code' => '1',
				'type'       => '1',
				'info'       => '1',
				'date'       => '1',
				'username'   => '1',
				'event_type' => '1',
				'object'     => '1',
				'source_ip'  => '1',
				'message'    => '1',
			);

			// Get selected columns.
			$selected = $this->get_columns_selected();

			if ( ! empty( $selected ) ) {
				$columns  = array(
					'site'       => '0',
					'alert_code' => '0',
					'type'       => '0',
					'info'       => '0',
					'date'       => '0',
					'username'   => '0',
					'event_type' => '0',
					'object'     => '0',
					'source_ip'  => '0',
					'message'    => '0',
				);
				$selected = (array) json_decode( $selected );
				$columns  = array_merge( $columns, $selected );
			}
			return $columns;
		}

		/**
		 * Get Selected Columns.
		 *
		 * @return string
		 */
		public function get_columns_selected() {
			return $this->get_option( 'columns' );
		}

		/**
		 * Set Columns.
		 *
		 * @param array $columns – Columns.
		 */
		public function set_columns( $columns ) {
			$this->update_option( 'columns', wp_json_encode( $columns ) );
		}

		/**
		 * Set WSAL Child Sites.
		 *
		 * @param array $site_ids – Array of Site ids.
		 * @return void
		 */
		public function set_wsal_child_sites( $site_ids ) {
			$wsal_sites     = $this->get_wsal_child_sites(); // Get WSAL child sites.
			$disabled_sites = $this->get_option( 'disabled-wsal-sites', array() ); // Get already disabled sites.
			$new_sites      = array();

			// Set new WSAL sites.
			if ( ! empty( $site_ids ) && is_array( $site_ids ) ) {
				foreach ( $site_ids as $id ) {
					if ( isset( $wsal_sites[ $id ] ) ) {
						$new_sites[ $id ] = $wsal_sites[ $id ];
						unset( $wsal_sites[ $id ] );
					} elseif ( isset( $disabled_sites[ $id ] ) ) {
						$new_sites[ $id ] = $disabled_sites[ $id ];
						unset( $disabled_sites[ $id ] );
					} else {
						$new_sites[ $id ] = new \stdClass();
					}
				}
			}

			// Remove events of removed sites from the DB.
			if ( ! empty( $wsal_sites ) && is_array( $wsal_sites ) ) {
				foreach ( $wsal_sites as $site_id => $site ) {
					// Delete events by site id.
					$delete_query = new \WSAL\MainWPExtension\Models\OccurrenceQuery();
					$delete_query->addCondition( 'site_id = %s ', $site_id );
					$delete_query->getAdapter()->Delete( $delete_query );
					$disabled_sites[ $site_id ] = $site;
				}
			}

			$this->update_option( 'wsal-child-sites', $new_sites );
			$this->update_option( 'disabled-wsal-sites', $disabled_sites );
		}

		/**
		 * Get Timezone.
		 *
		 * @return string
		 */
		public function get_timezone() {
			return $this->get_option( 'timezone', 'wp' );
		}

		/**
		 * Set Timezone.
		 *
		 * @param string $newvalue – New value.
		 */
		public function set_timezone( $newvalue ) {
			$this->update_option( 'timezone', $newvalue );
		}

		/**
		 * Get Username Type.
		 *
		 * @return string
		 */
		public function get_type_username() {
			return $this->get_option( 'type_username', 'display_name' );
		}

		/**
		 * Set Username Type.
		 *
		 * @param string $newvalue – New value.
		 */
		public function set_type_username( $newvalue ) {
			$this->update_option( 'type_username', $newvalue );
		}

		/**
		 * Get number of child site events.
		 *
		 * @return integer
         * 
         * @since 5.0.0
		 */
		public static function get_child_site_events() {
			return self::get_option_value( 'child_site_events', 100 );
		}

		/**
		 * Set number of child site events.
		 *
		 * @param integer $newvalue – New value.
		 */
		public function set_child_site_events( $newvalue ) {
			$this->update_option( 'child_site_events', $newvalue );
		}

		/**
		 * Get Events Frequency.
		 *
		 * @return integer
		 */
		public function get_events_frequency() {
			return $this->get_option( 'events_frequency', 3 );
		}

		/**
		 * Set Events Frequency.
		 *
		 * @param integer $newvalue – New value.
		 */
		public function set_events_frequency( $newvalue ) {
			$this->update_option( 'events_frequency', $newvalue );
		}

		/**
		 * Get Current User Roles.
		 *
		 * @param array $base_roles – Base roles.
		 * @return array
		 */
		public function get_current_user_roles( $base_roles = null ) {
			if ( null === $base_roles ) {
				$base_roles = wp_get_current_user()->roles;
			}
			if ( function_exists( 'is_super_admin' ) && is_super_admin() ) {
				$base_roles[] = 'superadmin';
			}
			return $base_roles;
		}

		/**
		 * Check if the user is super admin.
		 *
		 * @param string $username – Username.
		 * @return boolean
		 */
		public function is_login_super_admin( $username ) {
			$user_id = username_exists( $username );
			return function_exists( 'is_super_admin' ) && is_super_admin( $user_id );
		}

		/**
		 * Get Server IP.
		 *
		 * @return string
		 *
		 * @since 5.0.0
		 */
		public static function get_server_ip() {
			return self::retrieve_ip_address_for( 'SERVER_ADDR' );
		}

		/**
		 * Get Client IP.
		 *
		 * @return string
		 */
		public static function get_main_client_ip() {
			return self::retrieve_ip_address_for( 'REMOTE_ADDR' );
		}

		/**
		 * Retrieves ip address from global server array based on string value.
		 *
		 * @param string $which - String value to retrieve from server array.
		 *
		 * @return null|string
		 *
		 * @since 5.0.0
		 */
		private static function retrieve_ip_address_for( string $which ) {
			$result = null;
			if ( isset( $_SERVER[ $which ] ) ) {
				$result = Settings_Helper::normalize_ip( sanitize_text_field( wp_unslash( $_SERVER[ $which ] ) ) );
				if ( ! Validator::validate_ip( $result ) ) {
					$result = 'Error : Invalid IP Address';
				}
			}

			return $result;
		}

		/**
		 * Search & Return MainWP site.
		 *
		 * @param string $value – Column value.
		 * @param string $column – Column name.
		 *
		 * @return mixed
		 */
		public static function get_mwp_site_by( $value, $column = 'id' ) {
			// Get MainWP sites.
			$mwp_sites = self::get_mwp_child_sites();

			// Search by column name.
			$key = array_search( $value, array_column( $mwp_sites, $column ), true );
			if ( false !== $key ) {
				return $mwp_sites[ $key ];
			}
			return false;
		}

		/**
		 * Get last checked timestamp by site id.
		 *
		 * @since 1.0.1
		 *
		 * @param integer $site_id - Site id.
		 * @return mixed
		 */
		public function get_last_checked_by_siteid( $site_id = 0 ) {
			if ( $site_id ) {
				return $this->get_option( 'mwpal_last_checked_site_' . $site_id );
			}
			return false;
		}

		/**
		 * Set last checked timestamp by site id.
		 *
		 * @param string  $last_checked - Last checked timestamp.
		 * @param integer $site_id - Site id.
		 *
		 * @since 1.0.1
		 */
		public function set_last_checked_by_siteid( $last_checked, $site_id = 0 ) {
			if ( $site_id ) {
				$this->update_option( 'mwpal_last_checked_site_' . $site_id, $last_checked );
			}
		}

		/**
		 * Return Events Navigation Type.
		 *
		 * @since 1.1
		 *
		 * @return string
		 */
		public function get_events_type_nav() {
			return $this->get_option( 'events-nav-type', 'infinite-scroll' );
		}

		/**
		 * Sets Events Navigation Type.
		 *
		 * @since 1.1
		 *
		 * @param string $nav_type - Navigation type.
		 */
		public function set_events_type_nav( $nav_type ) {
			$this->update_option( 'events-nav-type', $nav_type );
		}

		/**
		 * Returns true if infinite scroll, otherwise false.
		 *
		 * @since 1.1
		 *
		 * @return boolean
		 */
		public function is_infinite_scroll() {
			return 'infinite-scroll' === $this->get_events_type_nav();
		}

		/**
		 * Sets Events Global Sync Option.
		 *
		 * @since 1.1
		 *
		 * @param bool $enabled - True if enabled, false otherwise.
		 */
		public function set_events_global_sync( $enabled ) {
			$this->update_option( 'events-global-sync', $enabled );
		}

		/**
		 * Returns true if events global sync is set, otherwise false.
		 *
		 * @since 1.1
		 *
		 * @return boolean
		 */
		public function is_events_global_sync() {
			return $this->get_option( 'events-global-sync' );
		}

		/**
		 * Checks if current admin page is extension's page or not.
		 *
		 * @since 1.1
		 *
		 * @return boolean
		 */
		public function is_current_extension_page() {
			global $pagenow;
			$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : false; // phpcs:ignore

			if ( 'admin.php' === $pagenow && MWPAL_EXTENSION_NAME === $page ) {
				return true;
			}

			return false;
		}

		/**
		 * Meta data formater.
		 *
		 * @param string  $name   - Name of the data.
		 * @param mixed   $value  - Value of the data.
		 * @param integer $occ_id - Event occurrence id.
		 * @return string
		 */
		public function meta_formatter( $name, $value, $occ_id ) {
			switch ( true ) {
				case '%Message%' == $name:
					return esc_html( $value );

				case '%PromoMessage%' == $name:
					return '<p class="promo-alert">' . $value . '</p>';

				case '%PromoLink%' == $name:
				case '%CommentLink%' == $name:
				case '%CommentMsg%' == $name:
					return $value;

				case '%MetaLink%' == $name:
					if ( ! empty( $value ) ) {
						return "<a href=\"#\" data-disable-custom-nonce='" . wp_create_nonce( 'disable-custom-nonce' . $value ) . "' onclick=\"WsalDisableCustom(this, '" . $value . "');\"> Exclude Custom Field from the Monitoring</a>";
					} else {
						return '';
					}

				case '%RevisionLink%' === $name:
					$check_value = (string) $value;
					if ( 'NULL' !== $check_value ) {
						return ' Click <a target="_blank" href="' . esc_url( $value ) . '">here</a> to see the content changes.';
					} else {
						return false;
					}

				case '%EditorLinkPost%' == $name:
					return ' View the <a target="_blank" href="' . esc_url( $value ) . '">post</a>';

				case '%EditorLinkPage%' == $name:
					return ' View the <a target="_blank" href="' . esc_url( $value ) . '">page</a>';

				case '%CategoryLink%' == $name:
					return ' View the <a target="_blank" href="' . esc_url( $value ) . '">category</a>';

				case '%TagLink%' == $name:
					return ' View the <a target="_blank" href="' . esc_url( $value ) . '">tag</a>';

				case '%EditorLinkForum%' == $name:
					return ' View the <a target="_blank" href="' . esc_url( $value ) . '">forum</a>';

				case '%EditorLinkTopic%' == $name:
					return ' View the <a target="_blank" href="' . esc_url( $value ) . '">topic</a>';

				case in_array( $name, array( '%MetaValue%', '%MetaValueOld%', '%MetaValueNew%' ) ):
					return '<strong>' . (
					strlen( $value ) > 50 ? ( esc_html( substr( $value, 0, 50 ) ) . '&hellip;' ) : esc_html( $value )
					) . '</strong>';

				case '%ClientIP%' == $name:
					if ( is_string( $value ) ) {
						return '<strong>' . str_replace( array( '"', '[', ']' ), '', $value ) . '</strong>';
					} else {
						return '<i>unknown</i>';
					}

				case '%LinkFile%' === $name:
					if ( 'NULL' != $value ) {
						$site_id = $this->get_view_site_id(); // Site id for multisite.
						return '<a href="javascript:;" onclick="download_404_log( this )" data-log-file="' . esc_attr( $value ) . '" data-site-id="' . esc_attr( $site_id ) . '" data-nonce-404="' . esc_attr( wp_create_nonce( 'wsal-download-404-log-' . $value ) ) . '" title="' . esc_html__( 'Download the log file', 'mwp-al-ext' ) . '">' . esc_html__( 'Download the log file', 'mwp-al-ext' ) . '</a>';
					} else {
						return 'Click <a href="' . esc_url( add_query_arg( 'page', 'wsal-togglealerts', admin_url( 'admin.php' ) ) ) . '">here</a> to log such requests to file';
					}

				case '%URL%' === $name:
					return ' or <a href="javascript:;" data-exclude-url="' . esc_url( $value ) . '" data-exclude-url-nonce="' . wp_create_nonce( 'wsal-exclude-url-' . $value ) . '" onclick="wsal_exclude_url( this )">exclude this URL</a> from being reported.';

				case '%LogFileLink%' === $name: // Failed login file link.
					return '';

				case '%Attempts%' === $name: // Failed login attempts.
					$check_value = (int) $value;
					if ( 0 === $check_value ) {
						return '';
					} else {
						return $value;
					}

				case '%LogFileText%' === $name: // Failed login file text.
					return '<a href="javascript:;" onclick="download_failed_login_log( this )" data-download-nonce="' . esc_attr( wp_create_nonce( 'wsal-download-failed-logins' ) ) . '" title="' . esc_html__( 'Download the log file.', 'mwp-al-ext' ) . '">' . esc_html__( 'Download the log file.', 'mwp-al-ext' ) . '</a>';

				case strncmp( $value, 'http://', 7 ) === 0:
				case strncmp( $value, 'https://', 7 ) === 0:
					return '<a href="' . esc_html( $value ) . '" title="' . esc_html( $value ) . '" target="_blank">' . esc_html( $value ) . '</a>';

				case '%PostStatus%' === $name:
					if ( ! empty( $value ) && 'publish' === $value ) {
						return '<strong>' . esc_html__( 'published', 'mwp-al-ext' ) . '</strong>';
					} else {
						return '<strong>' . esc_html( $value ) . '</strong>';
					}

				case '%multisite_text%' === $name:
					if ( $this->is_multisite() && $value ) {
						$site_info = get_blog_details( $value, true );
						if ( $site_info ) {
							return ' on site <a href="' . esc_url( $site_info->siteurl ) . '">' . esc_html( $site_info->blogname ) . '</a>';
						}
						return;
					}
					return;

				case '%ReportText%' === $name:
					return;

				case '%ChangeText%' === $name:
					if ( $occ_id ) {
						$url_args = array(
							'action'     => 'AjaxInspector',
							'occurrence' => $occ_id,
							'TB_iframe'  => 'true',
							'width'      => 600,
							'height'     => 550,
						);
						$url      = add_query_arg( $url_args, admin_url( 'admin-ajax.php' ) );
						return ' View the changes in <a class="thickbox"  title="' . __( 'Alert Data Inspector', 'mwp-al-ext' ) . '"'
						. ' href="' . $url . '">data inspector.</a>';
					} else {
						return;
					}

				case '%ScanError%' === $name:
					if ( 'NULL' === $value ) {
						return false;
					}
					/* translators: Mailto link for support. */
					return ' with errors. ' . sprintf( __( 'Contact us on %s for assistance', 'mwp-al-ext' ), '<a href="mailto:support@wpactivitylog.com" target="_blank">support@melapress.com</a>' );

				case '%TableNames%' === $name:
					$value = str_replace( ',', ', ', $value );
					return '<strong>' . esc_html( $value ) . '</strong>';

				case '%FileSettings%' === $name:
					$file_settings_args = array(
						'page' => 'wsal-settings',
						'tab'  => 'file-changes',
					);
					$file_settings      = add_query_arg( $file_settings_args, admin_url( 'admin.php' ) );
					return '<a href="' . esc_url( $file_settings ) . '">' . esc_html__( 'plugin settings', 'mwp-al-ext' ) . '</a>';

				case '%ContactSupport%' === $name:
					return '<a href="https://wpactivitylog.com/contact/" target="_blank">' . esc_html__( 'contact our support', 'mwp-al-ext' ) . '</a>';

				default:
					return '<strong>' . esc_html( $value ) . '</strong>';
			}
		}

		/**
		 * Set disabled events.
		 *
		 * @param array $types - IDs events to disable.
		 */
		public function set_disabled_events( $types ) {
			$disabled = array_unique( array_map( 'intval', $types ) );
			$this->update_option( 'disabled-events', implode( ',', $disabled ) );
		}

		/**
		 * Return IDs of disabled events.
		 *
		 * @return array
		 */
		public function get_disabled_events() {
			$disabled = $this->get_option( 'disabled-events', false );
			$disabled = ! $disabled ? array() : explode( ',', $disabled );
			$disabled = array_map( 'intval', $disabled );
			return $disabled;
		}

		/**
		 * Set events pruning.
		 *
		 * @param string $state - Enabled or disabled state.
		 */
		public function set_events_pruning( $state ) {
			$this->update_option( 'events-pruning', $state );
		}

		/**
		 * Checks if events pruning is enabled or disabled.
		 *
		 * @return boolean
		 */
		public function is_events_pruning() {
			$pruning = $this->get_option( 'events-pruning', 'disabled' );
			return 'enabled' === $pruning ? true : false;
		}

		/**
		 * Set events pruning date.
		 *
		 * @param string $date - Date.
		 * @param string $unit - Time unit.
		 */
		public function set_pruning_date( $date, $unit ) {
			$date_obj       = new \stdClass();
			$date_obj->date = $date;
			$date_obj->unit = $unit;
			$this->update_option( 'pruning-date', $date_obj );
		}

		/**
		 * Get events pruning date.
		 *
		 * @return stdClass|bool
		 */
		public function get_pruning_date() {
			$default       = new \stdClass();
			$default->date = false;
			$default->unit = false;
			return $this->get_option( 'pruning-date', $default );
		}

		public function get_enforce_settings_on_subsites() {
			return $this->get_option( 'enforce_settings_on_subsites', 'none' );
		}

		public function set_enforce_settings_on_subsites( $value ) {
			return $this->update_option( 'enforce_settings_on_subsites', $value );
		}

		public function get_sites_with_enforced_settings() {
			$result = array();
			$sites  = $this->get_option( 'subsites_with_enforced_settings' );
			if ( ! empty( $sites ) ) {
				if ( is_string( $sites ) ) {
					$result = array_map( 'intval', explode( ',', $sites ) );
				}
			}

			return $result;
		}

		public function set_sites_with_enforced_settings( $site_ids ) {
			if ( empty( $site_ids ) ) {
				return $this->delete_option( 'subsites_with_enforced_settings' );
			}
			return $this->update_option( 'subsites_with_enforced_settings', implode( ',', $site_ids ) );
		}

		public function get_enforced_child_sites_settings() {
			return $this->get_option( 'enforced_settings_data', array() );
		}

		public function set_enforced_child_sites_settings( $value ) {
			return $this->update_option( 'enforced_settings_data', $value );
		}

		/**
		 * Sets the setting driving the automatic addition of new sites to the list of sites that have WSAL.
		 *
		 * @param bool $enabled - True if enabled, false otherwise.
		 *
		 * @since 2.0.0
		 */
		public function set_automatically_add_new_sites( $enabled ) {
			$this->update_option( 'auto-add-new-sites', $enabled );
		}

		/**
		 * Returns true if new sites are automatically added to the list of sites that have WSAL. False otherwise.
		 *
		 * @since 2.0.0
		 *
		 * @return boolean
		 */
		public function can_automatically_add_new_sites() {
			return $this->get_option( 'auto-add-new-sites', true );
		}

		/**
		 * Adds prefix to the given option name is necessary.
		 *
		 * @param string $name - The option name to check and add prefix if needed.
		 *
		 * @since 5.0.0
		 */
		private static function convert_name_prefix_if_needed( string $name ): string {
			$name = trim( $name );
			if ( 0 !== strpos( $name, self::MWPAL_OPT_PREFIX ) ) {
				$name = self::MWPAL_OPT_PREFIX . $name;
			}

			return $name;
		}

		/**
		 * Internal function used to get the value of an option. Any necessary prefixes are already contained in the option
		 * name.
		 *
		 * @param string $option_name Option name we want to get a value for including necessary plugin prefix.
		 * @param mixed  $default     a default value to use when one doesn't exist.
		 *
		 * @return mixed
		 *
		 * @since 5.0.0
		 */
		private static function get_option_value_internal( $option_name = '', $default = null ) {
			// bail early if no option name w\as requested.
			if ( empty( $option_name ) || ! is_string( $option_name ) ) {
				return;
			}

			if ( WP_Helper::is_multisite() ) {
				\switch_to_blog( \get_main_network_id() );
			}

			$result = \get_option( $option_name, $default );

			if ( WP_Helper::is_multisite() ) {
				\restore_current_blog();
			}

			return \maybe_unserialize( $result );
		}

		/**
		 * Internal function used to set the value of an option. Any necessary prefixes are already contained in the option
		 * name.
		 *
		 * @param string $option_name Option name we want to save a value for including necessary plugin prefix.
		 * @param mixed  $value       A value to store under the option name.
		 * @param bool   $autoload    Whether to autoload this option.
		 *
		 * @return bool Whether the option w\as updated.
		 *
		 * @since 5.0.0
		 */
		private static function set_option_value_internal( $option_name = '', $value = null, $autoload = false ) {
			// bail early if no option name or value w\as passed.
			if ( empty( $option_name ) || null === $value ) {
				return;
			}

			if ( WP_Helper::is_multisite() ) {
				\switch_to_blog( \get_main_network_id() );
			}

			if ( false === $autoload ) {
				\delete_option( $option_name );
				$result = \add_option( $option_name, $value, '', $autoload );
			} else {
				$result = \update_option( $option_name, $value, $autoload );
			}

			if ( WP_Helper::is_multisite() ) {
				\restore_current_blog();
			}

			return $result;
		}
	}
}
