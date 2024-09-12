<?php
/**
 * Custom Alerts for Gravity Forms plugin.
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
use WSAL\WP_Sensors\Helpers\GravityForms_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\WP_Sensors\Alerts\Gravity_Forms_Custom_Alerts' ) ) {
	/**
	 * Custom sensor for Gravity Forms plugin.
	 *
	 * @since 4.6.0
	 */
	class Gravity_Forms_Custom_Alerts {

		/**
		 * Returns the structure of the alerts for extension.
		 *
		 * @return array
		 *
		 * @since 4.6.0
		 */
		public static function get_custom_alerts(): array {
			// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment
			if ( GravityForms_Helper::is_gravityforms_active() || MainWP_Addon::check_mainwp_plugin_active() ) {
				return array(
					esc_html__( 'Gravity Forms', 'wp-security-audit-log' ) => array(
						esc_html__( 'Monitor Gravity Forms', 'wp-security-audit-log' ) =>
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

				5700 => array(
					5700,
					WSAL_LOW,
					esc_html__( 'A form was created, modified', 'wp-security-audit-log' ),
					esc_html__( 'The Form called %form_name%.', 'wp-security-audit-log' ),

					array(
						esc_html__( 'Form ID', 'wp-security-audit-log' ) => '%form_id%',
					),
					array(
						esc_html__( 'View form in the editor', 'wp-security-audit-log' ) => '%EditorLinkForm%',
					),
					'gravityforms_forms',
					'created',
				),

				5701 => array(
					5701,
					WSAL_MEDIUM,
					esc_html__( 'A form was moved to trash', 'wp-security-audit-log' ),
					esc_html__( 'Moved the form to trash.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Form name', 'wp-security-audit-log' ) => '%form_name%',
						esc_html__( 'Form ID', 'wp-security-audit-log' ) => '%form_id%',
					),
					array(
						esc_html__( 'View form in the editor', 'wp-security-audit-log' ) => '%EditorLinkForm%',
					),
					'gravityforms_forms',
					'created',
				),

				5702 => array(
					5702,
					WSAL_MEDIUM,
					esc_html__( 'A form was permanently deleted', 'wp-security-audit-log' ),
					esc_html__( 'Permanently deleted the form %form_name%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Form ID', 'wp-security-audit-log' ) => '%form_id%',
					),
					array(),
					'gravityforms_forms',
					'created',
				),

				5703 => array(
					5703,
					WSAL_MEDIUM,
					esc_html__( 'A form setting was modified', 'wp-security-audit-log' ),
					esc_html__( 'The setting %setting_name% in form %form_name%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous value', 'wp-security-audit-log' ) => '%old_setting_value%',
						esc_html__( 'New value', 'wp-security-audit-log' ) => '%setting_value%',
						esc_html__( 'Form ID', 'wp-security-audit-log' ) => '%form_id%',
					),
					array(
						esc_html__( 'View form in the editor', 'wp-security-audit-log' ) => '%EditorLinkForm%',
					),
					'gravityforms_forms',
					'modified',
				),

				5704 => array(
					5704,
					WSAL_LOW,
					esc_html__( 'A form was duplicated', 'wp-security-audit-log' ),
					esc_html__( 'Duplicated the form %original_form_name%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'New form name', 'wp-security-audit-log' ) => '%new_form_name%',
						esc_html__( 'Source form ID', 'wp-security-audit-log' ) => '%original_form_id%',
						esc_html__( 'New form ID', 'wp-security-audit-log' ) => '%new_form_id%',
					),
					array(
						esc_html__( 'View new duplicated form in the editor', 'wp-security-audit-log' ) => '%EditorLinkForm%',
					),
					'gravityforms_forms',
					'duplicated',
				),

				5715 => array(
					5715,
					WSAL_MEDIUM,
					esc_html__( 'A field was created, modified or deleted', 'wp-security-audit-log' ),
					esc_html__( 'The Field called %field_name% in the form %form_name%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Field type', 'wp-security-audit-log' ) => '%field_type%',
						esc_html__( 'Form ID', 'wp-security-audit-log' ) => '%form_id%',
					),
					array(
						esc_html__( 'View form in the editor', 'wp-security-audit-log' ) => '%EditorLinkForm%',
					),
					'gravityforms_fields',
					'created',
				),

				5709 => array(
					5709,
					WSAL_LOW,
					esc_html__( 'A form was submitted', 'wp-security-audit-log' ),
					esc_html__( 'Submitted the form %form_name%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Form ID', 'wp-security-audit-log' ) => '%form_id%',
						esc_html__( 'Submission email', 'wp-security-audit-log' ) => '%email%',
					),
					array(
						esc_html__( 'View entry', 'wp-security-audit-log' ) => '%EntryLink%',
					),
					'gravityforms_forms',
					'duplicated',
				),

				/*
				 * Form confirmations.
				 */
				5705 => array(
					5705,
					WSAL_MEDIUM,
					esc_html__( 'A confirmation was created, modified or deleted', 'wp-security-audit-log' ),
					esc_html__( 'The Confirmation called %confirmation_name% in the form %form_name%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Confirmation type', 'wp-security-audit-log' ) => '%confirmation_type%',
						esc_html__( 'Confirmation message', 'wp-security-audit-log' ) => '%confirmation_message%',
						esc_html__( 'Form ID', 'wp-security-audit-log' ) => '%form_id%',
					),
					array(
						esc_html__( 'View form in the editor', 'wp-security-audit-log' ) => '%EditorLinkForm%',
					),
					'gravityforms_confirmations',
					'created',
				),

				5708 => array(
					5708,
					WSAL_LOW,
					esc_html__( 'A confirmation was activated or deactivated', 'wp-security-audit-log' ),
					esc_html__( 'The confirmation %confirmation_name% in the form %form_name%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Form ID', 'wp-security-audit-log' ) => '%form_id%',
					),
					array(
						esc_html__( 'View form in the editor', 'wp-security-audit-log' ) => '%EditorLinkForm%',
					),
					'gravityforms_confirmations',
					'created',
				),

				/*
				 * Form notifications.
				 */
				5706 => array(
					5706,
					WSAL_MEDIUM,
					esc_html__( 'A notification was created, modified or deleted', 'wp-security-audit-log' ),
					esc_html__( 'The Notification called %notification_name% in the form %form_name%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Form ID', 'wp-security-audit-log' ) => '%form_id%',
					),
					array(
						esc_html__( 'View form in the editor', 'wp-security-audit-log' ) => '%EditorLinkForm%',
					),
					'gravityforms_notifications',
					'created',
				),

				5707 => array(
					5707,
					WSAL_LOW,
					esc_html__( 'A notification was activated or deactivated', 'wp-security-audit-log' ),
					esc_html__( 'Changed the status of the Notification called %notification_name% in the form %form_name%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Form ID', 'wp-security-audit-log' ) => '%form_id%',
					),
					array(
						esc_html__( 'View form in the editor', 'wp-security-audit-log' ) => '%EditorLinkForm%',
					),
					'gravityforms_notifications',
					'activated',
				),

				/*
				 * Form entries.
				 */
				5710 => array(
					5710,
					WSAL_LOW,
					esc_html__( 'An entry was starred or unstarred', 'wp-security-audit-log' ),
					esc_html__( 'Entry title: %entry_title%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Form name', 'wp-security-audit-log' ) => '%form_name%',
						esc_html__( 'Form ID', 'wp-security-audit-log' ) => '%form_id%',
					),
					array(
						esc_html__( 'View entry', 'wp-security-audit-log' ) => '%EntryLink%',
					),
					'gravityforms_entries',
					'starred',
				),

				5711 => array(
					5711,
					WSAL_LOW,
					esc_html__( 'An entry was marked as read or unread', 'wp-security-audit-log' ),
					esc_html__( 'The entry called %entry_title% from form %form_name%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Form ID', 'wp-security-audit-log' ) => '%form_id%',
					),
					array(
						esc_html__( 'View entry', 'wp-security-audit-log' ) => '%EntryLink%',
					),
					'gravityforms_entries',
					'read',
				),

				5712 => array(
					5712,
					WSAL_MEDIUM,
					esc_html__( 'An entry was moved to trash', 'wp-security-audit-log' ),
					esc_html__( 'Deleted the entry %event_desc%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Form name', 'wp-security-audit-log' ) => '%form_name%',
						esc_html__( 'Form ID ', 'wp-security-audit-log' ) => '%form_id%',
					),
					array(
						esc_html__( 'View entry', 'wp-security-audit-log' ) => '%EntryLink%',
					),
					'gravityforms_entries',
					'read',
				),

				5713 => array(
					5713,
					WSAL_MEDIUM,
					esc_html__( 'An entry was permanently deleted', 'wp-security-audit-log' ),
					esc_html__( 'Permanently deleted the entry %entry_title%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Form name', 'wp-security-audit-log' ) => '%form_name%',
						esc_html__( 'Form ID', 'wp-security-audit-log' ) => '%form_id%',
					),
					array(),
					'gravityforms_entries',
					'read',
				),

				5714 => array(
					5714,
					WSAL_MEDIUM,
					esc_html__( 'An entry note was created or deleted', 'wp-security-audit-log' ),
					esc_html__( 'The entry note %entry_note%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Entry title', 'wp-security-audit-log' ) => '%entry_title%',
						esc_html__( 'Form name', 'wp-security-audit-log' ) => '%form_name%',
						esc_html__( 'Form ID', 'wp-security-audit-log' ) => '%form_id%',
					),
					array(
						esc_html__( 'View entry', 'wp-security-audit-log' ) => '%EntryLink%',
					),
					'gravityforms_entries',
					'read',
				),

				5717 => array(
					5717,
					WSAL_MEDIUM,
					esc_html__( 'An entry was edited', 'wp-security-audit-log' ),
					esc_html__( 'The entry %entry_name% was edited.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Form name', 'wp-security-audit-log' ) => '%form_name%',
						esc_html__( 'Form ID', 'wp-security-audit-log' ) => '%form_id%',
					),
					array(
						esc_html__( 'View entry', 'wp-security-audit-log' ) => '%EntryLink%',
					),
					'gravityforms_entries',
					'modified',
				),

				/*
				 * Settings.
				 */
				5716 => array(
					5716,
					WSAL_HIGH,
					esc_html__( 'A plugin setting was changed.', 'wp-security-audit-log' ),
					esc_html__( 'Changed the plugin setting %setting_name%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous value', 'wp-security-audit-log' ) => '%old_value%',
						esc_html__( 'New value', 'wp-security-audit-log' ) => '%new_value%',
					),
					array(),
					'gravityforms_settings',
					'modified',
				),
				5718 => array(
					5718,
					WSAL_LOW,
					esc_html__( 'Form entries were imported / exported.', 'wp-security-audit-log' ),
					esc_html__( 'The entries from the form %form_name%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Form ID', 'wp-security-audit-log' ) => '%form_id%',
						esc_html__( 'Date range start', 'wp-security-audit-log' ) => '%start%',
						esc_html__( 'Date range end', 'wp-security-audit-log' ) => '%end%',
					),
					array(),
					'gravityforms_settings',
					'exported',
				),
				5719 => array(
					5719,
					WSAL_LOW,
					esc_html__( 'A form was imported / exported.', 'wp-security-audit-log' ),
					esc_html__( 'The form %form_name%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Form ID', 'wp-security-audit-log' ) => '%form_id%',
					),
					array(),
					'gravityforms_settings',
					'imported',
				),
				5720 => array(
					5720,
					\WSAL_MEDIUM,
					esc_html__( 'A form was activated / deactivated.', 'wp-security-audit-log' ),
					esc_html__( 'The form %form_name%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Form ID', 'wp-security-audit-log' ) => '%form_id%',
					),
					array(
						__( 'View form in editor', 'wp-security-audit-log' ) => '%EditorLinkForm%',
					),
					'gravityforms_settings',
					'imported',
				),
			);
		}
	}
}
