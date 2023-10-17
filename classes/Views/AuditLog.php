<?php
/**
 * Audit Log View Class
 *
 * Class file for Audit Log View.
 *
 * @since      1.0.0
 * @package    wsal
 * @subpackage views
 */

use WSAL\Helpers\WP_Helper;
use WSAL\Controllers\Connection;
use WSAL\Helpers\Settings_Helper;
use WSAL\Entities\Occurrences_Entity;
use WSAL\ListAdminEvents\List_Events;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Audit Log Viewer Page
 *
 * @package    wsal
 * @subpackage views
 */
class WSAL_Views_AuditLog extends WSAL_AbstractView {

	/**
	 * Listing view object (Instance of WSAL_AuditLogListView).
	 *
	 * @var WSAL_AuditLogListView
	 */
	protected $view;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	protected $version;

	/**
	 * WSAL Adverts.
	 *
	 * @since 3.2.4
	 *
	 * @var array
	 */
	private $adverts;

	/**
	 * Audit Log View Arguments.
	 *
	 * @since 3.3.1.1
	 *
	 * @var stdClass
	 */
	private $page_args;

	/**
	 * Stores the value of the last view the user requested.
	 *
	 * @since 4.0.0
	 *
	 * @var string
	 */
	public $user_last_view = '';

	/**
	 * The default view to be used
	 *
	 * @var string
	 *
	 * @since 4.6.0
	 */
	private static $default_view = 'list';

