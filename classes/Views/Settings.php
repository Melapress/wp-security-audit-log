<?php
/**
 * Settings Page
 *
 * Settings page of the plugin.
 *
 * @since   1.0.0
 * @package Wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class: WSAL_Views_Settings
 *
 * Settings view class to handle settings page functions.
 *
 * @since 1.0.0
 */
class WSAL_Views_Settings extends WSAL_AbstractView {

	/**
	 * Adapter Message.
	 *
	 * @var string
	 */
	public $adapter_msg = '';

	/**
	 * Scan settings.
	 *
	 * @var array
	 */
	private $scan_settings = array();

	/**
	 * WSAL Setting Tabs.
	 *
	 * @var array
	 * @since 3.2.3
	 */
	private $wsal_setting_tabs = array();

	/**
	 * Current Setting Tab.
	 *
	 * @var string
	 * @since 3.2.3
	 */
	private $current_tab = '';

	/**
	 * Method: Constructor.
	 *
	 * @param WpSecurityAuditLog $plugin - Instance of WpSecurityAuditLog.
	 */
	public function __construct( WpSecurityAuditLog $plugin ) {
		parent::__construct( $plugin );
		add_action( 'wp_ajax_AjaxCheckSecurityToken', array( $this, 'AjaxCheckSecurityToken' ) );
		add_action( 'wp_ajax_AjaxRunCleanup', array( $this, 'AjaxRunCleanup' ) );
		add_action( 'wp_ajax_AjaxGetAllUsers', array( $this, 'AjaxGetAllUsers' ) );
		add_action( 'wp_ajax_AjaxGetAllRoles', array( $this, 'AjaxGetAllRoles' ) );
		add_action( 'wp_ajax_AjaxGetAllCPT', array( $this, 'AjaxGetAllCPT' ) );
		add_action( 'wp_ajax_wsal_scan_add_exception', array( $this, 'scan_add_exception_file' ) );
		add_action( 'wp_ajax_wsal_scan_remove_exception', array( $this, 'scan_remove_exception_file' ) );
		add_action( 'wp_ajax_wsal_manual_scan_now', array( $this, 'run_manual_scan_now' ) );
		add_action( 'wp_ajax_wsal_stop_file_changes_scan', array( $this, 'stop_file_changes_scan' ) );
		add_action( 'wp_ajax_wsal_reset_settings', array( $this, 'reset_settings' ) );
		add_action( 'wp_ajax_wsal_purge_activity', array( $this, 'purge_activity' ) );

		// Tab links.
		$wsal_setting_tabs = array(
			'general'           => array(
				'name'   => __( 'General', 'wp-security-audit-log' ),
				'link'   => add_query_arg( 'tab', 'general', $this->GetUrl() ),
				'render' => array( $this, 'tab_general' ),
				'save'   => array( $this, 'tab_general_save' ),
			),
			'audit-log'         => array(
				'name'   => __( 'Activity Log', 'wp-security-audit-log' ),
				'link'   => add_query_arg( 'tab', 'audit-log', $this->GetUrl() ),
				'render' => array( $this, 'tab_audit_log' ),
				'save'   => array( $this, 'tab_audit_log_save' ),
			),
			'file-changes'      => array(
				'name'   => __( 'File Integrity Scan', 'wp-security-audit-log' ),
				'link'   => add_query_arg( 'tab', 'file-changes', $this->GetUrl() ),
				'render' => array( $this, 'tab_file_changes' ),
				'save'   => array( $this, 'tab_file_changes_save' ),
			),
			'exclude-objects'   => array(
				'name'   => __( 'Exclude Objects', 'wp-security-audit-log' ),
				'link'   => add_query_arg( 'tab', 'exclude-objects', $this->GetUrl() ),
				'render' => array( $this, 'tab_exclude_objects' ),
				'save'   => array( $this, 'tab_exclude_objects_save' ),
			),
			'advanced-settings' => array(
				'name'   => __( 'Advanced Settings', 'wp-security-audit-log' ),
				'link'   => add_query_arg( 'tab', 'advanced-settings', $this->GetUrl() ),
				'render' => array( $this, 'tab_advanced_settings' ),
				'save'   => array( $this, 'tab_advanced_settings_save' ),
			),
		);

		/**
		 * Filter: `wsal_setting_tabs`
		 *
		 * This filter is used to filter the tabs of WSAL settings page.
		 *
		 * Setting tabs structure:
		 *     $wsal_setting_tabs['unique-tab-id'] = array(
		 *         'name'   => Name of the tab,
		 *         'link'   => Link of the tab,
		 *         'render' => This function is used to render HTML elements in the tab,
		 *         'name'   => This function is used to save the related setting of the tab,
		 *     );
		 *
		 * @param array $wsal_setting_tabs – Array of WSAL Setting Tabs.
		 * @since 3.2.3
		 */
		$this->wsal_setting_tabs = apply_filters( 'wsal_setting_tabs', $wsal_setting_tabs );

		// Get the current tab.
		$current_tab       = filter_input( INPUT_GET, 'tab', FILTER_SANITIZE_STRING );
		$this->current_tab = empty( $current_tab ) ? 'general' : $current_tab;
	}

	/**
	 * Method: Plugin Shortcut.
	 */
	public function HasPluginShortcutLink() {
		return true;
	}

	/**
	 * Method: Get View Title.
	 */
	public function GetTitle() {
		return __( 'Settings', 'wp-security-audit-log' );
	}

	/**
	 * Method: Get View Icon.
	 */
	public function GetIcon() {
		return 'dashicons-admin-generic';
	}

	/**
	 * Method: Get View Name.
	 */
	public function GetName() {
		return __( 'Settings', 'wp-security-audit-log' );
	}

	/**
	 * Method: Get View Weight.
	 */
	public function GetWeight() {
		return 3;
	}

	/**
	 * Method: Get Token Type.
	 *
	 * @param string $token - Token type.
	 */
	protected function GetTokenType( $token ) {
		return $this->_plugin->settings->get_token_type( $token );
	}

	/**
	 * Method: Load saved settings of this view.
	 */
	public function load_file_changes_settings() {
		if ( ! is_multisite() ) {
			$default_scan_dirs = array( 'root', 'wp-admin', 'wp-includes', 'wp-content', 'wp-content/themes', 'wp-content/plugins', 'wp-content/uploads' );
		} else {
			$default_scan_dirs = array( 'root', 'wp-admin', 'wp-includes', 'wp-content', 'wp-content/themes', 'wp-content/plugins', 'wp-content/uploads', 'wp-content/uploads/sites' );
		}

		// Load saved settings of this view.
		$this->scan_settings = array(
			'scan_file_changes'        => $this->_plugin->GetGlobalOption( 'scan-file-changes', 'enable' ),
			'scan_frequency'           => $this->_plugin->GetGlobalOption( 'scan-frequency', 'weekly' ),
			'scan_hour'                => $this->_plugin->GetGlobalOption( 'scan-hour', '04' ),
			'scan_day'                 => $this->_plugin->GetGlobalOption( 'scan-day', '1' ),
			'scan_date'                => $this->_plugin->GetGlobalOption( 'scan-date', '10' ),
			'scan_directories'         => $this->_plugin->GetGlobalOption( 'scan-directories', $default_scan_dirs ),
			'scan_excluded_dirs'       => $this->_plugin->GetGlobalOption( 'scan-excluded-directories', array() ),
			'scan_excluded_extensions' => $this->_plugin->GetGlobalOption( 'scan-excluded-extensions', array( 'jpg', 'jpeg', 'png', 'bmp', 'pdf', 'txt', 'log', 'mo', 'po', 'mp3', 'wav', 'gif', 'ico', 'jpe', 'psd', 'raw', 'svg', 'tif', 'tiff', 'aif', 'flac', 'm4a', 'oga', 'ogg', 'ra', 'wma', 'asf', 'avi', 'mkv', 'mov', 'mp4', 'mpe', 'mpeg', 'mpg', 'ogv', 'qt', 'rm', 'vob', 'webm', 'wm', 'wmv' ) ),
			'scan_in_progress'         => $this->_plugin->GetGlobalOption( 'scan-in-progress', false ),
		);
	}

	/**
	 * Method: Save settings.
	 *
	 * @throws Exception - Unrecognized settings tab error.
	 */
	protected function Save() {
		check_admin_referer( 'wsal-settings' );

		// Call respective tab save functions if they are set.
		if ( ! empty( $this->current_tab ) && ! empty( $this->wsal_setting_tabs[ $this->current_tab ]['save'] ) ) {
			call_user_func( $this->wsal_setting_tabs[ $this->current_tab ]['save'] );
		} else {
			throw new Exception( esc_html__( 'Unknown settings tab.', 'wp-security-audit-log' ) );
		}
	}

