<?php
/**
 * Abstract view class file.
 *
 * @package    wsal
 * @subpackage views
 */

use WSAL\Helpers\Settings_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract class used in all the views.
 *
 * @see        Views/*.php
 * @package    wsal
 * @subpackage views
 */
abstract class WSAL_AbstractView {

	/**
	 * Instance of WpSecurityAuditLog.
	 *
	 * @var WpSecurityAuditLog
	 */
	protected $plugin;

	/**
	 * Pointer to the hook suffix
	 *
	 * @var string
	 *
	 * @since 5.0.0
	 */
	private static $hook_suffix = null;

	/**
	 * Tells us whether this view is currently being displayed or not.
	 *
	 * @var boolean
	 */
	public $is_active = false;

	/**
	 * Allowed notice names.
	 *
	 * @var array
	 */
	public static $allowed_notice_names = array();

	/**
	 * Method: Constructor.
	 *
	 * @param WpSecurityAuditLog $plugin - Instance of WpSecurityAuditLog.
	 */
	public function __construct( WpSecurityAuditLog $plugin ) {
		$this->plugin = $plugin;

		// Handle admin notices.
		add_action( 'wp_ajax_AjaxDismissNotice', array( $this, 'ajax_dismiss_notice' ) );

		\add_action( 'in_admin_footer', array( __CLASS__, 'in_admin_footer' ) );
		\add_action( 'in_admin_header', array( __CLASS__, 'in_admin_header' ) );
		\add_filter( 'admin_footer_text', array( __CLASS__, 'admin_footer_text' ) );
		\add_filter( 'update_footer', array( __CLASS__, 'admin_footer_version_text' ), PHP_INT_MAX );
	}

	/**
	 * Dismiss an admin notice through ajax.
	 *
	 * @internal
	 */
	public function ajax_dismiss_notice() {
		if ( ! Settings_Helper::current_user_can( 'view' ) ) {
			die( 'Access Denied.' );
		}

		// Filter $_POST array for security.
		$post_array = filter_input_array( INPUT_POST );

		if ( ! isset( $post_array['notice'] ) ) {
			die( 'Notice name expected as "notice" parameter.' );
		}

		$this->dismiss_notice( $post_array['notice'] );
	}

	/**
	 * Method: Check if notice is dismissed.
	 *
	 * @param string $name — Name of notice.
	 * @return boolean — Whether notice got dismissed or not.
	 */
	public function is_notice_dismissed( $name ) {
		$user_id  = get_current_user_id();
		$meta_key = 'wsal-notice-' . $name;

		self::$allowed_notice_names[] = $name;
		return get_user_meta( $user_id, $meta_key, true );
	}

	/**
	 * Method: Dismiss notice.
	 *
	 * @param string $name — Name of notice to dismiss.
	 */
	public function dismiss_notice( $name ) {
		$user_id   = get_current_user_id();
		$meta_key  = 'wsal-notice-' . $name;
		$old_value = get_user_meta( $user_id, $meta_key, true );
		if ( in_array( $name, self::$allowed_notice_names ) || false === $old_value || empty( $old_value ) ) { // phpcs:ignore
			update_user_meta( $user_id, $meta_key, '1' );
		}
	}

	/**
	 * Method: Register notice.
	 *
	 * @param string $name — Makes this notice available.
	 */
	public function register_notice( $name ) {
		self::$allowed_notice_names[] = $name;
	}

	/**
	 * Method: Return page name (for menu etc).
	 *
	 * @return string
	 */
	abstract public function get_name();

	/**
	 * Method: Return page title.
	 *
	 * @return string
	 */
	abstract public function get_title();

	/**
	 * Method: Page icon name.
	 *
	 * @return string
	 */
	abstract public function get_icon();

	/**
	 * Method: Menu weight, the higher this is, the lower it goes.
	 *
	 * @return int
	 */
	abstract public function get_weight();

	/**
	 * Renders and outputs the view directly.
	 */
	abstract public function render();

	/**
	 * Renders the view icon (this has been deprecated in newer WP versions).
	 */
	public function render_icon() {
		?>
		<div id="icon-plugins" class="icon32"><br></div>
		<?php
	}

	/**
	 * Renders the view title.
	 */
	public function render_title() {
		if ( $this->is_title_visible() ) {
			echo '<h2>' . esc_html( $this->get_title() ) . '</h2>';
		}
	}

	/**
	 * Method: Render content of the view.
	 *
	 * @see WSAL_AbstractView::render()
	 */
	public function render_content() {
		$this->render();
	}

	/**
	 * Method: Whether page should appear in menu or not.
	 *
	 * @return boolean
	 */
	public function is_visible() {
		return true;
	}

	/**
	 * Method: Whether page should be accessible or not.
	 *
	 * @return boolean
	 */
	public function is_accessible() {
		return true;
	}

	/**
	 * Check if the page title is visible.
	 *
	 * @return boolean
	 */
	public function is_title_visible() {
		return true;
	}

	/**
	 * Method: Safe view menu name.
	 *
	 * @return string
	 */
	public function get_safe_view_name() {
		return 'wsal-' . preg_replace( '/[^A-Za-z0-9\-]/', '-', $this->get_view_name() );
	}

