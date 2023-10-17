<?php
/**
 * YOAST Sensor helper
 *
 * @since     4.6.0
 * @package   wsal
 * @subpackage sensors
 */

declare(strict_types=1);

namespace WSAL\WP_Sensors\Helpers;

use WSAL\Helpers\WP_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\WP_Sensors\Helpers\Yoast_SEO_Helper' ) ) {

	/**
	 * Helper Sensor class for YOAST.
	 *
	 * @package    wsal
	 * @subpackage sensors-helpers
	 * @since      4.6.0
	 */
	class Yoast_SEO_Helper {
		/**
		 * Adds new custom event objects for our plugin
		 *
		 * @method wsal_yoast_seo_extension_add_custom_event_objects
		 * @since 4.6.0
		 * @param  array $objects An array of default objects.
		 * @return array
		 */
		public static function wsal_yoast_seo_extension_add_custom_event_objects( $objects ) {
			$new_objects = array(
				'yoast-seo'                   => esc_html__( 'Yoast SEO', 'wp-security-audit-log' ),
				'yoast-seo-metabox'           => esc_html__( 'Yoast SEO Meta Box', 'wp-security-audit-log' ),
				'yoast-seo-search-appearance' => esc_html__( 'Yoast SEO Search Appearance', 'wp-security-audit-log' ),
				'yoast-seo-redirects'         => esc_html__( 'Yoast SEO Redirects', 'wp-security-audit-log' ),
			);

			// combine the two arrays.
			$objects = array_merge( $objects, $new_objects );

			return $objects;
		}

		/**
		 * Add specific events so we can use them for category titles.
		 *
		 * @param  array $sub_category_events - Current event list.
		 * @return array $sub_category_events - Appended list.
		 *
		 * @since 4.6.0
		 */
		public static function wsal_yoast_seo_extension_togglealerts_sub_category_events( $sub_category_events ) {
			$new_events          = array( 8813, 8815, 8838 );
			$sub_category_events = array_merge( $sub_category_events, $new_events );
			return $sub_category_events;
		}

		/**
		 * Add sub category titles to ToggleView page in WSAL.
		 *
		 * @param  string $subcat_title - Original title.
		 * @param  int    $alert_id - Alert ID.
		 * @return string $subcat_title - New title.
		 *
		 * @since 4.6.0
		 */
		public static function wsal_yoast_seo_extension_togglealerts_sub_category_titles( $subcat_title, $alert_id ) {
			if ( 8815 === $alert_id ) {
				$subcat_title = esc_html_e( 'Features:', 'wp-security-audit-log' );
			} elseif ( 8813 === $alert_id ) {
				$subcat_title = esc_html_e( 'Search Appearance', 'wp-security-audit-log' );
			} elseif ( 8838 === $alert_id ) {
				$subcat_title = esc_html_e( 'Multisite network', 'wp-security-audit-log' );
			}
			return $subcat_title;
		}

		/**
		 * Add obsolete events to the togglealerts view.
		 *
		 * @param  array $obsolete_events - Current events.
		 * @return array $obsolete_events - Appended events.
		 *
		 * @since 4.6.0
		 */
		public static function wsal_yoast_seo_extension_togglealerts_obsolete_events( $obsolete_events ) {
			$new_events      = array( 8810, 8811 );
			$obsolete_events = array_merge( $obsolete_events, $new_events );
			return $obsolete_events;
		}

		/**
		 * Checks if the YOAST is active.
		 *
		 * @return boolean
		 *
		 * @since 4.6.0
		 */
		public static function is_wpseo_active() {
			return ( WP_Helper::is_plugin_active( 'wordpress-seo/wp-seo.php' ) || WP_Helper::is_plugin_active( 'wordpress-seo-premium/wp-seo-premium.php' ) );
		}

		/**
		 * Gets the filename of the plugin this extension is targeting.
		 *
		 * @return string Filename.
		 *
		 * @since 4.5.0
		 */
		public static function get_plugin_filename(): string {
			return 'activity-log-wp-seo/activity-log-wp-seo.php';
		}
	}
}