	/**
	 * {@inheritDoc}
	 */
	public function __construct( WpSecurityAuditLog $plugin ) {
		parent::__construct( $plugin );
		add_action( 'wp_ajax_AjaxInspector', array( $this, 'AjaxInspector' ) );
		add_action( 'wp_ajax_AjaxRefresh', array( $this, 'ajax_refresh' ) );
		add_action( 'wp_ajax_AjaxSetIpp', array( $this, 'ajax_set_items_per_page' ) );
		add_action( 'wp_ajax_AjaxSearchSite', array( $this, 'ajax_search_site' ) );
		add_action( 'wp_ajax_AjaxSwitchDB', array( $this, 'ajax_switch_db' ) );
		add_action( 'wp_ajax_wsal_download_failed_login_log', array( $this, 'wsal_download_failed_login_log' ) );
		add_action( 'wp_ajax_wsal_freemius_opt_in', array( $this, 'wsal_freemius_opt_in' ) );
		add_action( 'wp_ajax_wsal_dismiss_setup_modal', array( $this, 'dismiss_setup_modal' ) );
		// add_action( 'wp_ajax_wsal_dismiss_notice_addon_available', array( $this, 'dismiss_notice_addon_available' ) );
		add_action( 'wp_ajax_wsal_dismiss_missing_aws_sdk_nudge', array( $this, 'dismiss_missing_aws_sdk_nudge' ) );
		add_action( 'wp_ajax_wsal_dismiss_helper_plugin_needed_nudge', array( $this, 'dismiss_helper_plugin_needed_nudge' ) );
		add_action( 'wp_ajax_wsal_dismiss_wp_pointer', array( $this, 'dismiss_wp_pointer' ) );

		add_action( 'all_admin_notices', array( '\WSAL\Helpers\Notices', 'init' ) );

		add_action( 'all_admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'load_pointers' ), 1000 );
		add_filter( 'wsal_pointers_toplevel_page_wsal-auditlog', array( $this, 'register_privacy_pointer' ), 10, 1 );
		add_action( 'admin_init', array( $this, 'handle_form_submission' ) );

		if ( $this->plugin->settings()->is_infinite_scroll() ) {
			add_action( 'wp_ajax_wsal_infinite_scroll_events', array( $this, 'infinite_scroll_events' ) );
		}

		add_filter( 'manage_toplevel_page_wsal-auditlog_columns', array( '\WSAL\ListAdminEvents\List_Events', 'manage_columns' ) );

		if ( WP_Helper::is_multisite() ) {
			add_filter( 'manage_toplevel_page_wsal-auditlog-network_columns', array( '\WSAL\ListAdminEvents\List_Events', 'manage_columns' ) );
		}

		// Check plugin version for to dismiss the notice only until upgrade.
		$this->version = WSAL_VERSION;

		// Set adverts array.
		$this->adverts = array(
			0 => array(
				'head' => esc_html__( 'Upgrade to Premium and enable search filters so you can find the events you need within seconds, get notified via email or SMS about critical website changes, see who is logged-in to your website in real time, manage user sessions, create detailed reports, and much more!', 'wp-security-audit-log' ),
				'desc' => esc_html__( '', 'wp-security-audit-log' ),
			),
			1 => array(
				'head' => esc_html__( 'Instant SMS & email alerts, search & filters, reports, users sessions management and much more!', 'wp-security-audit-log' ),
				'desc' => esc_html__( 'Upgrade to premium to get more out of your activity logs!', 'wp-security-audit-log' ),
			),
			2 => array(
				'head' => esc_html__( 'See who logged in on your site in real-time, generate reports, get SMS & email alerts of critical changes and more!', 'wp-security-audit-log' ),
				'desc' => esc_html__( 'Unlock these and other powerful features with WP Activity Log Premium.', 'wp-security-audit-log' ),
			),
		);

		// Setup the users last view by getting the value from user meta.
		$last_view            = get_user_meta( get_current_user_id(), 'wsal-selected-main-view', true );
		$this->user_last_view = ( in_array( $last_view, $this->supported_view_types(), true ) ) ? $last_view : self::$default_view;
	}

	/**
	 * Add premium extensions notice.
	 *
	 * Notices:
	 *   1. Plugin advert.
	 *   2. DB disconnection notice.
	 *   3. Freemius opt-in/out notice.
	 */
	public function admin_notices() {
		$is_current_view = $this->plugin->views->get_active_view() == $this; // phpcs:ignore

		// Check if any of the extensions are activated.
		if (
			! class_exists( 'WSAL_NP_Plugin' )
			&& ! class_exists( 'WSAL_Ext_Plugin' )
			&& ! class_exists( 'WSAL_Rep_Plugin' )
			&& ! class_exists( 'WSAL_SearchExtension' )
			&& ! class_exists( 'WSAL_UserSessions_Plugin' )
			&& ( 'anonymous' === \WSAL\Helpers\Settings_Helper::get_option_value( 'freemius_state', 'anonymous' ) || // Anonymous mode option.
			'skipped' === \WSAL\Helpers\Settings_Helper::get_option_value( 'freemius_state', 'anonymous' ) )
		) {
			$wsal_premium_advert = \WSAL\Helpers\Settings_Helper::get_option_value( 'premium-advert', false ); // Get the advert to display.
			$wsal_premium_advert = false !== $wsal_premium_advert ? (int) $wsal_premium_advert : 0; // Set the default.

			$more_info = add_query_arg(
				array(
					'utm_source'   => 'plugins',
					'utm_medium'   => 'banner',
					'utm_campaign' => 'wsal',
					'utm_content'  => 'tell+me+more',
				),
				'https://melapress.com/features/'
			);

			if ( current_user_can( 'manage_options' ) && $is_current_view ) : ?>
				<div class="updated wsal_notice">
					<div class="wsal_notice__wrapper">
						<div class="wsal_notice__content">
							<img src="<?php echo esc_url( WSAL_BASE_URL ); ?>img/wsal-logo@2x.png">
							<p>
								<strong><?php echo isset( $this->adverts[ $wsal_premium_advert ]['head'] ) ? esc_html( $this->adverts[ $wsal_premium_advert ]['head'] ) : false; ?></strong><br>
								<?php echo isset( $this->adverts[ $wsal_premium_advert ]['desc'] ) && ! empty( $this->adverts[ $wsal_premium_advert ]['desc'] ) ? esc_html( $this->adverts[ $wsal_premium_advert ]['desc'] ) : false; ?> <?php if ( isset( $this->adverts[ $wsal_premium_advert ]['desc'] ) && ! empty( $this->adverts[ $wsal_premium_advert ]['desc'] ) ) { ?>- <a href="<?php echo esc_url( $more_info ); ?>" target="_blank"><?php esc_html_e( 'Learn more', 'wp-security-audit-log' ); ?></a><?php } ?>
							</p>
						</div>
						<!-- /.wsal_notice__content -->
						<div class="wsal_notice__btns">
							<?php
							// Trial link arguments.
							$trial_link = add_query_arg(
								array(
									'utm_source'   => 'plugins',
									'utm_medium'   => 'banner',
									'utm_campaign' => 'wsal',
								),
								'https://melapress.com/wordpress-activity-log/pricing/'
							);

							$buy_now = add_query_arg(
								array(
									'utm_source'   => 'plugins',
									'utm_medium'   => 'banner',
									'utm_campaign' => 'wsal',
								),
								'https://melapress.com/wordpress-activity-log/features/'
							);
							?>
							<a href="<?php echo esc_url( $trial_link ); ?>" class="button button-primary wsal_notice__btn notice-cta" target="_blank"><?php esc_html_e( 'Get WP Activity Log Premium', 'wp-security-audit-log' ); ?></a>
							<br>
							<a href="<?php echo esc_url( $buy_now ); ?>" class="start-trial-link" style="text-transform: uppercase;" target="_blank"><?php esc_html_e( 'See plugin features', 'wp-security-audit-log' ); ?></a>
						</div>
						<!-- /.wsal_notice__btns -->
					</div>
					<!-- /.wsal_notice__wrapper -->
				</div>
				<?php
			endif;
		}

		// phpcs:disable
		// phpcs:enable

		// Check anonymous mode.
		if ( 'anonymous' === \WSAL\Helpers\Settings_Helper::get_option_value( 'freemius_state', 'anonymous' ) ) { // If user manually opt-out then don't show the notice.
			if (
				wsal_freemius()->is_anonymous() // Anonymous mode option.
				&& wsal_freemius()->is_not_paying() // Not paying customer.
				&& wsal_freemius()->has_api_connectivity() // Check API connectivity.
				&& $is_current_view
				&& Settings_Helper::current_user_can( 'edit' ) // Have permission to edit plugin settings.
			) {
				if ( ! WP_Helper::is_multisite() || ( WP_Helper::is_multisite() && is_network_admin() ) ) :
					?>
					<div class="notice notice-success">
						<p><strong><?php esc_html_e( 'Help WP Activity Log improve.', 'wp-security-audit-log' ); ?></strong></p>
						<p><?php echo esc_html__( 'You can help us improve the plugin by opting in to share non-sensitive data about the plugin usage. The technical data will be shared over a secure channel. Activity log data will never be shared. When you opt-in, you also subscribe to our announcement and newsletter (you can opt-out at any time). If you would rather not opt-in, we will not collect any data.', 'wp-security-audit-log' ) . ' <a href="https://melapress.com/support/kb/non-sensitive-diagnostic-data/" target="_blank">' . esc_html__( 'Read more about what data we collect and how.', 'wp-security-audit-log' ) . '</a>'; ?></p>
						<p>
							<a href="javascript:;" class="button button-primary" onclick="wsal_freemius_opt_in(this)" data-opt="yes"><?php esc_html_e( 'Sure, opt-in', 'wp-security-audit-log' ); ?></a>
							<a href="javascript:;" class="button" onclick="wsal_freemius_opt_in(this)" data-opt="no"><?php esc_html_e( 'No, thank you', 'wp-security-audit-log' ); ?></a>
							<input type="hidden" id="wsal-freemius-opt-nonce" value="<?php echo esc_attr( wp_create_nonce( 'wsal-freemius-opt' ) ); ?>" />
						</p>
					</div>
					<?php
				endif;
			}
		}

		// Display add-on available notice.
		$screen = get_current_screen();

		// phpcs:disable
		// phpcs:enable

		/*
		if ( $is_current_view && in_array( $screen->base, array( 'toplevel_page_wsal-auditlog', 'toplevel_page_wsal-auditlog-network' ), true ) ) {
			// Grab list of installed plugins.
			$all_plugins      = get_plugins();
			$plugin_filenames = array();
			foreach ( $all_plugins as $plugin => $info ) {
				$plugin_info        = pathinfo( $plugin );
				$plugin_filenames[] = $plugin_info['filename'];
			}

			// Grab list of plugins we have addons for.
			$predefined_plugins       = Plugins_Helper::get_installable_plugins();
			$predefined_plugins_check = array_column( $predefined_plugins, 'addon_for' );

			$plugin_filenames = array_unique( $plugin_filenames );

			// Loop through plugins and create an array of slugs, we will compare these against the plugins we have addons for.
			$we_have_addon = array_intersect( $plugin_filenames, $predefined_plugins_check );

			if ( isset( $we_have_addon ) && is_array( $we_have_addon ) ) {

				foreach ( $we_have_addon as $addon ) {
					$addon_slug         = array_search( $addon, array_column( $predefined_plugins, 'addon_for', 'plugin_slug' ) ); // phpcs:ignore
					$is_addon_installed = WP_Helper::is_plugin_active( $addon_slug );
					if ( $is_addon_installed ) {
						continue;
					}

					$is_dismissed = \WSAL\Helpers\Settings_Helper::get_option_value( $addon . '_addon_available_notice_dismissed' );

					if ( ! $is_dismissed ) {

						$image_filename     = array_search( $addon, array_column( $predefined_plugins, 'addon_for', 'image_filename' ), true );
						$title              = array_search( $addon, array_column( $predefined_plugins, 'addon_for', 'title' ), true );
						$plugin_description = array_search( $addon, array_column( $predefined_plugins, 'addon_for', 'plugin_description' ), true );

						?>
						<div class="notice notice-information is-dismissible notice-addon-available" id="wsal-notice-addon-available-<?php echo esc_attr( $addon ); ?>" data-addon="<?php echo esc_attr( $addon ); ?>">
							<div class="addon-logo-wrapper">
								<img src="<?php echo esc_url( trailingslashit( WSAL_BASE_URL ) . 'img/addons/' . $image_filename ); ?>">
							</div>
							<div class="addon-content-wrapper">
								<?php
								printf(
									'<p><b>%1$s %2$s %3$s</b></br>%4$s.</br> <a href="%6$s" class="button button-primary">%5$s</a></p>',
									esc_html__( 'We noticed you have', 'wp-security-audit-log' ),
									esc_html( $title ),
									esc_html__( 'installed.', 'wp-security-audit-log' ),
									esc_html( $plugin_description ),
									esc_html__( 'Install extension', 'wp-security-audit-log' ),
									$this->get_third_party_plugins_tab_url() // phpcs:ignore
								);
								?>
								<?php wp_nonce_field( 'wsal_dismiss_notice_addon_available_' . $addon, 'wsal-dismiss-notice-addon-available-' . $addon, false, true ); ?>
							</div>
						</div>
						<?php
					}
				}
			}
		}
		*/
	}

	/**
	 * Method: Ajax handler for dismissing addon notice.
	 */
	/*
		public function dismiss_notice_addon_available() {
		$addon = \sanitize_text_field( \wp_unslash( $_POST['addon'] ) );

		// Verify nonce.
		if ( wp_verify_nonce( \sanitize_text_field( \wp_unslash( $_POST['nonce'] ) ), 'wsal_dismiss_notice_addon_available_' . $addon ) ) {
			\WSAL\Helpers\Settings_Helper::set_option_value( $addon . '_addon_available_notice_dismissed', true );
			die();
		}
		die( 'Nonce verification failed!' );
	}
	*/

	/**
	 * {@inheritDoc}
	 */
	public function has_plugin_shortcut_link() {
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title() {
		return esc_html__( 'Activity Log Viewer', 'wp-security-audit-log' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_icon() {
		return $this->wp_version < 3.8
			? WSAL_BASE_URL . '/img/logo-main-menu.png'
			: $this->get_icon_encoded();
	}

	/**
	 * Returns an encoded SVG string for the menu icon.
	 *
	 * @return string
	 */
	private function get_icon_encoded() {
		return 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHhtbG5zOnhsaW5rPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5L3hsaW5rIiB3aWR0aD0iMTEyIiBoZWlnaHQ9IjExMCIgeG1sOnNwYWNlPSJwcmVzZXJ2ZSIgdmVyc2lvbj0iMS4xIiB2aWV3Qm94PSIwIDAgMTEyIDExMCI+CiAgICA8aW1hZ2Ugd2lkdGg9IjExMiIgaGVpZ2h0PSIxMTAiIHhsaW5rOmhyZWY9ImRhdGE6aW1hZ2UvcG5nO2Jhc2U2NCxpVkJPUncwS0dnb0FBQUFOU1VoRVVnQUFBSEFBQUFCdUNBWUFBQUQvUEplZ0FBQUFBWE5TUjBJQXJzNGM2UUFBR1V0SlJFRlVlRjd0M2ZldmRGMVZCL0ExZ2dXd2dGUXJWVUJFbW1KWGtLS2dTQkdsU0ZXRENjYkVHQkwrQThWWWZ0QllJVkhBZ2dKcWFCWXNvSUJVcVNKTmVwTW1UZUFGWGhqeXVjOWFOL3VkWitiT21YUDJtWm43eEVrbWM4dmNPM3Z2NzE1cmZkZDNyWDNPSWk3UngzSzVYTlRVRm92RjhoS2RacHhPOGxLYTRISzV2RkpFZkhsRVhDVWlQaGtSbjFnc0ZwKzdsT1o0dWprdnBVa3RsMHVBWFNjaXZqRWliaFFSVjQrSUQwZkVXeVBpN1JIeGdjVmk4YWxMYWM3bjNnS1h5K1dWMDlxdWxjQjlTMFRjS2lKdW1nQitKQ0xlRkJHdmpJalhSY1E3QUJrUi83ZFlMQzQvNzJDZVN3Q1h5K1VYUmNTWFJNUlYwK0p1RWhHM3ppZkwrN29FejNzK20xYjRub2o0NzRqNHp3UVRxQ2RBZXM5aXNmajhlUVR6M0FDWXBNUjR2emdpdmpLdDdaWnBiVGVQaU90SHhOZEV4TlVpZ2xVQzJmc1JHT0N3dGs5RXhQdlNwYjRoSWw0ZEVhOUo5L3J4Zk0vbnp4UHBPUmNBSm5oZkdoSFhqUWpXeGsxNmZsTUNkODJJK0xJRUYzQ2JIZ1drT1BpL0NSd2d1ZGIvaWdoZnM4ckx6Z3VJUncxZ3Nrbld4aVhlT0NLK09aODN5NTk5VmJwUnJIUFhCMWFLb1g0MEl0NmRjWko3QlNKWDYyY2ZQWGJYZXBRQUxwZEwxblNOQmpqVzlxM0pMRmtoNEx5bng0T0wvWFJFZkN6ZDY1dlRyYjQyZ1h3dmExMHNGcGYxK0xEZS8rTm9BRXcyS1EwQXpqZWtwU0Vtd0pNV1hEdlpwdmcyeDZOaXBWaklqVW83dU5aWFJRUXdXU1JHKzZsallxOEhCVERaSlBmSG1xUUJZaHJRV0p0WXgzWDZPVGJwZmZzWXJ6Z0pUQlluVHI2cmNhL0FmR05FZkNoLy83bER1OWg5TE1oRjFwS2tCTm40aXJTMld5Um8yS1JZaDAzNkhlRG1zcmh0Vmd4RWNmSXpFY0VxdVZMdWxWV3lTRS9nK2gwZ0R5TFg3UjNBakc5WTR3MGpBbUNJaVZmNUd6Y3BEY0E0OXo2Mk14Q3RPQ2tONFY0cE8xZ3JNRm1rNzZrOFl1bGVIM3RacEhTVkxJck1kWU9Jd0NMbGNNQVQ3NzY2eWQvMnVnQWpQcXp5U2U2VnF2UDZGQWU4K3Y1L1V1WFppekF3RzREcEpybkFBbzZGVmVKTjVycGVBaWYrelRhT0VRQU4vUk5XS1o5RWJFcmxJUXFJa3ljV21jejJNM082MTY0TDE4UTJhZ25na0JEeDdUYjV5dm9xRFNoUzBuVU1RMWUvMC91QTZFbXVFd3VwUEc5TDkxcnNGYmgrSjVaMlYzbTZMRjRqYzdFNDhRMkR4Q1JaWEtrbDJLUTBvV1N1VG10NDhtK0tRS3krMW1mVVBOdjVkcGw3TTRsVzVjRlN1Vk9pQUxMRE1va0RIMHdnbDcyc2N2SWtNcjZwdmJFMnJwSEZJU1dBKy9vVWxVdm02Z2xhQVNjbTJkMW92NTN1Nld0V0FWQnNsMGV3ZVl6VHM4Ymo1NVBYWU0ya2pJbDdwZkpVR3RLU0hqOVRvNXdjSjBjUGZybGNZb3JVRWlDeE9JU0V4ZmthV1NHQldiVFJuN0ZtWVV3WU9GUVR1MW10enlMVlV6enl0Y1VEb1BjWGdOaXRNUkVLUEgydFhtZ092RWFwTzJkcHFidHV3TW9ualJlNVlZV1Znb2lUNzB5Vnh3WWM5ZGhwY1ZPYkJBcldDRGdXVjJxSmFnQTNhV0Y2NW01Mk04M1NJbkJORnNJT2ZrdVNCMEFDcml6UFloUjRGdEFjQzBTYmp2VUJFM0RHeTNNWU83VkhmUFl6WUphN0g3V3dhLzZvNG1TcFBDeFNuT1JtcVR4WUxaVm5wODZCclFCbWZPTnFUTHp5Ti9FTk1TazNXZkhOLzl2NlB3ZXNDTXN4WVhrWGdCQURORjNPUmVKNmY0SUpOT0FDclMzT3JrdXEyemdJVUdDcUp5SmJOaVR3QUNuRkVRSVFMdlB5SHZQdllaa2wxL0VRTnFPTmFFN0tXc1ZlZVpVVER6SWtUbTVjN0VibXNsc3BJMkliYS9NcUNiZHpXWnZKOVpLNUFHY0hzalpBY1RjbXAvakszV0I1Zmdjdzd6dFprQ0VUWGQwMERXTzJCc2FQZ0xFOFFKWjNLU0lHV0VEekxEMDJhS2s4TmltWFh5b1BxelJmQWdHcnRJSE5iMk9zWER1WTVYSnBNbllsb0lqSlFPTXU1WExpbTExcEIvZllsYlcyTElncnJCMkp1VlZ5YkxmV3J0ekp4UXl3OXRPM1pJZ3c5L0kyWlpGYU5HeGVNaC9YMnpORUFNZUdYQzAyMjd6bUwxU29ocXlOazZjQXBzVUJSb0lOS0s0RWNNaUorR0RnckpIRjlYeVlBTGRob0hiZmY2U3l3UUpQM01raDFQK21PbUxlM0NrUWI1dnBFU0FSb0o0YjJKb0NTVWl3WVhrYzhiRnFsTmFIQi9wa2E1RW5BS1k3NFNKWTJmZEZ4TGZuMTErYkF3VnNEOWZSQXMrTjZFY1JCMWpiU3lMaVphbGlpSHVBTzRoQXZNYmRtcjk0Q0xqYlJjUjNKZU1XUnFRbHZkZkdwdVp4YkdCQ0FLLzA4b2g0ZmdKNjJqRlFBTnBKTk1sN1JNVDlNb2piWVhQa1NVQ3gwN0F1THNLZ1hwUiszdzR6dU1uNVVVOFgwV3h5UUlxUlNNNzNSTVQzcHFkQzd1WmFLM0d5T3V2K0lpSTh1ZFNUTldvQlpIMFB6cWVkMWRQUDEzcjZVQzZDU3ZHQ2lQaTM3QkRqTHNTQWc1VmxoZ0NlbmdyaEVVcUVGUzcxRG1tUjRxWFVvN2RiTGNJai9mamppUGdkTEx6U2pSWkFNZStoQ1NEWDJYc2d5QWZmTHM0OUp5TCtQVjBEUy96MHNiakxIWUJFNGhDOXNrWkFTcTNFekRFOU9tZDl0STB2ckFEd3Q4NEM4Q0dOQmZZRVVKME1LWGx4UkR3MzR4MHIxRng3ZE81eUNJanBWcXNvemZwdUh4RS9HQkhmbWVFSXdMMGUxcWdzOExmM0NhQVBSbFFFNFg5TjhDU3M3OXRFaThmTU9CazBsMS9KZWR0aVdLSUFDUTR4OEhwNXo0MlRzcUxZS05XNGN4SkJrcUk4dVFmQk9RaUFYQ2IzS0ovNWh3UVBMWjdjcXRlb1EySU85bHdhSjVlR0xWYkt3NVdWRklmUmNVUEdSQXlRUUZOeUJxc2VaMjJ1ekNFUlA2R0lKUUtTRU5ERHBlNFZRRUhYb2pGNWFjRXprMmx5bVhLWTBhbEJXaHEyaDBUSVY5RjY1RXVlUm1Dd1dBREZGaVhrZG4reFh1U0pOd0FrS2M1NEtCN3lxNU1xK3RRVyt4eWZUV1U4M3g4UmQ0K0k3MGdKY2tyK3ZEY0FDenk1SFliNWR4bjNMTkFrb3BLTFk0Y1RHWkFGdXh1QUdETWFMeDhEbXJoVWJmVmxOS1ZCbHVwUlFCcW5SaVZld3VFWDFRSWVZdlNobDZhTEhCRUUzbzlFeEEra0hHbDhZeDU3QXhCWnNhUC9KZDBtVlVXemoxeG05S05aRkxUOWJobGo2dmhZeGJ0ZG1CLzNEaVR4a0VWS1k0Z0pQSVluOFJ6Qm11SXRXQnl2SVBFSElyZktNc2VRbTcwQXlFWFp3ZjhZRVgrZjZ2cUhlcENGQkJCUUZKQUhSc1NQWmtLOUMyaWJObENwSHVJanN2WEN6RkVCZXBwdmpkbDlHUmQ1Qng0RGlIZktlaWszdTh0amRnQzVKQldEWjBmRXMxTHVtVXhXcnFDN1hUZ0hTRnkvWjZZNjhxOWU3ZlUrcWhnemE2UU0vVk8ra3JJR2xYYldJWkt1WDVVRFF3WGlEMlVaYnBkaTkyd0FWbWNXU1l6VlBUMkpRZmY4cnJGQ3U1bGk5T1Bwb25ybXE5V2doT1J3cFdMNFAyZHBaM1FNejdGanlqb1dFSnNmeS9nOVZHT2VCY0JxRjFERFluV2VXRjJYWG84TnV4bXJ4RDd2R2hHL21KV1NYZDNSRU5jbFBrbzNFQnViMHVaODU1VGN0ZW1OVmQxaGlmZHVRTncycHU0QWx1VUJUSnBnZ3BqY3BNQy9iUmFwZmdDTU8vcWxKQWJ5dmg3Sjh1ckhWeDc3aW9oNFNycFVJSTZ1UnphV3lQMnpRckZjR3JUTm5YWUhFR0VSOEo4UkVYKzdML0FTUU1RRm0vdlppTGgvZnQzVGpiWkFsbjVMZUg5U3BrYUl6UlIyYXJOeHAycXQ0amxXdlMyZWR3V1FjaUYzRWgvK0JuaUx4WUt5c1pkSDdtSnU5RDRSOGNoMG96M1k2S2J4YzZmS1hFTEVYMlplMjBPUWtMZnlKUGZLdUNpbkxmRmhkU3pkQUpUbnlaR3d6U2RuN3ZTeEtUdHlWOVFUUUc1VEhIeFVMc0tjQUJheGtTS3B4UUh4clIxeTJ4TEM1WW5pSVhlcUYyY2RpRjBBbEl4VExnbzhzYUZycWpBRXpBU1E1aW1uZW5UVzVPWUU4TVJ6WjczeWVSSHhKeG56Sjg4OWlRMzU3OXNpNHI1cGliekxxdXcyR1VDeEFMVUczbE16MmYzSWxJQStCS3d6bUNnTEZEc1FHYjBxY3dOb0tOYkFCc1pLLzVCbzBlTTRXU2I3NWtOMmUwQVNNNVdOTnE1UEF0REF5VTIwVFc3VHEzTFFRZXA0YVlGMHhwK0lpSjlMOVg4ZkFBSVJlZE83QTBDS2s5YUcwWVNtTm1nMlVBR05aZ3JFNzg2cVNySHIwUUFhbkxLTHBCWUxvM0crYTRyWU85YnlWaVlyNEFPUDI5SExNeGNMWFVjbWNJQy9qb2pINGdOVFkyRXpMMjZUTU0rejJKd2F5NnFlT0JwQUVwbEVIWGpTQlFNZTNjYy9GYnhNSTVTU3hBd0VSZ2VkR0RKSEhyaldnK2VHcHBmK3FrNnh4V0poalNZL212T1U1RUxNbE5KRXVaRWpNcVNkSy9Mb00zM3phZWs2MzdSWUxMaVFnejB5NkdOcWxJeGZTR0Y0akxvL1pRNDJzTUwwYjJiRlJUaVo3RVp6YzlxSTlGMDVJZ0I1R0RrdkhiaDZZZ2ExVkdCQ1JGeVY5RDlMU2VuanZRWTZadlZ5aDdJK0ZZbWZUbEVZRzkyWCs2eGhjMmZJek9NajRzOXQ4cDU4b09uUjVXVzBlRkpzWERzQUQ5SFV0QlhBbjhxS3R1WWplWTllbG9Nd3poYm83RHZSNWkrQi84a0p0YlV4KytjS1EwbHJrTmovWGtTOG9qY25TRThETkgybkdzMzBvTm80TnMyWkFGTDVtYTJERlJpbnVQZWVRNlFMSytCUjdUVUpsWkt2SWpGVXlaOEsyT3JmYzVlVUozSHdOOVFPNTdpS1V6SlQ0UUtwWVZTWU4wMTJJNEJFVmRvaXRGa2RuZlAxdlZqV21GWE1RemJhS0xCT2lmc1BaOTZuNzJYZnJyT2RBaTVBdkg4TWtYdXhXR2lPNnY3SUM5anFQSUFMYTlURi9nZWIyZ290a2dYQzZqVGVLcVVvRFhVSjBCZHQ0d3ZYdEs2amFYNWRlV1VkOThLK3hHSzlMeHFFeEQ1akJONitXT2NtVUtwMTVGZlNTMzE0am5WcTRqNlA0OWdEWGtMSysrQzZ6bXdtaXJiSysraCtYZG9oMW5MeEMrQmhqd0RSTjFMVmNHOG44bEltMnFQYjNLZVlnS0VkMHZKcU9tUkZsZnRmVG1YR1dzMjEwWWtVaklwU2d3SEx5ZW5QRjUyTnNOdTVVZkZQM0hOV1laWkg3aXh0Qm1JYWwyMkFkUzFyc1kzYkJKZ25wdWxuYzV6VkdEcy9LVllMSUl1WUM4RGE3QnE2UE9UbE1vS0xBSFJZQThvNnRRalZzNUdYWkZtQWVWQXlTaFpXQzFDblpWbG90UW1PWGVpNS9vNEY2aXRsZ2JqQ25CWm80ekl1WFcyTUNqL2hzcThBb0VYanp2aFpmZjUwUGl5cmk5YTNJZjRCVUhEK21hd29ISU5ySEFxNEdDak1pSUhxb3RLczdoYllWQ3R3QUd6VVo2cUdySTJCM0tlV1BTM2hCTnUvU2t2c2Z0Q3lhUzFBbWhSbE1hd3BuY3RERjc3WCsxaUNVMWJrTklTdmV5dEpyaEUrZ0pjNE5hWnV5TEIrOTZ6RExmSkFTVEphVExCVmNZZjY2TGE2TTBnTWx1bEVqNUtRM2JWdlNXd3NtQ1h3cXcyUzAxN1VvNnkweGtzVnlhUEdLUGppQlRUcHJVcU11RVFGRndjbDgzdzh2VzkwdS9rR0VBM1FaVW9BU1BkRGFnNmRIZ3dCRllCcW96UmlPZGxyWmxnYjRRUW40YUVZRlN2RVRiWXFNWFUrMEdWRnROVkpIT2w5WkxYVHdEbGtsdHZlazBxRHovbjVWQnFVVXZaVjE5czJ2TE4ranp3Z01JOXIyaXU2MVViVGRXTGZEb3dLYWVLZnpVMEwvYU1oRmdoeGkybW5FVzExS1A5cFdtUzMvcGVtWXhtUmVYZ1NtYkdIUDZZQXN1dmZJakJVbUYvTCttaTNGS0xwSFhWSVZDZ1RXaFI2YmV5ZHkwbk0yR0JkMDBzYmdWWUtIV2hkU2twTnB6VUNvN3BBZFovamlnKzdBblRXKzIxcW5nbVYvM1ZFcHZONnlIZTVTK0RKQ01obzBvaFI5VUFBK3NQcUFTV2thdUo5UTYraWJycFI5UzVpclQ1UGF0QXhKZXlyWUdvdlVTTWxaejJCSyswaDlEZWJHV0RBMHk5YXZhSjR3ZWlLdkFtVXpPVm9OQkRsUFNyemswbE5rMDVvRVh4RXFqSnFmc2RJWnFvekRTOVFSc0pDZGFaTnp2OVN0TmNhb2hLdkdzUUtXODEzRW9BRm9tdVZ1RWlCOUVLaDk3MmRRR1J4emd2WWVVaVUrSHVNT1NIcmsxTEpqN0ZCMWpmNUl1ZE4yZWd1S1d6STkxYXZBalVad0FKUklOVm1iaEtZcVRielNaYVlWbWpBTkZHTlN2dnVjeGtTSXl2M2swZy9NVHZ6SnAvQXlwWkNZUVBqMU1pa0c4MTV3bFUyM2dWQUU3VUxIWmZtUmxUcm5hTnpBbmYwZ1EvL05IZWh2SWNMd1lKWjVCeW5qb2FBZFZGT25UbVlhNVpKcVlnYjcrNndjWUVrWFpBbWNKdEFWSVZaeHdHNkFXaHloRndnWW1LVWdaZW1aam9WUkxFUGNHUWpWUXFhN0RHNFVpVWM2WlRRZ1luakFxUFBDdWFHclJJUjhJZ1lkMHpCZWhPQjZ3cWdNWmlVNHFJK1VSTVRHMCt2M3pWcW0xKzRxYU9FVlE0a2dWV0JsK2dmTXJtdmd5MWlQdVpwczA0U3JwdEtqUFFKZUVCVSt6eHJzM1lIRUVZQ3VKMUp6SlVudW5UV0pMVW1YU2szb2tOWjh4SzNvdUI3Q0JBdEdrOVRIZW5DeGRRejg5aTErZWt1NERZTHZHMEN4aXdBbGlXU2xSQWEybUJaNG1oMzJraHNKaWU1dDFOWjRqN3pRNWJId3lCc05xZVlUdzhlZmJXTkpHczJJdzhqMXF2dllkemJ3R3NKNUtDMndwTFNodGJudUZQVmFURlJnRmZ5SnkrTlpxY05pSGFxWTFmWUdUZERxWmo3UWJnd0h4NUZWNTY2S011YkFsNFJGdk94S1hjQmIzWUF5eEsxWXRpcFlxSllNWFhTTEk2NzBkQWpSK0pXcTZtcDE3V3JhelBVQllxVTBlUjZOcU9MRzJqc1VtMmZzaG5GdHVyeEJCN1BVcGZ6SENwWXpPWkNXMnRnaVdLR0hjdnRjRCtUNkhaYW90SVc0TFI3bUx4RTF3TFVOYnVITHNJNnk2MHJPT25Ic1FIcnFyZ0syazRoRSs5SGhZUG1lbTdFYUlTTXRta09GUTUyR2ZkZUFDeExyR3VrdWZDQjJQaVdLZHBwYy8weDhVT2FBVUJIazJtRjNDckphUXpKc1NqVUpjQzVUQXByVXdQMXRYcWZUb1RSWmFLOHpaNVVDSGpPY2hnM1N4eVRHdTBOd0VyMkhjU1FMeWxGY1VVT2cwdzlWODV0QXNzTzFnUlZWOUt2MjdOU2MveWVBTERhREdVQjZvTGlUaE1CRGtpdVllcGlEVUFqVXZNZ09yNUd1OHpNODFSVlhMV3dtcEgxdHE1VFdJYkc4NzBDV0VGWGY2a1dPTGtpSUgwdHpSamxrazZEMVlVck5sWHZLREFCU0FqMmRWMngwTy9yR3RaMXhsMThxeXNWdWs4RDlsejMrM01sWWRMWVZPQVFQODFhem5Eb0xSSzdlUTFoWUNncFhBZnEzZ0U4MllqWkJxZWVTTG5uVXJrcDVHYVNDTHh5dHpSZ3RkY010VmpyTGplcEVhbTlUcWl2VHk0eGFheFRxZ3JOMlQ0dTBxa2lyUkEwWGVXaEhtYzREZ0pnZ2FpWGcydHk1VUppTUtZS1ZITFU2QmlUcmdvUjhLekxTNHFGTExSdWNWZlhDL1U1ck12ejlJNHZVNEhMTWZoc0lQRUVtREtyRSs5NGhGNjNrajBZZ0FXaVhjNTljYU9zRVUwWEkyYzV0cFlXY1FWWE5NWENOZ1dxSkZoaW13c3QwRE94Wk9TS0d4MURWalo5MUdBQTU3eHF2VUdJUTg2WXl4TUJLZWwvKzV4dC9FTlp3cTd2V3k2WHJNNXhhR2ZaQWFkRnN1NnhOQ1hlYllxQlc2OWFqOW01UXNMRFpyNThGWmVLQWJMR3V1a0hsbnFRdTBDUEFJNWJSSmF3VEFxUk9pYlM0bWR6OWJiYS9OSWRYV20vdjY0elc2eFFXTVNjcXFSZnQ1VHI2UXBxdmNvYTBYakpzNlRmSzFZb2VUN29oUlRXWnZ3WGJnaUdJSWwxaUFxcm94TDVmcTd6aWtJUGFVOGpsUTJ2cFlWSWNucVJvZmJtVjF5Q3hGak80aVFNdjY3ZFhqQjJyS3YzdlcrTDNxUHdWQTlKTkJVSFV5VWtHN2c3dVV3aU9ydGFXUHYraktYbUxiZTBOZ0JqZGRhbjdtUzI2ZnBtWXorNkNCZDFpS2Nxa1VFcnY2Y1FkTm9wdjNyM3Nycjd0Tk13L0h2ZDc1MkwwRDJtWHRkYmg4UUtEWmFDUTRjMFNHQzY1REdMRkRkSDNTTnc3QW8yOXhhVW9vaHJkZWN5cisxWnhURUswRVpPbEF5WnlJQ2xzN2k2NzI3ZHVlemtidGd0SWJ0SWoydHltcnBqRnhlQldVbElQZXRRWnUrV0J4UmZyZ2JJdXBLOEJscEtpVjJud2lGK3p2cElDVXcrVi9jT3BQam9FcFBUK2JuT2dkNmxMUnZZRlJGdFlId0FjRjV0WU9URnVxdzluM0ttb0pwTk40QjBBRk9NQktUSmVHV2hMSlZWOXR5SkxGS0MzZDdLdSs3dm9JQnNRbFNleVUxRm1jZlZ6YXpNbzA0R0E4dG1OVThxVDkzV29QYzh6VU9Pek9MTWtkY2g2U0VzMGk3eTQ1bksxV0JGUEMvelFXOFVFMDJ3dlU4OElBVjRzWEx3Lzl4aVNtSkIzUlpBTEtoN1BOaWxkcVpKV2dEdm9lelV6WTlOdUpKMWNiYVMvVGJKcjNzQ0cyL05pY1h4TGx5a2VGZXlYTTlqM1hXWmF1TTJKMkFKR1JxbWVCMWcrdDNnbnB1ZEZyc0o2dHlJTWduM2FxZXFEckRNWW1Ub2RJLzc2cHF3SjBBS1RCYkl6ZEl6UGJrZTMyTnFkVHVkOWo3eWRUR0Y5alk5UEFxQXpNR0d0QUY5endJQjZyMDEvcDNXYU0ybXJQSGJZT1E3bTQrbEFRNUJFUjZNbjlmWm1iU05HdHpLemFhb0R0eU1vMklGSnZiYVhwUmcxT2VzTEVZdEJEQ0xYZ3ZxRnFWOUZTOU9ibkNWNFB0c01Zc2xJU1d0ZHNwcjFLMTY2b29aUFRaZUtWQzhDTy9BMnVwMnFoWFhWZjI1U1dQZEdiaGFtOGtMbTBWWEZtY3g3R1pBaWgyQ2Y4VVE4V1VPZGFMY1pXbWNnQzNOczlLUGNxR2xrUlpROVgwdndOcjk1ck41QTlhR2hCV2o1Q2FsU0RZZE56bXArdUVESndOWW8yNXVqMk5Ic3o1eEVvaFZ0eE5YeWlwblpaTDV6K3ZNUXJjNURoZzBhK01PV1pmNHh0cUFoNlN3UXA1aWNId2I4SG45QUZ4SmdPM3FxdGx4cnl4UkRsVjN4SjZEMVEyWjd4enZhVmt6b2dVMDhhMU5BekRtU1hYUVRRT2ZiWGMyTmJ1NlZhazBCTU1ESkVVRDQwTWFwQ25jV204WE93ZFlwdzRuV1c4clFLaXVWQnJBVFZaOG0xUnIzRGFKMlFCY2NhM0FBUktyYk85VEQwZ3VscFdLb2IyWTM3WjVqLzE5eVZ5SUVoMjNyRTBPeDAyZTNvZHdYK3JSN0FDdXVOWUNrdFZ4bzhRQXBBZUk1THBTZVhycmkyTUJhLzlPR2lDT0lTTGlHdkM0U1VCS2JXaTMxSks5YXJkN0JYQUZUQ3l3THF1RjROQmRpN2tDa3N2dGRRL2FzUUFpUW9nSDBZQWVLVzlyMHdDNkpiVmtNcHNjTzhDREFiamlZa3NSS2ZkYUtnOGdwU2FTNjdscWJldldEcHNFRHFFQWNHSmJxNWI0M1dWelZQbDNCZkxnQUs0QVdSMW5GQkxTRm9zVUo3RlhGaW1mck5MV3JuUGQ5djZxaXJBNGJGSmNRMHhZSERkSjhjRW1SN2ZZYnh2QW1OOGZEWUFOa0hVUFhISVdsWWM4Vit3Vmd5MzJLazVPTFcyVnVzUGlTSEdTYnRaV2JGSStSMVNmcEphTUFXYm8zeHdkZ0NzV2laV1d5c01DZ2NjYTZhN0ZYc1hSMVpzZWI1dC9hYXpZSkpCWUd4ZnBsVmgrcXBha3pEWDVRZ2JiQmpUMjkwY0w0QnIyQ2tocENORzVWQjRDQWZLajJDeW5ITkwrd1FWaWpkeGkxZDZxWElWbEVwVW50enlPQldUWHZ6c1hBSzVocjYzS1V3VlhRSEszQUY2OVlGQ3h5Wks1c01teXVOT2k2YkhGdHlGZ25qc0FWMXlzT0NtZkxKVkhXUXVEbFYreVNIR3k0bHV4U2ZGTnJPTW1UK0xidm5PM0ljQU1mYys1QmRBRUc3bXVlbmtBQ1R4V0NVamlPYmRZMWdaRXFZRnF3T1NXK3FHTFBPZjd6aldBWjZnOHJLL2tPV1dkYXNXZ2xsdytsN0E4SjFDYi92Y2xBK0NhZkxMeVJTckpTWUgzR0JMdjNpQmZjZ0QyWHFCai8zLy9EK0N4STdSbGZGOEFyMDMwOW1yMEI0SUFBQUFBU1VWT1JLNUNZSUk9Ii8+CiAgPC9zdmc+';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name() {
		return esc_html__( 'Log viewer', 'wp-security-audit-log' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_weight() {
		return 1;
	}

	/**
	 * Method: Get View.
	 */
	protected function get_view() {
		// Set page arguments.
		if ( ! $this->page_args ) {
			$this->page_args = new stdClass();

			// @codingStandardsIgnoreStart
			$this->page_args->page    = isset( $_REQUEST['page'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ) : false;
			$this->page_args->site_id = WP_Helper::get_view_site_id();

			// Order arguments.
			$this->page_args->order_by = isset( $_REQUEST['orderby'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) ) : false;
			$this->page_args->order    = isset( $_REQUEST['order'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) : false;

			// Search arguments.
			$this->page_args->search_term    = ( isset( $_REQUEST['s'] ) && ! empty( $_REQUEST['s'] ) ) ? trim( sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) ) : false;
			$this->page_args->search_filters = ( isset( $_REQUEST['filters'] ) && is_array( $_REQUEST['filters'] ) ) ? array_map( 'sanitize_text_field', wp_unslash( $_REQUEST['filters'] ) ) : false;
			// @codingStandardsIgnoreEnd
		}

		// Set events listing view class.
		if ( is_null( $this->view ) ) {
			// Set the requested view based on POST or GET value. We only care
			// if the view is 'grid' specifically.
			$requested_view = $this->detect_view_type();

			// If 'grid' is requested use it otherwise use list view by default.
			if ( 'grid' === $requested_view || $this->plugin->settings()->is_infinite_scroll() ) {
				$this->view = new WSAL_AuditLogListView( $this->plugin, $this, $this->page_args );
			} else {
				$this->view = new List_Events( $this->page_args, $this->plugin );
				//$this->view = new WSAL_AuditLogGridView( $this->plugin, $this, $this->page_args );
			}

			// if the requested view didn't match the view users last viewed
			// then update their preference.
			if ( $requested_view !== $this->user_last_view ) {
				update_user_meta( get_current_user_id(), 'wsal-selected-main-view', ( in_array( $requested_view, $this->supported_view_types(), true ) ) ? $requested_view : self::$default_view );
				$this->user_last_view = $requested_view;
			}
		}
		return $this->view;
	}

	/**
	 * Helper to store the views that are supported for the plugins lists.
	 *
	 * @method supported_view_types
	 * @since  4.0.0
	 * @return array
	 */
	public function supported_view_types() {
		return array(
			'list',
			'grid',
		);
	}

	/**
	 * Helper to get the current user selected view.
	 *
	 * @method detect_view_type
	 * @since  4.0.0
	 * @return string
	 *
	 * phpcs:disable WordPress.Security.NonceVerification.Missing
	 * phpcs:disable WordPress.Security.NonceVerification.Recommended
	 */
	public function detect_view_type() {
		// First check if there is a GET/POST request for a specific view.
		if ( defined( 'DOING_AJAX' ) ) {
			$requested_view = ( isset( $_POST['view'] ) ) ? \sanitize_text_field( \wp_unslash( $_POST['view'] ) ) : '';
		} else {
			$requested_view = ( isset( $_GET['view'] ) ) ? \sanitize_text_field( \wp_unslash( $_GET['view'] ) ) : '';
		}

		// When there is no GET/POST view requested use the user value.
		if ( empty( $requested_view ) ) {
			$requested_view = $this->user_last_view;
		}

		// return the requested view. This is 'list' by default.
		return ( in_array( $requested_view, $this->supported_view_types(), true ) ) ? $requested_view : self::$default_view;
	}

	/**
	 * Handle Audit Log Form Submission
	 *
	 * @since 3.2.3
	 */
	public function handle_form_submission() {
		// Global WP page now variable.
		global $pagenow;

		// Only run the function on audit log custom page.
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : false; // @codingStandardsIgnoreLine
		if ( 'admin.php' !== $pagenow ) {
			return;
		}

		if ( 'wsal-auditlog' !== $page ) { // Page is admin.php, now check auditlog page.
			return; // Return if the current page is not auditlog's.
		}

		// Verify nonce for security.
		if ( isset( $_GET['_wpnonce'] ) ) {
			check_admin_referer( 'bulk-logs' );
		}

		$wpnonce     = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : false; // View nonce.
		$search      = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : false; // Search.
		$site_id     = isset( $_GET['wsal-cbid'] ) ? (int) sanitize_text_field( wp_unslash( $_GET['wsal-cbid'] ) ) : false; // Site id.
		$search_save = ( isset( $_REQUEST['wsal-save-search-name'] ) && ! empty( $_REQUEST['wsal-save-search-name'] ) ) ? trim( sanitize_text_field( wp_unslash( $_REQUEST['wsal-save-search-name'] ) ) ) : false;

		if ( ! empty( $_GET['_wp_http_referer'] ) ) {
			// Remove args array.
			$remove_args = array(
				'_wp_http_referer',
				// '_wpnonce',
				'wsal_as_widget_ip',
				'load_saved_search_field',
				'view',
			);

			if ( empty( $site_id ) ) {
				$remove_args[] = 'wsal-cbid';
			}

			if ( empty( $search_save ) ) {
				$remove_args[] = 'wsal-save-search-name';
			}

			if ( empty( $search ) ) {
				$remove_args[] = 's';
			}
			wp_safe_redirect( remove_query_arg( $remove_args ) );
			exit();
		}
	}

	/**
	 * Render view table of Audit Log.
	 *
	 * @since 1.0.0
	 */
	public function render() {
		if ( ! Settings_Helper::current_user_can( 'view' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-security-audit-log' ) );
		}

		// Verify nonce for security.
		if ( isset( $_GET['_wpnonce'] ) ) {
			check_admin_referer( 'bulk-logs' );
		}

		$this->get_view()->prepare_items();
		$view_input_value = ( isset( $_GET['view'] ) && 'grid' === wp_unslash( $_GET['view'] ) ) ? 'grid' : self::$default_view; // phpcs:ignore
		?>
		<form id="audit-log-viewer" method="get">
			<div id="audit-log-viewer-content">
				<input type="hidden" name="page" value="<?php echo esc_attr( $this->page_args->page ); ?>" />
				<input type="hidden" id="wsal-cbid" name="wsal-cbid" value="<?php echo esc_attr( empty( $this->page_args->site_id ) ? '0' : $this->page_args->site_id ); ?>" />
				<input type="hidden" id="view" name="view" value="<?php echo esc_attr( $view_input_value ); ?>" />
				<?php
				/**
				 * Hook: `wsal_auditlog_before_view`
				 *
				 * This action hook is triggered before displaying the audit log view.
				 *
				 * @param WSAL_AuditLogListView $this->_view - Audit log view object.
				 */
				do_action( 'wsal_auditlog_before_view', $this->get_view() );

				$requested_view = $this->detect_view_type();

				if ( 'grid' !== $requested_view ) {
					/**
					 * Action: `wsal_search_filters_list`
					 *
					 * Display list of search filters of WSAL.
					 *
					 * @param string $which – Navigation position; value is either top or bottom.
					 * @since 3.2.3
					 */
					do_action( 'wsal_search_filters_list', 'top' );
				}

				?>
		<?php
				// Display the audit log list.
				$this->get_view()->display();

				/**
				 * Hook: `wsal_auditlog_after_view`
				 *
				 * This action hook is triggered after displaying the audit log view.
				 *
				 * @param WSAL_AuditLogListView $this->_view - Audit log view object.
				 */
				do_action( 'wsal_auditlog_after_view', $this->get_view() );
		?>
			</div>
		</form>

		<?php
		if (
			Settings_Helper::current_user_can( 'edit' )
			&& ! \WSAL\Helpers\Settings_Helper::get_boolean_option_value( 'setup-complete', false )
			&& ! \WSAL\Helpers\Settings_Helper::get_boolean_option_value( 'setup-modal-dismissed', false )
		) :
			?>
			<div data-remodal-id="wsal-setup-modal">
				<button data-remodal-action="close" class="remodal-close"></button>
				<p><?php esc_html_e( 'Thank you for installing WP Activity Log. Do you want to run the wizard to configure the basic plugin settings?', 'wp-security-audit-log' ); ?></p>
				<br>
				<button data-remodal-action="confirm" class="remodal-confirm"><?php esc_html_e( 'Yes', 'wp-security-audit-log' ); ?></button>
				<button data-remodal-action="cancel" class="remodal-cancel"><?php esc_html_e( 'No', 'wp-security-audit-log' ); ?></button>
				<?php wp_nonce_field( 'wsal_dismiss_setup_modal', 'wsal-dismiss-setup-modal', false, true ); ?>
			</div>

			<script type="text/javascript">
				jQuery( document ).ready( function() {
					var wsal_setup_modal = jQuery( '[data-remodal-id="wsal-setup-modal"]' );
					wsal_setup_modal.remodal().open();

					jQuery(document).on('confirmation', wsal_setup_modal, function () {
						window.location = '<?php echo esc_url( add_query_arg( 'page', 'wsal-setup', network_admin_url( 'index.php' ) ) ); ?>';
					});

					jQuery(document).on('closed', wsal_setup_modal, function () {
						wsal_dismiss_setup_modal();
					});
				});
			</script>
			<?php
		endif;

		$is_search_view = class_exists( 'WSAL_SearchExtension' ) && ( ! empty( $this->page_args->search_filters ) || ! empty( $this->page_args->search_term ) );
		?>
		<script type="text/javascript">
			jQuery( document ).ready( function() {
				WsalAuditLogInit(
					<?php
					echo wp_json_encode(
						array(
							'ajaxurl'     => admin_url( 'admin-ajax.php' ),
							'tr8n'        => array(
								'numofitems' => __( 'Please enter the number of alerts you would like to see on one page:', 'wp-security-audit-log' ),
								'searchback' => __( 'All Sites', 'wp-security-audit-log' ),
								'searchnone' => __( 'No Results', 'wp-security-audit-log' ),
							),
							// 'autorefresh' => array(
							// 	'enabled' => ! $is_search_view && ! WSAL\Helpers\Settings_Helper::get_option_value( 'disable-refresh' ),
							// 	'token'   => $this->plugin->settings()->is_infinite_scroll() ? $this->get_total_events() : $this->get_view()->get_total_items(),
							// ),
						)
					);
					?>
				);
			} );
		</script>
		<?php
	}

	/**
	 * Ajax callback to display meta data inspector.
	 */
	public function AjaxInspector() {
		if ( ! Settings_Helper::current_user_can( 'view' ) ) {
			die( 'Access Denied.' );
		}

		// Filter $_GET array for security.
		$get_array = filter_input_array( INPUT_GET );

		if ( ! isset( $get_array['occurrence'] ) ) {
			die( 'Occurrence parameter expected.' );
		}

		$wsal_db = Connection::get_connection();

		// phpcs:disable
		// phpcs:enable

		$alert_meta = Occurrences_Entity::get_meta_array( (int) $get_array['occurrence'], array(), $wsal_db );

		unset( $alert_meta['ReportText'] );

		// Set WSAL_Ref class scripts and styles.
		// WSAL_Ref::config( 'stylePath', esc_url( WSAL_BASE_DIR ) . '/css/wsal-ref.css' );
		// WSAL_Ref::config( 'scriptPath', esc_url( WSAL_BASE_DIR ) . '/js/wsal-ref.js' );

		echo '<div class="event-content-wrapper">';
		//wsal_r( $alert_meta );
		foreach ( $alert_meta as $item => $value ) {
			if ( $value ) {
				if ( is_array( $value ) || is_object( $value ) ) {
					$value = var_export( $value, true );
				}
				echo '<strong>' . $item . ':</strong> <span style="opacity: 0.7;">' . $value . '</span></br>';
			}
		}
		wp_die();
	}

	/**
	 * Ajax callback to refresh the view.
	 */
	public function ajax_refresh() {
		if ( ! Settings_Helper::current_user_can( 'view' ) ) {
			die( 'Access Denied.' );
		}

		// Filter $_POST array for security.
		$post_array = filter_input_array( INPUT_POST );

		// If log count is not set then return error.
		if ( ! isset( $post_array['logcount'] ) ) {
			die( 'Log count parameter expected.' );
		}

		// Total number of alerts.
		$old = (int) $post_array['logcount'];

		// phpcs:disable
		// phpcs:enable

		// Check for new total number of alerts.
		$new = (int) Occurrences_Entity::count();

		// If the count is changed, then return the new count.
		echo $old === $new ? 'false' : esc_html( $new );
		die;
	}

	/**
	 * Ajax callback to set number of alerts to
	 * show on a single page.
	 */
	public function ajax_set_items_per_page() {
		if ( ! Settings_Helper::current_user_can( 'view' ) ) {
			die( 'Access Denied.' );
		}

		// Filter $_POST array for security.
		$post_array = filter_input_array( INPUT_POST );

		if ( ! isset( $post_array['count'] ) ) {
			die( 'Count parameter expected.' );
		}
		$this->plugin->settings()->set_views_per_page( (int) $post_array['count'] );
		die;
	}

	/**
	 * Ajax callback to search.
	 */
	public function ajax_search_site() {
		if ( ! Settings_Helper::current_user_can( 'view' ) ) {
			die( 'Access Denied.' );
		}

		// Filter $_POST array for security.
		$post_array = filter_input_array( INPUT_POST );

		if ( ! isset( $post_array['search'] ) ) {
			die( 'Search parameter expected.' );
		}
		$grp1 = array();
		$grp2 = array();

		$search = $post_array['search'];

		foreach ( $this->get_view()->get_sites() as $site ) {
			if ( stripos( $site->blogname, $search ) !== false ) {
				$grp1[] = $site;
			} elseif ( stripos( $site->domain, $search ) !== false ) {
				$grp2[] = $site;
			}
		}
		die( json_encode( array_slice( $grp1 + $grp2, 0, 7 ) ) ); // phpcs:ignore
	}

	// phpcs:disable
	// phpcs:enable

	/**
	 * Ajax callback to download failed login log.
	 */
	public function wsal_download_failed_login_log() {
		if ( ! isset( $_POST['download_nonce'] ) ) {
			echo esc_html__( 'Nonce verification failed.', 'wp-security-audit-log' );
			die();
		}
		// Get post array through filter.
		$download_nonce = \sanitize_text_field( \wp_unslash( $_POST['download_nonce'] ) );

		// Verify nonce.
		if ( empty( $download_nonce ) || ! wp_verify_nonce( $download_nonce, 'wsal-download-failed-logins' ) ) {
			echo esc_html__( 'Nonce verification failed.', 'wp-security-audit-log' );
			die();
		}

		// Get alert by id.
		$alert_id = filter_input( INPUT_POST, 'alert_id', FILTER_SANITIZE_NUMBER_INT );

		// Get users using alert meta.
		$users = Occurrences_Entity::get_meta_value( array( 'id' => (int) $alert_id ), 'Users', array() );

		// Check if there are any users.
		if ( ! empty( $users ) && is_array( $users ) ) {
			// Prepare content.
			$content = implode( ',', $users );
			echo esc_html( $content );
		} else {
			echo esc_html__( 'No users found.', 'wp-security-audit-log' );
		}

		die();
	}

	/**
	 * Ajax callback to handle Freemius opt in/out.
	 */
	public function wsal_freemius_opt_in() {
		// Die if not have access.
		if ( ! Settings_Helper::current_user_can( 'edit' ) ) {
			die( 'Access Denied.' );
		}

		// Get post array through filter.
		$nonce  = \sanitize_text_field( \wp_unslash( $_POST['opt_nonce'] ) ); // Nonce.
		$choice = \sanitize_text_field( \wp_unslash( $_POST['choice'] ) ); // Choice selected by user.

		// Verify nonce.
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'wsal-freemius-opt' ) ) {
			// Nonce verification failed.
			echo wp_json_encode(
				array(
					'success' => false,
					'message' => esc_html__( 'Nonce verification failed.', 'wp-security-audit-log' ),
				)
			);
			exit();
		}

		// Check if choice is not empty.
		if ( ! empty( $choice ) ) {
			if ( 'yes' === $choice ) {
				if ( ! WP_Helper::is_multisite() ) {
					wsal_freemius()->opt_in(); // Opt in.
				} else {
					// Get sites.
					$sites      = Freemius::get_sites();
					$sites_data = array();

					if ( ! empty( $sites ) ) {
						foreach ( $sites as $site ) {
							$sites_data[] = wsal_freemius()->get_site_info( $site );
						}
					}
					wsal_freemius()->opt_in( false, false, false, false, false, false, false, false, $sites_data );
				}

				// Update Freemius state.
				\WSAL\Helpers\Settings_Helper::set_option_value( 'freemius_state', 'in', true );
			} elseif ( 'no' === $choice ) {
				if ( ! WP_Helper::is_multisite() ) {
					wsal_freemius()->skip_connection(); // Opt out.
				} else {
					wsal_freemius()->skip_connection( null, true ); // Opt out for all websites.
				}

				// Update Freemius state.
				\WSAL\Helpers\Settings_Helper::set_option_value( 'freemius_state', 'skipped', true );
			}

			echo wp_json_encode(
				array(
					'success' => true,
					'message' => esc_html__( 'Freemius opt choice selected.', 'wp-security-audit-log' ),
				)
			);
		} else {
			echo wp_json_encode(
				array(
					'success' => false,
					'message' => esc_html__( 'Freemius opt choice not found.', 'wp-security-audit-log' ),
				)
			);
		}
		exit();
	}

	/**
	 * Method: Render header of the view.
	 */
	public function header() {
		add_thickbox();

		// Darktooltip styles.
		wp_enqueue_style(
			'darktooltip',
			WSAL_BASE_URL . '/css/darktooltip.css',
			array(),
			'0.4.0'
		);

		// Remodal styles.
		wp_enqueue_style( 'wsal-remodal', WSAL_BASE_URL . '/css/remodal.css', array(), WSAL_VERSION );
		wp_enqueue_style( 'wsal-remodal-theme', WSAL_BASE_URL . '/css/remodal-default-theme.css', array(), WSAL_VERSION );

		// Audit log styles.
		wp_enqueue_style(
			'auditlog',
			WSAL_BASE_URL . '/css/auditlog.css',
			array(),
			WSAL_VERSION
		);

		// Admin notices styles.
		wp_enqueue_style(
			'wsal_admin_notices',
			WSAL_BASE_URL . '/css/admin-notices.css',
			array(),
			WSAL_VERSION
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function footer() {
		wp_enqueue_script( 'jquery' );

		// Darktooltip js.
		wp_enqueue_script(
			'darktooltip', // Identifier.
			WSAL_BASE_URL . '/js/jquery.darktooltip.js', // Script location.
			array( 'jquery' ), // Depends on jQuery.
			WSAL_VERSION, // Script version.
			true
		);

		// Remodal script.
		wp_enqueue_script(
			'wsal-remodal-js',
			WSAL_BASE_URL . '/js/remodal.min.js',
			array(),
			WSAL_VERSION,
			true
		);

		// WP Suggest Script.
		wp_enqueue_script( 'suggest' );

		// Audit log script.
		wp_register_script(
			'auditlog',
			WSAL_BASE_URL . '/js/auditlog.js',
			array(),
			WSAL_VERSION,
			true
		);

		$audit_log_data = array(
			'page'                 => isset( $this->page_args->page ) ? $this->page_args->page : false,
			'siteId'               => isset( $this->page_args->site_id ) ? $this->page_args->site_id : false,
			'orderBy'              => isset( $this->page_args->order_by ) ? $this->page_args->order_by : false,
			'order'                => isset( $this->page_args->order ) ? $this->page_args->order : false,
			'searchTerm'           => isset( $this->page_args->search_term ) ? $this->page_args->search_term : false,
			'searchFilters'        => isset( $this->page_args->search_filters ) ? $this->page_args->search_filters : false,
			'closeInspectorString' => esc_html__( 'Close inspector', 'wp-security-audit-log' ),
			'viewerNonce'          => wp_create_nonce( 'wsal_auditlog_viewer_nonce' ),
			'infiniteScroll'       => $this->plugin->settings()->is_infinite_scroll(),
			'userView'             => ( in_array( $this->user_last_view, $this->supported_view_types(), true ) ) ? $this->user_last_view : self::$default_view,
			'installAddonStrings'  => array(
				'defaultButton'    => esc_html__( 'Install and activate extension', 'wp-security-audit-log' ),
				'installingText'   => esc_html__( 'Installing extension', 'wp-security-audit-log' ),
				'otherInstalling'  => esc_html__( 'Other extension installing', 'wp-security-audit-log' ),
				'addonInstalled'   => esc_html__( 'Installed', 'wp-security-audit-log' ),
				'installedReload'  => esc_html__( 'Installed... reloading page', 'wp-security-audit-log' ),
				'buttonError'      => esc_html__( 'Problem enabling', 'wp-security-audit-log' ),
				'msgError'         => sprintf(
					/* translators: 1 - an opening link tag, 2 - the closing tag. */
					__( '<br>An error occurred when trying to install and activate the plugin. Please try install it again from the %1$sevent settings%2$s page.', 'wp-security-audit-log' ),
					List_Events::get_third_party_plugins_tab_url(),
					'</a>'
				),
			),
		);
		wp_localize_script( 'auditlog', 'wsalAuditLogArgs', $audit_log_data );
		wp_enqueue_script( 'auditlog' );
	}

	/**
	 * Method: Load WSAL Notice Pointer.
	 *
	 * @param string $hook_suffix - Current hook suffix.
	 * @since 3.2
	 */
	public function load_pointers( $hook_suffix ) {
		// Don't run on WP < 3.3.
		if ( get_bloginfo( 'version' ) < '3.3' ) {
			return;
		}

		// Don't display notice if the wizard notice is showing.
		if (
			! \WSAL\Helpers\Settings_Helper::get_boolean_option_value( 'setup-complete', false )
			&& ! \WSAL\Helpers\Settings_Helper::get_boolean_option_value( 'setup-modal-dismissed', false )
		) {
			return;
		}

		// Get screen id.
		$screen    = get_current_screen();
		$screen_id = $screen->id;

		// Get pointers for this screen.
		$pointers = apply_filters( 'wsal_pointers_' . $screen_id, array() );

		if ( ! $pointers || ! is_array( $pointers ) ) {
			return;
		}

		// Get dismissed pointers.
		$dismissed      = explode( ',', (string) \WSAL\Helpers\Settings_Helper::get_option_value( 'dismissed-privacy-notice', true ) );
		$valid_pointers = array();

		// Check pointers and remove dismissed ones.
		foreach ( $pointers as $pointer_id => $pointer ) {
			// Sanity check.
			if (
				in_array( $pointer_id, $dismissed ) // phpcs:ignore
				|| empty( $pointer )
				|| empty( $pointer_id )
				|| empty( $pointer['target'] )
				|| empty( $pointer['options'] )
			) {
				continue;
			}
			$pointer['pointer_id'] = $pointer_id;

			// Add the pointer to $valid_pointers array.
			$valid_pointers['pointers'][] = $pointer;
		}

		// No valid pointers? Stop here.
		if ( empty( $valid_pointers ) ) {
			return;
		}

		// Add pointers style to queue.
		wp_enqueue_style( 'wp-pointer' );

		// Add pointers script to queue. Add custom script.
		wp_enqueue_script(
			'auditlog-pointer',
			WSAL_BASE_URL . '/js/auditlog-pointer.js',
			array( 'wp-pointer' ),
			WSAL_VERSION,
			true
		);

		// Add pointer options to script.
		wp_localize_script( 'auditlog-pointer', 'wsalPointer', $valid_pointers );
	}

	/**
	 * Method: Register privacy pointer for WSAL.
	 *
	 * @param array $pointer - Current screen pointer array.
	 * @return array
	 * @since 3.2
	 */
	public function register_privacy_pointer( $pointer ) {
		$is_current_view = $this->plugin->views->get_active_view() == $this; // phpcs:ignore
		if ( current_user_can( 'manage_options' ) && $is_current_view && ! isset( $pointer['wsal_privacy'] ) ) {
			$pointer['wsal_privacy'] = array(
				'target'  => '#toplevel_page_wsal-auditlog .wp-first-item',
				'options' => array(
					'content'  => sprintf(
						'<h3> %s </h3> <p> %s </p> <p><strong>%s</strong></p>',
						esc_html__( 'WordPress Activity Log', 'wp-security-audit-log' ),
						esc_html__( 'When a user makes a change on your website the plugin will keep a record of that event here. Right now there is nothing because this is a new install.', 'wp-security-audit-log' ),
						esc_html__( 'Thank you for using WP Activity Log', 'wp-security-audit-log' )
					),
					'position' => array(
						'edge'  => 'left',
						'align' => 'top',
					),
				),
			);
		}
		return $pointer;
	}

	/**
	 * Method: Ajax request handler to dismiss pointers.
	 *
	 * @since 3.2.4
	 */
	public function dismiss_wp_pointer() {
		// @codingStandardsIgnoreStart
		$pointer = sanitize_text_field( wp_unslash( $_POST['pointer'] ) );
		// @codingStandardsIgnoreEnd

		if ( $pointer != sanitize_key( $pointer ) ) { // phpcs:ignore
			wp_die( 0 );
		}

		$dismissed = array_filter( explode( ',', (string) \WSAL\Helpers\Settings_Helper::get_option_value( 'dismissed-privacy-notice', true ) ) );

		if ( in_array( $pointer, $dismissed ) ) { // phpcs:ignore
			wp_die( 0 );
		}

		$dismissed[] = $pointer;
		$dismissed   = implode( ',', $dismissed );

		\WSAL\Helpers\Settings_Helper::set_option_value( 'dismissed-privacy-notice', $dismissed );
		wp_die( 1 );
	}

	/**
	 * Infinite Scroll Events AJAX handler.
	 *
	 * @since 3.3.1.1
	 */
	public function infinite_scroll_events() {
		// Check user permissions.
		if ( ! Settings_Helper::current_user_can( 'view' ) ) {
			die( esc_html__( 'Access Denied', 'wp-security-audit-log' ) );
		}

		// Verify nonce.
		if ( ! isset( $_POST['wsal_viewer_security'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wsal_viewer_security'] ) ), 'wsal_auditlog_viewer_nonce' ) ) {
			die( esc_html__( 'Nonce verification failed.', 'wp-security-audit-log' ) );
		}

		// Get $_POST arguments.
		$paged = isset( $_POST['page_number'] ) ? sanitize_text_field( wp_unslash( $_POST['page_number'] ) ) : 0;

		// Query events.
		$events_query = $this->get_view()->query_events( $paged );
		if ( ! empty( $events_query['items'] ) ) {
			foreach ( $events_query['items'] as $event ) {
				$this->get_view()->single_row( $event );
			}
		}
		exit();
	}

	/**
	 * Return the total number of events in audit log.
	 *
	 * @return int
	 */
	public function get_total_events() {
		return (int) Occurrences_Entity::count();
	}

	/**
	 * Method: Ajax request handler to dismiss setup modal.
	 *
	 * @since 4.1.4
	 */
	public function dismiss_setup_modal() {
		// Die if user does not have permission to dismiss.
		if ( ! Settings_Helper::current_user_can( 'edit' ) ) {
			echo wp_json_encode(
				array(
					'success' => false,
					'message' => esc_html__( 'You do not have sufficient permissions to dismiss this notice.', 'wp-security-audit-log' ),
				)
			);
			die();
		}

		// Filter $_POST array for security.
		$nonce  = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : false; // phpcs:ignore

		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'wsal_dismiss_setup_modal' ) ) {
			// Nonce verification failed.
			echo wp_json_encode(
				array(
					'success' => false,
					'message' => esc_html__( 'Nonce verification failed.', 'wp-security-audit-log' ),
				)
			);
			die();
		}

		\WSAL\Helpers\Settings_Helper::set_boolean_option_value( 'setup-modal-dismissed', true, true );
		wp_send_json_success();
	}

	// phpcs:disable
	// phpcs:enable
}
