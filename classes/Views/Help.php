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
		return __( 'Help', 'wp-security-audit-log' );
	}

	/**
	 * Method: Get View Weight.
	 */
	public function GetWeight() {
		return 14;
	}

	/**
	 * Method: Get View Header.
	 */
	public function Header() {
		// Information display style.
		?>
		<style>
			#system-info-textarea {
				font-family: monospace;
				white-space: pre;
				overflow: auto;
				width: 100%;
				height: 400px;
				margin: 0;
			}
		</style>
		<?php

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
		?>
		<h2 id="wsal-tabs" class="nav-tab-wrapper">
			<a href="#tab-help" class="nav-tab"><?php esc_html_e( 'Help', 'wp-security-audit-log' ); ?></a>
			<?php if ( $this->_plugin->settings->CurrentUserCan( 'edit' ) ) : ?>
				<a href="#tab-system-info" class="nav-tab"><?php esc_html_e( 'System Info', 'wp-security-audit-log' ); ?></a>
			<?php endif; ?>
		</h2>
		<div class="wsal-nav-tabs-wrap">
			<div class="nav-tabs">
				<table class="form-table wsal-tab widefat" id="tab-help">
					<tbody>
						<tr class="postbox">
							<td>
								<h2 class="wsal-tab__heading"><?php esc_html_e( 'Getting Started', 'wp-security-audit-log' ); ?></h2>
								<p>
									<?php esc_html_e( 'Getting started with WP Security Audit Log is really easy; once the plugin is installed it will automatically keep a log of everything that is happening on your website and you do not need to do anything. Watch the video below for a quick overview of the plugin.', 'wp-security-audit-log' ); ?>
								</p>
								<p>
									<iframe class="wsal-youtube-embed" width="560" height="315" src="https://www.youtube.com/embed/1nopATCS-CQ?rel=0" frameborder="0" allowfullscreen></iframe>
								</p>
							</td>
						</tr>
						<tr class="postbox">
							<td>
								<h2 class="wsal-tab__heading"><?php esc_html_e( 'Plugin Support', 'wp-security-audit-log' ); ?></h2>
								<p>
									<?php esc_html_e( 'Have you encountered or noticed any issues while using WP Security Audit Log plugin?', 'wp-security-audit-log' ); ?>
									<?php esc_html_e( 'Or you want to report something to us? Click any of the options below to post on the plugin\'s forum or contact our support directly.', 'wp-security-audit-log' ); ?>
								</p><p>
									<a class="button" href="https://wordpress.org/support/plugin/wp-security-audit-log" target="_blank"><?php esc_html_e( 'Free Support Forum', 'wp-security-audit-log' ); ?></a>
									&nbsp;&nbsp;&nbsp;&nbsp;
									<a class="button" href="http://www.wpsecurityauditlog.com/contact/" target="_blank"><?php esc_html_e( 'Free Support Email', 'wp-security-audit-log' ); ?></a>
								</p>
							</td>
						</tr>
						<tr class="postbox">
							<td>
								<h2 class="wsal-tab__heading"><?php esc_html_e( 'Plugin Documentation', 'wp-security-audit-log' ); ?></h2>
								<p>
									<?php esc_html_e( 'For more technical information about the WP Security Audit Log plugin please visit the plugin’s knowledge base.', 'wp-security-audit-log' ); ?>
									<?php esc_html_e( 'Refer to the list of WordPress security events for a complete list of Events and IDs that the plugin uses to keep a log of all the changes in the WordPress audit log.', 'wp-security-audit-log' ); ?>
								</p><p>
									<a class="button" href="http://www.wpsecurityauditlog.com/?utm_source=plugin&amp;utm_medium=helppage&amp;utm_campaign=support" target="_blank"><?php esc_html_e( 'Plugin Website', 'wp-security-audit-log' ); ?></a>
									&nbsp;&nbsp;&nbsp;&nbsp;
									<a class="button" href="https://www.wpsecurityauditlog.com/support-documentation/?utm_source=plugin&amp;utm_medium=helppage&amp;utm_campaign=support" target="_blank"><?php esc_html_e( 'Knowledge Base', 'wp-security-audit-log' ); ?></a>
									&nbsp;&nbsp;&nbsp;&nbsp;
									<a class="button" href="http://www.wpsecurityauditlog.com/documentation/list-monitoring-wordpress-security-alerts-audit-log/?utm_source=plugin&amp;utm_medium=helppage&amp;utm_campaign=support" target="_blank"><?php esc_html_e( 'List of WordPress Security Events', 'wp-security-audit-log' ); ?></a>
								</p>
							</td>
						</tr>
						<tr class="postbox">
							<td>
								<h2 class="wsal-tab__heading"><?php esc_html_e( 'Rate WP Security Audit Log', 'wp-security-audit-log' ); ?></h2>
								<p>
									<?php esc_html_e( 'We work really hard to deliver a plugin that enables you to keep a record of all the changes that are happening on your WordPress.', 'wp-security-audit-log' ); ?>
									<?php esc_html_e( 'It takes thousands of man-hours every year and endless amount of dedication to research, develop and maintain the free edition of WP Security Audit Log.', 'wp-security-audit-log' ); ?>
									<?php esc_html_e( 'Therefore if you like what you see, and find WP Security Audit Log useful we ask you nothing more than to please rate our plugin.', 'wp-security-audit-log' ); ?>
									<?php esc_html_e( 'We appreciate every star!', 'wp-security-audit-log' ); ?>
								</p>
								<p>
									<a class="rating-link" href="https://en-gb.wordpress.org/plugins/wp-security-audit-log/#reviews" target="_blank">
										<span class="dashicons dashicons-star-filled"></span>
										<span class="dashicons dashicons-star-filled"></span>
										<span class="dashicons dashicons-star-filled"></span>
										<span class="dashicons dashicons-star-filled"></span>
										<span class="dashicons dashicons-star-filled"></span>
									</a>
									<a class="button" href="https://en-gb.wordpress.org/plugins/wp-security-audit-log/#reviews" target="_blank"><?php esc_html_e( 'Rate Plugin', 'wp-security-audit-log' ); ?></a>
								</p>
							</td>
						</tr>
					</tbody>
				</table>
				<?php
				// Check user permissions to view page.
				if ( $this->_plugin->settings->CurrentUserCan( 'edit' ) ) :
					?>
					<table class="form-table wsal-tab widefat" id="tab-system-info">
						<tbody>
							<tr>
								<td>
									<h3><?php esc_html_e( 'System Info', 'wp-security-audit-log' ); ?></h3>
									<form method="post" dir="ltr">
										<textarea readonly="readonly" onclick="this.focus(); this.select()" id="system-info-textarea" name="wsal-sysinfo"><?php echo esc_html( $this->get_sysinfo() ); ?></textarea>
										<p class="submit">
											<input type="hidden" name="wsal-action" value="download_sysinfo" />
											<?php submit_button( 'Download System Info File', 'primary', 'wsal-download-sysinfo', false ); ?>
										</p>
									</form>
								</td>
							</tr>
						</tbody>
					</table>
				<?php endif; ?>
			</div>

			<?php
			$is_current_view = $this->_plugin->views->GetActiveView() == $this;
			// Check if any of the extensions is activated.
			if ( wsal_freemius()->is_not_paying() ) :
				if ( current_user_can( 'manage_options' ) && $is_current_view ) :
					?>
					<div class="wsal-sidebar-advert">
						<div class="postbox">
							<h3 class="hndl"><span><?php esc_html_e( 'Upgrade to Premium', 'wp-security-audit-log' ); ?></span></h3>
							<div class="inside">
								<ul class="wsal-features-list">
									<li>
										<?php esc_html_e( 'See who is logged in', 'wp-security-audit-log' ); ?><br />
										<?php esc_html_e( 'And remotely terminate sessions', 'wp-security-audit-log' ); ?>
									</li>
									<li>
										<?php esc_html_e( 'Generate reports', 'wp-security-audit-log' ); ?><br />
										<?php esc_html_e( 'Or configure automated email reports', 'wp-security-audit-log' ); ?>
									</li>
									<li>
										<?php esc_html_e( 'Configure email notifications', 'wp-security-audit-log' ); ?><br />
										<?php esc_html_e( 'Get instantly notified of important changes', 'wp-security-audit-log' ); ?>
									</li>
									<li>
										<?php esc_html_e( 'Add Search', 'wp-security-audit-log' ); ?><br />
										<?php esc_html_e( 'Easily track down suspicious behaviour', 'wp-security-audit-log' ); ?>
									</li>
									<li>
										<?php esc_html_e( 'Integrate & Centralise', 'wp-security-audit-log' ); ?><br />
										<?php esc_html_e( 'Export the logs to your centralised logging system', 'wp-security-audit-log' ); ?>
									</li>
								</ul>
								<?php
								// Buy Now button link.
								$buy_now = add_query_arg( 'page', 'wsal-auditlog-pricing', admin_url( 'admin.php' ) );
								$buy_now_target = '';

								// If user is not super admin and website is multisite then change the URL.
								if ( $this->_plugin->IsMultisite() && ! is_super_admin() ) {
									$buy_now = 'https://www.wpsecurityauditlog.com/pricing/';
									$buy_now_target = 'target="_blank"';
								} elseif ( $this->_plugin->IsMultisite() && is_super_admin() ) {
									$buy_now = add_query_arg( 'page', 'wsal-auditlog-pricing', network_admin_url( 'admin.php' ) );
								} elseif ( ! $this->_plugin->IsMultisite() && ! current_user_can( 'manage_options' ) ) {
									$buy_now = 'https://www.wpsecurityauditlog.com/pricing/';
									$buy_now_target = 'target="_blank"';
								}

								$more_info = add_query_arg(
									array(
										'utm_source' => 'plugin',
										'utm_medium' => 'page',
										'utm_content' => 'update+more+info',
										'utm_campaign' => 'upgrade+premium',
									),
									'https://www.wpsecurityauditlog.com/premium-features/'
								);
								?>
								<p>
									<a class="button-primary wsal-extension-btn" href="<?php echo esc_attr( $buy_now ); ?>" <?php echo esc_attr( $buy_now_target ); ?>><?php esc_html_e( 'Upgrade to Premium', 'wp-security-audit-log' ); ?></a>
									<a class="button-primary wsal-extension-btn" href="<?php echo esc_attr( $more_info ); ?>" target="_blank"><?php esc_html_e( 'More Information', 'wp-security-audit-log' ); ?></a>
								</p>
							</div>
						</div>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Method: Get system information.
	 *
	 * @return string - System information.
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
				'WP Security Audit Log' === $plugin['Name']
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
				'WP Security Audit Log' === $plugin['Name']
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
					'WP Security Audit Log' === $plugin['Name']
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
		$sysinfo .= 'PHP Safe Mode:            ';
		$sysinfo .= ini_get( 'safe_mode' ) ? 'Yes' . "\n" : 'No' . "\n";
		$sysinfo .= 'Memory Limit:             ' . ini_get( 'memory_limit' ) . "\n";
		$sysinfo .= 'Upload Max Size:          ' . ini_get( 'upload_max_filesize' ) . "\n";
		$sysinfo .= 'Post Max Size:            ' . ini_get( 'post_max_size' ) . "\n";
		$sysinfo .= 'Upload Max Filesize:      ' . ini_get( 'upload_max_filesize' ) . "\n";
		$sysinfo .= 'Time Limit:               ' . ini_get( 'max_execution_time' ) . "\n";
		$sysinfo .= 'Max Input Vars:           ' . ini_get( 'max_input_vars' ) . "\n";
		$sysinfo .= 'Display Errors:           ' . ( ini_get( 'display_errors' ) ? 'On (' . ini_get( 'display_errors' ) . ')' : 'N/A' ) . "\n";

		// WSAL options.
		$sysinfo .= "\n" . '-- WSAL Options --' . "\n\n";
		$options  = $this->get_wsal_options();

		if ( ! empty( $options ) && is_array( $options ) ) {
			foreach ( $options as $index => $option ) {
				$sysinfo .= 'Option: ' . $option->option_name . "\n";
				$sysinfo .= 'Value: ' . $option->option_value . "\n\n";
			}
		}

		$sysinfo .= "\n" . '### System Info → End ###' . "\n\n";

		return $sysinfo;
	}

	/**
	 * Method: Query WSAL Options from DB.
	 *
	 * @return array - WSAL Options array.
	 */
	public function get_wsal_options() {
		// Get options transient.
		$wsal_options = get_transient( 'wsal_options' );

		// If options transient is not set then query and set options.
		if ( false === $wsal_options ) {
			// Get raw options from DB.
			$raw_options = $this->query_wsal_options();

			if ( ! empty( $raw_options ) && is_array( $raw_options ) ) {
				foreach ( $raw_options as $option_id => $option ) {
					if ( ! empty( $option->option_value ) ) {
						$wsal_options[] = $option;
					}
				}
			}

			// Store the results in a transient.
			set_transient( 'wsal_options', $wsal_options, DAY_IN_SECONDS );
		}

		return $wsal_options;
	}

	/**
	 * Method: Query WSAL Options from DB.
	 *
	 * @return array - Array of options.
	 */
	public function query_wsal_options() {
		// Query WSAL options.
		global $wpdb;

		// Set table name.
		$options_table = $wpdb->prefix . 'wsal_options';

		// Query the options.
		return $wpdb->get_results( "SELECT * FROM $options_table" );
	}

	/**
	 * Method: Render footer content.
	 */
	public function Footer() {
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

			// tab handling code
			jQuery('#wsal-tabs>a').click(function(){
				jQuery('#wsal-tabs>a').removeClass('nav-tab-active');
				jQuery('table.wsal-tab').hide();
				jQuery(jQuery(this).addClass('nav-tab-active').attr('href')).show();
			});
			// show relevant tab
			var hashlink = jQuery('#wsal-tabs>a[href="' + location.hash + '"]');
			if (hashlink.length) {
				hashlink.click();
			} else {
				jQuery('#wsal-tabs>a:first').click();
			}

			jQuery(".sel-columns").change(function(){
				var notChecked = 1;
				jQuery(".sel-columns").each(function(){
					if(this.checked) notChecked = 0;
				})
				if(notChecked == 1){
					alert("You have to select at least one column!");
				}
			});
		</script>
		<?php
	}
}
