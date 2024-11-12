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
use WSAL\Writers\CSV_Writer;
use WSAL\Controllers\Connection;
use WSAL\Helpers\Settings_Helper;
use WSAL\Entities\Occurrences_Entity;
use WSAL\Helpers\View_Manager;
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
	 * @since 5.0.0 - It holds array
	 *
	 * @var array
	 */
	private static $page_args = null;

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
	public function __construct() {
		add_action( 'wp_ajax_AjaxInspector', array( $this, 'AjaxInspector' ) );
		add_action( 'wp_ajax_AjaxSearchSite', array( $this, 'ajax_search_site' ) );
		add_action( 'wp_ajax_AjaxSwitchDB', array( $this, 'ajax_switch_db' ) );
		add_action( 'wp_ajax_wsal_download_failed_login_log', array( $this, 'wsal_download_failed_login_log' ) );
		add_action( 'wp_ajax_wsal_freemius_opt_in', array( $this, 'wsal_freemius_opt_in' ) );
		add_action( 'wp_ajax_wsal_dismiss_setup_modal', array( __CLASS__, 'dismiss_setup_modal' ) );
		// add_action( 'wp_ajax_wsal_dismiss_notice_addon_available', array( $this, 'dismiss_notice_addon_available' ) );
		add_action( 'wp_ajax_wsal_dismiss_missing_aws_sdk_nudge', array( $this, 'dismiss_missing_aws_sdk_nudge' ) );
		add_action( 'wp_ajax_wsal_dismiss_helper_plugin_needed_nudge', array( $this, 'dismiss_helper_plugin_needed_nudge' ) );
		add_action( 'wp_ajax_wsal_dismiss_wp_pointer', array( __CLASS__, 'dismiss_wp_pointer' ) );

		add_action( 'all_admin_notices', array( '\WSAL\Helpers\Notices', 'init' ) );

		add_action( 'all_admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'load_pointers' ), 1000 );
		add_filter( 'wsal_pointers_toplevel_page_wsal-auditlog', array( $this, 'register_privacy_pointer' ), 10, 1 );
		add_action( 'admin_init', array( $this, 'handle_form_submission' ) );

		add_action( 'wp_ajax_wsal_export_csv_results', array( '\WSAL\Writers\CSV_Writer', 'write_csv_ajax' ) );

		CSV_Writer::init();

		add_filter( 'manage_toplevel_page_wsal-auditlog_columns', array( '\WSAL\ListAdminEvents\List_Events', 'manage_columns' ) );

		if ( WP_Helper::is_multisite() ) {
			add_filter( 'manage_toplevel_page_wsal-auditlog-network_columns', array( '\WSAL\ListAdminEvents\List_Events', 'manage_columns' ) );
		}

		// Check plugin version for to dismiss the notice only until upgrade.
		$this->version = WSAL_VERSION;

		// Set adverts array.
		$this->adverts = array(
			// 0 => array(
			// 	'head' => esc_html__( 'Upgrade to Premium and enable search filters so you can find the events you need within seconds, get notified via email or SMS about critical website changes, see who is logged-in to your website in real time, manage user sessions, create detailed reports, and much more!', 'wp-security-audit-log' ),
			// 	'desc' => esc_html__( '', 'wp-security-audit-log' ),
			// ),
			1 => array(
				'head' => esc_html__( 'Instant SMS & email alerts, search & filters, reports, users sessions management and much more!', 'wp-security-audit-log' ),
				'desc' => esc_html__( 'Upgrade to premium to get more out of your activity logs!', 'wp-security-audit-log' ),
			),
			2 => array(
				'head' => esc_html__( 'See who logged in on your site in real-time, generate reports, get SMS & email alerts of critical changes and more!', 'wp-security-audit-log' ),
				'desc' => esc_html__( 'Unlock these and other powerful features with WP Activity Log Premium.', 'wp-security-audit-log' ),
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
	public function admin_notices() {
		$is_current_view = View_Manager::get_active_view() == $this; // phpcs:ignore

		// Check if any of the extensions are activated.
		if (
			! class_exists( 'WSAL_NP_Plugin' )
			&& ! class_exists( 'WSAL_Ext_Plugin' )
			&& ! class_exists( 'WSAL_SearchExtension' )
			&& ! class_exists( 'WSAL_UserSessions_Plugin' )
			&& ( 'anonymous' === \WSAL\Helpers\Settings_Helper::get_option_value( 'freemius_state', 'anonymous' ) || // Anonymous mode option.
			'skipped' === \WSAL\Helpers\Settings_Helper::get_option_value( 'freemius_state', 'anonymous' ) )
		) {
			$wsal_premium_advert = \WSAL\Helpers\Settings_Helper::get_option_value( 'premium-advert', false ); // Get the advert to display.
			$wsal_premium_advert = false !== $wsal_premium_advert ? (int) $wsal_premium_advert : 0; // Set the default.

			$more_info = add_query_arg(
				array(
					'utm_source'   => 'plugin',
					'utm_medium'   => 'banner',
					'utm_campaign' => 'wsal',
					'utm_content'  => 'tell+me+more',
				),
				'https://melapress.com/features/'
			);

			if ( current_user_can( 'manage_options' ) && $is_current_view ) : 

				/* ?>
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
									'utm_source'   => 'plugin',
									'utm_medium'   => 'banner',
									'utm_campaign' => 'wsal',
								),
								'https://melapress.com/wordpress-activity-log/pricing/'
							);

							$buy_now = add_query_arg(
								array(
									'utm_source'   => 'plugin',
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
				<?php */
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
		return esc_html__( 'Activity Log Viewer', 'wp-security-audit-log' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_icon() {
		return self::get_icon_encoded();
	}

	/**
	 * Returns an encoded SVG string for the menu icon.
	 *
	 * @return string
	 */
	private static function get_icon_encoded() {
		return 'data:image/svg+xml;base64,PHN2ZyBpZD0iTGF5ZXJfMiIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB2aWV3Qm94PSIwIDAgNjUuNzIgNjUuNzIiPjxkZWZzPjxzdHlsZT4uY2xzLTF7ZmlsbDojZmZmO308L3N0eWxlPjwvZGVmcz48ZyBpZD0iTGF5ZXJfMS0yIj48cG9seWdvbiBjbGFzcz0iY2xzLTEiIHBvaW50cz0iNjUuNzIgNjUuNzIgNjUuNzIgNTEuNDEgMzIuODYgNjUuNzIgNjUuNzIgNjUuNzIiLz48cG9seWdvbiBjbGFzcz0iY2xzLTEiIHBvaW50cz0iMCA2NS43MiAzMi44NiA2NS43MiAwIDUxLjQxIDAgNjUuNzIiLz48cGF0aCBjbGFzcz0iY2xzLTEiIGQ9Im0zMi44NiwxMC4wN0MxNC43MSwxMC4wNywwLDMyLjg2LDAsMzIuODZjMCwwLDE0LjcxLDIyLjc5LDMyLjg2LDIyLjc5czMyLjg2LTIyLjc5LDMyLjg2LTIyLjc5YzAsMC0xNC43MS0yMi43OS0zMi44Ni0yMi43OVptMCwzNy44N2MtOC4zMSwwLTE1LjA1LTYuNzQtMTUuMDUtMTUuMDUsMC0uMTMuMDItLjI1LjAyLS4zOC42Ni4xOSwxLjMzLjMyLDIuMDUuMzIsNC4xNiwwLDcuNTMtMy4zNyw3LjUzLTcuNTMsMC0yLjA5LS44NS0zLjk4LTIuMjMtNS4zNCwyLjI1LTEuMzQsNC44Ny0yLjEyLDcuNjgtMi4xMiw4LjMxLDAsMTUuMDUsNi43NCwxNS4wNSwxNS4wNXMtNi43NCwxNS4wNS0xNS4wNSwxNS4wNVoiLz48cG9seWdvbiBjbGFzcz0iY2xzLTEiIHBvaW50cz0iMCAxNC4zMSAzMi44NiAwIDAgMCAwIDE0LjMxIi8+PHBvbHlnb24gY2xhc3M9ImNscy0xIiBwb2ludHM9IjMyLjg2IDAgNjUuNzIgMTQuMzEgNjUuNzIgMCAzMi44NiAwIi8+PC9nPjwvc3ZnPg==';
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

	public static function get_page_arguments(): array {
		if ( null === self::$page_args ) {

			self::$page_args = array();

			self::$page_args['page']    = isset( $_REQUEST['page'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ) : false;
			self::$page_args['site_id'] = WP_Helper::get_view_site_id();

			self::$page_args['site_id'] = apply_filters( 'wsal_main_view_site_id', self::$page_args['site_id'] );

			// Order arguments.
			self::$page_args['order_by'] = isset( $_REQUEST['orderby'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) ) : false;
			self::$page_args['order']    = isset( $_REQUEST['order'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) : false;

			// Search arguments.
			self::$page_args['search_term']    = ( isset( $_REQUEST['s'] ) && ! empty( $_REQUEST['s'] ) ) ? trim( sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) ) : false;
			self::$page_args['search_filters'] = ( isset( $_REQUEST['filters'] ) && is_array( $_REQUEST['filters'] ) ) ? array_map( 'sanitize_text_field', wp_unslash( $_REQUEST['filters'] ) ) : false;

		}

		return self::$page_args;
	}

	/**
	 * Method: Get View.
	 */
	protected function get_view() {

		// Set events listing view class.
		if ( is_null( $this->view ) ) {

			$this->view = new List_Events( self::get_page_arguments() );
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
		);
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
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : false;
		if ( 'admin.php' !== $pagenow ) {
			return;
		}

		if ( ! in_array( $page, ['wsal-auditlog', 'Extensions-Wp-Security-Audit-Log-Premium', 'Extensions-Wp-Security-Audit-Log'] ) ) { // Page is admin.php, now check auditlog page.
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

			if ( false === $site_id ) {
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
		$view_input_value = self::$default_view;
		?>
		<form id="audit-log-viewer" method="get">
			<div id="audit-log-viewer-content">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::get_page_arguments()['page'] ); ?>" />
				<input type="hidden" id="wsal-cbid" name="wsal-cbid" value="<?php echo esc_attr( empty( self::get_page_arguments()['site_id'] ) ? '-1' : self::get_page_arguments()['site_id'] ); ?>" />
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

				/**
				 * Action: `wsal_search_filters_list`
				 *
				 * Display list of search filters of WSAL.
				 *
				 * @param string $which – Navigation position; value is either top or bottom.
				 * @since 3.2.3
				 */
				do_action( 'wsal_search_filters_list', 'top' );


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

		$is_search_view = class_exists( 'WSAL_SearchExtension' ) && ( ! empty( self::get_page_arguments()['search_filters'] ) || ! empty( self::get_page_arguments()['search_term'] ) );
		?>
		<script type="text/javascript">
			jQuery( document ).ready( function() {
				window['WsalAuditLogRefreshed']();
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
				echo '<strong>' . $item . ':</strong> <span style="opacity: 0.7;"><pre style="display:inline">' . $value . '</pre></span></br>';
			}
		}
		echo '</div>';
		wp_die();
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

		foreach ( WP_Helper::get_sites() as $site ) {
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
	public static function header() {
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
	public static function footer() {
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
			'page'                 => isset( self::get_page_arguments()['page'] ) ? self::get_page_arguments()['page']: false,
			'siteId'               => isset( self::get_page_arguments()['site_id'] ) ? self::get_page_arguments()['site_id'] : false,
			'orderBy'              => isset( self::get_page_arguments()['order_by'] ) ? self::get_page_arguments()['order_by'] : false,
			'order'                => isset( self::get_page_arguments()['order'] ) ? self::get_page_arguments()['order'] : false,
			'searchTerm'           => isset( self::get_page_arguments()['search_term'] ) ? self::get_page_arguments()['search_term'] : false,
			'searchFilters'        => isset( self::get_page_arguments()['search_filters'] ) ? self::get_page_arguments()['search_filters'] : false,
			'closeInspectorString' => esc_html__( 'Close inspector', 'wp-security-audit-log' ),
			'viewerNonce'          => wp_create_nonce( 'wsal_auditlog_viewer_nonce' ),
			'installAddonStrings'  => array(
				'defaultButton'    => esc_html__( 'Install and activate extension', 'wp-security-audit-log' ),
				'installingText'   => esc_html__( 'Installing extension', 'wp-security-audit-log' ),
				'otherInstalling'  => esc_html__( 'Other extension installing', 'wp-security-audit-log' ),
				'addonInstalled'   => esc_html__( 'Installed', 'wp-security-audit-log' ),
				'installedReload'  => esc_html__( 'Installed... reloading page', 'wp-security-audit-log' ),
				'buttonError'      => esc_html__( 'Problem enabling', 'wp-security-audit-log' ),
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
	public static function load_pointers( $hook_suffix ) {
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
		$is_current_view = View_Manager::get_active_view() == $this; // phpcs:ignore
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
	public static function dismiss_wp_pointer() {
		if ( isset( $_POST['pointer'] ) ) {
			$pointer = sanitize_text_field( wp_unslash( $_POST['pointer'] ) );

			if ( sanitize_key( $pointer ) !== $pointer ) {
				wp_die( 0 );
			}

			$dismissed = array_filter( explode( ',', (string) \WSAL\Helpers\Settings_Helper::get_option_value( 'dismissed-privacy-notice', true ) ) );

			if ( in_array( $pointer, $dismissed, true ) ) {
				wp_die( 0 );
			}

			$dismissed[] = $pointer;
			$dismissed   = implode( ',', $dismissed );

			\WSAL\Helpers\Settings_Helper::set_option_value( 'dismissed-privacy-notice', $dismissed );
			wp_die( 1 );
		}
	}

	/**
	 * Method: Ajax request handler to dismiss setup modal.
	 *
	 * @since 4.1.4
	 */
	public static function dismiss_setup_modal() {
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
		$nonce = isset( $_POST['nonce'] ) ? \sanitize_text_field( \wp_unslash( $_POST['nonce'] ) ) : false;

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
