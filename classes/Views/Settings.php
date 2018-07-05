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
		// Get users.
		$users = array();
		foreach ( get_users( 'blog_id=0&fields[]=user_login' ) as $obj ) {
			$users[] = $obj->user_login;
		}

		// Get user roles.
		$roles = array_keys( get_editable_roles() );

		// Get custom post types.
		$post_types = get_post_types( array(), 'names', 'and' );

		// Check if the token matched users.
		if ( in_array( $token, $users ) ) {
			return 'user';
		}

		// Check if the token matched user roles.
		if ( in_array( $token, $roles ) ) {
			return 'role';
		}

		// Check if the token matched post types.
		if ( in_array( $token, $post_types ) ) {
			return 'cpts';
		}

		// Check if the token matches a URL.
		if ( ( false !== strpos( $token, home_url() ) ) && filter_var( $token, FILTER_VALIDATE_URL ) ) {
			return 'urls';
		}

		return 'other';
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
			'scan_file_changes'  => $this->_plugin->GetGlobalOption( 'scan-file-changes', 'enable' ),
			'scan_frequency'     => $this->_plugin->GetGlobalOption( 'scan-frequency', 'weekly' ),
			'scan_hour'          => $this->_plugin->GetGlobalOption( 'scan-hour', '04' ),
			'scan_day'           => $this->_plugin->GetGlobalOption( 'scan-day', '1' ),
			'scan_date'          => $this->_plugin->GetGlobalOption( 'scan-date', '10' ),
			'scan_directories'   => $this->_plugin->GetGlobalOption( 'scan-directories', $default_scan_dirs ),
			'scan_excluded_dirs' => $this->_plugin->GetGlobalOption( 'scan-excluded-directories', array() ),
			'scan_excluded_extensions' => $this->_plugin->GetGlobalOption( 'scan-excluded-extensions', array( 'jpg', 'jpeg', 'png', 'bmp', 'pdf', 'txt', 'log', 'mo', 'po', 'mp3', 'wav' ) ),
			'scan_in_progress'   => $this->_plugin->GetGlobalOption( 'scan-in-progress', false ),
		);
	}

	/**
	 * Method: Save settings.
	 */
	protected function Save() {
		check_admin_referer( 'wsal-settings' );

		// Filter $_POST array for security.
		$post_array = filter_input_array( INPUT_POST );

		// Get pruning date.
		$pruning_date = isset( $post_array['PruningDate'] ) ? (int) $post_array['PruningDate'] : '';
		$pruning_date = ( ! empty( $pruning_date ) ) ? $pruning_date . ' months' : '';

		$this->_plugin->settings->SetPruningDateEnabled( isset( $post_array['PruneBy'] ) ? 'date' === $post_array['PruneBy'] : '' );
		$this->_plugin->settings->SetPruningDate( $pruning_date );
		$this->_plugin->settings->SetPruningLimitEnabled( isset( $post_array['PruneBy'] ) ? 'limit' === $post_array['PruneBy'] : '' );
		$this->_plugin->settings->SetPruningLimit( isset( $post_array['PruningLimit'] ) ? $post_array['PruningLimit'] : '' );

		$this->_plugin->settings->SetFromEmail( $post_array['FromEmail'] );
		$this->_plugin->settings->SetDisplayName( $post_array['DisplayName'] );

		$this->_plugin->settings->SetWidgetsEnabled( $post_array['EnableDashboardWidgets'] );
		$this->_plugin->settings->SetAllowedPluginViewers( isset( $post_array['Viewers'] ) ? $post_array['Viewers'] : array() );
		$this->_plugin->settings->SetAllowedPluginEditors( isset( $post_array['Editors'] ) ? $post_array['Editors'] : array() );

		$this->_plugin->settings->SetExcludedMonitoringUsers( isset( $post_array['ExUsers'] ) ? $post_array['ExUsers'] : array() );
		$this->_plugin->settings->SetExcludedMonitoringRoles( isset( $post_array['ExRoles'] ) ? $post_array['ExRoles'] : array() );
		$this->_plugin->settings->SetExcludedMonitoringCustom( isset( $post_array['Customs'] ) ? $post_array['Customs'] : array() );
		$this->_plugin->settings->SetExcludedMonitoringIP( isset( $post_array['IpAddrs'] ) ? $post_array['IpAddrs'] : array() );
		$this->_plugin->settings->set_excluded_post_types( isset( $post_array['ExCPTss'] ) ? $post_array['ExCPTss'] : array() );
		$this->_plugin->settings->set_excluded_urls( isset( $post_array['ExURLss'] ) ? $post_array['ExURLss'] : array() );

		$this->_plugin->settings->SetRestrictAdmins( isset( $post_array['RestrictAdmins'] ) );
		$this->_plugin->settings->set_login_page_notification( isset( $post_array['login_page_notification'] ) ? 'true' : 'false' );
		$this->_plugin->settings->set_login_page_notification_text( isset( $post_array['login_page_notification_text'] ) ? $post_array['login_page_notification_text'] : false );
		$this->_plugin->settings->SetRefreshAlertsEnabled( $post_array['EnableAuditViewRefresh'] );
		$this->_plugin->settings->SetMainIPFromProxy( isset( $post_array['EnableProxyIpCapture'] ) );
		$this->_plugin->settings->SetInternalIPsFiltering( isset( $post_array['EnableIpFiltering'] ) );
		$this->_plugin->settings->SetIncognito( isset( $post_array['Incognito'] ) );
		$this->_plugin->settings->SetDeleteData( isset( $post_array['DeleteData'] ) );
		$this->_plugin->settings->SetTimezone( $post_array['Timezone'] );
		$this->_plugin->settings->set_type_username( $post_array['type_username'] );
		$this->_plugin->settings->SetWPBackend( isset( $post_array['WPBackend'] ) );
		if ( ! empty( $post_array['Columns'] ) ) {
			$this->_plugin->settings->SetColumns( $post_array['Columns'] );
		}
		$this->_plugin->settings->ClearDevOptions();

		if ( isset( $post_array['DevOptions'] ) ) {
			foreach ( $post_array['DevOptions'] as $opt ) {
				$this->_plugin->settings->SetDevOptionEnabled( $opt, true );
			}
		}

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
		if ( isset( $post_array['wsal-scan-directories'] )
		&& is_array( $post_array['wsal-scan-directories'] ) ) {
			$scan_directories = array_keys( $post_array['wsal-scan-directories'] );
			$this->_plugin->SetGlobalOption( 'scan-directories', $scan_directories );
		}
	}

	/**
	 * Method: Check security token.
	 */
	public function AjaxCheckSecurityToken() {
		if ( ! $this->_plugin->settings->CurrentUserCan( 'view' ) ) {
			die( 'Access Denied.' );
		}

		// Filter $_POST array for security.
		$post_array = filter_input_array( INPUT_POST );

		if ( ! isset( $post_array['token'] ) ) {
			die( 'Token parameter expected.' );
		}
		die( esc_html( $this->GetTokenType( $post_array['token'] ) ) );
	}

	/**
	 * Method: Run cleanup.
	 */
	public function AjaxRunCleanup() {
		if ( ! $this->_plugin->settings->CurrentUserCan( 'view' ) ) {
			die( 'Access Denied.' );
		}
		$this->_plugin->CleanUp();
		wp_safe_redirect( $this->GetUrl() );
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

		// Load file changes settings.
		$this->load_file_changes_settings();
		?>
		<h2 id="wsal-tabs" class="nav-tab-wrapper">
			<a href="#tab-general" class="nav-tab"><?php esc_html_e( 'General', 'wp-security-audit-log' ); ?></a>
			<a href="#tab-audit-log" class="nav-tab"><?php esc_html_e( 'Audit Log', 'wp-security-audit-log' ); ?></a>
			<a href="#tab-file-changes" class="nav-tab"><?php esc_html_e( 'File Integrity Checks & Warning', 'wp-security-audit-log' ); ?></a>
			<a href="#tab-exclude" class="nav-tab"><?php esc_html_e( 'Exclude Objects', 'wp-security-audit-log' ); ?></a>
		</h2>

		<form id="audit-log-settings" method="post">
			<input type="hidden" name="page" value="<?php echo filter_input( INPUT_GET, 'page', FILTER_SANITIZE_STRING ); ?>" />
			<input type="hidden" id="ajaxurl" value="<?php echo esc_attr( admin_url( 'admin-ajax.php' ) ); ?>" />
			<?php wp_nonce_field( 'wsal-settings' ); ?>

			<div id="audit-log-adverts">
			</div>
			<div class="nav-tabs">
				<!-- General Tab -->
				<table class="form-table wsal-tab widefat" id="tab-general">
					<tbody>
						<!-- From Email & Name -->
						<tr>
							<th><label for="FromEmail"><?php esc_html_e( 'From Email & Name', 'wp-security-audit-log' ); ?></label></th>
							<td>
								<fieldset>
									<label for="FromEmail"><?php esc_html_e( 'Email Address', 'wp-security-audit-log' ); ?></label>
									<input type="email" id="FromEmail" name="FromEmail" value="<?php echo esc_attr( $this->_plugin->settings->GetFromEmail() ); ?>" />
									&nbsp;
									<label for="DisplayName"><?php esc_html_e( 'Display Name', 'wp-security-audit-log' ); ?></label>
									<input type="text" id="DisplayName" name="DisplayName" value="<?php echo esc_attr( $this->_plugin->settings->GetDisplayName() ); ?>" />
								</fieldset>
								<p class="description">
									<?php
									echo sprintf(
										esc_html__( 'These email address and display name will be used as From details in the emails sent by the %s . Please ensure the mail server can relay emails with the domain of the specified email address.', 'wp-security-audit-log' ),
										'<a target="_blank" href="https://www.wpsecurityauditlog.com/plugin-extensions/">' . esc_html__( '(premium add-ons)', 'wp-security-audit-log' ) . '</a>'
									);
									?>
								</p>
							</td>
						</tr>
						<!-- Events Dashboard Widget -->
						<tr>
							<th><label for="dwoption_on"><?php esc_html_e( 'Events Dashboard Widget', 'wp-security-audit-log' ); ?></label></th>
							<td>
								<fieldset>
									<?php $dwe = $this->_plugin->settings->IsWidgetsEnabled(); ?>
									<label for="dwoption_on">
										<input type="radio" name="EnableDashboardWidgets" id="dwoption_on" style="margin-top: 2px;" <?php checked( $dwe ); ?> value="1">
										<span><?php esc_html_e( 'On', 'wp-security-audit-log' ); ?></span>
									</label>
									<br/>
									<label for="dwoption_off">
										<input type="radio" name="EnableDashboardWidgets" id="dwoption_off" style="margin-top: 2px;" <?php checked( $dwe, false ); ?>  value="0">
										<span><?php esc_html_e( 'Off', 'wp-security-audit-log' ); ?></span>
									</label>
									<br/>
									<p class="description">
										<?php
										echo sprintf(
											esc_html__( 'Display a dashboard widget with the latest %d security events.', 'wp-security-audit-log' ),
											esc_html( $this->_plugin->settings->GetDashboardWidgetMaxAlerts() )
										);
										?>
									</p>
								</fieldset>
							</td>
						</tr>
						<!-- Reverse Proxy / Firewall Options -->
						<tr>
							<th><label for="pioption_on"><?php esc_html_e( 'Reverse Proxy / Firewall Options', 'wp-security-audit-log' ); ?></label></th>
							<td>
								<fieldset>
									<label for="EnableProxyIpCapture">
										<input type="checkbox" name="EnableProxyIpCapture" value="1" id="EnableProxyIpCapture" <?php checked( $this->_plugin->settings->IsMainIPFromProxy() ); ?> />
										<?php esc_html_e( 'WordPress running behind firewall or proxy', 'wp-security-audit-log' ); ?>
									</label>
									<br/>
									<span class="description"><?php esc_html_e( 'Enable this option if your WordPress is running behind a firewall or reverse proxy. When this option is enabled the plugin will retrieve the user\'s IP address from the proxy header.', 'wp-security-audit-log' ); ?></span>
									<br/>
									<label for="EnableIpFiltering">
										<input type="checkbox" name="EnableIpFiltering" value="1" id="EnableIpFiltering" <?php checked( $this->_plugin->settings->IsInternalIPsFiltered() ); ?> />
										<?php esc_html_e( 'Filter Internal IP Addresses', 'wp-security-audit-log' ); ?>
									</label>
									<br/>
									<span class="description"><?php esc_html_e( 'Enable this option to filter internal IP addresses from the proxy headers.', 'wp-security-audit-log' ); ?></span>
								</fieldset>
							</td>
						</tr>
						<!-- Can Manage Plugin -->
						<tr>
							<th><label for="EditorQueryBox"><?php esc_html_e( 'Can Manage Plugin', 'wp-security-audit-log' ); ?></label></th>
							<td>
								<fieldset>
									<input type="text" id="EditorQueryBox" style="float: left; display: block; width: 250px;">
									<input type="button" id="EditorQueryAdd" style="float: left; display: block;" class="button-primary" value="Add">
									<br style="clear: both;"/>
									<p class="description">
										<?php esc_html_e( 'Users and Roles in this list can manage the plugin settings', 'wp-security-audit-log' ); ?>
									</p>
									<div id="EditorList">
										<?php foreach ( $this->_plugin->settings->GetAllowedPluginEditors() as $item ) : ?>
											<span class="sectoken-<?php echo esc_attr( $this->GetTokenType( $item ) ); ?>">
												<input type="hidden" name="Editors[]" value="<?php echo esc_attr( $item ); ?>"/>
												<?php echo esc_html( $item ); ?>
												<?php if ( wp_get_current_user()->user_login !== $item ) { ?>
													<a href="javascript:;" title="Remove">&times;</a>
												<?php } ?>
											</span>
										<?php endforeach; ?>
									</div>
								</fieldset>
							</td>
						</tr>
						<!-- Restrict Plugin Access -->
						<tr>
							<th><label for="RestrictAdmins"><?php esc_html_e( 'Restrict Plugin Access', 'wp-security-audit-log' ); ?></label></th>
							<td>
								<fieldset>
									<input type="hidden" id="RestrictAdminsDefaultUser" value="<?php echo esc_attr( wp_get_current_user()->user_login ); ?>"/>
									<label for="RestrictAdmins">
										<?php $ira = $this->_plugin->settings->IsRestrictAdmins(); ?>
										<input type="checkbox" name="RestrictAdmins" id="RestrictAdmins" <?php checked( $ira ); ?> />
									</label>
									<br/>
									<span class="description">
										<?php esc_html_e( 'If this option is disabled all the administrators on this WordPress have access to manage this plugin.', 'wp-security-audit-log' ); ?><br/>
										<?php echo wp_kses( __( 'By enabling this option only <strong>You</strong> and the users specified in the <strong>Can Manage Plugin</strong> and <strong>Can View Events</strong> can configure this plugin or view the events in the WordPress audit trail.', 'wp-security-audit-log' ), $this->_plugin->allowed_html_tags ); ?>
									</span>
								</fieldset>
							</td>
						</tr>
						<!-- Login Page Notification -->
						<tr>
							<th><label for="login_page_notification"><?php esc_html_e( 'Login Page Notification', 'wp-security-audit-log' ); ?></label></th>
							<td>
								<fieldset>
									<label for="login_page_notification">
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
										<input type="checkbox" name="login_page_notification" id="login_page_notification" <?php checked( $wsal_lpn ); ?> />
									</label>
									<br />
									<?php
									// Get login page notification text.
									$wsal_lpn_text = $this->_plugin->settings->get_login_page_notification_text();
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
									<span class="description">
										<?php esc_html_e( 'Many compliance regulations (such as the GDRP) require website administrators to tell the users of this website that a log is kept of all the changes they do when logged in.', 'wp-security-audit-log' ); ?>
										<br />
										<?php echo wp_kses( __( '<strong>Note: </strong>', 'wp-security-audit-log' ), $this->_plugin->allowed_html_tags ) . esc_html__( 'The only HTML code allowed in the login page notification is for links ( < a href >...< /a > ).', 'wp-security-audit-log' ); ?>
									</span>
								</fieldset>
							</td>
						</tr>
						<!-- Developer Options -->
						<tr>
							<th><label><?php esc_html_e( 'Developer Options', 'wp-security-audit-log' ); ?></label></th>
							<td>
								<fieldset>
									<?php $any = $this->_plugin->settings->IsAnyDevOptionEnabled(); ?>
									<a href="javascript:;" style="<?php echo ( $any ) ? 'display: none;' : false; ?>"
										onclick="jQuery(this).hide().next().show();">
										<?php esc_html_e( 'Show Developer Options', 'wp-security-audit-log' ); ?>
									</a>
									<div style="<?php echo ( ! $any ) ? 'display: none;' : false; ?>">
										<p style="border-left: 3px solid #FFD000; padding: 2px 8px; margin-left: 6px; margin-bottom: 16px;">
											<?php esc_html_e( 'Only enable these options on testing, staging and development websites. Enabling any of the settings below on LIVE websites may cause unintended side-effects including degraded performance.', 'wp-security-audit-log' ); ?>
										</p>
										<?php
										foreach ( array(
											/**
											WSAL_Settings::OPT_DEV_DATA_INSPECTOR => array(
												__( 'Data Inspector', 'wp-security-audit-log' ),
												__( 'View data logged for each triggered alert.', 'wp-security-audit-log' ),
											),
											 WSAL_Settings::OPT_DEV_PHP_ERRORS     => array(
												__('PHP Errors', 'wp-security-audit-log'),
												__('Enables sensor for alerts generated from PHP.', 'wp-security-audit-log')
											), */
											WSAL_Settings::OPT_DEV_REQUEST_LOG    => array(
												__( 'Request Log', 'wp-security-audit-log' ),
												__( 'Enables logging request to file.', 'wp-security-audit-log' ),
											),
											/**
											 WSAL_Settings::OPT_DEV_BACKTRACE_LOG  => array(
												__('Backtrace', 'wp-security-audit-log'),
												__('Log full backtrace for PHP-generated alerts.', 'wp-security-audit-log')
											), */
										) as $opt => $info ) {
											?>
											<label for="devoption_<?php echo esc_attr( $opt ); ?>">
												<input type="checkbox" name="DevOptions[]" id="devoption_<?php echo esc_attr( $opt ); ?>"
													<?php checked( $this->_plugin->settings->IsDevOptionEnabled( $opt ) ); ?>
													value="<?php echo esc_attr( $opt ); ?>">
												<span><?php echo esc_html( $info[0] ); ?></span>
												<!-- Title -->
												<?php if ( isset( $info[1] ) && $info[1] ) : ?>
													<span class="description"> &mdash; <?php echo esc_html( $info[1] ); ?></span>
												<?php endif; ?>
												<!-- Description -->
											</label><br/>
											<?php
										}
										?>
										<span class="description">
											<?php esc_html_e( 'The request log file is saved in the /wp-content/uploads/wp-security-audit-log/ directory.', 'wp-security-audit-log' ); ?>
										</span>
									</div>
								</fieldset>
							</td>
						</tr>
						<!-- Hide Plugin in Plugins Page -->
						<tr>
							<th><label for="Incognito"><?php esc_html_e( 'Hide Plugin in Plugins Page', 'wp-security-audit-log' ); ?></label></th>
							<td>
								<fieldset>
									<label for="Incognito">
										<input type="checkbox" name="Incognito" value="1" id="Incognito" <?php checked( $this->_plugin->settings->IsIncognito() ); ?> />
										<?php esc_html_e( 'Hide', 'wp-security-audit-log' ); ?>
									</label>
									<br/>
									<span class="description">
										<?php esc_html_e( 'To manually revert this setting set the value of option wsal-hide-plugin to 0 in the wp_wsal_options table.', 'wp-security-audit-log' ); ?>
									</span>
								</fieldset>
							</td>
						</tr>
						<!-- Remove Data on Uninstall -->
						<tr>
							<th><label for="DeleteData"><?php esc_html_e( 'Remove Data on Uninstall', 'wp-security-audit-log' ); ?></label></th>
							<td>
								<fieldset>
									<label for="DeleteData">
										<input type="checkbox" name="DeleteData" value="1" id="DeleteData" onclick="return delete_confirm(this);"
											<?php checked( $this->_plugin->settings->IsDeleteData() ); ?> />
										<span class="description">
											<?php esc_html_e( 'Check this box if you would like remove all data when the plugin is deleted.', 'wp-security-audit-log' ); ?>
										</span>
									</label>
								</fieldset>
							</td>
						</tr>
					</tbody>
				</table>
				<!-- Audit Log Tab -->
				<table class="form-table wsal-tab widefat" id="tab-audit-log">
					<tbody>
						<!-- Audit Log Retention -->
						<?php
						if ( $this->_plugin->settings->IsArchivingEnabled() ) :
							?>
							<tr>
								<td colspan="2">
									<?php
									$archiving_page = admin_url( 'admin.php?page=wsal-ext-settings#archiving' );
									echo sprintf( esc_html__( 'Retention settings moved to %1$s archiving settings %2$s because archiving is enabled', 'wp-security-audit-log' ), '<a href="' . esc_url( $archiving_page ) . '" target="_blank">', '</a>' );
									?>
								</td>
							</tr>
						<?php else : ?>
							<tr>
								<th><label for="delete1"><?php esc_html_e( 'Audit Log Retention', 'wp-security-audit-log' ); ?></label></th>
								<td>
									<fieldset>
										<?php $text = __( '(eg: 1 month)', 'wp-security-audit-log' ); ?>
										<?php $nbld = ! ($this->_plugin->settings->IsPruningDateEnabled() || $this->_plugin->settings->IsPruningLimitEnabled()); ?>
										<label for="delete0">
											<input type="radio" id="delete0" name="PruneBy" value="" <?php checked( $nbld ); ?> />
											<?php echo esc_html__( 'None', 'wp-security-audit-log' ); ?>
										</label>
									</fieldset>
									<fieldset>
										<?php $text = __( '(Leave empty or enter 0 to disable automatic pruning.)', 'wp-security-audit-log' ); ?>
										<?php $nbld = $this->_plugin->settings->IsPruningDateEnabled(); ?>
										<label for="delete1">
											<input type="radio" id="delete1" name="PruneBy" value="date" <?php checked( $nbld ); ?> />
											<?php echo esc_html__( 'Delete events older than', 'wp-security-audit-log' ); ?>
										</label>
										<?php
										// Find and replace ` months` in the string.
										$pruning_date = str_replace( ' months', '', $this->_plugin->settings->GetPruningDate() );
										?>
										<input type="text" id="PruningDate" name="PruningDate" placeholder="<?php echo esc_attr( $text ); ?>"
											value="<?php echo esc_attr( $pruning_date ); ?>"
											onfocus="jQuery('#delete1').attr('checked', true);" />
										<?php esc_html_e( 'months', 'wp-security-audit-log' ); ?>
										<span class="description"><?php echo esc_html( $text ); ?></span>
									</fieldset>
									<fieldset>
										<?php $text = __( '(eg: 80)', 'wp-security-audit-log' ); ?>
										<?php $nbld = $this->_plugin->settings->IsPruningLimitEnabled(); ?>
										<label for="delete2">
											<input type="radio" id="delete2" name="PruneBy" value="limit" <?php checked( $nbld ); ?> />
											<?php echo esc_html__( 'Keep up to', 'wp-security-audit-log' ); ?>
										</label>
										<input type="text" id="PruningLimit" name="PruningLimit" placeholder="<?php echo esc_attr( $text ); ?>"
											value="<?php echo esc_attr( $this->_plugin->settings->GetPruningLimit() ); ?>"
											onfocus="jQuery('#delete2').attr('checked', true);" />
										<?php echo esc_html__( 'events', 'wp-security-audit-log' ); ?>
										<span><?php echo esc_html( $text ); ?></span>
									</fieldset>
									<p class="description">
										<?php
										$next = wp_next_scheduled( 'wsal_cleanup' );
										echo esc_html__( 'Next Scheduled Cleanup is in ', 'wp-security-audit-log' );
										echo esc_html( human_time_diff( current_time( 'timestamp' ), $next ) );
										echo '<!-- ' . esc_html( date( 'dMy H:i:s', $next ) ) . ' --> ';
										echo sprintf(
											esc_html__( '(or %s)', 'wp-security-audit-log' ),
											'<a href="' . esc_url( add_query_arg( 'action', 'AjaxRunCleanup', admin_url( 'admin-ajax.php' ) ) ) . '">' . esc_html__( 'Run Manually', 'wp-security-audit-log' ) . '</a>'
										);
										?>
									</p>
								</td>
							</tr>
						<?php endif; ?>
						<!-- Can View Alerts -->
						<tr>
							<th><label for="ViewerQueryBox"><?php esc_html_e( 'Can View Events', 'wp-security-audit-log' ); ?></label></th>
							<td>
								<fieldset>
									<input type="text" id="ViewerQueryBox" style="float: left; display: block; width: 250px;">
									<input type="button" id="ViewerQueryAdd" style="float: left; display: block;" class="button-primary" value="Add">
									<br style="clear: both;"/>
									<p class="description">
										<?php esc_html_e( 'Users and Roles in this list can view the security events', 'wp-security-audit-log' ); ?>
									</p>
									<div id="ViewerList">
										<?php foreach ( $this->_plugin->settings->GetAllowedPluginViewers() as $item ) : ?>
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
						<!-- Refresh Audit Log Viewer -->
						<tr>
							<th><label for="aroption_on"><?php esc_html_e( 'Refresh Audit Log Viewer', 'wp-security-audit-log' ); ?></label></th>
							<td>
								<fieldset>
									<?php $are = $this->_plugin->settings->IsRefreshAlertsEnabled(); ?>
									<label for="aroption_on">
										<input type="radio" name="EnableAuditViewRefresh" id="aroption_on" style="margin-top: 2px;"
											<?php checked( $are ); ?> value="1">
										<span><?php esc_html_e( 'Automatic', 'wp-security-audit-log' ); ?></span>
									</label>
									<span class="description"> — <?php esc_html_e( 'Refresh Audit Log Viewer as soon as there are new events.', 'wp-security-audit-log' ); ?></span>
									<br/>
									<label for="aroption_off">
										<input type="radio" name="EnableAuditViewRefresh" id="aroption_off" style="margin-top: 2px;"
											<?php checked( $are, false ); ?> value="0">
										<span><?php esc_html_e( 'Manual', 'wp-security-audit-log' ); ?></span>
									</label>
									<span class="description"> — <?php esc_html_e( 'Refresh Audit Log Viewer only when the page is reloaded.', 'wp-security-audit-log' ); ?></span>
									<br/>
								</fieldset>
							</td>
						</tr>
						<!-- Alerts Timestamp -->
						<tr>
							<th><label for="timezone-default"><?php esc_html_e( 'Events Timestamp', 'wp-security-audit-log' ); ?></label></th>
							<td>
								<fieldset>
									<?php $timezone = $this->_plugin->settings->GetTimezone(); ?>
									<label for="timezone-default">
										<input type="radio" name="Timezone" id="timezone-default" style="margin-top: 2px;"
											<?php checked( $timezone, 0 ); ?> value="0">
										<span><?php esc_html_e( 'UTC', 'wp-security-audit-log' ); ?></span>
									</label>
									<br/>
									<label for="timezone">
										<input type="radio" name="Timezone" id="timezone" style="margin-top: 2px;"
											<?php checked( $timezone, 1 ); ?> value="1">
										<span><?php esc_html_e( 'WordPress\' timezone', 'wp-security-audit-log' ); ?></span>
									</label>
									<br/>
									<span class="description"><?php esc_html_e( 'Select which timestamp the events should have in the Audit Log viewer. Note that the WordPress\' timezone might be different from that of the server.', 'wp-security-audit-log' ); ?></span>
								</fieldset>
							</td>
						</tr>
						<!-- Select type of name -->
						<tr>
							<th><label for="timezone-default"><?php esc_html_e( 'User Information in Audit Log', 'wp-security-audit-log' ); ?></label></th>
							<td>
								<fieldset>
									<?php $type_username = $this->_plugin->settings->get_type_username(); ?>
									<label for="column_username">
										<input type="radio" name="type_username" id="column_username" style="margin-top: 2px;" <?php checked( $type_username, 'username' ); ?> value="username">
										<span><?php esc_html_e( 'Username', 'wp-security-audit-log' ); ?></span>
									</label>
									<br/>
									<label for="columns_display_name">
										<input type="radio" name="type_username" id="columns_display_name" style="margin-top: 2px;" <?php checked( $type_username, 'display_name' ); ?> value="display_name">
										<span><?php esc_html_e( 'First Name & Last Name', 'wp-security-audit-log' ); ?></span>
									</label>
									<br/>
									<span class="description"><?php esc_html_e( 'Select the type of user information that should be displayed in the audit log.', 'wp-security-audit-log' ); ?></span>
								</fieldset>
							</td>
						</tr>
						<!-- Audit Log Columns Selection -->
						<tr>
							<th><label for="columns"><?php esc_html_e( 'Audit Log Columns Selection', 'wp-security-audit-log' ); ?></label></th>
							<td>
								<fieldset>
									<?php $columns = $this->_plugin->settings->GetColumns(); ?>
									<?php foreach ( $columns as $key => $value ) { ?>
										<label for="columns">
											<input type="checkbox" name="Columns[<?php echo esc_attr( $key ); ?>]" id="<?php echo esc_attr( $key ); ?>" class="sel-columns" style="margin-top: 2px;"
												<?php checked( $value, '1' ); ?> value="1">
											<?php if ( 'alert_code' !== $key ) : ?>
												<span><?php echo esc_html( ucwords( str_replace( '_', ' ', $key ) ) ); ?></span>
											<?php else : ?>
												<span><?php esc_html_e( 'Event ID', 'wp-security-audit-log' ); ?></span>
											<?php endif; ?>
										</label>
										<br/>
									<?php } ?>
									<span class="description"><?php esc_html_e( 'When you disable any of the above such details won’t be shown in the Audit Log viewer though the plugin will still record such information in the database.', 'wp-security-audit-log' ); ?></span>
								</fieldset>
							</td>
						</tr>
						<!-- Disable Alerts for WordPress Background activity -->
						<tr>
							<th><label for="DeleteData"><?php esc_html_e( 'Disable Events for WordPress Background Activity', 'wp-security-audit-log' ); ?></label></th>
							<td>
								<fieldset>
									<label for="WPBackend">
										<input type="checkbox" name="WPBackend" value="1" id="WPBackend"
											<?php checked( $this->_plugin->settings->IsWPBackend() ); ?> />
										<?php esc_html_e( 'Hide activity', 'wp-security-audit-log' ); ?>
									</label>
									<br/>
									<span class="description">
										<?php esc_html_e( 'For example do not raise an event when WordPress deletes the auto drafts.', 'wp-security-audit-log' ); ?>
									</span>
								</fieldset>
							</td>
						</tr>
					</tbody>
				</table>
				<!-- File Changes Logging Tab -->
				<table class="form-table wsal-tab widefat" id="tab-file-changes">
					<tbody>
						<tr>
							<th>
								<label for="wsal-file-changes"><?php esc_html_e( 'Keep a Log of File Changes', 'wp-security-audit-log' ); ?></label>
							</th>
							<td>
								<fieldset>
									<label>
										<input id="enable"
											name="wsal-file-changes"
											type="radio"
											value="enable"
											<?php checked( $this->scan_settings['scan_file_changes'], 'enable' ); ?>
										/>
										<?php esc_html_e( 'Enable', 'wp-security-audit-log' ); ?>
									</label>
									<br />
									<label>
										<input id="disable"
											name="wsal-file-changes"
											type="radio"
											value="disable"
											<?php checked( $this->scan_settings['scan_file_changes'], 'disable' ); ?>
										/>
										<?php esc_html_e( 'Disable', 'wp-security-audit-log' ); ?>
									</label>
								</fieldset>
								<span class="description">
									<?php esc_html_e( 'Enable or disable the logging of file changes in the Activity Log.', 'wp-security-audit-log' ); ?>
								</span>
							</td>
						</tr>
						<!-- wsal-file-changes -->
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
									<a href="<?php echo esc_url( $wsal_events_page . '#tab-file-changes' ); ?>">
										<?php esc_html_e( 'these events happen', 'wp-security-audit-log' ); ?>
									</a>
								</p>
								<p class="description">
									<?php esc_html_e( 'Click on the link above to select the type of file changes events you want to keep a log of in the activity log. Save your settings prior to navigating away from this page.', 'wp-security-audit-log' ); ?>
								</p>
							</td>
						</tr>
						<!-- wsal-file-alert-types -->
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
								<span class="description">
									<?php esc_html_e( 'Select the frequency of the file changes scans.', 'wp-security-audit-log' ); ?>
								</span>
							</td>
						</tr>
						<!-- wsal-scan-frequency -->
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
						<tr>
							<th>
								<label for="wsal-scan-directories"><?php esc_html_e( 'Directories to scan', 'wp-security-audit-log' ); ?></label>
							</th>
							<td>
								<?php
								// WP Directories.
								$wp_directories = array(
									'root'        => __( 'Root directory of WordPress (excluding sub directories)', 'wp-security-audit-log' ),
									'wp-admin'    => __( 'WP Admin directory (/wp-admin/)', 'wp-security-audit-log' ),
									'wp-includes' => __( 'WP Includes directory (/wp-includes/)', 'wp-security-audit-log' ),
									'wp-content'  => __( '/wp-content/ directory (excluding plugins, themes & uploads directories)', 'wp-security-audit-log' ),
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
								<span class="description">
									<?php esc_html_e( 'Select which directories should be scanned for file changes.', 'wp-security-audit-log' ); ?>
								</span>
							</td>
						</tr>
						<!-- wsal-scan-directories -->
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
								<span class="description">
									<?php echo sprintf( esc_html__( 'Specify the name of the directory and the path to it in relation to the website\'s root. For example, if you want to want to exclude all files in the sub directory dir1/dir2 specify the following: %s.', 'wp-security-audit-log' ), esc_html( trailingslashit( ABSPATH ) ) . 'dir1/dir2/' ); ?>
									<?php esc_html_e( 'Note that when you exclude a sub directory, all the files in that sub directory and all sub directories underneath it will be excluded from the scan.', 'wp-security-audit-log' ); ?>
								</span>
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
								<span class="description">
									<?php esc_html_e( 'Specify the name and extension of the file(s) you want to exclude from the file changes scans. Specify the exact file name and extensions. Wildcard not supported.', 'wp-security-audit-log' ); ?>
								</span>
								<span class="error hide" id="wsal_file_name_error"></span>
							</td>
						</tr>
						<!-- wsal_add_file_name -->
						<tr>
							<th>
								<label for="wsal_add_file_type_name"><?php esc_html_e( 'Exclude File Types', 'wp-security-audit-log' ); ?></label>
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
								<span class="description">
									<?php esc_html_e( 'Specify the extension of the file types you want to exclude from the file changes scans. Ideally you should exclude any type of logs and backup files that tend to be very big.', 'wp-security-audit-log' ); ?>
								</span>
								<span class="error hide" id="wsal_file_type_error"></span>
							</td>
						</tr>
						<!-- wsal-scan-exclude-extensions -->
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
								<br />
								<span class="description">
									<?php esc_html_e( 'Click this button to launch an instant scan for file changes with the configured settings. If you navigate away from this page the scan will keep on running. Note that the instant scan can be more resource intensive than scheduled scans.', 'wp-security-audit-log' ); ?>
								</span>
							</td>
						</tr>
						<!-- wsal-scan-now -->
					</tbody>
				</table>
				<!-- Exclude Objects Tab -->
				<table class="form-table wsal-tab widefat" id="tab-exclude">
					<tbody>
						<tr>
							<th><h2 class="wsal-tab__heading"><?php esc_html_e( 'Users & Roles', 'wp-security-audit-log' ); ?></h2></th>
						</tr>
						<tr>
							<td colspan="2"><?php esc_html_e( 'Any of the users and roles listed in the below options will be excluded from monitoring. This means that any change they do will not be logged.', 'wp-security-audit-log' ); ?></td>
						</tr>
						<!-- Excluded Users -->
						<tr>
							<th><label for="ExUserQueryBox"><?php esc_html_e( 'Excluded Users', 'wp-security-audit-log' ); ?></label></th>
							<td>
								<fieldset>
									<input type="text" id="ExUserQueryBox" style="float: left; display: block; width: 250px;">
									<input type="button" id="ExUserQueryAdd" style="float: left; display: block;" class="button-primary" value="Add">
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
						<!-- Excluded Roles -->
						<tr>
							<th><label for="ExRoleQueryBox"><?php esc_html_e( 'Excluded Roles', 'wp-security-audit-log' ); ?></label></th>
							<td>
								<fieldset>
									<input type="text" id="ExRoleQueryBox" style="float: left; display: block; width: 250px;">
									<input type="button" id="ExRoleQueryAdd" style="float: left; display: block;" class="button-primary" value="Add">
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
						<tr>
							<td><h2 class="wsal-tab__heading"><?php esc_html_e( 'Custom Fields', 'wp-security-audit-log' ); ?></h2></td>
						</tr>
						<tr>
							<td colspan="2">
								<?php esc_html_e( 'All of the custom fields listed below will be excluded from monitoring. This means that if they are changed or updated the plugin will not log such activity.', 'wp-security-audit-log' ); ?><br>
								<?php esc_html_e( 'You can use the * wildcard to exclude more than one Custom Field. For example, to exclude all the Custom Fields that start with wp123 specify wp123*.', 'wp-security-audit-log' ); ?>
							</td>
						</tr>
						<!-- Excluded Custom Fields -->
						<tr>
							<th><label for="CustomQueryBox"><?php esc_html_e( 'Excluded Custom Fields', 'wp-security-audit-log' ); ?></label></th>
							<td>
								<fieldset>
									<input type="text" id="CustomQueryBox" style="float: left; display: block; width: 250px;">
									<input type="button" id="CustomQueryAdd" style="float: left; display: block;" class="button-primary" value="Add">
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
								</fieldset>
							</td>
						</tr>
						<tr>
							<th><h2 class="wsal-tab__heading"><?php esc_html_e( 'IP Addresses', 'wp-security-audit-log' ); ?></h2></th>
						</tr>
						<tr>
							<td colspan="2"><?php esc_html_e( 'Any of the IP addresses listed below will be excluded from monitoring. This means that all activity from such IP address will not be recorded.', 'wp-security-audit-log' ); ?></td>
						</tr>
						<!-- Excluded IP Addresses -->
						<tr>
							<th><label for="IpAddrQueryBox"><?php esc_html_e( 'Excluded IP Addresses', 'wp-security-audit-log' ); ?></label></th>
							<td>
								<fieldset>
									<input type="text" id="IpAddrQueryBox" style="float: left; display: block; width: 250px;">
									<input type="button" id="IpAddrQueryAdd" style="float: left; display: block;" class="button-primary" value="Add">
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
						<tr>
							<th><h2 class="wsal-tab__heading"><?php esc_html_e( 'Post Types', 'wp-security-audit-log' ); ?></h2></th>
						</tr>
						<tr>
							<td colspan="2"><?php esc_html_e( 'The below list of Post Types are excluded from monitoring. This means that all activity related to these post types will not be recorded.', 'wp-security-audit-log' ); ?></td>
						</tr>
						<tr>
							<th><label for="ExCPTsQueryBox"><?php esc_html_e( 'Exclude Post Type from monitoring', 'wp-security-audit-log' ); ?></label></th>
							<td>
								<fieldset>
									<input type="text" id="ExCPTsQueryBox" style="float: left; display: block; width: 250px;">
									<input type="button" id="ExCPTsQueryAdd" style="float: left; display: block;" class="button-primary" value="Add">
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
								</fieldset>
							</td>
						</tr>
						<!-- Excluded Custom Post Types -->
						<tr>
							<th colspan="2">
								<h2 class="wsal-tab__heading"><?php esc_html_e( 'Non-Existing URLs (404)', 'wp-security-audit-log' ); ?></h2>
							</th>
						</tr>
						<tr>
							<td colspan="2">
								<?php esc_html_e( 'Add the non existing URLs for which you do not want to be alerted of HTTP 404 errors in the activity log by specifying the whole URL.', 'wp-security-audit-log' ); ?>
								<br />
								<br />
								<?php echo sprintf( esc_html__( 'Example for file: %s/subdirectory/file.php', 'wp-security-audit-log' ), esc_url( home_url() ) ); ?>
								<br />
								<?php echo sprintf( esc_html__( 'Example for directory: %s/subdirectory/subdirectory2', 'wp-security-audit-log' ), esc_url( home_url() ) ); ?>
							</td>
						</tr>
						<tr>
							<th><label for="ExURLsQueryBox"><?php esc_html_e( 'Excluded Non-Existing URLs', 'wp-security-audit-log' ); ?></label></th>
							<td>
								<fieldset>
									<input type="text" id="ExURLsQueryBox" style="float: left; display: block; width: 250px;">
									<input type="button" id="ExURLsQueryAdd" style="float: left; display: block;" class="button-primary" value="Add">
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
								</fieldset>
							</td>
						</tr>
					</tbody>
				</table>
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
				// Enable/disable login notification textarea.
				function wsal_update_login_page_text( checkbox, textarea ) {
					if ( checkbox.prop( 'checked' ) ) {
						textarea.removeProp( 'disabled' );
					} else {
						textarea.prop( 'disabled', 'disabled' );
					}
				}

				// Login page notification settings.
				var login_page_notif = jQuery( '#login_page_notification' );
				var login_page_notif_text = jQuery( '#login_page_notification_text' );

				// Check the change event on checkbox.
				login_page_notif.on( 'change', function() {
					wsal_update_login_page_text( login_page_notif, login_page_notif_text );
				} );
			} );
		// --></script>
		<?php
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
		?>
		<style type="text/css">
			.wsal-tab {
				display: none;
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
			'wp_nonce' => wp_create_nonce( 'wsal-exclude-nonce' ),
		);
		wp_localize_script( 'settings', 'wsal_data', $wsal_data );
		wp_enqueue_script( 'settings' );
		?>
		<script type="text/javascript">
			jQuery(document).ready(function(){
				// tab handling code
				jQuery('#wsal-tabs>a').click(function(){
					jQuery('#wsal-tabs>a').removeClass('nav-tab-active');
					jQuery('table.wsal-tab').hide();
					jQuery(jQuery(this).addClass('nav-tab-active').attr('href')).show();
				});
				// show relevant tab
				var hashlink = jQuery('#wsal-tabs>a[href="' + location.hash + '"]');
				if (hashlink.length) {
					hashlink.click();
				} else {
					jQuery('#wsal-tabs>a:first').click();
				}

				jQuery(".sel-columns").change(function(){
					var notChecked = 1;
					jQuery(".sel-columns").each(function(){
						if(this.checked) notChecked = 0;
					})
					if(notChecked == 1){
						alert("You have to select at least one column!");
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
}
