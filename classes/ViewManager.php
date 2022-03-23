<?php
/**
 * Manager: View
 *
 * View manager class file.
 *
 * @since   1.0.0
 * @package wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * View Manager.
 *
 * This class includes all the views, initialize them and shows the active one.
 * It creates also the menu items.
 *
 * @package wsal
 */
class WSAL_ViewManager {

	/**
	 * Array of views.
	 *
	 * @var WSAL_AbstractView[]
	 */
	public $views = array();

	/**
	 * Instance of WpSecurityAuditLog.
	 *
	 * @var object
	 */
	protected $plugin;

	/**
	 * Active view.
	 *
	 * @var WSAL_AbstractView|null
	 */
	protected $active_view = false;

	/**
	 * Method: Constructor.
	 *
	 * @param WpSecurityAuditLog $plugin - Instance of WpSecurityAuditLog.
	 *
	 * @since  1.0.0
	 */
	public function __construct( WpSecurityAuditLog $plugin ) {
		$this->plugin = $plugin;

		// Skipped views array.
		$skip_views = array();


		/**
		 * Add setup wizard page to skip views. It will only be initialized
		 * one time.
		 *
		 * @since 3.2.3
		 */
		if ( file_exists( $this->plugin->get_base_dir() . 'classes/Views/SetupWizard.php' ) ) {
			$skip_views[] = $this->plugin->get_base_dir() . 'classes/Views/SetupWizard.php';
		}

		/**
		 * Removed in version 4.0.3 however some upgrade methods result in the
		 * file being left behind and `AddFromFile()` tries to load it as a
		 * class resulting in a fatal error because of it not existing.
		 *
		 * @since 4.0.4
		 */
		$skip_views[] = $this->plugin->get_base_dir() . 'classes/Views/Licensing.php';

		/**
		 * Skipped Views.
		 *
		 * Array of views which are skipped before plugin views are initialized.
		 *
		 * @param array $skip_views - Skipped views.
		 * @since 3.2.3
		 */
		$skip_views = apply_filters( 'wsal_skip_views', $skip_views );

		// Load views.
		foreach ( WSAL_Utilities_FileSystemUtils::read_files_in_folder( $this->plugin->get_base_dir() . 'classes/Views', '*.php' ) as $file ) {
			if ( empty( $skip_views ) || ! in_array( $file, $skip_views ) ) { // phpcs:ignore
				$this->add_from_file( $file );
			}
		}

		// Stop Freemius from hiding the menu on sub sites under certain circumstances.
		add_filter(
			'fs_should_hide_site_admin_settings_on_network_activation_mode_wp-security-audit-log',
			array(
				$this,
				'bypass_freemius_menu_hiding',
			)
		);

		// Add menus.
		add_action( 'admin_menu', array( $this, 'add_admin_menus' ) );
		add_action( 'network_admin_menu', array( $this, 'add_admin_menus' ) );

		// Add plugin shortcut links.
		add_filter( 'plugin_action_links_' . $plugin->get_base_name(), array( $this, 'add_plugin_shortcuts' ) );

		// Render header.
		add_action( 'admin_enqueue_scripts', array( $this, 'render_view_header' ) );

		// Render footer.
		add_action( 'admin_footer', array( $this, 'render_view_footer' ) );

		// Initialize setup wizard.
		if ( ! $this->plugin->get_global_boolean_setting( 'setup-complete', false )
			&& $this->plugin->settings()->current_user_can( 'edit' )
		) {
			new WSAL_Views_SetupWizard( $plugin );
		}


		add_action( 'admin_head', array( $this, 'hide_freemius_sites_section' ) );

		// Check if WFCM is running by seeing if we have the version defined.
		if ( defined( 'WFCM_VERSION' ) && ( version_compare( WFCM_VERSION, '1.6.0', '<' ) ) ) {
			add_action( 'admin_notices', array( $this, 'update_wfcm_notice' ) );
			add_action( 'network_admin_notices', array( $this, 'update_wfcm_notice' ) );
		}
	}

	/**
	 * Display notice if user is using older version of WFCM
	 */
	public function update_wfcm_notice() {
		if ( defined( 'WFCM_VERSION' ) ) {
			if ( version_compare( WFCM_VERSION, '1.6.0', '<' ) ) {
				echo '<div class="notice notice-success">
					<p>' . esc_html__( 'WP Activity Log requires Website File Changes Monitor 1.6.0. Please upgrade that plugin.', 'wp-security-audit-log' ) . '</p>
				</div>';
			}
		}
	}


	/**
	 * Add new view from file inside autoloader path.
	 *
	 * @param string $file Path to file.
	 */
	public function add_from_file( $file ) {
		$file = basename( $file, '.php' );
		$this->add_from_class( WSAL_CLASS_PREFIX . 'Views_' . $file );
	}

