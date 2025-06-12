<?php
/**
 * Custom Alerts for Rank Math plugin.
 *
 * Class file for alert manager.
 *
 * @since 5.4.0
 *
 * @package wsal
 * @subpackage wsal-termly
 */

declare(strict_types=1);

namespace WSAL\WP_Sensors\Alerts;

use WSAL\MainWP\MainWP_Addon;
use WSAL\WP_Sensors\Helpers\Rank_Math_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\WP_Sensors\Alerts\Rank_Math_Custom_Alerts' ) ) {
	/**
	 * Custom sensor for Rank Math plugin.
	 *
	 * @since 5.4.0
	 */
	class Rank_Math_Custom_Alerts {

		/**
		 * Returns the structure of the alerts for extension.
		 *
		 * @return array
		 *
		 * @since 5.4.0
		 */
		public static function get_custom_alerts(): array {
			// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment
			if ( \method_exists( Rank_Math_Helper::class, 'load_alerts_for_sensor' ) && Rank_Math_Helper::load_alerts_for_sensor() || MainWP_Addon::check_mainwp_plugin_active() ) {
				return array(
					__( 'Rank Math', 'wp-security-audit-log' ) => array(
						__( 'Monitor Rank Math', 'wp-security-audit-log' ) =>
						self::get_alerts_array(),
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
		 * @since 5.4.0
		 */
		public static function get_alerts_array(): array {
			return array(

				10701 => array(
					10701,
					\WSAL_MEDIUM,
					__( 'Changed the status of module in the plugin settings.', 'wp-security-audit-log' ),
					__( 'Changed the status of the %ModuleName% module in the plugin settings.', 'wp-security-audit-log' ),
					array(),
					array(),
					'rank-math',
					'deactivated',
				),
				10702 => array(
					10702,
					WSAL_INFORMATIONAL,
					esc_html__( 'User Modified the SEO title the post', 'wp-security-audit-log' ),
					esc_html__( 'Changed the SEO title of the post %PostTitle% to %NewSEOTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' ) => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
						esc_html__( 'Previous title', 'wp-security-audit-log' ) => '%OldSEOTitle%',
					),
					array(
						esc_html__( 'View the post in editor', 'wp-security-audit-log' ) => '%EditorLinkPost%',
					),
					'rank-math-snippet-editor',
					'modified',
				),
				10703 => array(
					10703,
					WSAL_INFORMATIONAL,
					esc_html__( 'Changed the Meta description of a post.', 'wp-security-audit-log' ),
					esc_html__( 'Changed the Meta description of the post %PostTitle%.', 'wp-security-audit-log' ),
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
					'rank-math-snippet-editor',
					'modified',
				),
				10704 => array(
					10704,
					\WSAL_INFORMATIONAL,
					esc_html__( 'User Modified the SEO title the post', 'wp-security-audit-log' ),
					esc_html__( 'Changed the Focus keyword of the post %PostTitle% to %new_keywords%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' ) => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
						esc_html__( 'Previous Focus keyword:', 'wp-security-audit-log' ) => '%old_keywords%',
					),
					array(
						esc_html__( 'View the post in editor', 'wp-security-audit-log' ) => '%EditorLinkPost%',
					),
					'rank-math',
					'modified',
				),
				10705 => array(
					10705,
					\WSAL_INFORMATIONAL,
					esc_html__( 'Changed the status of the SEO setting Pillar Content of a post.', 'wp-security-audit-log' ),
					esc_html__( 'Changed the status of the SEO setting Pillar Content in the post %PostTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' ) => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
						esc_html__( 'Previous status', 'wp-security-audit-log' ) => '%old_status%',
					),
					array(
						esc_html__( 'View the post in editor', 'wp-security-audit-log' ) => '%EditorLinkPost%',
					),
					'rank-math',
					'disabled',
				),
				10706 => array(
					10706,
					\WSAL_INFORMATIONAL,
					esc_html__( 'Changed the status of the ROBOTS META Index setting of a post.', 'wp-security-audit-log' ),
					esc_html__( 'Changed the status of the ROBOTS META Index setting in the post %PostTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' ) => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
						esc_html__( 'Previous status', 'wp-security-audit-log' ) => '%old_status%',
					),
					array(
						esc_html__( 'View the post in editor', 'wp-security-audit-log' ) => '%EditorLinkPost%',
					),
					'rank-math-robots-meta',
					'disabled',
				),
				10707 => array(
					10707,
					\WSAL_INFORMATIONAL,
					esc_html__( 'Changed the status of the ROBOTS META Nofollow setting of a post.', 'wp-security-audit-log' ),
					esc_html__( 'Changed the status of the ROBOTS META Nofollow setting in the post %PostTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' ) => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
						esc_html__( 'Previous status', 'wp-security-audit-log' ) => '%old_status%',
					),
					array(
						esc_html__( 'View the post in editor', 'wp-security-audit-log' ) => '%EditorLinkPost%',
					),
					'rank-math-robots-meta',
					'disabled',
				),
				10708 => array(
					10708,
					\WSAL_INFORMATIONAL,
					esc_html__( 'Changed the status of the ROBOTS META No Archive setting of a post.', 'wp-security-audit-log' ),
					esc_html__( 'Changed the status of the ROBOTS META No Archive setting in the post %PostTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' ) => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
						esc_html__( 'Previous status', 'wp-security-audit-log' ) => '%old_status%',
					),
					array(
						esc_html__( 'View the post in editor', 'wp-security-audit-log' ) => '%EditorLinkPost%',
					),
					'rank-math-robots-meta',
					'disabled',
				),
				10709 => array(
					10709,
					\WSAL_INFORMATIONAL,
					esc_html__( 'Changed the status of the ROBOTS META No Image Index setting of a post.', 'wp-security-audit-log' ),
					esc_html__( 'Changed the status of the ROBOTS META No Image Index setting in the post %PostTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' ) => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
						esc_html__( 'Previous status', 'wp-security-audit-log' ) => '%old_status%',
					),
					array(
						esc_html__( 'View the post in editor', 'wp-security-audit-log' ) => '%EditorLinkPost%',
					),
					'rank-math-robots-meta',
					'disabled',
				),
				10710 => array(
					10710,
					\WSAL_INFORMATIONAL,
					esc_html__( 'Changed the status of the ROBOTS META No Snippet setting of a post.', 'wp-security-audit-log' ),
					esc_html__( 'Changed the status of the ROBOTS META No Snippet setting in the post %PostTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' ) => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
						esc_html__( 'Previous status', 'wp-security-audit-log' ) => '%old_status%',
					),
					array(
						esc_html__( 'View the post in editor', 'wp-security-audit-log' ) => '%EditorLinkPost%',
					),
					'rank-math-robots-meta',
					'disabled',
				),
				10711 => array(
					10711,
					\WSAL_INFORMATIONAL,
					esc_html__( 'Changed the status of the ADVANCED ROBOTS META Max Snippet setting of a post.', 'wp-security-audit-log' ),
					esc_html__( 'Changed the status of the ADVANCED ROBOTS META Max Snippet setting in the post %PostTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' ) => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
						esc_html__( 'Previous status', 'wp-security-audit-log' ) => '%old_status%',
					),
					array(
						esc_html__( 'View the post in editor', 'wp-security-audit-log' ) => '%EditorLinkPost%',
					),
					'rank-math-robots-meta',
					'disabled',
				),
				10712 => array(
					10712,
					\WSAL_INFORMATIONAL,
					esc_html__( 'Changed the status of the ADVANCED ROBOTS META Max Image Preview setting of a post .', 'wp-security-audit-log' ),
					esc_html__( 'Changed the status of the ADVANCED ROBOTS META Max Image Preview setting in the post %PostTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' ) => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
						esc_html__( 'Previous status', 'wp-security-audit-log' ) => '%old_status%',
					),
					array(
						esc_html__( 'View the post in editor', 'wp-security-audit-log' ) => '%EditorLinkPost%',
					),
					'rank-math-robots-meta',
					'disabled',
				),
				10713 => array(
					10713,
					\WSAL_INFORMATIONAL,
					esc_html__( 'Changed the status of the ADVANCED ROBOTS META Max Image Preview setting of a post.', 'wp-security-audit-log' ),
					esc_html__( 'Changed the status of the ADVANCED ROBOTS META Max Image Preview setting in the post %PostTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' ) => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
						esc_html__( 'Previous status', 'wp-security-audit-log' ) => '%old_status%',
					),
					array(
						esc_html__( 'View the post in editor', 'wp-security-audit-log' ) => '%EditorLinkPost%',
					),
					'rank-math-robots-meta',
					'disabled',
				),
				10714 => array(
					10714,
					\WSAL_MEDIUM,
					__( 'Changed the Canonical URL of a post.', 'wp-security-audit-log' ),
					__( 'Changed the Canonical URL of the post %PostTitle%.', 'wp-security-audit-log' ),
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
					'rank-math-robots-meta',
					'modified',
				),
			);
		}
	}
}
