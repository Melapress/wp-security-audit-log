<?php
/**
 * Audit Log View Class
 *
 * Class file for Audit Log View.
 *
 * @since 1.0.0
 * @package wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Audit Log Viewer Page
 *
 * @package Wsal
 */
class WSAL_Views_AuditLog extends WSAL_AbstractView {

	/**
	 * Listing view object (Instance of WSAL_AuditLogListView).
	 *
	 * @var object
	 */
	protected $_listview;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	protected $_version;

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
	 * Method: Constructor
	 *
	 * @param WpSecurityAuditLog $plugin - Instance of WpSecurityAuditLog.
	 */
	public function __construct( WpSecurityAuditLog $plugin ) {
		parent::__construct( $plugin );
		add_action( 'wp_ajax_AjaxInspector', array( $this, 'AjaxInspector' ) );
		add_action( 'wp_ajax_AjaxRefresh', array( $this, 'AjaxRefresh' ) );
		add_action( 'wp_ajax_AjaxSetIpp', array( $this, 'AjaxSetIpp' ) );
		add_action( 'wp_ajax_AjaxSearchSite', array( $this, 'AjaxSearchSite' ) );
		add_action( 'wp_ajax_AjaxSwitchDB', array( $this, 'AjaxSwitchDB' ) );
		add_action( 'wp_ajax_wsal_download_failed_login_log', array( $this, 'wsal_download_failed_login_log' ) );
		add_action( 'wp_ajax_wsal_download_404_log', array( $this, 'wsal_download_404_log' ) );
		add_action( 'wp_ajax_wsal_freemius_opt_in', array( $this, 'wsal_freemius_opt_in' ) );
		add_action( 'wp_ajax_wsal_exclude_url', array( $this, 'wsal_exclude_url' ) );
		add_action( 'wp_ajax_wsal_dismiss_advert', array( $this, 'wsal_dismiss_advert' ) );
		add_action( 'wp_ajax_wsal_dismiss_notice_disconnect', array( $this, 'dismiss_notice_disconnect' ) );
		add_action( 'wp_ajax_wsal_dismiss_wp_pointer', array( $this, 'dismiss_wp_pointer' ) );
		add_action( 'all_admin_notices', array( $this, 'AdminNoticesPremium' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'load_pointers' ), 1000 );
		add_filter( 'wsal_pointers_toplevel_page_wsal-auditlog', array( $this, 'register_privacy_pointer' ), 10, 1 );
		add_action( 'admin_init', array( $this, 'handle_form_submission' ) );

		if ( $this->_plugin->settings->is_infinite_scroll() ) {
			add_action( 'wp_ajax_wsal_infinite_scroll_events', array( $this, 'infinite_scroll_events' ) );
		}

		// Check plugin version for to dismiss the notice only until upgrade.
		$this->_version = WSAL_VERSION;

		// Set adverts array.
		$this->adverts = array(
			0 => array(
				'head' => __( 'Get instantly alerted of critical changes via SMS & email, search the activity log, generate user reports, see who is logged in and more!', 'wp-security-audit-log' ),
				'desc' => __( 'Upgrade to premium to unlock these powerful activity log features & more!', 'wp-security-audit-log' ),
			),
			1 => array(
				'head' => __( 'Instant SMS & email alerts, search & filters, reports, users sessions management and much more!', 'wp-security-audit-log' ),
				'desc' => __( 'Upgrade to premium to get more out of your activity logs!', 'wp-security-audit-log' ),
			),
			2 => array(
				'head' => __( 'See who logged in on your site in real-time, generate reports, get SMS & email alerts of critical changes and more!', 'wp-security-audit-log' ),
				'desc' => __( 'Unlock these and other powerful features with WP Security Audit Log Premium.', 'wp-security-audit-log' ),
			),
		);
	}

