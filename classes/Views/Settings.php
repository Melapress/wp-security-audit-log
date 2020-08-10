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
		add_action( 'wp_ajax_AjaxCheckSecurityToken', array( $this, 'AjaxCheckSecurityToken' ) );
		add_action( 'wp_ajax_AjaxRunCleanup', array( $this, 'AjaxRunCleanup' ) );
		add_action( 'wp_ajax_AjaxGetAllUsers', array( $this, 'AjaxGetAllUsers' ) );
		add_action( 'wp_ajax_AjaxGetAllRoles', array( $this, 'AjaxGetAllRoles' ) );
		add_action( 'wp_ajax_AjaxGetAllCPT', array( $this, 'AjaxGetAllCPT' ) );
		add_action( 'wp_ajax_wsal_reset_settings', array( $this, 'reset_settings' ) );
		add_action( 'wp_ajax_wsal_purge_activity', array( $this, 'purge_activity' ) );
	}

	/**
	 * Setup WSAL Settings Page Tabs.
	 *
	 * @since 3.4
	 */
	public function setup_settings_tabs() {
		// @codingStandardsIgnoreStart
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : false;
		// @codingStandardsIgnoreEnd

		// Verify that the current page is WSAL settings page.
		if ( empty( $page ) || $this->GetSafeViewName() !== $page ) {
			return;
		}

		// Tab links.
		$wsal_setting_tabs = array(
			'general'           => array(
				'name'     => __( 'General', 'wp-security-audit-log' ),
				'link'     => add_query_arg( 'tab', 'general', $this->GetUrl() ),
				'render'   => array( $this, 'tab_general' ),
				'save'     => array( $this, 'tab_general_save' ),
				'priority' => 10,
			),
			'audit-log'         => array(
				'name'     => __( 'Activity Log Viewer', 'wp-security-audit-log' ),
				'link'     => add_query_arg( 'tab', 'audit-log', $this->GetUrl() ),
				'render'   => array( $this, 'tab_audit_log' ),
				'save'     => array( $this, 'tab_audit_log_save' ),
				'priority' => 20,
			),
			'file-changes'      => array(
				'name'     => __( 'File Changes', 'wp-security-audit-log' ),
				'link'     => add_query_arg( 'tab', 'file-changes', $this->GetUrl() ),
				'render'   => array( $this, 'tab_file_changes' ),
				'priority' => 30,
			),
			'exclude-objects'   => array(
				'name'     => __( 'Exclude Objects', 'wp-security-audit-log' ),
				'link'     => add_query_arg( 'tab', 'exclude-objects', $this->GetUrl() ),
				'render'   => array( $this, 'tab_exclude_objects' ),
				'save'     => array( $this, 'tab_exclude_objects_save' ),
				'priority' => 40,
			),
			'advanced-settings' => array(
				'name'     => __( 'Advanced Settings', 'wp-security-audit-log' ),
				'link'     => add_query_arg( 'tab', 'advanced-settings', $this->GetUrl() ),
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
		 * @since 3.2.3
		 */
		$wsal_setting_tabs = apply_filters( 'wsal_setting_tabs', $wsal_setting_tabs );

		// Sort by priority.
		array_multisort( array_column( $wsal_setting_tabs, 'priority' ), SORT_ASC, $wsal_setting_tabs );

		$this->wsal_setting_tabs = $wsal_setting_tabs;

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
		return 12;
	}

	/**
	 * Method: Get Token Type.
	 *
	 * @param string $token - Token type.
	 */
	protected function GetTokenType( $token ) {
		return $this->_plugin->settings()->get_token_type( $token );
	}

	/**
	 * Method: Save settings.
	 *
	 * @throws Exception - Unrecognized settings tab error.
	 */
	protected function Save() {
		// Bail early if user does not have sufficient permissions to save.
		if ( ! $this->_plugin->settings()->CurrentUserCan( 'edit' ) ) {
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
	public function AjaxCheckSecurityToken() {
		if ( ! $this->_plugin->settings()->CurrentUserCan( 'view' ) ) {
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
		if ( ! $this->_plugin->settings()->CurrentUserCan( 'view' ) ) {
			die( 'Access Denied.' );
		}

		$now       = current_time( 'timestamp' ); // Current time.
		$max_sdate = $this->_plugin->settings()->GetPruningDate(); // Pruning date.

		// If archiving is enabled then events are deleted from the archive database.
		$archiving = $this->_plugin->settings()->IsArchivingEnabled();
		if ( $archiving ) {
			// Switch to Archive DB.
			$this->_plugin->settings()->SwitchToArchiveDB();
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
			$archiving_args = array(
				'page' => 'wsal-ext-settings',
				'tab'  => 'archiving',
			);
			$archiving_url  = add_query_arg( $archiving_args, admin_url( 'admin.php' ) );
			wp_safe_redirect( $archiving_url );
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
		// Verify nonce if a form is submitted.
		if ( isset( $_POST['_wpnonce'] ) ) {
			check_admin_referer( 'wsal-settings' );
		}

		if ( ! $this->_plugin->settings()->CurrentUserCan( 'edit' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-security-audit-log' ) );
		}

		// Check to see if section parameter is set in the URL.
		$section = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : false;

		if ( isset( $_POST['submit'] ) ) {
			try {
				$this->Save(); // Save settings.
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
                <div class="error"><p><?php esc_html_e( 'Error: ', 'wp-security-audit-log' ); ?><?php echo esc_html( $ex->getMessage() ); ?></p></div>
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
            <input type="hidden" name="page" value="<?php echo isset( $_GET['page'] ) ? esc_attr( sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) : false; ?>" />
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
			<?php
			if ( 'sms-provider' === $this->current_tab && $section && 'test' === $section ) {
				submit_button( __( 'Send Message', 'wp-security-audit-log' ) );
			} else {
				submit_button();
			}
			?>
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
        <p class="description"><?php echo wp_kses( __( 'Need help with setting up the plugin to meet your requirements? <a href="https://wpactivitylog.com/contact/?utm_source=plugin&utm_medium=referral&utm_campaign=WSAL&utm_content=settings+pages" target="_blank">Schedule a 20 minutes consultation and setup call</a> with our experts for just $50.', 'wp-security-audit-log' ), $this->_plugin->allowed_html_tags ); ?></p>

        <h3><?php esc_html_e( 'Use infinite scroll or pagination for the event viewer?', 'wp-security-audit-log' ); ?></h3>
        <p class="description">
			<?php
			echo sprintf(
				/* translators: Learn more link. */
				esc_html__( 'When using infinite scroll the event viewer and search results %s load up much faster and require less resources.', 'wp-security-audit-log' ),
				'<a href="https://wpactivitylog.com/features/search-filters-wordpress-activity-log/?utm_source=plugin&utm_medium=referral&utm_campaign=WSAL&utm_content=settings+pages" target="_blank">' . esc_html__( '(Premium feature)', 'wp-security-audit-log' ) . '</a>'
			);
			?>
        </p>
        <table class="form-table wsal-tab">
            <tbody>
            <tr>
                <th><label for="infinite-scroll"><?php esc_html_e( 'Select event viewer view type:', 'wp-security-audit-log' ); ?></label></th>
                <td>
                    <fieldset>
                        <label for="infinite-scroll">
                            <input type="radio" name="events-type-nav" value="infinite-scroll" id="infinite-scroll" <?php checked( $this->_plugin->settings()->get_events_type_nav(), 'infinite-scroll' ); ?> />
							<?php esc_html_e( 'Infinite Scroll (Recommended)', 'wp-security-audit-log' ); ?>
                        </label>
                        <br/>
                        <label for="pagination">
                            <input type="radio" name="events-type-nav" value="pagination" id="pagination" <?php checked( $this->_plugin->settings()->get_events_type_nav(), 'pagination' ); ?> />
							<?php esc_html_e( 'Pagination', 'wp-security-audit-log' ); ?>
                        </label>
                        <br />
                    </fieldset>
                </td>
            </tr>
            <!-- / Reverse Proxy / Firewall Options -->
            </tbody>
        </table>
        <!-- Events Navigation Type -->

        <h3><?php esc_html_e( 'Do you want the activity log viewer to auto refresh?', 'wp-security-audit-log' ); ?></h3>
        <p class="description"><?php esc_html_e( 'The activity log viewer auto refreshes every 30 seconds when opened so you can see the latest events as they happen almost in real time.', 'wp-security-audit-log' ); ?></p>
        <table class="form-table wsal-tab">
            <tbody>
            <tr>
                <th><label for="aroption_on"><?php esc_html_e( 'Refresh activity log viewer', 'wp-security-audit-log' ); ?></label></th>
                <td>
                    <fieldset>
						<?php $are = $this->_plugin->settings()->IsRefreshAlertsEnabled(); ?>
                        <label for="aroption_on">
                            <input type="radio" name="EnableAuditViewRefresh" id="aroption_on" style="margin-top: -2px;" <?php checked( $are ); ?> value="1">
                            <span><?php esc_html_e( 'Auto refresh', 'wp-security-audit-log' ); ?></span>
                        </label>
                        <br/>
                        <label for="aroption_off">
                            <input type="radio" name="EnableAuditViewRefresh" id="aroption_off" style="margin-top: -2px;" <?php checked( $are, false ); ?> value="0">
                            <span><?php esc_html_e( 'Do not auto refresh', 'wp-security-audit-log' ); ?></span>
                        </label>
                    </fieldset>
                </td>
            </tr>
            <!-- Refresh activity log viewer -->
            </tbody>
        </table>
        <!-- Refresh activity log -->

        <h3><?php esc_html_e( 'Display latest events widget in Dashboard & Admin bar', 'wp-security-audit-log' ); ?></h3>
        <p class="description">
			<?php
			echo sprintf(
				/* translators: Max number of dashboard widget alerts. */
				esc_html__( 'The events widget displays the latest %d security events in the dashboard and the admin bar notification displays the latest event.', 'wp-security-audit-log' ),
				esc_html( $this->_plugin->settings()->GetDashboardWidgetMaxAlerts() )
			);
			?>
        </p>
        <table class="form-table wsal-tab">
            <tbody>
            <tr>
                <th><label for="dwoption_on"><?php esc_html_e( 'Dashboard Widget', 'wp-security-audit-log' ); ?></label></th>
                <td>
                    <fieldset>
						<?php $dwe = $this->_plugin->settings()->IsWidgetsEnabled(); ?>
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
				$label    = __( 'Admin Bar Notification', 'wp-security-audit-log' );
				if ( wsal_freemius()->is_free_plan() ) {
					$disabled = 'disabled';
					$label    = __( 'Admin Bar Notification (Premium)', 'wp-security-audit-log' );
				}
				?>
                <th><label for="admin_bar_notif_on"><?php echo esc_html( $label ); ?></label></th>
                <td>
                    <fieldset <?php echo esc_attr( $disabled ); ?>>
						<?php $abn = $this->_plugin->settings()->is_admin_bar_notif(); ?>
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
				$label    = __( 'Admin Bar Notification Updates', 'wp-security-audit-log' );
				if ( wsal_freemius()->is_free_plan() ) {
					$disabled = 'disabled';
					$label    = __( 'Admin Bar Notification Updates (Premium)', 'wp-security-audit-log' );
				}
				?>
                <th><label for="admin_bar_notif_refresh"><?php echo esc_html( $label ); ?></label></th>
                <td>
                    <fieldset <?php echo esc_attr( $disabled ); ?>>
						<?php $abn_updates = $this->_plugin->settings()->get_admin_bar_notif_updates(); ?>
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
                    <fieldset>
						<?php
						// Get login page notification checkbox.
						$wsal_lpn = $this->_plugin->settings()->is_login_page_notification();
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
						$wsal_lpn_text         = $this->_plugin->settings()->get_login_page_notification_text();
						$wsal_lpn_text_default = __( 'For security and auditing purposes, a record of all of your logged-in actions and changes within the WordPress dashboard will be recorded in an activity log with the <a href="https://wpactivitylog.com/?utm_source=plugin&utm_medium=referral&utm_campaign=WSAL&utm_content=settings+pages" target="_blank">WP Activity Log plugin</a>. The audit log also includes the IP address where you accessed this site from.', 'wp-security-audit-log' );

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
				'<a href="https://wpactivitylog.com/support/kb/support-reverse-proxies-web-application-firewalls/?utm_source=plugin&utm_medium=referral&utm_campaign=WSAL&utm_content=settings+pages" target="_blank">' . esc_html__( 'learn more', 'wp-security-audit-log' ) . '</a>'
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
                            <input type="radio" name="EnableProxyIpCapture" value="1" id="enable_proxy_ip_capture_yes" <?php checked( $this->_plugin->settings()->IsMainIPFromProxy() ); ?> />
							<?php esc_html_e( 'Yes', 'wp-security-audit-log' ); ?>
                        </label>
                        <br/>
                        <label for="EnableIpFiltering">
                            <input type="checkbox" name="EnableIpFiltering" value="1" id="EnableIpFiltering" <?php checked( $this->_plugin->settings()->IsInternalIPsFiltered() ); ?> />
							<?php esc_html_e( 'Filter internal IP addresses from the proxy headers. Enable this option only if you are	are still seeing the internal IP addresses of the firewall or proxy.', 'wp-security-audit-log' ); ?>
                        </label>
                        <br/>
                        <label for="enable_proxy_ip_capture_no">
                            <input type="radio" name="EnableProxyIpCapture" value="0" id="enable_proxy_ip_capture_no" <?php checked( $this->_plugin->settings()->IsMainIPFromProxy(), false ); ?> />
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
					'<a href="https://wpactivitylog.com/support/kb/managing-wordpress-activity-log-plugin-privileges/?utm_source=plugin&utm_medium=referral&utm_campaign=WSAL&utm_content=settings+pages" target="_blank">' . __( 'learn more', 'wp-security-audit-log' ) . '</a>'
				),
				$allowed_tags
			);
			$restrict_settings = $this->_plugin->settings()->get_restrict_plugin_setting();
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
							if ( $this->_plugin->IsMultisite() ) {
								esc_html_e( 'All superadmins', 'wp-security-audit-log' );
							} else {
								esc_html_e( 'All administrators', 'wp-security-audit-log' );
							}
							?>
							<?php  ?>
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
			$allowed_tags = array(
				'a' => array(
					'href'   => true,
					'target' => true,
				),
			);
			$is_multisite = $this->_plugin->IsMultisite();
			$section_label = '';
			if ($is_multisite) {
				$section_label = esc_html__('By default only super administrators and the child sites\' administrators can view the WordPress activity log. Though you can change this by using the setting below.', 'wp-security-audit-log');
			} else {
				$section_label = esc_html__('By default only users with administrator role can view the WordPress activity log. To allow someone who does not have an admin role to view the activity log, specify them in the below setting.', 'wp-security-audit-log');
			}

			echo wp_kses(
				$section_label . ' - <a href="https://wpactivitylog.com/support/kb/allow-users-read-access-activity-log/?utm_source=plugin&utm_medium=referral&utm_campaign=WSAL&utm_content=settings+pages" target="_blank">' . __( 'learn more', 'wp-security-audit-log' ) . '</a>',
				$allowed_tags
			);
			?>
        </p>
		<?php if ( $is_multisite ): ?>
            <table class="form-table wsal-tab">
                <tbody>
                <tr>
                    <th><?php esc_html_e( 'Can view events', 'wp-security-audit-log' ); ?></th>
                    <td>
                        <fieldset>
							<?php
							$restrict_settings = $this->_plugin->settings()->get_restrict_log_viewer();
							$viewer_restriction_options = array(
								'only_me' => __('Only me', 'wp-security-audit-log'),
								'only_superadmins' => __('Super administators only', 'wp-security-audit-log'),
								'only_admins' => __('Super administators and site administrators', 'wp-security-audit-log'),
							);
							?>
							<?php foreach ($viewer_restriction_options as $option => $label): ?>
                                <label for="log_viewer_<?php echo $option; ?>">
									<?php $disabled = ('only_me' === $option && 'only_superadmins' === $restrict_settings); ?>
                                    <input type="radio" name="restrict-log-viewer" id="log_viewer_<?php echo $option; ?>" value="<?php echo $option; ?>" <?php checked( $restrict_settings, $option ); ?> <?php disabled( $disabled ); ?> />
									<?php echo esc_html( $label ); ?>
                                </label>
                                <br/>
							<?php endforeach; ?>
                        </fieldset>
                    </td>
                </tr>
                </tbody>
            </table>
            <p class="description"><?php esc_html_e('To allow someone who does not have an admin role to view the activity log, specify them in the below setting.', 'wp-security-audit-log'); ?></p>
		<?php endif; ?>
        <table class="form-table wsal-tab">
            <tbody>

				<tr>
                    <?php $row_label = $is_multisite ? esc_html__( 'Can also view events', 'wp-security-audit-log' ) : esc_html__( 'Can view events', 'wp-security-audit-log' ); ?>
					<th><label for="ViewerQueryBox"><?php echo $row_label; ?></label></th>
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
								foreach ( $this->_plugin->settings()->GetAllowedPluginViewers() as $item ) :
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
		<p class="description"><?php esc_html_e( 'By default when the plugin sends an email notification it uses the email address specified in this website’s general settings. Though you can change the email address and display name from this section.', 'wp-security-audit-log' ); ?></p>
		<table class="form-table wsal-tab">
			<tbody>
				<tr>
					<th><label for="FromEmail"><?php esc_html_e( 'From Email & Name', 'wp-security-audit-log' ); ?></label></th>
					<td>
						<fieldset>
							<?php $use_email = $this->_plugin->GetGlobalSetting( 'use-email', 'default_email' ); ?>
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
								<input type="email" id="FromEmail" name="FromEmail" value="<?php echo esc_attr( $this->_plugin->settings()->GetFromEmail() ); ?>" />
							</label>
							<br>
							<label for="DisplayName">
								<?php esc_html_e( 'Display Name', 'wp-security-audit-log' ); ?>&nbsp;
								<input type="text" id="DisplayName" name="DisplayName" value="<?php echo esc_attr( $this->_plugin->settings()->GetDisplayName() ); ?>" />
							</label>
						</fieldset>
					</td>
				</tr>
				<!-- / From Email & Name -->
			</tbody>
		</table>
		<!-- From Email & Name -->

		<h3><?php esc_html_e( 'Do you want to hide the plugin from the list of installed plugins?', 'wp-security-audit-log' ); ?></h3>
		<p class="description"><?php esc_html_e( 'By default all installed plugins are listed in the plugins page. If you do not want other administrators to see that you installed this plugin set this option to Yes so the WP Activity Log is not listed as an installed plugin on this website.', 'wp-security-audit-log' ); ?></p>
		<table class="form-table wsal-tab">
			<tbody>
				<tr>
					<th><label for="incognito_yes"><?php esc_html_e( 'Hide Plugin in Plugins Page', 'wp-security-audit-log' ); ?></label></th>
					<td>
						<fieldset>
							<label for="incognito_yes">
								<input type="radio" name="Incognito" value="yes" id="incognito_yes" <?php checked( $this->_plugin->settings()->IsIncognito() ); ?> />
								<?php esc_html_e( 'Yes, hide the plugin from the list of installed plugins', 'wp-security-audit-log' ); ?>
							</label>
							<br/>
							<label for="incognito_no">
								<input type="radio" name="Incognito" value="no" id="incognito_no" <?php checked( $this->_plugin->settings()->IsIncognito(), false ); ?> />
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

		$this->_plugin->settings()->SetRefreshAlertsEnabled( $post_array['EnableAuditViewRefresh'] );
		$this->_plugin->settings()->set_events_type_nav( sanitize_text_field( $post_array['events-type-nav'] ) );
		$this->_plugin->settings()->set_use_email( sanitize_text_field( $post_array['use-email'] ) );
		$this->_plugin->settings()->SetFromEmail( sanitize_email( $post_array['FromEmail'] ) );
		$this->_plugin->settings()->SetDisplayName( sanitize_text_field( $post_array['DisplayName'] ) );

		$this->_plugin->settings()->SetWidgetsEnabled( sanitize_text_field( $post_array['EnableDashboardWidgets'] ) );

		if ( ! wsal_freemius()->is_free_plan() ) {
			$this->_plugin->settings()->set_admin_bar_notif( sanitize_text_field( $post_array['admin_bar_notif'] ) );
			$this->_plugin->settings()->set_admin_bar_notif_updates( sanitize_text_field( $post_array['admin_bar_notif_updates'] ) );
		}

		//  handle log viewer settings in multisite context
		if ($this->_plugin->IsMultisite()) {
			$log_viewer_restrictions = isset( $post_array['restrict-log-viewer'] ) ? sanitize_text_field( $post_array['restrict-log-viewer'] ) : '';
			$this->_plugin->settings()->set_restrict_log_viewer($log_viewer_restrictions);
			if ( 'only_me' === $log_viewer_restrictions ) {
				$this->_plugin->settings()->set_only_me_user_id(get_current_user_id());
			}
		}

		// Get plugin viewers.
		$viewers = isset( $post_array['Viewers'] ) ? array_map( 'sanitize_text_field', $post_array['Viewers'] ) : array();
		$this->_plugin->settings()->SetAllowedPluginViewers( $viewers );

		// handle plugin settings permissions
		$restrict_settings = isset( $post_array['restrict-plugin-settings'] ) ? sanitize_text_field( $post_array['restrict-plugin-settings'] ) : false;
		$this->_plugin->settings()->set_restrict_plugin_setting( $restrict_settings );
		if ( 'only_me' === $restrict_settings ) {
			$this->_plugin->settings()->set_only_me_user_id(get_current_user_id());
		}

		$this->_plugin->settings()->set_login_page_notification( isset( $post_array['login_page_notification'] ) ? sanitize_text_field( $post_array['login_page_notification'] ) : false );
		$this->_plugin->settings()->set_login_page_notification_text( isset( $post_array['login_page_notification_text'] ) ? $post_array['login_page_notification_text'] : false );
		$this->_plugin->settings()->SetMainIPFromProxy( isset( $post_array['EnableProxyIpCapture'] ) ? sanitize_text_field( $post_array['EnableProxyIpCapture'] ) : false );
		$this->_plugin->settings()->SetInternalIPsFiltering( isset( $post_array['EnableIpFiltering'] ) ? sanitize_text_field( $post_array['EnableIpFiltering'] ) : false );

		$is_incognito = isset( $post_array['Incognito'] ) ? \WSAL\Helpers\Options::string_to_bool( sanitize_text_field( $post_array['Incognito'] ) ) : false;
		$this->_plugin->settings()->SetIncognito( $is_incognito );
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
			$retention_help_text = __( '<a href="https://wpactivitylog.com/pricing/?utm_source=plugin&utm_medium=referral&utm_campaign=WSAL&utm_content=settings+pages" target="_blank">Upgrade to Premium</a> to store the activity log data in an external database.', 'wp-security-audit-log' );

			echo wp_kses( $retention_help_text, $this->_plugin->allowed_html_tags );
			?>
        </p>
		<?php if ( $this->_plugin->settings()->IsArchivingEnabled() ) : ?>
            <p class="description">
				<?php
				$archiving_args = array(
					'page' => 'wsal-ext-settings',
					'tab'  => 'archiving',
				);
				$archiving_page = add_query_arg( $archiving_args, admin_url( 'admin.php' ) );
				/* translators: 1: Archive page link tag. 2: Link closing tag. */
				echo '<span class="dashicons dashicons-warning"></span> ' . sprintf( esc_html__( 'Retention settings moved to %1$s archiving settings %2$s because archiving is enabled', 'wp-security-audit-log' ), '<a href="' . esc_url( $archiving_page ) . '" target="_blank">', '</a>' );
				?>
            </p>
		<?php else : ?>
			<table class="form-table wsal-tab">
				<tbody>
					<tr>
						<th><label for="delete1"><?php esc_html_e( 'Activity log retention', 'wp-security-audit-log' ); ?></label></th>
						<td>
							<fieldset>
								<?php $nbld = ! $this->_plugin->settings()->IsPruningDateEnabled(); ?>
								<label for="delete0">
									<input type="radio" id="delete0" name="PruneBy" value="" <?php checked( $nbld ); ?> />
									<?php echo esc_html__( 'Keep all data', 'wp-security-audit-log' ); ?>
								</label>
							</fieldset>

							<fieldset>
								<?php
								// Check pruning date option.
								$nbld = $this->_plugin->settings()->IsPruningDateEnabled();

								// Find and replace ` months` in the string.
								$pruning_date = $this->_plugin->settings()->GetPruningDate();
								$pruning_date = str_replace( ' months', '', $pruning_date );
								$pruning_date = str_replace( ' years', '', $pruning_date );
								$pruning_unit = $this->_plugin->settings()->get_pruning_unit();

								// Check if pruning limit was enabled for backwards compatibility.
								if ( $this->_plugin->settings()->IsPruningLimitEnabled() ) {
									$nbld         = true;
									$pruning_date = '6';
									$pruning_unit = 'months';
									$this->_plugin->settings()->SetPruningDate( $pruning_date . ' ' . $pruning_unit );
									$this->_plugin->settings()->SetPruningDateEnabled( true );
									$this->_plugin->settings()->SetPruningLimitEnabled( false );
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

							<?php if ( $this->_plugin->settings()->IsPruningDateEnabled() ) : ?>
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
									<a class="button-primary" href="<?php echo esc_url( add_query_arg( 'action', 'AjaxRunCleanup', admin_url( 'admin-ajax.php' ) ) ); ?>"><?php esc_html_e( 'Purge Old Data', 'wp-security-audit-log' ); ?></a>
								</p>
							<?php endif; ?>
						</td>
					</tr>
					<!-- Activity log retention -->
				</tbody>
			</table>
		<?php endif; ?>
		<!-- Activity log retention -->

		<h3><?php esc_html_e( 'What timestamp you would like to see in the WordPress activity log?', 'wp-security-audit-log' ); ?></h3>
		<p class="description"><?php esc_html_e( 'Note that the WordPress\' timezone might be different from that configured on the server so when you switch from UTC to WordPress timezone or vice versa you might notice a big difference.', 'wp-security-audit-log' ); ?></p>
		<table class="form-table wsal-tab">
			<tbody>
				<tr>
					<th><label for="timezone-default"><?php esc_html_e( 'Events Timestamp', 'wp-security-audit-log' ); ?></label></th>
					<td>
						<fieldset>
							<?php
							$timezone = $this->_plugin->settings()->GetTimezone();

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
							<?php $show_milliseconds = $this->_plugin->settings()->get_show_milliseconds(); ?>
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
							<?php $type_username = $this->_plugin->settings()->get_type_username(); ?>
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

		<h3><?php esc_html_e( 'Select the columns to be displayed in the WordPress activity log', 'wp-security-audit-log' ); ?></h3>
		<p class="description"><?php esc_html_e( 'When you deselect a column it won’t be shown in the activity log viewer in both views. The data will still be recorded by the plugin.', 'wp-security-audit-log' ); ?></p>
		<table class="form-table wsal-tab">
			<tbody>
				<tr>
					<th><label for="columns"><?php esc_html_e( 'Activity log columns selection', 'wp-security-audit-log' ); ?></label></th>
					<td>
						<fieldset>
							<?php $columns = $this->_plugin->settings()->GetColumns(); ?>
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
									<?php elseif ( 'info' === $key ) : ?>
										<span><?php esc_html_e( 'Info (used in Grid view mode only)', 'wp-security-audit-log' ); ?></span>
									<?php else : ?>
										<span><?php echo esc_html( ucwords( str_replace( '_', ' ', $key ) ) ); ?></span>
									<?php endif; ?>
								</label>
								<br/>
							<?php } ?>
						</fieldset>
					</td>
				</tr>
				<!-- Activity log columns selection -->
			</tbody>
		</table>
		<!-- Activity log columns -->

        <?php $is_wp_backend = $this->_plugin->settings()->IsWPBackend(); ?>
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
		$pruning_date = isset( $post_array['PruningDate'] ) ? (int) sanitize_text_field( $post_array['PruningDate'] ) : false;
		$pruning_unit = isset( $post_array['pruning-unit'] ) ? sanitize_text_field( $post_array['pruning-unit'] ) : false;
		$pruning_date = ( ! empty( $pruning_date ) && ! empty( $pruning_unit ) ) ? $pruning_date . ' ' . $pruning_unit : false;

		$this->_plugin->settings()->SetPruningDateEnabled( isset( $post_array['PruneBy'] ) ? 'date' === $post_array['PruneBy'] : '' );
		$this->_plugin->settings()->SetPruningDate( $pruning_date );
		$this->_plugin->settings()->set_pruning_unit( $pruning_unit );
		$this->_plugin->settings()->SetTimezone( $post_array['Timezone'] );
		$this->_plugin->settings()->set_type_username( $post_array['type_username'] );
		$this->_plugin->settings()->SetWPBackend( isset( $post_array['WPBackend'] ) ? sanitize_text_field( $post_array['WPBackend'] ) : false );
		if ( ! empty( $post_array['Columns'] ) ) {
			$this->_plugin->settings()->SetColumns( $post_array['Columns'] );
		}
		$show_milliseconds = ( isset( $post_array['show_milliseconds'] ) && 'yes' === $post_array['show_milliseconds'] ) ? true : false;
		$this->_plugin->settings()->set_show_milliseconds( $show_milliseconds );
	}

	/**
	 * Tab: `File Changes`
	 */
	private function tab_file_changes() {
		?>

		<table class="form-table wsal-tab">
			<tbody>
				<tr>
					<?php if ( ! defined( 'WFCM_PLUGIN_FILE' ) ) : ?>
						<div class="addon-wrapper" style="max-width: 380px; text-align: center; border: 1px solid #ccc; padding: 25px;">
							<img src="<?php echo trailingslashit( WSAL_BASE_URL ) . 'img/help/website-file-changes-monitor.jpg'; ?>">
							<h4><?php echo esc_html__( 'Website File Changes Monitor', 'wp-security-audit-log' ); ?></h4>
							<p><?php echo esc_html__( 'To keep a log of file changes please install Website File Changes Monitor, a plugin which is also developed by us.', 'wp-security-audit-log' ); ?></p><br>
							<p><button class="install-addon button button-primary" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wsal-install-addon' ) ); ?>" data-plugin-slug="website-file-changes-monitor/website-file-changes-monitor.php" data-plugin-download-url="https://downloads.wordpress.org/plugin/website-file-changes-monitor.latest-stable.zip"><?php _e( 'Install plugin now', 'wp-security-audit-log' ); ?></button><span class="spinner" style="display: none; visibility: visible; float: none; margin: 0 0 0 8px;"></span> <a href="https://wpactivitylog.com/support/kb/wordpress-files-changes-warning-activity-logs/?utm_source=plugin&utm_medium=referral&utm_campaign=WSAL&utm_content=settings+pages" target="_blank" style="margin-left: 15px;"><?php echo esc_html__( 'Learn More', 'wp-security-audit-log' ); ?></a></p>
						</div>
					<?php else : ?>
						<?php
						$wcfm_settings_page = '';
						$redirect_args      = array(
							'page' => 'wfcm-file-changes',
						);
						if ( ! is_multisite() ) {
							$wcfm_settings_page = add_query_arg( $redirect_args, admin_url( 'admin.php' ) );
						} else {
							$wcfm_settings_page = add_query_arg( $redirect_args, network_admin_url( 'admin.php' ) );
						}
						?>
						<p><?php echo esc_html__( 'Configure how often file changes scan run and other settings from the', 'wp-security-audit-log' ); ?> <a class="button button-primary" href="<?php echo esc_url( $wcfm_settings_page ); ?>"><?php echo esc_html__( 'Website File Changes plugin settings', 'wp-security-audit-log' ); ?></a></p>
					<?php endif; ?>
				</tr>
			</tbody>
		</table>

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
							<input type="button" id="ExUserQueryAdd" class="button-primary" value="Add">
							<br style="clear: both;"/>
							<div id="ExUserList">
								<?php foreach ( $this->_plugin->settings()->GetExcludedMonitoringUsers() as $item ) : ?>
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
								<?php foreach ( $this->_plugin->settings()->GetExcludedMonitoringRoles() as $item ) : ?>
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
					<th><label for="IpAddrQueryBox"><?php esc_html_e( 'Exclude IP Address(es):', 'wp-security-audit-log' ); ?></label></th>
					<td>
						<fieldset>
							<input type="text" id="IpAddrQueryBox" style="width: 250px;">
							<input type="button" id="IpAddrQueryAdd" class="button-primary" value="Add">
							<br style="clear: both;"/>
							<div id="IpAddrList">
								<?php foreach ( $this->_plugin->settings()->GetExcludedMonitoringIP() as $item ) : ?>
									<span class="sectoken-<?php echo esc_attr( $this->GetTokenType( $item ) ); ?>">
										<input type="hidden" name="IpAddrs[]" value="<?php echo esc_attr( $item ); ?>"/>
										<?php echo esc_html( $item ); ?>
										<a href="javascript:;" title="Remove">&times;</a>
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
                        <input type="button" id="ExCPTsQueryAdd" class="button-primary" value="Add">
                        <br style="clear: both;"/>
                        <div id="ExCPTsList">
							<?php foreach ( $this->_plugin->settings()->get_excluded_post_types() as $item ) : ?>
                                <span class="sectoken-<?php echo esc_attr( $this->GetTokenType( $item ) ); ?>">
										<input type="hidden" name="ExCPTss[]" value="<?php echo esc_attr( $item ); ?>"/>
										<?php echo esc_html( $item ); ?>
										<a href="javascript:;" title="Remove">&times;</a>
									</span>
							<?php endforeach; ?>
                        </div>
                    </fieldset>
                    <p class="description"><?php esc_html_e( 'WordPress has the post and page post types by default though your website might use more post types (custom post types). You can exclude all post types, including the default WordPress ones.', 'wp-security-audit-log' ); ?></p>
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
							<?php foreach ( $this->_plugin->settings()->GetExcludedMonitoringCustom() as $item ) : ?>
                                <span class="sectoken-<?php echo esc_attr( $this->GetTokenType( $item ) ); ?>">
										<input type="hidden" name="Customs[]" value="<?php echo esc_attr( $item ); ?>"/>
										<?php echo esc_html( $item ); ?>
										<a href="javascript:;" title="Remove">&times;</a>
									</span>
							<?php endforeach; ?>
                        </div>
                    </fieldset>
                    <p class="description"><?php esc_html_e( 'You can use the * wildcard to exclude multiple matching custom fields. For example to exclude all custom fields starting with wp123 enter wp123*', 'wp-security-audit-log' ); ?></p>
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
							<?php foreach ( $this->_plugin->settings()->get_excluded_urls() as $item ) : ?>
                                <span class="sectoken-<?php echo esc_attr( $this->GetTokenType( $item ) ); ?>">
										<input type="hidden" name="ExURLss[]" value="<?php echo esc_attr( $item ); ?>"/>
										<?php echo esc_html( $item ); ?>
										<a href="javascript:;" title="Remove">&times;</a>
									</span>
							<?php endforeach; ?>
                        </div>
                    </fieldset>
                    <p class="description"><?php esc_html_e( 'Add the non existing URLs for which you do not want to be alerted of HTTP 404 errors in the activity log by specifying the complete URL.	Examples below:', 'wp-security-audit-log' ); ?><br><?php echo esc_html__( 'File: ', 'wp-security-audit-log' ) . esc_url( home_url() ) . '/subdirectory/file.php'; ?><br><?php echo esc_html__( 'Directory: ', 'wp-security-audit-log' ) . esc_url( home_url() ) . '/subdirectory/subdirectory2'; ?></p>
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

		$this->_plugin->settings()->SetExcludedMonitoringUsers( isset( $post_array['ExUsers'] ) ? $post_array['ExUsers'] : array() );
		$this->_plugin->settings()->SetExcludedMonitoringRoles( isset( $post_array['ExRoles'] ) ? $post_array['ExRoles'] : array() );
		$this->_plugin->settings()->SetExcludedMonitoringCustom( isset( $post_array['Customs'] ) ? $post_array['Customs'] : array() );
		$this->_plugin->settings()->SetExcludedMonitoringIP( isset( $post_array['IpAddrs'] ) ? $post_array['IpAddrs'] : array() );
		$this->_plugin->settings()->set_excluded_post_types( isset( $post_array['ExCPTss'] ) ? $post_array['ExCPTss'] : array() );
		$this->_plugin->settings()->set_excluded_urls( isset( $post_array['ExURLss'] ) ? $post_array['ExURLss'] : array() );
	}

	/**
	 * Tab: `Advanced Settings`
	 */
	private function tab_advanced_settings() {
		$location = $this->_plugin->GetGlobalSetting( 'custom-logging-dir', $this->_plugin->settings()->get_default_working_dir_relative() );
		?>
        <p class="description">
			<?php esc_html_e( 'These settings are for advanced users.', 'wp-security-audit-log' ); ?>
			<?php echo sprintf( __( 'If you have any questions <a href="https://wpactivitylog.com/contact/?utm_source=plugin&utm_medium=referral&utm_campaign=WSAL&utm_content=settings+pages" target="_blank">contact us</a>.', 'wp-security-audit-log' ), $this->_plugin->allowed_html_tags ); ?>
		</p>

        <h3><?php esc_html_e( 'Where do you want the plugin\'s working directory for log files, reports and other files?', 'wp-security-audit-log' ); ?></h3>
        <p class="description"><?php esc_html_e( 'The plugin stores the reports it generates, a number of log files ( for example to keep a log of 404 errors), and the request log in this working directory. By default the directory is in the default WordPress uploads directory. Use the below setting to create the working directory in a different location. Note that the plugin requires write permissions to this directory. Please specify the relative path of the directory.', 'wp-security-audit-log' ); ?></p>
        <table class="form-table wsal-tab">
            <tbody>
            <!-- custom log directory -->
            <tr>
                <th><label><?php esc_html_e( 'Working directory location', 'wp-security-audit-log' ); ?></label></th>
                <td>
                    <fieldset>
                        <label for="wsal-custom-logs-dir">
                            <input type="text" name="wsal-custom-logs-dir" id="wsal-custom-logs-dir"
                                   value="<?php echo esc_attr( $location ); ?>">
                        </label>
                        <p class="description">
							<?php
							echo wp_kses(
								__( '<strong>Note:</strong> Enter a path from the root of your website: eg "/wp-content/uploads/wp-activity-log/".' ),
								$this->_plugin->allowed_html_tags
							);
							?>
                        </p>
                    </fieldset>
                </td>
            </tr>
            <!-- / custom log directory -->
            </tbody>
        </table>


        <h3><?php esc_html_e( 'Troubleshooting setting: Keep a debug log of all the requests this website receives', 'wp-security-audit-log' ); ?></h3>
        <p class="description"><?php esc_html_e( 'Only enable the request log on testing, staging and development website. Never enable logging on a live website unless instructed to do so. Enabling request logging on a live website may degrade the performance of the website.', 'wp-security-audit-log' ); ?></p>
        <table class="form-table wsal-tab">
            <tbody>
            <tr>
                <th><label><?php esc_html_e( 'Request Log', 'wp-security-audit-log' ); ?></label></th>
                <td>
                    <fieldset>
						<?php $devoption_checked = $this->_plugin->settings()->IsDevOptionEnabled( WSAL_Settings::OPT_DEV_REQUEST_LOG ); ?>
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
								__( '<strong>Note:</strong> The requests debug log file is saved as request.log.php in the /wp-content/uploads/wp-activity-log/ directory.' ),
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

		<?php $stealth_mode = $this->_plugin->settings()->is_stealth_mode(); ?>
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
				<!-- / MainWP Child Site Stealth Mode -->
                <?php $admin_blocking_plugins_support = $this->_plugin->settings()->get_admin_blocking_plugin_support(); ?>
                <tr>
                    <th><label for="mwp_admin_blocking_support"><?php esc_html_e( 'Admin blocking plugins support', 'wp-security-audit-log' ); ?></label></th>
                    <td>
                        <fieldset>
                            <label for="mwp_admin_blocking_support">
                                <input type="checkbox" name="mwp_admin_blocking_support" value="yes" id="mwp_admin_blocking_support" <?php checked( $admin_blocking_plugins_support ); ?> <?php disabled( ! WpSecurityAuditLog::is_mainwp_active() || !$stealth_mode); ?>/>
                                <?php esc_html_e( 'Enable early plugin loading on sites that use admin blocking plugins', 'wp-security-audit-log' ); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
                <!-- /  Admin blocking plugins support -->
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
									<?php checked( $this->_plugin->settings()->IsDeleteData() ); ?>
								/>
								<?php esc_html_e( 'Yes', 'wp-security-audit-log' ); ?>
							</label>
							<br>
							<label for="delete_data_no">
								<input type="radio" name="DeleteData" value="0" id="delete_data_no"
									<?php checked( $this->_plugin->settings()->IsDeleteData(), false ); ?>
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
	 *
	 * @throws Exception - MainWP Child plugin not active exception.
	 */
	private function tab_advanced_settings_save() {
		// Get $_POST global array.
		$post_array = filter_input_array( INPUT_POST );

		$this->_plugin->settings()->SetDeleteData( isset( $post_array['DeleteData'] ) ? sanitize_text_field( $post_array['DeleteData'] ) : false );
		$this->_plugin->settings()->ClearDevOptions();
		if ( isset( $post_array['DevOptions'] ) && 'r' === $post_array['DevOptions'] ) {
			$this->_plugin->settings()->SetDevOptionEnabled( 'r', true );
		} else {
			$this->_plugin->settings()->SetDevOptionEnabled( 'r', false );
		}

        $was_admin_blocking_plugins_support_just_enabled = false;
		$stealth_mode = isset( $post_array['mwp_stealth_mode'] ) ? $post_array['mwp_stealth_mode'] : false;
		if ( 'yes' === $stealth_mode ) {
			if ( ! WpSecurityAuditLog::is_mainwp_active() ) {
				throw new Exception( __( 'MainWP Child plugin is not active on this website.', 'wp-security-audit-log' ) );
			}
			$this->_plugin->settings()->set_mainwp_child_stealth_mode();

			$admin_blocking_plugins_support = isset( $post_array['mwp_admin_blocking_support'] ) ? $post_array['mwp_admin_blocking_support'] : false;
			if ( 'yes' === $admin_blocking_plugins_support ) {
				$this->_plugin->settings()->set_admin_blocking_plugin_support(true);
                $was_admin_blocking_plugins_support_just_enabled = true;
			}
		} else {
			$this->_plugin->settings()->deactivate_mainwp_child_stealth_mode();
		}

		$custom_logging_dir = $this->_plugin->settings()->get_default_working_dir_relative();
		if ( isset( $post_array['wsal-custom-logs-dir'] ) ) {
			$posted_logging_dir = filter_var( $post_array['wsal-custom-logs-dir'], FILTER_SANITIZE_STRING );
			if (!empty($posted_logging_dir)) {
				$custom_logging_dir = $posted_logging_dir;
			}
		}

		if ( ! empty( $custom_logging_dir ) ) {
			$custom_logging_path = trailingslashit( ABSPATH ) . ltrim( trailingslashit( $custom_logging_dir ), '/' );
			if ( ! is_dir( $custom_logging_path ) || ! is_readable( $custom_logging_path ) || ! is_writable( $custom_logging_path ) ) {
                $dir_made = wp_mkdir_p( $custom_logging_path );
                if ( $dir_made ) {
                    // make an empty index.php in the directory.
                    @file_put_contents( $custom_logging_path . 'index.php', '<?php // Silence is golden' );
                }

				// if the directory was not made then we will display an error message
				if ( ! $dir_made ) {
				    //  throw an exception to display an error message
				    throw new Exception( __( 'The plugin cannot create the directory for the log files. Please check permissions and configure it again.', 'wp-security-audit-log' ) );
				}
			}
			// save.
			$this->_plugin->SetGlobalSetting( 'custom-logging-dir', $custom_logging_dir );
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
		if ( ! $this->_plugin->settings()->CurrentUserCan( 'view' ) ) {
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
		if ( ! $this->_plugin->settings()->CurrentUserCan( 'view' ) ) {
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
		if ( ! $this->_plugin->settings()->CurrentUserCan( 'view' ) ) {
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
		$post_types        = get_post_types( array(), 'names' );
		// if we are running multisite and have networkwide cpt tracker get the
		// list from and merge to the post_types array.
		if ( is_multisite() && class_exists( '\WSAL\Multisite\NetworkWide\CPTsTracker' ) ) {
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
	 * Method: Reset plugin settings table.
	 */
	public function reset_settings() {
		// Die if user does not have permission to change settings.
		if ( ! $this->_plugin->settings()->CurrentUserCan( 'view' ) ) {
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
		if ( ! $this->_plugin->settings()->CurrentUserCan( 'view' ) ) {
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