	/**
	 * Override this and make it return true to create a shortcut link in plugin page to the view.
	 *
	 * @return boolean
	 */
	public function has_plugin_shortcut_link() {
		return false;
	}

	/**
	 * Method: URL to backend page for displaying view.
	 *
	 * @return string
	 */
	public function get_url() {
		$fn = function_exists( 'network_admin_url' ) ? 'network_admin_url' : 'admin_url';
		return $fn( 'admin.php?page=' . $this->get_safe_view_name() );
	}

	/**
	 * Method: Generates view name out of class name.
	 *
	 * @return string
	 */
	public function get_view_name() {
		return strtolower( str_replace( array( 'WSAL_Views_', 'WSAL_' ), '', get_class( $this ) ) );
	}

	public static function set_hook_suffix( $suffix ) {
		self::$hook_suffix = $suffix;
	}

	public static function get_hook_suffix() {
		return self::$hook_suffix;
	}

	/**
	 * Puts the navigation in the admin header.
	 *
	 * @return void
	 *
	 * @since 5.1.1
	 */
	public static function in_admin_header() {

		global $current_screen;

		if ( isset( $current_screen ) && ( in_array( $current_screen->base, WpSecurityAuditLog::get_plugin_screens_array(), true ) ) ) {
			if ( 'free' === WpSecurityAuditLog::get_plugin_version() ) {
				include_once WSAL_BASE_DIR . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'Free' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'nav-bar.php';
			}
		}
	}


	/**
	 * Modifies the admin footer.
	 *
	 *
	 * @since 5.1.1
	 */
	public static function in_admin_footer() {

		global $current_screen;

		if ( isset( $current_screen ) && ( in_array( $current_screen->base, WpSecurityAuditLog::get_plugin_screens_array(), true ) ) ) {
			
			if ( 'free' === WpSecurityAuditLog::get_plugin_version() ) {
				
				include_once WSAL_BASE_DIR . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'Free' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'upgrade-to-premium.php';

			}

		}

	}

	/**
	 * Modifies the admin footer text.
	 *
	 * @param   string $text The current admin footer text.
	 * @return  string
	 *
	 * @since 5.1.1
	 */
	public static function admin_footer_text( $text ) {

		global $current_screen;

		if ( isset( $current_screen ) && ( in_array( $current_screen->base, WpSecurityAuditLog::get_plugin_screens_array(), true ) ) ) {
			$our_footer = '';
			// if ( 'free' === WpSecurityAuditLog::get_plugin_version() ) {
			// 	\ob_start();
			// 	include_once WSAL_BASE_DIR . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'Free' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'upgrade-to-premium.php';

			// 	$our_footer = \ob_get_clean();
			// }

			$wsal_footer_link = 'https://melapress.com/?utm_source=plugin&utm_medium=referral&utm_campaign=wsal&utm_content=footer';
			$wsal_link        = 'https://melapress.com/wordpress-activity-log/?utm_source=plugin&utm_medium=link&utm_campaign=wsal';

			return $our_footer . \sprintf(
			/* translators: This text is prepended by a link to Melapress's website, and appended by a link to Melapress's website. */
				'<a href="%1$s" target="_blank">' . ( 'free' === WpSecurityAuditLog::get_plugin_version() ? 'WP Activity Log' : 'WP Activity Log Premium' ) . '</a> ' . __( 'is developed and maintained by', 'wp-security-audit-log' ) . ' <a href="%2$s" target="_blank">Melapress</a>.',
				$wsal_link,
				$wsal_footer_link
			);
		}

		return $text;
	}

	/**
	 * Modifies the admin footer version text.
	 *
	 * @param   string $text The current admin footer version text.
	 * @return  string
	 *
	 * @since 5.1.1
	 */
	public static function admin_footer_version_text( $text ) {

		global $current_screen;

		if ( isset( $current_screen ) && ( in_array( $current_screen->base, WpSecurityAuditLog::get_plugin_screens_array(), true ) ) ) {
			$documentation_link = 'https://melapress.com/support/kb/?utm_source=plugin&utm_medium=link&utm_campaign=wsal';
			$support_link       = 'https://melapress.com/support/?utm_source=plugin&utm_medium=link&utm_campaign=wsal';
			$feedback_link      = 'https://melapress.com/contact/?utm_source=plugin&utm_medium=link&utm_campaign=wsal';
			$version_link       = 'https://melapress.com/support/kb/wp-activity-log-plugin-changelog/?utm_source=plugin&utm_medium=link&utm_campaign=wsal';

			return sprintf(
				'<a href="%s" target="_blank">%s</a> &#8729; <a href="%s" target="_blank">%s</a> &#8729; <a href="%s" target="_blank">%s</a> &#8729; <a href="%s" target="_blank">%s %s</a>',
				$documentation_link,
				__( 'Documentation', 'wp-security-audit-log' ),
				$support_link,
				__( 'Support', 'wp-security-audit-log' ),
				$feedback_link,
				__( 'Feedback', 'wp-security-audit-log' ),
				$version_link,
				__( 'Version ', 'wp-security-audit-log' ),
				WSAL_VERSION
			);
		}

		return $text;
	}
}