	/**
	 * Method: Check security token.
	 */
	public function AjaxCheckSecurityToken() {
		if ( ! $this->_plugin->settings->CurrentUserCan( 'view' ) ) {
			echo wp_json_encode(
				array(
					'success' => false,
					'message' => esc_html__( 'Access Denied.', 'wp-security-audit-log' ),
				)
			);
			die();
		}

		//@codingStandardsIgnoreStart
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : false;
		$token = isset( $_POST['token'] ) ? sanitize_text_field( $_POST['token'] ) : false;
		//@codingStandardsIgnoreEnd

		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'wsal-exclude-nonce' ) ) {
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

		echo wp_json_encode(
			array(
				'success'   => true,
				'token'     => $token,
				'tokenType' => esc_html( $this->GetTokenType( $token ) ),
			)
		);
		die();
	}

	/**
	 * Method: Run cleanup.
	 */
	public function AjaxRunCleanup() {
		if ( ! $this->_plugin->settings->CurrentUserCan( 'view' ) ) {
			die( 'Access Denied.' );
		}

		$now       = current_time( 'timestamp' ); // Current time.
		$max_sdate = $this->_plugin->settings->GetPruningDate(); // Pruning date.

		// If archiving is enabled then events are deleted from the archive database.
		$archiving = $this->_plugin->settings->IsArchivingEnabled();
		if ( $archiving ) {
			// Switch to Archive DB.
			$this->_plugin->settings->SwitchToArchiveDB();
		}

		// Calculate limit timestamp.
		$max_stamp = $now - ( strtotime( $max_sdate ) - $now );

		$query = new WSAL_Models_OccurrenceQuery();
		$query->addOrderBy( 'created_on', false ); // Descending order.
		$query->addCondition( 'created_on <= %s', intval( $max_stamp ) ); // Add limits of timestamp.
		$results = $query->getAdapter()->Execute( $query );
		$items   = count( $results );

		if ( $items ) {
			$this->_plugin->CleanUp();
		}

		if ( $archiving ) {
			$redirect_url  = add_query_arg( 'page', 'wsal-ext-settings', admin_url( 'admin.php' ) );
			$redirect_url .= '#archiving';
			wp_safe_redirect( $redirect_url );
		} else {
			if ( $items ) {
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
			wp_safe_redirect( add_query_arg( $redirect_args, $this->GetUrl() ) );
		}
		exit;
	}

	/**
	 * Method: Get View.
	 */
	public function Render() {
		// Filter $_POST array for security.
		$post_array = filter_input_array( INPUT_POST );

		if ( isset( $post_array['_wpnonce'] ) && ! wp_verify_nonce( $post_array['_wpnonce'], 'wsal-settings' ) ) {
			wp_die( esc_html__( 'Nonce verification failed.', 'wp-security-audit-log' ) );
		}

		if ( ! $this->_plugin->settings->CurrentUserCan( 'edit' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-security-audit-log' ) );
		}

		if ( isset( $post_array['submit'] ) ) {
			try {
				$this->Save();
				?><div class="updated">
					<p><?php esc_html_e( 'Settings have been saved.', 'wp-security-audit-log' ); ?></p>
				</div>
				<?php
			} catch ( Exception $ex ) {
				?>
				<div class="error"><p><?php esc_html_e( 'Error: ', 'wp-security-audit-log' ); ?><?php echo esc_html( $ex->getMessage() ); ?></p></div>
				<?php
			}
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
				<?php if ( empty( $this->current_tab ) ) : ?>
					<a href="<?php echo esc_url( $tab['link'] ); ?>" class="nav-tab <?php echo ( 'general' === $tab_id ) ? 'nav-tab-active' : false; ?>">
						<?php echo esc_html( $tab['name'] ); ?>
					</a>
				<?php else : ?>
					<a href="<?php echo esc_url( $tab['link'] ); ?>" class="nav-tab <?php echo ( $tab_id === $this->current_tab ) ? 'nav-tab-active' : false; ?>">
						<?php echo esc_html( $tab['name'] ); ?>
					</a>
				<?php endif; ?>
			<?php endforeach; ?>
		</nav>

		<form id="audit-log-settings" method="post">
			<input type="hidden" name="page" value="<?php echo filter_input( INPUT_GET, 'page', FILTER_SANITIZE_STRING ); ?>" />
			<input type="hidden" id="ajaxurl" value="<?php echo esc_attr( admin_url( 'admin-ajax.php' ) ); ?>" />
			<?php wp_nonce_field( 'wsal-settings' ); ?>

			<div id="audit-log-adverts">
			</div>
			<div class="nav-tabs">
				<?php
				if ( ! empty( $this->current_tab ) && ! empty( $this->wsal_setting_tabs[ $this->current_tab ]['render'] ) ) {
					call_user_func( $this->wsal_setting_tabs[ $this->current_tab ]['render'] );
				} else {
					call_user_func( $this->wsal_setting_tabs['general']['render'] );
				}
				?>
			</div>
			<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes"></p>
		</form>
		<script type="text/javascript">
		<!--
			function delete_confirm(elementRef) {
				if (elementRef.checked) {
					if ( window.confirm('Do you want remove all data when the plugin is deleted?') == false )
					elementRef.checked = false;
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
		// --></script>
		<?php
	}

	/**
	 * Tab: `General`
	 */
	private function tab_general() {
		?>
		<p class="description">
			<?php echo wp_kses( __( 'Need help with setting up the plugin to meet your requirements? <a href="https://www.wpsecurityauditlog.com/contact/" target="_blank">Schedule a 20 minutes consultation and setup call</a> with our experts for just $50.', 'wp-security-audit-log' ), $this->_plugin->allowed_html_tags ); ?>
		</p>

		<h3><?php esc_html_e( 'Display latest events widget in dashboard', 'wp-security-audit-log' ); ?></h3>
		<p class="description">
			<?php
			echo sprintf(
				/* translators: Max number of dashboard widget alerts. */
				esc_html__( 'The events widget displays the latest %d security events in the dashboard so you can get an overview of the latest events once you login.', 'wp-security-audit-log' ),
				esc_html( $this->_plugin->settings->GetDashboardWidgetMaxAlerts() )
			);
			?>
		</p>
		<table class="form-table wsal-tab">
			<tbody>
				<tr>
					<th><label for="dwoption_on"><?php esc_html_e( 'Dashboard Widget', 'wp-security-audit-log' ); ?></label></th>
					<td>
						<fieldset>
							<?php $dwe = $this->_plugin->settings->IsWidgetsEnabled(); ?>
							<label for="dwoption_on">
								<input type="radio" name="EnableDashboardWidgets" id="dwoption_on" style="margin-top: 2px;" <?php checked( $dwe ); ?> value="1">
								<span><?php esc_html_e( 'Yes', 'wp-security-audit-log' ); ?></span>
							</label>
							<br/>
							<label for="dwoption_off">
								<input type="radio" name="EnableDashboardWidgets" id="dwoption_off" style="margin-top: 2px;" <?php checked( $dwe, false ); ?>  value="0">
								<span><?php esc_html_e( 'No', 'wp-security-audit-log' ); ?></span>
							</label>
						</fieldset>
					</td>
				</tr>
				<!-- / Events Dashboard Widget -->
			</tbody>
		</table>
		<!-- Dashboard Widget -->

		<h3><?php esc_html_e( 'Add user notification on the WordPress login page', 'wp-security-audit-log' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'Many compliance regulations (such as the GDRP) require website administrators to tell the users of their website that all the changes they do when logged in are being logged.', 'wp-security-audit-log' ); ?>
		</p>
		<table class="form-table wsal-tab">
			<tbody>
				<tr>
					<th><label for="login_page_notification"><?php esc_html_e( 'Login Page Notification', 'wp-security-audit-log' ); ?></label></th>
					<td>
						<fieldset>
							<?php
							// Get login page notification checkbox.
							$wsal_lpn = $this->_plugin->settings->is_login_page_notification();
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
							$wsal_lpn_text         = $this->_plugin->settings->get_login_page_notification_text();
							$wsal_lpn_text_default = __( 'For security and auditing purposes, a record of all of your logged-in actions and changes within the WordPress dashboard will be recorded in an audit log with the <a href="https://www.wpsecurityauditlog.com/" target="_blank">WP Security Audit Log plugin</a>. The audit log also includes the IP address where you accessed this site from.', 'wp-security-audit-log' );

							// Allowed HTML tags for this setting.
							$allowed_tags = array(
								'a' => array(
									'href' => array(),
									'title' => array(),
									'target' => array(),
								),
							);
							?>
							<textarea name="login_page_notification_text"
								id="login_page_notification_text"
								cols="60" rows="6"
								<?php echo ( $wsal_lpn ) ? false : 'disabled'; ?>
							><?php echo ( $wsal_lpn_text ) ? wp_kses( $wsal_lpn_text, $allowed_tags ) : wp_kses( $wsal_lpn_text_default, $allowed_tags ); ?></textarea>
							<br/>
							<p class="description">
								<?php echo wp_kses( __( '<strong>Note: </strong>', 'wp-security-audit-log' ), $this->_plugin->allowed_html_tags ) . esc_html__( 'The only HTML code allowed in the login page notification is for links ( < a href >< /a > ).', 'wp-security-audit-log' ); ?>
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
				'<a href="https://www.wpsecurityauditlog.com/support-documentation/support-reverse-proxies-web-application-firewalls/" target="_blank">' . esc_html__( 'learn more', 'wp-security-audit-log' ) . '</a>'
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
								<input type="radio" name="EnableProxyIpCapture" value="1" id="enable_proxy_ip_capture_yes" <?php checked( $this->_plugin->settings->IsMainIPFromProxy() ); ?> />
								<?php esc_html_e( 'Yes', 'wp-security-audit-log' ); ?>
							</label>
							<br/>
							<label for="EnableIpFiltering">
								<input type="checkbox" name="EnableIpFiltering" value="1" id="EnableIpFiltering" <?php checked( $this->_plugin->settings->IsInternalIPsFiltered() ); ?> />
								<?php esc_html_e( 'Filter internal IP addresses from the proxy headers. Enable this option only if you are	are still seeing the internal IP addresses of the firewall or proxy.', 'wp-security-audit-log' ); ?>
							</label>
							<br/>
							<label for="enable_proxy_ip_capture_no">
								<input type="radio" name="EnableProxyIpCapture" value="0" id="enable_proxy_ip_capture_no" <?php checked( $this->_plugin->settings->IsMainIPFromProxy(), false ); ?> />
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
			<?php esc_html_e( 'By default only users with administrator or super administrator (multisite) roles can change the settings of the plugin. Though you can change these privileges from this section.', 'wp-security-audit-log' ); ?>
		</p>
		<table class="form-table wsal-tab">
			<tbody>
				<tr>
					<th><label for="RestrictAdmins"><?php esc_html_e( 'Restrict Plugin Access', 'wp-security-audit-log' ); ?></label></th>
					<td>
						<fieldset>
							<?php $restrict_settings = $this->_plugin->settings->get_restrict_plugin_setting(); ?>
							<label for="only_me">
								<input type="radio" name="restrict-plugin-settings" id="only_me" value="only_me" <?php checked( $restrict_settings, 'only_me' ); ?> />
								<?php esc_html_e( 'Only me', 'wp-security-audit-log' ); ?>
							</label>
							<br/>
							<label for="only_admins">
								<input type="radio" name="restrict-plugin-settings" id="only_admins" value="only_admins" <?php checked( $restrict_settings, 'only_admins' ); ?> />
								<?php esc_html_e( 'Only administrators', 'wp-security-audit-log' ); ?>
							</label>
							<br/>
							<label for="only_selected_users">
								<input type="radio" name="restrict-plugin-settings" id="only_selected_users" value="only_selected_users" <?php checked( $restrict_settings, 'only_selected_users' ); ?> />
								<?php esc_html_e( 'All these users or users with these roles', 'wp-security-audit-log' ); ?>
							</label>

							<p class="description">
								<?php esc_html_e( 'Specify the username or the users which can change the plugin settings. You can also specify roles.', 'wp-security-audit-log' ); ?>
							</p>

							<label>
								<input type="text" id="EditorQueryBox" style="width: 250px;">
								<input type="button" id="EditorQueryAdd" style="" class="button-primary" value="Add">
							</label>

							<div id="EditorList">
								<?php
								foreach ( $this->_plugin->settings->GetAllowedPluginEditors() as $item ) :
									if ( wp_get_current_user()->user_login === $item ) {
										continue;
									}
									?>
									<span class="sectoken-<?php echo esc_attr( $this->GetTokenType( $item ) ); ?>">
										<input type="hidden" name="Editors[]" value="<?php echo esc_attr( $item ); ?>"/>
										<?php echo esc_html( $item ); ?>
										<?php if ( wp_get_current_user()->user_login !== $item ) : ?>
											<a href="javascript:;" title="Remove">&times;</a>
										<?php endif; ?>
									</span>
								<?php endforeach; ?>
							</div>
						</fieldset>
					</td>
				</tr>
				<!-- / Restrict Plugin Access -->
			</tbody>
		</table>
		<!-- Restrict Plugin Access -->

		<h3><?php esc_html_e( 'Allow other users to view the activity log', 'wp-security-audit-log' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'By default only users with administrator and super administrator (multisite) role can view the WordPress activity log. Though you can allow other users with no admin role to view the events.', 'wp-security-audit-log' ); ?>
		</p>
		<table class="form-table wsal-tab">
			<tbody>
				<tr>
					<th><label for="ViewerQueryBox"><?php esc_html_e( 'Can View Events', 'wp-security-audit-log' ); ?></label></th>
					<td>
						<fieldset>
							<label>
								<input type="text" id="ViewerQueryBox" style="width: 250px;">
								<input type="button" id="ViewerQueryAdd" class="button-primary" value="Add">

								<p class="description">
									<?php esc_html_e( 'Specify the username or the users which do not have an admin role but can also see the WordPress activity role. You can also specify roles.', 'wp-security-audit-log' ); ?>
								</p>
							</label>

							<div id="ViewerList">
								<?php
								foreach ( $this->_plugin->settings->GetAllowedPluginViewers() as $item ) :
									if ( wp_get_current_user()->user_login === $item ) {
										continue;
									}
									?>
									<span class="sectoken-<?php echo esc_attr( $this->GetTokenType( $item ) ); ?>">
									<input type="hidden" name="Viewers[]" value="<?php echo esc_attr( $item ); ?>"/>
									<?php echo esc_html( $item ); ?>
									<a href="javascript:;" title="Remove">&times;</a>
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
		<p class="description">
			<?php esc_html_e( 'By default when the plugin sends an email notification it uses the email address specified in this website’s general settings. Though you can change the email address and display name from this section.', 'wp-security-audit-log' ); ?>
		</p>
		<table class="form-table wsal-tab">
			<tbody>
				<tr>
					<th><label for="FromEmail"><?php esc_html_e( 'From Email & Name', 'wp-security-audit-log' ); ?></label></th>
					<td>
						<fieldset>
							<?php $use_email = $this->_plugin->GetGlobalOption( 'use-email', 'default_email' ); ?>
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
								<input type="email" id="FromEmail" name="FromEmail" value="<?php echo esc_attr( $this->_plugin->settings->GetFromEmail() ); ?>" />
							</label>
							<br>
							<label for="DisplayName">
								<?php esc_html_e( 'Display Name', 'wp-security-audit-log' ); ?>&nbsp;
								<input type="text" id="DisplayName" name="DisplayName" value="<?php echo esc_attr( $this->_plugin->settings->GetDisplayName() ); ?>" />
							</label>
						</fieldset>
					</td>
				</tr>
				<!-- / From Email & Name -->
			</tbody>
		</table>
		<!-- From Email & Name -->

		<h3><?php esc_html_e( 'Do you want to hide the plugin from the list of installed plugins?', 'wp-security-audit-log' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'By default all installed plugins are listed in the plugins page. If you do not want other administrators to see that you installed this plugin set this option to Yes so the WP Security Audit Log is not listed as an installed plugin on this website.', 'wp-security-audit-log' ); ?>
		</p>
		<table class="form-table wsal-tab">
			<tbody>
				<tr>
					<th><label for="incognito_yes"><?php esc_html_e( 'Hide Plugin in Plugins Page', 'wp-security-audit-log' ); ?></label></th>
					<td>
						<fieldset>
							<label for="incognito_yes">
								<input type="radio" name="Incognito" value="1" id="incognito_yes" <?php checked( $this->_plugin->settings->IsIncognito() ); ?> />
								<?php esc_html_e( 'Yes, hide the plugin from the list of installed plugins', 'wp-security-audit-log' ); ?>
							</label>
							<br/>
							<label for="incognito_no">
								<input type="radio" name="Incognito" value="0" id="incognito_no" <?php checked( $this->_plugin->settings->IsIncognito(), false ); ?> />
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

		$this->_plugin->settings->set_use_email( sanitize_text_field( $post_array['use-email'] ) );
		$this->_plugin->settings->SetFromEmail( sanitize_email( $post_array['FromEmail'] ) );
		$this->_plugin->settings->SetDisplayName( sanitize_text_field( $post_array['DisplayName'] ) );

		$this->_plugin->settings->SetWidgetsEnabled( sanitize_text_field( $post_array['EnableDashboardWidgets'] ) );

		// Get plugin viewers.
		$viewers = isset( $post_array['Viewers'] ) ? array_map( 'sanitize_text_field', $post_array['Viewers'] ) : array();
		$this->_plugin->settings->SetAllowedPluginViewers( $viewers );

		// Get plugin editors.
		$editors           = isset( $post_array['Editors'] ) ? array_map( 'sanitize_text_field', $post_array['Editors'] ) : array();
		$restrict_settings = isset( $post_array['restrict-plugin-settings'] ) ? sanitize_text_field( $post_array['restrict-plugin-settings'] ) : false;
		if ( ! empty( $restrict_settings ) && 'only_me' === $restrict_settings ) {
			// Add current username to plugin editors.
			$editors   = array(); // Empty the array to remove previous editors in restrict mode.
			$editors[] = wp_get_current_user()->user_login;
		} elseif ( ! empty( $restrict_settings ) && 'only_selected_users' !== $restrict_settings ) {
			// Empty the editors if option is not user or user roles.
			$editors = array();
		}
		$this->_plugin->settings->SetAllowedPluginEditors( $editors );

		if ( ! empty( $restrict_settings ) && 'only_me' === $restrict_settings ) {
			$this->_plugin->settings->SetRestrictAdmins( true );
		} else {
			$this->_plugin->settings->SetRestrictAdmins( false );
		}

		$this->_plugin->settings->set_restrict_plugin_setting( $restrict_settings );
		$this->_plugin->settings->set_login_page_notification( isset( $post_array['login_page_notification'] ) ? sanitize_text_field( $post_array['login_page_notification'] ) : false );
		$this->_plugin->settings->set_login_page_notification_text( isset( $post_array['login_page_notification_text'] ) ? $post_array['login_page_notification_text'] : false );
		$this->_plugin->settings->SetMainIPFromProxy( isset( $post_array['EnableProxyIpCapture'] ) ? sanitize_text_field( $post_array['EnableProxyIpCapture'] ) : false );
		$this->_plugin->settings->SetInternalIPsFiltering( isset( $post_array['EnableIpFiltering'] ) ? sanitize_text_field( $post_array['EnableIpFiltering'] ) : false );
		$this->_plugin->settings->SetIncognito( isset( $post_array['Incognito'] ) ? sanitize_text_field( $post_array['Incognito'] ) : false );
	}

	/**
	 * Tab: `Audit Log`
	 */
	private function tab_audit_log() {
		?>
		<h3><?php esc_html_e( 'For how long do you want to keep the activity log events (Retention settings) ?', 'wp-security-audit-log' ); ?></h3>
		<p class="description">
			<?php
			esc_html_e( 'The plugin uses an efficient way to store the activity log data in the WordPress database, though the more data you keep the more disk space will be required. ', 'wp-security-audit-log' );
			$retention_help_text = __( '<a href="https://www.wpsecurityauditlog.com/pricing/" target="_blank">Upgrade to Premium</a> to store the activity log data in an external database.', 'wp-security-audit-log' );

			echo wp_kses( $retention_help_text, $this->_plugin->allowed_html_tags );
			?>
		</p>
		<?php if ( $this->_plugin->settings->IsArchivingEnabled() ) : ?>
			<p class="description">
				<?php
				$archiving_page = add_query_arg( 'page', 'wsal-ext-settings', admin_url( 'admin.php' ) ) . '#archiving';
				/* translators: 1: Archive page link tag. 2: Link closing tag. */
				echo '<span class="dashicons dashicons-warning"></span> ' . sprintf( esc_html__( 'Retention settings moved to %1$s archiving settings %2$s because archiving is enabled', 'wp-security-audit-log' ), '<a href="' . esc_url( $archiving_page ) . '" target="_blank">', '</a>' );
				?>
			</p>
		<?php else : ?>
			<table class="form-table wsal-tab">
				<tbody>
					<tr>
						<th><label for="delete1"><?php esc_html_e( 'Audit Log Retention', 'wp-security-audit-log' ); ?></label></th>
						<td>
							<fieldset>
								<?php $nbld = ! $this->_plugin->settings->IsPruningDateEnabled(); ?>
								<label for="delete0">
									<input type="radio" id="delete0" name="PruneBy" value="" <?php checked( $nbld ); ?> />
									<?php echo esc_html__( 'Keep all data', 'wp-security-audit-log' ); ?>
								</label>
							</fieldset>

							<fieldset>
								<?php
								// Check pruning date option.
								$nbld = $this->_plugin->settings->IsPruningDateEnabled();

								// Find and replace ` months` in the string.
								$pruning_date = $this->_plugin->settings->GetPruningDate();
								$pruning_date = str_replace( ' months', '', $pruning_date );
								$pruning_date = str_replace( ' years', '', $pruning_date );
								$pruning_unit = $this->_plugin->settings->get_pruning_unit();

								// Check if pruning limit was enabled for backwards compatibility.
								if ( $this->_plugin->settings->IsPruningLimitEnabled() ) {
									$nbld         = true;
									$pruning_date = '6';
									$pruning_unit = 'months';
									$this->_plugin->settings->SetPruningDate( $pruning_date . ' ' . $pruning_unit );
									$this->_plugin->settings->SetPruningDateEnabled( true );
									$this->_plugin->settings->SetPruningLimitEnabled( false );
								}
								?>
								<label for="delete1">
									<input type="radio" id="delete1" name="PruneBy" value="date" <?php checked( $nbld ); ?> />
									<?php esc_html_e( 'Delete events older than', 'wp-security-audit-log' ); ?>
								</label>
								<input type="text" id="PruningDate" name="PruningDate"
									value="<?php echo esc_attr( $pruning_date ); ?>"
									onfocus="jQuery('#delete1').attr('checked', true);"
								/>
								<select name="pruning-unit" id="pruning-unit">
									<option value="months" <?php echo ( 'months' === $pruning_unit ) ? 'selected' : false; ?>><?php esc_html_e( 'Months', 'wp-security-audit-log' ); ?></option>
									<option value="years" <?php echo ( 'years' === $pruning_unit ) ? 'selected' : false; ?>><?php esc_html_e( 'Years', 'wp-security-audit-log' ); ?></option>
								</select>
							</fieldset>

							<?php if ( $this->_plugin->settings->IsPruningDateEnabled() ) : ?>
								<p class="description">
									<?php
									$next = wp_next_scheduled( 'wsal_cleanup' );
									echo esc_html__( 'The next scheduled purging of activity log data that is older than ', 'wp-security-audit-log' );
									echo esc_html( $pruning_date . ' ' . $pruning_unit );
									echo sprintf(
										' is in %s.',
										esc_html( human_time_diff( current_time( 'timestamp' ), $next ) )
									);
									echo '<!-- ' . esc_html( date( 'dMy H:i:s', $next ) ) . ' --> ';
									echo esc_html__( 'You can run the purging process now by clicking the button below.', 'wp-security-audit-log' );
									?>
								</p>
								<p>
									<a class="button-primary" href="<?php echo esc_url( add_query_arg( 'action', 'AjaxRunCleanup', admin_url( 'admin-ajax.php' ) ) ); ?>"><?php esc_html_e( 'Purge Old Data', 'wp-security-audit-log' ) ?></a>
								</p>
							<?php endif; ?>
						</td>
					</tr>
					<!-- Audit Log Retention -->
				</tbody>
			</table>
		<?php endif; ?>
		<!-- Audit Log Retention -->

		<h3><?php esc_html_e( 'What timestamp you would like to see in the WordPress activity log?', 'wp-security-audit-log' ); ?></h3>
		<p class="description"><?php esc_html_e( 'Note that the WordPress\' timezone might be different from that configured on the server so when you switch from UTC to WordPress timezone or vice versa you might notice a big difference.', 'wp-security-audit-log' ); ?></p>
		<table class="form-table wsal-tab">
			<tbody>
				<tr>
					<th><label for="timezone-default"><?php esc_html_e( 'Events Timestamp', 'wp-security-audit-log' ); ?></label></th>
					<td>
						<fieldset>
							<?php
							$timezone = $this->_plugin->settings->GetTimezone();

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
			</tbody>
		</table>
		<!-- Timestamp -->

		<h3><?php esc_html_e( 'What user information should be displayed in the WordPress activity log?', 'wp-security-audit-log' ); ?></h3>
		<p class="description"><?php esc_html_e( 'Usernames might not be the same as a user\'s first and last name so it can be difficult to recognize whose user was that did a change. When there is no first & last name or public display name configured the plugin will revert back to the WordPress username.', 'wp-security-audit-log' ); ?></p>
		<table class="form-table wsal-tab">
			<tbody>
				<tr>
					<th><label for="timezone-default"><?php esc_html_e( 'User Information in Audit Log', 'wp-security-audit-log' ); ?></label></th>
					<td>
						<fieldset>
							<?php $type_username = $this->_plugin->settings->get_type_username(); ?>
							<label for="column_username">
								<input type="radio" name="type_username" id="column_username" style="margin-top: -2px;" <?php checked( $type_username, 'username' ); ?> value="username">
								<span><?php esc_html_e( 'WordPress Username', 'wp-security-audit-log' ); ?></span>
							</label>
							<br/>
							<label for="columns_first_last_name">
								<input type="radio" name="type_username" id="columns_first_last_name" style="margin-top: -2px;" <?php checked( $type_username, 'first_last_name' ); ?> value="first_last_name">
								<span><?php esc_html_e( 'First Name & Last Name', 'wp-security-audit-log' ); ?></span>
							</label>
							<br/>
							<label for="columns_display_name">
								<input type="radio" name="type_username" id="columns_display_name" style="margin-top: -2px;" <?php checked( $type_username, 'display_name' ); ?> value="display_name">
								<span><?php esc_html_e( 'Configured Public Display Name', 'wp-security-audit-log' ); ?></span>
							</label>
						</fieldset>
					</td>
				</tr>
				<!-- Select type of name -->
			</tbody>
		</table>
		<!-- User Information -->

		<h3><?php esc_html_e( 'Select the columns to be displayed in the WordPress activity log', 'wp-security-audit-log' ); ?></h3>
		<p class="description"><?php esc_html_e( 'When you deselect a column it won’t be shown in the activity log viewer but the data will still be recorded by the plugin, so when you select it again all the data will be displayed.', 'wp-security-audit-log' ); ?></p>
		<table class="form-table wsal-tab">
			<tbody>
				<tr>
					<th><label for="columns"><?php esc_html_e( 'Audit Log Columns Selection', 'wp-security-audit-log' ); ?></label></th>
					<td>
						<fieldset>
							<?php $columns = $this->_plugin->settings->GetColumns(); ?>
							<?php foreach ( $columns as $key => $value ) { ?>
								<label for="columns">
									<input type="checkbox" name="Columns[<?php echo esc_attr( $key ); ?>]" id="<?php echo esc_attr( $key ); ?>" class="sel-columns" style="margin-top: -2px;"
										<?php checked( $value, '1' ); ?> value="1">
									<?php if ( 'alert_code' === $key ) : ?>
										<span><?php esc_html_e( 'Event ID', 'wp-security-audit-log' ); ?></span>
									<?php elseif ( 'type' === $key ) : ?>
										<span><?php esc_html_e( 'Severity', 'wp-security-audit-log' ); ?></span>
									<?php elseif ( 'date' === $key ) : ?>
										<span><?php esc_html_e( 'Date & Time', 'wp-security-audit-log' ); ?></span>
									<?php elseif ( 'username' === $key ) : ?>
										<span><?php esc_html_e( 'User', 'wp-security-audit-log' ); ?></span>
									<?php elseif ( 'source_ip' === $key ) : ?>
										<span><?php esc_html_e( 'Source IP Address', 'wp-security-audit-log' ); ?></span>
									<?php else : ?>
										<span><?php echo esc_html( ucwords( str_replace( '_', ' ', $key ) ) ); ?></span>
									<?php endif; ?>
								</label>
								<br/>
							<?php } ?>
						</fieldset>
					</td>
				</tr>
				<!-- Audit Log Columns Selection -->
			</tbody>
		</table>
		<!-- Audit Log Columns -->

		<h3><?php esc_html_e( 'Do you want the activity log viewer to auto refresh?', 'wp-security-audit-log' ); ?></h3>
		<p class="description"><?php esc_html_e( 'The activity log viewer auto refreshes every 30 seconds when opened so you can see the latest events as they happen almost in real time.', 'wp-security-audit-log' ); ?></p>
		<table class="form-table wsal-tab">
			<tbody>
				<tr>
					<th><label for="aroption_on"><?php esc_html_e( 'Refresh Audit Log Viewer', 'wp-security-audit-log' ); ?></label></th>
					<td>
						<fieldset>
							<?php $are = $this->_plugin->settings->IsRefreshAlertsEnabled(); ?>
							<label for="aroption_on">
								<input type="radio" name="EnableAuditViewRefresh" id="aroption_on" style="margin-top: -2px;"
									<?php checked( $are ); ?> value="1">
								<span><?php esc_html_e( 'Auto refresh', 'wp-security-audit-log' ); ?></span>
							</label>
							<br/>
							<label for="aroption_off">
								<input type="radio" name="EnableAuditViewRefresh" id="aroption_off" style="margin-top: -2px;"
									<?php checked( $are, false ); ?> value="0">
								<span><?php esc_html_e( 'Do not auto refresh', 'wp-security-audit-log' ); ?></span>
							</label>
						</fieldset>
					</td>
				</tr>
				<!-- Refresh Audit Log Viewer -->
			</tbody>
		</table>
		<!-- Refresh Audit Log -->

		<h3><?php esc_html_e( 'Do you want to keep a log of WordPress background activity?', 'wp-security-audit-log' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'WordPress does a lot of things in the background that you do not necessarily need to know about, such as; deletion of post revisions, deletion of auto saved drafts etc. By default the plugin does not report them since there might be a lot and are irrelevant to the user.', 'wp-security-audit-log' ); ?>
		</p>
		<table class="form-table wsal-tab">
			<tbody>
				<tr>
					<th><label for="wp_backend_no"><?php esc_html_e( 'Disable Events for WordPress Background Activity', 'wp-security-audit-log' ); ?></label></th>
					<td>
						<fieldset>
							<label for="wp_backend_yes">
								<input type="radio" name="WPBackend" value="1" id="wp_backend_yes"
									<?php checked( $this->_plugin->settings->IsWPBackend() ); ?> />
								<?php esc_html_e( 'Yes', 'wp-security-audit-log' ); ?>
							</label>
							<br/>
							<label for="wp_backend_no">
								<input type="radio" name="WPBackend" value="0" id="wp_backend_no"
									<?php checked( $this->_plugin->settings->IsWPBackend(), false ); ?> />
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
		$pruning_date = isset( $post_array['PruningDate'] ) ? (int) sanitize_text_field( $post_array['PruningDate'] ) : false;
		$pruning_unit = isset( $post_array['pruning-unit'] ) ? sanitize_text_field( $post_array['pruning-unit'] ) : false;
		$pruning_date = ( ! empty( $pruning_date ) && ! empty( $pruning_unit ) ) ? $pruning_date . ' ' . $pruning_unit : false;

		$this->_plugin->settings->SetPruningDateEnabled( isset( $post_array['PruneBy'] ) ? 'date' === $post_array['PruneBy'] : '' );
		$this->_plugin->settings->SetPruningDate( $pruning_date );
		$this->_plugin->settings->set_pruning_unit( $pruning_unit );
		$this->_plugin->settings->SetRefreshAlertsEnabled( $post_array['EnableAuditViewRefresh'] );
		$this->_plugin->settings->SetTimezone( $post_array['Timezone'] );
		$this->_plugin->settings->set_type_username( $post_array['type_username'] );
		$this->_plugin->settings->SetWPBackend( isset( $post_array['WPBackend'] ) ? sanitize_text_field( $post_array['WPBackend'] ) : false );
		if ( ! empty( $post_array['Columns'] ) ) {
			$this->_plugin->settings->SetColumns( $post_array['Columns'] );
		}
	}

	/**
	 * Tab: `File Changes`
	 */
	private function tab_file_changes() {
		// Load file changes settings.
		$this->load_file_changes_settings();
		?>
		<p class="description">
			<?php esc_html_e( 'The plugin runs file integrity scans on your website so it keeps a log when a file is added, modified or deleted. All the settings for the file integrity scans can be found in this page.', 'wp-security-audit-log' ); ?>
			<?php echo wp_kses( __( '<a href="https://www.wpsecurityauditlog.com/support-documentation/wordpress-files-changes-warning-activity-logs/" target="_blank">Refer to the WordPress file integrity scans feature page</a> for more information.', 'wp-security-audit-log' ), $this->_plugin->allowed_html_tags ); ?>
		</p>

		<h3><?php esc_html_e( 'Do you want the plugin to scan your website for file changes?', 'wp-security-audit-log' ); ?></h3>
		<table class="form-table wsal-tab">
			<tbody>
				<tr>
					<th>
						<label for="wsal-file-changes"><?php esc_html_e( 'Keep a Log of File Changes', 'wp-security-audit-log' ); ?></label>
					</th>
					<td>
						<fieldset>
							<label>
								<input id="enable" name="wsal-file-changes" type="radio" value="enable"
									<?php checked( $this->scan_settings['scan_file_changes'], 'enable' ); ?>
								/>
								<?php esc_html_e( 'Yes', 'wp-security-audit-log' ); ?>
							</label>
							<br />
							<label>
								<input id="disable" name="wsal-file-changes" type="radio" value="disable"
									<?php checked( $this->scan_settings['scan_file_changes'], 'disable' ); ?>
								/>
								<?php esc_html_e( 'No', 'wp-security-audit-log' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
			</tbody>
		</table>
		<!-- wsal-file-changes -->

		<h3><?php esc_html_e( 'Which file changes events do you want to keep a log of in the activity log?', 'wp-security-audit-log' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'By default the plugin will keep a log whenever a file has been added, modified or deleted. It will also log an event in the activity log when a file is too big to scan or there are too many files to scan. Click on the link to specify which of these events the plugin should keep a log of.', 'wp-security-audit-log' ); ?>
		</p>
		<table class="form-table wsal-tab">
			<tbody>
				<tr>
					<th>
						<label for="wsal-file-alert-types"><?php esc_html_e( 'Alert me when', 'wp-security-audit-log' ); ?></label>
					</th>
					<td>
						<p>
							<?php
							$wsal_events_page = '';
							if ( ! is_multisite() ) {
								$wsal_events_page = add_query_arg( 'page', 'wsal-togglealerts', admin_url( 'admin.php' ) );
							} else {
								$wsal_events_page = add_query_arg( 'page', 'wsal-togglealerts', network_admin_url( 'admin.php' ) );
							}
							?>
							<a href="<?php echo esc_url( $wsal_events_page . '#tab-system' ); ?>">
								<?php esc_html_e( 'Configure Events', 'wp-security-audit-log' ); ?>
							</a>
						</p>
					</td>
				</tr>
			</tbody>
		</table>
		<!-- wsal-file-alert-types -->

		<h3><?php esc_html_e( 'When should the plugin scan your website for file changes?', 'wp-security-audit-log' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'By default the plugin will run file integrity scans once a week. If you can, ideally you should run file integrity scans on a daily basis. The file integrity scanner is very efficient and requires very little resources. Though if you have a fairly large website we recommend you to scan it when it is the least busy. The scan process should only take a few seconds to complete.', 'wp-security-audit-log' ); ?>
		</p>
		<table class="form-table wsal-tab">
			<tbody>
				<tr>
					<th>
						<label for="wsal-scan-frequency"><?php esc_html_e( 'Scan Frequency', 'wp-security-audit-log' ); ?></label>
					</th>
					<td>
						<?php
						$frequency_options = array(
							'daily'   => __( 'Daily', 'wp-security-audit-log' ),
							'weekly'  => __( 'Weekly', 'wp-security-audit-log' ),
							'monthly' => __( 'Monthly', 'wp-security-audit-log' ),
						);
						?>
						<fieldset id="wsal-scan-frequency">
							<select name="wsal-scan-frequency">
								<?php foreach ( $frequency_options as $value => $html ) : ?>
									<option
										value="<?php echo esc_attr( $value ); ?>"
										<?php echo esc_attr( $value === $this->scan_settings['scan_frequency'] ? 'selected' : false ); ?>>
										<?php echo esc_html( $html ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</fieldset>
					</td>
				</tr>
				<tr id="wsal-scan-time">
					<th>
						<label for="wsal-scan-hour"><?php esc_html_e( 'Scan Time', 'wp-security-audit-log' ); ?></label>
					</th>
					<td>
						<?php
						// Scan hours option.
						$scan_hours = array(
							'00' => __( '00:00', 'wp-security-audit-log' ),
							'01' => __( '01:00', 'wp-security-audit-log' ),
							'02' => __( '02:00', 'wp-security-audit-log' ),
							'03' => __( '03:00', 'wp-security-audit-log' ),
							'04' => __( '04:00', 'wp-security-audit-log' ),
							'05' => __( '05:00', 'wp-security-audit-log' ),
							'06' => __( '06:00', 'wp-security-audit-log' ),
							'07' => __( '07:00', 'wp-security-audit-log' ),
							'08' => __( '08:00', 'wp-security-audit-log' ),
							'09' => __( '09:00', 'wp-security-audit-log' ),
							'10' => __( '10:00', 'wp-security-audit-log' ),
							'11' => __( '11:00', 'wp-security-audit-log' ),
							'12' => __( '12:00', 'wp-security-audit-log' ),
							'13' => __( '13:00', 'wp-security-audit-log' ),
							'14' => __( '14:00', 'wp-security-audit-log' ),
							'15' => __( '15:00', 'wp-security-audit-log' ),
							'16' => __( '16:00', 'wp-security-audit-log' ),
							'17' => __( '17:00', 'wp-security-audit-log' ),
							'18' => __( '18:00', 'wp-security-audit-log' ),
							'19' => __( '19:00', 'wp-security-audit-log' ),
							'20' => __( '20:00', 'wp-security-audit-log' ),
							'21' => __( '21:00', 'wp-security-audit-log' ),
							'22' => __( '22:00', 'wp-security-audit-log' ),
							'23' => __( '23:00', 'wp-security-audit-log' ),
						);

						// Scan days option.
						$scan_days = array(
							'1' => __( 'Monday', 'wp-security-audit-log' ),
							'2' => __( 'Tuesday', 'wp-security-audit-log' ),
							'3' => __( 'Wednesday', 'wp-security-audit-log' ),
							'4' => __( 'Thursday', 'wp-security-audit-log' ),
							'5' => __( 'Friday', 'wp-security-audit-log' ),
							'6' => __( 'Saturday', 'wp-security-audit-log' ),
							'7' => __( 'Sunday', 'wp-security-audit-log' ),
						);

						// Scan date option.
						$scan_date = array(
							'01' => __( '01', 'wp-security-audit-log' ),
							'02' => __( '02', 'wp-security-audit-log' ),
							'03' => __( '03', 'wp-security-audit-log' ),
							'04' => __( '04', 'wp-security-audit-log' ),
							'05' => __( '05', 'wp-security-audit-log' ),
							'06' => __( '06', 'wp-security-audit-log' ),
							'07' => __( '07', 'wp-security-audit-log' ),
							'08' => __( '08', 'wp-security-audit-log' ),
							'09' => __( '09', 'wp-security-audit-log' ),
							'10' => __( '10', 'wp-security-audit-log' ),
							'11' => __( '11', 'wp-security-audit-log' ),
							'12' => __( '12', 'wp-security-audit-log' ),
							'13' => __( '13', 'wp-security-audit-log' ),
							'14' => __( '14', 'wp-security-audit-log' ),
							'15' => __( '15', 'wp-security-audit-log' ),
							'16' => __( '16', 'wp-security-audit-log' ),
							'17' => __( '17', 'wp-security-audit-log' ),
							'18' => __( '18', 'wp-security-audit-log' ),
							'19' => __( '19', 'wp-security-audit-log' ),
							'20' => __( '20', 'wp-security-audit-log' ),
							'21' => __( '21', 'wp-security-audit-log' ),
							'22' => __( '22', 'wp-security-audit-log' ),
							'23' => __( '23', 'wp-security-audit-log' ),
							'24' => __( '24', 'wp-security-audit-log' ),
							'25' => __( '25', 'wp-security-audit-log' ),
							'26' => __( '26', 'wp-security-audit-log' ),
							'27' => __( '27', 'wp-security-audit-log' ),
							'28' => __( '28', 'wp-security-audit-log' ),
							'29' => __( '29', 'wp-security-audit-log' ),
							'30' => __( '30', 'wp-security-audit-log' ),
						);
						?>
						<fieldset>
							<span class="wsal-scan-time-container" id="wsal-scan-hour">
								<select name="wsal-scan-hour">
									<?php foreach ( $scan_hours as $value => $html ) : ?>
										<option
											value="<?php echo esc_attr( $value ); ?>"
											<?php echo esc_attr( $value == $this->scan_settings['scan_hour'] ? 'selected' : false ); ?>>
											<?php echo esc_html( $html ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<br />
								<span class="description">
									<?php esc_html_e( 'Hour', 'wp-security-audit-log' ); ?>
								</span>
							</span>

							<span class="wsal-scan-time-container hide" id="wsal-scan-day">
								<select name="wsal-scan-day">
									<?php foreach ( $scan_days as $value => $html ) : ?>
										<option
											value="<?php echo esc_attr( $value ); ?>"
											<?php echo esc_attr( $value == $this->scan_settings['scan_day'] ? 'selected' : false ); ?>>
											<?php echo esc_html( $html ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<br />
								<span class="description">
									<?php esc_html_e( 'Day', 'wp-security-audit-log' ); ?>
								</span>
							</span>

							<span class="wsal-scan-time-container hide" id="wsal-scan-date">
								<select name="wsal-scan-date">
									<?php foreach ( $scan_date as $value => $html ) : ?>
										<option
											value="<?php echo esc_attr( $value ); ?>"
											<?php echo esc_attr( $value == $this->scan_settings['scan_date'] ? 'selected' : false ); ?>>
											<?php echo esc_html( $html ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<br />
								<span class="description">
									<?php esc_html_e( 'Day', 'wp-security-audit-log' ); ?>
								</span>
							</span>
						</fieldset>
					</td>
				</tr>
			</tbody>
		</table>
		<!-- wsal-scan-frequency -->

		<h3><?php esc_html_e( 'Which directories should be scanned for file changes?', 'wp-security-audit-log' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'The plugin will scan all the directories in your WordPress website by default because that is the most secure option. Though if for some reason you do not want the plugin to scan any of these directories you can uncheck them from the below list.', 'wp-security-audit-log' ); ?>
		</p>
		<table class="form-table wsal-tab">
			<tbody>
				<tr>
					<th>
						<label for="wsal-scan-directories"><?php esc_html_e( 'Directories to scan', 'wp-security-audit-log' ); ?></label>
					</th>
					<td>
						<?php
						// WP Directories.
						$wp_directories = array(
							'root'               => __( 'Root directory of WordPress (excluding sub directories)', 'wp-security-audit-log' ),
							'wp-admin'           => __( 'WP Admin directory (/wp-admin/)', 'wp-security-audit-log' ),
							'wp-includes'        => __( 'WP Includes directory (/wp-includes/)', 'wp-security-audit-log' ),
							'wp-content'         => __( '/wp-content/ directory (excluding plugins, themes & uploads directories)', 'wp-security-audit-log' ),
							'wp-content/themes'  => __( 'Themes directory (/wp-content/themes/)', 'wp-security-audit-log' ),
							'wp-content/plugins' => __( 'Plugins directory (/wp-content/plugins/)', 'wp-security-audit-log' ),
							'wp-content/uploads' => __( 'Uploads directory (/wp-content/uploads/)', 'wp-security-audit-log' ),
						);

						// Check if multisite.
						if ( is_multisite() ) {
							// Upload directories of subsites.
							$wp_directories['wp-content/uploads/sites'] = __( 'Uploads directory of all sub sites on this network (/wp-content/sites/*)', 'wp-security-audit-log' );
						}
						?>
						<fieldset id="wsal-scan-directories">
							<?php foreach ( $wp_directories as $value => $html ) : ?>
								<label>
									<input
										name="wsal-scan-directories[<?php echo esc_attr( $value ); ?>]"
										type="checkbox"
										value="<?php echo esc_attr( $value ); ?>"
										<?php echo esc_attr( in_array( $value, $this->scan_settings['scan_directories'], true ) ? 'checked' : false ); ?>
									/>
									<?php echo esc_html( $html ); ?>
								</label>
								<br />
							<?php endforeach; ?>
						</fieldset>
					</td>
				</tr>
			</tbody>
		</table>
		<!-- wsal-scan-directories -->

		<h3><?php esc_html_e( 'Do you want to exclude specific files or files with a particular extension from the scan?', 'wp-security-audit-log' ); ?></h3>
		<p class="description"><?php esc_html_e( 'The plugin will scan everything that is in the WordPress root directory or below, even if the files and directories are not part of WordPress. It is recommended to scan all source code files and only exclude files that cannot be tampered, such as text files, media files etc, most of which are already excluded by default.', 'wp-security-audit-log' ); ?></p>
		<table class="form-table wsal-tab">
			<tbody>
				<tr>
					<th>
						<label for="wsal_add_dir_name"><?php esc_html_e( 'Exclude All Files in These Directories', 'wp-security-audit-log' ); ?></label>
					</th>
					<td>
						<div class="wsal_file_containter">
							<div id="wsal_dirs">
								<?php foreach ( $this->scan_settings['scan_excluded_dirs'] as $index => $dir ) : ?>
									<span id="wsal_dir-<?php echo esc_attr( $dir ); ?>">
										<input type="checkbox" id="<?php echo esc_attr( $dir ); ?>" value="<?php echo esc_attr( $dir ); ?>" />
										<label for="<?php echo esc_attr( $dir ); ?>"><?php echo esc_html( $dir ); ?></label>
									</span>
								<?php endforeach; ?>
							</div>
							<?php wp_nonce_field( 'wsal-scan-remove-exception-dir', 'wsal_scan_remove_exception_dir' ); ?>
							<input class="button" id="wsal_remove_exception_dir" type="button" value="<?php esc_html_e( 'REMOVE', 'wp-security-audit-log' ); ?>" />
						</div>
						<div class="wsal_file_containter">
							<input type="text" id="wsal_add_dir_name" />
							<?php wp_nonce_field( 'wsal-scan-exception-dir', 'wsal_scan_exception_dir' ); ?>
							<input id="wsal_add_dir" class="button" type="button" value="<?php esc_html_e( 'ADD', 'wp-security-audit-log' ); ?>" />
						</div>
						<p class="description">
							<?php esc_html_e( 'Specify the name of the directory and the path to it in relation to the website\'s root. For example, if you want to want to exclude all files in the sub directory dir1/dir2 specify the following:', 'wp-security-audit-log' ); ?>
							<br>
							<?php echo esc_html( trailingslashit( ABSPATH ) ) . 'dir1/dir2/'; ?>
						</p>
						<span class="error hide" id="wsal_dir_error"></span>
					</td>
				</tr>
				<!-- wsal-scan-exclude-dirs -->

				<tr>
					<th>
						<label for="wsal_add_file_name"><?php esc_html_e( 'Exclude These Files', 'wp-security-audit-log' ); ?></label>
					</th>
					<td>
						<?php
						// Get files to be excluded.
						$excluded_files = $this->_plugin->GetGlobalOption( 'scan_excluded_files', array() );
						?>
						<div class="wsal_file_containter">
							<div id="wsal_files">
								<?php foreach ( $excluded_files as $index => $file ) : ?>
									<span id="wsal_file-<?php echo esc_attr( $file ); ?>">
										<input type="checkbox" id="<?php echo esc_attr( $file ); ?>" value="<?php echo esc_attr( $file ); ?>" />
										<label for="<?php echo esc_attr( $file ); ?>"><?php echo esc_html( $file ); ?></label>
									</span>
								<?php endforeach; ?>
							</div>
							<?php wp_nonce_field( 'wsal-scan-remove-exception-file', 'wsal_scan_remove_exception_file' ); ?>
							<input class="button" id="wsal_remove_exception_file" type="button" value="<?php esc_html_e( 'REMOVE', 'wp-security-audit-log' ); ?>" />
						</div>
						<div class="wsal_file_containter">
							<input type="text" id="wsal_add_file_name" />
							<?php wp_nonce_field( 'wsal-scan-exception-file', 'wsal_scan_exception_file' ); ?>
							<input id="wsal_add_file" class="button" type="button" value="<?php esc_html_e( 'ADD', 'wp-security-audit-log' ); ?>" />
						</div>
						<p class="description">
							<?php esc_html_e( 'Specify the name and extension of the file(s) you want to exclude. Wildcard not supported. There is no need to specify the path of the file.', 'wp-security-audit-log' ); ?>
						</p>
						<span class="error hide" id="wsal_file_name_error"></span>
					</td>
				</tr>
				<!-- wsal_add_file_name -->

				<tr>
					<th>
						<label for="wsal_add_file_type_name"><?php esc_html_e( 'Exclude these File Types', 'wp-security-audit-log' ); ?></label>
					</th>
					<td>
						<div class="wsal_file_containter">
							<div id="wsal_files_types">
								<?php foreach ( $this->scan_settings['scan_excluded_extensions'] as $index => $file_type ) : ?>
									<span id="wsal_file_type-<?php echo esc_attr( $file_type ); ?>">
										<input type="checkbox" id="<?php echo esc_attr( $file_type ); ?>" value="<?php echo esc_attr( $file_type ); ?>" />
										<label for="<?php echo esc_attr( $file_type ); ?>"><?php echo esc_html( $file_type ); ?></label>
									</span>
								<?php endforeach; ?>
							</div>
							<?php wp_nonce_field( 'wsal-scan-remove-exception-file-type', 'wsal_scan_remove_exception_file_type' ); ?>
							<input class="button" id="wsal_remove_exception_file_type" type="button" value="<?php esc_html_e( 'REMOVE', 'wp-security-audit-log' ); ?>" />
						</div>
						<div class="wsal_file_containter">
							<input type="text" id="wsal_add_file_type_name" />
							<?php wp_nonce_field( 'wsal-scan-exception-file-type', 'wsal_scan_exception_file_type' ); ?>
							<input id="wsal_add_file_type" class="button" type="button" value="<?php esc_html_e( 'ADD', 'wp-security-audit-log' ); ?>" />
						</div>
						<p class="description">
							<?php esc_html_e( 'Specify the extension of the file types you want to exclude. You should exclude any type of logs and backup files that tend to be very big.', 'wp-security-audit-log' ); ?>
						</p>
						<span class="error hide" id="wsal_file_type_error"></span>
					</td>
				</tr>
				<!-- wsal-scan-exclude-extensions -->
			</tbody>
		</table>

		<h3><?php esc_html_e( 'Launch an instant file integrity scan', 'wp-security-audit-log' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'Click the Scan Now button to launch an instant file integrity scan using the configured settings. You can navigate away from this page during the scan. Note that the instant scan can be more resource intensive than scheduled scans.', 'wp-security-audit-log' ); ?>
		</p>
		<table class="form-table wsal-tab">
			<tbody>
				<tr>
					<th>
						<label for="wsal-scan-now"><?php esc_html_e( 'Launch Instant Scan', 'wp-security-audit-log' ); ?></label>
					</th>
					<td>
						<input type="hidden" id="wsal-scan-now-nonce" name="wsal_scan_now_nonce" value="<?php echo esc_attr( wp_create_nonce( 'wsal-scan-now' ) ); ?>" />
						<input type="hidden" id="wsal-stop-scan-nonce" name="wsal_stop_scan_nonce" value="<?php echo esc_attr( wp_create_nonce( 'wsal-stop-scan' ) ); ?>" />
						<?php if ( ! $this->scan_settings['scan_in_progress'] ) : ?>
							<a href="javascript:;" class="button button-primary" id="wsal-scan-now">
								<?php esc_attr_e( 'Scan Now', 'wp-security-audit-log' ); ?>
							</a>
							<a href="javascript:;" class="button button-secondary" id="wsal-stop-scan" disabled>
								<?php esc_attr_e( 'Stop Scan', 'wp-security-audit-log' ); ?>
							</a>
						<?php else : ?>
							<a href="javascript:;" class="button button-primary" id="wsal-scan-now" disabled>
								<?php esc_attr_e( 'Scan in Progress', 'wp-security-audit-log' ); ?>
							</a>
							<a href="javascript:;" class="button button-ui-primary" id="wsal-stop-scan">
								<?php esc_attr_e( 'Stop Scan', 'wp-security-audit-log' ); ?>
							</a>
							<!-- Scan in progress -->
						<?php endif; ?>
					</td>
				</tr>
				<!-- wsal-scan-now -->
			</tbody>
		</table>
		<!-- / File Changes Logging Tab -->
		<?php
	}

	/**
	 * Save: `File Changes`
	 */
	private function tab_file_changes_save() {
		// Get $_POST global array.
		$post_array = filter_input_array( INPUT_POST );

		// Check and save enable/disable file changes feature.
		if ( isset( $post_array['wsal-file-changes'] ) && ! empty( $post_array['wsal-file-changes'] ) ) {
			$this->_plugin->SetGlobalOption( 'scan-file-changes', $post_array['wsal-file-changes'] );

			// Get file change scan alerts.
			$file_change_events = $this->_plugin->alerts->get_alerts_by_sub_category( 'File Changes' );
			$file_change_events = array_keys( $file_change_events );

			// Enable/disable events based on file changes.
			if ( 'disable' === $post_array['wsal-file-changes'] ) {
				// Get disabled events.
				$disabled_events = $this->_plugin->settings->GetDisabledAlerts();

				// Merge file changes events.
				$disabled_events = array_merge( $disabled_events, $file_change_events );

				// Save the events.
				$this->_plugin->alerts->SetDisabledAlerts( $disabled_events );
			} else {
				// Get disabled events.
				$disabled_events = $this->_plugin->settings->GetDisabledAlerts();

				foreach ( $file_change_events as $file_change_event ) {
					// Search for file change events in disabled events.
					$key = array_search( $file_change_event, $disabled_events, true );

					// If key is found, then unset it.
					if ( $key ) {
						unset( $disabled_events[ $key ] );
					}
				}

				// Save the disabled events.
				$this->_plugin->alerts->SetDisabledAlerts( $disabled_events );
			}
		} else {
			$this->_plugin->SetGlobalOption( 'scan-file-changes', false );
		}

		// Check and save scan frequency.
		$this->_plugin->SetGlobalOption( 'scan-frequency', isset( $post_array['wsal-scan-frequency'] ) ? $post_array['wsal-scan-frequency'] : false );
		$this->_plugin->SetGlobalOption( 'scan-hour', isset( $post_array['wsal-scan-hour'] ) ? $post_array['wsal-scan-hour'] : false );
		$this->_plugin->SetGlobalOption( 'scan-day', isset( $post_array['wsal-scan-day'] ) ? $post_array['wsal-scan-day'] : false );
		$this->_plugin->SetGlobalOption( 'scan-date', isset( $post_array['wsal-scan-date'] ) ? $post_array['wsal-scan-date'] : false );

		// Check and save scan directories.
		if (
			isset( $post_array['wsal-scan-directories'] )
			&& is_array( $post_array['wsal-scan-directories'] )
		) {
			$scan_directories = array_keys( $post_array['wsal-scan-directories'] );
			$this->_plugin->SetGlobalOption( 'scan-directories', $scan_directories );
		}
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
							<input type="button" id="ExUserQueryAdd" class="button-primary" value="Add">
							<br style="clear: both;"/>
							<div id="ExUserList">
								<?php foreach ( $this->_plugin->settings->GetExcludedMonitoringUsers() as $item ) : ?>
									<span class="sectoken-<?php echo esc_attr( $this->GetTokenType( $item ) ); ?>">
									<input type="hidden" name="ExUsers[]" value="<?php echo esc_attr( $item ); ?>"/>
									<?php echo esc_html( $item ); ?>
									<a href="javascript:;" title="Remove">&times;</a>
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
							<input type="button" id="ExRoleQueryAdd" class="button-primary" value="Add">
							<br style="clear: both;"/>
							<div id="ExRoleList">
								<?php foreach ( $this->_plugin->settings->GetExcludedMonitoringRoles() as $item ) : ?>
									<span class="sectoken-<?php echo esc_attr( $this->GetTokenType( $item ) ); ?>">
									<input type="hidden" name="ExRoles[]" value="<?php echo esc_attr( $item ); ?>"/>
									<?php echo esc_html( $item ); ?>
									<a href="javascript:;" title="Remove">&times;</a>
									</span>
								<?php endforeach; ?>
							</div>
						</fieldset>
					</td>
				</tr>
				<!-- Exclude Roles -->

				<tr>
					<th><label for="IpAddrQueryBox"><?php esc_html_e( 'Exclude IP Addresses:', 'wp-security-audit-log' ); ?></label></th>
					<td>
						<fieldset>
							<input type="text" id="IpAddrQueryBox" style="width: 250px;">
							<input type="button" id="IpAddrQueryAdd" class="button-primary" value="Add">
							<br style="clear: both;"/>
							<div id="IpAddrList">
								<?php foreach ( $this->_plugin->settings->GetExcludedMonitoringIP() as $item ) : ?>
									<span class="sectoken-<?php echo esc_attr( $this->GetTokenType( $item ) ); ?>">
										<input type="hidden" name="IpAddrs[]" value="<?php echo esc_attr( $item ); ?>"/>
										<?php echo esc_html( $item ); ?>
										<a href="javascript:;" title="Remove">&times;</a>
									</span>
								<?php endforeach; ?>
							</div>
						</fieldset>
					</td>
				</tr>
				<!-- Exclude IP Addresses -->

				<tr>
					<th><label for="ExCPTsQueryBox"><?php esc_html_e( 'Exclude Post Type:', 'wp-security-audit-log' ); ?></label></th>
					<td>
						<fieldset>
							<input type="text" id="ExCPTsQueryBox" style="width: 250px;">
							<input type="button" id="ExCPTsQueryAdd" class="button-primary" value="Add">
							<br style="clear: both;"/>
							<div id="ExCPTsList">
								<?php foreach ( $this->_plugin->settings->get_excluded_post_types() as $item ) : ?>
									<span class="sectoken-<?php echo esc_attr( $this->GetTokenType( $item ) ); ?>">
										<input type="hidden" name="ExCPTss[]" value="<?php echo esc_attr( $item ); ?>"/>
										<?php echo esc_html( $item ); ?>
										<a href="javascript:;" title="Remove">&times;</a>
									</span>
								<?php endforeach; ?>
							</div>
							<p class="description"><?php esc_html_e( 'WordPress has the post and page post types by default though your website might use more post types (custom post types). You can exclude all post types, including the default WordPress ones.', 'wp-security-audit-log' ); ?></p>
						</fieldset>
					</td>
				</tr>
				<!-- Exclude Custom Post Types -->

				<tr>
					<th><label for="CustomQueryBox"><?php esc_html_e( 'Exclude Custom Fields:', 'wp-security-audit-log' ); ?></label></th>
					<td>
						<fieldset>
							<input type="text" id="CustomQueryBox" style="width: 250px;">
							<input type="button" id="CustomQueryAdd" class="button-primary" value="Add">
							<br style="clear: both;"/>
							<div id="CustomList">
								<?php foreach ( $this->_plugin->settings->GetExcludedMonitoringCustom() as $item ) : ?>
									<span class="sectoken-<?php echo esc_attr( $this->GetTokenType( $item ) ); ?>">
										<input type="hidden" name="Customs[]" value="<?php echo esc_attr( $item ); ?>"/>
										<?php echo esc_html( $item ); ?>
										<a href="javascript:;" title="Remove">&times;</a>
									</span>
								<?php endforeach; ?>
							</div>
							<p class="description"><?php esc_html_e( 'You can use the * wildcard to exclude multiple matching custom fields. For example to exclude all custom fields starting with wp123 enter wp123*', 'wp-security-audit-log' ); ?></p>
						</fieldset>
					</td>
				</tr>
				<!-- Exclude Custom Fields -->

				<tr>
					<th><label for="ExURLsQueryBox"><?php esc_html_e( 'Exclude Non-Existing URLs:', 'wp-security-audit-log' ); ?></label></th>
					<td>
						<fieldset>
							<input type="text" id="ExURLsQueryBox" style="width: 250px;">
							<input type="button" id="ExURLsQueryAdd" class="button-primary" value="Add">
							<br style="clear: both;"/>
							<div id="ExURLsList">
								<?php foreach ( $this->_plugin->settings->get_excluded_urls() as $item ) : ?>
									<span class="sectoken-<?php echo esc_attr( $this->GetTokenType( $item ) ); ?>">
										<input type="hidden" name="ExURLss[]" value="<?php echo esc_attr( $item ); ?>"/>
										<?php echo esc_html( $item ); ?>
										<a href="javascript:;" title="Remove">&times;</a>
									</span>
								<?php endforeach; ?>
							</div>
							<p class="description">
								<?php esc_html_e( 'Add the non existing URLs for which you do not want to be alerted of HTTP 404 errors in the activity log by specifying the complete URL.	Examples below:', 'wp-security-audit-log' ); ?>
								<br>
								<?php echo esc_html__( 'File: ', 'wp-security-audit-log' ) . esc_url( home_url() ) . '/subdirectory/file.php'; ?>
								<br>
								<?php echo esc_html__( 'Directory: ', 'wp-security-audit-log' ) . esc_url( home_url() ) . '/subdirectory/subdirectory2'; ?>
							</p>
						</fieldset>
					</td>
				</tr>
				<!-- Exclude 404 URLs -->
			</tbody>
		</table>
		<!-- / Exclude Objects Tab -->
		<?php
	}

	/**
	 * Save: `Exclude Objects`
	 */
	private function tab_exclude_objects_save() {
		// Get $_POST global array.
		$post_array = filter_input_array( INPUT_POST );

		$this->_plugin->settings->SetExcludedMonitoringUsers( isset( $post_array['ExUsers'] ) ? $post_array['ExUsers'] : array() );
		$this->_plugin->settings->SetExcludedMonitoringRoles( isset( $post_array['ExRoles'] ) ? $post_array['ExRoles'] : array() );
		$this->_plugin->settings->SetExcludedMonitoringCustom( isset( $post_array['Customs'] ) ? $post_array['Customs'] : array() );
		$this->_plugin->settings->SetExcludedMonitoringIP( isset( $post_array['IpAddrs'] ) ? $post_array['IpAddrs'] : array() );
		$this->_plugin->settings->set_excluded_post_types( isset( $post_array['ExCPTss'] ) ? $post_array['ExCPTss'] : array() );
		$this->_plugin->settings->set_excluded_urls( isset( $post_array['ExURLss'] ) ? $post_array['ExURLss'] : array() );
	}

	/**
	 * Tab: `Advanced Settings`
	 */
	private function tab_advanced_settings() {
		?>
		<p class="description">
			<?php esc_html_e( 'These settings are for advanced users.', 'wp-security-audit-log' ); ?>
			<?php echo sprintf( __( 'If you have any questions <a href="https://www.wpsecurityauditlog.com/contact/" target="_blank">contact us</a>.', 'wp-security-audit-log' ), $this->_plugin->allowed_html_tags ); ?>
		</p>

		<h3><?php esc_html_e( 'Troubleshooting setting: Keep a debug log of all the requests this website receives', 'wp-security-audit-log' ); ?></h3>
		<p class="description"><?php esc_html_e( 'Only enable the request log on testing, staging and development website. Never enable logging on a live website unless instructed to do so. Enabling request logging on a live website may degrade the performance of the website.', 'wp-security-audit-log' ); ?></p>
		<table class="form-table wsal-tab">
			<tbody>
				<tr>
					<th><label><?php esc_html_e( 'Request Log', 'wp-security-audit-log' ); ?></label></th>
					<td>
						<fieldset>
							<?php $devoption_checked = $this->_plugin->settings->IsDevOptionEnabled( WSAL_Settings::OPT_DEV_REQUEST_LOG ); ?>
							<label for="devoption_yes">
								<input type="radio" name="DevOptions" id="devoption_yes"
									<?php checked( $devoption_checked, true ); ?>
									value="<?php echo esc_attr( WSAL_Settings::OPT_DEV_REQUEST_LOG ); ?>">
								<?php esc_html_e( 'Yes', 'wp-security-audit-log' ); ?>
							</label>
							<br>
							<label for="devoption_no">
								<input type="radio" name="DevOptions" id="devoption_no" <?php checked( $devoption_checked, false ); ?> value="0">
								<?php esc_html_e( 'No', 'wp-security-audit-log' ); ?>
							</label>
							<p class="description">
								<?php
								echo wp_kses(
									__( '<strong>Note:</strong> The requests debug log file is saved as request.log.php in the /wp-content/uploads/wp-security-audit-log/ directory.' ),
									$this->_plugin->allowed_html_tags
								);
								?>
							</p>
						</fieldset>
					</td>
				</tr>
				<!-- / Developer Options -->
			</tbody>
		</table>

		<h3><?php esc_html_e( 'Reset plugin settings to default', 'wp-security-audit-log' ); ?></h3>
		<p class="description"><?php esc_html_e( 'Click the RESET button to reset ALL plugin settings to default. Note that the activity log data will be retained and only the plugin settings will be reset. To purge the data of the activity log use the setting below.', 'wp-security-audit-log' ); ?></p>
		<table class="form-table wsal-tab">
			<tbody>
				<tr>
					<th><?php esc_html_e( 'Reset Settings', 'wp-security-audit-log' ); ?></th>
					<td>
						<a href="#wsal_reset_settings" class="button-primary"><?php esc_html_e( 'RESET', 'wp-security-audit-log' ); ?></a>
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
						<a href="#wsal_purge_activity" class="button-primary"><?php esc_html_e( 'PURGE', 'wp-security-audit-log' ); ?></a>
					</td>
				</tr>
			</tbody>
		</table>

		<h3><?php esc_html_e( 'MainWP Child Site Stealth Mode', 'wp-security-audit-log' ); ?></h3>
		<p class="description"><?php esc_html_e( 'This option is enabled automatically when the plugin detects the MainWP Child plugin on the site. When this setting is enabled plugin access is restricted to the administrator who installs the plugin, the plugin is not shown in the list of installed plugins and no admin notifications are shown. Disable this option to change the plugin to the default setup.', 'wp-security-audit-log' ); ?></p>
		<table class="form-table wsal-tab">
			<tbody>
				<tr>
					<th><label for="mwp_stealth_mode"><?php esc_html_e( 'Enable MainWP Child Site Stealth Mode', 'wp-security-audit-log' ); ?></label></th>
					<td>
						<fieldset <?php echo wsal_freemius()->is_premium() ? 'disabled' : false; ?>>
							<label for="mwp_stealth_yes">
								<?php $stealth_mode = $this->_plugin->settings->is_stealth_mode(); ?>
								<input type="radio" name="mwp_stealth_mode" value="yes" id="mwp_stealth_yes"
									<?php checked( $stealth_mode ); ?>
								/>
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
				<!-- / Remove Data on Uninstall -->
			</tbody>
		</table>

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
									<?php checked( $this->_plugin->settings->IsDeleteData() ); ?>
								/>
								<?php esc_html_e( 'Yes', 'wp-security-audit-log' ); ?>
							</label>
							<br>
							<label for="delete_data_no">
								<input type="radio" name="DeleteData" value="0" id="delete_data_no"
									<?php checked( $this->_plugin->settings->IsDeleteData(), false ); ?>
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
			<h3><?php esc_html_e( 'Are you sure you want to reset all the plugin settings to default?', 'wp-security-audit-log' ); ?></h3>
			<br>
			<input type="hidden" id="wsal-reset-settings-nonce" value="<?php echo esc_attr( wp_create_nonce( 'wsal-reset-settings' ) ); ?>">
			<button data-remodal-action="confirm" class="remodal-confirm"><?php esc_html_e( 'Yes', 'wp-security-audit-log' ); ?></button>
			<button data-remodal-action="cancel" class="remodal-cancel"><?php esc_html_e( 'No', 'wp-security-audit-log' ); ?></button>
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
	 */
	private function tab_advanced_settings_save() {
		// Get $_POST global array.
		$post_array = filter_input_array( INPUT_POST );

		$this->_plugin->settings->SetDeleteData( isset( $post_array['DeleteData'] ) ? sanitize_text_field( $post_array['DeleteData'] ) : false );
		$this->_plugin->settings->ClearDevOptions();
		if ( isset( $post_array['DevOptions'] ) && 'r' === $post_array['DevOptions'] ) {
			$this->_plugin->settings->SetDevOptionEnabled( 'r', true );
		} else {
			$this->_plugin->settings->SetDevOptionEnabled( 'r', false );
		}

		$stealth_mode = isset( $post_array['mwp_stealth_mode'] ) ? $post_array['mwp_stealth_mode'] : false;
		if ( 'yes' === $stealth_mode ) {
			if ( is_plugin_active( 'mainwp-child/mainwp-child.php' ) ) {
				$this->_plugin->settings->set_mainwp_child_stealth_mode();
			} else {
				throw new Exception( __( 'MainWP Child plugin is not active on this website.', 'wp-security-audit-log' ) );
			}
		} else {
			$this->_plugin->settings->deactivate_mainwp_child_stealth_mode();
		}
	}

	/**
	 * Method: Get View Header.
	 */
	public function Header() {
		wp_enqueue_style(
			'settings',
			$this->_plugin->GetBaseUrl() . '/css/settings.css',
			array(),
			filemtime( $this->_plugin->GetBaseDir() . '/css/settings.css' )
		);

		// Check current tab.
		if ( ! empty( $this->current_tab ) && 'advanced-settings' === $this->current_tab ) {
			// Remodal styles.
			wp_enqueue_style( 'wsal-remodal', WSAL_BASE_URL . 'css/remodal.css', array(), '1.1.1' );
			wp_enqueue_style( 'wsal-remodal-theme', WSAL_BASE_URL . 'css/remodal-default-theme.css', array(), '1.1.1' );
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
	 * Method: Get View Footer.
	 */
	public function Footer() {
		// Enqueue jQuery UI from core.
		wp_enqueue_script(
			'wsal-jquery-ui',
			'//code.jquery.com/ui/1.10.3/jquery-ui.js',
			array(),
			'1.10.3',
			false
		);

		// Check current tab.
		if ( ! empty( $this->current_tab ) && 'advanced-settings' === $this->current_tab ) {
			// Remodal script.
			wp_enqueue_script(
				'wsal-remodal-js',
				WSAL_BASE_URL . 'js/remodal.min.js',
				array(),
				'1.1.1',
				true
			);
		}

		// Register settings script.
		wp_register_script(
			'settings',
			$this->_plugin->GetBaseUrl() . '/js/settings.js',
			array(),
			filemtime( $this->_plugin->GetBaseDir() . '/js/settings.js' ),
			true
		);
		// Passing nonce for security to JS file.
		$wsal_data = array(
			'wp_nonce'       => wp_create_nonce( 'wsal-exclude-nonce' ),
			'invalidURL'     => esc_html__( 'The specified value is not a valid URL!', 'wp-security-audit-log' ),
			'invalidCPT'     => esc_html__( 'The specified value is not a valid post type!', 'wp-security-audit-log' ),
			'invalidIP'      => esc_html__( 'The specified value is not a valid IP address!', 'wp-security-audit-log' ),
			'invalidUser'    => esc_html__( 'The specified value is not a user nor a role!', 'wp-security-audit-log' ),
			'invalidFile'    => esc_html__( 'Filename cannot be added because it contains invalid characters.', 'wp-security-audit-log' ),
			'invalidFileExt' => esc_html__( 'File extension cannot be added because it contains invalid characters.', 'wp-security-audit-log' ),
			'invalidDir'     => esc_html__( 'Directory cannot be added because it contains invalid characters.', 'wp-security-audit-log' ),
			'scanNow'        => esc_html__( 'Scan Now', 'wp-security-audit-log' ),
			'scanFailed'     => esc_html__( 'Scan Failed', 'wp-security-audit-log' ),
			'scanInProgress' => esc_html__( 'Scan in Progress', 'wp-security-audit-log' ),
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
						alert( esc_html__( 'You have to select at least one column!', 'wp-security-audit-log' ) );
					}
				});
			});</script>
		<?php
	}

	/**
	 * Method: Ajax Request handler for AjaxGetAllUsers.
	 */
	public function AjaxGetAllUsers() {
		// Die if user does not have permission to view.
		if ( ! $this->_plugin->settings->CurrentUserCan( 'view' ) ) {
			die( 'Access Denied.' );
		}

		// Filter $_GET array for security.
		$get_array = filter_input_array( INPUT_GET );

		// Die if nonce verification failed.
		if ( ! wp_verify_nonce( $get_array['wsal_nonce'], 'wsal-exclude-nonce' ) ) {
			die( esc_html__( 'Nonce verification failed.', 'wp-security-audit-log' ) );
		}

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
	public function AjaxGetAllRoles() {
		// Die if user does not have permission to view.
		if ( ! $this->_plugin->settings->CurrentUserCan( 'view' ) ) {
			die( 'Access Denied.' );
		}

		// Filter $_GET array for security.
		$get_array = filter_input_array( INPUT_GET );

		// Die if nonce verification failed.
		if ( ! wp_verify_nonce( $get_array['wsal_nonce'], 'wsal-exclude-nonce' ) ) {
			die( esc_html__( 'Nonce verification failed.', 'wp-security-audit-log' ) );
		}

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
	 * Method: Get CPTs ajax handle.
	 *
	 * @since 2.6.7
	 */
	public function AjaxGetAllCPT() {
		// Die if user does not have permission to view.
		if ( ! $this->_plugin->settings->CurrentUserCan( 'view' ) ) {
			die( 'Access Denied.' );
		}

		// Filter $_GET array for security.
		$get_array = filter_input_array( INPUT_GET );

		// Die if nonce verification failed.
		if ( ! wp_verify_nonce( $get_array['wsal_nonce'], 'wsal-exclude-nonce' ) ) {
			die( esc_html__( 'Nonce verification failed.', 'wp-security-audit-log' ) );
		}

		// Get custom post types.
		$custom_post_types = array();
		$output     = 'names'; // names or objects, note names is the default
		$operator   = 'and'; // Conditions: and, or.
		$post_types = get_post_types( array(), $output, $operator );
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
	 * Method: Add file to file changes scan exception.
	 */
	public function scan_add_exception_file() {
		// Die if user does not have permission to change settings.
		if ( ! $this->_plugin->settings->CurrentUserCan( 'view' ) ) {
			die( 'Access Denied.' );
		}

		// Filter $_POST array for security.
		$post_array = filter_input_array( INPUT_POST );

		// Get data type to check file or extension.
		if ( ! isset( $post_array['data_type'] ) || empty( $post_array['data_type'] ) ) {
			die( esc_html__( 'Invalid setting type.', 'wp-security-audit-log' ) );
		} else {
			$data_type = $post_array['data_type'];
		}

		// Die if nonce verification failed.
		if ( 'file' === $data_type && ! wp_verify_nonce( $post_array['nonce'], 'wsal-scan-exception-file' ) ) {
			die( esc_html__( 'Nonce verification failed.', 'wp-security-audit-log' ) );
		} elseif ( 'extension' === $data_type && ! wp_verify_nonce( $post_array['nonce'], 'wsal-scan-exception-file-type' ) ) {
			die( esc_html__( 'Nonce verification failed.', 'wp-security-audit-log' ) );
		} elseif ( 'dir' === $data_type && ! wp_verify_nonce( $post_array['nonce'], 'wsal-scan-exception-dir' ) ) {
			die( esc_html__( 'Nonce verification failed.', 'wp-security-audit-log' ) );
		}

		// Get option type to be excluded.
		if ( 'file' === $data_type ) {
			$excluded_option = $this->_plugin->GetGlobalOption( 'scan_excluded_files', array() );
		} elseif ( 'extension' === $data_type ) {
			$excluded_option = $this->_plugin->GetGlobalOption( 'scan-excluded-extensions', array( 'jpg', 'jpeg', 'png', 'bmp', 'pdf', 'txt', 'log', 'mo', 'po', 'mp3', 'wav' ) );
		} elseif ( 'dir' === $data_type ) {
			$excluded_option = $this->_plugin->GetGlobalOption( 'scan-excluded-directories', array() );
		}

		// Check if the file name is set and not empty.
		if ( isset( $post_array['data_name'] ) && ! empty( $post_array['data_name'] ) ) {
			// Check if option already exists.
			if ( ! in_array( $post_array['data_name'], $excluded_option, true ) ) {
				// Add to excluded files array.
				if ( 'dir' === $data_type ) {
					// Prepare directories array.
					// @todo Store this in transient to cache the value. We don't need to load it every time.
					$uploads_dir = wp_upload_dir();

					// Server directories.
					$server_dirs = array(
						untrailingslashit( ABSPATH ), // Root directory.
						ABSPATH . 'wp-admin', // WordPress Admin.
						ABSPATH . WPINC, // wp-includes.
						WP_CONTENT_DIR, // wp-content.
						WP_CONTENT_DIR . '/themes', // Themes.
						WP_PLUGIN_DIR, // Plugins.
						$uploads_dir['basedir'], // Uploads.
					);

					$dir_name = $post_array['data_name'];
					if ( '/' === substr( $dir_name, -1 ) ) {
						$dir_name = untrailingslashit( $dir_name );
					}

					if ( ! in_array( $dir_name, $server_dirs, true ) ) {
						$excluded_option[] = $dir_name;
					} else {
						echo wp_json_encode( array(
							'success' => false,
							'message' => esc_html__( 'You can exclude this directory using the check boxes above.', 'wp-security-audit-log' ),
						) );
						exit();
					}
				} else {
					$excluded_option[] = $post_array['data_name'];
				}

				// Save the option.
				if ( 'file' === $data_type ) {
					$this->_plugin->SetGlobalOption( 'scan_excluded_files', $excluded_option );
				} elseif ( 'extension' === $data_type ) {
					$this->_plugin->SetGlobalOption( 'scan-excluded-extensions', $excluded_option );
				} elseif ( 'dir' === $data_type ) {
					$this->_plugin->SetGlobalOption( 'scan-excluded-directories', $excluded_option );
				}

				echo wp_json_encode( array(
					'success' => true,
					'message' => esc_html__( 'Option added to excluded types.', 'wp-security-audit-log' ),
				) );
			} else {
				if ( 'file' === $data_type ) {
					$message = esc_html__( 'This file is already excluded from the scan.', 'wp-security-audit-log' );
				} elseif ( 'extension' === $data_type ) {
					$message = esc_html__( 'This file extension is already excluded from the scan.', 'wp-security-audit-log' );
				} elseif ( 'dir' === $data_type ) {
					$message = esc_html__( 'This directory is already excluded from the scan.', 'wp-security-audit-log' );
				}
				echo wp_json_encode( array(
					'success' => false,
					'message' => $message,
				) );
			}
		} else {
			echo wp_json_encode( array(
				'success' => false,
				'message' => esc_html__( 'Option name is empty.', 'wp-security-audit-log' ),
			) );
		}
		exit();
	}

	/**
	 * Method: Remove files from file changes scan exception.
	 */
	public function scan_remove_exception_file() {
		// Die if user does not have permission to change settings.
		if ( ! $this->_plugin->settings->CurrentUserCan( 'view' ) ) {
			die( 'Access Denied.' );
		}

		// Filter $_POST array for security.
		$post_array = filter_input_array( INPUT_POST );

		// Get data type to check file or extension.
		if ( ! isset( $post_array['data_type'] ) || empty( $post_array['data_type'] ) ) {
			die( esc_html__( 'Invalid setting type.', 'wp-security-audit-log' ) );
		} else {
			$data_type = $post_array['data_type'];
		}

		// Die if nonce verification failed.
		if ( 'file' === $data_type && ! wp_verify_nonce( $post_array['nonce'], 'wsal-scan-remove-exception-file' ) ) {
			die( esc_html__( 'Nonce verification failed.', 'wp-security-audit-log' ) );
		} elseif ( 'extension' === $data_type && ! wp_verify_nonce( $post_array['nonce'], 'wsal-scan-remove-exception-file-type' ) ) {
			die( esc_html__( 'Nonce verification failed.', 'wp-security-audit-log' ) );
		} elseif ( 'dir' === $data_type && ! wp_verify_nonce( $post_array['nonce'], 'wsal-scan-remove-exception-dir' ) ) {
			die( esc_html__( 'Nonce verification failed.', 'wp-security-audit-log' ) );
		}

		// Get files to be excluded.
		if ( 'file' === $data_type ) {
			$excluded_option = $this->_plugin->GetGlobalOption( 'scan_excluded_files', array() );
		} elseif ( 'extension' === $data_type ) {
			$excluded_option = $this->_plugin->GetGlobalOption( 'scan-excluded-extensions', array( 'jpg', 'jpeg', 'png', 'bmp', 'pdf', 'txt', 'log', 'mo', 'po', 'mp3', 'wav' ) );
		} elseif ( 'dir' === $data_type ) {
			$excluded_option = $this->_plugin->GetGlobalOption( 'scan-excluded-directories', array() );
		}

		if ( ! empty( $excluded_option ) && isset( $post_array['data_removed'] ) && ! empty( $post_array['data_removed'] ) ) {
			// Get data_removed.
			$data_removed = $post_array['data_removed'];

			// Confirmed array of list to be excluded.
			$to_be_excluded = array();

			foreach ( $data_removed as $file ) {
				if ( in_array( $file, $excluded_option, true ) ) {
					$key = array_search( $file, $excluded_option, true );

					if ( false !== $key ) {
						$to_be_excluded[] = $excluded_option[ $key ];
						unset( $excluded_option[ $key ] );
					}
				}
			}

			// Get excluded scan content.
			$site_content = $this->_plugin->GetGlobalOption( 'site_content' );
			if ( empty( $site_content ) ) {
				$site_content = new stdClass();
			}
			if ( empty( $site_content->skip_files ) ) {
				$site_content->skip_files = array();
			}
			if ( empty( $site_content->skip_extensions ) ) {
				$site_content->skip_extensions = array();
			}
			if ( empty( $site_content->skip_directories ) ) {
				$site_content->skip_directories = array();
			}

			// Save the option.
			if ( 'file' === $data_type ) {
				$this->_plugin->SetGlobalOption( 'scan_excluded_files', $excluded_option );

				$site_content->skip_files = array_merge( $site_content->skip_files, $to_be_excluded );
				$this->_plugin->SetGlobalOption( 'site_content', $site_content );
			} elseif ( 'extension' === $data_type ) {
				$this->_plugin->SetGlobalOption( 'scan-excluded-extensions', $excluded_option );

				$site_content->skip_extensions = array_merge( $site_content->skip_extensions, $to_be_excluded );
				$this->_plugin->SetGlobalOption( 'site_content', $site_content );
			} elseif ( 'dir' === $data_type ) {
				$this->_plugin->SetGlobalOption( 'scan-excluded-directories', $excluded_option );

				$site_content->skip_directories = array_merge( $site_content->skip_directories, $to_be_excluded );
				$this->_plugin->SetGlobalOption( 'site_content', $site_content );
			}

			echo wp_json_encode( array(
				'success' => true,
				'message' => esc_html__( 'Option removed from excluded scan types.', 'wp-security-audit-log' ),
			) );
		} else {
			echo wp_json_encode( array(
				'success' => false,
				'message' => esc_html__( 'Something went wrong.', 'wp-security-audit-log' ),
			) );
		}
		exit();
	}

	/**
	 * Method: Run a manual file changes scan.
	 */
	public function run_manual_scan_now() {
		// Die if user does not have permission to change settings.
		if ( ! $this->_plugin->settings->CurrentUserCan( 'view' ) ) {
			die( 'Access Denied.' );
		}

		// Filter $_POST array for security.
		$post_array = filter_input_array( INPUT_POST );

		// Die if nonce verification failed.
		if ( ! wp_verify_nonce( $post_array['nonce'], 'wsal-scan-now' ) ) {
			die( esc_html__( 'Nonce verification failed.', 'wp-security-audit-log' ) );
		}

		// Return if a cron is running.
		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			echo wp_json_encode( array(
				'success' => false,
				'message' => esc_html__( 'A cron job is in progress.', 'wp-security-audit-log' ),
			) );
			exit();
		}

		// Get plugin sensors.
		$sensors = $this->_plugin->sensors->GetSensors();

		// Get file changes sensor.
		$file_changes = '';

		if ( ! empty( $sensors ) ) {
			foreach ( $sensors as $sensor ) {
				if ( $sensor instanceof WSAL_Sensors_FileChanges ) {
					$file_changes = $sensor;
				}
			}
		}

		// Check for file changes sensor.
		if ( ! empty( $file_changes ) && $file_changes instanceof WSAL_Sensors_FileChanges ) {
			// Run a manual scan on all directories.
			for ( $dir = 0; $dir < 7; $dir++ ) {
				if ( ! $this->_plugin->GetGlobalOption( 'stop-scan', false ) ) {
					$file_changes->detect_file_changes( true, $dir );
				} else {
					break;
				}
			}
			$this->_plugin->SetGlobalOption( 'stop-scan', false );

			echo wp_json_encode( array(
				'success' => true,
				'message' => esc_html__( 'Scan started successfully.', 'wp-security-audit-log' ),
			) );
		} else {
			echo wp_json_encode( array(
				'success' => false,
				'message' => esc_html__( 'Something went wrong.', 'wp-security-audit-log' ),
			) );
		}
		exit();
	}

	/**
	 * Method: Stop a file changes scan.
	 */
	public function stop_file_changes_scan() {
		// Die if user does not have permission to change settings.
		if ( ! $this->_plugin->settings->CurrentUserCan( 'view' ) ) {
			die( 'Access Denied.' );
		}

		// Filter $_POST array for security.
		$post_array = filter_input_array( INPUT_POST );

		// Die if nonce verification failed.
		if ( ! wp_verify_nonce( $post_array['nonce'], 'wsal-stop-scan' ) ) {
			echo wp_json_encode( array(
				'success' => false,
				'message' => esc_html__( 'Nonce verification failed.', 'wp-security-audit-log' ),
			) );
			exit();
		}

		// Set stop scan option to true.
		$this->_plugin->SetGlobalOption( 'stop-scan', true );

		echo wp_json_encode( array(
			'success' => true,
			'message' => esc_html__( 'Scan started successfully.', 'wp-security-audit-log' ),
		) );
		exit();
	}

	/**
	 * Method: Reset plugin settings table.
	 */
	public function reset_settings() {
		// Die if user does not have permission to change settings.
		if ( ! $this->_plugin->settings->CurrentUserCan( 'view' ) ) {
			die( esc_html__( 'Access Denied.', 'wp-security-audit-log' ) );
		}

		// Verify nonce.
		$nonce = filter_input( INPUT_POST, 'nonce', FILTER_SANITIZE_STRING );
		if ( ! empty( $nonce ) && wp_verify_nonce( $nonce, 'wsal-reset-settings' ) ) {
			global $wpdb;

			$table_name = $wpdb->prefix . 'wsal_options';
			$result     = $wpdb->query( "TRUNCATE {$table_name}" );

			if ( $result ) {
				// Log settings reset event.
				$this->_plugin->alerts->Trigger( 6006 );
				die( esc_html__( 'Tables has been reset.', 'wp-security-audit-log' ) );
			} else {
				die( esc_html__( 'Reset query failed.', 'wp-security-audit-log' ) );
			}
		} else {
			die( esc_html__( 'Nonce Verification Failed.', 'wp-security-audit-log' ) );
		}
	}

	/**
	 * Method: Purge plugin occurrence & meta tables.
	 */
	public function purge_activity() {
		// Die if user does not have permission to change settings.
		if ( ! $this->_plugin->settings->CurrentUserCan( 'view' ) ) {
			die( esc_html__( 'Access Denied.', 'wp-security-audit-log' ) );
		}

		// Verify nonce.
		$nonce = filter_input( INPUT_POST, 'nonce', FILTER_SANITIZE_STRING );
		if ( ! empty( $nonce ) && wp_verify_nonce( $nonce, 'wsal-purge-activity' ) ) {
			$connector = WpSecurityAuditLog::getConnector();
			$result    = $connector->purge_activity();

			if ( $result ) {
				// Log purge activity event.
				$this->_plugin->alerts->Trigger( 6034 );
				die( esc_html__( 'Tables has been reset.', 'wp-security-audit-log' ) );
			} else {
				die( esc_html__( 'Reset query failed.', 'wp-security-audit-log' ) );
			}
		} else {
			die( esc_html__( 'Nonce Verification Failed.', 'wp-security-audit-log' ) );
		}
	}
}
