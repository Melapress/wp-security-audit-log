<?php
/**
 * Custom Alerts for Redirection plugin.
 *
 * Class file for alert manager.
 *
 * @since 5.1.0
 *
 * @package wsal
 * @subpackage wsal-redirection
 */

// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment
// phpcs:disable WordPress.WP.I18n.UnorderedPlaceholdersText

declare(strict_types=1);

namespace WSAL\WP_Sensors\Alerts;

use WSAL\MainWP\MainWP_Addon;
use WSAL\WP_Sensors\Helpers\Redirection_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\WP_Sensors\Alerts\Redirection_Custom_Alerts' ) ) {
	/**
	 * Custom sensor for Redirection plugin.
	 *
	 * @since 5.1.0
	 */
	class Redirection_Custom_Alerts {

		/**
		 * Returns the structure of the alerts for extension.
		 *
		 * @return array
		 *
		 * @since 5.1.0
		 */
		public static function get_custom_alerts(): array {
			// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment
			if ( Redirection_Helper::is_redirection_active() || MainWP_Addon::check_mainwp_plugin_active() ) {
				return array(
					__( 'Redirection', 'wp-security-audit-log' ) => array(
						__( 'Monitor redirection', 'wp-security-audit-log' ) =>
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
		 * @since 5.1.0
		 */
		public static function get_alerts_array(): array {
			return array(

				10501 => array(
					10501,
					WSAL_MEDIUM,
					__( 'A redirection was created', 'wp-security-audit-log' ),
					__( 'Created a new redirection with source URL %SourceURL%', 'wp-security-audit-log' ),

					array(
						__( 'Redirection ID:', 'wp-security-audit-log' ) => '%ID%',
						__( 'Target URL:', 'wp-security-audit-log' ) => '%TargetURL%',
						__( 'Group:', 'wp-security-audit-log' ) => '%GroupTitle%',
						__( 'Redirection status:', 'wp-security-audit-log' ) => '%Status%',
					),
					array(
						__( 'View Redirections list', 'wp-security-audit-log' ) => '%EditorLink%',
					),
					'redirection',
					'added',
				),

				10502 => array(
					10502,
					WSAL_MEDIUM,
					__( 'Activated redirection', 'wp-security-audit-log' ),
					__( 'Activated the redirection with source URL %SourceURL%', 'wp-security-audit-log' ),

					array(
						__( 'Redirection ID:', 'wp-security-audit-log' ) => '%ID%',
						__( 'Target URL:', 'wp-security-audit-log' ) => '%TargetURL%',
						__( 'Group:', 'wp-security-audit-log' ) => '%GroupTitle%',
						__( 'Redirection status:', 'wp-security-audit-log' ) => '%Status%',
					),
					array(
						__( 'View Redirections list', 'wp-security-audit-log' ) => '%EditorLink%',
					),
					'redirection',
					'activated',
				),

				10503 => array(
					10503,
					WSAL_MEDIUM,
					__( 'Deactivated redirection', 'wp-security-audit-log' ),
					__( 'Deactivated the redirection with source URL %SourceURL%', 'wp-security-audit-log' ),

					array(
						__( 'Redirection ID:', 'wp-security-audit-log' ) => '%ID%',
						__( 'Target URL:', 'wp-security-audit-log' ) => '%TargetURL%',
						__( 'Group:', 'wp-security-audit-log' ) => '%GroupTitle%',
						__( 'Redirection status:', 'wp-security-audit-log' ) => '%Status%',
					),
					array(
						__( 'View Redirections list', 'wp-security-audit-log' ) => '%EditorLink%',
					),
					'redirection',
					'deactivated',
				),

				10504 => array(
					10504,
					WSAL_MEDIUM,
					__( 'Reset redirection hits', 'wp-security-audit-log' ),
					__( 'Reset redirection hits with source URL %SourceURL%', 'wp-security-audit-log' ),

					array(
						__( 'Redirection ID:', 'wp-security-audit-log' ) => '%ID%',
						__( 'Target URL:', 'wp-security-audit-log' ) => '%TargetURL%',
						__( 'Group:', 'wp-security-audit-log' ) => '%GroupTitle%',
						__( 'Redirection status:', 'wp-security-audit-log' ) => '%Status%',
					),
					array(
						__( 'View Redirections list', 'wp-security-audit-log' ) => '%EditorLink%',
					),
					'redirection',
					'reset',
				),

				10505 => array(
					10505,
					WSAL_MEDIUM,
					__( 'Modified redirection', 'wp-security-audit-log' ),
					__( 'Modified redirection with source URL %SourceURL%', 'wp-security-audit-log' ),

					array(
						__( 'Redirection ID:', 'wp-security-audit-log' ) => '%ID%',
						__( 'Target URL:', 'wp-security-audit-log' ) => '%TargetURL%',
						__( 'Group:', 'wp-security-audit-log' ) => '%GroupTitle%',
						__( 'Redirection status:', 'wp-security-audit-log' ) => '%Status%',
					),
					array(
						__( 'View Redirections list', 'wp-security-audit-log' ) => '%EditorLink%',
					),
					'redirection',
					'modified',
				),

				10508 => array(
					10508,
					WSAL_MEDIUM,
					__( 'Deleted redirection', 'wp-security-audit-log' ),
					__( 'Deleted the redirection with source URL %SourceURL%', 'wp-security-audit-log' ),

					array(
						__( 'Redirection ID:', 'wp-security-audit-log' ) => '%ID%',
						__( 'Target URL:', 'wp-security-audit-log' ) => '%TargetURL%',
						__( 'Group:', 'wp-security-audit-log' ) => '%GroupTitle%',
						__( 'Redirection status was:', 'wp-security-audit-log' ) => '%Status%',
					),
					array(),
					'redirection',
					'deleted',
				),

				// Groups part - not supported yet.
				10509 => array(
					10509,
					WSAL_MEDIUM,
					__( 'Created a new Redirection Group', 'wp-security-audit-log' ),
					__( 'Created a new Redirection Group %GroupTitle%', 'wp-security-audit-log' ),

					array(
						__( 'Group ID:', 'wp-security-audit-log' ) => '%ID%',
						__( 'Module:', 'wp-security-audit-log' ) => '%ModuleTitle%',
						__( 'Redirection status:', 'wp-security-audit-log' ) => '%Status%',
					),
					array(
						__( 'View in Redirection Groups list', 'wp-security-audit-log' ) => '%EditorLink%',
					),
					'redirection',
					'created',
				),
			);
		}
	}
}
