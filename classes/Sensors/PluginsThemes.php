<?php
/**
 * Sensor: Plugins & Themes
 *
 * Plugins & Themes sensor file.
 *
 * @since 1.0.0
 * @package Wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugins & Themes sensor.
 *
 * 5000 User installed a plugin
 * 5001 User activated a WordPress plugin
 * 5002 User deactivated a WordPress plugin
 * 5003 User uninstalled a plugin
 * 5004 User upgraded a plugin
 * 5005 User installed a theme
 * 5006 User activated a theme
 * 5007 User uninstalled a theme
 * 5019 A plugin created a post
 * 5020 A plugin created a page
 * 5021 A plugin created a custom post
 * 5025 A plugin deleted a post
 * 5026 A plugin deleted a page
 * 5027 A plugin deleted a custom post
 * 5031 User updated a theme
 * 2106 A plugin modified a post
 * 2107 A plugin modified a page
 * 2108 A plugin modified a custom post
 *
 * @package Wsal
 * @subpackage Sensors
 */
class WSAL_Sensors_PluginsThemes extends WSAL_AbstractSensor {

	/**
	 * List of Themes.
	 *
	 * @var array
	 */
	protected $old_themes = array();

	/**
	 * List of Plugins.
	 *
	 * @var array
	 */
	protected $old_plugins = array();

	/**
	 * Website plugins + themes.
	 *
	 * Used to keep track of file change alerts. If a plugin/theme is
	 * installed/updated/uninstalled, then its name is added to the
	 * respective skip array of this object. These arrays are used in
	 * Sensors/FileChanges.php to filter the files during a scan.
	 *
	 * @var stdClass
	 */
	private $site_content;

	/**
	 * Listening to events using WP hooks.
	 */
	public function HookEvents() {
		$has_permission = ( current_user_can( 'install_plugins' ) || current_user_can( 'activate_plugins' ) ||
							current_user_can( 'delete_plugins' ) || current_user_can( 'update_plugins' ) || current_user_can( 'install_themes' ) );

		add_action( 'admin_init', array( $this, 'EventAdminInit' ) );
		if ( $has_permission ) {
			add_action( 'shutdown', array( $this, 'EventAdminShutdown' ) );
		}
		add_action( 'switch_theme', array( $this, 'EventThemeActivated' ) );

		// TO DO.
		add_action( 'wp_insert_post', array( $this, 'EventPluginPostCreate' ), 10, 2 );
		add_action( 'delete_post', array( $this, 'EventPluginPostDelete' ), 10, 1 );

		// Set site plugins.
		$this->set_site_plugins();

		// Set site content.
		$this->set_site_themes();

		// Check if MainWP Child Plugin exists.
		if ( is_plugin_active( 'mainwp-child/mainwp-child.php' ) ) {
			$this->mainwp_child_init();

			// Handle plugin/theme installation event via MainWP dashboard.
			add_action( 'mainwp_child_installPluginTheme', array( $this, 'mainwp_child_install_assets' ), 10, 1 );

			// Activate/Deactivate plugin event.
			add_action( 'activated_plugin', array( $this, 'mainwp_child_plugin_events' ), 10, 1 );
			add_action( 'deactivated_plugin', array( $this, 'mainwp_child_plugin_events' ), 10, 1 );

			// Uninstall plugin from MainWP dashboard.
			add_action( 'mainwp_child_plugin_action', array( $this, 'mainwp_child_uninstall_plugin' ), 10, 1 );

			// Uninstall theme from MainWP dashboard.
			add_action( 'mainwp_child_theme_action', array( $this, 'mainwp_child_uninstall_theme' ), 10, 1 );

			// Update theme/plugin from MainWP dashboard.
			add_action( 'mainwp_child_upgradePluginTheme', array( $this, 'mainwp_child_update_assets' ), 10, 1 );
		}
	}

	/**
	 * Triggered when a user accesses the admin area.
	 */
	public function EventAdminInit() {
		$this->old_themes  = wp_get_themes();
		$this->old_plugins = get_plugins();
	}

	/**
	 * Method: Check and initialize class members for MainWP.
	 */
	public function mainwp_child_init() {
		// $_POST array arguments.
		$post_array_args = array(
			'function' => FILTER_SANITIZE_STRING,
			'action'   => FILTER_SANITIZE_STRING,
			'theme'    => FILTER_SANITIZE_STRING,
			'mainwpsignature' => FILTER_SANITIZE_STRING,
		);

		// Get $_POST array.
		$post_array = filter_input_array( INPUT_POST, $post_array_args );

		if (
			isset( $post_array['function'] ) && 'theme_action' === $post_array['function']
			&& isset( $post_array['action'] ) && 'delete' === $post_array['action']
			&& isset( $post_array['theme'] ) && ! empty( $post_array['theme'] )
			&& isset( $post_array['mainwpsignature'] ) && ! empty( $post_array['mainwpsignature'] )
		) {
			if ( empty( $this->old_themes ) ) {
				$this->old_themes = wp_get_themes();
			}
		}
	}

