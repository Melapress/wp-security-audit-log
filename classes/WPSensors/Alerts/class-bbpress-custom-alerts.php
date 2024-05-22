<?php
/**
 * Custom Alerts for bbPress plugin.
 *
 * Class file for alert manager.
 *
 * @since   1.0.0
 *
 * @package wsal
 * @subpackage wsal-bbpress-forms
 */

// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment
// phpcs:disable WordPress.WP.I18n.UnorderedPlaceholdersText

declare(strict_types=1);

namespace WSAL\WP_Sensors\Alerts;

use WSAL\MainWP\MainWP_Addon;
use WSAL\WP_Sensors\Helpers\BBPress_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\WP_Sensors\Alerts\BBPress_Custom_Alerts' ) ) {
	/**
	 * Custom sensor for BBpress plugin.
	 *
	 * @since 4.6.0
	 */
	class BBPress_Custom_Alerts {
		/**
		 * Returns the structure of the alerts for extension.
		 *
		 * @since 4.6.0
		 */
		public static function get_custom_alerts(): array {
			// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment
			if ( BBPress_Helper::is_bbpress_active() || MainWP_Addon::check_mainwp_plugin_active() ) {
				return array(
					__( 'bbPress Forums', 'wp-security-audit-log' ) => array(
						__( 'Forums', 'wp-security-audit-log' ) => self::get_forum_changes_array(),

						__( 'bbPress Forum Topics', 'wp-security-audit-log' ) => self::get_forum_topics_changes_array(),

						__( 'bbPress Settings', 'wp-security-audit-log' ) => self::get_settings_changes_array(),

						__( 'bbPress User Profiles', 'wp-security-audit-log' ) => self::get_user_profiles_changes_array(),
					),
				);
			}

			return array();
		}

		/**
		 * Returns array with all the events attached to the sensor (if there are different types of events, that method will merge them into one array - the events ids will be uses as keys).
		 *
		 * @since 4.6.0
		 */
		public static function get_alerts_array(): array {
			return self::get_forum_changes_array() +
			self::get_forum_topics_changes_array() +
			self::get_settings_changes_array() +
			self::get_user_profiles_changes_array();
		}

		/**
		 * Returns the array with plugin forum changes alerts.
		 *
		 * @since 4.6.0
		 */
		private static function get_forum_changes_array(): array {
			return array(
				array(
					8000,
					WSAL_INFORMATIONAL,
					__( 'User created new forum', 'wp-security-audit-log' ),
					__( 'Created a new forum called %ForumName%.', 'wp-security-audit-log' ),
					array(),
					array( __( 'View the forum in editor', 'wp-security-audit-log' ) => '%EditorLinkForum%' ),
					'bbpress-forum',
					'created',
				),
				array(
					8001,
					WSAL_MEDIUM,
					__( 'User changed status of a forum', 'wp-security-audit-log' ),
					__( 'Changed the status of the forum %ForumName% to %NewStatus%.', 'wp-security-audit-log' ),
					array(
						__( 'Previous status', 'wp-security-audit-log' ) => '%OldStatus%',
					),
					array(
						__( 'View the forum in editor', 'wp-security-audit-log' ) => '%EditorLinkForum%',
					),
					'bbpress-forum',
					'modified',
				),
				array(
					8002,
					WSAL_MEDIUM,
					__( 'User changed visibility of a forum', 'wp-security-audit-log' ),
					__( 'Changed the visibility of the forum %ForumName% to %NewVisibility%.', 'wp-security-audit-log' ),
					array(
						__( 'Previous visibility', 'wp-security-audit-log' ) => '%OldVisibility%',
					),
					array(
						__( 'View the forum in editor', 'wp-security-audit-log' ) => '%EditorLinkForum%',
					),
					'bbpress-forum',
					'modified',
				),
				array(
					8003,
					WSAL_LOW,
					__( 'User changed the URL of a forum', 'wp-security-audit-log' ),
					__( 'Changed the URL of the forum %ForumName%.', 'wp-security-audit-log' ),
					array(
						__( 'Previous URL', 'wp-security-audit-log' ) => '%OldUrl%',
						__( 'New URL', 'wp-security-audit-log' ) => '%NewUrl%',
					),
					array(
						__( 'View the forum in editor', 'wp-security-audit-log' ) => '%EditorLinkForum%',
					),
					'bbpress-forum',
					'modified',
				),
				array(
					8004,
					WSAL_INFORMATIONAL,
					__( 'User changed order of a forum', 'wp-security-audit-log' ),
					__( 'Changed the sorting order of the forum %ForumName% to %NewOrder%.', 'wp-security-audit-log' ),
					array(
						__( 'Previous sorting order', 'wp-security-audit-log' ) => '%OldOrder%',
					),
					array(
						__( 'View the forum in editor', 'wp-security-audit-log' ) => '%EditorLinkForum%',
					),
					'bbpress-forum',
					'modified',
				),
				array(
					8005,
					WSAL_HIGH,
					__( 'User moved forum to trash', 'wp-security-audit-log' ),
					__( 'Moved the forum %ForumName% to trash.', 'wp-security-audit-log' ),
					array(),
					array(),
					'bbpress-forum',
					'deleted',
				),
				array(
					8006,
					WSAL_HIGH,
					__( 'User permanently deleted forum', 'wp-security-audit-log' ),
					__( 'Permanently deleted the forum %ForumName%.', 'wp-security-audit-log' ),
					array(),
					array(),
					'bbpress-forum',
					'deleted',
				),
				array(
					8007,
					WSAL_HIGH,
					__( 'User restored forum from trash', 'wp-security-audit-log' ),
					__( 'Restored the forum %ForumName% from trash.', 'wp-security-audit-log' ),
					array(),
					array(),
					'bbpress-forum',
					'restored',
				),
				array(
					8008,
					WSAL_LOW,
					__( 'User changed the parent of a forum', 'wp-security-audit-log' ),
					__( 'Changed the parent of the forum %ForumName% to %NewParent%.', 'wp-security-audit-log' ),
					array(
						__( 'Previous parent', 'wp-security-audit-log' ) => '%OldParent%',
					),
					array(
						__( 'View the forum in editor', 'wp-security-audit-log' ) => '%EditorLinkForum%',
					),
					'bbpress-forum',
					'modified',
				),
				array(
					8011,
					WSAL_LOW,
					__( 'User changed type of a forum', 'wp-security-audit-log' ),
					__( 'Changed the type of the forum %ForumName% to %NewType%.', 'wp-security-audit-log' ),
					array(
						__( 'Previous type', 'wp-security-audit-log' ) => '%OldType%',
					),
					array(
						__( 'View the forum in editor', 'wp-security-audit-log' ) => '%EditorLinkForum%',
					),
					'bbpress-forum',
					'modified',
				),
			);
		}

		/**
		 * Returns the array with plugin forum topics changes alerts.
		 *
		 * @since 4.6.0
		 */
		private static function get_forum_topics_changes_array(): array {
			return array(
				array(
					8014,
					WSAL_INFORMATIONAL,
					__( 'User created new topic', 'wp-security-audit-log' ),
					__( 'Created a new topic called %TopicName%.', 'wp-security-audit-log' ),
					array(),
					array(
						__( 'View the topic in editor', 'wp-security-audit-log' ) => '%EditorLinkTopic%',
					),
					'bbpress-forum',
					'created',
				),
				array(
					8015,
					WSAL_INFORMATIONAL,
					__( 'User changed status of a topic', 'wp-security-audit-log' ),
					__( 'Changed the status of the topic %TopicName% to %NewStatus%.', 'wp-security-audit-log' ),
					array(
						__( 'Previous status', 'wp-security-audit-log' ) => '%OldStatus%',
					),
					array(
						__( 'View the topic in editor', 'wp-security-audit-log' ) => '%EditorLinkTopic%',
					),
					'bbpress-forum',
					'modified',
				),
				array(
					8016,
					WSAL_INFORMATIONAL,
					__( 'User changed type of a topic', 'wp-security-audit-log' ),
					__( 'Changed the type of the topic %TopicName% to %NewType%.', 'wp-security-audit-log' ),
					array(
						__( 'Previous type', 'wp-security-audit-log' ) => '%OldType%',
					),
					array(
						__( 'View the topic in editor', 'wp-security-audit-log' ) => '%EditorLinkTopic%',
					),
					'bbpress-forum',
					'modified',
				),
				array(
					8017,
					WSAL_INFORMATIONAL,
					__( 'User changed URL of a topic', 'wp-security-audit-log' ),
					__( 'Changed the URL of the topic %TopicName%.', 'wp-security-audit-log' ),
					array(
						__( 'Previous URL', 'wp-security-audit-log' ) => '%OldUrl%',
						__( 'New URL', 'wp-security-audit-log' ) => '%NewUrl%',
					),
					array(
						__( 'View the topic in editor', 'wp-security-audit-log' ) => '%EditorLinkTopic%',
					),
					'bbpress-forum',
					'modified',
				),
				array(
					8018,
					WSAL_INFORMATIONAL,
					__( 'User changed the forum of a topic', 'wp-security-audit-log' ),
					__( 'Changed the forum of the topic %TopicName% to %NewForum%.', 'wp-security-audit-log' ),
					array(
						__( 'Previous forum', 'wp-security-audit-log' ) => '%OldForum%',
					),
					array(
						__( 'View the topic in editor', 'wp-security-audit-log' ) => '%EditorLinkTopic%',
					),
					'bbpress-forum',
					'modified',
				),
				array(
					8019,
					WSAL_MEDIUM,
					__( 'User moved topic to trash', 'wp-security-audit-log' ),
					__( 'Moved the %TopicName% to trash.', 'wp-security-audit-log' ),
					array(),
					array(),
					'bbpress-forum',
					'deleted',
				),
				array(
					8020,
					WSAL_MEDIUM,
					__( 'User permanently deleted topic', 'wp-security-audit-log' ),
					__( 'Permanently deleted the topic %TopicName%.', 'wp-security-audit-log' ),
					array(),
					array(),
					'bbpress-forum',
					'deleted',
				),
				array(
					8021,
					WSAL_INFORMATIONAL,
					__( 'User restored topic from trash', 'wp-security-audit-log' ),
					__( 'Restored the topic %TopicName% from trash.', 'wp-security-audit-log' ),
					array(),
					array(),
					'bbpress-forum',
					'restored',
				),
				array(
					8022,
					WSAL_LOW,
					__( 'User changed visibility of a topic', 'wp-security-audit-log' ),
					__( 'Changed the visibility of the topic %TopicName% to %NewVisibility%.', 'wp-security-audit-log' ),
					array(
						__( 'Previous visibility', 'wp-security-audit-log' ) => '%OldVisibility%',
					),
					array(
						__( 'View the topic in editor', 'wp-security-audit-log' ) => '%EditorLinkTopic%',
					),
					'bbpress-forum',
					'modified',
				),
				array(
					8024,
					WSAL_LOW,
					__( 'User created a topic tag', 'wp-security-audit-log' ),
					__( 'Created the topic tag %TagName%.', 'wp-security-audit-log' ),
					array(
						__( 'Slug', 'wp-security-audit-log' ) => '%slug%',
					),
					array(
						__( 'View the tag in editor', 'wp-security-audit-log' ) => '%EditorLinkTag%',
					),
					'bbpress-forum',
					'created',
				),
				array(
					8025,
					WSAL_LOW,
					__( 'User deleted a topic tag', 'wp-security-audit-log' ),
					__( 'Deleted the topic tag %TagName%.', 'wp-security-audit-log' ),
					array(
						__( 'Slug', 'wp-security-audit-log' ) => '%slug%',
					),
					array(),
					'bbpress-forum',
					'deleted',
				),
				array(
					8026,
					WSAL_LOW,
					__( 'User renamed a topic tag', 'wp-security-audit-log' ),
					__( 'Renamed the topic tag %OldName% to %NewName%.', 'wp-security-audit-log' ),
					array(
						__( 'Slug', 'wp-security-audit-log' ) => '%slug%',
					),
					array(
						__( 'View the tag in editor', 'wp-security-audit-log' ) => '%EditorLinkTag%',
					),
					'bbpress-forum',
					'renamed',
				),
				array(
					8027,
					WSAL_LOW,
					__( 'User changed a topic tag slug', 'wp-security-audit-log' ),
					__( 'Changed the slug of the topic tag %TagName% to %NewSlug%.', 'wp-security-audit-log' ),
					array(
						__( 'Previous slug', 'wp-security-audit-log' ) => '%slug%',
					),
					array(
						__( 'View the tag in editor', 'wp-security-audit-log' ) => '%EditorLinkTag%',
					),
					'bbpress-forum',
					'renamed',
				),
			);
		}

		/**
		 * Returns the array with plugin settings changes alerts.
		 *
		 * @since 4.6.0
		 */
		private static function get_settings_changes_array(): array {
			return array(
				array(
					8009,
					WSAL_HIGH,
					__( 'User changed forum\'s role', 'wp-security-audit-log' ),
					__( 'Changed the bbPress setting <strong>Automatically give registered users a forum role</strong> to %NewRole%.', 'wp-security-audit-log' ),
					array(
						__( 'Previous role', 'wp-security-audit-log' ) => '%OldRole%',
					),
					array(),
					'bbpress',
					'modified',
				),
				array(
					8010,
					WSAL_CRITICAL,
					__( 'User changed option of a forum', 'wp-security-audit-log' ),
					__( 'Changed the bbPress setting <strong>Anonymous</strong> (allow guest users to post on the forums).', 'wp-security-audit-log' ),
					array(),
					array(),
					'bbpress',
					'enabled',
				),
				array(
					8012,
					WSAL_MEDIUM,
					__( 'User changed time to disallow post editing', 'wp-security-audit-log' ),
					__( 'Changed the time of the bbPress setting <strong>Editing</strong> (to allow users to edit their content after posting) to %NewTime%,', 'wp-security-audit-log' ),
					array(
						__( 'Previous time', 'wp-security-audit-log' ) => '%OldTime%',
					),
					array(),
					'bbpress',
					'modified',
				),
				array(
					8013,
					WSAL_HIGH,
					__( 'User changed the forum setting posting throttle time', 'wp-security-audit-log' ),
					__( 'Changed the time of the bbPress setting <strong>Flooding</strong> (throttling users setting) to %NewTime%.', 'wp-security-audit-log' ),
					array(
						__( 'Previous time', 'wp-security-audit-log' ) => '%OldTime%',
					),
					array(),
					'bbpress',
					'modified',
				),
			);
		}

		/**
		 * Returns the array with plugin settings changes alerts.
		 *
		 * @since 4.6.0
		 */
		private static function get_user_profiles_changes_array(): array {
			return array(
				array(
					8023,
					WSAL_LOW,
					__( 'The forum role of a user was changed by another WordPress user', 'wp-security-audit-log' ),
					__( 'Changed the role of user %TargetUsername% to %NewRole%.', 'wp-security-audit-log' ),
					array(
						__( 'Previous role', 'wp-security-audit-log' ) => '%OldRole%',
						__( 'First name', 'wp-security-audit-log' ) => '%FirstName%',
					),
					array(
						__( 'User profile page', 'wp-security-audit-log' ) => '%EditUserLink%',
					),
					'user',
					'modified',
				),
			);
		}
	}
}
