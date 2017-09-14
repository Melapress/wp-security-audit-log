<?php
/**
 * @package Wsal
 * @subpackage Sensors
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
 */
class WSAL_Sensors_PluginsThemes extends WSAL_AbstractSensor {

	protected $old_themes = array();
	protected $old_plugins = array();

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
	}

	/**
	 * Triggered when a user accesses the admin area.
	 */
	public function EventAdminInit() {
		$this->old_themes = wp_get_themes();
		$this->old_plugins = get_plugins();
	}

	/**
	 * Install, uninstall, activate, deactivate, upgrade and update.
	 */
	public function EventAdminShutdown()
	{
		$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != "-1")  ? $_REQUEST['action'] : '';
		$action = (isset($_REQUEST['action2']) && $_REQUEST['action2'] != "-1") ? $_REQUEST['action2'] : $action;
		$actype = basename($_SERVER['SCRIPT_NAME'], '.php');
		$is_themes = $actype == 'themes';
		$is_plugins = $actype == 'plugins';

		// install plugin
		if (in_array($action, array('install-plugin', 'upload-plugin')) && current_user_can("install_plugins")) {
			$plugin = array_values(array_diff(array_keys(get_plugins()), array_keys($this->old_plugins)));
			if (count($plugin) != 1) {
				return $this->LogError(
					'Expected exactly one new plugin but found ' . count($plugin),
					array('NewPlugin' => $plugin, 'OldPlugins' => $this->old_plugins, 'NewPlugins' => get_plugins())
				);
			}
			$pluginPath = $plugin[0];
			$plugin = get_plugins();
			$plugin = $plugin[$pluginPath];
			$pluginPath = plugin_dir_path(WP_PLUGIN_DIR . '/' . $pluginPath[0]);
			$this->plugin->alerts->Trigger(5000, array(
				'Plugin' => (object)array(
					'Name' => $plugin['Name'],
					'PluginURI' => $plugin['PluginURI'],
					'Version' => $plugin['Version'],
					'Author' => $plugin['Author'],
					'Network' => $plugin['Network'] ? 'True' : 'False',
					'plugin_dir_path' => $pluginPath,
				),
			));
		}

		// activate plugin
		if ($is_plugins && in_array($action, array('activate', 'activate-selected')) && current_user_can("activate_plugins")) {
			if (isset($_REQUEST['plugin'])) {
				if (!isset($_REQUEST['checked'])) {
					$_REQUEST['checked'] = array();
				}
				$_REQUEST['checked'][] = $_REQUEST['plugin'];
			}
			foreach ($_REQUEST['checked'] as $pluginFile) {
				$pluginFile = WP_PLUGIN_DIR . '/' . $pluginFile;
				$pluginData = get_plugin_data($pluginFile, false, true);
				$this->plugin->alerts->Trigger(5001, array(
					'PluginFile' => $pluginFile,
					'PluginData' => (object)array(
						'Name' => $pluginData['Name'],
						'PluginURI' => $pluginData['PluginURI'],
						'Version' => $pluginData['Version'],
						'Author' => $pluginData['Author'],
						'Network' => $pluginData['Network'] ? 'True' : 'False',
					),
				));
			}
		}

		// deactivate plugin
		if ($is_plugins && in_array($action, array('deactivate', 'deactivate-selected')) && current_user_can("activate_plugins")) {
			if (isset($_REQUEST['plugin'])) {
				if (!isset($_REQUEST['checked'])) {
					$_REQUEST['checked'] = array();
				}
				$_REQUEST['checked'][] = $_REQUEST['plugin'];
			}
			foreach ($_REQUEST['checked'] as $pluginFile) {
				$pluginFile = WP_PLUGIN_DIR . '/' . $pluginFile;
				$pluginData = get_plugin_data($pluginFile, false, true);
				$this->plugin->alerts->Trigger(5002, array(
					'PluginFile' => $pluginFile,
					'PluginData' => (object)array(
						'Name' => $pluginData['Name'],
						'PluginURI' => $pluginData['PluginURI'],
						'Version' => $pluginData['Version'],
						'Author' => $pluginData['Author'],
						'Network' => $pluginData['Network'] ? 'True' : 'False',
					),
				));
			}
		}

		// uninstall plugin
		if ($is_plugins && in_array($action, array('delete-selected')) && current_user_can("delete_plugins")) {
			if (!isset($_REQUEST['verify-delete'])) {
				// first step, before user approves deletion
				// TODO store plugin data in session here
			} else {
				// second step, after deletion approval
				// TODO use plugin data from session
				foreach ($_REQUEST['checked'] as $pluginFile) {
					$pluginName = basename($pluginFile, '.php');
					$pluginName = str_replace(array('_', '-', '  '), ' ', $pluginName);
					$pluginName = ucwords($pluginName);
					$pluginFile = WP_PLUGIN_DIR . '/' . $pluginFile;
					$this->plugin->alerts->Trigger(5003, array(
						'PluginFile' => $pluginFile,
						'PluginData' => (object)array(
							'Name' => $pluginName,
						),
					));
				}
			}
		}

		// uninstall plugin for Wordpress version 4.6
		if (in_array($action, array('delete-plugin')) && current_user_can("delete_plugins")) {
			if (isset($_REQUEST['plugin'])) {
				$pluginFile = WP_PLUGIN_DIR . '/' . $_REQUEST['plugin'];
				$pluginName = basename($pluginFile, '.php');
				$pluginName = str_replace(array('_', '-', '  '), ' ', $pluginName);
				$pluginName = ucwords($pluginName);
				$this->plugin->alerts->Trigger(5003, array(
					'PluginFile' => $pluginFile,
					'PluginData' => (object)array(
						'Name' => $pluginName,
					),
				));
			}
		}

		// upgrade plugin
		if (in_array($action, array('upgrade-plugin', 'update-plugin', 'update-selected')) && current_user_can("update_plugins")) {
			$plugins = array();
			if (isset($_REQUEST['plugins'])) {
				$plugins = explode(",", $_REQUEST['plugins']);
			} else if (isset($_REQUEST['plugin'])) {
				$plugins[] = $_REQUEST['plugin'];
			}
			if (isset($plugins)) {
				foreach ($plugins as $pluginFile) {
					$pluginFile = WP_PLUGIN_DIR . '/' . $pluginFile;
					$pluginData = get_plugin_data($pluginFile, false, true);
					$this->plugin->alerts->Trigger(5004, array(
						'PluginFile' => $pluginFile,
						'PluginData' => (object)array(
							'Name' => $pluginData['Name'],
							'PluginURI' => $pluginData['PluginURI'],
							'Version' => $pluginData['Version'],
							'Author' => $pluginData['Author'],
							'Network' => $pluginData['Network'] ? 'True' : 'False',
						),
					));
				}
			}
		}

		// update theme
		if (in_array($action, array('upgrade-theme', 'update-theme', 'update-selected-themes')) && current_user_can("install_themes")) {
			$themes = array();
			if (isset($_REQUEST['slug']) || isset($_REQUEST['theme'])) {
				$themes[] = isset($_REQUEST['slug']) ? $_REQUEST['slug'] : $_REQUEST['theme'];
			} elseif (isset($_REQUEST['themes'])) {
				$themes = explode(",", $_REQUEST['themes']);
			}
			if (isset($themes)) {
				foreach ($themes as $theme_name) {
					$theme = wp_get_theme($theme_name);
					$this->plugin->alerts->Trigger(5031, array(
						'Theme' => (object)array(
							'Name' => $theme->Name,
							'ThemeURI' => $theme->ThemeURI,
							'Description' => $theme->Description,
							'Author' => $theme->Author,
							'Version' => $theme->Version,
							'get_template_directory' => $theme->get_template_directory(),
						),
					));
				}
			}
		}

		// install theme
		if (in_array($action, array('install-theme', 'upload-theme')) && current_user_can("install_themes")) {
			$themes = array_diff(wp_get_themes(), $this->old_themes);
			foreach ($themes as $theme) {
				$this->plugin->alerts->Trigger(5005, array(
					'Theme' => (object)array(
						'Name' => $theme->Name,
						'ThemeURI' => $theme->ThemeURI,
						'Description' => $theme->Description,
						'Author' => $theme->Author,
						'Version' => $theme->Version,
						'get_template_directory' => $theme->get_template_directory(),
					),
				));
			}
		}

		// uninstall theme
		if (in_array($action, array('delete-theme')) && current_user_can("install_themes")) {
			foreach ($this->GetRemovedThemes() as $theme) {
				$this->plugin->alerts->Trigger(5007, array(
					'Theme' => (object)array(
						'Name' => $theme->Name,
						'ThemeURI' => $theme->ThemeURI,
						'Description' => $theme->Description,
						'Author' => $theme->Author,
						'Version' => $theme->Version,
						'get_template_directory' => $theme->get_template_directory(),
					),
				));
			}
		}
	}

	/**
	 * Activated a theme.
	 * @param string $themeName name
	 */
	public function EventThemeActivated($themeName)
	{
		$theme = null;
		foreach (wp_get_themes() as $item) {
			if ($item->Name == $themeName) {
				$theme = $item;
				break;
			}
		}
		if ($theme == null) {
			return $this->LogError(
				'Could not locate theme named "' . $theme . '".',
				array('ThemeName' => $themeName, 'Themes' => wp_get_themes())
			);
		}
		$this->plugin->alerts->Trigger(5006, array(
			'Theme' => (object)array(
				'Name' => $theme->Name,
				'ThemeURI' => $theme->ThemeURI,
				'Description' => $theme->Description,
				'Author' => $theme->Author,
				'Version' => $theme->Version,
				'get_template_directory' => $theme->get_template_directory(),
			),
		));
	}

	/**
	 * Plugin creates/modifies posts.
	 *
	 * @param int 	 $post_id - Post ID.
	 * @param object $post - Post object.
	 */
	public function EventPluginPostCreate( $post_id, $post ) {
		$WPActions = array( 'editpost', 'heartbeat', 'inline-save', 'trash', 'untrash' );
		if ( isset( $_REQUEST['action'] ) && ! in_array( $_REQUEST['action'], $WPActions ) ) {
			if ( ! in_array( $post->post_type, array( 'attachment', 'revision', 'nav_menu_item', 'customize_changeset', 'custom_css' ) )
				|| ! empty( $post->post_title ) ) {
				// If the plugin modify the post.
				if ( false !== strpos( $_REQUEST['action'], 'edit' ) ) {
					$event = $this->GetEventTypeForPostType( $post, 2106, 2107, 2108 );
					$editorLink = $this->GetEditorLink( $post );
					$this->plugin->alerts->Trigger( $event, array(
						'PostID' 	=> $post->ID,
						'PostType' 	=> $post->post_type,
						'PostTitle'	=> $post->post_title,
						$editorLink['name'] => $editorLink['value'],
					) );
				} else {
					$event = $this->GetEventTypeForPostType( $post, 5019, 5020, 5021 );
					$this->plugin->alerts->Trigger( $event, array(
						'PostID' 	=> $post->ID,
						'PostType' 	=> $post->post_type,
						'PostTitle'	=> $post->post_title,
						'Username' 	=> 'Plugins',
					) );
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
		if ( empty( $_REQUEST['action'] ) && isset( $_REQUEST['page'] ) ) {
			$post = get_post( $post_id );
			if ( ! in_array( $post->post_type, array( 'attachment', 'revision', 'nav_menu_item', 'customize_changeset', 'custom_css' ) )
				|| ! empty( $post->post_title ) ) {
				$event = $this->GetEventTypeForPostType( $post, 5025, 5026, 5027 );
				$this->plugin->alerts->Trigger($event, array(
					'PostID' 	=> $post->ID,
					'PostType' 	=> $post->post_type,
					'PostTitle' => $post->post_title,
					'Username' 	=> 'Plugins',
				) );
			}
		}
	}

	/**
	 * Get removed themes.
	 * @return array of WP_Theme objects
	 */
	protected function GetRemovedThemes()
	{
		$result = $this->old_themes;
		foreach ($result as $i => $theme) {
			if (file_exists($theme->get_template_directory())) {
				unset($result[$i]);
			}
		}
		return array_values($result);
	}

	/**
	 * Get event code by post type.
	 */
	protected function GetEventTypeForPostType( $post, $typePost, $typePage, $typeCustom ) {
		if ( empty( $post ) || ! isset( $post->post_type ) ) {
			return false;
		}

		switch ( $post->post_type ) {
			case 'page':
				return $typePage;
			case 'post':
				return $typePost;
			default:
				return $typeCustom;
		}
	}

	/**
	 * Get editor link.
	 *
	 * @param object $post - The post object.
	 * @return array $aLink name and value link.
	 */
	private function GetEditorLink( $post ) {
		$name = 'EditorLink';
		$name .= ( $post->post_type == 'page' ) ? 'Page' : 'Post' ;
		$value = get_edit_post_link( $post->ID );
		$aLink = array(
			'name' 	=> $name,
			'value' => $value,
		);
		return $aLink;
	}
}