	/**
	 * Add premium extensions notice.
	 *
	 * Notices:
	 *   1. Plugin advert.
	 *   2. DB disconnection notice.
	 *   3. Freemius opt-in/out notice.
	 */
	public function AdminNoticesPremium() {
		$is_current_view = $this->_plugin->views->GetActiveView() == $this;

		// Check if any of the extensions is activated.
		if (
			! class_exists( 'WSAL_NP_Plugin' )
			&& ! class_exists( 'WSAL_Ext_Plugin' )
			&& ! class_exists( 'WSAL_Rep_Plugin' )
			&& ! class_exists( 'WSAL_SearchExtension' )
			&& ! class_exists( 'WSAL_User_Management_Plugin' )
			&& 'anonymous' !== get_site_option( 'wsal_freemius_state', 'anonymous' ) // Anonymous mode option.
		) {
			$get_transient_fn         = $this->_plugin->IsMultisite() ? 'get_site_transient' : 'get_transient'; // Check for multisite.
			$wsal_is_advert_dismissed = $get_transient_fn( 'wsal-is-advert-dismissed' ); // Check if advert has been dismissed.
			$wsal_premium_advert      = $this->_plugin->GetGlobalOption( 'premium-advert', false ); // Get the advert to display.
			$wsal_premium_advert      = false !== $wsal_premium_advert ? (int) $wsal_premium_advert : 0; // Set the default.

			if ( current_user_can( 'manage_options' ) && $is_current_view && ! $wsal_is_advert_dismissed ) : ?>
				<div class="updated wsal_notice">
					<div class="wsal_notice__wrapper">
						<div class="wsal_notice__content">
							<img src="<?php echo esc_url( WSAL_BASE_URL ); ?>img/wsal-logo@2x.png">
							<p>
								<strong><?php echo isset( $this->adverts[ $wsal_premium_advert ]['head'] ) ? esc_html( $this->adverts[ $wsal_premium_advert ]['head'] ) : false; ?></strong><br>
								<?php echo isset( $this->adverts[ $wsal_premium_advert ]['desc'] ) ? esc_html( $this->adverts[ $wsal_premium_advert ]['desc'] ) : false; ?>
							</p>
						</div>
						<!-- /.wsal_notice__content -->
						<div class="wsal_notice__btns">
							<?php
							// Trial link arguments.
							$trial_args = array(
								'page'          => 'wsal-auditlog-pricing',
								'billing_cycle' => 'annual',
								'trial'         => 'true',
							);

							// Buy Now button link.
							$buy_now    = add_query_arg( 'page', 'wsal-auditlog-pricing', admin_url( 'admin.php' ) );
							$trial_link = add_query_arg( $trial_args, admin_url( 'admin.php' ) );

							// If user is not super admin and website is multisite then change the URL.
							if ( $this->_plugin->IsMultisite() && ! is_super_admin() ) {
								$buy_now    = 'https://www.wpsecurityauditlog.com/pricing/';
								$trial_link = 'https://www.wpsecurityauditlog.com/pricing/';
							} elseif ( $this->_plugin->IsMultisite() && is_super_admin() ) {
								$buy_now    = add_query_arg( 'page', 'wsal-auditlog-pricing', network_admin_url( 'admin.php' ) );
								$trial_link = add_query_arg( $trial_args, network_admin_url( 'admin.php' ) );
							}

							$more_info = add_query_arg(
								array(
									'utm_source'   => 'plugin',
									'utm_medium'   => 'banner',
									'utm_content'  => 'audit+log+viewier+more+info',
									'utm_campaign' => 'upgrade+premium',
								),
								'https://www.wpsecurityauditlog.com/premium-features/'
							);
							?>
							<?php wp_nonce_field( 'wsal_dismiss_advert', 'wsal-dismiss-advert', false, true ); ?>
							<a href="<?php echo esc_url( $buy_now ); ?>" class="button button-primary wsal_notice__btn"><?php esc_html_e( 'UPGRADE NOW', 'wp-security-audit-log' ); ?></a>
							<a href="<?php echo esc_url( $trial_link ); ?>" class="button button-primary"><?php esc_html_e( 'Start Free Trial', 'wp-security-audit-log' ); ?></a>
							<a href="<?php echo esc_url( $more_info ); ?>" target="_blank"><?php esc_html_e( 'Tell me more', 'wp-security-audit-log' ); ?></a>
							<br>
							<a href="javascript:;" data-advert="<?php echo esc_attr( $wsal_premium_advert ); ?>" onclick="wsal_dismiss_advert(this)" class="wsal_notice__btn_dismiss" title="<?php esc_attr_e( 'Dismiss the banner', 'wp-security-audit-log' ); ?>"><?php esc_html_e( 'Close', 'wp-security-audit-log' ); ?></a>
						</div>
						<!-- /.wsal_notice__btns -->
					</div>
					<!-- /.wsal_notice__wrapper -->
				</div>
				<?php
			endif;
		}

		// Get DB connector.
		$db_config  = WSAL_Connector_ConnectorFactory::GetConfig(); // Get DB connector configuration.
		$wsal_db    = $this->_plugin->getConnector( $db_config )->getConnection(); // Get DB connection.
		$connection = true;
		if ( isset( $wsal_db->dbh->errno ) ) {
			$connection = 0 !== (int) $wsal_db->dbh->errno ? false : true; // Database connection error check.
		} elseif ( is_wp_error( $wsal_db->error ) ) {
			$connection = false;
		}

		// Add connectivity notice.
		$notice_dismissed = get_transient( 'wsal-dismiss-notice-disconnect' );
		if ( ! $connection && false === $notice_dismissed && $is_current_view ) {
			?>
			<div class="notice notice-error is-dismissible" id="wsal-notice-connect-issue">
				<p><?php esc_html_e( 'There are connectivity issues with the database where the WordPress activity log is stored. The logs will be temporary buffered in the WordPress database until the connection is fully restored.', 'wp-security-audit-log' ); ?></p>
				<?php wp_nonce_field( 'wsal_dismiss_notice_disconnect', 'wsal-dismiss-notice-disconnect', false, true ); ?>
			</div>
			<?php
		}

		// Check anonymous mode.
		if ( 'anonymous' === get_site_option( 'wsal_freemius_state', 'anonymous' ) ) { // If user manually opt-out then don't show the notice.
			if (
				wsal_freemius()->is_anonymous() // Anonymous mode option.
				&& wsal_freemius()->is_not_paying() // Not paying customer.
				&& wsal_freemius()->has_api_connectivity() // Check API connectivity.
				&& $is_current_view
				&& $this->_plugin->settings->CurrentUserCan( 'edit' ) // Have permission to edit plugin settings.
			) {
				if ( ! is_multisite() || ( is_multisite() && is_network_admin() ) ) :
					?>
					<div class="notice notice-success">
						<p><strong><?php esc_html_e( 'Help WP Security Audit Log improve.', 'wp-security-audit-log' ); ?></strong></p>
						<p><?php echo esc_html__( 'Gathering non-sensitive diagnostic data about the plugin install helps us improve the plugin. When you opt-in, you also subscribe to our announcements (you can opt-out at any time). If you would rather opt-out, we will not collect any data.', 'wp-security-audit-log' ) . ' <a href="https://www.wpsecurityauditlog.com/support-documentation/non-sensitive-diagnostic-data/" target="_blank">' . esc_html__( 'Read more about what we collect.', 'wp-security-audit-log' ) . '</a>'; ?></p>
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
	}

	/**
	 * Method: Ajax handler for dismissing DB disconnect issue.
	 */
	public function dismiss_notice_disconnect() {
		// Get $_POST array arguments.
		$post_array_args = array(
			'nonce' => FILTER_SANITIZE_STRING,
		);
		$post_array      = filter_input_array( INPUT_POST, $post_array_args );

		// Verify nonce.
		if ( wp_verify_nonce( $post_array['nonce'], 'wsal_dismiss_notice_disconnect' ) ) {
			set_transient( 'wsal-dismiss-notice-disconnect', 1, 6 * HOUR_IN_SECONDS );
			die();
		}
		die( 'Nonce verification failed!' );
	}

	/**
	 * Method: Check if view has shortcut link.
	 */
	public function HasPluginShortcutLink() {
		return true;
	}

	/**
	 * Method: Get View Title.
	 */
	public function GetTitle() {
		return __( 'Audit Log Viewer', 'wp-security-audit-log' );
	}

	/**
	 * Method: Get View Icon.
	 */
	public function GetIcon() {
		return $this->_wpversion < 3.8
			? $this->_plugin->GetBaseUrl() . '/img/logo-main-menu.png'
			: $this->_plugin->GetBaseUrl() . '/img/wsal-menu-icon.svg';
	}

	/**
	 * Method: Get View Name.
	 */
	public function GetName() {
		return __( 'Audit Log Viewer', 'wp-security-audit-log' );
	}

	/**
	 * Method: Get View Weight.
	 */
	public function GetWeight() {
		return 1;
	}

	/**
	 * Method: Get View.
	 */
	protected function GetListView() {
		// Set page arguments.
		if ( ! $this->page_args ) {
			$this->page_args = new stdClass();

			// @codingStandardsIgnoreStart
			$this->page_args->page    = isset( $_REQUEST['page'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ) : false;
			$this->page_args->site_id = $this->_plugin->settings->get_view_site_id();

			// Order arguments.
			$this->page_args->order_by = isset( $_REQUEST['orderby'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) ) : false;
			$this->page_args->order    = isset( $_REQUEST['order'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) : false;

			// Search arguments.
			$this->page_args->search_term    = ( isset( $_REQUEST['s'] ) && ! empty( $_REQUEST['s'] ) ) ? trim( sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) ) : false;
			$this->page_args->search_filters = ( isset( $_REQUEST['filters'] ) && is_array( $_REQUEST['filters'] ) ) ? array_map( 'sanitize_text_field', wp_unslash( $_REQUEST['filters'] ) ) : false;
			// @codingStandardsIgnoreEnd
		}

		// Set events listing view class.
		if ( is_null( $this->_listview ) ) {
			$this->_listview = new WSAL_AuditLogListView( $this->_plugin, $this->page_args );
		}
		return $this->_listview;
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
		} elseif ( 'wsal-auditlog' !== $page ) { // Page is admin.php, now check auditlog page.
			return; // Return if the current page is not auditlog's.
		}

		// Verify nonce for security.
		if ( isset( $_GET['_wpnonce'] ) ) {
			check_admin_referer( 'bulk-logs' );
		}

		// @codingStandardsIgnoreStart
		$wpnonce     = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( $_GET['_wpnonce'] ) : false; // View nonce.
		$search      = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : false; // Search.
		$site_id     = isset( $_GET['wsal-cbid'] ) ? (int) sanitize_text_field( $_GET['wsal-cbid'] ) : false; // Site id.
		$search_save = ( isset( $_REQUEST['wsal-save-search-name'] ) && ! empty( $_REQUEST['wsal-save-search-name'] ) ) ? trim( sanitize_text_field( $_REQUEST['wsal-save-search-name'] ) ) : false;
		// @codingStandardsIgnoreEnd

		if ( ! empty( $wpnonce ) ) {
			// Remove args array.
			$remove_args = array(
				'_wp_http_referer',
				'_wpnonce',
				'wsal_as_widget_ip',
				'load_saved_search_field',
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
	public function Render() {
		if ( ! $this->_plugin->settings->CurrentUserCan( 'view' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-security-audit-log' ) );
		}

		// Verify nonce for security.
		if ( isset( $_GET['_wpnonce'] ) ) {
			check_admin_referer( 'bulk-logs' );
		}

		$this->GetListView()->prepare_items();
		?>
		<form id="audit-log-viewer" method="get">
			<div id="audit-log-viewer-content">
				<input type="hidden" name="page" value="<?php echo esc_attr( $this->page_args->page ); ?>" />
				<input type="hidden" id="wsal-cbid" name="wsal-cbid" value="<?php echo esc_attr( empty( $this->page_args->site_id ) ? '0' : $this->page_args->site_id ); ?>" />
				<?php
				/**
				 * Hook: `wsal_auditlog_before_view`
				 *
				 * This action hook is triggered before displaying the audit log view.
				 *
				 * @param WSAL_AuditLogListView $this->_listview - Audit log list view object.
				 */
				do_action( 'wsal_auditlog_before_view', $this->GetListView() );

				// Display the audit log list.
				$this->GetListView()->display();

				/**
				 * Hook: `wsal_auditlog_after_view`
				 *
				 * This action hook is triggered after displaying the audit log view.
				 *
				 * @param WSAL_AuditLogListView $this->_listview - Audit log list view object.
				 */
				do_action( 'wsal_auditlog_after_view', $this->GetListView() );
				?>
			</div>
		</form>

		<?php
		if (
			'no' === $this->_plugin->GetGlobalOption( 'wsal-setup-complete', 'no' )
			&& 'no' === $this->_plugin->GetGlobalOption( 'wsal-setup-modal-dismissed', 'no' )
		) :
			?>
			<div data-remodal-id="wsal-setup-modal">
				<button data-remodal-action="close" class="remodal-close"></button>
				<p><?php esc_html_e( 'Thank you for installing WP Security Audit Log. Do you want to run the wizard to configure the basic plugin settings?', 'wp-security-audit-log' ); ?></p>
				<br>
				<button data-remodal-action="confirm" class="remodal-confirm"><?php esc_html_e( 'Yes', 'wp-security-audit-log' ); ?></button>
				<button data-remodal-action="cancel" class="remodal-cancel"><?php esc_html_e( 'No', 'wp-security-audit-log' ); ?></button>
			</div>
			<script type="text/javascript">
				jQuery( document ).ready( function() {
					var wsal_setup_modal = jQuery( '[data-remodal-id="wsal-setup-modal"]' );
					wsal_setup_modal.remodal().open();

					jQuery(document).on('confirmation', wsal_setup_modal, function () {
						<?php $this->_plugin->SetGlobalOption( 'wsal-setup-modal-dismissed', 'yes' ); ?>
						window.location = '<?php echo esc_url( add_query_arg( 'page', 'wsal-setup', admin_url( 'index.php' ) ) ); ?>';
					});

					jQuery(document).on('cancellation', wsal_setup_modal, function () {
						<?php $this->_plugin->SetGlobalOption( 'wsal-setup-modal-dismissed', 'yes' ); ?>
					});

					jQuery(document).on('closed', wsal_setup_modal, function () {
						<?php $this->_plugin->SetGlobalOption( 'wsal-setup-modal-dismissed', 'yes' ); ?>
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
							'autorefresh' => array(
								'enabled' => ! $is_search_view ? $this->_plugin->settings->IsRefreshAlertsEnabled() : false,
								'token'   => $this->GetListView()->get_total_items(),
							),
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
		if ( ! $this->_plugin->settings->CurrentUserCan( 'view' ) ) {
			die( 'Access Denied.' );
		}

		// Filter $_GET array for security.
		$get_array = filter_input_array( INPUT_GET );

		if ( ! isset( $get_array['occurrence'] ) ) {
			die( 'Occurrence parameter expected.' );
		}

		// Get selected db.
		$selected_db      = get_transient( 'wsal_wp_selected_db' );
		$selected_db_user = (int) get_transient( 'wsal_wp_selected_db_user' );

		// Check if archive db is enabled and the current user matches the one who selected archive db.
		if ( ! empty( $selected_db ) && 'archive' === $selected_db && get_current_user_id() === $selected_db_user ) {
			$this->_plugin->settings->SwitchToArchiveDB(); // Switch to archive DB.
		}

		$occ = new WSAL_Models_Occurrence();
		$occ->Load( 'id = %d', array( (int) $get_array['occurrence'] ) );
		$alert_meta = $occ->GetMetaArray();
		unset( $alert_meta['ReportText'] );

		// Set WSAL_Ref class scripts and styles.
		WSAL_Ref::config( 'stylePath', esc_url( $this->_plugin->GetBaseDir() ) . '/css/wsal-ref.css' );
		WSAL_Ref::config( 'scriptPath', esc_url( $this->_plugin->GetBaseDir() ) . '/js/wsal-ref.js' );

		echo '<!DOCTYPE html><html><head>';
		echo '<style type="text/css">';
		echo 'html, body { margin: 0; padding: 0; }';
		echo '</style>';
		echo '</head><body>';
		wsal_r( $alert_meta );
		echo '</body></html>';
		die;
	}

	/**
	 * Ajax callback to refrest the view.
	 */
	public function AjaxRefresh() {
		if ( ! $this->_plugin->settings->CurrentUserCan( 'view' ) ) {
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

		// Check if the user is viewing archived db.
		$is_archive = false;
		if ( $this->_plugin->settings->IsArchivingEnabled() ) {
			$selected_db = get_transient( 'wsal_wp_selected_db' );
			if ( $selected_db && 'archive' === $selected_db ) {
				$is_archive = true;
			}
		}

		// Check for new total number of alerts.
		$occ = new WSAL_Models_Occurrence();
		$new = (int) $occ->Count();

		// If the current view is archive then don't refresh.
		if ( $is_archive ) {
			echo 'false';
		} else {
			// If the count is changed, then return the new count.
			echo $old === $new ? 'false' : esc_html( $new );
		}
		die;
	}

	/**
	 * Ajax callback to set number of alerts to
	 * show on a single page.
	 */
	public function AjaxSetIpp() {
		if ( ! $this->_plugin->settings->CurrentUserCan( 'view' ) ) {
			die( 'Access Denied.' );
		}

		// Filter $_POST array for security.
		$post_array = filter_input_array( INPUT_POST );

		if ( ! isset( $post_array['count'] ) ) {
			die( 'Count parameter expected.' );
		}
		$this->_plugin->settings->SetViewPerPage( (int) $post_array['count'] );
		die;
	}

	/**
	 * Ajax callback to search.
	 */
	public function AjaxSearchSite() {
		if ( ! $this->_plugin->settings->CurrentUserCan( 'view' ) ) {
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

		foreach ( $this->GetListView()->get_sites() as $site ) {
			if ( stripos( $site->blogname, $search ) !== false ) {
				$grp1[] = $site;
			} elseif ( stripos( $site->domain, $search ) !== false ) {
				$grp2[] = $site;
			}
		}
		die( json_encode( array_slice( $grp1 + $grp2, 0, 7 ) ) );
	}

	/**
	 * Ajax callback to switch database.
	 */
	public function AjaxSwitchDB() {
		// Filter $_POST array for security.
		$post_array = filter_input_array( INPUT_POST );

		if ( isset( $post_array['selected_db'] ) ) {
			set_transient( 'wsal_wp_selected_db', $post_array['selected_db'], HOUR_IN_SECONDS );
			set_transient( 'wsal_wp_selected_db_user', get_current_user_id(), HOUR_IN_SECONDS );
		}
	}

	/**
	 * Ajax callback to download failed login log.
	 */
	public function wsal_download_failed_login_log() {
		// Get post array through filter.
		$download_nonce = filter_input( INPUT_POST, 'download_nonce', FILTER_SANITIZE_STRING );
		$alert_id       = filter_input( INPUT_POST, 'alert_id', FILTER_SANITIZE_NUMBER_INT );

		// Verify nonce.
		if ( ! empty( $download_nonce ) && wp_verify_nonce( $download_nonce, 'wsal-download-failed-logins' ) ) {
			// Get alert by id.
			$alert     = new WSAL_Models_Occurrence();
			$alert->id = (int) $alert_id;

			// Get users using alert meta.
			$users = $alert->GetMetaValue( 'Users', array() );

			// Check if there are any users.
			if ( ! empty( $users ) && is_array( $users ) ) {
				// Prepare content.
				$content = implode( ',', $users );
				echo esc_html( $content );
			} else {
				echo esc_html__( 'No users found.', 'wp-security-audit-log' );
			}
		} else {
			echo esc_html__( 'Nonce verification failed.', 'wp-security-audit-log' );
		}
		die();
	}

	/**
	 * Ajax callback to download 404 log.
	 */
	public function wsal_download_404_log() {
		// Get post array through filter.
		$nonce    = filter_input( INPUT_POST, 'nonce', FILTER_SANITIZE_STRING );
		$filename = filter_input( INPUT_POST, 'log_file', FILTER_SANITIZE_STRING );

		// If file name is empty then return error.
		if ( empty( $filename ) ) {
			// Nonce verification failed.
			echo wp_json_encode(
				array(
					'success' => false,
					'message' => esc_html__( 'Log file does not exist.', 'wp-security-audit-log' ),
				)
			);
			die();
		}

		// Verify nonce.
		if ( ! empty( $filename ) && ! empty( $nonce ) && wp_verify_nonce( $nonce, 'wsal-download-404-log-' . $filename ) ) {
			$is_subsite = strpos( $filename, '/sites/' ); // Check for subsite on a multisite network.

			if ( ! $is_subsite ) {
				// Site is not a subsite on a multisite network.
				$uploads_dir = wp_upload_dir(); // Get uploads directory.
				$filename    = basename( $filename ); // Get basename to prevent path traversal attack.

				// Construct log file path to eliminate the risks of path traversal attack.
				$log_file_path = trailingslashit( $uploads_dir['basedir'] ) . 'wp-security-audit-log/404s/' . $filename;
			} else {
				// Site is a subsite on a multisite network.
				$filepath = substr( $filename, $is_subsite ); // Get the chunk of string from `/sites/` to find the site id.

				// Get basename to prevent path traversal attack.
				$filename = basename( $filename );

				// Search for site id in the file path by replacing the remaining known chunks such as `/sites/` and the path after site id.
				$site_id = str_replace( array( '/sites/', '/wp-security-audit-log/404s/' . $filename ), '', $filepath );

				// Construct log file path to eliminate the risks of path traversal attack.
				$log_file_path = trailingslashit( WP_CONTENT_DIR ) . 'uploads/sites/' . $site_id . '/wp-security-audit-log/404s/' . $filename;
			}

			// Request the file.
			$response = file_get_contents( $log_file_path, true );

			// Check if the response is valid.
			if ( $response ) {
				// Return the file body.
				echo wp_json_encode(
					array(
						'success'      => true,
						'filename'     => $filename,
						'file_content' => $response,
					)
				);
			} else {
				// Request failed.
				echo wp_json_encode(
					array(
						'success' => false,
						'message' => esc_html__( 'Request to get log file failed.', 'wp-security-audit-log' ),
					)
				);
			}
		} else {
			// Nonce verification failed.
			echo wp_json_encode(
				array(
					'success' => false,
					'message' => esc_html__( 'Nonce verification failed.', 'wp-security-audit-log' ),
				)
			);
		}
		die();
	}

	/**
	 * Ajax callback to handle freemius opt in/out.
	 */
	public function wsal_freemius_opt_in() {
		// Die if not have access.
		if ( ! $this->_plugin->settings->CurrentUserCan( 'view' ) ) {
			die( 'Access Denied.' );
		}

		// Get post array through filter.
		$nonce  = filter_input( INPUT_POST, 'opt_nonce', FILTER_SANITIZE_STRING ); // Nonce.
		$choice = filter_input( INPUT_POST, 'choice', FILTER_SANITIZE_STRING ); // Choice selected by user.

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
				if ( ! is_multisite() ) {
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

				// Update freemius state.
				update_site_option( 'wsal_freemius_state', 'in' );
			} elseif ( 'no' === $choice ) {
				if ( ! is_multisite() ) {
					wsal_freemius()->skip_connection(); // Opt out.
				} else {
					wsal_freemius()->skip_connection( null, true ); // Opt out for all websites.
				}

				// Update freemius state.
				update_site_option( 'wsal_freemius_state', 'skipped' );
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
	public function Header() {
		add_thickbox();

		// Darktooltip styles.
		wp_enqueue_style(
			'darktooltip',
			$this->_plugin->GetBaseUrl() . '/css/darktooltip.css',
			array(),
			'0.4.0'
		);

		// Remodal styles.
		wp_enqueue_style( 'wsal-remodal', $this->_plugin->GetBaseUrl() . '/css/remodal.css', array(), '1.1.1' );
		wp_enqueue_style( 'wsal-remodal-theme', $this->_plugin->GetBaseUrl() . '/css/remodal-default-theme.css', array(), '1.1.1.1' );

		// Audit log styles.
		wp_enqueue_style(
			'auditlog',
			$this->_plugin->GetBaseUrl() . '/css/auditlog.css',
			array(),
			filemtime( $this->_plugin->GetBaseDir() . '/css/auditlog.css' )
		);
	}

	/**
	 * Method: Render footer of the view.
	 */
	public function Footer() {
		wp_enqueue_script( 'jquery' );

		// Darktooltip js.
		wp_enqueue_script(
			'darktooltip', // Identifier.
			$this->_plugin->GetBaseUrl() . '/js/jquery.darktooltip.js', // Script location.
			array( 'jquery' ), // Depends on jQuery.
			'0.4.0', // Script version.
			true
		);

		// Remodal script.
		wp_enqueue_script(
			'wsal-remodal-js',
			$this->_plugin->GetBaseUrl() . '/js/remodal.min.js',
			array(),
			'1.1.1',
			true
		);

		// WP Suggest Script.
		wp_enqueue_script( 'suggest' );

		// Audit log script.
		wp_register_script(
			'auditlog',
			$this->_plugin->GetBaseUrl() . '/js/auditlog.js',
			array(),
			filemtime( $this->_plugin->GetBaseDir() . '/js/auditlog.js' ),
			true
		);
		$audit_log_data = array(
			'page'           => isset( $this->page_args->page ) ? $this->page_args->page : false,
			'siteId'         => isset( $this->page_args->site_id ) ? $this->page_args->site_id : false,
			'orderBy'        => isset( $this->page_args->order_by ) ? $this->page_args->order_by : false,
			'order'          => isset( $this->page_args->order ) ? $this->page_args->order : false,
			'searchTerm'     => isset( $this->page_args->search_term ) ? $this->page_args->search_term : false,
			'searchFilters'  => isset( $this->page_args->search_filters ) ? $this->page_args->search_filters : false,
			'viewerNonce'    => wp_create_nonce( 'wsal_auditlog_viewer_nonce' ),
			'infiniteScroll' => $this->_plugin->settings->is_infinite_scroll(),
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
			'no' === $this->_plugin->GetGlobalOption( 'wsal-setup-complete', 'no' )
			&& 'no' === $this->_plugin->GetGlobalOption( 'wsal-setup-modal-dismissed', 'no' )
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
		$dismissed      = explode( ',', (string) $this->_plugin->GetGlobalOption( 'dismissed-privacy-notice', true ) );
		$valid_pointers = array();

		// Check pointers and remove dismissed ones.
		foreach ( $pointers as $pointer_id => $pointer ) {
			// Sanity check.
			if (
				in_array( $pointer_id, $dismissed )
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
			$this->_plugin->GetBaseUrl() . '/js/auditlog-pointer.js',
			array( 'wp-pointer' ),
			filemtime( $this->_plugin->GetBaseDir() . '/js/auditlog-pointer.js' ),
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
		$is_current_view = $this->_plugin->views->GetActiveView() == $this;
		if ( current_user_can( 'manage_options' ) && $is_current_view && ! isset( $pointer['wsal_privacy'] ) ) {
			$pointer['wsal_privacy'] = array(
				'target'  => '#toplevel_page_wsal-auditlog .wp-first-item',
				'options' => array(
					'content'  => sprintf(
						'<h3> %s </h3> <p> %s </p> <p><strong>%s</strong></p>',
						__( 'WordPress Activity Log', 'wp-security-audit-log' ),
						__( 'When a user makes a change on your website the plugin will keep a record of that event here. Right now there is nothing because this is a new install.', 'wp-security-audit-log' ),
						__( 'Thank you for using WP Security Audit Log', 'wp-security-audit-log' )
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
	 * Method: Ajax request handler to exclude URL from
	 * the event.
	 *
	 * @since 3.2.2
	 */
	public function wsal_exclude_url() {
		// Die if user does not have permission to disable.
		if ( ! $this->_plugin->settings->CurrentUserCan( 'edit' ) ) {
			echo '<p>' . esc_html__( 'Error: You do not have sufficient permissions to exclude this URL.', 'wp-security-audit-log' ) . '</p>';
			die();
		}

		// Set filter input args.
		$filter_input_args = array(
			'nonce' => FILTER_SANITIZE_STRING,
			'url'   => FILTER_SANITIZE_STRING,
		);

		// Filter $_POST array for security.
		$post_array = filter_input_array( INPUT_POST, $filter_input_args );

		if ( isset( $post_array['nonce'] ) && ! wp_verify_nonce( $post_array['nonce'], 'wsal-exclude-url-' . $post_array['url'] ) ) {
			die();
		}

		$excluded_urls = $this->_plugin->GetGlobalOption( 'excluded-urls' );
		if ( isset( $excluded_urls ) && '' !== $excluded_urls ) {
			$excluded_urls .= ',' . esc_url( $post_array['url'] );
		} else {
			$excluded_urls = esc_url( $post_array['url'] );
		}
		$this->_plugin->SetGlobalOption( 'excluded-urls', $excluded_urls );
		$settings_exclude_url = add_query_arg(
			array(
				'page' => 'wsal-settings',
				'tab'  => 'exclude-objects',
			),
			admin_url( 'admin.php' )
		);
		echo '<p>URL ' . esc_html( $post_array['url'] ) . ' is no longer being monitored.<br />Enable the monitoring of this URL again from the <a href="' . esc_url( $settings_exclude_url ) . '">Excluded Objects</a> tab in the plugin settings.</p>';
		die;
	}

	/**
	 * Method: Ajax request handler to dismiss adverts.
	 *
	 * @since 3.2.4
	 */
	public function wsal_dismiss_advert() {
		// Die if user does not have permission to dismiss.
		if ( ! $this->_plugin->settings->CurrentUserCan( 'edit' ) ) {
			echo wp_json_encode(
				array(
					'success' => false,
					'message' => esc_html__( 'You do not have sufficient permissions to dismiss this notice.', 'wp-security-audit-log' ),
				)
			);
			die();
		}

		// Filter $_POST array for security.
		// @codingStandardsIgnoreStart
		$nonce  = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : false;
		$advert = isset( $_POST['advert'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['advert'] ) ) : false;
		// @codingStandardsIgnoreEnd

		if ( ! empty( $nonce ) && ! wp_verify_nonce( $nonce, 'wsal_dismiss_advert' ) ) {
			// Nonce verification failed.
			echo wp_json_encode(
				array(
					'success' => false,
					'message' => esc_html__( 'Nonce verification failed.', 'wp-security-audit-log' ),
				)
			);
			die();
		}

		$advert = 2 === $advert ? '0' : $advert + 1;
		$this->_plugin->SetGlobalOption( 'premium-advert', $advert );
		$set_transient_fn = $this->_plugin->IsMultisite() ? 'set_site_transient' : 'set_transient';
		$set_transient_fn( 'wsal-is-advert-dismissed', true, MONTH_IN_SECONDS );
		echo wp_json_encode(
			array(
				'success' => true,
			)
		);
		die();
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

		if ( $pointer != sanitize_key( $pointer ) ) {
			wp_die( 0 );
		}

		$dismissed = array_filter( explode( ',', (string) $this->_plugin->GetGlobalOption( 'dismissed-privacy-notice', true ) ) );

		if ( in_array( $pointer, $dismissed ) ) {
			wp_die( 0 );
		}

		$dismissed[] = $pointer;
		$dismissed   = implode( ',', $dismissed );

		$this->_plugin->SetGlobalOption( 'dismissed-privacy-notice', $dismissed );
		wp_die( 1 );
	}

	/**
	 * Infinite Scroll Events AJAX Hanlder.
	 *
	 * @since 3.3.1.1
	 */
	public function infinite_scroll_events() {
		// Check user permissions.
		if ( ! $this->_plugin->settings->CurrentUserCan( 'view' ) ) {
			die( esc_html__( 'Access Denied', 'wp-security-audit-log' ) );
		}

		// Verify nonce.
		if ( isset( $_POST['wsal_viewer_security'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wsal_viewer_security'] ) ), 'wsal_auditlog_viewer_nonce' ) ) {
			// Get $_POST arguments.
			$paged = isset( $_POST['page_number'] ) ? sanitize_text_field( wp_unslash( $_POST['page_number'] ) ) : 0;

			// Query events.
			$events_query = $this->GetListView()->query_events( $paged );
			if ( ! empty( $events_query['items'] ) ) {
				foreach ( $events_query['items'] as $event ) {
					$this->GetListView()->single_row( $event );
				}
			}
			exit();
		} else {
			die( esc_html__( 'Nonce verification failed.', 'wp-security-audit-log' ) );
		}
	}
}
