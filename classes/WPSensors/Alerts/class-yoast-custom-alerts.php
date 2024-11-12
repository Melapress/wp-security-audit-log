<?php
/**
 * Custom Alerts for Yoast plugin.
 *
 * Class file for alert manager.
 *
 * @since   1.0.0
 *
 * @package wsal
 * @subpackage wsal-yoast-forms
 */

// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment
// phpcs:disable WordPress.WP.I18n.UnorderedPlaceholdersText

declare(strict_types=1);

namespace WSAL\WP_Sensors\Alerts;

use WSAL\MainWP\MainWP_Addon;
use WSAL\WP_Sensors\Helpers\Yoast_SEO_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\WP_Sensors\Alerts\Yoast_Custom_Alerts' ) ) {
	/**
	 * Custom sensor for Yoast plugin.
	 *
	 * @since 4.6.0
	 */
	class Yoast_Custom_Alerts {

		/**
		 * Returns the structure of the alerts for extension.
		 *
		 * @return array
		 *
		 * @since 4.6.0
		 */
		public static function get_custom_alerts(): array {
			// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment
			if ( Yoast_SEO_Helper::is_wpseo_active() || MainWP_Addon::check_mainwp_plugin_active() ) {
				return array(
					esc_html__( 'Yoast SEO', 'wp-security-audit-log' ) => array(
						esc_html__( 'Post Changes', 'wp-security-audit-log' )    =>
						self::get_post_changes_array(),

						esc_html__( 'Website Changes', 'wp-security-audit-log' ) => self::get_website_changes_array(),

						esc_html__( 'Plugin Settings Changes', 'wp-security-audit-log' ) => self::get_plugin_settings_changes(),
					),
				);
			}

			return array();
		}

		/**
		 * Returns array with all the events attached to the sensor (if there are different types of events, that method will merge them into one array - the events ids will be uses as keys)
		 *
		 * @return array
		 *
		 * @since 4.6.0
		 */
		public static function get_alerts_array(): array {
			return self::get_post_changes_array() +
			self::get_website_changes_array() +
			self::get_plugin_settings_changes();
		}

		/**
		 * Returns the array with plugin (YOAST) settings changes alerts
		 *
		 * @return array
		 *
		 * @since 4.6.0
		 */
		private static function get_plugin_settings_changes(): array {
			return array(
				8815 => array(
					8815,
					WSAL_MEDIUM,
					esc_html__( 'User Enabled/Disabled SEO analysis in the Yoast SEO plugin settings', 'wp-security-audit-log' ),
					__( 'Changed the status of the <strong>SEO Analysis</strong> plugin feature.', 'wp-security-audit-log' ),
					array(),
					array(),
					'yoast-seo',
					'enabled',
				),
				8816 => array(
					8816,
					WSAL_MEDIUM,
					esc_html__( 'User Enabled/Disabled readability analysis in the Yoast SEO plugin settings', 'wp-security-audit-log' ),
					__( 'Changed the <strong>Readability Analysis</strong> plugin feature.', 'wp-security-audit-log' ),
					array(),
					array(),
					'yoast-seo',
					'enabled',
				),
				8817 => array(
					8817,
					WSAL_MEDIUM,
					esc_html__( 'User Enabled/Disabled cornerstone content in the Yoast SEO plugin settings', 'wp-security-audit-log' ),
					__( 'Changed the <strong>Cornerstone content</strong> plugin feature.', 'wp-security-audit-log' ),
					array(),
					array(),
					'yoast-seo',
					'enabled',
				),
				8818 => array(
					8818,
					WSAL_MEDIUM,
					esc_html__( 'User Enabled/Disabled the text link counter in the Yoast SEO plugin settings', 'wp-security-audit-log' ),
					__( 'Changed the <strong>Text link counter</strong> plugin feature.', 'wp-security-audit-log' ),
					array(),
					array(),
					'yoast-seo',
					'enabled',
				),
				8819 => array(
					8819,
					WSAL_MEDIUM,
					esc_html__( 'User Enabled/Disabled XML sitemaps in the Yoast SEO plugin settings', 'wp-security-audit-log' ),
					__( 'Changed the <strong>XML sitemap</strong> plugin feature.', 'wp-security-audit-log' ),
					array(),
					array(),
					'yoast-seo',
					'enabled',
				),
				8820 => array(
					8820,
					WSAL_MEDIUM,
					esc_html__( 'User Enabled/Disabled an integration in the Yoast SEO plugin settings', 'wp-security-audit-log' ),
					esc_html__( 'Changed the status of the %type% integration.', 'wp-security-audit-log' ),
					array(),
					array(),
					'yoast-seo',
					'enabled',
				),

				8821 => array(
					8821,
					WSAL_MEDIUM,
					esc_html__( 'User Enabled/Disabled the admin bar menu in the Yoast SEO plugin settings', 'wp-security-audit-log' ),
					__( 'Changed the <strong>Admin bar menu</strong> plugin feature.', 'wp-security-audit-log' ),
					array(),
					array(),
					'yoast-seo',
					'enabled',
				),
				8822 => array(
					8822,
					WSAL_INFORMATIONAL,
					esc_html__( 'User changed the Posts/Pages meta description template in the Yoast SEO plugin settings', 'wp-security-audit-log' ),
					esc_html__( 'Changed the %SEOPostType% Meta description template to %new%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous template', 'wp-security-audit-log' ) => '%old%',
					),
					array(),
					'yoast-seo-search-appearance',
					'modified',
				),
				8824 => array(
					8824,
					WSAL_LOW,
					esc_html__( 'User set the option to show the Yoast SEO Meta Box for Posts/Pages in the Yoast SEO plugin settings', 'wp-security-audit-log' ),
					__( 'Changed the status of the option to show the <strong>Yoast SEO Meta Box</strong> for %SEOPostType%.', 'wp-security-audit-log' ),
					array(),
					array(),
					'yoast-seo-search-appearance',
					'enabled',
				),
				8825 => array(
					8825,
					WSAL_LOW,
					esc_html__( 'User Enabled/Disabled the advanced or schema settings for authors in the Yoast SEO plugin settings', 'wp-security-audit-log' ),
					__( 'Changed the setting <strong>Security: advanced or schema settings for authors</strong> in the plugin.', 'wp-security-audit-log' ),
					array(),
					array(),
					'yoast-seo',
					'enabled',
				),
				8826 => array(
					8826,
					WSAL_LOW,
					esc_html__( 'User Enabled/Disabled redirecting attachment URLs in the Yoast SEO plugin settings', 'wp-security-audit-log' ),
					__( 'Changed the status of the setting <strong>Redirect attachment URLs</strong> in the <strong>Media</strong> search appearance settings.', 'wp-security-audit-log' ),
					array(),
					array(),
					'yoast-seo',
					'enabled',
				),

				8827 => array(
					8827,
					WSAL_MEDIUM,
					esc_html__( 'User Enabled/Disabled Usage tracking in the Yoast SEO plugin settings', 'wp-security-audit-log' ),
					__( 'Changed the status of the <strong>Usage tracking</strong> plugin setting.', 'wp-security-audit-log' ),
					array(),
					array(),
					'yoast-seo',
					'enabled',
				),
				8828 => array(
					8828,
					WSAL_MEDIUM,
					esc_html__( 'User Enabled/Disabled REST API: Head endpoint in the Yoast SEO plugin settings', 'wp-security-audit-log' ),
					__( 'Changed the status of the <strong>REST API: Head endpoint</strong> plugin setting.', 'wp-security-audit-log' ),
					array(),
					array(),
					'yoast-seo',
					'enabled',
				),
				8829 => array(
					8829,
					WSAL_LOW,
					esc_html__( 'User Added/Removed a social profile in the Yoast SEO plugin settings', 'wp-security-audit-log' ),
					__( 'Changed the URL of an <strong>Organization social profile</strong>.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Social media profile', 'wp-security-audit-log' ) => '%social_profile%',
						esc_html__( 'Old URL', 'wp-security-audit-log' ) => '%old_url%',
						esc_html__( 'New URL', 'wp-security-audit-log' ) => '%new_url%',
					),
					array(),
					'yoast-seo',
					'added',
				),

				// Multisite.
				8838 => array(
					8838,
					WSAL_HIGH,
					esc_html__( 'User changed who should have access to the setting on Network Level', 'wp-security-audit-log' ),
					__( 'Changed the setting <strong>Who should have access to the Yoast SEO settings</strong> on a multisite network to %new%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous setting', 'wp-security-audit-log' ) => '%old%',
					),
					array(),
					'yoast-seo',
					'enabled',
				),
				8839 => array(
					8839,
					WSAL_LOW,
					esc_html__( 'New sites inherit their SEO options from site changed', 'wp-security-audit-log' ),
					__( 'Changed the setting <strong>New sites in the network inherit their SEO settings from this site</strong> to %new%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous setting', 'wp-security-audit-log' ) => '%old%',
					),
					array(),
					'yoast-seo',
					'enabled',
				),
				8840 => array(
					8840,
					WSAL_MEDIUM,
					esc_html__( "Reset the site's SEO settings to default", 'wp-security-audit-log' ),
					esc_html__( 'Reset the SEO settings of the site %old% to default.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Site ID', 'wp-security-audit-log' ) => '%old%',
					),
					array(),
					'yoast-seo',
					'enabled',
				),

				// Network features enabled / disabled.
				8842 => array(
					8842,
					WSAL_HIGH,
					esc_html__( 'Disabled a plugin feature networkwide', 'wp-security-audit-log' ),
					esc_html__( 'Disabled the plugin feature %feature_name% networkwide.', 'wp-security-audit-log' ),
					array(),
					array(),
					'yoast-seo',
					'disabled',
				),
				8843 => array(
					8843,
					WSAL_HIGH,
					esc_html__( 'Allowed site administrators to toggle a plugin feature on or off for their site', 'wp-security-audit-log' ),
					esc_html__( 'Allowed site administrators to toggle the plugin feature %feature_name% on or off on their sites.', 'wp-security-audit-log' ),
					array(),
					array(),
					'yoast-seo',
					'enabled',
				),

				8813 => array(
					8813,
					WSAL_MEDIUM,
					esc_html__( 'User Enabled/Disabled the option Show Posts/Pages in Search Results in the Yoast SEO plugin settings', 'wp-security-audit-log' ),
					esc_html__( 'Changed the status of the content type setting to show %SEOPostType% in search results.', 'wp-security-audit-log' ),
					array(),
					array(),
					'yoast-seo-search-appearance',
					'enabled',
				),
				8814 => array(
					8814,
					WSAL_INFORMATIONAL,
					esc_html__( 'User changed the Posts/Pages title template in the Yoast SEO plugin settings', 'wp-security-audit-log' ),
					esc_html__( 'Changed the %SEOPostType% SEO title template in the plugin settings to %new%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous template', 'wp-security-audit-log' ) => '%old%',
					),
					array(),
					'yoast-seo-search-appearance',
					'modified',
				),

				8830 => array(
					8830,
					WSAL_MEDIUM,
					esc_html__( 'User Enabled/Disabled the taxonomies to show in search results setting in the Yoast SEO plugin settings', 'wp-security-audit-log' ),
					esc_html__( 'Changed the status of the taxonomies setting to show %SEOPostType% in search results.', 'wp-security-audit-log' ),
					array(),
					array(),
					'yoast-seo-search-appearance',
					'enabled',
				),
				8831 => array(
					8831,
					WSAL_LOW,
					esc_html__( 'User Modified the SEO title template for a taxonomy in the Yoast SEO plugin settings', 'wp-security-audit-log' ),
					esc_html__( 'Changed the SEO title template for the taxonomy %SEOPostType% to %new%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous title', 'wp-security-audit-log' ) => '%old%',
					),
					array(),
					'yoast-seo-search-appearance',
					'modified',
				),
				8832 => array(
					8832,
					WSAL_LOW,
					esc_html__( 'User Modified the Meta description template for a taxonomy in the Yoast SEO plugin settings', 'wp-security-audit-log' ),
					esc_html__( 'Changed the Meta description template for the taxonomy %SEOPostType% to %new%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous description', 'wp-security-audit-log' ) => '%old%',
					),
					array(),
					'yoast-seo-search-appearance',
					'modified',
				),
				8833 => array(
					8833,
					WSAL_MEDIUM,
					esc_html__( 'User Enabled/Disabled Author or Data archives in Yoast SEO plugin settings', 'wp-security-audit-log' ),
					esc_html__( 'Changed the status of the %archive_type% archives in the plugin settings.' ),
					array(),
					array(),
					'yoast-seo-search-appearance',
					'enabled',
				),
				8834 => array(
					8834,
					WSAL_MEDIUM,
					esc_html__( 'User Enabled/Disabled showing Author or Date archives in search results in Yoast SEO plugin settings', 'wp-security-audit-log' ),
					esc_html__( 'Changed the status of the setting to show the %archive_type% archives in the search results.', 'wp-security-audit-log' ),
					array(),
					array(),
					'yoast-seo-search-appearance',
					'enabled',
				),
				8835 => array(
					8835,
					WSAL_LOW,
					esc_html__( 'User Modified the SEO title template for Author or Date archives in the Yoast SEO plugin settings', 'wp-security-audit-log' ),
					esc_html__( 'Changed the SEO title template for the %archive_type% archives to %new%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous title', 'wp-security-audit-log' ) => '%old%',
					),
					array(),
					'yoast-seo-search-appearance',
					'modified',
				),
				8836 => array(
					8836,
					WSAL_LOW,
					esc_html__( 'User Modified the SEO Meta description for Author or Date archives in the Yoast SEO plugin settings', 'wp-security-audit-log' ),
					esc_html__( 'Changed the Meta description template for the %archive_type% archives to %new%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous description', 'wp-security-audit-log' ) => '%old%',
					),
					array(),
					'yoast-seo-search-appearance',
					'modified',
				),
				8837 => array(
					8837,
					WSAL_LOW,
					esc_html__( 'User Enabled/Disabled the SEO meta box for a taxonomy', 'wp-security-audit-log' ),
					esc_html__( 'Changed the status of the setting to show SEO settings for the %SEOPostType% taxonomy.', 'wp-security-audit-log' ),
					array(),
					array(),
					'yoast-seo-search-appearance',
					'enabled',
				),
				8853 => array(
					8853,
					WSAL_MEDIUM,
					esc_html__( 'User the Default Page type in the Scheme settings', 'wp-security-audit-log' ),
					__( 'Changed the <strong>Default Page type</strong> in the Schema settings for <strong>%SEOPostType%</strong> to <strong>%new_type%.</strong>', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous Default Page type', 'wp-security-audit-log' ) => '%old_type%',
					),
					array(),
					'yoast-seo',
					'added',
				),
				8854 => array(
					8854,
					WSAL_MEDIUM,
					esc_html__( 'User the Default Article type in the Scheme settings', 'wp-security-audit-log' ),
					__( 'Changed the <strong>Default Article type</strong> in the Schema settings for <strong>%SEOPostType%</strong> to <strong>%new_type%.</strong>', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous Default Article type', 'wp-security-audit-log' ) => '%old_type%',
					),
					array(),
					'yoast-seo',
					'added',
				),
				8841 => array(
					8841,
					WSAL_MEDIUM,
					esc_html__( 'User Added/Removed/Modified a Webmaster Tools verification code for a search engine', 'wp-security-audit-log' ),
					__( 'Changed the <strong>Webmaster Tools verification code</strong> for a search engine.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Search engine', 'wp-security-audit-log' ) => '%search_engine_type%',
						esc_html__( 'Previous code', 'wp-security-audit-log' ) => '%old%',
						esc_html__( 'New code', 'wp-security-audit-log' )      => '%new%',
					),
					array(),
					'yoast-seo',
					'added',
				),
				8844 => array(
					8844,
					WSAL_MEDIUM,
					esc_html__( 'Changed the status of the setting Add Open Graph meta data in the Facebook settings', 'wp-security-audit-log' ),
					__( 'Changed the status of the setting <strong>Add Open Graph meta data</strong> in the <strong>Facebook settings.</strong>', 'wp-security-audit-log' ),
					array(),
					array(),
					'yoast-seo',
					'disabled',
				),
				8845 => array(
					8845,
					WSAL_MEDIUM,
					esc_html__( 'Changed the Default Image in the Facebook settings', 'wp-security-audit-log' ),
					__( 'Changed the <strong>Default Image</strong> in the Facebook settings to <strong>%image_name%</strong>.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Image path', 'wp-security-audit-log' )          => '%image_path%',
						esc_html__( 'Previous image', 'wp-security-audit-log' )      => '%old_image%',
						esc_html__( 'Previous image path', 'wp-security-audit-log' ) => '%old_path%',
					),
					array(),
					'yoast-seo',
					'modified',
				),
				8846 => array(
					8846,
					WSAL_MEDIUM,
					esc_html__( 'Changed the status of the setting Add Twitter card meta data in the Twitter settings', 'wp-security-audit-log' ),
					__( 'Changed the status of the setting <strong>Add Twitter card meta data</strong> in the <strong>Twitter settings.</strong>', 'wp-security-audit-log' ),
					array(),
					array(),
					'yoast-seo',
					'disabled',
				),
				8847 => array(
					8847,
					WSAL_MEDIUM,
					esc_html__( 'Changed the Default card type to use in Twitter settings', 'wp-security-audit-log' ),
					__( 'Changed the <strong>Default card type to use</strong> in Twitter settings to <strong>%new_setting%.</strong>', 'wp-security-audit-log' ),
					array(
						__( 'Previous setting', 'wp-security-audit-log' ) => '%old_setting%',
					),
					array(),
					'yoast-seo',
					'modified',
				),
				8848 => array(
					8848,
					WSAL_MEDIUM,
					esc_html__( 'Changed the Pinterest confirmation meta tag in the Pinterest settings', 'wp-security-audit-log' ),
					esc_html__( 'Changed the Pinterest confirmation meta tag in the Pinterest settings to %new_value%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous value', 'wp-security-audit-log' ) => '%old_value%',
					),
					array(),
					'yoast-seo',
					'modified',
				),
				8855 => array(
					8855,
					WSAL_MEDIUM,
					esc_html__( 'A new plain / regex redirect was added', 'wp-security-audit-log' ),
					__( 'A <strong>%redirect_code%</strong> %redirect_type% redirect for the old URL <strong>%old_url%.</strong>', 'wp-security-audit-log' ),
					array(
						esc_html__( 'New URL', 'wp-security-audit-log' ) => '%new_url%',
					),
					array(),
					'yoast-seo-redirects',
					'added',
				),
				8856 => array(
					8856,
					WSAL_MEDIUM,
					esc_html__( 'A plain / regex redirect was modified', 'wp-security-audit-log' ),
					__( 'A <strong>%redirect_type%</strong> redirect was modified.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous old URL', 'wp-security-audit-log' ) => '%old_url%',
						esc_html__( 'New old URL', 'wp-security-audit-log' ) => '%new_old_url%',
						esc_html__( 'Previous new URL', 'wp-security-audit-log' ) => '%old_new_url%',
						esc_html__( 'New URL', 'wp-security-audit-log' ) => '%new_new_url%',
						esc_html__( 'Previous redirect type', 'wp-security-audit-log' ) => '%old_redirect_code%',
						esc_html__( 'New redirect type', 'wp-security-audit-log' ) => '%new_redirect_code%',
					),
					array(),
					'yoast-seo-redirects',
					'modified',
				),
				8857 => array(
					8857,
					WSAL_MEDIUM,
					esc_html__( 'A plain / regex redirect was deleted', 'wp-security-audit-log' ),
					__( 'A <strong>%redirect_code%</strong> %redirect_type% redirect for the URL <strong>%old_url%</strong> was deleted.', 'wp-security-audit-log' ),
					array(),
					array(),
					'yoast-seo-redirects',
					'deleted',
				),
				8858 => array(
					8858,
					WSAL_MEDIUM,
					esc_html__( 'The Redirect method was modified', 'wp-security-audit-log' ),
					__( 'The <strong>Redirect method</strong> has been Changed to <strong>%new_method%.</strong>', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Old redirect method', 'wp-security-audit-log' ) => '%old_method%',
					),
					array(),
					'yoast-seo-redirects',
					'modified',
				),
			);
		}

		/**
		 * Returns the array with website changes alerts
		 *
		 * @return array
		 *
		 * @since 4.6.0
		 */
		private static function get_website_changes_array(): array {
			return array(
				8809 => array(
					8809,
					WSAL_INFORMATIONAL,
					esc_html__( 'User changed the Title Separator', 'wp-security-audit-log' ),
					__( 'Changed the <strong>Title separator</strong> in the plugin settings to %new%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous separator', 'wp-security-audit-log' ) => '%old%',
					),
					array(),
					'yoast-seo',
					'modified',
				),
				// 8810/8811 Are obsolete but remain for backwards compatibilty.
				8810 => array(
					8810,
					WSAL_MEDIUM,
					esc_html__( 'User changed the Homepage Title', 'wp-security-audit-log' ),
					__( 'Changed the <strong>Knowledge Graph & Schema.org</strong> in the plugin settings to %new%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous title', 'wp-security-audit-log' ) => '%old%',
					),
					array(),
					'yoast-seo',
					'modified',
				),
				8811 => array(
					8811,
					WSAL_MEDIUM,
					esc_html__( 'User changed the Homepage Meta description', 'wp-security-audit-log' ),
					esc_html__( 'Changed the homepage Meta description.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous description', 'wp-security-audit-log' ) => '%old%',
						esc_html__( 'New description', 'wp-security-audit-log' ) => '%new%',
					),
					array(),
					'yoast-seo',
					'modified',
				),
				8812 => array(
					8812,
					WSAL_INFORMATIONAL,
					esc_html__( 'User changed the Knowledge Graph & Schema.org', 'wp-security-audit-log' ),
					__( 'Changed the <strong>Knowledge Graph & Schema.org</strong> in the plugin settings to %new%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous setting', 'wp-security-audit-log' ) => '%old%',
					),
					array(),
					'yoast-seo',
					'modified',
				),
			);
		}

		/**
		 * Returns the array with post changes alerts
		 *
		 * @return array
		 *
		 * @since 4.6.0
		 */
		private static function get_post_changes_array(): array {
			return array(
				8801 => array(
					8801,
					WSAL_INFORMATIONAL,
					esc_html__( 'User changed title of a post', 'wp-security-audit-log' ),
					__( 'Changed the <strong>SEO title</strong> of the post %PostTitle% to %NewSEOTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' ) => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
						esc_html__( 'Previous title', 'wp-security-audit-log' ) => '%OldSEOTitle%',
					),
					array(
						esc_html__( 'View the post in editor', 'wp-security-audit-log' ) => '%EditorLinkPost%',
					),
					'yoast-seo-metabox',
					'modified',
				),
				8802 => array(
					8802,
					WSAL_INFORMATIONAL,
					esc_html__( 'User changed the meta description of a post', 'wp-security-audit-log' ),
					__( 'Changed the <strong>Meta description</strong> of the post %PostTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' ) => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
						esc_html__( 'Previous description', 'wp-security-audit-log' ) => '%old_desc%',
						esc_html__( 'New description', 'wp-security-audit-log' ) => '%new_desc%',
					),
					array(
						esc_html__( 'View the post in editor', 'wp-security-audit-log' ) => '%EditorLinkPost%',
					),
					'yoast-seo-metabox',
					'modified',
				),
				8803 => array(
					8803,
					WSAL_INFORMATIONAL,
					esc_html__( 'User changed setting to allow search engines to show post in search results of a post', 'wp-security-audit-log' ),
					__( 'Changed the setting <strong>Allow seach engines to show post in search results</strong> for the post %PostTitle% to %NewStatus%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' ) => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
						esc_html__( 'Previous setting', 'wp-security-audit-log' ) => '%OldStatus%',
					),
					array(
						esc_html__( 'View the post in editor', 'wp-security-audit-log' ) => '%EditorLinkPost%',
					),
					'yoast-seo-metabox',
					'modified',
				),
				8804 => array(
					8804,
					WSAL_INFORMATIONAL,
					esc_html__( 'User Enabled/Disabled the option for search engine to follow links of a post', 'wp-security-audit-log' ),
					__( 'Changed the status of the setting <strong>Search engines to follow links in post</strong> in the post %PostTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' ) => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
					),
					array(
						esc_html__( 'View the post in editor', 'wp-security-audit-log' ) => '%EditorLinkPost%',
					),
					'yoast-seo-metabox',
					'enabled',
				),
				8805 => array(
					8805,
					WSAL_LOW,
					esc_html__( 'User set the Meta robots advanced setting of a post', 'wp-security-audit-log' ),
					__( 'Changed the <strong>Meta robots advanced</strong> setting for the post %PostTitle% to %NewStatus%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' ) => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
						esc_html__( 'Previous setting', 'wp-security-audit-log' ) => '%OldStatus%',
					),
					array(
						esc_html__( 'View the post in editor', 'wp-security-audit-log' ) => '%EditorLinkPost%',
					),
					'yoast-seo-metabox',
					'modified',
				),
				8806 => array(
					8806,
					WSAL_INFORMATIONAL,
					esc_html__( 'User changed the canonical URL of a post', 'wp-security-audit-log' ),
					__( 'Changed the <strong>Canonical URL</strong> of the post %PostTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' ) => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
						esc_html__( 'Previous URL', 'wp-security-audit-log' ) => '%OldCanonicalUrl%',
						esc_html__( 'New URL', 'wp-security-audit-log' ) => '%NewCanonicalUrl%',
					),
					array(
						esc_html__( 'View the post in editor', 'wp-security-audit-log' ) => '%EditorLinkPost%',
					),
					'yoast-seo-metabox',
					'modified',
				),
				8807 => array(
					8807,
					WSAL_INFORMATIONAL,
					esc_html__( 'User changed the focus keyword of a post', 'wp-security-audit-log' ),
					__( 'Changed the <strong>focus keyword</strong> for the post %PostTitle% to %new_keywords%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' ) => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
						esc_html__( 'Previous keyword', 'wp-security-audit-log' ) => '%old_keywords%',
					),
					array(
						esc_html__( 'View the post in editor', 'wp-security-audit-log' ) => '%EditorLinkPost%',
					),
					'yoast-seo-metabox',
					'modified',
				),
				8808 => array(
					8808,
					WSAL_INFORMATIONAL,
					esc_html__( 'User Enabled/Disabled the option Cornerston Content of a post', 'wp-security-audit-log' ),
					__( 'Changed the setting <strong>Cornerstone content</strong> in the post %PostTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' ) => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
					),
					array(
						esc_html__( 'View the post in editor', 'wp-security-audit-log' ) => '%EditorLinkPost%',
					),
					'yoast-seo-metabox',
					'enabled',
				),
				8850 => array(
					8850,
					WSAL_INFORMATIONAL,
					esc_html__( 'User changed Breadcrumbs Title for a post', 'wp-security-audit-log' ),
					__( 'Changed the <strong>Breadcrumbs Title</strong> for the post %PostTitle% to %new_breadcrumb%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' ) => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
						esc_html__( 'Previous Breadcrumbs Title', 'wp-security-audit-log' ) => '%old_breadcrumb%',
					),
					array(
						esc_html__( 'View the post in editor', 'wp-security-audit-log' ) => '%EditorLinkPost%',
					),
					'yoast-seo-metabox',
					'modified',
				),
				8851 => array(
					8851,
					WSAL_INFORMATIONAL,
					esc_html__( 'User changed to the Schema settings of a post', 'wp-security-audit-log' ),
					__( 'Changed the <strong>Page type</strong> in the <strong>Schema</strong> settings to <strong>%new_type%</strong>', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' ) => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
						esc_html__( 'Previous Page type', 'wp-security-audit-log' ) => '%old_type%',
					),
					array(
						esc_html__( 'View the post in editor', 'wp-security-audit-log' ) => '%EditorLinkPost%',
					),
					'yoast-seo-metabox',
					'modified',
				),
				8852 => array(
					8852,
					WSAL_INFORMATIONAL,
					esc_html__( 'User changed to the Schema settings of a post', 'wp-security-audit-log' ),
					__( 'Changed the <strong>Article type</strong> in the <strong>Schema</strong> settings to <strong>%new_type%</strong>', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' ) => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
						esc_html__( 'Previous Article type', 'wp-security-audit-log' ) => '%old_type%',
					),
					array(
						esc_html__( 'View the post in editor', 'wp-security-audit-log' ) => '%EditorLinkPost%',
					),
					'yoast-seo-metabox',
					'modified',
				),
			);
		}
	}
}
