<?php
/**
 * View: Help
 *
 * WSAL help page.
 *
 * @since 1.0.0
 * @package Wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Help Page.
 *
 * - Plugin Support
 * - Plugin Documentation
 *
 * @package Wsal
 */
class WSAL_Views_Help extends WSAL_AbstractView {

	/**
	 * WSAL Help Tabs.
	 *
	 * @var array
	 */
	private $wsal_help_tabs = array();

	/**
	 * Current Help Tab.
	 *
	 * @var string
	 */
	private $current_tab = '';

	/**
	 * Constructor.
	 */
	public function __construct( $plugin ) {
		parent::__construct( $plugin );
		add_action( 'admin_init', array( $this, 'setup_help_tabs' ) );
	}

	/**
	 * Setup help page tabs.
	 */
	public function setup_help_tabs() {
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : false; // phpcs:ignore

		// Verify that the current page is WSAL settings page.
		if ( empty( $page ) || $this->GetSafeViewName() !== $page ) {
			return;
		}

		// Tab links.
		$wsal_help_tabs = array(
			'help'        => array(
				'name'     => __( 'Help', 'wp-security-audit-log' ),
				'link'     => $this->GetUrl(),
				'render'   => array( $this, 'tab_help' ),
				'priority' => 10,
			),
		);

		if ( $this->_plugin->settings()->CurrentUserCan( 'edit' ) ) {
			$wsal_help_tabs['contact'] = array(
				'name'     => __( 'Contact Us', 'wp-security-audit-log' ),
				'link'     => add_query_arg( 'tab', 'contact', $this->GetUrl() ),
				'render'   => array( $this, 'tab_contact_us' ),
				'priority' => 15,
			);
			$wsal_help_tabs['system-info'] = array(
				'name'     => __( 'System Info', 'wp-security-audit-log' ),
				'link'     => add_query_arg( 'tab', 'system-info', $this->GetUrl() ),
				'render'   => array( $this, 'tab_system_info' ),
				'priority' => 20,
			);
		}

		/**
		 * Filter: `wsal_help_tabs`
		 *
		 * Help tabs structure:
		 *     $wsal_help_tabs['unique-tab-id'] = array(
		 *         'name'     => Name of the tab,
		 *         'link'     => Link of the tab,
		 *         'render'   => This function is used to render HTML elements in the tab,
		 *         'priority' => Priority of the tab,
		 *     );
		 *
		 * @param array $wsal_help_tabs – Array of WSAL Help Tabs.
		 */
		$wsal_help_tabs = apply_filters( 'wsal_help_tabs', $wsal_help_tabs );

		// Sort by priority.
		array_multisort( array_column( $wsal_help_tabs, 'priority' ), SORT_ASC, $wsal_help_tabs );

		$this->wsal_help_tabs = $wsal_help_tabs;

		// Get the current tab.
		$current_tab       = filter_input( INPUT_GET, 'tab', FILTER_SANITIZE_STRING );
		$this->current_tab = empty( $current_tab ) ? 'help' : $current_tab;
	}

	/**
	 * Method: Get View Title.
	 */
	public function GetTitle() {
		return __( 'Help', 'wp-security-audit-log' );
	}

	/**
	 * Method: Get View Icon.
	 */
	public function GetIcon() {
		return 'dashicons-sos';
	}

	/**
	 * Method: Get View Name.
	 */
	public function GetName() {
		return __( 'Help & Contact Us', 'wp-security-audit-log' );
	}

	/**
	 * Method: Get View Weight.
	 */
	public function GetWeight() {
		return 13;
	}

	/**
	 * Method: Get View Header.
	 */
	public function Header() {
		wp_enqueue_style(
			'extensions',
			$this->_plugin->GetBaseUrl() . '/css/extensions.css',
			array(),
			filemtime( $this->_plugin->GetBaseDir() . '/css/extensions.css' )
		);
	}

