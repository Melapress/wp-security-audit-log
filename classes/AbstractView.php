<?php
/**
 * Abstract view class file.
 *
 * @package    wsal
 * @subpackage views
 */

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
	 * WordPress version.
	 *
	 * @var string
	 */
	protected $wp_version;

	/**
	 * Contains the result to a call to add_submenu_page().
	 *
	 * @var string
	 */
	public $hook_suffix = '';

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

		// Get and store WordPress version.
		global $wp_version;
		if ( ! isset( $wp_version ) ) {
			$wp_version = get_bloginfo( 'version' ); // phpcs:ignore
		}
		$this->wp_version = floatval( $wp_version );

		// Handle admin notices.
		add_action( 'wp_ajax_AjaxDismissNotice', array( $this, 'ajax_dismiss_notice' ) );
	}

	/**
	 * Dismiss an admin notice through ajax.
	 *
	 * @internal
	 */
	public function ajax_dismiss_notice() {
		if ( ! $this->plugin->settings()->current_user_can( 'view' ) ) {
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
	 * Renders the view icon (this has been deprecated in newwer WP versions).
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
	 * Used for rendering stuff into head tag.
	 */
	public function header() {}

	/**
	 * Used for rendering stuff in page footer.
	 */
	public function footer() {}

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

}
