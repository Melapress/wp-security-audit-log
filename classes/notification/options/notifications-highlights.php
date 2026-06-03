<?php
/**
 * Build-in notification settings of the plugin
 *
 * @package wsal
 *
 * @since 5.1.1
 */

use WSAL\Helpers\WP_Helper;
use WSAL\Views\Notifications;
use WSAL\Helpers\Settings_Helper;
use WSAL\Helpers\Settings\Settings_Builder;
use WSAL\Extensions\Helpers\Notification_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$built_in_notifications = (array) Settings_Helper::get_option_value( Notifications::BUILT_IN_NOTIFICATIONS_SETTINGS_NAME, array() );

$defaults = '';
if ( Notifications::is_default_mail_set() ) {
	$current_default_mail = Notifications::get_default_mail();
	$defaults            .= esc_html__( ' Currently default email is set to: ', 'wp-security-audit-log' ) . $current_default_mail;
} else {
	$defaults .= Notification_Helper::no_default_email_is_set();
}

if ( Notifications::is_default_twilio_set() ) {
	$current_default_twilio = Notifications::get_default_twilio();
	$defaults              .= esc_html__( ' Currently default phone is set to: ', 'wp-security-audit-log' ) . $current_default_twilio;
} else {
	$defaults .= Notification_Helper::no_default_phone_is_set();
}

if ( Notifications::is_default_slack_set() ) {
	$current_default_twilio = Notifications::get_default_slack();
	$defaults              .= esc_html__( ' Currently default slack channel is set to: ', 'wp-security-audit-log' ) . $current_default_twilio;
} else {
	$defaults .= Notification_Helper::no_default_slack_is_set();
}

$notifications = array();
foreach ( $built_in_notifications as $name => $value ) {
	$notifications[ 'notification_' . $name ] = $value;
}
unset( $built_in_notifications );

Settings_Builder::set_current_options( array_merge( $notifications, Notifications::get_global_notifications_setting() ) );

Settings_Builder::build_option(
	array(
		'title'         => esc_html__( 'Activity log highlights', 'wp-security-audit-log' ),
		'id'            => 'built-in-notification-settings-tab',
		'type'          => 'tab-title',
		'settings_name' => Notifications::BUILT_IN_NOTIFICATIONS_SETTINGS_NAME,
	)
);


$settings_url = \add_query_arg(
	array(
		'page' => Notifications::get_safe_view_name(),
	),
	\network_admin_url( 'admin.php' )
) . '#wsal-options-tab-notification-settings';


$general_settings_content = '<p>' . \esc_html__( 'Use this section to configure a daily or weekly activity log summary, or both. This gives you a regular overview of key events.', 'wp-security-audit-log' ) . '</p>';


Settings_Builder::build_option(
	array(
		'id'      => 'general-settings-tab',
		'type'    => 'html',
		'content' => $general_settings_content,
	)
);

Settings_Builder::build_option(
	array(
		'title'         => esc_html__( 'Daily Activity log highlights email', 'wp-security-audit-log' ),
		'id'            => 'daily-summary-notification-settings',
		'type'          => 'header',
		'settings_name' => Notifications::BUILT_IN_NOTIFICATIONS_SETTINGS_NAME,
	)
);

Settings_Builder::build_option(
	array(
		'name'          => esc_html__( 'Send me a summary of what happens every day. ', 'wp-security-audit-log' ),
		'id'            => 'notification_daily_summary_notification',
		'toggle'        => '#notification_daily_email_address-item, #notification_daily_send_now_ajax-item, #notification_daily_send_empty_summary_emails-item',
		'type'          => 'checkbox',
		'default'       => false,
		'settings_name' => Notifications::BUILT_IN_NOTIFICATIONS_SETTINGS_NAME,
	)
);

Settings_Builder::build_option(
	Notification_Helper::email_settings_array( 'notification_daily_email_address', Notifications::BUILT_IN_NOTIFICATIONS_SETTINGS_NAME )
);

Settings_Builder::build_option(
	array(
		'name'          => esc_html__(
			'Send empty summary emails ',
			'wp-security-audit-log'
		),
		'id'            => 'notification_daily_send_empty_summary_emails',
		'type'          => 'checkbox',
		'default'       => false,
		'hint'          => esc_html__( 'Do you want to receive an email even if there are no event IDs that match the criteria for the periodic reports? ', 'wp-security-audit-log' ),
		'settings_name' => Notifications::BUILT_IN_NOTIFICATIONS_SETTINGS_NAME,
	)
);