	/**
	 * Add new view given class name.
	 *
	 * @param string $class Class name.
	 */
	public function add_from_class( $class ) {
		$view = new $class( $this->plugin );
		// Only load WSAL_AbstractView instances to prevent lingering classes
		// that did not implement this from throwing fatals by being autoloaded.
		if ( is_a( $view, '\WSAL_AbstractView' ) ) {
			$this->add_instance( $view );
		}
	}

	/**
	 * Add newly created view to list.
	 *
	 * @param WSAL_AbstractView $view The new view.
	 */
	public function add_instance( WSAL_AbstractView $view ) {
		$this->views[] = $view;
	}

	/**
	 * Order views by their declared weight.
	 */
	public function reorder_views() {
		usort( $this->views, array( $this, 'order_by_weight' ) );
	}

	/**
	 * Get page order by its weight.
	 *
	 * @internal This has to be public for PHP to call it.
	 * @param WSAL_AbstractView $a - First view.
	 * @param WSAL_AbstractView $b - Second view.
	 * @return int
	 */
	public function order_by_weight( WSAL_AbstractView $a, WSAL_AbstractView $b ) {
		$wa = $a->get_weight();
		$wb = $b->get_weight();
		switch ( true ) {
			case $wa < $wb:
				return -1;
			case $wa > $wb:
				return 1;
			default:
				return 0;
		}
	}

	/**
	 * WordPress Action
	 */
	public function add_admin_menus() {
		$this->reorder_views();

		if ( $this->plugin->settings()->current_user_can( 'view' ) && count( $this->views ) ) {
			// Add main menu.
			$main_view_menu_slug         = $this->views[0]->get_safe_view_name();
			$this->views[0]->hook_suffix = add_menu_page(
				'WP Activity Log',
				'WP Activity Log',
				'read', // No capability requirement.
				$main_view_menu_slug,
				array( $this, 'render_view_body' ),
				$this->views[0]->get_icon(),
				'2.5' // Right after dashboard.
			);

			// Protected views to be displayed only to user with full plugin access.
			$protected_views = array(
				'wsal-togglealerts',
				'wsal-usersessions-views',
				'wsal-settings',
				'wsal-ext-settings',
				'wsal-rep-views-main',
				'wsal-np-notifications',
				'wsal-setup',
			);

			// Check edit privileges of the current user.
			$has_current_user_edit_priv = $this->plugin->settings()->current_user_can( 'edit' );

			// Add menu items.
			foreach ( $this->views as $view ) {
				if ( $view->is_accessible() ) {
					$safe_view_name = $view->get_safe_view_name();
					if ( $this->get_class_name_by_view( $safe_view_name ) ) {
						continue;
					}

					if ( in_array( $safe_view_name, $protected_views ) && ! $has_current_user_edit_priv ) { // phpcs:ignore
						continue;
					}

					$view->hook_suffix = add_submenu_page(
						$view->is_visible() ? $main_view_menu_slug : null,
						$view->get_title(),
						$view->get_name(),
						'read', // No capability requirement.
						$safe_view_name,
						array( $this, 'render_view_body' )
					);
				}
			}
		}
	}

	/**
	 * WordPress Filter
	 *
	 * @param array $old_links - Array of old links.
	 */
	public function add_plugin_shortcuts( $old_links ) {
		$this->reorder_views();

		$new_links = array();
		foreach ( $this->views as $view ) {
			if ( $view->has_plugin_shortcut_link() ) {
				$new_links[] = '<a href="' . add_query_arg( 'page', $view->get_safe_view_name(), admin_url( 'admin.php' ) ) . '">' . $view->get_name() . '</a>';

				if ( 1 === count( $new_links ) && ! wsal_freemius()->is__premium_only() ) {
					// Trial link.
					$trial_link  = 'https://wpactivitylog.com/trial-premium-edition-plugin/?utm_source=plugin&utm_medium=referral&utm_campaign=WSAL';
					$new_links[] = '<a style="font-weight:bold" href="' . $trial_link . '" target="_blank">' . __( 'Free Premium Trial', 'wp-security-audit-log' ) . '</a>';
				}
			}
		}
		return array_merge( $new_links, $old_links );
	}

	/**
	 * Returns page id of current page (or false on error).
	 *
	 * @return int
	 */
	protected function get_backend_page_index() {
		// Get current view via $_GET array.
		$current_view = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_STRING );

