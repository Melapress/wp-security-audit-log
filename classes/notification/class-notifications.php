<?php
/**
 * View: WSAL Notifications
 *
 * WSAL setup class file.
 *
 * @since 5.1.1
 * @package    wsal
 * @subpackage views
 */

declare(strict_types=1);

namespace WSAL\Views;

use Tools\Select2_WPWS;
use WSAL\Helpers\WP_Helper;
use WSAL\Helpers\Email_Helper;
use WSAL\Helpers\View_Manager;
use WSAL\Controllers\Slack\Slack;
use WSAL\Helpers\Settings_Helper;
use WSAL\Controllers\Alert_Manager;
use WSAL\Controllers\Twilio\Twilio;
use WSAL\Controllers\Slack\Slack_API;
use WSAL\Controllers\Twilio\Twilio_API;
use WSAL\Helpers\Settings\Settings_Builder;
use WSAL\Entities\Custom_Notifications_Entity;
use WSAL\WP_Sensors\Helpers\Woocommerce_Helper;
use WSAL\Extensions\Helpers\Notification_Helper;
use WSAL\Helpers\Formatters\Alert_Formatter_Configuration;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( '\WSAL\Views\Notifications' ) ) {
	/**
	 * Class: WSAL notifications.
	 *
	 * @package    wsal
	 * @subpackage views
	 *
	 * @since 5.1.1
	 */
	class Notifications {
		public const NOTIFICATIONS_SETTINGS_NAME          = 'notifications';
		public const BUILT_IN_NOTIFICATIONS_SETTINGS_NAME = 'built-in-notifications';
		public const CUSTOM_NOTIFICATIONS_SETTINGS_NAME   = 'custom-notifications';
		public const BUILT_IN_SEND_NOW_NONCE_NAME         = WSAL_PREFIX . 'send_daily_summary_now';

		/**
		 * Pointer to the hook suffix
		 *
		 * @var string
		 *
		 * @since 5.1.1
		 */
		private static $hook_suffix = null;

		/**
		 * Caching the twilio status.
		 *
		 * @var boolean
		 *
		 * @since 5.1.1
		 */
		private static $twilio_is_set = null;

		/**
		 * Caching the slack status.
		 *
		 * @var boolean
		 *
		 * @since 5.3.4
		 */
		private static $slack_is_set = null;

		/**
		 * Caching the global notification settings.
		 *
		 * @var array
		 *
		 * @since 5.2.0
		 */
		private static $global_notifications_setting = null;

		/**
		 * This holds the main ids to search for. If there are multiple IDs they are in separate array where the main id is the key and the value are ids of events to collect for this main id.
		 *
		 * @var array
		 *
		 * @since 5.2.1
		 */
		private static $events_to_collect = array(
			// Suspicious activity Notifications.
			1002,
			1003,
			// Plugin Changes Notifications.
			6004,
			// Plugin Changes Notifications.
			5000,
			5001,
			2051,
			5002,
			5003,
			5004,
			// Theme Changes Notifications.
			5005,
			5006,
			2046,
			5007,
			5031,
			// User Activity.
			1000,
			4003,
			// User profile Changes.
			4005,
			4002,
			4004,
			4000,
			// Content Changes.
			2001,
			2065,
			2008,
			2012,
			2002,
			// Multisite.
			4008,
			4009,
			4010,
			4011,
			7000,
			5008,
			5009,
			// Woo.
			9000,
			9027,
			9063,
			9035,
		);

		/**
		 * This holds the severities ids to search for. The principle is exactly the same as the events ids, except this is using the fact that the severities are 3 digits, so there wont be overlap with some event id as they start form 4 digits.
		 *
		 * @var array
		 *
		 * @since 5.2.1
		 */
		private static $severities_to_collect = array(
			500,
		);

		/**
		 * Holds the relation array one -> many for the stored ids (some notifications are triggered by more than one event id - this array is used to represent the relations)
		 *
		 * @var array
		 *
		 * @since 5.2.1
		 */
		public static $additional_events_to_store = array(
			4005 => array(
				4006,
			),
			4000 => array(
				4001,
				4012,
			),
			2002 => array(
				2016,
				2017,
				2019,
				2021,
				2025,
				2027,
				2047,
				2048,
				2049,
				2050,
				2053,
				2054,
				2055,
				2062,
				2086,
				2119,
				2120,
				2131,
				2132,
			),
			7000 => array(
				7001,
				7002,
				7003,
				7004,
				7005,
			),
			9000 => array(
				9001,
				9003,
				9004,
				9005,
				9006,
				9008,
				9009,
				9010,
				9011,
				9012,
				9013,
				9014,
				9015,
				9072,
				9073,
				9077,
				9007,
				9016,
				9017,
				9018,
				9019,
				9020,
				9021,
				9022,
				9023,
				9024,
				9025,
				9026,
				9042,
				9043,
				9044,
				9045,
				9046,
				9047,
				9048,
				9049,
				9050,
				9051,
			),
			9027 => array(
				9028,
				9029,
				9030,
				9031,
				9032,
				9033,
				9034,
				9074,
				9075,
				9076,
				9159,
			),
			9063 => array(
				9064,
				9065,
				9066,
				9067,
				9068,
				9069,
				9070,
				9071,
			),
			9035 => array(
				9036,
				9037,
				9038,
				9039,
				9040,
				9041,
			),
		);

		/**
		 * Initialize the report class
		 *
		 * @return void
		 *
		 * @since 5.1.1
		 */
		public static function init() {
			if ( \is_admin() ) {
				\add_action( 'wsal_init', array( __CLASS__, 'wsal_init' ), 20 );

				Settings_Builder::init();

				/**
				 * Save Options
				 */
				\add_action( 'wp_ajax_notifications_data_save', array( __CLASS__, 'save_settings_ajax' ) );

				\add_action( 'wp_ajax_send_daily_summary_now', array( __CLASS__, 'send_daily_summary' ) );

				// AJAX test email part.
				\add_action( 'wp_ajax_wsal_send_notifications_test_email', array( Notification_Helper::class, 'send_test_email' ) );

				// phpcs:disable
				// phpcs:enable
			}

			$current_settings = Settings_Helper::get_option_value( self::BUILT_IN_NOTIFICATIONS_SETTINGS_NAME, array() );

			/** Set defaults */
			if ( empty( $current_settings ) ) {
				self::build_in_check_and_save( array( 'notification_weekly_summary_notification' => true ), true );
			}

			// phpcs:disable
			// phpcs:enable
		}

		/**
		 * Hooks on the main plugin init process
		 *
		 * @return void
		 *
		 * @since 5.1.1
		 */
		public static function wsal_init() {
			View_Manager::add_from_class( __CLASS__ );
		}

		/**
		 * Registers alert formatters for SMS and email notifications.
		 *
		 * @param array $formatters Formatter definitions array.
		 *
		 * @return array
		 *
		 * @since 5.3.0
		 */
		public static function register_alert_formatters( $formatters ) {

			$formatters['sms']   = Alert_Formatter_Configuration::get_default_configuration();
			$formatters['email'] = Alert_Formatter_Configuration::set_configuration(
				array(
					'is_js_in_links_allowed'  => false,
					'set_supports_metadata'   => false,
					'set_supports_hyperlinks' => false,
				),
				true
			);
			$formatters['slack'] = Alert_Formatter_Configuration::get_default_slack_configuration();

			return $formatters;
		}

		/**
		 * Override this and make it return true to create a shortcut link in plugin page to the view.
		 *
		 * @return boolean
		 *
		 * @since 5.1.1
		 */
		public static function has_plugin_shortcut_link() {
			return false;
		}

		/**
		 * Stores the view weight (where is should be positioned in the menu).
		 *
		 * @since 5.1.1
		 */
		public static function get_weight() {
			return 3;
		}

		/**
		 * Method: Whether page should be accessible or not.
		 *
		 * @return boolean
		 *
		 * @since 5.1.1
		 */
		public static function is_accessible() {
			return true;
		}

		/**
		 * Method: Safe view menu name.
		 *
		 * @return string
		 *
		 * @since 5.1.1
		 */
		public static function get_safe_view_name() {
			return 'wsal-notifications';
		}

		/**
		 * Returns the view name
		 *
		 * @since 5.1.1
		 */
		public static function get_title() {
			return \esc_html__( 'Notifications', 'wp-security-audit-log' );
		}

		/**
		 * Returns the view name
		 *
		 * @since 5.1.1
		 */
		public static function get_name() {
			return \esc_html__( 'Notifications', 'wp-security-audit-log' );
		}

		/**
		 * Method: Whether page should appear in menu or not.
		 *
		 * @return boolean
		 *
		 * @since 5.1.1
		 */
		public static function is_visible() {
			return true;
		}

		/**
		 * Sets the hook suffix
		 *
		 * @param string $suffix - The hook suffix.
		 *
		 * @return void
		 *
		 * @since 5.1.1
		 */
		public static function set_hook_suffix( $suffix ) {
			self::$hook_suffix = $suffix;
		}

		/**
		 * Returns the hook suffix
		 *
		 * @return string
		 *
		 * @since 5.1.1
		 */
		public static function get_hook_suffix() {
			return self::$hook_suffix;
		}

		/**
		 * Draws the header
		 *
		 * @since 5.1.1
		 */
		public static function header() {

			$dependencies = array(
				'jquery',
				'jquery-ui-sortable',
				'jquery-ui-draggable',
				'wp-color-picker',
				'jquery-ui-autocomplete',
			);

			// phpcs:disable
			// phpcs:enable

			\wp_enqueue_script(
				'wsal-notifications-admin-scripts',
				WSAL_BASE_URL . 'classes/Helpers/settings/admin/wsal-notification-settings.js',
				$dependencies,
				WSAL_VERSION,
				false
			);

			\wp_enqueue_style(
				'wsal-notifications-admin-style',
				WSAL_BASE_URL . 'classes/Helpers/settings/admin/style.css',
				array(),
				WSAL_VERSION,
				'all'
			);

			// phpcs:disable
			// phpcs:enable
		}

		/**
		 * Draws the footer
		 *
		 * @since 5.1.1
		 */
		public static function footer() {
		}

		/**
		 * Renders the view icon (this has been deprecated in newer WP versions).
		 *
		 * @since 5.1.1
		 */
		public static function render_icon() {
			?>
			<div id="icon-plugins" class="icon32"><br></div>
			<?php
		}

		/**
		 * Renders the view title.
		 *
		 * @since 5.1.1
		 */
		public static function render_title() {
			echo '<h2>' . esc_html( self::get_title() ) . '</h2>';
		}

		/**
		 * Method: Render content of the view.
		 *
		 * @since 5.1.1
		 */
		public static function render_content() {
			self::content_show();
		}

		/**
		 * Shows the view of the selected options
		 *
		 * @return void
		 *
		 * @since 5.1.1
		 */
		public static function content_show() {

			\wp_enqueue_media();

			$settings_tabs = array(
				'notifications-highlights' => array(
					'icon'  => 'email-alt',
					'title' => \esc_html__( 'Activity log highlights', 'wp-security-audit-log' ),
				),
			);
			// phpcs:disable
			// phpcs:enable

			if ( ! WP_Helper::is_multisite() ) {
				unset( $settings_tabs['multisite-notifications'] );
			}

			if ( ! Woocommerce_Helper::is_woocommerce_active() ) {
				unset( $settings_tabs['woocommerce-notifications'] );
			}

			?>
			<div id="wsal-page-overlay"></div>

			<div id="wsal-saving-settings">
				<svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
					<circle class="checkmark__circle" cx="26" cy="26" r="25" fill="none" />
					<path class="checkmark__check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8" />
					<path class="checkmark__error_1" d="M38 38 L16 16 Z" />
					<path class="checkmark__error_2" d="M16 38 38 16 Z" />
				</svg>
			</div>

			<div class="wsal-panel wrap">

				<div class="wsal-panel-tabs">
					
					<ul>
					<?php
					$custom_notification_id_hidden_field = '';

					if ( isset( $_REQUEST['action'] ) && 'edit' === $_REQUEST['action'] && isset( $_REQUEST['_wpnonce'] ) && isset( $_REQUEST[ Custom_Notifications_Entity::get_table_name() ] ) ) {

						$nonce = \sanitize_text_field( \wp_unslash( $_REQUEST['_wpnonce'] ) );
						// verify the nonce.
						if ( \wp_verify_nonce( $nonce, 'bulk-custom-notifications' ) ) {

							$custom_notification_id_hidden_field = '<input type="hidden" name="' . self::NOTIFICATIONS_SETTINGS_NAME . '[generated_custom_notification_id]" value="' . absint( ( (array) $_REQUEST[ Custom_Notifications_Entity::get_table_name() ] )[0] ) . '">';

						} else {
							unset( $settings_tabs['custom-notification-edit'] );
						}
					} else {
						unset( $settings_tabs['custom-notification-edit'] );
					}

					if ( empty( $custom_notification_id_hidden_field ) ) {
						foreach ( $settings_tabs as $tab => $settings ) {

							$icon  = $settings['icon'];
							$title = $settings['title'];
							$style = $settings['style'] ?? '';
							?>

						<li class="wsal-tabs wsal-options-tab-<?php echo \esc_attr( $tab ); ?>">
							<a href="#wsal-options-tab-<?php echo \esc_attr( $tab ); ?>">
								<span style="<?php echo $style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" class="dashicons-before dashicons-<?php echo \esc_html( $icon ); ?> wsal-icon-menu"></span>
							<?php echo \esc_html( $title ); ?>
							</a>
						</li>
							<?php
						}
					} else {
						self::get_report_back_link();
					}
					?>
					</ul>
					<div class="clear"></div>
				</div> <!-- .wsal-panel-tabs -->

				<div class="wsal-panel-content">

					<form method="post" name="wsal_form" id="wsal_form" enctype="multipart/form-data">

			<?php
			foreach ( $settings_tabs as $tab => $settings ) {

				?>
						<!-- <?php echo \esc_attr( $tab ); ?> Settings -->
						<div id="wsal-options-tab-<?php echo \esc_attr( $tab ); ?>" class="tabs-wrap">

					<?php
					include_once __DIR__ . '/options/' . $tab . '.php';
					?>

						</div>
					<?php
			}
			?>

			<?php \wp_nonce_field( 'notifications-data', 'wsal-security' ); ?>
						<input type="hidden" name="action" value="notifications_data_save" />

						<div class="wsal-footer">
				<?php \do_action( 'wsal_settings_save_button' ); ?>
						</div>
					</form>
				</div><!-- .wsal-panel-content -->
				<div class="clear"></div>

			</div><!-- .wsal-panel -->
				<?php if ( ! isset( $_GET['action'] ) || ( isset( $_GET['action'] ) && 'view_data' !== $_GET['action'] ) ) { ?>
			<script>
				jQuery('.wsal-save-button').text('Save Notifications');
			</script>
					<?php
				}
		}

		/**
		 * Reports get back link
		 *
		 * @return void
		 *
		 * @since 5.2.1
		 */
		public static function get_report_back_link() {
			?>
			<li>
			<?php
			echo '<a href="' . \esc_url( \add_query_arg( 'page', self::get_safe_view_name(), \network_admin_url( 'admin.php' ) ) ) . '#wsal-options-tab-custom-notifications"><span class="dashicons-before dashicons-controls-back wsal-icon-menu"></span>' . \esc_html__( 'Back', 'wp-security-audit-log' ) . '</a>';
			?>
			</li>
			<?php
		}

		/**
		 * Collects all the submitted reports data and saves them or generates a new report.
		 *
		 * @return void
		 *
		 * @since 5.1.1
		 */
		public static function save_settings_ajax() {
			if ( \check_ajax_referer( 'notifications-data', 'wsal-security' ) ) {

				if ( ! \current_user_can( 'manage_options' ) ) {
					\wp_die( \esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-security-audit-log' ) );
				}

				if ( isset( $_POST[ self::BUILT_IN_NOTIFICATIONS_SETTINGS_NAME ] ) && ! empty( $_POST[ self::BUILT_IN_NOTIFICATIONS_SETTINGS_NAME ] ) && \is_array( $_POST[ self::BUILT_IN_NOTIFICATIONS_SETTINGS_NAME ] ) ) {

					$data = \stripslashes_deep( $_POST[ self::BUILT_IN_NOTIFICATIONS_SETTINGS_NAME ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
					self::build_in_check_and_save( $data );
				}

				if ( isset( $_POST[ self::NOTIFICATIONS_SETTINGS_NAME ] ) && ! empty( $_POST[ self::NOTIFICATIONS_SETTINGS_NAME ] ) && \is_array( $_POST[ self::NOTIFICATIONS_SETTINGS_NAME ] ) ) {

					$data = \stripslashes_deep( $_POST[ self::NOTIFICATIONS_SETTINGS_NAME ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
					self::settings_check_and_save( $data );
				}

				if ( isset( $_POST[ self::CUSTOM_NOTIFICATIONS_SETTINGS_NAME ] ) && ! empty( $_POST[ self::CUSTOM_NOTIFICATIONS_SETTINGS_NAME ] ) && \is_array( $_POST[ self::CUSTOM_NOTIFICATIONS_SETTINGS_NAME ] ) ) {

					$data         = \stripslashes_deep( $_POST[ self::CUSTOM_NOTIFICATIONS_SETTINGS_NAME ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
					$return_value = self::custom_notifications_check_and_save( $data );

					if ( isset( $return_value['redirect'] ) && '' !== trim( (string) $return_value['redirect'] ) ) {
						\wp_send_json_success( array( 'redirect' => $return_value['redirect'] ) );
						exit;
					}
				}

				\wp_send_json_success( 2 );
			}
		}

		/**
		 * Ajax handler for test summary button.
		 *
		 * @since 5.1.1
		 */
		public static function send_daily_summary() {
			if ( ! \current_user_can( 'manage_options' ) ) {
				\wp_die(
					\esc_html__(
						'You do not have sufficient permissions to access this page.',
						'wp-security-audit-log'
					)
				);
			}

			if ( isset( $_REQUEST['_wpnonce'] ) ) {
				$nonce_check = \wp_verify_nonce( \sanitize_text_field( \wp_unslash( $_REQUEST['_wpnonce'] ) ), self::BUILT_IN_SEND_NOW_NONCE_NAME );
				if ( ! $nonce_check ) {
					\wp_send_json_error( new \WP_Error( 500, \esc_html__( 'Nonce checking failed', 'wp-security-audit-log' ) ), 400 );
				}
			} else {
				\wp_send_json_error( new \WP_Error( 500, \esc_html__( 'Nonce is not provided', 'wp-security-audit-log' ) ), 400 );
			}

			$report = Notification_Helper::get_report( true );

			if ( ! $report ) {
				\wp_send_json_error( __( 'Daily report is empty', 'wp-security-audit-log' ) );
			}

			// Summary email address.
			$summary_emails = Settings_Helper::get_option_value( self::BUILT_IN_NOTIFICATIONS_SETTINGS_NAME, array() );

			$email_from = 'daily_email_address';

			if ( isset( $_REQUEST['weekly'] ) && 1 === (int) $_REQUEST['weekly'] ) {
				$email_from = 'weekly_email_address';
			}

			if ( isset( $summary_emails[ $email_from ] ) ) {
				$summary_emails = $summary_emails[ $email_from ];
			}

			$summary_emails = explode( ',', $summary_emails );
			$result         = false;
			if ( $summary_emails && isset( $report['subject'] ) && isset( $report['body'] ) ) {
				foreach ( $summary_emails as $email ) {
					$result = Notification_Helper::send_notification_email( $email, $report['subject'], $report['body'] );
				}
			}

			if ( $result ) {
				\wp_send_json_success();
			} else {
				\wp_send_json_error( __( 'The plugin failed to send the email. Is your email set up correctly? Please consult with your website administrator or contact us to assist you with the troubleshooting.', 'wp-security-audit-log' ) );
			}

			exit();
		}

		/**
		 * Daily Cron report sending.
		 *
		 * @since 5.3.0
		 */
		public static function send_daily_summary_cron() {
			$report = Notification_Helper::get_report( false );

			if ( ! $report ) {
				return;
			}

			// Summary email address.
			$summary_emails = Settings_Helper::get_option_value( self::BUILT_IN_NOTIFICATIONS_SETTINGS_NAME, array() );

			if ( isset( $summary_emails['daily_email_address'] ) ) {
				$summary_emails = $summary_emails['daily_email_address'];
			}

			$summary_emails = explode( ',', $summary_emails );
			if ( empty( $summary_emails ) ) {
				return;
			}

			if ( $summary_emails && isset( $report['subject'] ) && isset( $report['body'] ) ) {
				foreach ( $summary_emails as $email ) {
					Notification_Helper::send_notification_email( $email, $report['subject'], $report['body'] );
				}
			}
		}

		/**
		 * Weekly Cron report sending.
		 *
		 * @since 5.3.0
		 */
		public static function send_weekly_summary_cron() {
			$report = Notification_Helper::get_report( false, true );

			if ( ! $report ) {
				return;
			}

			// Summary email address.
			$summary_emails = Settings_Helper::get_option_value( self::BUILT_IN_NOTIFICATIONS_SETTINGS_NAME, array() );

			if ( isset( $summary_emails['weekly_email_address'] ) ) {
				$summary_emails = $summary_emails['weekly_email_address'];
			}

			$summary_emails = explode( ',', $summary_emails );
			if ( empty( $summary_emails ) ) {
				return;
			}

			if ( $summary_emails && isset( $report['subject'] ) && isset( $report['body'] ) ) {
				foreach ( $summary_emails as $email ) {
					Notification_Helper::send_notification_email( $email, $report['subject'], $report['body'] );
				}
			}
		}

		/**
		 * Checks if default email address is set in the notifications settings.
		 *
		 * @return boolean
		 *
		 * @since 5.1.1
		 */
		public static function is_default_mail_set(): bool {
			$settings = self::get_global_notifications_setting();

			if ( ! empty( $settings ) && isset( $settings['notification_default_email_address'] ) && ! empty( $settings['notification_default_email_address'] ) ) {
				return true;
			}

			return false;
		}

		/**
		 * Returns the default email address from the notifications settings.
		 *
		 * @return string
		 *
		 * @since 5.1.1
		 */
		public static function get_default_mail(): string {
			$settings = self::get_global_notifications_setting();

			if ( ! empty( $settings ) && isset( $settings['notification_default_email_address'] ) && ! empty( $settings['notification_default_email_address'] ) ) {
				return $settings['notification_default_email_address'];
			}

			return '';
		}

		/**
		 * Checks if default phone number is set in the notifications settings.
		 *
		 * @return boolean
		 *
		 * @since 5.1.1
		 */
		public static function is_default_twilio_set(): bool {

			if ( null === self::$twilio_is_set ) {
				self::$twilio_is_set = false;

				if ( ! Twilio::is_set() ) {
					return false;
				}

				$settings = self::get_global_notifications_setting();

				if ( ! empty( $settings ) && isset( $settings['notification_default_phone'] ) && ! empty( $settings['notification_default_phone'] ) ) {
					self::$twilio_is_set = true;

					return true;
				}
			}

			return self::$twilio_is_set;
		}

		/**
		 * Returns the default phone number from the notifications settings.
		 *
		 * @return string
		 *
		 * @since 5.1.1
		 */
		public static function get_default_twilio(): string {
			$settings = self::get_global_notifications_setting();

			if ( ! empty( $settings ) && isset( $settings['notification_default_phone'] ) && ! empty( $settings['notification_default_phone'] ) ) {
				return $settings['notification_default_phone'];
			}

			return '';
		}

		/**
		 * Checks if default phone number is set in the notifications settings.
		 *
		 * @return boolean
		 *
		 * @since 5.1.1
		 */
		public static function is_default_slack_set(): bool {

			if ( null === self::$slack_is_set ) {
				self::$slack_is_set = false;

				if ( ! Slack::is_set() ) {
					return false;
				}

				$settings = self::get_global_notifications_setting();

				if ( ! empty( $settings ) && isset( $settings['notification_default_slack_channel'] ) && ! empty( $settings['notification_default_slack_channel'] ) ) {
					self::$slack_is_set = true;

					return true;
				}
			}

			return self::$slack_is_set;
		}

		/**
		 * Returns the default phone number from the notifications settings.
		 *
		 * @return string
		 *
		 * @since 5.1.1
		 */
		public static function get_default_slack(): string {
			$settings = self::get_global_notifications_setting();

			if ( ! empty( $settings ) && isset( $settings['notification_default_slack_channel'] ) && ! empty( $settings['notification_default_slack_channel'] ) ) {
				return $settings['notification_default_slack_channel'];
			}

			return '';
		}

		/**
		 * Checks if url shortening is enabled in the notifications settings.
		 *
		 * @return boolean
		 *
		 * @since 5.1.1
		 */
		public static function is_url_shortner_enabled(): bool {
			$settings = self::get_global_notifications_setting();

			if ( ! empty( $settings ) && isset( $settings['shorten_notification_urls'] ) && true === (bool) $settings['shorten_notification_urls'] ) {
				return true;
			}

			return \false;
		}

		/**
		 * There is a bug (not clear where exactly) which stores serialized PHP data with newlines, which later on after some (unknown variable) unserialization, causes issues probably due to fact that the newline symbol is changed - not 100% sure what is causing it, but I am getting it randomly now and then.
		 * Thats the reason for that method existance - when there is a need to get the global notifications settings, use this and not direct call.
		 *
		 * Basically - the template bodies are json encoded during storing and they need to be decoded before use.
		 *
		 * @return array
		 *
		 * @since 5.2.0
		 */
		public static function get_global_notifications_setting(): array {
			if ( null === self::$global_notifications_setting ) {
				self::$global_notifications_setting = Settings_Helper::get_option_value(
					self::NOTIFICATIONS_SETTINGS_NAME,
					array()
				);

				if ( isset( self::$global_notifications_setting['email_notifications_body'] ) ) {
					self::$global_notifications_setting['email_notifications_body'] = json_decode( self::$global_notifications_setting['email_notifications_body'] );
				}
				if ( isset( self::$global_notifications_setting['sms_notifications_body'] ) ) {
					self::$global_notifications_setting['sms_notifications_body'] = json_decode( self::$global_notifications_setting['sms_notifications_body'] );
				}
				if ( isset( self::$global_notifications_setting['slack_notifications_body'] ) ) {
					self::$global_notifications_setting['slack_notifications_body'] = json_decode( self::$global_notifications_setting['slack_notifications_body'] );
				}
			}

			return self::$global_notifications_setting;
		}

		/**
		 * Saves the global notifications settings. Check the comment on the method above for more details.
		 *
		 * @param array $settings - The array with all the settings to be saved.
		 *
		 * @return void
		 *
		 * @since 5.2.0
		 */
		public static function set_global_notifications_setting( array $settings ) {
			if ( isset( $settings['email_notifications_body'] ) ) {
				$settings['email_notifications_body'] = json_encode( $settings['email_notifications_body'] );
			}
			if ( isset( $settings['sms_notifications_body'] ) ) {
				$settings['sms_notifications_body'] = json_encode( $settings['sms_notifications_body'] );
			}
			if ( isset( $settings['slack_notifications_body'] ) ) {
				$settings['slack_notifications_body'] = json_encode( $settings['slack_notifications_body'] );
			}

			Settings_Helper::set_option_value(
				self::NOTIFICATIONS_SETTINGS_NAME,
				$settings
			);

			self::$global_notifications_setting = $settings;
		}

		/**
		 * Returns the notification titles used in the notifications UI
		 *
		 * @return array
		 *
		 * @since 5.2.1
		 */
		public static function get_notification_titles(): array {
			return array(
				// Suspicious activity Notifications.
				1002 => \esc_html__( 'Failed login for WordPress users', 'wp-security-audit-log' ),
				1003 => \esc_html__( 'Failed login for non existing WordPress users', 'wp-security-audit-log' ),
				// Plugin Changes Notifications.
				6004 => \esc_html__( 'WordPress was updated', 'wp-security-audit-log' ),
				// Plugin Changes Notifications.
				5000 => \esc_html__( 'New plugin is installed', 'wp-security-audit-log' ),
				5001 => \esc_html__( 'Installed plugin is activated', 'wp-security-audit-log' ),
				2051 => \esc_html__( 'Plugin file is modified', 'wp-security-audit-log' ),
				5002 => \esc_html__( 'Installed plugin is deactivated', 'wp-security-audit-log' ),
				5003 => \esc_html__( 'A plugin is uninstalled', 'wp-security-audit-log' ),
				5004 => \esc_html__( 'Installed plugin is upgraded', 'wp-security-audit-log' ),
				// Theme Changes Notifications.
				5005 => \esc_html__( 'New theme is installed', 'wp-security-audit-log' ),
				5006 => \esc_html__( 'Installed theme is activated', 'wp-security-audit-log' ),
				2046 => \esc_html__( 'Theme file is modified', 'wp-security-audit-log' ),
				5007 => \esc_html__( 'A theme is uninstalled', 'wp-security-audit-log' ),
				5031 => \esc_html__( 'Installed theme is updated', 'wp-security-audit-log' ),
				// User Activity.
				1000 => \esc_html__( 'User logs in', 'wp-security-audit-log' ),
				4003 => \esc_html__( 'User changed password', 'wp-security-audit-log' ),
				// User profile Changes.
				4005 => \esc_html__( 'User changed email address', 'wp-security-audit-log' ),
				4002 => \esc_html__( 'User\'s role has changed', 'wp-security-audit-log' ),
				4004 => \esc_html__( 'User changed the password of another user', 'wp-security-audit-log' ),
				4000 => \esc_html__( 'New user is created', 'wp-security-audit-log' ),
				// Content Changes.
				2001 => \esc_html__( 'New content is published', 'wp-security-audit-log' ),
				2065 => \esc_html__( 'Content in a post, page or custom post type is changed', 'wp-security-audit-log' ),
				2008 => \esc_html__( 'Post is permanently deleted from the trash', 'wp-security-audit-log' ),
				2012 => \esc_html__( 'Post is moved to the trash', 'wp-security-audit-log' ),
				2002 => \esc_html__( 'Anything but content in a post is changed', 'wp-security-audit-log' ),
				// Multisite.
				4008 => \esc_html__( 'User granted super admin', 'wp-security-audit-log' ),
				4009 => \esc_html__( 'User revoked super admin', 'wp-security-audit-log' ),
				4010 => \esc_html__( 'User added to site', 'wp-security-audit-log' ),
				4011 => \esc_html__( 'User removed from site', 'wp-security-audit-log' ),
				7000 => \esc_html__( 'Site changes', 'wp-security-audit-log' ),
				5008 => \esc_html__( 'Activated theme on network', 'wp-security-audit-log' ),
				5009 => \esc_html__( 'Deactivated theme from network', 'wp-security-audit-log' ),
				// Woo.
				9000 => \esc_html__( 'Any product change', 'wp-security-audit-log' ),
				9027 => \esc_html__( 'Any store settings change', 'wp-security-audit-log' ),
				9063 => \esc_html__( 'Any coupon code changes', 'wp-security-audit-log' ),
				9035 => \esc_html__( 'Any orders changes', 'wp-security-audit-log' ),
				// Severities.
				500  => \esc_html__( 'Critical event is generated', 'wp-security-audit-log' ),
			);

			// \esc_html__( 'Published content is modified', 'wp-security-audit-log' )
			// \esc_html__( 'Content is published', 'wp-security-audit-log' )
			// \esc_html__( 'First time user logs in', 'wp-security-audit-log' )

			// \esc_html__( 'Critical Alert is Generated', 'wp-security-audit-log' )
		}

		/**
		 * Checks and validates the data for the built-in notifications.
		 *
		 * @param array $post_array - The array with all the data provided.
		 * @param bool  $first_time - Set defaults.
		 *
		 * @return void
		 *
		 * @since 5.1.1
		 */
		private static function build_in_check_and_save( array $post_array, bool $first_time = false ) {
			if ( ! \current_user_can( 'manage_options' ) ) {

				if ( ! $first_time ) {
					\wp_die( \esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-security-audit-log' ) );
				}
			}

			$report_options = array();

			$no_defaults = false;
			if ( ! self::is_default_mail_set() && ! self::is_default_twilio_set() && ! self::is_default_slack_set() ) {
				$no_defaults = true;
			}

			$current_settings = Settings_Helper::get_option_value( self::BUILT_IN_NOTIFICATIONS_SETTINGS_NAME, array() );

			$report_options['daily_summary_notification'] = ( ( isset( $post_array['notification_daily_summary_notification'] ) ) ? filter_var( $post_array['notification_daily_summary_notification'], FILTER_VALIDATE_BOOLEAN ) : false );

			$current_daily_summary_notification = isset( $current_settings['daily_summary_notification'] ) ? $current_settings['daily_summary_notification'] : '';
			if ( $current_daily_summary_notification !== $report_options['daily_summary_notification'] ) {
				Alert_Manager::trigger_event(
					6310,
					array(
						'EventType' => ( $report_options['daily_summary_notification'] ) ? 'enabled' : 'disabled',
					)
				);
			}

			$report_options['daily_email_address'] = ( ( isset( $post_array['notification_daily_email_address'] ) ) ? \sanitize_text_field( \wp_unslash( $post_array['notification_daily_email_address'] ) ) : Email_Helper::get_default_email_to() );

			$current_daily_mail_address = isset( $current_settings['daily_email_address'] ) ? $current_settings['daily_email_address'] : '';
			if ( $report_options['daily_email_address'] !== $current_daily_mail_address ) {
				Alert_Manager::trigger_event(
					6311,
					array(
						'EventType'          => 'modified',
						'recipient'          => $report_options['daily_email_address'],
						'previous_recipient' => $current_daily_mail_address,
					)
				);
			}

			$report_options['daily_send_empty_summary_emails'] = ( ( isset( $post_array['notification_daily_send_empty_summary_emails'] ) ) ? filter_var( $post_array['notification_daily_send_empty_summary_emails'], FILTER_VALIDATE_BOOLEAN ) : false );

			// Weekly Settings start.

			$report_options['weekly_summary_notification'] = ( ( isset( $post_array['notification_weekly_summary_notification'] ) ) ? filter_var( $post_array['notification_weekly_summary_notification'], FILTER_VALIDATE_BOOLEAN ) : false );

			$current_weekly_summary_notification = isset( $current_settings['weekly_summary_notification'] ) ? $current_settings['weekly_summary_notification'] : '';
			if ( $current_weekly_summary_notification !== $report_options['weekly_summary_notification'] ) {
				Alert_Manager::trigger_event(
					6319,
					array(
						'EventType' => ( $report_options['weekly_summary_notification'] ) ? 'enabled' : 'disabled',
					)
				);
			}

			$report_options['weekly_email_address'] = ( ( isset( $post_array['notification_weekly_email_address'] ) ) ? \sanitize_text_field( \wp_unslash( $post_array['notification_weekly_email_address'] ) ) : Email_Helper::get_default_email_to() );

			$current_weekly_mail_address = isset( $current_settings['weekly_email_address'] ) ? $current_settings['weekly_email_address'] : '';
			if ( $report_options['weekly_email_address'] !== $current_weekly_mail_address ) {
				Alert_Manager::trigger_event(
					6328,
					array(
						'EventType'          => 'modified',
						'recipient'          => $report_options['weekly_email_address'],
						'previous_recipient' => $current_weekly_mail_address,
					)
				);
			}

			$report_options['weekly_send_empty_summary_emails'] = ( ( isset( $post_array['notification_weekly_send_empty_summary_emails'] ) ) ? filter_var( $post_array['notification_weekly_send_empty_summary_emails'], FILTER_VALIDATE_BOOLEAN ) : false );

			// Weekly Settings end.
			foreach ( self::$events_to_collect as $event_id ) {
				$report_options[ 'event_' . $event_id . '_notification' ] = ( ( isset( $post_array[ 'notification_event_' . $event_id . '_notification' ] ) ) ? filter_var( $post_array[ 'notification_event_' . $event_id . '_notification' ], FILTER_VALIDATE_BOOLEAN ) : false );

				if ( isset( $post_array[ 'notification_event_' . $event_id . '_failed_more_than' ] ) ) {
					$int = filter_var( $post_array[ 'notification_event_' . $event_id . '_failed_more_than' ], \FILTER_VALIDATE_INT );
					$report_options[ 'event_' . $event_id . '_failed_more_than' ] = ( $int && $int > 0 ) ? $int : 10;
				}

				$report_options[ 'event_' . $event_id . '_notification_custom_message' ] = ( ( isset( $post_array[ 'notification_event_' . $event_id . '_notification_custom_message' ] ) ) ? filter_var( $post_array[ 'notification_event_' . $event_id . '_notification_custom_message' ], FILTER_VALIDATE_BOOLEAN ) : false );

				if ( $report_options[ 'event_' . $event_id . '_notification_custom_message' ] ) {
					$report_options[ 'event_' . $event_id . '_notification_email_address' ] = ( ( isset( $post_array[ 'notification_event_' . $event_id . '_notification_email_address' ] ) ) ? \sanitize_text_field( \wp_unslash( $post_array[ 'notification_event_' . $event_id . '_notification_email_address' ] ) ) : '' );

					$report_options[ 'event_' . $event_id . '_notification_phone' ] = ( ( isset( $post_array[ 'notification_event_' . $event_id . '_notification_phone' ] ) ) ? \sanitize_text_field( \wp_unslash( $post_array[ 'notification_event_' . $event_id . '_notification_phone' ] ) ) : '' );

					$report_options[ 'event_' . $event_id . '_notification_slack' ] = ( ( isset( $post_array[ 'notification_event_' . $event_id . '_notification_slack' ] ) ) ? \sanitize_text_field( \wp_unslash( $post_array[ 'notification_event_' . $event_id . '_notification_slack' ] ) ) : '' );
				}

				if ( ( ! isset( $report_options[ 'event_' . $event_id . '_notification_email_address' ] ) || empty( $report_options[ 'event_' . $event_id . '_notification_email_address' ] ) ) &&
				( ! isset( $report_options[ 'event_' . $event_id . '_notification_phone' ] ) || empty( $report_options[ 'event_' . $event_id . '_notification_phone' ] ) ) &&
				( ! isset( $report_options[ 'event_' . $event_id . '_notification_slack' ] ) || empty( $report_options[ 'event_' . $event_id . '_notification_slack' ] ) ) &&
				( $no_defaults ) ) {
					unset( $report_options[ 'event_' . $event_id . '_notification' ] );
					unset( $report_options[ 'event_' . $event_id . '_notification_email_address' ] );
					unset( $report_options[ 'event_' . $event_id . '_notification_phone' ] );
					unset( $report_options[ 'event_' . $event_id . '_notification_slack' ] );
					unset( $report_options[ 'event_' . $event_id . '_notification_custom_message' ] );
					unset( $report_options[ 'event_' . $event_id . '_failed_more_than' ] );
				} elseif ( $report_options[ 'event_' . $event_id . '_notification' ] ) {
					$report_options['notification_ids'][] = $event_id;

					if ( isset( self::$additional_events_to_store[ $event_id ] ) ) {
						$report_options['notification_ids'] = \array_merge( $report_options['notification_ids'], self::$additional_events_to_store[ $event_id ] );
					}
				}
			}

			foreach ( self::$severities_to_collect as $severity ) {
				$report_options[ 'event_' . $severity . '_notification' ] = ( ( isset( $post_array[ 'notification_event_' . $severity . '_notification' ] ) ) ? filter_var( $post_array[ 'notification_event_' . $severity . '_notification' ], FILTER_VALIDATE_BOOLEAN ) : false );

				$report_options[ 'event_' . $severity . '_notification_custom_message' ] = ( ( isset( $post_array[ 'notification_event_' . $severity . '_notification_custom_message' ] ) ) ? filter_var( $post_array[ 'notification_event_' . $severity . '_notification_custom_message' ], FILTER_VALIDATE_BOOLEAN ) : false );

				if ( $report_options[ 'event_' . $severity . '_notification_custom_message' ] ) {
					$report_options[ 'event_' . $severity . '_notification_email_address' ] = ( ( isset( $post_array[ 'notification_event_' . $severity . '_notification_email_address' ] ) ) ? \sanitize_text_field( \wp_unslash( $post_array[ 'notification_event_' . $severity . '_notification_email_address' ] ) ) : '' );

					$report_options[ 'event_' . $severity . '_notification_phone' ] = ( ( isset( $post_array[ 'notification_event_' . $severity . '_notification_phone' ] ) ) ? \sanitize_text_field( \wp_unslash( $post_array[ 'notification_event_' . $severity . '_notification_phone' ] ) ) : '' );

					$report_options[ 'event_' . $severity . '_notification_slack' ] = ( ( isset( $post_array[ 'notification_event_' . $severity . '_notification_slack' ] ) ) ? \sanitize_text_field( \wp_unslash( $post_array[ 'notification_event_' . $severity . '_notification_slack' ] ) ) : '' );
				}

				if ( ( ! isset( $report_options[ 'event_' . $severity . '_notification_email_address' ] ) || empty( $report_options[ 'event_' . $severity . '_notification_email_address' ] ) ) &&
				( ! isset( $report_options[ 'event_' . $severity . '_notification_phone' ] ) || empty( $report_options[ 'event_' . $severity . '_notification_phone' ] ) ) &&
				( ! isset( $report_options[ 'event_' . $severity . '_notification_slack' ] ) || empty( $report_options[ 'event_' . $severity . '_notification_slack' ] ) ) &&
				( $no_defaults ) ) {
					unset( $report_options[ 'event_' . $severity . '_notification' ] );
					unset( $report_options[ 'event_' . $severity . '_notification_email_address' ] );
					unset( $report_options[ 'event_' . $severity . '_notification_phone' ] );
					unset( $report_options[ 'event_' . $severity . '_notification_slack' ] );
					unset( $report_options[ 'event_' . $severity . '_notification_custom_message' ] );
				} elseif ( $report_options[ 'event_' . $severity . '_notification' ] ) {
					$report_options['notification_severities'][] = $severity;
				}
			}
			self::compare_and_fire_events( $report_options );
			if ( empty( $report_options ) ) {
				Settings_Helper::delete_option_value( self::BUILT_IN_NOTIFICATIONS_SETTINGS_NAME );
			} else {
				Settings_Helper::set_option_value( self::BUILT_IN_NOTIFICATIONS_SETTINGS_NAME, $report_options );
			}
		}

		/**
		 * Compares the settings before storing them in against the current settings and fires events if there are changes.
		 *
		 * @param array $settings_to_store - Array with the settings to store.
		 *
		 * @return void
		 *
		 * @since 5.2.1
		 */
		private static function compare_and_fire_events( array $settings_to_store ) {
			$current_settings = Settings_Helper::get_option_value( self::BUILT_IN_NOTIFICATIONS_SETTINGS_NAME, array() );

			if ( empty( $settings_to_store ) && ! empty( $current_settings ) ) {
				foreach ( self::$events_to_collect as $event_id ) {

					if ( isset( $current_settings[ 'event_' . $event_id . '_notification' ] ) &&
					true === $current_settings[ 'event_' . $event_id . '_notification' ] ) {
						Alert_Manager::trigger_event(
							6312,
							array(
								'notification_name' => self::get_notification_titles()[ $event_id ],
								'EventType'         => 'disabled',
							)
						);
					}
				}
			}

			if ( ! empty( $settings_to_store ) && ! empty( $current_settings ) ) {
				$events_to_check = \array_merge( self::$events_to_collect, self::$severities_to_collect );
				foreach ( $events_to_check as $event_id ) {

					if ( isset( $settings_to_store[ 'event_' . $event_id . '_notification' ] ) && isset( $current_settings[ 'event_' . $event_id . '_notification' ] ) &&
					$current_settings[ 'event_' . $event_id . '_notification' ] !== $settings_to_store[ 'event_' . $event_id . '_notification' ] ) {
						$type = 'enabled';
						if ( false === $settings_to_store[ 'event_' . $event_id . '_notification' ] ) {
							$type = 'disabled';
						}
						Alert_Manager::trigger_event(
							6312,
							array(
								'notification_name' => self::get_notification_titles()[ $event_id ],
								'EventType'         => $type,
							)
						);
					}

					$store_custom_address   = ( isset( $settings_to_store[ 'event_' . $event_id . '_notification_custom_message' ] ) ? $settings_to_store[ 'event_' . $event_id . '_notification_custom_message' ] : false );
					$current_custom_address = ( isset( $current_settings[ 'event_' . $event_id . '_notification_custom_message' ] ) ? $current_settings[ 'event_' . $event_id . '_notification_custom_message' ] : false );

					$default_recipient = \esc_html__( 'Use default', 'wp-security-audit-log' );

					$store_email   = ( isset( $settings_to_store[ 'event_' . $event_id . '_notification_email_address' ] ) ? $settings_to_store[ 'event_' . $event_id . '_notification_email_address' ] : $default_recipient );
					$current_email = ( isset( $current_settings[ 'event_' . $event_id . '_notification_email_address' ] ) ? $current_settings[ 'event_' . $event_id . '_notification_email_address' ] : $default_recipient );

					$store_phone   = ( isset( $settings_to_store[ 'event_' . $event_id . '_notification_phone' ] ) ? $settings_to_store[ 'event_' . $event_id . '_notification_phone' ] : $default_recipient );
					$current_phone = ( isset( $current_settings[ 'event_' . $event_id . '_notification_phone' ] ) ? $current_settings[ 'event_' . $event_id . '_notification_phone' ] : $default_recipient );

					$store_slack   = ( isset( $settings_to_store[ 'event_' . $event_id . '_notification_slack' ] ) ? $settings_to_store[ 'event_' . $event_id . '_notification_slack' ] : $default_recipient );
					$current_slack = ( isset( $current_settings[ 'event_' . $event_id . '_notification_slack' ] ) ? $current_settings[ 'event_' . $event_id . '_notification_slack' ] : $default_recipient );

					if ( $store_custom_address !== $current_custom_address || $store_email !== $current_email || $store_phone !== $current_phone || $store_slack !== $current_slack ) {
						Alert_Manager::trigger_event(
							6313,
							array(
								'notification_name'  => self::get_notification_titles()[ $event_id ],
								'recipient'          => Settings_Helper::create_recipient_string( $store_email, $store_phone ),
								'previous_recipient' => Settings_Helper::create_recipient_string( $current_email, $current_phone ),
								'EventType'          => 'modified',
							)
						);
					}
				}
			}
		}

		/**
		 * Checks and validates the data for the settings.
		 *
		 * @param array $post_array - The array with all the data provided.
		 *
		 * @return void
		 *
		 * @since 5.1.1
		 */
		private static function settings_check_and_save( array $post_array ) {
			if ( ! \current_user_can( 'manage_options' ) ) {
				\wp_die( \esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-security-audit-log' ) );
			}

			$current_settings = Settings_Helper::get_option_value( self::NOTIFICATIONS_SETTINGS_NAME, array() );

			$options = array();

			if ( isset( $post_array['twilio_notification_account_sid'] ) && ! empty( $post_array['twilio_notification_account_sid'] ) &&
			isset( $post_array['twilio_notification_auth_token'] ) && ! empty( $post_array['twilio_notification_auth_token'] ) &&
			isset( $post_array['twilio_notification_phone_number'] ) && ! empty( $post_array['twilio_notification_phone_number'] ) ) {
				$twilio_valid =
				Twilio_API::check_credentials(
					(string) \sanitize_text_field( \wp_unslash( $post_array['twilio_notification_account_sid'] ) ),
					(string) \sanitize_text_field( \wp_unslash( $post_array['twilio_notification_auth_token'] ) ),
					(string) \sanitize_text_field( \wp_unslash( $post_array['twilio_notification_phone_number'] ) )
				);
				if ( $twilio_valid ) {
					$options['twilio_notification_account_sid']  = \sanitize_text_field( \wp_unslash( $post_array['twilio_notification_account_sid'] ) );
					$options['twilio_notification_auth_token']   = \sanitize_text_field( \wp_unslash( $post_array['twilio_notification_auth_token'] ) );
					$options['twilio_notification_phone_number'] = \sanitize_text_field( \wp_unslash( $post_array['twilio_notification_phone_number'] ) );
				}
			}

			if (
			isset( $post_array['slack_notification_auth_token'] ) && ! empty( $post_array['slack_notification_auth_token'] ) ) {
				$slack_valid =
				Slack_API::verify_slack_token(
					(string) \sanitize_text_field( \wp_unslash( $post_array['slack_notification_auth_token'] ) ),
				);
				if ( $slack_valid ) {
					$options['slack_notification_auth_token'] = \sanitize_text_field( \wp_unslash( $post_array['slack_notification_auth_token'] ) );
				}
			}

			if ( isset( $post_array['notification_default_email_address'] ) ) {
				$options['notification_default_email_address'] = ( ( isset( $post_array['notification_default_email_address'] ) ) ? \sanitize_text_field( \wp_unslash( $post_array['notification_default_email_address'] ) ) : '' );

			}

			if ( isset( $post_array['notification_default_phone'] ) ) {
				$options['notification_default_phone'] = ( ( isset( $post_array['notification_default_phone'] ) ) ? \sanitize_text_field( \wp_unslash( $post_array['notification_default_phone'] ) ) : '' );
			}

			if ( isset( $post_array['notification_default_slack_channel'] ) ) {
				$options['notification_default_slack_channel'] = ( ( isset( $post_array['notification_default_slack_channel'] ) ) ? \sanitize_text_field( \wp_unslash( $post_array['notification_default_slack_channel'] ) ) : '' );
			}

			if ( isset( $post_array['email_notifications_subject'] ) ) {
				$options['email_notifications_subject'] = ( ( isset( $post_array['email_notifications_subject'] ) ) ? \sanitize_text_field( \wp_unslash( $post_array['email_notifications_subject'] ) ) : '' );
			}

			$options['email_notifications_body'] = '';
			if ( isset( $post_array['email_notifications_body'] ) ) {
				$options['email_notifications_body'] = ( ( isset( $post_array['email_notifications_body'] ) ) ? \wpautop( \wp_unslash( $post_array['email_notifications_body'] ) ) : '' );

				if ( ! isset( $current_settings['email_notifications_body'] ) ) {
					$current_settings['email_notifications_body'] = json_encode( stripslashes( Notification_Helper::get_default_email_body() ) );
				}

				if ( json_encode( stripslashes( $options['email_notifications_body'] ) ) !== $current_settings['email_notifications_body'] || $options['email_notifications_subject'] !== $current_settings['email_notifications_subject'] ) {
					Alert_Manager::trigger_event(
						6318,
						array(
							'EventType'     => 'modified',
							'template_name' => 'Email',
						)
					);
				}
			}

			$options['sms_notifications_body'] = '';
			if ( isset( $post_array['sms_notifications_body'] ) ) {
				$options['sms_notifications_body'] = ( ( isset( $post_array['sms_notifications_body'] ) ) ? ( \wp_unslash( $post_array['sms_notifications_body'] ) ) : '' );

				if ( ! isset( $current_settings['sms_notifications_body'] ) ) {
					$current_settings['sms_notifications_body'] = json_encode( stripslashes( Notification_Helper::get_default_sms_body() ) );
				}

				if ( json_encode( stripslashes( $options['sms_notifications_body'] ) ) !== $current_settings['sms_notifications_body'] ) {
					Alert_Manager::trigger_event(
						6318,
						array(
							'EventType'     => 'modified',
							'template_name' => 'SMS',
						)
					);
				}
			}

			$options['slack_notifications_body'] = '';
			if ( isset( $post_array['slack_notifications_body'] ) ) {
				$options['slack_notifications_body'] = ( ( isset( $post_array['slack_notifications_body'] ) ) ? ( \wp_unslash( $post_array['slack_notifications_body'] ) ) : '' );

				if ( ! isset( $current_settings['slack_notifications_body'] ) ) {
					$current_settings['slack_notifications_body'] = json_encode( stripslashes( Notification_Helper::get_default_slack_body() ) );
				}

				if ( json_encode( stripslashes( $options['slack_notifications_body'] ) ) !== $current_settings['slack_notifications_body'] ) {
					Alert_Manager::trigger_event(
						6318,
						array(
							'EventType'     => 'modified',
							'template_name' => 'Slack',
						)
					);
				}
			}

			$options['shorten_notification_urls'] = ( ( isset( $post_array['shorten_notification_urls'] ) ) ? filter_var( $post_array['shorten_notification_urls'], FILTER_VALIDATE_BOOLEAN ) : false );

			if ( isset( $post_array['notification_bitly_shorten_key'] ) ) {
				$options['notification_bitly_shorten_key'] = ( ( isset( $post_array['notification_bitly_shorten_key'] ) ) ? \sanitize_text_field( \wp_unslash( $post_array['notification_bitly_shorten_key'] ) ) : '' );
			}

			$default = ( 'free' === \WpSecurityAuditLog::get_plugin_version() ) ? true : false;

			// Summarized emails.
			$options['notification_summary_user_logins'] = ( ( isset( $post_array['notification_summary_user_logins'] ) ) ? filter_var( $post_array['notification_summary_user_logins'], FILTER_VALIDATE_BOOLEAN ) : $default );

			$options['notification_summary_failed_logins'] = ( ( isset( $post_array['notification_summary_failed_logins'] ) ) ? filter_var( $post_array['notification_summary_failed_logins'], FILTER_VALIDATE_BOOLEAN ) : $default );

			$options['notification_wrong_password'] = ( ( isset( $post_array['notification_wrong_password'] ) ) ? filter_var( $post_array['notification_wrong_password'], FILTER_VALIDATE_BOOLEAN ) : $default );

			$options['notification_summary_wrong_username'] = ( ( isset( $post_array['notification_summary_wrong_username'] ) ) ? filter_var( $post_array['notification_summary_wrong_username'], FILTER_VALIDATE_BOOLEAN ) : $default );

			if ( $options['notification_summary_failed_logins'] && ! $options['notification_wrong_password'] && ! $options['notification_summary_wrong_username'] ) {
				$options['notification_summary_failed_logins'] = $default;
			}

			$options['notification_summary_password_changes'] = ( ( isset( $post_array['notification_summary_password_changes'] ) ) ? filter_var( $post_array['notification_summary_password_changes'], FILTER_VALIDATE_BOOLEAN ) : $default );

			$options['notification_summary_password_user_change_own_password'] = ( ( isset( $post_array['notification_summary_password_user_change_own_password'] ) ) ? filter_var( $post_array['notification_summary_password_user_change_own_password'], FILTER_VALIDATE_BOOLEAN ) : $default );

			$options['notification_summary_password_user_change_other_password'] = ( ( isset( $post_array['notification_summary_password_user_change_other_password'] ) ) ? filter_var( $post_array['notification_summary_password_user_change_other_password'], FILTER_VALIDATE_BOOLEAN ) : $default );

			if ( $options['notification_summary_password_changes'] && ! $options['notification_summary_password_user_change_own_password'] && ! $options['notification_summary_password_user_change_other_password'] ) {
				$options['notification_summary_password_changes'] = $default;
			}

			$options['notification_summary_plugins_activity'] = ( ( isset( $post_array['notification_summary_plugins_activity'] ) ) ? filter_var( $post_array['notification_summary_plugins_activity'], FILTER_VALIDATE_BOOLEAN ) : $default );

			$options['notification_summary_system_activity'] = ( ( isset( $post_array['notification_summary_system_activity'] ) ) ? filter_var( $post_array['notification_summary_system_activity'], FILTER_VALIDATE_BOOLEAN ) : $default );

			$options['notification_summary_content_changes'] = ( ( isset( $post_array['notification_summary_content_changes'] ) ) ? filter_var( $post_array['notification_summary_content_changes'], FILTER_VALIDATE_BOOLEAN ) : $default );

			$options['notification_summary_published_posts'] = ( ( isset( $post_array['notification_summary_published_posts'] ) ) ? filter_var( $post_array['notification_summary_published_posts'], FILTER_VALIDATE_BOOLEAN ) : $default );

			$options['notification_summary_deleted_posts'] = ( ( isset( $post_array['notification_summary_deleted_posts'] ) ) ? filter_var( $post_array['notification_summary_deleted_posts'], FILTER_VALIDATE_BOOLEAN ) : $default );

			$options['notification_summary_changed_posts'] = ( ( isset( $post_array['notification_summary_changed_posts'] ) ) ? filter_var( $post_array['notification_summary_changed_posts'], FILTER_VALIDATE_BOOLEAN ) : $default );

			$options['notification_summary_status_changed_posts'] = ( ( isset( $post_array['notification_summary_status_changed_posts'] ) ) ? filter_var( $post_array['notification_summary_status_changed_posts'], FILTER_VALIDATE_BOOLEAN ) : $default );

			if ( $options['notification_summary_content_changes'] && ! $options['notification_summary_published_posts'] && ! $options['notification_summary_deleted_posts'] && ! $options['notification_summary_deleted_posts'] && ! $options['notification_summary_status_changed_posts'] ) {
				$options['notification_summary_content_changes'] = false;
			}

			$options['notification_summary_multisite_individual_site'] = ( ( isset( $post_array['notification_summary_multisite_individual_site'] ) ) ? filter_var( $post_array['notification_summary_multisite_individual_site'], FILTER_VALIDATE_BOOLEAN ) : false );

			$options['notification_events_included'] = ( ( isset( $post_array['notification_events_included'] ) ) ? filter_var( $post_array['notification_events_included'], FILTER_VALIDATE_BOOLEAN ) : false );

			if ( ! $options['notification_events_included'] ) {
				$options['notification_summary_number_of_events_included'] = null;
			} else {
				$options['notification_summary_number_of_events_included'] = ( ( isset( $post_array['notification_summary_number_of_events_included'] ) ) ? \esc_attr( \absint( $post_array['notification_summary_number_of_events_included'] ) ) : 10 );

			}

			if ( WP_Helper::is_multisite() && ! $options['notification_summary_multisite_individual_site'] ) {
				$options['notification_summary_content_changes'] = false;
			}

			if ( empty( $options ) ) {
				Settings_Helper::delete_option_value( self::NOTIFICATIONS_SETTINGS_NAME );
			} else {
				self::set_global_notifications_setting( $options );
			}
		}

		/**
		 * Checks and validates the data for the custom notifications.
		 *
		 * @param array $post_array - The array with all the data provided.
		 *
		 * @return array
		 *
		 * @since 5.2.1
		 */
		private static function custom_notifications_check_and_save( array $post_array ) {
			if ( ! \current_user_can( 'manage_options' ) ) {
				\wp_die( \esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-security-audit-log' ) );
			}

			$report_options = array();

			$no_defaults = false;
			if ( ! self::is_default_mail_set() && ! self::is_default_twilio_set() && ! self::is_default_slack_set() ) {
				$no_defaults = true;
			}

			if ( isset( $post_array['custom_notifications_id'] ) && 0 < \absint( (int) $post_array['custom_notifications_id'] ) ) {
				$report_options['id'] = \absint( (int) $post_array['custom_notifications_id'] );
			}

			if ( isset( $post_array['store_global_mail'] ) && 'yes' === $post_array['store_global_mail'] ) {
				$notification_mail = ( ( isset( $post_array['custom_notification_email'] ) ) ? \sanitize_text_field( \wp_unslash( $post_array['custom_notification_email'] ) ) : '' );

				if ( ! empty( $notification_mail ) ) {

					$t_options = Settings_Helper::get_option_value( self::NOTIFICATIONS_SETTINGS_NAME, array() );

					$t_options['notification_default_email_address'] = $notification_mail;

					self::set_global_notifications_setting( $t_options );

				}
			}

			$report_options['notification_status'] = ( ( isset( $post_array['custom_notification_enabled'] ) ) ? filter_var( $post_array['custom_notification_enabled'], FILTER_VALIDATE_BOOLEAN ) : false );

			$report_options['notification_title'] = ( ( isset( $post_array['custom_notification_title'] ) ) ? \sanitize_text_field( \wp_unslash( $post_array['custom_notification_title'] ) ) : \esc_html__( 'Custom notification - no name', 'wp-security-audit-log' ) . ' ' . \substr( \str_shuffle( '0123456789' ), 0, 5 ) );

			$report_options['notification_email'] = ( ( isset( $post_array['custom_notification_email'] ) ) ? \sanitize_text_field( \wp_unslash( $post_array['custom_notification_email'] ) ) : '' );

			$report_options['notification_email_bcc'] = ( ( isset( $post_array['custom_notification_email_bcc'] ) ) ? \sanitize_text_field( \wp_unslash( $post_array['custom_notification_email_bcc'] ) ) : '' );

			$report_options['notification_email_user'] = ( ( isset( $post_array['custom_notification_email_user'] ) ) ? filter_var( $post_array['custom_notification_email_user'], FILTER_VALIDATE_BOOLEAN ) : false );

			$report_options['notification_phone'] = ( ( isset( $post_array['custom_notification_phone'] ) ) ? \sanitize_text_field( \wp_unslash( $post_array['custom_notification_phone'] ) ) : '' );

			$report_options['notification_slack'] = ( ( isset( $post_array['custom_notification_slack'] ) ) ? \sanitize_text_field( \wp_unslash( $post_array['custom_notification_slack'] ) ) : '' );

			$report_options['notification_username'] = \wp_get_current_user()->user_login;
			$report_options['notification_user_id']  = \get_current_user_id();

			$report_options['notification_template'] = array();

			$report_options['notification_template']['custom_notification_template_enabled'] = ( ( isset( $post_array['custom_notification_template_enabled'] ) ) ? filter_var( $post_array['custom_notification_template_enabled'], FILTER_VALIDATE_BOOLEAN ) : false );

			if ( isset( $post_array['email_custom_notifications_subject'] ) ) {
				$report_options['notification_template']['email_custom_notifications_subject'] = ( ( isset( $post_array['email_custom_notifications_subject'] ) ) ? \sanitize_text_field( \wp_unslash( $post_array['email_custom_notifications_subject'] ) ) : '' );
			}

			if ( isset( $post_array['email_custom_notifications_body'] ) ) {
				$report_options['notification_template']['email_custom_notifications_body'] = ( ( isset( $post_array['email_custom_notifications_body'] ) ) ? \wpautop( \wp_unslash( $post_array['email_custom_notifications_body'] ) ) : '' );
			}

			$report_options['notification_sms_template'] = array();

			$report_options['notification_sms_template']['custom_notification_sms_template_enabled'] = ( ( isset( $post_array['custom_notification_sms_template_enabled'] ) ) ? filter_var( $post_array['custom_notification_sms_template_enabled'], FILTER_VALIDATE_BOOLEAN ) : false );

			if ( isset( $post_array['sms_custom_notifications_body'] ) ) {
				$report_options['notification_sms_template']['sms_custom_notifications_body'] = ( ( isset( $post_array['sms_custom_notifications_body'] ) ) ? ( \wp_unslash( $post_array['sms_custom_notifications_body'] ) ) : '' );
			}

			$report_options['notification_slack_template'] = array();

			$report_options['notification_slack_template']['custom_notification_slack_template_enabled'] = ( ( isset( $post_array['custom_notification_slack_template_enabled'] ) ) ? filter_var( $post_array['custom_notification_slack_template_enabled'], FILTER_VALIDATE_BOOLEAN ) : false );

			if ( isset( $post_array['slack_custom_notifications_body'] ) ) {
				$report_options['notification_slack_template']['slack_custom_notifications_body'] = ( ( isset( $post_array['slack_custom_notifications_body'] ) ) ? ( \wp_unslash( $post_array['slack_custom_notifications_body'] ) ) : '' );
			}

			$report_options['notification_query'] = '';
			if ( isset( $post_array['custom_notification_query'] ) ) {
				$report_options['notification_query'] = ( ( isset( $post_array['custom_notification_query'] ) ) ? \sanitize_text_field( \wp_unslash( $post_array['custom_notification_query'] ) ) : '' );

				// if ( ! empty( $post_array['custom_notification_query'] ) ) {
				// $report_options['notification_query_sql'] = Query_Builder_Parser::parse( $report_options['notification_query'] );
				// }
			}

			$report_options['notification_query_sql'] = ( ( isset( $post_array['custom_notification_query_sql'] ) ) ? ( \esc_sql( self::obscure_query( $post_array['custom_notification_query_sql'] ) ) ) : '' );

			if ( ! empty( $report_options['notification_query_sql'] ) ) {
				$report_options['notification_query_sql'] = Notification_Helper::normalize_query( $report_options['notification_query_sql'] );
			}

			$report_options['notification_view_state'] = 1;

			$report_options['notification_settings'] = array();

			$report_options['notification_settings']['custom_notification_send_to_default_email'] = ( ( isset( $post_array['custom_notification_send_to_default_email'] ) ) ? filter_var( $post_array['custom_notification_send_to_default_email'], FILTER_VALIDATE_BOOLEAN ) : false );

			$report_options['notification_settings']['custom_notification_send_to_default_slack'] = ( ( isset( $post_array['custom_notification_send_to_default_slack'] ) ) ? filter_var( $post_array['custom_notification_send_to_default_slack'], FILTER_VALIDATE_BOOLEAN ) : false );

			$report_options['notification_settings']['custom_notification_send_to_default_phone'] = ( ( isset( $post_array['custom_notification_send_to_default_phone'] ) ) ? filter_var( $post_array['custom_notification_send_to_default_phone'], FILTER_VALIDATE_BOOLEAN ) : false );

			$last_id = Custom_Notifications_Entity::save( $report_options );

			$query_args_view_data = array(
				'page'     => self::get_safe_view_name(),
				'action'   => 'edit',
				Custom_Notifications_Entity::get_table_name() . '[0]' => absint( $last_id ),
				'_wpnonce' => \wp_create_nonce( 'bulk-custom-notifications' ),
			);

			$admin_page_url = \network_admin_url( 'admin.php' );
			$view_data_link = \add_query_arg( $query_args_view_data, $admin_page_url );

			return array( 'redirect' => $view_data_link . '#wsal-options-tab-custom-notification-edit' );
		}

		/**
		 * Changes the generated query string.
		 *
		 * @param string $query - The query string to be changes.
		 *
		 * @return string
		 *
		 * @since 5.2.1
		 */
		public static function obscure_query( string $query ): string {
			// Replace all with leading space first.
			$query = str_replace( ' \'', ' ' . Notification_Helper::VALUE_QUERY_PREFIX, $query );
			// Then replace all with trailing space.
			$query = str_replace( '\' ', Notification_Helper::VALUE_QUERY_PREFIX . ' ', $query );
			// And finally replace everything else.
			$query = str_replace( '\'', Notification_Helper::VALUE_QUERY_PREFIX, $query );

			return $query;
		}
	}
}
