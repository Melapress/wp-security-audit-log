<?php
/**
 * Settings Page
 *
 * Settings page of the plugin.
 *
 * @since      1.0.0
 * @package    wsal
 * @subpackage views
 */

use WSAL\Helpers\WP_Helper;
use WSAL\Helpers\User_Helper;
use WSAL\Controllers\Constants;
use WSAL\Controllers\Connection;
use WSAL\Helpers\Settings_Helper;
use WSAL\Controllers\Alert_Manager;
use WSAL\Controllers\Database_Manager;
use WSAL\Helpers\Plugin_Settings_Helper;
use WSAL\Entities\Archive\Delete_Records;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class: WSAL_Views_Settings
 *
 * Settings view class to handle settings page functions.
 *
 * @since      1.0.0
 *
 * @package    wsal
 * @subpackage views
 */
class WSAL_Views_Settings extends WSAL_AbstractView {

	/**
	 * Adapter Message.
	 *
	 * @var string
	 */
	public $adapter_msg = '';

	/**
	 * WSAL Setting Tabs.
	 *
	 * @since 3.2.3
	 *
	 * @var array
	 */
	private $wsal_setting_tabs = array();

	/**
	 * Current Setting Tab.
	 *
	 * @since 3.2.3
	 *
	 * @var string
	 */
	private $current_tab = '';

	/**
	 * Method: Constructor.
	 *
	 * @param WpSecurityAuditLog $plugin - Instance of WpSecurityAuditLog.
	 */
	public function __construct( WpSecurityAuditLog $plugin ) {
		parent::__construct( $plugin );
		add_action( 'admin_init', array( $this, 'setup_settings_tabs' ) );
		add_action( 'wp_ajax_AjaxCheckSecurityToken', array( $this, 'ajax_check_security_token' ) );
		add_action( 'wp_ajax_AjaxRunCleanup', array( $this, 'ajax_run_cleanup' ) );
		add_action( 'wp_ajax_AjaxGetAllUsers', array( $this, 'ajax_get_all_users' ) );
		add_action( 'wp_ajax_AjaxGetAllRoles', array( $this, 'ajax_get_all_roles' ) );
		add_action( 'wp_ajax_AjaxGetAllCPT', array( $this, 'ajax_get_all_cpts' ) );
		add_action( 'wp_ajax_AjaxGetAllStatuses', array( $this, 'ajax_get_all_stati' ) );
		add_action( 'wp_ajax_wsal_reset_settings', array( $this, 'reset_settings' ) );
		add_action( 'wp_ajax_wsal_purge_activity', array( $this, 'purge_activity' ) );
		add_action( 'wp_ajax_wsal_ajax_get_all_severities', array( $this, 'ajax_get_all_severities' ) );
		add_action( 'wp_ajax_wsal_ajax_get_all_event_types', array( $this, 'ajax_get_all_event_types' ) );
		add_action( 'wp_ajax_wsal_ajax_get_all_object_types', array( $this, 'ajax_get_all_object_types' ) );
		add_action( 'wp_ajax_wsal_ajax_get_all_event_ids', array( $this, 'ajax_get_all_event_ids' ) );
	}