Settings_Builder::build_option(
	array(
		'id'            => 'send_daily_notification_nonce',
		'type'          => 'hidden',
		'default'       => \wp_create_nonce( Notifications::BUILT_IN_SEND_NOW_NONCE_NAME ),
		'settings_name' => Notifications::BUILT_IN_NOTIFICATIONS_SETTINGS_NAME,
	)
);

if ( isset( $notifications['notification_daily_email_address'] ) && ! empty( $notifications['notification_daily_email_address'] ) ) {

	Settings_Builder::build_option(
		array(
			'add_label'     => true,
			'id'            => 'notification_daily_send_now_ajax',
			'type'          => 'button',
			'default'       => esc_html__( 'Send test report now (one day data)', 'wp-security-audit-log' ),
			'settings_name' => Notifications::BUILT_IN_NOTIFICATIONS_SETTINGS_NAME,
		)
	);
}
// ---- WEEKLY summary notifications

Settings_Builder::build_option(
	array(
		'title'         => esc_html__( 'Weekly Activity log highlights email', 'wp-security-audit-log' ),
		'id'            => 'weekly-summary-notification-settings',
		'type'          => 'header',
		'settings_name' => Notifications::BUILT_IN_NOTIFICATIONS_SETTINGS_NAME,
	)
);

Settings_Builder::build_option(
	array(
		'name'          => esc_html__( 'Send me a summary of what happens every week. ', 'wp-security-audit-log' ),
		'id'            => 'notification_weekly_summary_notification',
		'toggle'        => '#notification_weekly_email_address-item, #notification_weekly_send_now_ajax-item, #notification_weekly_send_empty_summary_emails-item',
		'type'          => 'checkbox',
		'default'       => true,
		'settings_name' => Notifications::BUILT_IN_NOTIFICATIONS_SETTINGS_NAME,
	)
);

Settings_Builder::build_option(
	Notification_Helper::email_settings_array( 'notification_weekly_email_address', Notifications::BUILT_IN_NOTIFICATIONS_SETTINGS_NAME )
);

Settings_Builder::build_option(
	array(
		'name'          => esc_html__(
			'Send empty summary emails ',
			'wp-security-audit-log'
		),
		'id'            => 'notification_weekly_send_empty_summary_emails',
		'type'          => 'checkbox',
		'default'       => false,
		'hint'          => esc_html__( 'Do you want to receive an email even if there are no event IDs that match the criteria for the periodic reports? ', 'wp-security-audit-log' ),
		'settings_name' => Notifications::BUILT_IN_NOTIFICATIONS_SETTINGS_NAME,
	)
);

if ( isset( $notifications['notification_weekly_email_address'] ) && ! empty( $notifications['notification_weekly_email_address'] ) ) {

	Settings_Builder::build_option(
		array(
			'add_label'     => true,
			'id'            => 'notification_weekly_send_now_ajax',
			'type'          => 'button',
			'default'       => esc_html__( 'Send test report now (one day data)', 'wp-security-audit-log' ),
			'settings_name' => Notifications::BUILT_IN_NOTIFICATIONS_SETTINGS_NAME,
		)
	);
}

// @free:start
if ( \wsal_freemius()->is_free_plan() ) {
	$custom_notifications_lock = '<span class="wsal-custom-notifications-lock" aria-hidden="true"></span>';

	$custom_notifications_unlock_url = \esc_url( 'https://melapress.com/wordpress-activity-log/pricing/?utm_source=plugin&utm_medium=wsal&utm_campaign=notifications-custom-activity-log-notifications' );

	Settings_Builder::build_option(
		array(
			'title'         => \esc_html__( 'Custom activity log notifications', 'wp-security-audit-log' ) . ' ' . $custom_notifications_lock,
			'id'            => 'custom-notifications-unlock-settings',
			'type'          => 'header',
			'settings_name' => Notifications::BUILT_IN_NOTIFICATIONS_SETTINGS_NAME,
		)
	);

	Settings_Builder::build_option(
		array(
			'id'      => 'custom-notifications-unlock',
			'type'    => 'html',
			'content' => '<div id="custom-notifications-unlock-item" class="option-item wsal-custom-notifications-unlock">' .
				'<p>' . \esc_html__( 'Create custom report schedules, get instant alerts via email, SMS, and Slack, create advanced notification rules, and more.', 'wp-security-audit-log' ) . '</p>' .
				'<p><a class="wsal-primary-button button" href="' . $custom_notifications_unlock_url . '" target="_blank" rel="noopener noreferrer">' . \esc_html__( 'Unlock Advanced Notifications', 'wp-security-audit-log' ) . '</a></p>' .
			'</div>',
		)
	);
}
// @free:end

// Sections include.

?>

<input type="hidden" name="<?php echo Notifications::NOTIFICATIONS_SETTINGS_NAME;  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>[]" value="0" />
