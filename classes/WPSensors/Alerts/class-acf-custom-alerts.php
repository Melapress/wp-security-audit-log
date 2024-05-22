<?php
/**
 * Custom Alerts for Table Press plugin.
 *
 * Class file for alert manager.
 *
 * @since   5.0.0
 *
 * @package wsal
 */

// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment
// phpcs:disable WordPress.WP.I18n.UnorderedPlaceholdersText

declare(strict_types=1);

namespace WSAL\WP_Sensors\Alerts;

use WSAL\MainWP\MainWP_Addon;
use WSAL\WP_Sensors\Helpers\ACF_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\WP_Sensors\Alerts\ACF_Custom_Alerts' ) ) {
	/**
	 * Custom sensor for Gravity Forms plugin.
	 *
	 * @since 5.0.0
	 */
	class ACF_Custom_Alerts {

		/**
		 * Returns the structure of the alerts for extension.
		 *
		 * @return array
		 *
		 * @since 5.0.0
		 */
		public static function get_custom_alerts(): array {
			// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment
			if ( ACF_Helper::is_acf_active() || MainWP_Addon::check_mainwp_plugin_active() ) {
				return array(
					__( 'Advanced Custom Fields', 'wp-security-audit-log' ) => array(
						__( 'Advanced Custom Fields', 'wp-security-audit-log' ) =>
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
		 * @since 5.0.0
		 */
		public static function get_alerts_array(): array {
			return array(

				10000 => array(
					10000,
					WSAL_INFORMATIONAL,
					__( 'A post type was created', 'wp-security-audit-log' ),
					__( 'Created the Post Type %PostTypeTitle%.', 'wp-security-audit-log' ),
					array(
						__( 'Post Type ID', 'wp-security-audit-log' ) => '%PostID%',
						__( 'Post Type status', 'wp-security-audit-log' ) => '%PostStatus%',
					),
					array(
						__( 'View in the editor', 'wp-security-audit-log' ) => '%EditorLink%',
					),
					'acf-config-post-types',
					'created',
				),
				10001 => array(
					10001,
					WSAL_LOW,
					__( 'A post type was activated / deactivated', 'wp-security-audit-log' ),
					__( 'Changed the status of the Post Type %PostTypeTitle%.', 'wp-security-audit-log' ),
					array(
						__( 'Post Type ID', 'wp-security-audit-log' ) => '%PostID%',
					),
					array(
						__( 'View in the editor', 'wp-security-audit-log' ) => '%EditorLink%',
					),
					'acf-config-post-types',
					'activated',
				),
				10002 => array(
					10002,
					WSAL_LOW,
					__( 'A post type was renamed', 'wp-security-audit-log' ),
					__( 'Renamed the Post Type %OldPostTypeTitle% to %PostTypeTitle%.', 'wp-security-audit-log' ),
					array(
						__( 'Post Type ID', 'wp-security-audit-log' ) => '%PostID%',
						__( 'Post Type status', 'wp-security-audit-log' ) => '%PostStatus%',
					),
					array(
						__( 'View in the editor', 'wp-security-audit-log' ) => '%EditorLink%',
					),
					'acf-config-post-types',
					'renamed',
				),
				10003 => array(
					10003,
					WSAL_LOW,
					__( 'A post types singular name was renamed', 'wp-security-audit-log' ),
					__( 'Changed the singular label of Post Type %PostTypeTitle%.', 'wp-security-audit-log' ),
					array(
						__( 'Post Type ID', 'wp-security-audit-log' ) => '%PostID%',
						__( 'Post Type status', 'wp-security-audit-log' ) => '%PostStatus%',
						__( 'Previous singular Label', 'wp-security-audit-log' ) => '%old_label%',
						__( 'New singular Label', 'wp-security-audit-log' ) => '%new_label%',
					),
					array(
						__( 'View in the editor', 'wp-security-audit-log' ) => '%EditorLink%',
					),
					'acf-config-post-types',
					'modified',
				),
				10004 => array(
					10004,
					WSAL_LOW,
					__( 'A post type key was modified', 'wp-security-audit-log' ),
					__( 'Changed the Post Type Key of the Post Type %PostTypeTitle%.', 'wp-security-audit-log' ),
					array(
						__( 'Post Type ID', 'wp-security-audit-log' ) => '%PostID%',
						__( 'Post Type status', 'wp-security-audit-log' ) => '%PostStatus%',
						__( 'Previous Post Type Key', 'wp-security-audit-log' ) => '%old_key%',
						__( 'New Post Type Key', 'wp-security-audit-log' ) => '%new_key%',
					),
					array(
						__( 'View in the editor', 'wp-security-audit-log' ) => '%EditorLink%',
					),
					'acf-config-post-types',
					'modified',
				),
				10005 => array(
					10005,
					WSAL_MEDIUM,
					__( 'A post types Taxonomies was modified', 'wp-security-audit-log' ),
					__( 'Modified the list of Taxonomies of the Post Type %PostTypeTitle%.', 'wp-security-audit-log' ),
					array(
						__( 'Post Type ID', 'wp-security-audit-log' ) => '%PostID%',
						__( 'Post Type status', 'wp-security-audit-log' ) => '%PostStatus',
						__( 'Previous Taxonomies', 'wp-security-audit-log' ) => '%old_tax%',
						__( 'New Taxonomies', 'wp-security-audit-log' ) => '%new_tax%',
					),
					array(
						__( 'View in the editor', 'wp-security-audit-log' ) => '%EditorLink%',
					),
					'acf-config-post-types',
					'modified',
				),
				10007 => array(
					10007,
					WSAL_MEDIUM,
					__( 'A post type was moved to trash', 'wp-security-audit-log' ),
					__( 'Moved the Post Type %PostTypeTitle% to trash.', 'wp-security-audit-log' ),
					array(
						__( 'Post Type ID', 'wp-security-audit-log' ) => '%PostID%',
						__( 'Post Type status', 'wp-security-audit-log' ) => '%PostStatus%',
					),
					array(),
					'acf-config-post-types',
					'deleted',
				),
				10008 => array(
					10008,
					WSAL_MEDIUM,
					__( 'A post type was restored from trash', 'wp-security-audit-log' ),
					__( 'Restored the Post Type %PostTypeTitle% from trash.', 'wp-security-audit-log' ),
					array(
						__( 'Post Type ID', 'wp-security-audit-log' ) => '%PostID%',
						__( 'Post Type status', 'wp-security-audit-log' ) => '%PostStatus%',
						__( 'URL', 'wp-security-audit-log' ) => '%PostUrl%',
					),
					array(
						__( 'View in the editor', 'wp-security-audit-log' ) => '%EditorLink%',
					),
					'acf-config-post-types',
					'restored',
				),
				10009 => array(
					10009,
					WSAL_MEDIUM,
					__( 'A post type was deleted', 'wp-security-audit-log' ),
					__( 'Permanently deleted the Post Type %PostTypeTitle%.', 'wp-security-audit-log' ),
					array(
						__( 'Post Type ID', 'wp-security-audit-log' ) => '%PostID%',
						__( 'Post Type status', 'wp-security-audit-log' ) => '%PostStatus%',
					),
					array(),
					'acf-config-post-types',
					'deleted',
				),

				10010 => array(
					10010,
					WSAL_INFORMATIONAL,
					__( 'A Taxonomy was created', 'wp-security-audit-log' ),
					__( 'Created the Taxonomy %PostTypeTitle%.', 'wp-security-audit-log' ),
					array(
						__( 'Taxonomy ID', 'wp-security-audit-log' ) => '%PostID%',
						__( 'Taxonomy status', 'wp-security-audit-log' ) => '%PostStatus%',
					),
					array(
						__( 'View in the editor', 'wp-security-audit-log' ) => '%EditorLink%',
					),
					'acf-config-taxonomies',
					'created',
				),
				10011 => array(
					10011,
					WSAL_LOW,
					__( 'A Taxonomy was activated / deactivated', 'wp-security-audit-log' ),
					__( 'Changed the status of the Taxonomy %PostTypeTitle%.', 'wp-security-audit-log' ),
					array(
						__( 'Taxonomy ID', 'wp-security-audit-log' ) => '%PostID%',
					),
					array(
						__( 'View in the editor', 'wp-security-audit-log' ) => '%EditorLink%',
					),
					'acf-config-taxonomies',
					'activated',
				),
				10012 => array(
					10012,
					WSAL_LOW,
					__( 'A Taxonomy was renamed', 'wp-security-audit-log' ),
					__( 'Renamed the Taxonomy %OldPostTypeTitle% to %PostTypeTitle%.', 'wp-security-audit-log' ),
					array(
						__( 'Taxonomy ID', 'wp-security-audit-log' ) => '%PostID%',
						__( 'Taxonomy status', 'wp-security-audit-log' ) => '%PostStatus%',
					),
					array(
						__( 'View in the editor', 'wp-security-audit-log' ) => '%EditorLink%',
					),
					'acf-config-taxonomies',
					'renamed',
				),
				10013 => array(
					10013,
					WSAL_LOW,
					__( 'A Taxonomy singular label was renamed', 'wp-security-audit-log' ),
					__( 'Changed the singular label of Taxonomy %PostTypeTitle%.', 'wp-security-audit-log' ),
					array(
						__( 'Taxonomy ID', 'wp-security-audit-log' ) => '%PostID%',
						__( 'Taxonomy status', 'wp-security-audit-log' ) => '%PostStatus%',
						__( 'Previous singular label', 'wp-security-audit-log' ) => '%old_label%',
						__( 'New singular label', 'wp-security-audit-log' ) => '%new_label%',
					),
					array(
						__( 'View in the editor', 'wp-security-audit-log' ) => '%EditorLink%',
					),
					'acf-config-taxonomies',
					'modified',
				),
				10014 => array(
					10014,
					WSAL_LOW,
					__( 'A Taxonomy key was modified', 'wp-security-audit-log' ),
					__( 'Changed the Taxonomy Key of the Taxonomy %PostTypeTitle%.', 'wp-security-audit-log' ),
					array(
						__( 'Taxonomy ID', 'wp-security-audit-log' ) => '%PostID%',
						__( 'Taxonomy status', 'wp-security-audit-log' ) => '%PostStatus%',
						__( 'Previous Taxonomy Key', 'wp-security-audit-log' ) => '%old_key%',
						__( 'New Taxonomy Key', 'wp-security-audit-log' ) => '%new_key%',
					),
					array(
						__( 'View in the editor', 'wp-security-audit-log' ) => '%EditorLink%',
					),
					'acf-config-taxonomies',
					'modified',
				),
				10015 => array(
					10015,
					WSAL_MEDIUM,
					__( 'A Taxonomies post type was modified', 'wp-security-audit-log' ),
					__( 'Modified the list of Post Types of the Taxonomy %PostTypeTitle%.', 'wp-security-audit-log' ),
					array(
						__( 'Taxonomy ID', 'wp-security-audit-log' ) => '%PostID%',
						__( 'Taxonomy status', 'wp-security-audit-log' ) => '%PostStatus%',
						__( 'Previous Post Types', 'wp-security-audit-log' ) => '%old_tax%',
						__( 'New Post Types', 'wp-security-audit-log' ) => '%new_tax%',
					),
					array(
						__( 'View in the editor', 'wp-security-audit-log' ) => '%EditorLink%',
					),
					'acf-config-taxonomies',
					'modified',
				),
				10017 => array(
					10017,
					WSAL_MEDIUM,
					__( 'A Taxonomy was moved to trash', 'wp-security-audit-log' ),
					__( 'Moved the Taxonomy %PostTypeTitle% to trash.', 'wp-security-audit-log' ),
					array(
						__( 'Taxonomy ID', 'wp-security-audit-log' ) => '%PostID%',
						__( 'Taxonomy status', 'wp-security-audit-log' ) => '%PostStatus%',
					),
					array(),
					'acf-config-taxonomies',
					'deleted',
				),
				10018 => array(
					10018,
					WSAL_MEDIUM,
					__( 'A Taxonomy was restored from trash', 'wp-security-audit-log' ),
					__( 'Restored the Taxonomy %PostTypeTitle% from trash.', 'wp-security-audit-log' ),
					array(
						__( 'Taxonomy ID', 'wp-security-audit-log' ) => '%PostID%',
						__( 'Taxonomy status', 'wp-security-audit-log' ) => '%PostStatus%',
						__( 'URL', 'wp-security-audit-log' ) => '%PostUrl%',
					),
					array(
						__( 'View in the editor', 'wp-security-audit-log' ) => '%EditorLink%',
					),
					'acf-config-taxonomies',
					'restored',
				),
				10019 => array(
					10019,
					WSAL_MEDIUM,
					__( 'A Taxonomy was deleted', 'wp-security-audit-log' ),
					__( 'Permanently deleted the Taxonomy %PostTypeTitle%.', 'wp-security-audit-log' ),
					array(
						__( 'Taxonomy ID', 'wp-security-audit-log' ) => '%PostID%',
						__( 'Taxonomy status', 'wp-security-audit-log' ) => '%PostStatus%',
					),
					array(),
					'acf-config-taxonomies',
					'deleted',
				),

				10020 => array(
					10020,
					WSAL_INFORMATIONAL,
					__( 'A Taxonomy term was created', 'wp-security-audit-log' ),
					__( 'Created the Taxonomy Term %TaxonomyTerm%.', 'wp-security-audit-log' ),
					array(
						__( 'Slug', 'wp-security-audit-log' ) => '%slug%',
					),
					array(
						__( 'View in the editor', 'wp-security-audit-log' ) => '%EditorLink%',
					),
					'acf-config-terms',
					'created',
				),
				10021 => array(
					10021,
					WSAL_LOW,
					__( 'A Taxonomy term was renamed', 'wp-security-audit-log' ),
					__( 'Renamed the Taxonomy Term %old_name% to %new_name%.', 'wp-security-audit-log' ),
					array(
						__( 'Slug', 'wp-security-audit-log' ) => '%slug%',
					),
					array(
						__( 'View in the editor', 'wp-security-audit-log' ) => '%EditorLink%',
					),
					'acf-config-terms',
					'renamed',
				),
				10022 => array(
					10022,
					WSAL_MEDIUM,
					__( 'A Taxonomy term was deleted', 'wp-security-audit-log' ),
					__( 'Deleted the Taxonomy Term %TermName%.', 'wp-security-audit-log' ),
					array(),
					array(),
					'acf-config-terms',
					'deleted',
				),
				10023 => array(
					10023,
					WSAL_MEDIUM,
					__( 'A Taxonomy terms slug was modified', 'wp-security-audit-log' ),
					__( 'Changed the slug of the Taxonomy Term %TaxonomyTerm% to %new_slug%.', 'wp-security-audit-log' ),
					array(
						__( 'Previous slug', 'wp-security-audit-log' ) => '%old_slug%',
					),
					array(
						__( 'View in the editor', 'wp-security-audit-log' ) => '%EditorLink%',
					),
					'acf-config-terms',
					'modified',
				),
				10024 => array(
					10024,
					WSAL_LOW,
					__( 'A posts taxonomy terms were modified', 'wp-security-audit-log' ),
					__( 'Changed the Taxonomy Term(s) of the post %PostTitle%', 'wp-security-audit-log' ),
					array(
						__( 'Post ID', 'wp-security-audit-log' ) => '%PostID%',
						__( 'Previous Taxonomy Term(s)', 'wp-security-audit-log' ) => '%old_terms%',
						__( 'New Taxonomy Term(s)', 'wp-security-audit-log' ) => '%new_terms%',
					),
					array(
						__( 'View in the editor', 'wp-security-audit-log' ) => '%EditorLink%',
					),
					'acf-config-terms',
					'modified',
				),
			);
		}
	}
}