	/**
	 * Setup WSAL Settings Page Tabs.
	 *
	 * @since 3.4
	 */
	public function setup_settings_tabs() {

		// Verify that the current page is WSAL settings page.
		$page = isset( $_GET['page'] ) ? \sanitize_text_field( wp_unslash( $_GET['page'] ) ) : false;
		if ( empty( $page ) || $this->get_safe_view_name() !== $page ) {
			return;
		}

		// Tab links.
		$wsal_setting_tabs = array(
			'general'           => array(
				'name'     => esc_html__( 'General', 'wp-security-audit-log' ),
				'link'     => add_query_arg( 'tab', 'general', $this->get_url() ),
				'render'   => array( $this, 'tab_general' ),
				'save'     => array( $this, 'tab_general_save' ),
				'priority' => 10,
			),
			'audit-log'         => array(
				'name'     => esc_html__( 'Activity log viewer', 'wp-security-audit-log' ),
				'link'     => add_query_arg( 'tab', 'audit-log', $this->get_url() ),
				'render'   => array( $this, 'tab_audit_log' ),
				'save'     => array( $this, 'tab_audit_log_save' ),
				'priority' => 20,
			),
			'file-changes'      => array(
				'name'     => esc_html__( 'File changes', 'wp-security-audit-log' ),
				'link'     => add_query_arg( 'tab', 'file-changes', $this->get_url() ),
				'render'   => array( $this, 'tab_file_changes' ),
				'priority' => 30,
			),
			'exclude-objects'   => array(
				'name'     => esc_html__( 'Exclude objects', 'wp-security-audit-log' ),
				'link'     => add_query_arg( 'tab', 'exclude-objects', $this->get_url() ),
				'render'   => array( $this, 'tab_exclude_objects' ),
				'save'     => array( $this, 'tab_exclude_objects_save' ),
				'priority' => 40,
			),
			'advanced-settings' => array(
				'name'     => esc_html__( 'Advanced settings', 'wp-security-audit-log' ),
				'link'     => add_query_arg( 'tab', 'advanced-settings', $this->get_url() ),
				'render'   => array( $this, 'tab_advanced_settings' ),
				'save'     => array( $this, 'tab_advanced_settings_save' ),
				'priority' => 100,
			),
		);

		/**
		 * Filter: `wsal_setting_tabs`
		 *
		 * This filter is used to filter the tabs of WSAL settings page.
		 *
		 * Setting tabs structure:
		 *     $wsal_setting_tabs['unique-tab-id'] = array(
		 *         'name'     => Name of the tab,
		 *         'link'     => Link of the tab,
		 *         'render'   => This function is used to render HTML elements in the tab,
		 *         'name'     => This function is used to save the related setting of the tab,
		 *         'priority' => Priority of the tab,
		 *     );
		 *
		 * @param array $wsal_setting_tabs – Array of WSAL Setting Tabs.
		 *
		 * @since 3.2.3
		 */
		$wsal_setting_tabs = apply_filters( 'wsal_setting_tabs', $wsal_setting_tabs );

		// Sort by priority.
		array_multisort( array_column( $wsal_setting_tabs, 'priority' ), SORT_ASC, $wsal_setting_tabs );

		$this->wsal_setting_tabs = $wsal_setting_tabs;

		// Get the current tab.
		$current_tab       = ( isset( $_GET['tab'] ) ) ? \sanitize_text_field( \wp_unslash( $_GET['tab'] ) ) : '';
		$this->current_tab = empty( $current_tab ) ? 'general' : $current_tab;
	}

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
		return esc_html__( 'Settings', 'wp-security-audit-log' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_icon() {
		return 'dashicons-admin-generic';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name() {
		return esc_html__( 'Settings', 'wp-security-audit-log' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_weight() {
		return 8;
	}

	/**
	 * Method: Save settings.
	 *
	 * @throws Exception - Unrecognized settings tab error.
	 */
	protected function save() {
		// Bail early if user does not have sufficient permissions to save.
		if ( ! Settings_Helper::current_user_can( 'edit' ) ) {
			throw new Exception( esc_html__( 'Current user is not allowed to save settings.', 'wp-security-audit-log' ) );
		}
		// Call respective tab save functions if they are set. Nonce is already verified at this point.
		if ( ! empty( $this->current_tab ) && ! empty( $this->wsal_setting_tabs[ $this->current_tab ]['save'] ) ) {
			call_user_func( $this->wsal_setting_tabs[ $this->current_tab ]['save'] );
		} else {
			throw new Exception( esc_html__( 'Unknown settings tab.', 'wp-security-audit-log' ) );
		}
	}

	/**
	 * Method: Check security token.
	 */
	public function ajax_check_security_token() {
		if ( ! Settings_Helper::current_user_can( 'edit' ) ) {
			echo wp_json_encode(
				array(
					'success' => false,
					'message' => esc_html__( 'Access Denied.', 'wp-security-audit-log' ),
				)
			);
			die();
		}

		$nonce = isset( $_POST['nonce'] ) ? \sanitize_text_field( \wp_unslash( $_POST['nonce'] ) ) : false;
		$token = isset( $_POST['token'] ) ? \sanitize_text_field( \wp_unslash( $_POST['token'] ) ) : false;

		if ( empty( $nonce ) || ! \wp_verify_nonce( $nonce, 'wsal-exclude-nonce' ) ) {
			echo wp_json_encode(
				array(
					'success' => false,
					'message' => esc_html__( 'Nonce verification failed.', 'wp-security-audit-log' ),
				)
			);
			die();
		}

		if ( empty( $token ) ) {
			echo wp_json_encode(
				array(
					'success' => false,
					'message' => esc_html__( 'Invalid input.', 'wp-security-audit-log' ),
				)
			);
			die();
		}

		$input_type = isset( $_POST['type'] ) ? \sanitize_text_field( \wp_unslash( $_POST['type'] ) ) : false;

		echo wp_json_encode(
			array(
				'success'   => true,
				'token'     => $token,
				'tokenType' => esc_html( Plugin_Settings_Helper::get_token_type( $token, $input_type ) ),
			)
		);
		die();
	}

	/**
	 * Method: Run cleanup.
	 */
	public function ajax_run_cleanup() {
		// Verify nonce.
		if ( ! isset( $_REQUEST['nonce'] ) || false === wp_verify_nonce( \sanitize_text_field( \wp_unslash( $_REQUEST['nonce'] ) ), 'wsal-run-cleanup' ) ) {
			wp_send_json_error( esc_html__( 'Insecure request.', 'wp-security-audit-log' ) );
		}

		if ( ! Settings_Helper::current_user_can( 'edit' ) ) {
			die( 'Access Denied.' );
		}

		$now       = time();
		$max_sdate = Plugin_Settings_Helper::get_pruning_date(); // Pruning date.
		$archiving = Settings_Helper::is_archiving_set_and_enabled();

		// phpcs:disable
		// phpcs:enable

		// Calculate limit timestamp.
		$max_stamp = $now - ( strtotime( $max_sdate ) - $now );

		$items = array();

		if ( $archiving ) {
			$connection_name = Settings_Helper::get_option_value( 'archive-connection' );

			$wsal_db = Connection::get_connection( $connection_name );

			$items = Delete_Records::delete( array(), 0, array( 'created_on <= %s' => intval( $max_stamp ) ), $wsal_db );
		}

		$main_items = Delete_Records::delete( array(), 0, array( 'created_on <= %s' => intval( $max_stamp ) ) );

		if ( $archiving ) {
			$archiving_args = array(
				'page' => 'wsal-ext-settings',
				'tab'  => 'archiving',
			);
			$archiving_url  = add_query_arg( $archiving_args, \network_admin_url( 'admin.php' ) );
			wp_safe_redirect( $archiving_url );
		} else {
			if ( ( is_array( $items ) && count( $items ) ) || ( is_array( $main_items ) && count( $main_items ) ) ) {
				$redirect_args = array(
					'tab'     => 'audit-log',
					'pruning' => 1,
				);
			} else {
				$redirect_args = array(
					'tab'     => 'audit-log',
					'pruning' => 0,
				);
			}
			wp_safe_redirect( add_query_arg( $redirect_args, $this->get_url() ) );
		}
		exit;
	}

	/**
	 * {@inheritDoc}
	 */
	public function render() {
		// Verify nonce if a form is submitted.
		if ( isset( $_POST['_wpnonce'] ) ) {
			check_admin_referer( 'wsal-settings' );
		}

		if ( ! Settings_Helper::current_user_can( 'edit' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-security-audit-log' ) );
		}

		// Check to see if section parameter is set in the URL.
		$section = isset( $_GET['section'] ) ? \sanitize_text_field( wp_unslash( $_GET['section'] ) ) : false;

		if ( isset( $_POST['submit'] ) ) {
			try {
				$this->save(); // Save settings.
				if ( 'sms-provider' === $this->current_tab && $section && 'test' === $section ) :
					?>
					<div class="updated">
						<p><?php esc_html_e( 'Message sent successfully.', 'wp-security-audit-log' ); ?></p>
					</div>
				<?php else : ?>
					<div class="updated">
						<p><?php esc_html_e( 'Settings have been saved.', 'wp-security-audit-log' ); ?></p>
					</div>
					<?php
				endif;
			} catch ( Exception $ex ) {
				?>
				<div class="error">
					<p><?php esc_html_e( 'Error: ', 'wp-security-audit-log' ); ?><?php echo esc_html( $ex->getMessage() ); ?></p>
				</div>
				<?php
			}
		}

		if ( isset( $_POST['import'] ) ) {
			call_user_func( $this->wsal_setting_tabs[ $this->current_tab ]['save'] );
		}

		if ( isset( $_GET['pruning'] ) && '1' === $_GET['pruning'] ) {
			?>
			<div class="updated">
				<p><?php esc_html_e( 'Old data successfully purged.', 'wp-security-audit-log' ); ?></p>
			</div>
			<?php
		} elseif ( isset( $_GET['pruning'] ) && '0' === $_GET['pruning'] ) {
			?>
			<div class="error">
				<p><?php esc_html_e( 'No data is old enough to be purged.', 'wp-security-audit-log' ); ?></p>
			</div>
			<?php
		}
		?>
		<nav id="wsal-tabs" class="nav-tab-wrapper">
			<?php foreach ( $this->wsal_setting_tabs as $tab_id => $tab ) : ?>
				<a href="<?php echo esc_url( $tab['link'] ); ?>" class="nav-tab <?php echo ( $tab_id === $this->current_tab ) ? 'nav-tab-active' : false; ?>">
					<?php echo esc_html( $tab['name'] ); ?>
				</a>
			<?php endforeach; ?>
		</nav>

		<form id="audit-log-settings" method="post">
			<input type="hidden" name="page" value="<?php echo isset( $_GET['page'] ) ? esc_attr( \sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) : false; ?>" />
			<input type="hidden" id="ajaxurl" value="<?php echo esc_attr( admin_url( 'admin-ajax.php' ) ); ?>" />
			<?php wp_nonce_field( 'wsal-settings' ); ?>

			<div id="audit-log-adverts"></div>
			<div class="nav-tabs">
				<?php
				if ( ! empty( $this->current_tab ) && ! empty( $this->wsal_setting_tabs[ $this->current_tab ]['render'] ) ) {
					call_user_func( $this->wsal_setting_tabs[ $this->current_tab ]['render'] );
				} else {
					call_user_func( $this->wsal_setting_tabs['general']['render'] );
				}
				?>
			</div>
			<?php
			if ( 'sms-provider' === $this->current_tab && $section && 'test' === $section ) {
				\submit_button( esc_html__( 'Send Message', 'wp-security-audit-log' ) );
			} elseif ( 'settings-export-import' !== $this->current_tab ) {
					\submit_button();
			}
			?>
		</form>

		<script>
			function delete_confirm(elementRef) {
				if (elementRef.checked) {
					if ( window.confirm('<?php esc_html_e( 'Do you want to remove all data when the plugin is deleted?', 'wp-security-audit-log' ); ?>') == false ) {
						elementRef.checked = false;
						// Ensure the "no" option is reselected.
						jQuery('#delete_data_no').click();
					}
				}
			}

			jQuery( document ).ready( function() {
				// Enable/disable setting.
				function wsal_update_setting( checkbox, setting ) {
					if ( checkbox.prop( 'checked' ) ) {
						setting.removeProp( 'disabled' );
					} else {
						setting.prop( 'disabled', 'disabled' );
					}
				}

				// Login page notification settings.
				var login_page_notif = jQuery( 'input[name=login_page_notification]' );
				var login_page_notif_text = jQuery( '#login_page_notification_text' );

				// Check the change event on checkbox.
				login_page_notif.on( 'change', function() {
					wsal_update_setting( login_page_notif, login_page_notif_text );
				} );

				// Proxy settings.
				var proxy_ip_setting = jQuery( 'input[name=EnableProxyIpCapture]' );
				var ip_filtering = jQuery( '#EnableIpFiltering' );
				wsal_update_setting( proxy_ip_setting, ip_filtering );
				proxy_ip_setting.on( 'change', function() {
					wsal_update_setting( proxy_ip_setting, ip_filtering );
				} );
			} );
			</script>
		<?php
	}

	/**
	 * Tab: `General`
	 */
	private function tab_general() {
		$enforced_settings                                   = Plugin_Settings_Helper::get_mainwp_enforced_settings();
		$login_page_notification_settings_enforced_by_mainwp = array_key_exists( 'login_notification_enabled', $enforced_settings );
		$incognito_setting_enforced_by_mainwp                = array_key_exists( 'incognito_mode_enabled', $enforced_settings );

		?>

		<h3><?php esc_html_e( 'Display latest events widget in Dashboard & Admin bar', 'wp-security-audit-log' ); ?></h3>
		<p class="description">
			<?php
			echo sprintf(
				/* translators: Max number of dashboard widget alerts. */
				esc_html__( 'The events widget displays the latest %d security events in the dashboard and the admin bar notification displays the latest event.', 'wp-security-audit-log' ),
				esc_html( Settings_Helper::DASHBOARD_WIDGET_MAX_ALERTS )
			);
			?>
		</p>
		<table class="form-table wsal-tab">
			<tbody>
			<tr>
				<th><label for="dwoption_on"><?php esc_html_e( 'Dashboard Widget', 'wp-security-audit-log' ); ?></label></th>
				<td>
					<fieldset>
						<?php $dwe = ! Settings_Helper::get_boolean_option_value( 'disable-widgets' ); ?>
						<label for="dwoption_on">
							<input type="radio" name="EnableDashboardWidgets" id="dwoption_on" style="margin-top: -2px;" <?php checked( $dwe ); ?> value="1">
							<span><?php esc_html_e( 'Yes', 'wp-security-audit-log' ); ?></span>
						</label>
						<br/>
						<label for="dwoption_off">
							<input type="radio" name="EnableDashboardWidgets" id="dwoption_off" style="margin-top: -2px;" <?php checked( $dwe, false ); ?>  value="0">
							<span><?php esc_html_e( 'No', 'wp-security-audit-log' ); ?></span>
						</label>
					</fieldset>
				</td>
			</tr>
			<!-- / Events Dashboard Widget -->

			<tr>
				<?php
				$disabled = '';
				$label    = esc_html__( 'Admin Bar Notification', 'wp-security-audit-log' );
				if ( wsal_freemius()->is_free_plan() ) {
					$disabled = 'disabled';
					$label    = esc_html__( 'Admin Bar Notification', 'wp-security-audit-log' );
				}
				?>
				<th><label for="admin_bar_notif_on"><?php echo esc_html( $label ); ?></label></th>
				<td>
					<fieldset <?php echo esc_attr( $disabled ); ?>>
						<?php $abn = ! Settings_Helper::get_boolean_option_value( 'disable-admin-bar-notif', true ); ?>
						<label for="admin_bar_notif_on">
							<input type="radio" name="admin_bar_notif" id="admin_bar_notif_on" style="margin-top: -2px;" <?php checked( $abn ); ?> value="1">
							<span><?php esc_html_e( 'Yes', 'wp-security-audit-log' ); ?></span>
						</label>
						<br/>
						<label for="admin_bar_notif_off">
							<input type="radio" name="admin_bar_notif" id="admin_bar_notif_off" style="margin-top: -2px;" <?php checked( $abn, false ); ?>  value="0">
							<span><?php esc_html_e( 'No', 'wp-security-audit-log' ); ?></span>
						</label>
					</fieldset>
				</td>
			</tr>
			<!-- / Admin Bar Notification -->

			<tr>
				<?php
				$disabled = '';
				$label    = esc_html__( 'Admin Bar Notification Updates', 'wp-security-audit-log' );
				if ( wsal_freemius()->is_free_plan() ) {
					$disabled = 'disabled';
					$label    = esc_html__( 'Admin Bar Notification Updates', 'wp-security-audit-log' );
				}
				?>
				<th><label for="admin_bar_notif_refresh"><?php echo esc_html( $label ); ?></label></th>
				<td>
					<fieldset <?php echo esc_attr( $disabled ); ?>>
						<?php $abn_updates = WSAL\Helpers\Settings_Helper::get_option_value( 'admin-bar-notif-updates', 'page-refresh' ); ?>
						<label for="admin_bar_notif_realtime">
							<input type="radio" name="admin_bar_notif_updates" id="admin_bar_notif_realtime" style="margin-top: -2px;" <?php checked( $abn_updates, 'real-time' ); ?> value="real-time">
							<span><?php esc_html_e( 'Update in near real time', 'wp-security-audit-log' ); ?></span>
						</label>
						<br/>
						<label for="admin_bar_notif_refresh">
							<input type="radio" name="admin_bar_notif_updates" id="admin_bar_notif_refresh" style="margin-top: -2px;" <?php checked( $abn_updates, 'page-refresh' ); ?>  value="page-refresh">
							<span><?php esc_html_e( 'Update only on page refreshes', 'wp-security-audit-log' ); ?></span>
						</label>
					</fieldset>
				</td>
			</tr>
			<!-- / Admin Bar Notification Updates -->
			</tbody>
		</table>
		<!-- Dashboard Widget -->

		<h3><?php esc_html_e( 'Add user notification on the WordPress login page', 'wp-security-audit-log' ); ?></h3>
		<p class="description"><?php esc_html_e( 'Many compliance regulations (such as the GDPR) require website administrators to tell the users of their website that all the changes they do when logged in are being logged.', 'wp-security-audit-log' ); ?></p>
		<table class="form-table wsal-tab">
			<tbody>
			<tr>
				<th><label for="login_page_notification"><?php esc_html_e( 'Login Page Notification', 'wp-security-audit-log' ); ?></label></th>
				<td>
					<fieldset <?php echo disabled( $login_page_notification_settings_enforced_by_mainwp ); ?>>
						<?php
						// Get login page notification checkbox.
						$wsal_lpn = Settings_Helper::get_boolean_option_value( 'login_page_notification', false );
						if ( $wsal_lpn && 'true' === $wsal_lpn ) {
							// If option exists, value is true then set to true.
							$wsal_lpn = true;
						} elseif ( $wsal_lpn && 'false' === $wsal_lpn ) {
							// If option exists, value is false then set to false.
							$wsal_lpn = false;
						} elseif ( ! $wsal_lpn ) {
							// Default option value.
							$wsal_lpn = false;
						}
						?>
						<label for="wsal_lpn_yes">
							<input type="radio" name="login_page_notification" id="wsal_lpn_yes" <?php checked( $wsal_lpn ); ?> value="true" />
							<?php esc_html_e( 'Yes', 'wp-security-audit-log' ); ?>
						</label>
						<br />
						<?php
						// Get login page notification text.
						$wsal_lpn_text = WSAL\Helpers\Settings_Helper::get_option_value( 'login_page_notification_text', false );
						if ( ! $wsal_lpn_text ) {
							$wsal_lpn_text = __( 'For security and auditing purposes, a record of all of your logged-in actions and changes within the WordPress dashboard will be recorded in an activity log with the <a href="https://melapress.com/?utm_source=plugin&utm_medium=referral&utm_campaign=wsal&utm_content=settings+pages" target="_blank">WP Activity Log plugin</a>. The audit log also includes the IP address where you accessed this site from.', 'wp-security-audit-log' );
						}
						// Allowed HTML tags for this setting.
						$allowed_tags = array(
							'a' => array(
								'href'   => array(),
								'title'  => array(),
								'target' => array(),
							),
						);

						?>
						<textarea name="login_page_notification_text"
								id="login_page_notification_text"
								cols="60" rows="6"
								<?php echo ( $wsal_lpn ) ? false : 'disabled'; ?>
							><?php echo wp_kses( $wsal_lpn_text, $allowed_tags ); ?></textarea>
						<br/>
						<p class="description">
							<?php echo wp_kses( __( '<strong>Note: </strong>', 'wp-security-audit-log' ), Plugin_Settings_Helper::get_allowed_html_tags() ) . esc_html__( 'The only HTML code allowed in the login page notification is for links ( < a href >< /a > ).', 'wp-security-audit-log' ); // phpcs:ignore ?>
						</p>
						<br />

						<label for="wsal_lpn_no">
							<input type="radio" name="login_page_notification" id="wsal_lpn_no" <?php checked( $wsal_lpn, false ); ?> value="false" />
							<?php esc_html_e( 'No', 'wp-security-audit-log' ); ?>
						</label>
					</fieldset>
				</td>
			</tr>
			<!-- / Login Page Notification -->
			</tbody>
		</table>
		<!-- Login Page Notification -->

		<h3><?php esc_html_e( 'Is your website running behind a firewall or reverse proxy?', 'wp-security-audit-log' ); ?></h3>
		<p class="description">
			<?php
			echo sprintf(
				/* translators: Learn more link. */
				esc_html__( 'If your website is running behind a firewall set this option to yes so the plugin retrieves the end user’s IP address from the proxy header - %s.', 'wp-security-audit-log' ),
				'<a href="https://melapress.com/support/kb/wp-activity-log-support-reverse-proxies-web-application-firewalls/?utm_source=plugin&utm_medium=link&utm_campaign=wsal" target="_blank">' . esc_html__( 'learn more', 'wp-security-audit-log' ) . '</a>'
			);
			?>
		</p>
		<table class="form-table wsal-tab">
			<tbody>
			<tr>
				<th><label for="pioption_on"><?php esc_html_e( 'Reverse Proxy / Firewall Options', 'wp-security-audit-log' ); ?></label></th>
				<td>
					<fieldset>
						<label for="enable_proxy_ip_capture_yes">
							<input type="radio" name="EnableProxyIpCapture" value="1" id="enable_proxy_ip_capture_yes" <?php checked( Settings_Helper::get_boolean_option_value( 'use-proxy-ip' ) ); ?> />
							<?php esc_html_e( 'Yes', 'wp-security-audit-log' ); ?>
						</label>
						<br/>
						<label for="EnableIpFiltering">
							<input type="checkbox" name="EnableIpFiltering" value="1" id="EnableIpFiltering" <?php checked( Plugin_Settings_Helper::is_internal_ips_filtered() ); ?> />
							<?php esc_html_e( 'Filter internal IP addresses from the proxy headers. Enable this option only if you are	are still seeing the internal IP addresses of the firewall or proxy.', 'wp-security-audit-log' ); ?>
						</label>
						<br/>
						<label for="enable_proxy_ip_capture_no">
							<input type="radio" name="EnableProxyIpCapture" value="0" id="enable_proxy_ip_capture_no" <?php checked( Settings_Helper::get_boolean_option_value( 'use-proxy-ip' ), false ); ?> />
							<?php esc_html_e( 'No', 'wp-security-audit-log' ); ?>
						</label>
						<br />
					</fieldset>
				</td>
			</tr>
			<!-- / Reverse Proxy / Firewall Options -->
			</tbody>
		</table>
		<!-- Reverse Proxy -->

		<h3><?php esc_html_e( 'Who can change the plugin settings?', 'wp-security-audit-log' ); ?></h3>
		<p class="description">
			<?php
			$allowed_tags = array(
				'a' => array(
					'href'   => true,
					'target' => true,
				),
			);
			echo wp_kses(
				sprintf(
					/* translators: Learn more link. */
					esc_html__( 'By default only users with administrator role (single site) and super administrator role (multisite) can change the settings of the plugin. Though you can restrict the privileges to just your user - %s.', 'wp-security-audit-log' ),
					'<a href="https://melapress.com/support/kb/wp-activity-log-managing-plugin-privileges/?utm_source=plugin&utm_medium=link&utm_campaign=wsal" target="_blank">' . __( 'learn more', 'wp-security-audit-log' ) . '</a>'
				),
				$allowed_tags
			);
			$restrict_settings = Plugin_Settings_Helper::get_restrict_plugin_setting();
			?>
		</p>
		<table class="form-table wsal-tab">
			<tbody>
			<tr>
				<th><label for="RestrictAdmins"><?php esc_html_e( 'Restrict plugin access', 'wp-security-audit-log' ); ?></label></th>
				<td>
					<fieldset>
						<label for="only_me">
							<input type="radio" name="restrict-plugin-settings" id="only_me" value="only_me" <?php checked( $restrict_settings, 'only_me' ); ?> />
							<?php esc_html_e( 'Only me', 'wp-security-audit-log' ); ?>
						</label>
						<br/>
						<label for="only_admins">
							<input type="radio" name="restrict-plugin-settings" id="only_admins" value="only_admins" <?php checked( $restrict_settings, 'only_admins' ); ?> />
							<?php
							if ( WP_Helper::is_multisite() ) {
								esc_html_e( 'All superadmins', 'wp-security-audit-log' );
							} else {
								esc_html_e( 'All administrators', 'wp-security-audit-log' );
							}
							?>
						</label>
						<br/>
					</fieldset>
				</td>
			</tr>
			<!-- / Restrict Plugin Access -->
			</tbody>
		</table>
		<!-- Restrict Plugin Access -->

		<h3><?php esc_html_e( 'Allow other users to view the activity log', 'wp-security-audit-log' ); ?></h3>
		<p class="description">
			<?php
			$is_multisite = WP_Helper::is_multisite();
			if ( $is_multisite ) {
				$section_label = esc_html__( 'By default only super administrators and the child sites\' administrators can view the WordPress activity log. Though you can change this by using the setting below.', 'wp-security-audit-log' );
			} else {
				$section_label = esc_html__( 'You can specify the username of the user that you want to allow. If you want to add all the users with a specific role, you can also specify their role here.', 'wp-security-audit-log' );
			}

			echo wp_kses(
				$section_label . ' - <a href="https://melapress.com/support/kb/wp-activity-log-allow-users-read-access-activity-log/?utm_source=plugin&utm_medium=link&utm_campaign=wsal" target="_blank">' . __( 'learn more', 'wp-security-audit-log' ) . '</a>',
				$allowed_tags
			);
			?>
		</p>
		<?php if ( $is_multisite ) : ?>
			<table class="form-table wsal-tab">
				<tbody>
				<tr>
					<th><?php esc_html_e( 'Can view events', 'wp-security-audit-log' ); ?></th>
					<td>
						<fieldset>
							<?php
							$restrict_settings          = Plugin_Settings_Helper::get_restrict_log_viewer();
							$viewer_restriction_options = array(
								'only_me'          => __( 'Only me', 'wp-security-audit-log' ),
								'only_superadmins' => __( 'Super administators only', 'wp-security-audit-log' ),
								'only_admins'      => __( 'Super administators and site administrators', 'wp-security-audit-log' ),
							);
							?>
							<?php foreach ( $viewer_restriction_options as $option => $label ) : ?>
								<label for="<?php esc_attr( 'log_viewer_' . $option ); ?>">
									<?php $disabled = ( 'only_me' === $option && 'only_superadmins' === $restrict_settings ); ?>
									<input type="radio" name="restrict-log-viewer" id="<?php esc_attr( 'log_viewer_' . $option ); ?>" value="<?php echo esc_attr( $option ); ?>" <?php checked( $restrict_settings, $option ); ?> <?php disabled( $disabled ); ?> />
									<?php echo esc_html( $label ); ?>
								</label>
								<br/>
							<?php endforeach; ?>
						</fieldset>
					</td>
				</tr>
				</tbody>
			</table>
			<p class="description"><?php esc_html_e( 'To allow someone who does not have an admin role to view the activity log, specify them in the below setting.', 'wp-security-audit-log' ); ?></p>
		<?php endif; ?>
		<table class="form-table wsal-tab">
			<tbody>
				<tr>
					<?php $row_label = $is_multisite ? esc_html__( 'Can also view events', 'wp-security-audit-log' ) : esc_html__( 'Can view events', 'wp-security-audit-log' ); ?>
					<th><label for="ViewerQueryBox"><?php echo $row_label; // phpcs:ignore ?></label></th>
					<td>
						<fieldset>
							<label>
								<input type="text" id="ViewerQueryBox" style="width: 250px;">
								<input type="button" id="ViewerQueryAdd" class="button-primary" value="<?php esc_attr_e( 'Add', 'wp-security-audit-log' ); ?>">

								<p class="description">
									<?php esc_html_e( 'Specify the username or the users which do not have an admin role but can also see the WordPress activity role. You can also specify roles.', 'wp-security-audit-log' ); ?>
								</p>
							</label>

							<div id="ViewerList">
								<?php
								foreach ( Plugin_Settings_Helper::get_allowed_plugin_viewers() as $item ) :
									if ( User_Helper::get_current_user()->user_login === $item ) {
										continue;
									}
									?>
									<span class="sectoken-<?php echo esc_attr( Plugin_Settings_Helper::get_token_type( $item ) ); ?>">
									<input type="hidden" name="Viewers[]" value="<?php echo esc_attr( $item ); ?>"/>
									<?php echo esc_html( $item ); ?>
									<a href="javascript:;" title="<?php esc_attr_e( 'Remove', 'wp-security-audit-log' ); ?>">&times;</a>
									</span>
								<?php endforeach; ?>
							</div>
						</fieldset>
					</td>
				</tr>
				<!-- / Can View Alerts -->
			</tbody>
		</table>
		<!-- Can View Events -->

		<h3><?php esc_html_e( 'Which email address should the plugin use as a from address?', 'wp-security-audit-log' ); ?></h3>
		<p class="description"><?php esc_html_e( 'By default when the plugin sends an email notification it uses the email address specified in this website’s general settings. Though you can change the email address and display name from this section.', 'wp-security-audit-log' ); ?></p>
		<table class="form-table wsal-tab">
			<tbody>
				<tr>
					<th><label for="FromEmail"><?php esc_html_e( 'From Email & Name', 'wp-security-audit-log' ); ?></label></th>
					<td>
						<fieldset>
							<?php $use_email = Settings_Helper::get_option_value( 'use-email', 'default_email' ); ?>
							<label for="default_email">
								<input type="radio" name="use-email" id="default_email" value="default_email" <?php checked( $use_email, 'default_email' ); ?> />
								<?php esc_html_e( 'Use the email address from the WordPress general settings', 'wp-security-audit-log' ); ?>
							</label>
							<br>
							<label for="custom_email">
								<input type="radio" name="use-email" id="custom_email" value="custom_email" <?php checked( $use_email, 'custom_email' ); ?> />
								<?php esc_html_e( 'Use another email address', 'wp-security-audit-log' ); ?>
							</label>
							<br>
							<label for="FromEmail">
								<?php esc_html_e( 'Email Address', 'wp-security-audit-log' ); ?>
								<input type="email" id="FromEmail" name="FromEmail" value="<?php echo esc_attr( WSAL\Helpers\Settings_Helper::get_option_value( 'from-email' ) ); ?>" />
							</label>
							<br>
							<label for="DisplayName">
								<?php esc_html_e( 'Display Name', 'wp-security-audit-log' ); ?>&nbsp;
								<input type="text" id="DisplayName" name="DisplayName" value="<?php echo esc_attr( WSAL\Helpers\Settings_Helper::get_option_value( 'display-name' ) ); ?>" />
							</label>
						</fieldset>
					</td>
				</tr>
				<!-- / From Email & Name -->
			</tbody>
		</table>
		<!-- From Email & Name -->

		<?php
		$is_incognito = Settings_Helper::get_boolean_option_value( 'hide-plugin' );
		?>
		<h3><?php esc_html_e( 'Do you want to hide the plugin from the list of installed plugins?', 'wp-security-audit-log' ); ?></h3>
		<p class="description"><?php esc_html_e( 'By default all installed plugins are listed in the plugins page. Set this option to Yes remove WP Activity Log from the list of installed plugins for users who are unable to access the WP Activity Log settings.', 'wp-security-audit-log' ); ?></p>
		<table class="form-table wsal-tab">
			<tbody>
				<tr>
					<th><label for="incognito_yes"><?php esc_html_e( 'Hide Plugin in Plugins Page', 'wp-security-audit-log' ); ?></label></th>
					<td>
						<fieldset <?php echo disabled( $incognito_setting_enforced_by_mainwp ); ?>>
							<label for="incognito_yes">
								<input type="radio" name="Incognito" value="yes" id="incognito_yes" <?php checked( $is_incognito ); ?> />
								<?php esc_html_e( 'Yes, hide the plugin from the list of installed plugins', 'wp-security-audit-log' ); ?>
							</label>
							<br/>
							<label for="incognito_no">
								<input type="radio" name="Incognito" value="no" id="incognito_no" <?php checked( $is_incognito, false ); ?> />
								<?php esc_html_e( 'No, do not hide the plugin', 'wp-security-audit-log' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
				<!-- / Hide Plugin in Plugins Page -->
			</tbody>
		</table>
		<!-- Hide Plugin -->
		<?php
	}

	/**
	 * Save: `General`
	 */
	private function tab_general_save() {
		// Get $_POST global array.
		$post_array = filter_input_array( INPUT_POST );

		// Settings_Helper::set_option_value( 'disable-refresh', ! $post_array['EnableAuditViewRefresh'] );
		Settings_Helper::set_option_value( 'use-email', \sanitize_text_field( $post_array['use-email'] ) );
		Settings_Helper::set_option_value( 'from-email', trim( \sanitize_email( $post_array['FromEmail'] ) ) );
		Settings_Helper::set_option_value( 'display-name', trim( \sanitize_text_field( $post_array['DisplayName'] ) ) );
		Settings_Helper::set_boolean_option_value( 'disable-widgets', ! \sanitize_text_field( $post_array['EnableDashboardWidgets'] ) );

		if ( ! wsal_freemius()->is_free_plan() ) {
			Settings_Helper::set_boolean_option_value( 'disable-admin-bar-notif', ! \sanitize_text_field( $post_array['admin_bar_notif'] ), true );
			Settings_Helper::set_option_value( 'admin-bar-notif-updates', \sanitize_text_field( $post_array['admin_bar_notif_updates'] ), true );
		}

		// Handle log viewer settings in multisite context.
		if ( WP_Helper::is_multisite() ) {
			$log_viewer_restrictions = isset( $post_array['restrict-log-viewer'] ) ? \sanitize_text_field( $post_array['restrict-log-viewer'] ) : '';
			Plugin_Settings_Helper::set_restrict_log_viewer( $log_viewer_restrictions );
			if ( 'only_me' === $log_viewer_restrictions ) {
				Plugin_Settings_Helper::set_only_me_user_id( \get_current_user_id() );
			}
		}

		// Get plugin viewers.
		$viewers = isset( $post_array['Viewers'] ) ? array_map( 'sanitize_text_field', $post_array['Viewers'] ) : array();
		Plugin_Settings_Helper::set_allowed_plugin_viewers( $viewers );

		// Handle plugin settings permissions.
		$restrict_settings = isset( $post_array['restrict-plugin-settings'] ) ? \sanitize_text_field( $post_array['restrict-plugin-settings'] ) : false;
		Plugin_Settings_Helper::set_restrict_plugin_setting( $restrict_settings );
		if ( 'only_me' === $restrict_settings ) {
			Plugin_Settings_Helper::set_only_me_user_id( get_current_user_id() );
		}

		Plugin_Settings_Helper::set_login_page_notification( isset( $post_array['login_page_notification'] ) ? \sanitize_text_field( $post_array['login_page_notification'] ) : false );
		Plugin_Settings_Helper::set_login_page_notification_text( isset( $post_array['login_page_notification_text'] ) ? $post_array['login_page_notification_text'] : false );
		Plugin_Settings_Helper::set_main_ip_from_proxy( isset( $post_array['EnableProxyIpCapture'] ) ? \sanitize_text_field( $post_array['EnableProxyIpCapture'] ) : false );
		Plugin_Settings_Helper::set_internal_ips_filtering( isset( $post_array['EnableIpFiltering'] ) ? \sanitize_text_field( $post_array['EnableIpFiltering'] ) : false );

		$is_incognito = isset( $post_array['Incognito'] ) ? Settings_Helper::string_to_bool( \sanitize_text_field( $post_array['Incognito'] ) ) : false;
		Plugin_Settings_Helper::set_incognito( $is_incognito );
	}

	/**
	 * Tab: `Audit Log`
	 */
	private function tab_audit_log() {
		?>
		<h3><?php esc_html_e( 'For how long do you want to keep the activity log events (Retention settings)?', 'wp-security-audit-log' ); ?></h3>
		<p class="description">
			<?php
			esc_html_e( 'The plugin uses an efficient way to store the activity log data in the WordPress database, though the more data you keep the more disk space will be required. ', 'wp-security-audit-log' );
			$retention_help_text = __( '<a href="https://melapress.com/wordpress-activity-log/pricing/?utm_source=plugin&utm_medium=link&utm_campaign=wsal" target="_blank">Upgrade to Premium</a> to store the activity log data in an external database.', 'wp-security-audit-log' );

			// phpcs:disable
			// phpcs:enable
			echo wp_kses( $retention_help_text, Plugin_Settings_Helper::get_allowed_html_tags() );
			?>
		</p>

		<?php
		// phpcs:disable
		/* @free:start */
		// phpcs:enable
		// Ensure it doesn't load a 2nd time for premium users.
		if ( ! wsal_freemius()->can_use_premium_code() ) {
			$this->render_retention_settings_table();
		}
		// phpcs:disable
		/* @free:end */
		// phpcs:enable
		?>

		<h3><?php esc_html_e( 'What timestamp you would like to see in the WordPress activity log?', 'wp-security-audit-log' ); ?></h3>
		<p class="description"><?php esc_html_e( 'Note that the WordPress\' timezone might be different from that configured on the server so when you switch from UTC to WordPress timezone or vice versa you might notice a big difference.', 'wp-security-audit-log' ); ?></p>
		<table class="form-table wsal-tab">
			<tbody>
				<tr>
					<th><label for="timezone-default"><?php esc_html_e( 'Events Timestamp', 'wp-security-audit-log' ); ?></label></th>
					<td>
						<fieldset>
							<?php
							$timezone = Settings_Helper::get_timezone();

							/**
							 * Transform timezone values.
							 *
							 * @since 3.2.3
							 */
							if ( '0' === $timezone ) {
								$timezone = 'utc';
							} elseif ( '1' === $timezone ) {
								$timezone = 'wp';
							}
							?>
							<label for="timezone-default">
								<input type="radio" name="Timezone" id="timezone-default" style="margin-top: -2px;"
									<?php checked( $timezone, 'utc' ); ?> value="utc">
								<?php esc_html_e( 'UTC', 'wp-security-audit-log' ); ?>
							</label>
							<br/>
							<label for="timezone">
								<input type="radio" name="Timezone" id="timezone" style="margin-top: -2px;"
									<?php checked( $timezone, 'wp' ); ?> value="wp">
								<?php esc_html_e( 'Timezone configured on this WordPress website', 'wp-security-audit-log' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
				<!-- Alerts Timestamp -->
				<tr>
					<th><?php esc_html_e( 'Show Milliseconds', 'wp-security-audit-log' ); ?></th>
					<td>
						<fieldset>
							<?php $show_milliseconds = Settings_Helper::get_show_milliseconds(); ?>
							<label for="show_milliseconds">
								<input type="checkbox" name="show_milliseconds" id="show_milliseconds" style="margin-top: -2px;"
									<?php checked( $show_milliseconds ); ?> value="yes">
								<?php esc_html_e( 'Show Milliseconds in list view', 'wp-security-audit-log' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
				<!-- Alerts Timestamp -->
			</tbody>
		</table>
		<!-- Timestamp -->

		<h3><?php esc_html_e( 'What user information should be displayed in the WordPress activity log?', 'wp-security-audit-log' ); ?></h3>
		<p class="description"><?php esc_html_e( 'Usernames might not be the same as a user\'s first and last name so it can be difficult to recognize whose user was that did a change. When there is no first & last name or public display name configured the plugin will revert back to the WordPress username.', 'wp-security-audit-log' ); ?></p>
		<table class="form-table wsal-tab">
			<tbody>
				<tr>
					<th><label for="timezone-default"><?php esc_html_e( 'User information in Activity log', 'wp-security-audit-log' ); ?></label></th>
					<td>
						<fieldset>
							<?php $type_username = Plugin_Settings_Helper::get_type_username(); ?>
							<label for="column_username">
								<input type="radio" name="type_username" id="column_username" style="margin-top: -2px;" <?php checked( $type_username, 'username' ); ?> value="username">
								<span><?php esc_html_e( 'WordPress username', 'wp-security-audit-log' ); ?></span>
							</label>
							<br/>
							<label for="columns_first_last_name">
								<input type="radio" name="type_username" id="columns_first_last_name" style="margin-top: -2px;" <?php checked( $type_username, 'first_last_name' ); ?> value="first_last_name">
								<span><?php esc_html_e( 'First name & last name', 'wp-security-audit-log' ); ?></span>
							</label>
							<br/>
							<label for="columns_display_name">
								<input type="radio" name="type_username" id="columns_display_name" style="margin-top: -2px;" <?php checked( $type_username, 'display_name' ); ?> value="display_name">
								<span><?php esc_html_e( 'Configured public display name', 'wp-security-audit-log' ); ?></span>
							</label>
						</fieldset>
					</td>
				</tr>
				<!-- Select type of name -->
			</tbody>
		</table>
		<!-- User Information -->

		<?php $is_wp_backend = Settings_Helper::get_boolean_option_value( 'wp-backend' ); ?>
		<h3><?php esc_html_e( 'Do you want to keep a log of WordPress background activity?', 'wp-security-audit-log' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'WordPress does a lot of things in the background that you do not necessarily need to know about, such as; deletion of post revisions, deletion of auto saved drafts etc. By default the plugin does not report them since there might be a lot and are irrelevant to the user.', 'wp-security-audit-log' ); ?>
		</p>
		<table class="form-table wsal-tab">
			<tbody>
				<tr>
					<th><label for="wp_backend_no"><?php esc_html_e( 'Enable Events for WordPress Background Activity', 'wp-security-audit-log' ); ?></label></th>
					<td>
						<fieldset>
							<label for="wp_backend_yes">
								<input type="radio" name="WPBackend" value="1" id="wp_backend_yes" <?php checked( $is_wp_backend ); ?> />
								<?php esc_html_e( 'Yes', 'wp-security-audit-log' ); ?>
							</label>
							<br/>
							<label for="wp_backend_no">
								<input type="radio" name="WPBackend" value="0" id="wp_backend_no" <?php checked( ! $is_wp_backend ); ?> />
								<?php esc_html_e( 'No', 'wp-security-audit-log' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
				<!-- Disable Alerts for WordPress Background activity -->
			</tbody>
		</table>
		<!-- Background Events -->
		<?php
	}

	/**
	 * Save: `Audit Log`
	 */
	private function tab_audit_log_save() {
		// Get $_POST global array.
		$post_array = filter_input_array( INPUT_POST );

		// Get pruning date.
		$pruning_date = isset( $post_array['PruningDate'] ) ? (int) \sanitize_text_field( $post_array['PruningDate'] ) : false;
		$pruning_unit = isset( $post_array['pruning-unit'] ) ? \sanitize_text_field( $post_array['pruning-unit'] ) : false;
		$pruning_date = ( ! empty( $pruning_date ) && ! empty( $pruning_unit ) ) ? $pruning_date . ' ' . $pruning_unit : false;

		$pruning_enabled = isset( $post_array['PruneBy'] ) ? 'date' === $post_array['PruneBy'] : '';
		Settings_Helper::set_pruning_date_settings( $pruning_enabled, $pruning_date, $pruning_unit );
		Plugin_Settings_Helper::set_timezone( $post_array['Timezone'] );
		Plugin_Settings_Helper::set_type_username( $post_array['type_username'] );
		Settings_Helper::set_boolean_option_value( 'wp-backend', isset( $post_array['WPBackend'] ) ? \sanitize_text_field( $post_array['WPBackend'] ) : false );

		$show_milliseconds = isset( $post_array['show_milliseconds'] ) && 'yes' === $post_array['show_milliseconds'];
		Plugin_Settings_Helper::set_show_milliseconds( $show_milliseconds );
	}

	/**
	 * Tab: `File Changes`
	 */
	private function tab_file_changes() {
		?>

		<table class="form-table wsal-tab">
			<tbody>
				<tr>
					<?php if ( ! defined( 'WFCM_PLUGIN_FILE' ) && ! defined( 'MFM_BASE_URL' ) ) : ?>
						<div class="addon-wrapper" style="max-width: 380px; text-align: center; border: 1px solid #ccc; padding: 25px;">
							<img src="<?php echo esc_html( trailingslashit( WSAL_BASE_URL ) . 'img/help/website-file-changes-monitor.jpg' ); ?>">
							<h4><?php echo esc_html__( 'Melapress File Monitor', 'wp-security-audit-log' ); ?></h4>
							<p><?php echo esc_html__( 'To keep a log of file changes please install Melapress File Monitor, a plugin which is also developed by us.', 'wp-security-audit-log' ); ?></p><br>
							<p><button class="install-addon button button-primary" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wsal-install-addon' ) ); ?>" data-plugin-slug="website-file-changes-monitor/website-file-changes-monitor.php" data-plugin-download-url="https://downloads.wordpress.org/plugin/website-file-changes-monitor.latest-stable.zip"><?php esc_html_e( 'Install plugin now', 'wp-security-audit-log' ); ?></button><span class="spinner" style="display: none; visibility: visible; float: none; margin: 0 0 0 8px;"></span> <a href="https://melapress.com/support/kb/wp-activity-log-wordpress-files-changes-warning-activity-logs/?utm_source=plugin&utm_medium=link&utm_campaign=wsal" rel="noopener noreferrer" target="_blank" style="margin-left: 15px;"><?php esc_html_e( 'Learn More', 'wp-security-audit-log' ); ?></a></p>
						</div>
					<?php else : ?>
						<?php
						$redirect_args = array(
							'page' => 'file-monitor-settings',
						);

						$wcfm_settings_page = add_query_arg( $redirect_args, \network_admin_url( 'admin.php' ) );
						?>
						<p><?php echo esc_html__( 'Configure how often file changes scan run and other settings from the', 'wp-security-audit-log' ); ?> <a class="button button-primary" href="<?php echo esc_url( $wcfm_settings_page ); ?>"><?php echo esc_html__( 'Melapress File Monitor plugin settings', 'wp-security-audit-log' ); ?></a></p>
					<?php endif; ?>
				</tr>
			</tbody>
		</table>
		<style>
			#submit {
				display: none !important;
			}
		</style>
		<!-- / File Changes Logging Tab -->
		<?php
	}

	/**
	 * Tab: `Exclude Objects`
	 */
	private function tab_exclude_objects() {
		?>
		<p class="description"><?php esc_html_e( 'By default the plugin keeps a log of all user changes done on your WordPress website. Use the setting below to exclude any objects from the activity log. When an object is excluded from the activity log, any event in which that object is referred will not be logged in the activity log.', 'wp-security-audit-log' ); ?></p>
		<table class="form-table wsal-tab">
			<tbody>
				<tr>
					<th><label for="ExUserQueryBox"><?php esc_html_e( 'Exclude Users:', 'wp-security-audit-log' ); ?></label></th>
					<td>
						<fieldset>
							<input type="text" id="ExUserQueryBox" style="width: 250px;">
							<input type="button" id="ExUserQueryAdd" class="button-primary" value="<?php esc_attr_e( 'Add', 'wp-security-audit-log' ); ?>">
							<br style="clear: both;"/>
							<div id="ExUserList">
								<?php foreach ( Settings_Helper::get_excluded_monitoring_users() as $item ) : ?>
									<span class="sectoken-<?php echo esc_attr( Plugin_Settings_Helper::get_token_type( $item ) ); ?>">
									<input type="hidden" name="ExUsers[]" value="<?php echo esc_attr( $item ); ?>"/>
									<?php echo esc_html( $item ); ?>
									<a href="javascript:;" title="<?php esc_attr_e( 'Remove', 'wp-security-audit-log' ); ?>">&times;</a>
									</span>
								<?php endforeach; ?>
							</div>
						</fieldset>
					</td>
				</tr>
				<!-- Exclude Users -->

				<tr>
					<th><label for="ExRoleQueryBox"><?php esc_html_e( 'Exclude Roles:', 'wp-security-audit-log' ); ?></label></th>
					<td>
						<fieldset>
							<input type="text" id="ExRoleQueryBox" style="width: 250px;">
							<input type="button" id="ExRoleQueryAdd" class="button-primary" value="<?php esc_attr_e( 'Add', 'wp-security-audit-log' ); ?>">
							<br style="clear: both;"/>
							<div id="ExRoleList">
								<?php foreach ( Settings_Helper::get_excluded_monitoring_roles() as $item ) : ?>
									<span class="sectoken-<?php echo esc_attr( Plugin_Settings_Helper::get_token_type( $item ) ); ?>">
									<input type="hidden" name="ExRoles[]" value="<?php echo esc_attr( $item ); ?>"/>
									<?php echo esc_html( $item ); ?>
									<a href="javascript:;" title="<?php esc_attr_e( 'Remove', 'wp-security-audit-log' ); ?>">&times;</a>
									</span>
								<?php endforeach; ?>
							</div>
						</fieldset>
					</td>
				</tr>
				<!-- Exclude Roles -->

				<tr>
					<th><label for="IpAddrQueryBox"><?php esc_html_e( 'Exclude IP Address(es):', 'wp-security-audit-log' ); ?></label></th>
					<td>
						<fieldset>
							<input type="text" id="IpAddrQueryBox" style="width: 250px;">
							<input type="button" id="IpAddrQueryAdd" class="button-primary" value="<?php esc_attr_e( 'Add', 'wp-security-audit-log' ); ?>">
							<br style="clear: both;"/>
							<div id="IpAddrList">
								<?php foreach ( Settings_Helper::get_excluded_monitoring_ips() as $item ) : ?>
									<span class="sectoken-<?php echo esc_attr( Plugin_Settings_Helper::get_token_type( $item ) ); ?>">
										<input type="hidden" name="IpAddrs[]" value="<?php echo esc_attr( $item ); ?>"/>
										<?php echo esc_html( $item ); ?>
										<a href="javascript:;" title="<?php esc_attr_e( 'Remove', 'wp-security-audit-log' ); ?>">&times;</a>
									</span>
							<?php endforeach; ?>
						</div>
					</fieldset>
					<p class="description"><?php esc_html_e( 'You can exclude an individual IP address or a range of IP addresses. To exclude a range use the following format: [first IP]-[last octet of the last IP]. Example: 172.16.180.6-127.', 'wp-security-audit-log' ); ?></p>
				</td>
			</tr>
			<!-- Exclude IP Addresses -->

			<tr>
				<th><label for="ExCPTsQueryBox"><?php esc_html_e( 'Exclude Post Type:', 'wp-security-audit-log' ); ?></label></th>
				<td>
					<fieldset>
						<input type="text" id="ExCPTsQueryBox" style="width: 250px;">
						<input type="button" id="ExCPTsQueryAdd" class="button-primary" value="<?php esc_attr_e( 'Add', 'wp-security-audit-log' ); ?>">
						<br style="clear: both;"/>
						<div id="ExCPTsList">
							<?php foreach ( Settings_Helper::get_excluded_post_types() as $item ) : ?>
								<span class="sectoken-<?php echo esc_attr( Plugin_Settings_Helper::get_token_type( $item ) ); ?>">
										<input type="hidden" name="ExCPTss[]" value="<?php echo esc_attr( $item ); ?>"/>
										<?php echo esc_html( $item ); ?>
										<a href="javascript:;" title="<?php esc_attr_e( 'Remove', 'wp-security-audit-log' ); ?>">&times;</a>
									</span>
							<?php endforeach; ?>
						</div>
					</fieldset>
					<p class="description"><?php esc_html_e( 'WordPress has the post and page post types by default though your website might use more post types (custom post types). You can exclude all post types, including the default WordPress ones.', 'wp-security-audit-log' ); ?></p>
				</td>
			</tr>
			<!-- Exclude Custom Post Types -->

			<tr>
				<th><label for="StatusQueryBox"><?php esc_html_e( 'Exclude Post Status:', 'wp-security-audit-log' ); ?></label></th>
				<td>
					<fieldset>
						<input type="text" id="StatusQueryBox" style="width: 250px;">
						<input type="button" id="StatusQueryAdd" class="button-primary" value="<?php esc_attr_e( 'Add', 'wp-security-audit-log' ); ?>">
						<br style="clear: both;"/>
						<div id="StatusList">
							<?php foreach ( Settings_Helper::get_excluded_post_statuses() as $item ) : ?>
								<span class="sectoken-<?php echo esc_attr( Plugin_Settings_Helper::get_token_type( $item ) ); ?>">
										<input type="hidden" name="Statuss[]" value="<?php echo esc_attr( $item ); ?>"/>
										<?php echo esc_html( $item ); ?>
										<a href="javascript:;" title="<?php esc_attr_e( 'Remove', 'wp-security-audit-log' ); ?>">&times;</a>
									</span>
							<?php endforeach; ?>
						</div>
					</fieldset>
					<p class="description"><?php esc_html_e( 'You can exclude posts which have a specific Post Status from the activity logs by specifying the Post Status in this setting.', 'wp-security-audit-log' ); ?></p>
				</td>
			</tr>
			<!-- Exclude Custom Post Status -->

				<?php
				$this->renderMetaExclusionSection(
					esc_html__( 'Exclude custom post fields:', 'wp-security-audit-log' ),
					Settings_Helper::get_excluded_post_meta_fields(),
					'PostMeta'
				);
				?>
				<!-- Exclude Custom Post Fields -->

				<?php
				$this->renderMetaExclusionSection(
					esc_html__( 'Exclude custom user fields:', 'wp-security-audit-log' ),
					Settings_Helper::get_excluded_user_meta_fields(),
					'UserMeta'
				);
				?>
				<!-- Exclude Custom User Fields -->

			</tbody>
		</table>
		<!-- / Exclude Objects Tab -->
		<?php
	}

	/**
	 * Renders form section for excluding metadata of certain object type.
	 *
	 * @param string $title  Section title.
	 * @param array  $values Values.
	 * @param string $type   Object type.
	 */
	private function renderMetaExclusionSection( $title, $values, $type ) {
		?>
		<tr>
			<th><label for="Custom<?php echo \esc_attr( $type ); ?>QueryBox"><?php echo $title; ?></label></th>
			<td>
				<fieldset data-type="<?php echo \esc_attr( $type ); ?>">
					<input type="text" id="<?php echo \esc_attr( $type ); ?>QueryBox" class="js-query-box" style="width: 250px;">
					<input type="button" id="<?php echo \esc_attr( $type ); ?>QueryAdd" class="js-query-add button-primary"
							value="<?php esc_attr_e( 'Add', 'wp-security-audit-log' ); ?>">
					<br style="clear: both;"/>
					<div id="<?php echo \esc_attr( $type ); ?>List" class="js-list">
						<?php foreach ( $values as $item ) : ?>
							<span class="sectoken-<?php echo esc_attr( Plugin_Settings_Helper::get_token_type( $item ) ); ?>">
										<input type="hidden" name="<?php echo \esc_attr( $type ); ?>s[]"
												value="<?php echo esc_attr( $item ); ?>"/>
										<?php echo esc_html( $item ); ?>
										<a href="javascript:;" title="<?php esc_attr_e( 'Remove', 'wp-security-audit-log' ); ?>">&times;</a>
									</span>
						<?php endforeach; ?>
					</div>
				</fieldset>
				<p class="description"><?php esc_html_e( 'You can use the * wildcard to exclude multiple matching custom fields. For example to exclude all custom fields starting with wp123 enter wp123*', 'wp-security-audit-log' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save: `Exclude Objects`
	 */
	private function tab_exclude_objects_save() {
		// Get $_POST global array.
		$post_array = filter_input_array( INPUT_POST );

		Settings_Helper::set_excluded_monitoring_users( isset( $post_array['ExUsers'] ) ? $post_array['ExUsers'] : array() );
		Settings_Helper::set_excluded_monitoring_roles( isset( $post_array['ExRoles'] ) ? $post_array['ExRoles'] : array() );
		Settings_Helper::set_excluded_post_meta_fields( isset( $post_array['PostMetas'] ) ? $post_array['PostMetas'] : array() );
		Settings_Helper::set_excluded_user_meta_fields( isset( $post_array['UserMetas'] ) ? $post_array['UserMetas'] : array() );
		Plugin_Settings_Helper::set_excluded_monitoring_ip( isset( $post_array['IpAddrs'] ) ? $post_array['IpAddrs'] : array() );
		Plugin_Settings_Helper::set_excluded_post_types( isset( $post_array['ExCPTss'] ) ? $post_array['ExCPTss'] : array() );
		Plugin_Settings_Helper::set_excluded_post_statuses( isset( $post_array['Statuss'] ) ? $post_array['Statuss'] : array() );
	}

	/**
	 * Tab: `Advanced Settings`
	 */
	private function tab_advanced_settings() {
		?>
		<p class="description">
			<?php esc_html_e( 'These settings are for advanced users.', 'wp-security-audit-log' ); ?>
			<?php echo sprintf( __( 'If you have any questions <a href="https://melapress.com/contact/?utm_source=plugin&utm_medium=referral&utm_source=plugin&utm_medium=link&utm_campaign=wsal" target="_blank">contact us</a>.', 'wp-security-audit-log' ), Plugin_Settings_Helper::get_allowed_html_tags() ); // phpcs:ignore ?>
		</p>

		<h3><?php esc_html_e( 'Reset plugin settings to default', 'wp-security-audit-log' ); ?></h3>
		<p class="description"><?php _e( 'Use this button to <em>factory reset</em> the plugin. This means that all the configured settings will be reset to default and all email notifications, scheduled reports, external database / third party services connections, archiving and mirroring rule will be deleted. NOTE: the activity log data will not be purged. Use the setting below to purge the activity log.', 'wp-security-audit-log' ); // phpcs:ignore ?></p>
		<table class="form-table wsal-tab">
			<tbody>
			<tr>
				<th><?php esc_html_e( 'Reset Settings', 'wp-security-audit-log' ); ?></th>
				<td>
					<a href="#wsal_reset_settings" class="button-primary js-settings-reset"><?php esc_html_e( 'RESET', 'wp-security-audit-log' ); ?></a>
				</td>
			</tr>
			</tbody>
		</table>

		<h3><?php esc_html_e( 'Purge the WordPress activity log', 'wp-security-audit-log' ); ?></h3>
		<p class="description"><?php esc_html_e( 'Click the Purge button below to delete all the data from the WordPress activity log and start afresh.', 'wp-security-audit-log' ); ?></p>
		<table class="form-table wsal-tab">
			<tbody>
				<tr>
					<th><?php esc_html_e( 'Purge Activity Log', 'wp-security-audit-log' ); ?></th>
					<td>
						<a href="#wsal_purge_activity" class="button-primary js-purge-reset"><?php esc_html_e( 'PURGE', 'wp-security-audit-log' ); ?></a>
						<!-- <span class="notice purge-notice notice-success" style="display: none; margin-left: 10px; padding: 6px 10px;"><?php esc_html_e( 'Activity log successfully purged', 'wp-security-audit-log' ); ?></span> -->
					</td>
				</tr>
			</tbody>
		</table>

		<?php $stealth_mode = Settings_Helper::get_boolean_option_value( 'mwp-child-stealth-mode', false ); ?>
		<h3><?php esc_html_e( 'MainWP Child Site Stealth Mode', 'wp-security-audit-log' ); ?></h3>
		<p class="description"><?php esc_html_e( 'This option is enabled automatically when the plugin detects the MainWP Child plugin on the site. When this setting is enabled plugin access is restricted to the administrator who installs the plugin, the plugin is not shown in the list of installed plugins and no admin notifications are shown. Disable this option to change the plugin to the default setup.', 'wp-security-audit-log' ); ?></p>
		<table class="form-table wsal-tab">
			<tbody>
				<tr>
					<th><label for="mwp_stealth_mode"><?php esc_html_e( 'Enable MainWP Child Site Stealth Mode', 'wp-security-audit-log' ); ?></label></th>
					<td>
						<fieldset <?php echo ! WpSecurityAuditLog::is_mainwp_active() ? 'disabled' : false; ?>>
							<label for="mwp_stealth_yes">
								<input type="radio" name="mwp_stealth_mode" value="yes" id="mwp_stealth_yes" <?php checked( $stealth_mode ); ?> />
								<?php esc_html_e( 'Yes', 'wp-security-audit-log' ); ?>
							</label>
							<br>
							<label for="mwp_stealth_no">
								<input type="radio" name="mwp_stealth_mode" value="no" id="mwp_stealth_no"
									<?php checked( $stealth_mode, false ); ?>
								/>
								<?php esc_html_e( 'No', 'wp-security-audit-log' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
			</tbody>
		</table>

		<?php
		$data_deletion_enabled = Settings_Helper::get_boolean_option_value( 'delete-data' );
		?>
		<h3><?php esc_html_e( 'Do you want to delete the plugin data from the database upon uninstall?', 'wp-security-audit-log' ); ?></h3>
		<p class="description"><?php esc_html_e( 'The plugin saves the activity log data and settings in the WordPress database. By default upon uninstalling the plugin the data is kept in the database so if it is installed again, you can still access the data. If the data is deleted it is not possible to recover it so you won\'t be able to access it again even when you reinstall the plugin.', 'wp-security-audit-log' ); ?></p>
		<table class="form-table wsal-tab">
			<tbody>
				<tr>
					<th><label for="DeleteData"><?php esc_html_e( 'Remove Data on Uninstall', 'wp-security-audit-log' ); ?></label></th>
					<td>
						<fieldset>
							<label for="delete_data_yes">
								<input type="radio" name="DeleteData" value="1" id="delete_data_yes" onclick="return delete_confirm(this);"
									<?php checked( $data_deletion_enabled ); ?>
								/>
								<?php esc_html_e( 'Yes', 'wp-security-audit-log' ); ?>
							</label>
							<br>
							<label for="delete_data_no">
								<input type="radio" name="DeleteData" value="0" id="delete_data_no"
									<?php checked( $data_deletion_enabled, false ); ?>
								/>
								<?php esc_html_e( 'No', 'wp-security-audit-log' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
				<!-- / Remove Data on Uninstall -->
			</tbody>
		</table>

		<div class="remodal" data-remodal-id="wsal_reset_settings">
			<button data-remodal-action="close" class="remodal-close"></button>
			<h3><?php esc_html_e( 'Are you sure you want to reset all the plugin settings to default? This action cannot be undone.', 'wp-security-audit-log' ); ?></h3>
			<br>
			<input type="hidden" id="wsal-reset-settings-nonce" value="<?php echo esc_attr( wp_create_nonce( 'wsal-reset-settings' ) ); ?>">
			<button data-remodal-action="confirm" class="remodal-confirm"><?php esc_html_e( 'Yes' ); ?></button>
			<button data-remodal-action="cancel" class="remodal-cancel"><?php esc_html_e( 'No' ); ?></button>
		</div>
		<!-- Reset Settings Modal -->

		<div class="remodal" data-remodal-id="wsal_purge_activity">
			<button data-remodal-action="close" class="remodal-close"></button>
			<h3><?php esc_html_e( 'Are you sure you want to purge all the activity log data?', 'wp-security-audit-log' ); ?></h3>
			<br>
			<input type="hidden" id="wsal-purge-activity-nonce" value="<?php echo esc_attr( wp_create_nonce( 'wsal-purge-activity' ) ); ?>">
			<button data-remodal-action="confirm" class="remodal-confirm"><?php esc_html_e( 'Yes', 'wp-security-audit-log' ); ?></button>
			<button data-remodal-action="cancel" class="remodal-cancel"><?php esc_html_e( 'No', 'wp-security-audit-log' ); ?></button>
		</div>
		<!-- Purge Activity Log Modal -->
		<?php
	}

	/**
	 * Save: `Advanced Settings`
	 *
	 * @throws Exception - MainWP Child plugin not active exception.
	 */
	private function tab_advanced_settings_save() {
		// Get $_POST global array.
		$post_array = filter_input_array( INPUT_POST );

		Plugin_Settings_Helper::set_delete_data( isset( $post_array['DeleteData'] ) ? \sanitize_text_field( $post_array['DeleteData'] ) : false );

		$stealth_mode = isset( $post_array['mwp_stealth_mode'] ) ? $post_array['mwp_stealth_mode'] : false;
		if ( 'yes' === $stealth_mode ) {
			if ( ! WpSecurityAuditLog::is_mainwp_active() ) {
				throw new Exception( __( 'MainWP Child plugin is not active on this website.', 'wp-security-audit-log' ) );
			}
			Plugin_Settings_Helper::set_mainwp_child_stealth_mode();

		} else {
			Plugin_Settings_Helper::deactivate_mainwp_child_stealth_mode();
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function header() {
		wp_enqueue_style(
			'settings',
			WSAL_BASE_URL . '/css/settings.css',
			array(),
			WSAL_VERSION
		);

		// Check current tab.
		if ( ! empty( $this->current_tab ) && 'advanced-settings' === $this->current_tab ) {
			// Remodal styles.
			wp_enqueue_style( 'wsal-remodal', WSAL_BASE_URL . 'css/remodal.css', array(), WSAL_VERSION );
			wp_enqueue_style( 'wsal-remodal-theme', WSAL_BASE_URL . 'css/remodal-default-theme.css', array(), WSAL_VERSION );
		}
		?>
		<style type="text/css">
			.wsal-tab {
				/* display: none; */
			}
			.wsal-tab tr.alert-incomplete td {
				color: #9BE;
			}
			.wsal-tab tr.alert-unavailable td {
				color: #CCC;
			}
		</style>
		<?php
	}

	/**
	 * {@inheritDoc}
	 */
	public function footer() {
		wp_enqueue_script( 'jquery-ui-autocomplete' );

		// Check current tab.
		if ( ! empty( $this->current_tab ) && 'advanced-settings' === $this->current_tab ) {
			// Remodal script.
			wp_enqueue_script(
				'wsal-remodal-js',
				WSAL_BASE_URL . 'js/remodal.min.js',
				array(),
				WSAL_VERSION,
				true
			);
		}

		// Register settings script.
		wp_register_script(
			'settings',
			WSAL_BASE_URL . '/js/settings.js',
			array(),
			WSAL_VERSION,
			true
		);
		// Passing nonce for security to JS file.
		$wsal_data = array(
			'wp_nonce'            => wp_create_nonce( 'wsal-exclude-nonce' ),
			'invalidURL'          => esc_html__( 'The specified value is not a valid URL!', 'wp-security-audit-log' ),
			'invalidCPT'          => esc_html__( 'The specified value is not a valid post type!', 'wp-security-audit-log' ),
			'invalidStatus'       => esc_html__( 'The specified value is not a valid post status!', 'wp-security-audit-log' ),
			'invalidIP'           => esc_html__( 'The specified value is not a valid IP address!', 'wp-security-audit-log' ),
			'invalidUser'         => esc_html__( 'The specified value is not a user nor a role!', 'wp-security-audit-log' ),
			'invalidFile'         => esc_html__( 'Filename cannot be added because it contains invalid characters.', 'wp-security-audit-log' ),
			'invalidFileExt'      => esc_html__( 'File extension cannot be added because it contains invalid characters.', 'wp-security-audit-log' ),
			'invalidDir'          => esc_html__( 'Directory cannot be added because it contains invalid characters.', 'wp-security-audit-log' ),
			'remove'              => esc_html__( 'Remove', 'wp-security-audit-log' ),
			'saveSettingsChanges' => esc_html__( 'Please save any changes before switching tabs.', 'wp-security-audit-log' ),
		);
		wp_localize_script( 'settings', 'wsal_data', $wsal_data );
		wp_enqueue_script( 'settings' );
		?>
		<script type="text/javascript">
			jQuery( document ).ready( function() {
				jQuery( '.sel-columns' ).change( function() {
					var notChecked = 1;
					jQuery( '.sel-columns' ).each( function() {
						if ( this.checked ) notChecked = 0;
					})
					if ( notChecked == 1 ) {
						alert( "<?php esc_html_e( 'You have to select at least one column!', 'wp-security-audit-log' ); ?>" );
					}
				});
				// jQuery( 'body' ).on( 'click', '.remodal-confirm', function ( e ) {
				// 	jQuery( '.purge-notice' ).fadeIn(500);
				// 	setTimeout(function() { 
				// 		jQuery( '.purge-notice' ).fadeOut(500);
				// 	}, 4000);
				// });
			});</script>
		<?php
	}

	/**
	 * Method: Ajax Request handler for AjaxGetAllUsers.
	 */
	public function ajax_get_all_users() {
		// Filter $_GET array for security.
		$get_array = filter_input_array( INPUT_GET );
		$this->check_ajax_request_is_valid( $get_array );

		// Fetch users.
		$users = array();
		foreach ( get_users() as $user ) {
			if ( strpos( $user->user_login, $get_array['term'] ) !== false ) {
				array_push( $users, $user->user_login );
			}
		}
		echo wp_json_encode( $users );
		exit;
	}

	/**
	 * Method: Ajax Request handler for AjaxGetAllRoles.
	 */
	public function ajax_get_all_roles() {
		// Filter $_GET array for security.
		$get_array = filter_input_array( INPUT_GET );
		$this->check_ajax_request_is_valid( $get_array );

		// Get roles.
		$roles = array();
		foreach ( get_editable_roles() as $role_name => $role_info ) {
			if ( strpos( $role_name, $get_array['term'] ) !== false ) {
				array_push( $roles, $role_name );
			}
		}
		echo wp_json_encode( $roles );
		exit;
	}

	/**
	 * Create json array of all possible severities.
	 *
	 * @return void
	 * @since  4.3.3
	 */
	public function ajax_get_all_severities() {
		// Filter $_GET array for security.
		$get_array = filter_input_array( INPUT_GET );
		$this->check_ajax_request_is_valid( $get_array );

		$result = array_map(
			function ( $item ) {
				return ucfirst( strtolower( str_replace( 'WSAL_', '', $item ) ) );
			},
			array_keys( Constants::get_severities() )
		);

		echo $this->filter_values_for_searched_term( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$result,
			\sanitize_text_field( \wp_unslash( $get_array['term'] ) )
		);
		exit;
	}

	/**
	 * Filters values to return ones matching the desired term.
	 *
	 * @param array  $items_to_filter List of items to filter.
	 * @param string $term            Search term.
	 *
	 * @return string JSON encoded filtered list.
	 */
	public function filter_values_for_searched_term( $items_to_filter, $term ) {

		$result = array_filter(
			$items_to_filter,
			function( $value ) use ( $term ) {
				return strpos( strtolower( $value ), strtolower( $term ) ) !== false;
			}
		);

		$result = array_map( 'strval', $result );

		return wp_json_encode( $result );
	}

	/**
	 * Create json array of all possible event types.
	 *
	 * @return void
	 * @since  4.3.3
	 */
	public function ajax_get_all_event_types() {
		// Filter $_GET array for security.
		$get_array = filter_input_array( INPUT_GET );
		$this->check_ajax_request_is_valid( $get_array );

		$event_types = Alert_Manager::get_event_type_data();

		echo $this->filter_values_for_searched_term( array_values( $event_types ), $get_array['term'] );
		exit;
	}

	/**
	 * Create json array of all possible object types.
	 *
	 * @return void
	 * @since  4.3.3
	 */
	public function ajax_get_all_object_types() {
		// Filter $_GET array for security.
		$get_array = filter_input_array( INPUT_GET );
		$this->check_ajax_request_is_valid( $get_array );

		$event_objects = Alert_Manager::get_event_objects_data();

		echo $this->filter_values_for_searched_term( array_values( $event_objects ), $get_array['term'] ); // phpcs:ignore
		exit;
	}

	/**
	 * Create json array of all possible event IDs.
	 *
	 * @return void
	 * @since  4.3.3
	 */
	public function ajax_get_all_event_ids() {

		$get_array = filter_input_array( INPUT_GET );
		$this->check_ajax_request_is_valid( $get_array );

		$registered_alerts = Alert_Manager::get_alerts();

		// $alerts = array();
		// foreach ( $registered_alerts as $alert => $details ) {
		// $alerts[] = (string) $details->code;
		// }

		echo $this->filter_values_for_searched_term( array_keys( $registered_alerts ), \sanitize_text_field( \wp_unslash( $get_array['term'] ) ) );
		exit;
	}

	/**
	 * Method: Get CPTs ajax handle.
	 *
	 * @since 2.6.7
	 */
	public function ajax_get_all_cpts() {
		// Filter $_GET array for security.
		$get_array = filter_input_array( INPUT_GET );
		$this->check_ajax_request_is_valid( $get_array );

		// Get custom post types.
		$custom_post_types = array();
		$post_types        = get_post_types(
			array(
				'public' => false,
			),
			'names'
		);
		// if we are running multisite and have networkwide cpt tracker get the
		// list from and merge to the post_types array.
		if ( WP_Helper::is_multisite() && class_exists( '\WSAL\Multisite\NetworkWide\CPTsTracker' ) ) {
			$network_cpts = \WSAL\Multisite\NetworkWide\CPTsTracker::get_network_data_list();
			foreach ( $network_cpts as $cpt ) {
				$post_types[ $cpt ] = $cpt;
			}
		}

		$post_types = array_diff( $post_types, array( 'attachment', 'revision', 'nav_menu_item', 'customize_changeset', 'custom_css' ) );
		foreach ( $post_types as $post_type ) {
			if ( strpos( $post_type, $get_array['term'] ) !== false ) {
				array_push( $custom_post_types, $post_type );
			}
		}
		echo wp_json_encode( $custom_post_types );
		exit;
	}

	/**
	 * Method: Get statuses ajax handle.
	 *
	 * @since 5.0.0
	 */
	public function ajax_get_all_stati() {
		// Filter $_GET array for security.
		$get_array = filter_input_array( INPUT_GET );
		$this->check_ajax_request_is_valid( $get_array );
		$post_stati = get_post_stati();

		echo wp_json_encode( $post_stati );
		exit;
	}

	/**
	 * Checks if provided GET array is valid and bails if not.
	 *
	 * @param array $get_array Get data.
	 */
	public function check_ajax_request_is_valid( $get_array ) {
		// Die if user does not have permission to edit.
		if ( ! Settings_Helper::current_user_can( 'edit' ) ) {
			die( 'Access Denied.' );
		}

		// Die if nonce verification failed.
		if ( ! wp_verify_nonce( $get_array['wsal_nonce'], 'wsal-exclude-nonce' ) ) {
			die( esc_html__( 'Nonce verification failed.', 'wp-security-audit-log' ) );
		}
	}

	/**
	 * Method: Reset plugin settings table.
	 */
	public function reset_settings() {
		// Die if user does not have permission to change settings.
		if ( ! Settings_Helper::current_user_can( 'edit' ) ) {
			wp_send_json_error( esc_html__( 'Access Denied.', 'wp-security-audit-log' ) );
		}

		// Verify nonce.
		if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( \sanitize_text_field( \wp_unslash( $_POST['nonce'] ) ), 'wsal-reset-settings' ) ) {
			wp_send_json_error( esc_html__( 'Nonce Verification Failed.', 'wp-security-audit-log' ) );
		}

		// Delete all settings.
		Settings_Helper::delete_all_settings();

		// Log settings reset event.
		Alert_Manager::trigger_event( 6006 );
		wp_send_json_success( esc_html__( 'Plugin settings have been reset.', 'wp-security-audit-log' ) );
	}

	/**
	 * Method: Purge plugin occurrence & meta tables.
	 */
	public function purge_activity() {
		// Die if user does not have permission to change settings.
		if ( ! Settings_Helper::current_user_can( 'edit' ) ) {
			wp_send_json_error( esc_html__( 'Access Denied.', 'wp-security-audit-log' ) );
		}

		// Verify nonce.
		if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( \sanitize_text_field( \wp_unslash( $_POST['nonce'] ) ), 'wsal-purge-activity' ) ) {
			wp_send_json_error( esc_html__( 'Nonce Verification Failed.', 'wp-security-audit-log' ) );
		}

		$result = Database_Manager::purge_activity();

		if ( $result ) {
			// Log purge activity event.
			Alert_Manager::trigger_event( 6034 );
			wp_send_json_success( esc_html__( 'Tables has been reset.', 'wp-security-audit-log' ) );
		} else {
			wp_send_json_error( esc_html__( 'Reset query failed.', 'wp-security-audit-log' ) );
		}
	}

	/**
	 * Renders table with retention related settings.
	 */
	public function render_retention_settings_table() {
		// Check if the retention settings are enforced from the MainWP master site.
		$enforced_settings                     = Plugin_Settings_Helper::get_mainwp_enforced_settings();
		$retention_settings_enforced_by_mainwp = array_key_exists( 'pruning_enabled', $enforced_settings );
		?>
		<table class="form-table wsal-tab">
			<tbody>
			<tr>
				<th><label for="delete1"><?php esc_html_e( 'Activity log retention', 'wp-security-audit-log' ); ?></label></th>
				<td>
					<fieldset>
						<?php $nbld = ! Settings_Helper::get_boolean_option_value( 'pruning-date-e' ); ?>
						<label for="delete0">
							<input type="radio" id="delete0" name="PruneBy" value="" <?php checked( $nbld ); ?>
								<?php disabled( $retention_settings_enforced_by_mainwp ); ?> />
							<?php esc_html_e( 'Keep all data', 'wp-security-audit-log' ); ?>
						</label>
					</fieldset>

					<fieldset>
						<?php
						// Check pruning date option.
						$nbld = Settings_Helper::get_boolean_option_value( 'pruning-date-e' );

						// Find and replace ` months` in the string.
						$pruning_date = Plugin_Settings_Helper::get_pruning_date();
						$pruning_date = preg_replace( '/[^0-9]/', '', $pruning_date );

						$pruning_unit = Plugin_Settings_Helper::get_pruning_unit();

						$pruning_unit_options = array(
							'days'   => esc_html__( 'Days', 'wp-security-audit-log' ),
							'months' => esc_html__( 'Months', 'wp-security-audit-log' ),
							'years'  => esc_html__( 'Years', 'wp-security-audit-log' ),
						);

						// Check if pruning limit was enabled for backwards compatibility.
						if ( Settings_Helper::get_boolean_option_value( 'pruning-limit-e' ) ) {
							$nbld         = true;
							$pruning_date = '6';
							$pruning_unit = 'months';
							Settings_Helper::set_pruning_date_settings( true, $pruning_date . ' ' . $pruning_unit, $pruning_unit );
							Plugin_Settings_Helper::set_pruning_limit_enabled( false );
						}
						?>
						<label for="delete1">
							<input type="radio" id="delete1" name="PruneBy" value="date" <?php checked( $nbld ); ?>
									<?php disabled( $retention_settings_enforced_by_mainwp ); ?> />
							<?php esc_html_e( 'Delete events older than', 'wp-security-audit-log' ); ?>
						</label>
						<input type="number" id="PruningDate" name="PruningDate"
							value="<?php echo esc_attr( $pruning_date ); ?>"
							onfocus="jQuery('#delete1').attr('checked', true);"
							<?php disabled( $retention_settings_enforced_by_mainwp ); ?>
							min="1"
						/>
						<select name="pruning-unit" id="pruning-unit" <?php disabled( $retention_settings_enforced_by_mainwp ); ?>>
							<?php
							foreach ( $pruning_unit_options as $option => $label ) {
								echo '<option value="' . esc_attr( $option ) . '" ' . selected( $pruning_unit, $option, true ) . '>' . ucwords( $label ) . '</option>'; // phpcs:ignore
							}
							?>
						</select>
					</fieldset>

					<?php if ( Settings_Helper::get_boolean_option_value( 'pruning-date-e' ) ) : ?>
						<p class="description">
							<?php
							$next = (int) wp_next_scheduled( 'wsal_cleanup_hook' );
							echo esc_html__( 'The next scheduled purging of activity log data that is older than ', 'wp-security-audit-log' );
							echo esc_html( $pruning_date . ' ' . $pruning_unit );
							echo sprintf(
								' is in %s.',
								esc_html( human_time_diff( time(), $next ) )
							);
							echo '<!-- ' . esc_html( gmdate( 'dMy H:i:s', $next ) ) . ' --> ';
							echo esc_html__( 'You can run the purging process now by clicking the button below.', 'wp-security-audit-log' );
							?>
						</p>
						<p>
							<a class="button-primary" href="
							<?php
							echo esc_url(
								add_query_arg(
									array(
										'action' => 'AjaxRunCleanup',
										'nonce'  => wp_create_nonce( 'wsal-run-cleanup' ),
									),
									admin_url( 'admin-ajax.php' )
								)
							);
							?>
							" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wsal-run-cleanup' ) ); ?>" ><?php esc_html_e( 'Purge Old Data', 'wp-security-audit-log' ); ?></a>
						</p>
					<?php endif; ?>
				</td>
			</tr>
			<!-- Activity log retention -->
			</tbody>
		</table>
		<?php
	}
}