	/**
	 * Method: Get View.
	 */
	public function Render() {
		$can_current_user_edit = $this->_plugin->settings()->CurrentUserCan( 'edit' );
		?>
		<nav id="wsal-tabs" class="nav-tab-wrapper">
			<?php
			foreach ( $this->wsal_help_tabs as $tab_id => $tab ) :
				if ( 'system-info' !== $this->current_tab || ( 'system-info' === $this->current_tab && $can_current_user_edit ) ) :
					?>
					<a href="<?php echo esc_url( $tab['link'] ); ?>" class="nav-tab <?php echo ( $tab_id === $this->current_tab ) ? 'nav-tab-active' : false; ?>"><?php echo esc_html( $tab['name'] ); ?></a>
					<?php
				endif;
			endforeach;
			?>
		</nav>
		<div class="nav-tabs">
			<div class="wsal-help-sidebar our-wordpress-plugins side-bar"><?php $this->sidebar(); ?></div>
			<div class="wsal-help-main">
				<?php
				if ( ! empty( $this->current_tab ) && ! empty( $this->wsal_help_tabs[ $this->current_tab ]['render'] ) ) {
					if ( 'system-info' !== $this->current_tab || ( 'system-info' === $this->current_tab && $can_current_user_edit ) ) {
						call_user_func( $this->wsal_help_tabs[ $this->current_tab ]['render'] );
					}
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Tab: Help.
	 */
	public function tab_help() {
		?>
		<div class="wsal-help-section">
			<h2 class="wsal-tab__heading"><?php esc_html_e( 'Getting Started', 'wp-security-audit-log' ); ?></h2>
			<p><?php esc_html_e( 'Getting started with WP Activity Log is really easy; once the plugin is installed it will automatically keep a log of everything that is happening on your website and you do not need to do anything. Watch the video below for a quick overview of the plugin.', 'wp-security-audit-log' ); ?></p>
			<p><iframe class="wsal-youtube-embed" width="100%" height="315" src="https://www.youtube.com/embed/pgFEMIvKFTA?rel=0" frameborder="0" allowfullscreen></iframe></p>
		</div>
		<div class="wsal-help-section">
			<h2 class="wsal-tab__heading"><?php esc_html_e( 'Plugin Support', 'wp-security-audit-log' ); ?></h2>
			<p>
				<?php esc_html_e( 'Have you encountered or noticed any issues while using WP Activity Log plugin?', 'wp-security-audit-log' ); ?>
				<?php esc_html_e( 'Or you want to report something to us? Click any of the options below to post on the plugin\'s forum or contact our support directly.', 'wp-security-audit-log' ); ?>
			</p><p>
				<a class="button" rel="noopener noreferrer" href="https://wordpress.org/support/plugin/wp-security-audit-log" target="_blank"><?php esc_html_e( 'Free Support Forum', 'wp-security-audit-log' ); ?></a>
				&nbsp;&nbsp;&nbsp;&nbsp;
				<a class="button" rel="noopener noreferrer" href="https://wpactivitylog.com/support/submit-ticket/?utm_source=plugin&utm_medium=referral&utm_campaign=WSAL&utm_content=free+support+email" target="_blank"><?php esc_html_e( 'Free Support Email', 'wp-security-audit-log' ); ?></a>
			</p>
		</div>
		<div class="wsal-help-section">
			<h2 class="wsal-tab__heading"><?php esc_html_e( 'Plugin Documentation', 'wp-security-audit-log' ); ?></h2>
			<p>
				<?php esc_html_e( 'For more technical information about the WP Activity Log plugin please visit the plugin’s knowledge base.', 'wp-security-audit-log' ); ?>
				<?php esc_html_e( 'Refer to the list of WordPress security events for a complete list of Events and IDs that the plugin uses to keep a log of all the changes in the WordPress activity log.', 'wp-security-audit-log' ); ?>
			</p><p>
				<a class="button" rel="noopener noreferrer" href="https://wpactivitylog.com/?utm_source=plugin&utm_medium=referral&utm_campaign=WSAL&utm_content=plugin+website" target="_blank"><?php esc_html_e( 'Plugin Website', 'wp-security-audit-log' ); ?></a>
				&nbsp;&nbsp;&nbsp;&nbsp;
				<a class="button" rel="noopener noreferrer" href="https://wpactivitylog.com/support/kb/?utm_source=plugin&utm_medium=referral&utm_campaign=WSAL&utm_content=knowledge+base" target="_blank"><?php esc_html_e( 'Knowledge Base', 'wp-security-audit-log' ); ?></a>
				&nbsp;&nbsp;&nbsp;&nbsp;
				<a class="button" rel="noopener noreferrer" href="https://wpactivitylog.com/support/kb/list-wordpress-activity-log-event-ids/?utm_source=plugin&utm_medium=referral&utm_campaign=WSAL&utm_content=list+events" target="_blank"><?php esc_html_e( 'List of activity logs event IDs', 'wp-security-audit-log' ); ?></a>
			</p>
		</div>
		<div class="wsal-help-section">
			<h2 class="wsal-tab__heading"><?php esc_html_e( 'Rate WP Activity Log', 'wp-security-audit-log' ); ?></h2>
			<p>
				<?php esc_html_e( 'We work really hard to deliver a plugin that enables you to keep a record of all the changes that are happening on your WordPress.', 'wp-security-audit-log' ); ?>
				<?php esc_html_e( 'It takes thousands of man-hours every year and endless amount of dedication to research, develop and maintain the free edition of WP Activity Log.', 'wp-security-audit-log' ); ?>
				<?php esc_html_e( 'Therefore if you like what you see, and find WP Activity Log useful we ask you nothing more than to please rate our plugin.', 'wp-security-audit-log' ); ?>
				<?php esc_html_e( 'We appreciate every star!', 'wp-security-audit-log' ); ?>
			</p>
			<p>
				<a class="rating-link" rel="noopener noreferrer" href="https://en-gb.wordpress.org/plugins/wp-security-audit-log/#reviews" target="_blank">
					<span class="dashicons dashicons-star-filled"></span>
					<span class="dashicons dashicons-star-filled"></span>
					<span class="dashicons dashicons-star-filled"></span>
					<span class="dashicons dashicons-star-filled"></span>
					<span class="dashicons dashicons-star-filled"></span>
				</a>
				<a class="button" rel="noopener noreferrer" href="https://en-gb.wordpress.org/plugins/wp-security-audit-log/#reviews" target="_blank"><?php esc_html_e( 'Rate Plugin', 'wp-security-audit-log' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Tab: Contact us.
	 */
	public function tab_contact_us() {
		?>
		<h3 class="wsal-tab__heading"><?php esc_html_e( 'Contact Us', 'wp-security-audit-log' ); ?></h3>
			<style type="text/css">
			.fs-secure-notice {
				position: relative !important;
				top: 0 !important;
				left: 0 !important;
			}
			.fs-full-size-wrapper {
			  margin: 10px 20px 0 2px !important;
			}
		</style>
		<?php
		$freemius_id = wsal_freemius()->get_id();
		$vars = array( 'id' => $freemius_id );
		echo fs_get_template( 'contact.php', $vars );
	}

	/**
	 * Tab: System info.
	 */
	public function tab_system_info() {
		?>
		<h3 class="wsal-tab__heading"><?php esc_html_e( 'System Info', 'wp-security-audit-log' ); ?></h3>
		<form method="post" dir="ltr">
			<textarea readonly="readonly" onclick="this.focus(); this.select()" id="system-info-textarea" name="wsal-sysinfo"><?php echo esc_html( $this->get_sysinfo() ); ?></textarea>
			<p class="submit">
				<input type="hidden" name="wsal-action" value="download_sysinfo" />
				<?php submit_button( 'Download System Info File', 'primary', 'wsal-download-sysinfo', false ); ?>
			</p>
		</form>
		<?php
	}

	/**
	 * Sidebar.
	 */
	private function sidebar() {
		$plugins_data = array(
			array(
				'img'  => trailingslashit( WSAL_BASE_URL ) . 'img/help/password-policy-manager.jpg',
				'desc' => __( 'Enforce strong password policies on WordPress', 'wp-security-audit-log' ),
				'alt'  => 'Password Policy Manager',
				'link' => 'https://www.wpwhitesecurity.com/wordpress-plugins/password-policy-manager-wordpress/?utm_source=plugin&utm_medium=referral&utm_campaign=PPMWP&utm_content=WSAL+banner',
			),
			array(
				'img'  => trailingslashit( WSAL_BASE_URL ) . 'img/help/website-file-changes-monitor.jpg',
				'desc' => __( 'Automatically identify unauthorized file changes on WordPress', 'wp-security-audit-log' ),
				'alt'  => 'Website File Changes Monitor',
				'link' => 'https://www.wpwhitesecurity.com/wordpress-plugins/website-file-changes-monitor/?utm_source=plugin&utm_medium=referral&utm_campaign=WFCM&utm_content=WSAL+banner',
			),
			array(
				'img'  => trailingslashit( WSAL_BASE_URL ) . 'img/help/wp-2fa-img.jpg',
				'desc' => __( 'Add an extra layer of security to your login pages with 2FA & require your users to use it.', 'wp-security-audit-log' ),
				'alt'  => 'WP 2FA',
				'link' => 'https://www.wpwhitesecurity.com/wordpress-plugins/wp-2fa/?utm_source=plugin&utm_medium=referral&utm_campaign=WP2FA&utm_content=WSAL+banner',
			),
			array(
				'img'  => trailingslashit( WSAL_BASE_URL ) . 'img/help/activity-log-for-mainwp.jpg',
				'desc' => __( 'See the child sites activity logs from the central MainWP dashboard', 'wp-security-audit-log' ),
				'alt'  => 'Activity Log for MainWP',
				'link' => 'https://wpactivitylog.com/extensions/mainwp-activity-log/?utm_source=plugin&utm_medium=referral&utm_campaign=AL4MWP&utm_content=WSAL+banner',
			),
		);
		?>
		<h3><?php esc_html_e( 'Our other WordPress plugins', 'wp-security-audit-log' ); ?></h3>
		<ul>
			<?php foreach ( $plugins_data as $data ) : ?>
				<li>
					<div class="plugin-box">
						<div class="plugin-img">
							<img src="<?php echo esc_url( $data['img'] ); ?>" alt="<?php echo esc_attr( $data['alt'] ); ?>">
						</div>
						<div class="plugin-desc">
							<p><?php echo esc_html( $data['desc'] ); ?></p>
							<div class="cta-btn">
								<a href="<?php echo esc_url( $data['link'] ); ?>" target="_blank"><?php esc_html_e( 'LEARN MORE', 'wp-security-audit-log' ); ?></a>
							</div>
						</div>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	/**
	 * Method: Get system information.
	 *
	 * @return string - System information.
	 * @throws Freemius_Exception
	 */
	public function get_sysinfo() {
		// System info.
		global $wpdb;

		$sysinfo = '### System Info → Begin ###' . "\n\n";

		// Start with the basics...
		$sysinfo .= '-- Site Info --' . "\n\n";
		$sysinfo .= 'Site URL (WP Address):    ' . site_url() . "\n";
		$sysinfo .= 'Home URL (Site Address):  ' . home_url() . "\n";
		$sysinfo .= 'Multisite:                ' . ( is_multisite() ? 'Yes' : 'No' ) . "\n";

		// Browser information.
		if ( ! class_exists( 'WSAL_Browser' ) && file_exists( WSAL_BASE_DIR . 'sdk/class-wsal-browser.php' ) ) {
			require_once WSAL_BASE_DIR . 'sdk/class-wsal-browser.php';

			$browser  = new WSAL_Browser();
			$sysinfo .= "\n" . '-- User Browser --' . "\n\n";
			$sysinfo .= $browser;
		}

		// Get theme info.
		$theme_data   = wp_get_theme();
		$theme        = $theme_data->Name . ' ' . $theme_data->Version;
		$parent_theme = $theme_data->Template;
		if ( ! empty( $parent_theme ) ) {
			$parent_theme_data = wp_get_theme( $parent_theme );
			$parent_theme      = $parent_theme_data->Name . ' ' . $parent_theme_data->Version;
		}

		// Language information.
		$locale = get_locale();

		// WordPress configuration.
		$sysinfo .= "\n" . '-- WordPress Configuration --' . "\n\n";
		$sysinfo .= 'Version:                  ' . get_bloginfo( 'version' ) . "\n";
		$sysinfo .= 'Language:                 ' . ( ! empty( $locale ) ? $locale : 'en_US' ) . "\n";
		$sysinfo .= 'Permalink Structure:      ' . ( get_option( 'permalink_structure' ) ? get_option( 'permalink_structure' ) : 'Default' ) . "\n";
		$sysinfo .= 'Active Theme:             ' . $theme . "\n";
		if ( $parent_theme !== $theme ) {
			$sysinfo .= 'Parent Theme:             ' . $parent_theme . "\n";
		}
		$sysinfo .= 'Show On Front:            ' . get_option( 'show_on_front' ) . "\n";

		// Only show page specs if frontpage is set to 'page'.
		if ( 'page' === get_option( 'show_on_front' ) ) {
			$front_page_id = (int) get_option( 'page_on_front' );
			$blog_page_id  = (int) get_option( 'page_for_posts' );

			$sysinfo .= 'Page On Front:            ' . ( 0 !== $front_page_id ? get_the_title( $front_page_id ) . ' (#' . $front_page_id . ')' : 'Unset' ) . "\n";
			$sysinfo .= 'Page For Posts:           ' . ( 0 !== $blog_page_id ? get_the_title( $blog_page_id ) . ' (#' . $blog_page_id . ')' : 'Unset' ) . "\n";
		}

		$sysinfo .= 'ABSPATH:                  ' . ABSPATH . "\n";
		$sysinfo .= 'WP_DEBUG:                 ' . ( defined( 'WP_DEBUG' ) ? WP_DEBUG ? 'Enabled' : 'Disabled' : 'Not set' ) . "\n";
		$sysinfo .= 'WP Memory Limit:          ' . WP_MEMORY_LIMIT . "\n";

		// Get plugins that have an update.
		$updates = get_plugin_updates();

		// Must-use plugins.
		// NOTE: MU plugins can't show updates!
		$muplugins = get_mu_plugins();
		if ( count( $muplugins ) > 0 ) {
			$sysinfo .= "\n" . '-- Must-Use Plugins --' . "\n\n";

			foreach ( $muplugins as $plugin => $plugin_data ) {
				$sysinfo .= $plugin_data['Name'] . ': ' . $plugin_data['Version'] . "\n";
			}
		}

		// WordPress active plugins.
		$sysinfo .= "\n" . '-- WordPress Active Plugins --' . "\n\n";

		$plugins        = get_plugins();
		$active_plugins = get_option( 'active_plugins', array() );

		foreach ( $plugins as $plugin_path => $plugin ) {
			if ( ! in_array( $plugin_path, $active_plugins ) ) {
				continue;
			}

			if (
				'WP Activity Log' === $plugin['Name']
				&& wsal_freemius()->can_use_premium_code()
			) {
				$update   = ( array_key_exists( $plugin_path, $updates ) ) ? ' (needs update - ' . $updates[ $plugin_path ]->update->new_version . ')' : '';
				$sysinfo .= $plugin['Name'] . ' Premium: ' . $plugin['Version'] . $update . "\n";
			} else {
				$update   = ( array_key_exists( $plugin_path, $updates ) ) ? ' (needs update - ' . $updates[ $plugin_path ]->update->new_version . ')' : '';
				$sysinfo .= $plugin['Name'] . ': ' . $plugin['Version'] . $update . "\n";
			}
		}

		// WordPress inactive plugins.
		$sysinfo .= "\n" . '-- WordPress Inactive Plugins --' . "\n\n";

		foreach ( $plugins as $plugin_path => $plugin ) {
			if ( in_array( $plugin_path, $active_plugins ) ) {
				continue;
			}

			if (
				'WP Activity Log' === $plugin['Name']
				&& wsal_freemius()->can_use_premium_code()
			) {
				$update   = ( array_key_exists( $plugin_path, $updates ) ) ? ' (needs update - ' . $updates[ $plugin_path ]->update->new_version . ')' : '';
				$sysinfo .= $plugin['Name'] . ' Premium: ' . $plugin['Version'] . $update . "\n";
			} else {
				$update   = ( array_key_exists( $plugin_path, $updates ) ) ? ' (needs update - ' . $updates[ $plugin_path ]->update->new_version . ')' : '';
				$sysinfo .= $plugin['Name'] . ': ' . $plugin['Version'] . $update . "\n";
			}
		}

		if ( is_multisite() ) {
			// WordPress Multisite active plugins.
			$sysinfo .= "\n" . '-- Network Active Plugins --' . "\n\n";

			$plugins        = wp_get_active_network_plugins();
			$active_plugins = get_site_option( 'active_sitewide_plugins', array() );

			foreach ( $plugins as $plugin_path ) {
				$plugin_base = plugin_basename( $plugin_path );

				if ( ! array_key_exists( $plugin_base, $active_plugins ) ) {
					continue;
				}

				if (
					'WP Activity Log' === $plugin['Name']
					&& wsal_freemius()->can_use_premium_code()
				) {
					$update   = ( array_key_exists( $plugin_path, $updates ) ) ? ' (needs update - ' . $updates[ $plugin_path ]->update->new_version . ')' : '';
					$plugin   = get_plugin_data( $plugin_path );
					$sysinfo .= $plugin['Name'] . ' Premium: ' . $plugin['Version'] . $update . "\n";
				} else {
					$update   = ( array_key_exists( $plugin_path, $updates ) ) ? ' (needs update - ' . $updates[ $plugin_path ]->update->new_version . ')' : '';
					$plugin   = get_plugin_data( $plugin_path );
					$sysinfo .= $plugin['Name'] . ': ' . $plugin['Version'] . $update . "\n";
				}
			}
		}

		// Server configuration.
		$server_software = filter_input( INPUT_SERVER, 'SERVER_SOFTWARE', FILTER_SANITIZE_STRING );
		$sysinfo        .= "\n" . '-- Webserver Configuration --' . "\n\n";
		$sysinfo        .= 'PHP Version:              ' . PHP_VERSION . "\n";
		$sysinfo        .= 'MySQL Version:            ' . $wpdb->db_version() . "\n";

		if ( isset( $server_software ) ) {
			$sysinfo .= 'Webserver Info:           ' . $server_software . "\n";
		} else {
			$sysinfo .= 'Webserver Info:           Global $_SERVER array is not set.' . "\n";
		}

		// PHP configs.
		$sysinfo .= "\n" . '-- PHP Configuration --' . "\n\n";
		$sysinfo .= 'Memory Limit:             ' . ini_get( 'memory_limit' ) . "\n";
		$sysinfo .= 'Upload Max Size:          ' . ini_get( 'upload_max_filesize' ) . "\n";
		$sysinfo .= 'Post Max Size:            ' . ini_get( 'post_max_size' ) . "\n";
		$sysinfo .= 'Upload Max Filesize:      ' . ini_get( 'upload_max_filesize' ) . "\n";
		$sysinfo .= 'Time Limit:               ' . ini_get( 'max_execution_time' ) . "\n";
		$sysinfo .= 'Max Input Vars:           ' . ini_get( 'max_input_vars' ) . "\n";
		$sysinfo .= 'Display Errors:           ' . ( ini_get( 'display_errors' ) ? 'On (' . ini_get( 'display_errors' ) . ')' : 'N/A' ) . "\n";

		// WSAL options.
		$sysinfo .= "\n" . '-- WSAL Options --' . "\n\n";
		$options  = $this->_plugin->settings()->get_plugin_settings();

		if ( ! empty( $options ) && is_array( $options ) ) {
			foreach ( $options as $option ) {
				$sysinfo .= 'Option: ' . $option->option_name . "\n";
				$sysinfo .= 'Value: ' . $option->option_value . "\n\n";
			}
		}

		$sysinfo .= "\n" . '### System Info → End ###' . "\n\n";

		return $sysinfo;
	}

	/**
	 * Method: Render footer content.
	 */
	public function Footer() {
		if ( 'system-info' === $this->current_tab && $this->_plugin->settings()->CurrentUserCan( 'edit' ) ) :
			?>
			<script>
				/**
				 * Create and download a temporary file.
				 *
				 * @param {string} filename - File name.
				 * @param {string} text - File content.
				 */
				function download(filename, text) {
					// Create temporary element.
					var element = document.createElement('a');
					element.setAttribute('href', 'data:text/plain;charset=utf-8,' + encodeURIComponent(text));
					element.setAttribute('download', filename);

					// Set the element to not display.
					element.style.display = 'none';
					document.body.appendChild(element);

					// Simlate click on the element.
					element.click();

					// Remove temporary element.
					document.body.removeChild(element);
				}

				jQuery( document ).ready( function() {
					var download_btn = jQuery( '#wsal-download-sysinfo' );
					download_btn.click( function( event ) {
						event.preventDefault();
						download( 'wsal-system-info.txt', jQuery( '#system-info-textarea' ).val() );
					} );
				} );
			</script>
			<?php
		endif;
	}
}
