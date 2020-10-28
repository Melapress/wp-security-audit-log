<?php
/**
 * Manager: View
 *
 * View manager class file.
 *
 * @since 1.0.0
 * @package Wsal
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
 * @package Wsal
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
	protected $_plugin;

	/**
	 * Active view.
	 *
	 * @var WSAL_AbstractView|null
	 */
	protected $_active_view = false;

	/**
	 * Method: Constructor.
	 *
	 * @param WpSecurityAuditLog $plugin - Instance of WpSecurityAuditLog.
	 *
	 * @throws Freemius_Exception
	 * @since  1.0.0
	 */
	public function __construct( WpSecurityAuditLog $plugin ) {
		$this->_plugin = $plugin;

		// Skipped views array.
		$skip_views = array();


		/**
		 * Add setup wizard page to skip views. It will only be initialized
		 * one time.
		 *
		 * @since 3.2.3
		 */
		if ( file_exists( $this->_plugin->GetBaseDir() . 'classes/Views/SetupWizard.php' ) ) {
			$skip_views[] = $this->_plugin->GetBaseDir() . 'classes/Views/SetupWizard.php';
		}

		/**
		 * Removed in version 4.0.3 however some upgrade methods result in the
		 * file being left behind and `AddFromFile()` tries to load it as a
		 * class resulting in a fatal error because of it not existing.
		 *
		 * @since 4.0.4
		 */
		$skip_views[] = $this->_plugin->GetBaseDir() . 'classes/Views/Licensing.php';

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
		foreach ( glob( $this->_plugin->GetBaseDir() . 'classes/Views/*.php' ) as $file ) {
			if ( empty( $skip_views ) || ! in_array( $file, $skip_views ) ) {
				$this->AddFromFile( $file );
			}
		}

		//  stop Freemius from hiding the menu on sub sites under certain circumstances
		add_filter('fs_should_hide_site_admin_settings_on_network_activation_mode_wp-security-audit-log', array( $this, 'bypass_freemius_menu_hiding'));

		// Add menus.
		add_action( 'admin_menu', array( $this, 'AddAdminMenus' ) );
		add_action( 'network_admin_menu', array( $this, 'AddAdminMenus' ) );

		// Add plugin shortcut links.
		add_filter( 'plugin_action_links_' . $plugin->GetBaseName(), array( $this, 'AddPluginShortcuts' ) );

		// Render header.
		add_action( 'admin_enqueue_scripts', array( $this, 'RenderViewHeader' ) );

		// Render footer.
		add_action( 'admin_footer', array( $this, 'RenderViewFooter' ) );

		// Initialize setup wizard.
		if ( ! $this->_plugin->GetGlobalBooleanSetting( 'setup-complete', false )
            && $this->_plugin->settings()->CurrentUserCan( 'edit' )
		) {
			new WSAL_Views_SetupWizard( $plugin );
		}

		// Reorder WSAL submenu.
		add_filter( 'custom_menu_order', array( $this, 'reorder_wsal_submenu' ), 10, 1 );


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
					<p>' . __( 'WP Activity Log requires Website File Changes Monitor 1.6.0. Please upgrade that plugin.', 'wp-security-audit-log' ) . '</p>
				</div>';
			}
		}
	}


	/**
	 * Add new view from file inside autoloader path.
	 *
	 * @param string $file Path to file.
	 */
	public function AddFromFile( $file ) {
		$file = basename( $file, '.php' );
		$this->AddFromClass( WSAL_CLASS_PREFIX . 'Views_' . $file );
	}

	/**
	 * Add new view given class name.
	 *
	 * @param string $class Class name.
	 */
	public function AddFromClass( $class ) {
		$view = new $class( $this->_plugin );
		// only load WSAL_AbstractView instances to prevent lingering classes
		// that did not impliment this from throwing fatals by being autoloaded.
		if ( is_a( $view, '\WSAL_AbstractView' ) ) {
			$this->AddInstance( $view );
		}
	}

	/**
	 * Add newly created view to list.
	 *
	 * @param WSAL_AbstractView $view The new view.
	 */
	public function AddInstance( WSAL_AbstractView $view ) {
		$this->views[] = $view;
	}

	/**
	 * Order views by their declared weight.
	 */
	public function ReorderViews() {
		usort( $this->views, array( $this, 'OrderByWeight' ) );
	}

	/**
	 * Get page order by its weight.
	 *
	 * @internal This has to be public for PHP to call it.
	 * @param WSAL_AbstractView $a - First view.
	 * @param WSAL_AbstractView $b - Second view.
	 * @return int
	 */
	public function OrderByWeight( WSAL_AbstractView $a, WSAL_AbstractView $b ) {
		$wa = $a->GetWeight();
		$wb = $b->GetWeight();
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
	public function AddAdminMenus() {
		$this->ReorderViews();

		if ( $this->_plugin->settings()->CurrentUserCan( 'view' ) && count( $this->views ) ) {
			// Add main menu.
            $main_view_menu_slug = $this->views[0]->GetSafeViewName();
			$this->views[0]->hook_suffix = add_menu_page(
				'WP Activity Log',
				'WP Activity Log',
				'read', // No capability requirement.
				$main_view_menu_slug,
				array( $this, 'RenderViewBody' ),
				$this->views[0]->GetIcon(),
				'2.5' // Right after dashboard.
			);

			//  protected views to be displayed only to user with full plugin access
			$protected_views = array(
                'wsal-togglealerts',
                'wsal-usersessions-views',
                'wsal-settings',
                'wsal-ext-settings',
                'wsal-rep-views-main',
                'wsal-np-notifications',
                'wsal-setup'
            );

			//  check edit privileges of the current user
			$has_current_user_edit_priv = $this->_plugin->settings()->CurrentUserCan( 'edit' );

			// Add menu items.
			foreach ( $this->views as $view ) {
				if ( $view->IsAccessible() ) {
				    $safe_view_name = $view->GetSafeViewName();
					if ( $this->GetClassNameByView( $safe_view_name ) ) {
						continue;
					}

					if ( in_array( $safe_view_name, $protected_views ) && ! $has_current_user_edit_priv ) {
						continue;
					}

					$view->hook_suffix = add_submenu_page(
						$view->IsVisible() ? $main_view_menu_slug : null,
						$view->GetTitle(),
						$view->GetName(),
						'read', // No capability requirement.
						$safe_view_name,
						array( $this, 'RenderViewBody' )
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
	public function AddPluginShortcuts( $old_links ) {
		$this->ReorderViews();

		$new_links = array();
		foreach ( $this->views as $view ) {
			if ( $view->HasPluginShortcutLink() ) {
				$new_links[] = '<a href="' . add_query_arg( 'page', $view->GetSafeViewName(), admin_url( 'admin.php' ) ) . '">' . $view->GetName() . '</a>';

				if ( 1 === count( $new_links ) && ! wsal_freemius()->is__premium_only() ) {
					// Trial link
					$trial_link = 'https://wpactivitylog.com/trial-premium-edition-plugin/?utm_source=plugin&utm_medium=referral&utm_campaign=WSAL';
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
	protected function GetBackendPageIndex() {
		// Get current view via $_GET array.
		$current_view = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_STRING );

		if ( isset( $current_view ) ) {
			foreach ( $this->views as $i => $view ) {
				if ( $current_view === $view->GetSafeViewName() ) {
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
	public function GetActiveView() {
		if ( false === $this->_active_view ) {
			$this->_active_view = null;

			// Get current view via $_GET array.
			$current_view = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_STRING );

			if ( isset( $current_view ) ) {
				foreach ( $this->views as $view ) {
					if ( $current_view === $view->GetSafeViewName() ) {
						$this->_active_view = $view;
					}
				}
			}

			if ( $this->_active_view ) {
				$this->_active_view->is_active = true;
			}
		}
		return $this->_active_view;
	}

	/**
	 * Render header of the current view.
	 */
	public function RenderViewHeader() {
		if ( $view = $this->GetActiveView() ) {
			$view->Header();
		}
	}

	/**
	 * Render footer of the current view.
	 */
	public function RenderViewFooter() {
		if ( $view = $this->GetActiveView() ) {
			$view->Footer();
		}
	}

	/**
	 * Render content of the current view.
	 */
	public function RenderViewBody() {
		$view = $this->GetActiveView();

		if ( $view && $view instanceof WSAL_AbstractView ) :
			?>
			<div class="wrap">
				<?php
					$view->RenderIcon();
					$view->RenderTitle();
					$view->RenderContent();
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
	public function FindByClassName( $class_name ) {
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
	private function GetClassNameByView( $view_slug ) {
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
	 * Reorder WSAL Submenu.
	 *
	 * @since 3.2.4
	 *
	 * @param boolean $menu_order - Custom order.
	 * @return boolean
	 */
	public function reorder_wsal_submenu( $menu_order ) {
		// Get global $submenu order.
		global $submenu;

		// Get WSAL admin menu.
		$auditlog_menu = isset( $submenu['wsal-auditlog'] ) ? $submenu['wsal-auditlog'] : false;
		$help_link     = new stdClass();
		$account_link  = new stdClass();

		if ( ! empty( $auditlog_menu ) ) {
			foreach ( $auditlog_menu as $key => $auditlog_submenu ) {
				if ( 'wsal-help' === $auditlog_submenu[2] ) {
					$help_link->key  = $key;
					$help_link->menu = $auditlog_submenu;
				} elseif ( 'wsal-auditlog-account' === $auditlog_submenu[2] ) {
					$account_link->key  = $key;
					$account_link->menu = $auditlog_submenu;
				}
			}
		}

		if ( ! empty( $help_link ) && ! empty( $account_link ) ) {
			// Swap the menus at their positions.
			if ( isset( $help_link->key ) && isset( $account_link->menu ) ) {
				$submenu['wsal-auditlog'][ $help_link->key ] = $account_link->menu;
			}
			if ( isset( $account_link->key ) && isset( $help_link->menu ) ) {
				$submenu['wsal-auditlog'][ $account_link->key ] = $help_link->menu;
			}
			if ( isset( $submenu['wsal-auditlog'] ) && is_array( $submenu['wsal-auditlog'] ) ) {
				ksort( $submenu['wsal-auditlog'] );
			}
		}
		return $menu_order;
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
		if ( is_multisite() && ! is_network_admin() ) {
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
	 * @param bool $should_hide
	 *
	 * @return bool
	 */
	public function bypass_freemius_menu_hiding($should_hide) {
	    return false;
	}

	/**
     * Builds a relative asset path that takes SCRIPT_DEBUG constant into account.
     *
	 * @param string $path Path relative to the plugin folder.
	 * @param string $filename Filename base (.min is optionally appended to this).
	 * @param string $extension File extension
	 *
	 * @return string
	 */
	public static function get_asset_path($path, $filename, $extension) {
		$result = $path . $filename;
		$result .= SCRIPT_DEBUG ? '.' : '.min.';
		$result .= $extension;
		return $result;
	}
}