		if ( isset( $current_view ) ) {
			foreach ( $this->views as $i => $view ) {
				if ( $current_view === $view->get_safe_view_name() ) {
					return $i;
				}
			}
		}
		return false;
	}

	/**
	 * Returns the current active view or null if none.
	 *
	 * @return WSAL_AbstractView|null
	 */
	public function get_active_view() {
		if ( false === $this->active_view ) {
			$this->active_view = null;

			// Get current view via $_GET array.
			$current_view = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_STRING );

			if ( isset( $current_view ) ) {
				foreach ( $this->views as $view ) {
					if ( $current_view === $view->get_safe_view_name() ) {
						$this->active_view = $view;
					}
				}
			}

			if ( $this->active_view ) {
				$this->active_view->is_active = true;
			}
		}
		return $this->active_view;
	}

	/**
	 * Render header of the current view.
	 */
	public function render_view_header() {
		$view = $this->get_active_view();
		if ( $view ) {
			$view->header();
		}
	}

	/**
	 * Render footer of the current view.
	 */
	public function render_view_footer() {
		$view = $this->get_active_view();
		if ( $view ) {
			$view->footer();
		}

		global $pagenow;
		if ( 'admin.php' === $pagenow && 'wsal-auditlog-pricing' === $_GET['page'] ) { // phpcs:ignore
			?>
			<style>
				.fs-full-size-wrapper {
					margin: 0px 0 -65px -20px !important;
				}

				#root .fs-app-header .fs-page-title h2, #fs_pricing_wrapper .fs-app-header .fs-page-title h2 {
					font-size: 23px;
					font-weight: 400;
					margin: 0;
					padding: 9px 0 4px 20px;
					line-height: 1.3;
				}

				@media only screen and (max-width: 768px) {
					#root #fs_pricing_wrapper .fs-app-main .fs-section--plans-and-pricing .fs-section--packages .fs-packages-menu, #fs_pricing_wrapper #fs_pricing_wrapper .fs-app-main .fs-section--plans-and-pricing .fs-section--packages .fs-packages-menu {
						padding: 5px;
						display: flex;
						width: 100%;
						margin: 0 auto;
					}
				}
			</style>
			<?php
		}
	}

	/**
	 * Render content of the current view.
	 */
	public function render_view_body() {
		$view = $this->get_active_view();
		if ( $view && $view instanceof WSAL_AbstractView ) :
			?>
			<div class="wrap">
				<?php
					$view->render_icon();
					$view->render_title();
					$view->render_content();
				?>
			</div>
			<?php
		endif;
	}

	/**
	 * Returns view instance corresponding to its class name.
	 *
	 * @param string $class_name View class name.
	 * @return WSAL_AbstractView|bool The view or false on failure.
	 */
	public function find_by_class_name( $class_name ) {
		foreach ( $this->views as $view ) {
			if ( $view instanceof $class_name ) {
				return $view;
			}
		}
		return false;
	}

	/**
	 * Method: Returns class name of the view using view name.
	 *
	 * @param  string $view_slug - Slug of view.
	 * @since  1.0.0
	 */
	private function get_class_name_by_view( $view_slug ) {
		$not_show = false;
		switch ( $view_slug ) {
			case 'wsal-emailnotifications':
				if ( class_exists( 'WSAL_NP_Plugin' ) ) {
					$not_show = true;
				}
				break;
			case 'wsal-loginusers':
				if ( class_exists( 'WSAL_UserSessions_Plugin' ) ) {
					$not_show = true;
				}
				break;
			case 'wsal-reports':
				if ( class_exists( 'WSAL_Rep_Plugin' ) ) {
					$not_show = true;
				}
				break;
			case 'wsal-search':
				if ( class_exists( 'WSAL_SearchExtension' ) ) {
					$not_show = true;
				}
				break;
			case 'wsal-externaldb':
				if ( class_exists( 'WSAL_Ext_Plugin' ) ) {
					$not_show = true;
				}
				break;
			default:
				break;
		}
		return $not_show;
	}


	/**
	 * Hide Freemius sites section on the account page
	 * of a multisite WordPress network.
	 */
	public function hide_freemius_sites_section() {
		global $pagenow;

		// Return if not multisite.
		if ( ! is_multisite() ) {
			return;
		}

		// Return if multisite but not on the network admin.
		if ( ! is_network_admin() ) {
			return;
		}

		// Return if the pagenow is not on admin.php page.
		if ( 'admin.php' !== $pagenow ) {
			return;
		}

		// Get page query parameter.
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : false; // phpcs:ignore

		if ( 'wsal-auditlog-account' === $page ) {
			echo '<style type="text/css">#fs_sites {display:none;}</style>';
		}
	}

	/**
	 * Bypasses Freemius hiding menu items.
	 *
	 * @param bool $should_hide Should allow Freemium to hide menu items.
	 *
	 * @return bool
	 */
	public function bypass_freemius_menu_hiding( $should_hide ) {
		return false;
	}

	/**
	 * Builds a relative asset path that takes SCRIPT_DEBUG constant into account.
	 *
	 * @param string $path                 Path relative to the plugin folder.
	 * @param string $filename             Filename base (.min is optionally appended to this).
	 * @param string $extension            File extension.
	 * @param bool   $use_minified_version If true, the minified version of the file is used.
	 *
	 * @return string
	 */
	public static function get_asset_path( $path, $filename, $extension, $use_minified_version = true ) {
		$result = $path . $filename . '.';
		if ( $use_minified_version && SCRIPT_DEBUG ) {
			$result .= 'min.';
		}
		$result .= $extension;

		return $result;
	}
}
