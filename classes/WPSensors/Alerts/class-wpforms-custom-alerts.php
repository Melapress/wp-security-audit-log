<?php
/**
 * Custom Alerts for WPForms plugin.
 *
 * Class file for alert manager.
 *
 * @since   1.0.0
 *
 * @package wsal
 * @subpackage wsal-wpforms-forms
 */

// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment
// phpcs:disable WordPress.WP.I18n.UnorderedPlaceholdersText

declare(strict_types=1);

namespace WSAL\WP_Sensors\Alerts;

use WSAL\MainWP\MainWP_Addon;
use WSAL\WP_Sensors\Helpers\WPForms_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\WP_Sensors\Alerts\WPForms_Custom_Alerts' ) ) {
	/**
	 * Custom sensor for Yoast plugin.
	 *
	 * @since 4.6.0
	 */
	class WPForms_Custom_Alerts {

		/**
		 * Returns the structure of the alerts for extension.
		 *
		 * @return array
		 *
		 * @since 4.6.0
		 */
		public static function get_custom_alerts(): array {
			// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment
			if ( WPForms_Helper::is_wpforms_active() || MainWP_Addon::check_mainwp_plugin_active() ) {
				return array(
					esc_html__( 'WPForms', 'wp-security-audit-log' ) => array(
						esc_html__( 'Form Content', 'wp-security-audit-log' ) => self::get_alerts_array(),
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

				5500 => array(
					5500,
					WSAL_LOW,
					esc_html__( 'A form was created, modified or deleted', 'wp-security-audit-log' ),
					esc_html__( 'The Form called %PostTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Form ID', 'wp-security-audit-log' ) => '%PostID%',
					),
					array(
						esc_html__( 'View form in the editor', 'wp-security-audit-log' ) => '%EditorLinkForm%',
					),
					'wpforms_forms',
					'created',
				),

				5501 => array(
					5501,
					WSAL_MEDIUM,
					esc_html__( 'A field was created, modified or deleted from a form.', 'wp-security-audit-log' ),
					esc_html__( 'The Field called %field_name% in the form %form_name%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Form ID', 'wp-security-audit-log' ) => '%PostID%',
					),
					array(
						esc_html__( 'View form in the editor', 'wp-security-audit-log' ) => '%EditorLinkForm%',
					),
					'wpforms_fields',
					'deleted',
				),

				5502 => array(
					5502,
					WSAL_MEDIUM,
					esc_html__( 'A form was duplicated', 'wp-security-audit-log' ),
					esc_html__( 'Duplicated the form %OldPostTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Source form ID', 'wp-security-audit-log' ) => '%SourceID%',
						esc_html__( 'New form ID', 'wp-security-audit-log' )    => '%PostID%',
					),
					array(
						esc_html__( 'View form in the editor', 'wp-security-audit-log' ) => '%EditorLinkFormDuplicated%',
					),
					'wpforms_forms',
					'duplicated',
				),

				5503 => array(
					5503,
					WSAL_LOW,
					esc_html__( 'A notification was added to a form, enabled or modified', 'wp-security-audit-log' ),
					esc_html__( 'The Notification called %notifiation_name% in the form %form_name%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Form ID', 'wp-security-audit-log' ) => '%PostID%',
					),
					array(
						esc_html__( 'View form in the editor', 'wp-security-audit-log' ) => '%EditorLinkForm%',
					),
					'wpforms_notifications',
					'added',
				),

				5504 => array(
					5504,
					WSAL_MEDIUM,
					esc_html__( 'An entry was deleted', 'wp-security-audit-log' ),
					esc_html__( 'Deleted the Entry with the email address %entry_email%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Entry ID', 'wp-security-audit-log' )  => '%entry_id%',
						esc_html__( 'Form name', 'wp-security-audit-log' ) => '%form_name%',
						esc_html__( 'Form ID', 'wp-security-audit-log' )   => '%form_id%',
					),
					array(
						esc_html__( 'View form in the editor', 'wp-security-audit-log' ) => '%EditorLinkForm%',
					),
					'wpforms_entries',
					'deleted',
				),

				5505 => array(
					5505,
					WSAL_LOW,
					esc_html__( 'Notifications were enabled or disabled in a form', 'wp-security-audit-log' ),
					esc_html__( 'Changed the status of all the notifications in the form %form_name%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Form ID', 'wp-security-audit-log' ) => '%PostID%',
					),
					array(
						esc_html__( 'View form in the editor', 'wp-security-audit-log' ) => '%EditorLinkForm%',
					),
					'wpforms_notifications',
					'deleted',
				),

				5506 => array(
					5506,
					WSAL_LOW,
					esc_html__( 'A form was renamed', 'wp-security-audit-log' ),
					esc_html__( 'Renamed the form %old_form_name% to %new_form_name%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Form ID', 'wp-security-audit-log' ) => '%PostID%',
					),
					array(
						esc_html__( 'View form in the editor', 'wp-security-audit-log' ) => '%EditorLinkForm%',
					),
					'wpforms_forms',
					'renamed',
				),

				5507 => array(
					5507,
					WSAL_MEDIUM,
					esc_html__( 'An entry was modified', 'wp-security-audit-log' ),
					esc_html__( 'Modified the Entry with ID %entry_id%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'From form', 'wp-security-audit-log' )      => '%form_name%',
						esc_html__( 'Modified field name', 'wp-security-audit-log' ) => '%field_name%',
						esc_html__( 'Previous value', 'wp-security-audit-log' ) => '%old_value%',
						esc_html__( 'New Value', 'wp-security-audit-log' )      => '%new_value%',
					),
					array(
						esc_html__( 'View entry in the editor', 'wp-security-audit-log' ) => '%EditorLinkEntry%',
					),
					'wpforms_entries',
					'modified',
				),

				5523 => array(
					5523,
					WSAL_MEDIUM,
					esc_html__( 'An form was submitted', 'wp-security-audit-log' ),
					esc_html__( 'Submitted the form %form_name%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Form ID', 'wp-security-audit-log' )  => '%form_id%',
						esc_html__( 'Entry email', 'wp-security-audit-log' )  => '%entry_email%',
					),
					array(
						esc_html__( 'View entry in the editor', 'wp-security-audit-log' ) => '%EditorLinkEntry%',
					),
					'wpforms_entries',
					'submitted',
				),

				5508 => array(
					5508,
					WSAL_HIGH,
					esc_html__( 'Plugin access settings were changed', 'wp-security-audit-log' ),
					esc_html__( 'Changed the WPForms access setting %setting_name%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Type', 'wp-security-audit-log' ) => '%setting_type%',
						esc_html__( 'Previous privileges', 'wp-security-audit-log' ) => '%old_value%',
						esc_html__( 'New privileges', 'wp-security-audit-log' ) => '%new_value%',
					),
					array(),
					'wpforms',
					'modified',
				),

				5509 => array(
					5509,
					WSAL_HIGH,
					esc_html__( 'Currency settings were changed', 'wp-security-audit-log' ),
					__( 'Changed the <strong>currency</strong> to %new_value%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous currency', 'wp-security-audit-log' ) => '%old_value%',
					),
					array(),
					'wpforms',
					'modified',
				),

				5510 => array(
					5510,
					WSAL_HIGH,
					esc_html__( 'A service integration was added or deleted', 'wp-security-audit-log' ),
					esc_html__( 'A service integration with %service_name%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Connection name', 'wp-security-audit-log' ) => '%connection_name%',
						esc_html__( 'Service', 'wp-security-audit-log' ) => '%service_name%',
					),
					array(),
					'wpforms',
					'added',
				),

				5511 => array(
					5511,
					WSAL_HIGH,
					esc_html__( 'An addon was installed, activated or deactivated.', 'wp-security-audit-log' ),
					esc_html__( 'The addon %addon_name%.', 'wp-security-audit-log' ),
					array(),
					array(),
					'wpforms',
					'activated',
				),

				5513 => array(
					5513,
					WSAL_HIGH,
					esc_html__( 'Changed the status of the setting Enable anti-spam protection', 'wp-security-audit-log' ),
					__( 'Changed the status of the setting <strong>Enable anti-spam protection.</strong>', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Form name', 'wp-security-audit-log' ) => '%form_name%',
						esc_html__( 'Form ID', 'wp-security-audit-log' )   => '%form_id%',
					),
					array(),
					'wpforms_forms',
					'enabled',
				),

				5514 => array(
					5514,
					WSAL_MEDIUM,
					esc_html__( 'Changed the status of the setting Enable dynamic fields population', 'wp-security-audit-log' ),
					__( 'Changed the status of the setting <strong>Enable dynamic fields population.</strong>', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Form name', 'wp-security-audit-log' ) => '%form_name%',
						esc_html__( 'Form ID', 'wp-security-audit-log' )   => '%form_id%',
					),
					array(),
					'wpforms_forms',
					'enabled',
				),

				5515 => array(
					5515,
					WSAL_MEDIUM,
					esc_html__( 'Changed the status of the setting Enable AJAX form submission.', 'wp-security-audit-log' ),
					__( 'Changed the status of the setting <strong>Enable AJAX form submission.</strong>', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Form name', 'wp-security-audit-log' ) => '%form_name%',
						esc_html__( 'Form ID', 'wp-security-audit-log' )   => '%form_id%',
					),
					array(),
					'wpforms_forms',
					'enabled',
				),

				5516 => array(
					5516,
					WSAL_MEDIUM,
					esc_html__( 'A notification name was renamed', 'wp-security-audit-log' ),
					esc_html__( 'Renamed the notification %old_name% to %new_name%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Form name', 'wp-security-audit-log' ) => '%form_name%',
						esc_html__( 'Form ID', 'wp-security-audit-log' )   => '%form_id%',
					),
					array(),
					'wpforms_notifications',
					'renamed',
				),

				5517 => array(
					5517,
					WSAL_MEDIUM,
					esc_html__( 'A notifications metadata was modified', 'wp-security-audit-log' ),
					esc_html__( 'Changed the %metadata_name% to %new_value% in %notification_name%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous value', 'wp-security-audit-log' ) => '%old_value%',
						esc_html__( 'Form name', 'wp-security-audit-log' )      => '%form_name%',
						esc_html__( 'Form ID', 'wp-security-audit-log' )        => '%form_id%',
					),
					array(),
					'wpforms_notifications',
					'modified',
				),

				5518 => array(
					5518,
					WSAL_MEDIUM,
					esc_html__( 'A confirmation was added / removed', 'wp-security-audit-log' ),
					esc_html__( 'The confirmation %confirmation_name%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Form name', 'wp-security-audit-log' ) => '%form_name%',
						esc_html__( 'Form ID', 'wp-security-audit-log' )   => '%form_id%',
					),
					array(),
					'wpforms_confirmations',
					'added',
				),

				5519 => array(
					5519,
					WSAL_MEDIUM,
					esc_html__( 'A Confirmation Type type was modified', 'wp-security-audit-log' ),
					__( 'Changed the <strong>Confirmation Type</strong> of the confirmation %confirmation_name%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'New Confirmation Type', 'wp-security-audit-log' ) => '%new_value%',
						esc_html__( 'Previous Confirmation Type', 'wp-security-audit-log' ) => '%old_value%%',
						esc_html__( 'Form name', 'wp-security-audit-log' ) => '%form_name%',
						esc_html__( 'Form ID', 'wp-security-audit-log' )   => '%form_id%',
					),
					array(),
					'wpforms_confirmations',
					'modified',
				),

				5520 => array(
					5520,
					WSAL_MEDIUM,
					esc_html__( 'A Confirmation Page type was modified', 'wp-security-audit-log' ),
					__( 'Changed the <strong>Confirmation Page</strong> to %new_value%', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous Confirmation Page', 'wp-security-audit-log' ) => '%old_value%%',
						esc_html__( 'Form name', 'wp-security-audit-log' ) => '%form_name%',
						esc_html__( 'Form ID', 'wp-security-audit-log' )   => '%form_id%',
					),
					array(),
					'wpforms_confirmations',
					'modified',
				),

				5521 => array(
					5521,
					WSAL_MEDIUM,
					esc_html__( 'A Confirmation Redirecttype was modified', 'wp-security-audit-log' ),
					__( 'Changed the <strong>Confirmation Redirect URL</strong> to %new_value%', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous Confirmation Redirect URL', 'wp-security-audit-log' ) => '%old_value%%',
						esc_html__( 'Form name', 'wp-security-audit-log' ) => '%form_name%',
						esc_html__( 'Form ID', 'wp-security-audit-log' )   => '%form_id%',
					),
					array(),
					'wpforms_confirmations',
					'modified',
				),

				5522 => array(
					5522,
					WSAL_MEDIUM,
					esc_html__( 'A Confirmation Message type was modified', 'wp-security-audit-log' ),
					__( 'Changed the <strong>Confirmation Message</strong> to %new_value%', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous Confirmation Message', 'wp-security-audit-log' ) => '%old_value%',
						esc_html__( 'Form name', 'wp-security-audit-log' ) => '%form_name%',
						esc_html__( 'Form ID', 'wp-security-audit-log' )   => '%form_id%',
					),
					array(),
					'wpforms_confirmations',
					'modified',
				),
			);
		}
	}
}
