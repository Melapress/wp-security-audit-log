<?php
/**
 * Custom Alerts for Table Press plugin.
 *
 * Class file for alert manager.
 *
 * @since   1.0.0
 *
 * @package wsal
 * @subpackage wsal-gravity-forms
 */

// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment
// phpcs:disable WordPress.WP.I18n.UnorderedPlaceholdersText

declare(strict_types=1);

namespace WSAL\WP_Sensors\Alerts;

use WSAL\MainWP\MainWP_Addon;
use WSAL\WP_Sensors\Helpers\TablePress_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\WP_Sensors\Alerts\Tablepress_Custom_Alerts' ) ) {
	/**
	 * Custom sensor for Gravity Forms plugin.
	 *
	 * @since 4.6.0
	 */
	class Tablepress_Custom_Alerts {

		/**
		 * Returns the structure of the alerts for extension.
		 *
		 * @return array
		 *
		 * @since 4.6.0
		 */
		public static function get_custom_alerts(): array {
			// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment
			if ( TablePress_Helper::is_tablepress_active() || MainWP_Addon::check_mainwp_plugin_active() ) {
				return array(
					__( 'TablePress', 'wp-security-audit-log' ) => array(
						__( 'Monitor TablePress', 'wp-security-audit-log' ) =>
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
		 * @since 4.6.0
		 */
		public static function get_alerts_array(): array {
			return array(

				8900 => array(
					8900,
					WSAL_MEDIUM,
					__( 'A table was created', 'wp-security-audit-log' ),
					__( 'Added the new table %table_name%.', 'wp-security-audit-log' ),

					array(
						__( 'Table ID', 'wp-security-audit-log' ) => '%table_id%',
						__( 'Number of rows', 'wp-security-audit-log' ) => '%rows%',
						__( 'Number of columns', 'wp-security-audit-log' ) => '%columns%',
					),
					array(
						__( 'View in the editor', 'wp-security-audit-log' ) => '%EditorLink%',
					),
					'tablepress_tables',
					'added',
				),

				8901 => array(
					8901,
					WSAL_HIGH,
					__( 'A table was deleted', 'wp-security-audit-log' ),
					__( 'Deleted the table %table_name%.', 'wp-security-audit-log' ),

					array(
						__( 'Table ID', 'wp-security-audit-log' ) => '%table_id%',
					),
					array(),
					'tablepress_tables',
					'deleted',
				),

				8902 => array(
					8902,
					WSAL_MEDIUM,
					__( 'A table was duplicated', 'wp-security-audit-log' ),
					__( 'Created a copy of the table %table_name%.', 'wp-security-audit-log' ),

					array(
						__( 'New table name', 'wp-security-audit-log' ) => '%new_table_name%',
						__( 'New table ID', 'wp-security-audit-log' ) => '%table_id%',
					),
					array(
						__( 'View in the editor', 'wp-security-audit-log' ) => '%EditorLink%',
					),
					'tablepress_tables',
					'added',
				),

				8903 => array(
					8903,
					WSAL_MEDIUM,
					__( 'A table was imported', 'wp-security-audit-log' ),
					__( 'Imported the table %table_name%.', 'wp-security-audit-log' ),

					array(
						__( 'Table ID', 'wp-security-audit-log' ) => '%table_id%',
					),
					array(
						__( 'View in the editor', 'wp-security-audit-log' ) => '%EditorLink%',
					),
					'tablepress_tables',
					'imported',
				),

				8904 => array(
					8904,
					WSAL_MEDIUM,
					__( 'A table ID was changed', 'wp-security-audit-log' ),
					__( 'Changed the ID of the table %table_name%.', 'wp-security-audit-log' ),

					array(
						__( 'Previous table ID', 'wp-security-audit-log' ) => '%old_table_id%',
						__( 'New table ID', 'wp-security-audit-log' ) => '%table_id%',
					),
					array(
						__( 'View in the editor', 'wp-security-audit-log' ) => '%EditorLink%',
					),
					'tablepress_tables',
					'updated',
				),

				8905 => array(
					8905,
					WSAL_MEDIUM,
					__( 'A table was modified', 'wp-security-audit-log' ),
					__( 'Made changes to the table %table_name%', 'wp-security-audit-log' ),

					array(
						__( 'Table ID', 'wp-security-audit-log' ) => '%table_id%',
						__( 'Number of rows', 'wp-security-audit-log' ) => '%rows%',
						__( 'Number of columns', 'wp-security-audit-log' ) => '%columns%',
						__( 'Previous number of rows', 'wp-security-audit-log' ) => '%old_rows%',
						__( 'Previous number of columns', 'wp-security-audit-log' ) => '%old_columns%',
					),
					array(
						__( 'View in the editor', 'wp-security-audit-log' ) => '%EditorLink%',
					),
					'tablepress_tables',
					'updated',
				),

				8906 => array(
					8906,
					WSAL_MEDIUM,
					__( 'A table row was added or removed', 'wp-security-audit-log' ),
					__( 'A row was added or removed from the table %table_name%', 'wp-security-audit-log' ),

					array(
						__( 'Table ID', 'wp-security-audit-log' ) => '%table_id%',
						__( 'Previous row count', 'wp-security-audit-log' ) => '%old_count%',
						__( 'New row count', 'wp-security-audit-log' ) => '%count%',
					),
					array(
						__( 'View in the editor', 'wp-security-audit-log' ) => '%EditorLink%',
					),
					'tablepress_tables',
					'added',
				),

				8907 => array(
					8907,
					WSAL_MEDIUM,
					__( 'A table column was added or removed', 'wp-security-audit-log' ),
					__( 'A column was added or removed from the table %table_name%', 'wp-security-audit-log' ),

					array(
						__( 'Table ID', 'wp-security-audit-log' ) => '%table_id%',
						__( 'Previous column count', 'wp-security-audit-log' ) => '%old_count%',
						__( 'New column count', 'wp-security-audit-log' ) => '%count%',
					),
					array(
						__( 'View in the editor', 'wp-security-audit-log' ) => '%EditorLink%',
					),
					'tablepress_tables',
					'added',
				),

				8908 => array(
					8908,
					WSAL_MEDIUM,
					__( 'A table option was modified', 'wp-security-audit-log' ),
					__( 'Changed the status of the table option %option_name% in %table_name%', 'wp-security-audit-log' ),

					array(
						__( 'Table ID', 'wp-security-audit-log' ) => '%table_id%',
						__( 'Previous value', 'wp-security-audit-log' ) => '%old_value%',
						__( 'New value', 'wp-security-audit-log' ) => '%new_value%',
					),
					array(
						__( 'View in the editor', 'wp-security-audit-log' ) => '%EditorLink%',
					),
					'tablepress_tables',
					'modified',
				),

			);
		}
	}
}
