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
	 * @param  WpSecurityAuditLog $plugin - Instance of WpSecurityAuditLog.
	 * @author Ashar Irfan
	 * @since  1.0.0
	 */
	public function __construct( WpSecurityAuditLog $plugin ) {
		$this->_plugin = $plugin;

		// Skipped views array.
		$skip_views = array();

		// Array of views to skip for premium version.
		if ( wsal_freemius()->is_plan_or_trial__premium_only( 'starter' ) ) {
			$skip_views[] = $this->_plugin->GetBaseDir() . 'classes/Views/EmailNotifications.php';
			$skip_views[] = $this->_plugin->GetBaseDir() . 'classes/Views/Search.php';
		}

		if ( wsal_freemius()->is_plan_or_trial__premium_only( 'professional' ) ) {
			$skip_views[] = $this->_plugin->GetBaseDir() . 'classes/Views/EmailNotifications.php';
			$skip_views[] = $this->_plugin->GetBaseDir() . 'classes/Views/Search.php';
			$skip_views[] = $this->_plugin->GetBaseDir() . 'classes/Views/ExternalDB.php';
			$skip_views[] = $this->_plugin->GetBaseDir() . 'classes/Views/Licensing.php';
			$skip_views[] = $this->_plugin->GetBaseDir() . 'classes/Views/LogInUsers.php';
			$skip_views[] = $this->_plugin->GetBaseDir() . 'classes/Views/Reports.php';
		}

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
		if (
			'no' === $this->_plugin->GetGlobalOption( 'wsal-setup-complete', 'no' )
			|| 'no' === $this->_plugin->GetGlobalOption( 'wsal-setup-modal-dismissed', 'no' )
		) {
			new WSAL_Views_SetupWizard( $plugin );
		}

		// Reorder WSAL submenu.
		add_filter( 'custom_menu_order', array( $this, 'reorder_wsal_submenu' ), 10, 1 );

		if ( wsal_freemius()->is__premium_only() ) {
			if ( $this->_plugin->settings->is_admin_bar_notif() ) {
				add_action( 'admin_bar_menu', array( $this, 'live_notifications__premium_only' ), 1000, 1 );
				add_action( 'wp_ajax_wsal_adminbar_events_refresh', array( $this, 'wsal_adminbar_events_refresh__premium_only' ) );
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
		$this->AddInstance( new $class( $this->_plugin ) );
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

		if ( $this->_plugin->settings->CurrentUserCan( 'view' ) && count( $this->views ) ) {
			// Add main menu.
			$this->views[0]->hook_suffix = add_menu_page(
				'WP Security Audit Log',
				'Audit Log',
				'read', // No capability requirement.
				$this->views[0]->GetSafeViewName(),
				array( $this, 'RenderViewBody' ),
				$this->views[0]->GetIcon(),
				'2.5' // Right after dashboard.
			);

			// Add menu items.
			foreach ( $this->views as $view ) {
				if ( $view->IsAccessible() ) {
					if ( $this->GetClassNameByView( $view->GetSafeViewName() ) ) {
						continue;
					}

					if ( ( 'wsal-togglealerts' === $view->GetSafeViewName()
							|| 'wsal-settings' === $view->GetSafeViewName()
							|| 'wsal-ext-settings' === $view->GetSafeViewName()
							|| 'wsal-rep-views-main' === $view->GetSafeViewName()
							|| 'wsal-np-notifications' === $view->GetSafeViewName()
						)
						&& ! $this->_plugin->settings->CurrentUserCan( 'edit' ) ) {
						continue;
					}

					$view->hook_suffix = add_submenu_page(
						$view->IsVisible() ? $this->views[0]->GetSafeViewName() : null,
						$view->GetTitle(),
						$view->GetName(),
						'read', // No capability requirement.
						$view->GetSafeViewName(),
						array( $this, 'RenderViewBody' ),
						$view->GetIcon()
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
					// Trial link arguments.
					$trial_args  = array(
						'page'          => 'wsal-auditlog-pricing',
						'billing_cycle' => 'annual',
						'trial'         => 'true',
					);
					$admin_url   = $this->_plugin->IsMultisite() ? network_admin_url( 'admin.php' ) : admin_url( 'admin.php' );
					$new_links[] = '<a style="font-weight:bold" href="' . add_query_arg( $trial_args, $admin_url ) . '">' . __( 'Free Premium Trial', 'wp-security-audit-log' ) . '</a>';
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
		if ( ! ! ( $view = $this->GetActiveView() ) ) {
			$view->Header();
		}
	}

	/**
	 * Render footer of the current view.
	 */
	public function RenderViewFooter() {
		if ( ! ! ( $view = $this->GetActiveView() ) ) {
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
	 * @return WSAL_AbstractView The view or false on failure.
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
				if ( class_exists( 'WSAL_User_Management_Plugin' ) ) {
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
	 * Add WSAL to WP-Admin menu bar.
	 *
	 * @since 3.2.4
	 *
	 * @param WP_Admin_Bar $admin_bar - Instance of WP_Admin_Bar.
	 */
	public function live_notifications__premium_only( $admin_bar ) {
		if ( $this->_plugin->settings->CurrentUserCan( 'view' ) && is_admin() ) {
			$adn_updates = $this->_plugin->settings->get_admin_bar_notif_updates();
			$event       = $this->_plugin->alerts->get_admin_bar_event( 'page-refresh' === $adn_updates ? true : false );

			if ( $event ) {
				$code = $this->_plugin->alerts->GetAlert( $event->alert_id );
				$admin_bar->add_node(
					array(
						'id'    => 'wsal-menu',
						'title' => 'LIVE: ' . $code->desc . ' from ' . $event->GetSourceIp(),
						'href'  => add_query_arg( 'page', 'wsal-auditlog', admin_url( 'admin.php' ) ),
						'meta'  => array( 'class' => 'wsal-live-notif-item' ),
					)
				);
			}
		}
	}

	/**
	 * WP-Admin bar refresh event handler.
	 *
	 * @since 3.2.4
	 */
	public function wsal_adminbar_events_refresh__premium_only() {
		if ( ! $this->_plugin->settings->CurrentUserCan( 'view' ) ) {
			echo wp_json_encode(
				array(
					'success' => false,
					'message' => __( 'Access Denied.', 'wp-security-audit-log' ),
				)
			);
			die();
		}

		if ( isset( $_POST['nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wsal-common-js-nonce' ) ) {
			$events_count = isset( $_POST['eventsCount'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['eventsCount'] ) ) : false;

			if ( $events_count ) {
				$occurrence = new WSAL_Models_Occurrence();
				$new_count  = (int) $occurrence->Count();

				if ( $events_count !== $new_count ) {
					$event = $this->_plugin->alerts->get_admin_bar_event( true );
					$code  = $this->_plugin->alerts->GetAlert( $event->alert_id );

					echo wp_json_encode(
						array(
							'success' => true,
							'count'   => $new_count,
							'message' => 'LIVE: ' . $code->desc . ' from ' . $event->GetSourceIp(),
						)
					);
				} else {
					echo wp_json_encode( array( 'success' => false ) );
				}
			} else {
				echo wp_json_encode(
					array(
						'success' => false,
						'message' => __( 'Log count parameter expected.', 'wp-security-audit-log' ),
					)
				);
			}
		} else {
			echo wp_json_encode(
				array(
					'success' => false,
					'message' => __( 'Nonce verification failed.', 'wp-security-audit-log' ),
				)
			);
		}
		die();
	}
}