	/**
	 * Install, uninstall, activate, deactivate, upgrade and update.
	 */
	public function EventAdminShutdown() {
		// Filter global arrays for security.
		$post_array  = filter_input_array( INPUT_POST );
		$get_array   = filter_input_array( INPUT_GET );
		$script_name = isset( $_SERVER['SCRIPT_NAME'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) : false;

		$action = '';
		if ( isset( $get_array['action'] ) && '-1' != $get_array['action'] ) {
			$action = $get_array['action'];
		} elseif ( isset( $post_array['action'] ) && '-1' != $post_array['action'] ) {
			$action = $post_array['action'];
		}

		if ( isset( $get_array['action2'] ) && '-1' != $get_array['action2'] ) {
			$action = $get_array['action2'];
		} elseif ( isset( $post_array['action2'] ) && '-1' != $post_array['action2'] ) {
			$action = $post_array['action2'];
		}

		$actype = '';
		if ( ! empty( $script_name ) ) {
			$actype = basename( $script_name, '.php' );
		}
		$is_themes  = 'themes' === $actype;
		$is_plugins = 'plugins' === $actype;

		// Install plugin.
		if ( in_array( $action, array( 'install-plugin', 'upload-plugin' ) ) && current_user_can( 'install_plugins' ) ) {
			$plugin = array_values( array_diff( array_keys( get_plugins() ), array_keys( $this->old_plugins ) ) );
			if ( count( $plugin ) != 1 ) {
				return $this->LogError(
					'Expected exactly one new plugin but found ' . count( $plugin ),
					array(
						'NewPlugin' => $plugin,
						'OldPlugins' => $this->old_plugins,
						'NewPlugins' => get_plugins(),
					)
				);
			}
			$plugin_path = $plugin[0];
			$plugin = get_plugins();
			$plugin = $plugin[ $plugin_path ];

			// Get plugin directory name.
			$plugin_dir = $this->get_plugin_dir( $plugin_path );

			// Add plugin to site plugins list.
			$this->set_site_plugins( $plugin_dir );

			$plugin_path = plugin_dir_path( WP_PLUGIN_DIR . '/' . $plugin_path[0] );
			$this->plugin->alerts->Trigger(
				5000, array(
					'Plugin' => (object) array(
						'Name' => $plugin['Name'],
						'PluginURI' => $plugin['PluginURI'],
						'Version' => $plugin['Version'],
						'Author' => $plugin['Author'],
						'Network' => $plugin['Network'] ? 'True' : 'False',
						'plugin_dir_path' => $plugin_path,
					),
				)
			);
		}

		// Activate plugin.
		if ( $is_plugins && in_array( $action, array( 'activate', 'activate-selected' ) ) && current_user_can( 'activate_plugins' ) ) {
			// Check $_GET array case.
			if ( isset( $get_array['plugin'] ) ) {
				if ( ! isset( $get_array['checked'] ) ) {
					$get_array['checked'] = array();
				}
				$get_array['checked'][] = $get_array['plugin'];
			}

			// Check $_POST array case.
			if ( isset( $post_array['plugin'] ) ) {
				if ( ! isset( $post_array['checked'] ) ) {
					$post_array['checked'] = array();
				}
				$post_array['checked'][] = $post_array['plugin'];
			}

			if ( isset( $get_array['checked'] ) && ! empty( $get_array['checked'] ) ) {
				foreach ( $get_array['checked'] as $plugin_file ) {
					$plugin_file = WP_PLUGIN_DIR . '/' . $plugin_file;
					$plugin_data = get_plugin_data( $plugin_file, false, true );
					$this->plugin->alerts->Trigger(
						5001, array(
							'PluginFile' => $plugin_file,
							'PluginData' => (object) array(
								'Name' => $plugin_data['Name'],
								'PluginURI' => $plugin_data['PluginURI'],
								'Version' => $plugin_data['Version'],
								'Author' => $plugin_data['Author'],
								'Network' => $plugin_data['Network'] ? 'True' : 'False',
							),
						)
					);
				}
			} elseif ( isset( $post_array['checked'] ) && ! empty( $post_array['checked'] ) ) {
				foreach ( $post_array['checked'] as $plugin_file ) {
					$plugin_file = WP_PLUGIN_DIR . '/' . $plugin_file;
					$plugin_data = get_plugin_data( $plugin_file, false, true );
					$this->plugin->alerts->Trigger(
						5001, array(
							'PluginFile' => $plugin_file,
							'PluginData' => (object) array(
								'Name' => $plugin_data['Name'],
								'PluginURI' => $plugin_data['PluginURI'],
								'Version' => $plugin_data['Version'],
								'Author' => $plugin_data['Author'],
								'Network' => $plugin_data['Network'] ? 'True' : 'False',
							),
						)
					);
				}
			}
		}

		// Deactivate plugin.
		if ( $is_plugins && in_array( $action, array( 'deactivate', 'deactivate-selected' ) ) && current_user_can( 'activate_plugins' ) ) {
			// Check $_GET array case.
			if ( isset( $get_array['plugin'] ) ) {
				if ( ! isset( $get_array['checked'] ) ) {
					$get_array['checked'] = array();
				}
				$get_array['checked'][] = $get_array['plugin'];
			}

			// Check $_POST array case.
			if ( isset( $post_array['plugin'] ) ) {
				if ( ! isset( $post_array['checked'] ) ) {
					$post_array['checked'] = array();
				}
				$post_array['checked'][] = $post_array['plugin'];
			}

			if ( isset( $get_array['checked'] ) && ! empty( $get_array['checked'] ) ) {
				foreach ( $get_array['checked'] as $plugin_file ) {
					$plugin_file = WP_PLUGIN_DIR . '/' . $plugin_file;
					$plugin_data = get_plugin_data( $plugin_file, false, true );
					$this->plugin->alerts->Trigger(
						5002, array(
							'PluginFile' => $plugin_file,
							'PluginData' => (object) array(
								'Name' => $plugin_data['Name'],
								'PluginURI' => $plugin_data['PluginURI'],
								'Version' => $plugin_data['Version'],
								'Author' => $plugin_data['Author'],
								'Network' => $plugin_data['Network'] ? 'True' : 'False',
							),
						)
					);
				}
			} elseif ( isset( $post_array['checked'] ) && ! empty( $post_array['checked'] ) ) {
				foreach ( $post_array['checked'] as $plugin_file ) {
					$plugin_file = WP_PLUGIN_DIR . '/' . $plugin_file;
					$plugin_data = get_plugin_data( $plugin_file, false, true );
					$this->plugin->alerts->Trigger(
						5002, array(
							'PluginFile' => $plugin_file,
							'PluginData' => (object) array(
								'Name' => $plugin_data['Name'],
								'PluginURI' => $plugin_data['PluginURI'],
								'Version' => $plugin_data['Version'],
								'Author' => $plugin_data['Author'],
								'Network' => $plugin_data['Network'] ? 'True' : 'False',
							),
						)
					);
				}
			}
		}

		// Uninstall plugin.
		if ( $is_plugins && in_array( $action, array( 'delete-selected' ) ) && current_user_can( 'delete_plugins' ) ) {
			if ( ! isset( $post_array['verify-delete'] ) ) {
				// First step, before user approves deletion
				// TODO store plugin data in session here.
			} else {
				// second step, after deletion approval
				// TODO use plugin data from session.
				foreach ( $post_array['checked'] as $plugin_file ) {
					$plugin_name = basename( $plugin_file, '.php' );
					$plugin_name = str_replace( array( '_', '-', '  ' ), ' ', $plugin_name );
					$plugin_name = ucwords( $plugin_name );
					$plugin_file = WP_PLUGIN_DIR . '/' . $plugin_file;
					$this->plugin->alerts->Trigger(
						5003, array(
							'PluginFile' => $plugin_file,
							'PluginData' => (object) array(
								'Name' => $plugin_name,
							),
						)
					);
				}
			}
		}

		// Uninstall plugin for WordPress version 4.6.
		if ( in_array( $action, array( 'delete-plugin' ) ) && current_user_can( 'delete_plugins' ) ) {
			if ( isset( $post_array['plugin'] ) ) {
				$plugin_file = WP_PLUGIN_DIR . '/' . $post_array['plugin'];
				$plugin_name = basename( $plugin_file, '.php' );
				$plugin_name = str_replace( array( '_', '-', '  ' ), ' ', $plugin_name );
				$plugin_name = ucwords( $plugin_name );
				$this->plugin->alerts->Trigger(
					5003, array(
						'PluginFile' => $plugin_file,
						'PluginData' => (object) array(
							'Name' => $plugin_name,
						),
					)
				);

				// Get plugin directory name.
				$plugin_dir = $this->get_plugin_dir( $post_array['plugin'] );

				// Set plugin to skip file changes alert.
				$this->skip_plugin_change_alerts( $plugin_dir );

				// Remove it from the list.
				$this->remove_site_plugin( $plugin_dir );
			}
		}

		// Upgrade plugin.
		if ( in_array( $action, array( 'upgrade-plugin', 'update-plugin', 'update-selected' ) ) && current_user_can( 'update_plugins' ) ) {
			$plugins = array();

			// Check $_GET array cases.
			if ( isset( $get_array['plugins'] ) ) {
				$plugins = explode( ',', $get_array['plugins'] );
			} elseif ( isset( $get_array['plugin'] ) ) {
				$plugins[] = $get_array['plugin'];
			}

			// Check $_POST array cases.
			if ( isset( $post_array['plugins'] ) ) {
				$plugins = explode( ',', $post_array['plugins'] );
			} elseif ( isset( $post_array['plugin'] ) ) {
				$plugins[] = $post_array['plugin'];
			}
			if ( isset( $plugins ) ) {
				foreach ( $plugins as $plugin_file ) {
					// Get plugin directory name.
					$plugin_dir = $this->get_plugin_dir( $plugin_file );

					// Set plugin to skip file changes alert.
					$this->skip_plugin_change_alerts( $plugin_dir );

					$plugin_file = WP_PLUGIN_DIR . '/' . $plugin_file;
					$plugin_data = get_plugin_data( $plugin_file, false, true );
					$this->plugin->alerts->Trigger(
						5004, array(
							'PluginFile' => $plugin_file,
							'PluginData' => (object) array(
								'Name' => $plugin_data['Name'],
								'PluginURI' => $plugin_data['PluginURI'],
								'Version' => $plugin_data['Version'],
								'Author' => $plugin_data['Author'],
								'Network' => $plugin_data['Network'] ? 'True' : 'False',
							),
						)
					);
				}
			}
		}

		// Update theme.
		if ( in_array( $action, array( 'upgrade-theme', 'update-theme', 'update-selected-themes' ) ) && current_user_can( 'install_themes' ) ) {
			// Themes.
			$themes = array();

			// Check $_GET array cases.
			if ( isset( $get_array['slug'] ) || isset( $get_array['theme'] ) ) {
				$themes[] = isset( $get_array['slug'] ) ? $get_array['slug'] : $get_array['theme'];
			} elseif ( isset( $get_array['themes'] ) ) {
				$themes = explode( ',', $get_array['themes'] );
			}

			// Check $_POST array cases.
			if ( isset( $post_array['slug'] ) || isset( $post_array['theme'] ) ) {
				$themes[] = isset( $post_array['slug'] ) ? $post_array['slug'] : $post_array['theme'];
			} elseif ( isset( $post_array['themes'] ) ) {
				$themes = explode( ',', $post_array['themes'] );
			}
			if ( isset( $themes ) ) {
				foreach ( $themes as $theme_name ) {
					$theme = wp_get_theme( $theme_name );
					$this->plugin->alerts->Trigger(
						5031, array(
							'Theme' => (object) array(
								'Name' => $theme->Name,
								'ThemeURI' => $theme->ThemeURI,
								'Description' => $theme->Description,
								'Author' => $theme->Author,
								'Version' => $theme->Version,
								'get_template_directory' => $theme->get_template_directory(),
							),
						)
					);

					// Set theme to skip file changes alert.
					$this->skip_theme_change_alerts( $theme_name );
				}
			}
		}

		// Install theme.
		if ( in_array( $action, array( 'install-theme', 'upload-theme' ) ) && current_user_can( 'install_themes' ) ) {
			$themes = array_diff( wp_get_themes(), $this->old_themes );
			foreach ( $themes as $name => $theme ) {
				$this->plugin->alerts->Trigger(
					5005, array(
						'Theme' => (object) array(
							'Name' => $theme->Name,
							'ThemeURI' => $theme->ThemeURI,
							'Description' => $theme->Description,
							'Author' => $theme->Author,
							'Version' => $theme->Version,
							'get_template_directory' => $theme->get_template_directory(),
						),
					)
				);
				// Add theme to site themes list.
				$this->set_site_themes( $name );
			}
		}

		// Uninstall theme.
		if ( in_array( $action, array( 'delete-theme' ) ) && current_user_can( 'install_themes' ) ) {
			foreach ( $this->GetRemovedThemes() as $index => $theme ) {
				$this->plugin->alerts->Trigger(
					5007, array(
						'Theme' => (object) array(
							'Name' => $theme->Name,
							'ThemeURI' => $theme->ThemeURI,
							'Description' => $theme->Description,
							'Author' => $theme->Author,
							'Version' => $theme->Version,
							'get_template_directory' => $theme->get_template_directory(),
						),
					)
				);

				// Set theme to skip file changes alert.
				$this->skip_theme_change_alerts( $theme->stylesheet );

				// Remove it from the list.
				$this->remove_site_theme( $theme->stylesheet );
			}
		}
	}

	/**
	 * Activated a theme.
	 *
	 * @param string $theme_name - Theme name.
	 */
	public function EventThemeActivated( $theme_name ) {
		$theme = null;
		foreach ( wp_get_themes() as $item ) {
			if ( $theme_name == $item->Name ) {
				$theme = $item;
				break;
			}
		}
		if ( null == $theme ) {
			return $this->LogError(
				'Could not locate theme named "' . $theme . '".',
				array(
					'ThemeName' => $theme_name,
					'Themes' => wp_get_themes(),
				)
			);
		}
		$this->plugin->alerts->Trigger(
			5006, array(
				'Theme' => (object) array(
					'Name' => $theme->Name,
					'ThemeURI' => $theme->ThemeURI,
					'Description' => $theme->Description,
					'Author' => $theme->Author,
					'Version' => $theme->Version,
					'get_template_directory' => $theme->get_template_directory(),
				),
			)
		);
	}

	/**
	 * Plugin creates/modifies posts.
	 *
	 * @param int    $post_id - Post ID.
	 * @param object $post - Post object.
	 */
	public function EventPluginPostCreate( $post_id, $post ) {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		// Filter $_REQUEST array for security.
		$get_array  = filter_input_array( INPUT_GET );
		$post_array = filter_input_array( INPUT_POST );

		// Check if Yoast SEO is active.
		$is_yoast = is_plugin_active( 'wordpress-seo/wp-seo.php' ) || is_plugin_active( 'wordpress-seo-premium/wp-seo-premium.php' );
		if ( $is_yoast && isset( $get_array['classic-editor'] ) ) {
			return;
		}

		$wp_actions = array( 'editpost', 'heartbeat', 'inline-save', 'trash', 'untrash' );
		if ( isset( $get_array['action'] ) && ! in_array( $get_array['action'], $wp_actions ) ) {
			if (
				! in_array( $post->post_type, array( 'attachment', 'revision', 'nav_menu_item', 'customize_changeset', 'custom_css' ), true )
				&& ! empty( $post->post_title )
			) {
				// Get post editor link.
				$editor_link = $this->GetEditorLink( $post );

				// If the plugin modify the post.
				if ( false !== strpos( $get_array['action'], 'edit' ) ) {
					$this->plugin->alerts->Trigger(
						2106, array(
							'PostID'             => $post->ID,
							'PostType'           => $post->post_type,
							'PostTitle'          => $post->post_title,
							'PostStatus'         => $post->post_status,
							'PostUrl'            => get_permalink( $post->ID ),
							$editor_link['name'] => $editor_link['value'],
						)
					);
				} else {
					$this->plugin->alerts->Trigger(
						5019, array(
							'PostID'             => $post->ID,
							'PostType'           => $post->post_type,
							'PostTitle'          => $post->post_title,
							'Username'           => 'Plugins',
							$editor_link['name'] => $editor_link['value'],
						)
					);
				}
			}
		}

		if ( isset( $post_array['action'] ) && ! in_array( $post_array['action'], $wp_actions ) ) {
			if (
				! in_array( $post->post_type, array( 'attachment', 'revision', 'nav_menu_item', 'customize_changeset', 'custom_css' ), true )
				&& ! empty( $post->post_title )
			) {
				// If the plugin modify the post.
				if ( false !== strpos( $post_array['action'], 'edit' ) ) {
					$editor_link = $this->GetEditorLink( $post );
					$this->plugin->alerts->Trigger(
						2106, array(
							'PostID'             => $post->ID,
							'PostType'           => $post->post_type,
							'PostTitle'          => $post->post_title,
							$editor_link['name'] => $editor_link['value'],
						)
					);
				} elseif (
					( isset( $post_array['page'] ) && 'woocommerce-bulk-stock-management' === $post_array['page'] ) // If page index is set in post array then ignore.
					|| (
						isset( $post_array['mainwpsignature'] )
						&& ( 'restore' === $post_array['action'] || 'unpublish' === $post_array['action'] || 'publish' === $post_array['action'] )
					) // OR If the request is coming from MainWP then ignore.
				) {
					// Ignore WooCommerce Bulk Stock Management page.
					// OR MainWP plugin requests.
				} else {
					$editor_link = $this->GetEditorLink( $post );
					$this->plugin->alerts->Trigger(
						5019, array(
							'PostID'             => $post->ID,
							'PostType'           => $post->post_type,
							'PostTitle'          => $post->post_title,
							'Username'           => 'Plugins',
							$editor_link['name'] => $editor_link['value'],
						)
					);
				}
			}
		}
	}

	/**
	 * Plugin deletes posts.
	 *
	 * @param integer $post_id - Post ID.
	 */
	public function EventPluginPostDelete( $post_id ) {
		// Filter $_REQUEST array for security.
		$get_array  = filter_input_array( INPUT_GET );
		$post_array = filter_input_array( INPUT_POST );

		if ( empty( $get_array['action'] ) && isset( $get_array['page'] ) ) {
			$post = get_post( $post_id );
			if ( ! in_array( $post->post_type, array( 'attachment', 'revision', 'nav_menu_item', 'customize_changeset', 'custom_css' ) )
				|| ! empty( $post->post_title ) ) {
				$this->plugin->alerts->Trigger(
					5025, array(
						'PostID'    => $post->ID,
						'PostType'  => $post->post_type,
						'PostTitle' => $post->post_title,
						'Username'  => 'Plugins',
					)
				);
			}
		}

		if ( empty( $post_array['action'] ) && isset( $post_array['page'] ) ) {
			$post = get_post( $post_id );
			if ( ! in_array( $post->post_type, array( 'attachment', 'revision', 'nav_menu_item', 'customize_changeset', 'custom_css' ) )
				|| ! empty( $post->post_title ) ) {
				$this->plugin->alerts->Trigger(
					5025, array(
						'PostID'    => $post->ID,
						'PostType'  => $post->post_type,
						'PostTitle' => $post->post_title,
						'Username'  => 'Plugins',
					)
				);
			}
		}
	}

	/**
	 * Get removed themes.
	 *
	 * @return array of WP_Theme objects
	 */
	protected function GetRemovedThemes() {
		$result = $this->old_themes;
		foreach ( $result as $i => $theme ) {
			if ( file_exists( $theme->get_template_directory() ) ) {
				unset( $result[ $i ] );
			}
		}
		return array_values( $result );
	}

	/**
	 * Method: Handle plugin/theme install event
	 * from MainWP dashboard on child site.
	 *
	 * @param array $args - Array of arguments related to asset installed.
	 * @since 3.2.2
	 */
	public function mainwp_child_install_assets( $args ) {
		if ( empty( $args ) || ! is_array( $args ) ) {
			return;
		}

		// Verify the action from MainWP.
		if (
			isset( $args['action'] ) && 'install' === $args['action']
			&& isset( $args['success'] ) && ! empty( $args['success'] )
		) {
			if ( isset( $args['type'] ) && 'theme' === $args['type'] ) { // Installing theme.
				// Get theme name & object.
				$theme_slug = isset( $args['slug'] ) ? $args['slug'] : false;
				$theme_obj  = wp_get_theme( $theme_slug );

				// Check if theme exists.
				if ( $theme_obj->exists() ) {
					$this->plugin->alerts->Trigger(
						5005, array(
							'Theme' => (object) array(
								'Name'        => $theme_obj->Name,
								'ThemeURI'    => $theme_obj->ThemeURI,
								'Description' => $theme_obj->Description,
								'Author'      => $theme_obj->Author,
								'Version'     => $theme_obj->Version,
								'get_template_directory' => $theme_obj->get_template_directory(),
							),
						)
					);
					// Add theme to site themes list.
					$this->set_site_themes( $theme_slug );
				}
			} elseif ( isset( $args['type'] ) && 'plugin' === $args['type'] ) {
				// Get plugin slug.
				$plugin_slug = isset( $args['slug'] ) ? $args['slug'] : false;

				$plugins = get_plugins(); // Get all plugins.
				$plugin  = $plugins[ $plugin_slug ]; // Take out the plugin being installed.

				// Get plugin directory name.
				$plugin_dir = $this->get_plugin_dir( $plugin_slug );

				// Add plugin to site plugins list.
				$this->set_site_plugins( $plugin_dir );

				$plugin_path = plugin_dir_path( WP_PLUGIN_DIR . '/' . $plugin_slug );
				$this->plugin->alerts->Trigger(
					5000, array(
						'Plugin' => (object) array(
							'Name'            => $plugin['Name'],
							'PluginURI'       => $plugin['PluginURI'],
							'Version'         => $plugin['Version'],
							'Author'          => $plugin['Author'],
							'Network'         => $plugin['Network'] ? 'True' : 'False',
							'plugin_dir_path' => $plugin_path,
						),
					)
				);
			}
		}
	}

	/**
	 * Method: Handle plugin uninstall event
	 * from MainWP dashboard on child site.
	 *
	 * @param array $args - Array of arguments related to asset uninstalled.
	 * @since 3.2.2
	 */
	public function mainwp_child_uninstall_plugin( $args ) {
		if ( empty( $args ) || ! is_array( $args ) ) {
			return;
		}

		// Get MainWP post data.
		$post_array = filter_input_array( INPUT_POST );

		// Get plugins from MainWP.
		if ( isset( $post_array['plugin'] ) && ! empty( $post_array['plugin'] ) ) {
			$wp_plugins = explode( '||', $post_array['plugin'] );
		}

		// Verify actions from MainWP.
		if (
			isset( $args['action'] ) && 'delete' === $args['action']
			&& isset( $args['Name'] ) && ! empty( $args['Name'] )
			&& isset( $post_array['mainwpsignature'] ) && ! empty( $post_array['mainwpsignature'] )
		) {
			// Get plugin name.
			$plugin_name = $args['Name'];

			// Get plugin filename.
			$plugin_filename = $this->get_plugin_file_name( $plugin_name );

			if ( ! empty( $plugin_filename ) && in_array( $plugin_filename, $wp_plugins, true ) ) {
				$this->plugin->alerts->Trigger(
					5003, array(
						'PluginFile' => $plugin_filename,
						'PluginData' => (object) array(
							'Name' => $plugin_name,
						),
					)
				);

				// Get plugin directory name.
				$plugin_dir = $this->get_plugin_dir( $plugin_filename );

				// Set plugin to skip file changes alert.
				$this->skip_plugin_change_alerts( $plugin_dir );

				// Remove it from the list.
				$this->remove_site_plugin( $plugin_dir );
			}
		}
	}

	/**
	 * Method: Handle theme uninstall event
	 * from MainWP dashboard on child site.
	 *
	 * @param array $args - Array of arguments related to asset uninstalled.
	 * @since 3.2.2
	 */
	public function mainwp_child_uninstall_theme( $args ) {
		if ( empty( $args ) || ! is_array( $args ) ) {
			return;
		}

		// Get MainWP post data.
		$post_array = filter_input_array( INPUT_POST );

		// Get themes from MainWP.
		if ( isset( $post_array['theme'] ) && ! empty( $post_array['theme'] ) ) {
			$wp_themes = explode( '||', $post_array['theme'] );
		}

		// Verify actions from MainWP.
		if (
			isset( $args['action'] ) && 'delete' === $args['action']
			&& isset( $args['Name'] ) && ! empty( $args['Name'] )
			&& isset( $post_array['mainwpsignature'] ) && ! empty( $post_array['mainwpsignature'] )
		) {
			// Get theme object.
			$themes = $this->GetRemovedThemes();

			if ( ! empty( $themes ) ) {
				foreach ( $themes as $index => $theme ) {
					if ( ! empty( $theme ) && $theme instanceof WP_Theme && in_array( $theme->Name, $wp_themes, true ) ) {
						$this->plugin->alerts->Trigger(
							5007, array(
								'Theme' => (object) array(
									'Name'        => $theme->Name,
									'ThemeURI'    => $theme->ThemeURI,
									'Description' => $theme->Description,
									'Author'      => $theme->Author,
									'Version'     => $theme->Version,
									'get_template_directory' => $theme->get_template_directory(),
								),
							)
						);

						// Set theme to skip file changes alert.
						$this->skip_theme_change_alerts( $theme->stylesheet );

						// Remove it from the list.
						$this->remove_site_theme( $theme->stylesheet );
					}
				}
			}
		}
	}

	/**
	 * Method: Handle plugin activation event
	 * from MainWP dashboard on a child site.
	 *
	 * @param string $plugin - Plugin slug.
	 * @since 3.2.2
	 */
	public function mainwp_child_plugin_events( $plugin ) {
		// Check parameter.
		if ( empty( $plugin ) ) {
			return;
		}

		// Get MainWP post data.
		$post_array = filter_input_array( INPUT_POST );

		// Get plugins from MainWP.
		if ( isset( $post_array['plugin'] ) && ! empty( $post_array['plugin'] ) ) {
			$wp_plugins = explode( '||', $post_array['plugin'] );
		}

		if (
			isset( $post_array['mainwpsignature'] ) // Check MainWP signature.
			&& isset( $post_array['action'] ) // Check if action is set.
			&& isset( $post_array['function'] ) // Check if function is set.
			&& 'plugin_action' === $post_array['function']
			&& in_array( $plugin, $wp_plugins, true ) // Check if plugin being activate/deactivate is in the list of plugins from MainWP.
		) {
			if ( 'activate' === $post_array['action'] ) {
				$event = 5001;
			} elseif ( 'deactivate' === $post_array['action'] ) {
				$event = 5002;
			}

			$plugin      = WP_PLUGIN_DIR . '/' . $plugin;
			$plugin_data = get_plugin_data( $plugin, false, true );
			$this->plugin->alerts->Trigger(
				$event, array(
					'PluginFile' => $plugin,
					'PluginData' => (object) array(
						'Name'      => $plugin_data['Name'],
						'PluginURI' => $plugin_data['PluginURI'],
						'Version'   => $plugin_data['Version'],
						'Author'    => $plugin_data['Author'],
						'Network'   => $plugin_data['Network'] ? 'True' : 'False',
					),
				)
			);
		} elseif (
			isset( $post_array['mainwpsignature'] ) // Check MainWP signature.
			&& isset( $post_array['function'] ) // Check if function is set.
			&& 'installplugintheme' === $post_array['function']
			&& isset( $post_array['type'] ) // Check if type is set.
			&& 'plugin' === $post_array['type']
		) {
			$plugin      = WP_PLUGIN_DIR . '/' . $plugin;
			$plugin_data = get_plugin_data( $plugin, false, true );
			$this->plugin->alerts->Trigger(
				5001, array(
					'PluginFile' => $plugin,
					'PluginData' => (object) array(
						'Name'      => $plugin_data['Name'],
						'PluginURI' => $plugin_data['PluginURI'],
						'Version'   => $plugin_data['Version'],
						'Author'    => $plugin_data['Author'],
						'Network'   => $plugin_data['Network'] ? 'True' : 'False',
					),
				)
			);
		}
	}

	/**
	 * Method: Handle plugin/theme update event
	 * from MainWP dashboard on child site.
	 *
	 * @param array $args - Array of arguments related to asset updated.
	 * @since 3.2.2
	 */
	public function mainwp_child_update_assets( $args ) {
		if ( empty( $args ) || ! is_array( $args ) ) {
			return;
		}

		// Get MainWP post data.
		$post_array = filter_input_array( INPUT_POST );

		// Check type.
		if (
			isset( $post_array['function'] ) && 'upgradeplugintheme' === $post_array['function']
			&& isset( $post_array['mainwpsignature'] ) && ! empty( $post_array['mainwpsignature'] )
			&& isset( $post_array['list'] ) && ! empty( $post_array['list'] )
			&& isset( $args['type'] ) && ! empty( $args['type'] )
			&& isset( $args['name'] ) && ! empty( $args['name'] )
		) {
			if ( 'theme' === $args['type'] ) {
				// Site themes updated.
				$site_themes = explode( ',', $post_array['list'] );

				// Theme name.
				$theme_name = $args['name'];

				// Get theme object.
				$theme = $this->get_theme_by_name( $theme_name );

				if ( ! empty( $theme ) && $theme instanceof WP_Theme && in_array( $theme->stylesheet, $site_themes, true ) ) {
					$this->plugin->alerts->Trigger(
						5031, array(
							'Theme' => (object) array(
								'Name'        => $theme->Name,
								'ThemeURI'    => $theme->ThemeURI,
								'Description' => $theme->Description,
								'Author'      => $theme->Author,
								'Version'     => $theme->Version,
								'get_template_directory' => $theme->get_template_directory(),
							),
						)
					);

					// Set theme to skip file changes alert.
					$this->skip_theme_change_alerts( $theme->stylesheet );
				}
			} elseif ( 'plugin' === $args['type'] ) {
				// Plugin name.
				$plugin_name = $args['name'];

				// Get plugin filename.
				$plugin_file = $this->get_plugin_file_name( $plugin_name );

				// If plugin file is empty then return.
				if ( empty( $plugin_file ) ) {
					return;
				}

				// Get plugin directory name.
				$plugin_dir = $this->get_plugin_dir( $plugin_file );

				// Set plugin to skip file changes alert.
				$this->skip_plugin_change_alerts( $plugin_dir );

				$plugin_file = WP_PLUGIN_DIR . '/' . $plugin_file;
				$plugin_data = get_plugin_data( $plugin_file, false, true );

				$this->plugin->alerts->Trigger(
					5004, array(
						'PluginFile' => $plugin_file,
						'PluginData' => (object) array(
							'Name'      => $plugin_data['Name'],
							'PluginURI' => $plugin_data['PluginURI'],
							'Version'   => $plugin_data['Version'],
							'Author'    => $plugin_data['Author'],
							'Network'   => $plugin_data['Network'] ? 'True' : 'False',
						),
					)
				);
			}
		}
	}

	/**
	 * Get event code by post type.
	 *
	 * @param object $post - Post object.
	 * @param int    $type_post - Code for post.
	 * @param int    $type_page - Code for page.
	 * @param int    $type_custom - Code for custom post type.
	 */
	protected function GetEventTypeForPostType( $post, $type_post, $type_page, $type_custom ) {
		if ( empty( $post ) || ! isset( $post->post_type ) ) {
			return false;
		}

		switch ( $post->post_type ) {
			case 'page':
				return $type_page;
			case 'post':
				return $type_post;
			default:
				return $type_custom;
		}
	}

	/**
	 * Get editor link.
	 *
	 * @param object $post - The post object.
	 * @return array $editor_link name and value link.
	 */
	private function GetEditorLink( $post ) {
		$name = 'EditorLink';
		$name .= ( 'page' == $post->post_type ) ? 'Page' : 'Post' ;
		$value = get_edit_post_link( $post->ID );
		$editor_link = array(
			'name'  => $name,
			'value' => $value,
		);
		return $editor_link;
	}

	/**
	 * Method: Add plugins to site plugins list.
	 *
	 * @param string $plugin – Plugin directory name.
	 */
	public function set_site_plugins( $plugin = '' ) {
		// Call the wrapper function to setup content.
		$this->set_site_content( 'plugin', $plugin );
	}

	/**
	 * Method: Add themes to site themes list.
	 *
	 * @param string $theme – Theme name.
	 */
	public function set_site_themes( $theme = '' ) {
		// Call the wrapper function to setup content.
		$this->set_site_content( 'theme', $theme );
	}

	/**
	 * Method: Add plugins or themes to site content class member.
	 *
	 * @param string $type – Type of content i.e. `plugin` or `theme`.
	 * @param string $content – Name of the content. It can be a plugin or a theme.
	 */
	public function set_site_content( $type, $content = '' ) {
		/**
		 * $type should not be empty.
		 * Possible values: `plugin` | `theme`.
		 */
		if ( empty( $type ) ) {
			return;
		}

		// Set content option.
		$content_option = 'site_content';

		// Get site plugins options.
		$this->site_content = $this->plugin->GetGlobalOption( $content_option, false );

		/**
		 * Initiate the content option.
		 *
		 * If option does not exists then set the option.
		 */
		if ( false === $this->site_content ) {
			$this->site_content = new stdClass(); // New stdClass object.
			$plugins = $this->get_site_plugins(); // Get plugins on the site.
			$themes  = $this->get_site_themes(); // Get themes on the site.

			// Assign the plugins to content object.
			foreach ( $plugins as $index => $plugin ) {
				$this->site_content->plugins[] = strtolower( $plugin );
				$this->site_content->skip_plugins[] = strtolower( $plugin );
			}

			// Assign the themes to content object.
			foreach ( $themes as $index => $theme ) {
				$this->site_content->themes[] = strtolower( $theme );
				$this->site_content->skip_themes[] = strtolower( $theme );
			}

			$this->plugin->SetGlobalOption( $content_option, $this->site_content );
		}

		// Check if type is plugin and content is not empty.
		if ( 'plugin' === $type && ! empty( $content ) ) {
			// If the plugin is not already present in the current list then.
			if ( ! in_array( $content, $this->site_content->plugins, true ) ) {
				// Add the plugin to the list and save it.
				$this->site_content->plugins[] = strtolower( $content );
				$this->site_content->skip_plugins[] = strtolower( $content );
				$this->plugin->SetGlobalOption( $content_option, $this->site_content );
			}
		} elseif ( 'theme' === $type && ! empty( $content ) ) {
			// If the theme is not already present in the current list then.
			if ( ! in_array( $content, $this->site_content->themes, true ) ) {
				// Add the theme to the list and save it.
				$this->site_content->themes[] = strtolower( $content );
				$this->site_content->skip_themes[] = strtolower( $content );
				$this->plugin->SetGlobalOption( $content_option, $this->site_content );
			}
		}
	}

	/**
	 * Method: Remove plugin from site plugins list.
	 *
	 * @param string $plugin – Plugin name.
	 * @return bool
	 */
	public function remove_site_plugin( $plugin ) {
		return $this->remove_site_content( 'plugin', $plugin );
	}

	/**
	 * Method: Remove theme from site themes list.
	 *
	 * @param string $theme – Theme name.
	 * @return bool
	 */
	public function remove_site_theme( $theme ) {
		return $this->remove_site_content( 'theme', $theme );
	}

	/**
	 * Method: Remove content from site content list.
	 *
	 * @param string $type – Type of content.
	 * @param string $content – Name of content.
	 * @return bool
	 */
	public function remove_site_content( $type, $content ) {
		/**
		 * $type should not be empty.
		 * Possible values: `plugin` | `theme`.
		 */
		if ( empty( $type ) ) {
			return;
		}

		// Check if $content is empty.
		if ( empty( $content ) ) {
			return false;
		}

		// Check if the plugin is already present in the list.
		if ( 'plugin' === $type && in_array( $content, $this->site_content->plugins, true ) ) {
			// Get key of the plugin from plugins array.
			$key = array_search( $content, $this->site_content->plugins, true );

			// If key is found then remove it from the array and save the plugins list.
			if ( false !== $key ) {
				unset( $this->site_content->plugins[ $key ] );
				$this->plugin->SetGlobalOption( 'site_content', $this->site_content );
				return true;
			}
		} elseif ( 'theme' === $type && in_array( $content, $this->site_content->themes, true ) ) {
			// Get key of the theme from themes array.
			$key = array_search( $content, $this->site_content->themes, true );

			// If key is found then remove it from the array and save the themes list.
			if ( false !== $key ) {
				unset( $this->site_content->themes[ $key ] );
				$this->plugin->SetGlobalOption( 'site_content', $this->site_content );
				return true;
			}
		}
		return false;
	}

	/**
	 * Method: Add plugin to skip file changes alert list.
	 *
	 * @param string $plugin – Plugin name.
	 * @return bool
	 */
	public function skip_plugin_change_alerts( $plugin ) {
		return $this->skip_content_change_alerts( 'plugin', $plugin );
	}

	/**
	 * Method: Add theme to skip file changes alert list.
	 *
	 * @param string $theme – Theme name.
	 * @return bool
	 */
	public function skip_theme_change_alerts( $theme ) {
		return $this->skip_content_change_alerts( 'theme', $theme );
	}

	/**
	 * Method: Add content to skip file changes alert list.
	 *
	 * @param string $type – Type of content.
	 * @param string $content – Name of content.
	 * @return bool
	 */
	public function skip_content_change_alerts( $type, $content ) {
		/**
		 * $type should not be empty.
		 * Possible values: `plugin` | `theme`.
		 */
		if ( empty( $type ) ) {
			return;
		}

		// Check if $content is empty.
		if ( empty( $content ) ) {
			return false;
		}

		// Add plugin to skip file alerts list.
		if ( 'plugin' === $type ) {
			$this->site_content->skip_plugins[] = $content;
			$this->plugin->SetGlobalOption( 'site_content', $this->site_content );
		} elseif ( 'theme' === $type ) {
			// Add theme to skip file alerts list.
			$this->site_content->skip_themes[] = $content;
			$this->plugin->SetGlobalOption( 'site_content', $this->site_content );
		}
		return true;
	}

	/**
	 * Method: Get site plugin directories.
	 *
	 * @return array
	 */
	public function get_site_plugins() {
		// Get plugins.
		$plugins = array_keys( get_plugins() );

		// Remove php file name from the plugins.
		$plugins = array_map( array( $this, 'get_plugin_dir' ), $plugins );

		// Return plugins.
		return $plugins;
	}

	/**
	 * Method: Get site themes.
	 *
	 * @return array
	 */
	public function get_site_themes() {
		// Get themes.
		return array_keys( wp_get_themes() );
	}

	/**
	 * Method: Remove the PHP file after `/` in the plugin
	 * directory name.
	 *
	 * For example, it will remove `/akismet.php` from
	 * `akismet/akismet.php`.
	 *
	 * @param string $plugin – Plugin name.
	 * @return string
	 */
	public function get_plugin_dir( $plugin ) {
		$position = strpos( $plugin, '/' );
		if ( false !== $position ) {
			$plugin = substr_replace( $plugin, '', $position );
		}
		return $plugin;
	}

	/**
	 * Method: Return plugin file name.
	 *
	 * @param string $plugin_name - Plugin name.
	 * @return string
	 */
	public function get_plugin_file_name( $plugin_name ) {
		// Verify parameter.
		if ( empty( $plugin_name ) ) {
			return;
		}

		// Get all plugins.
		$all_plugins = get_plugins();

		$plugin_filename = '';
		if ( ! empty( $all_plugins ) && is_array( $all_plugins ) ) {
			foreach ( $all_plugins as $plugin_file => $plugin_data ) {
				if ( $plugin_name === $plugin_data['Name'] ) {
					$plugin_filename = $plugin_file;
				}
			}
		}
		return $plugin_filename;
	}

	/**
	 * Method: Search and return theme object by name.
	 *
	 * @param string $theme_name - Theme name.
	 * @return WP_Theme
	 */
	public function get_theme_by_name( $theme_name ) {
		// Check if $theme_name is empty.
		if ( empty( $theme_name ) ) {
			return;
		}

		// Get all themes.
		$all_themes = wp_get_themes();

		$theme = '';
		if ( ! empty( $all_themes ) ) {
			foreach ( $all_themes as $stylesheet => $theme_obj ) {
				if ( $theme_name === $theme_obj->Name ) {
					$theme = $theme_obj;
				}
			}
		}
		return $theme;
	}
}
