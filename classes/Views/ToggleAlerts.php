<?php
/**
 * Settings Page
 *
 * Settings page of the plugin.
 *
 * @since   1.0.0
 * @package Wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enable/Disable Alerts Page.
 *
 * @package Wsal
 */
class WSAL_Views_ToggleAlerts extends WSAL_AbstractView {

	/**
	 * Method: Get View Title.
	 */
	public function GetTitle() {
		return __( 'Enable/Disable Events', 'wp-security-audit-log' );
	}

	/**
	 * Method: Get View Icon.
	 */
	public function GetIcon() {
		return 'dashicons-forms';
	}

	/**
	 * Method: Get View Name.
	 */
	public function GetName() {
		return __( 'Enable/Disable Events', 'wp-security-audit-log' );
	}

	/**
	 * Method: Get View Weight.
	 */
	public function GetWeight() {
		return 2;
	}

	/**
	 * Method: Get safe category name.
	 *
	 * @param string $name - Name of the category.
	 */
	protected function GetSafeCatgName( $name ) {
		return strtolower(
			preg_replace( '/[^A-Za-z0-9\-]/', '-', $name )
		);
	}

	/**
	 * View Save.
	 *
	 * @since 3.3
	 */
	private function save() {
		// Filter $_POST array.
		$post_array = filter_input_array( INPUT_POST );

		$enabled  = array_map( 'intval', $post_array['alert'] );
		$disabled = array();
		foreach ( $this->_plugin->alerts->GetAlerts() as $alert ) {
			if ( ! in_array( $alert->type, $enabled, true ) ) {
				$disabled[] = $alert->type;
			}
		}

		if ( isset( $post_array['disable-visitor-events'] ) && 'yes' === $this->_plugin->GetGlobalOption( 'disable-visitor-events', 'no' ) ) {
			$public_events = $this->_plugin->alerts->get_public_events();
			$disabled      = array_diff( $disabled, $public_events );
		}
		$this->_plugin->alerts->SetDisabledAlerts( $disabled );

		$this->_plugin->SetGlobalOption( 'log-404', isset( $post_array['log_404'] ) ? 'on' : 'off' );
		$this->_plugin->SetGlobalOption( 'purge-404-log', isset( $post_array['purge_log'] ) ? 'on' : 'off' );
		$this->_plugin->SetGlobalOption( 'log-404-referrer', isset( $post_array['log_404_referrer'] ) ? 'on' : 'off' );

		$this->_plugin->SetGlobalOption( 'log-visitor-404', isset( $post_array['log_visitor_404'] ) ? 'on' : 'off' );
		$this->_plugin->SetGlobalOption( 'purge-visitor-404-log', isset( $post_array['purge_visitor_log'] ) ? 'on' : 'off' );
		$this->_plugin->SetGlobalOption( 'log-visitor-404-referrer', isset( $post_array['log_visitor_404_referrer'] ) ? 'on' : 'off' );
		$this->_plugin->SetGlobalOption( 'wc-all-stock-changes', isset( $post_array['wc_all_stock_changes'] ) ? 'on' : 'off' );

		$this->_plugin->settings->Set404LogLimit( $post_array['user_404Limit'] );
		$this->_plugin->settings->SetVisitor404LogLimit( $post_array['visitor_404Limit'] );

		$this->_plugin->settings->set_failed_login_limit( $post_array['log_failed_login_limit'] );
		$this->_plugin->settings->set_visitor_failed_login_limit( $post_array['log_visitor_failed_login_limit'] );

		// Get file change scan alerts.
		$file_change_alerts = $this->_plugin->alerts->get_alerts_by_sub_category( 'File Changes' );
		$file_change_alerts = array_keys( $file_change_alerts );

		// Toggle file change.
		$file_change_toggle = 'disable';

		// Check each file change alert to see if it is active or not.
		foreach ( $file_change_alerts as $alert ) {
			if ( ! in_array( $alert, $disabled, true ) ) { // If any one alert is active, then.
				$file_change_toggle = 'enable'; // Enable the file change.
			}
		}

		// Set the option.
		$this->_plugin->SetGlobalOption( 'scan-file-changes', $file_change_toggle );

		// Set the visitor events option.
		$this->_plugin->SetGlobalOption( 'disable-visitor-events', isset( $post_array['disable-visitor-events'] ) ? 'no' : 'yes' );
	}

	/**
	 * Method: Get View.
	 */
	public function Render() {
		// Die if user does not have permission to view.
		if ( ! $this->_plugin->settings->CurrentUserCan( 'edit' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-security-audit-log' ) );
		}

		$alert          = new WSAL_Alert(); // IDE type hinting.
		$grouped_alerts = $this->_plugin->alerts->GetCategorizedAlerts( false );
		$safe_names     = array_map( array( $this, 'GetSafeCatgName' ), array_keys( $grouped_alerts ) );
		$safe_names     = array_combine( array_keys( $grouped_alerts ), $safe_names );

		// Filter $_POST array.
		$post_array = filter_input_array( INPUT_POST );

		if ( isset( $post_array['submit'] ) && isset( $post_array['alert'] ) ) {
			check_admin_referer( 'wsal-togglealerts' );
			try {
				$this->save();
				?>
				<div class="updated">
					<p><?php esc_html_e( 'Settings have been saved.', 'wp-security-audit-log' ); ?></p>
				</div>
				<?php
			} catch ( Exception $ex ) {
				?>
				<div class="error">
					<p><?php esc_html_e( 'Error: ', 'wp-security-audit-log' ); ?><?php echo esc_html( $ex->getMessage() ); ?></p>
				</div>
				<?php
			}
		}

		// Log level form submission.
		$log_level       = isset( $post_array['wsal-log-level'] ) ? sanitize_text_field( $post_array['wsal-log-level'] ) : false;
		$log_level_nonce = isset( $post_array['wsal-log-level-nonce'] ) ? sanitize_text_field( $post_array['wsal-log-level-nonce'] ) : false;

		if ( wp_verify_nonce( $log_level_nonce, 'wsal-log-level' ) ) {
			$this->_plugin->SetGlobalOption( 'details-level', $log_level );

			if ( 'basic' === $log_level ) {
				$this->_plugin->settings->set_basic_mode();
			} elseif ( 'geek' === $log_level ) {
				$this->_plugin->settings->set_geek_mode();
			}
		}

		$disabled_events = $this->_plugin->GetGlobalOption( 'disabled-alerts' ); // Get disabled events.
		$disabled_events = explode( ',', $disabled_events );
		$events_diff     = array_diff( $disabled_events, $this->_plugin->settings->geek_alerts ); // Calculate the difference of events.
		$events_diff     = array_filter( $events_diff ); // Remove empty values.
		$is_custom       = ! empty( $events_diff ) ? true : false; // If difference is not empty then mode is custom.
		$log_details     = $this->_plugin->GetGlobalOption( 'details-level', false ); // Get log level option.

		$subcat_alerts = array( 1004, 2010, 6007, 2111, 2119, 2016, 2053, 7000, 8009, 8014, 4013, 9007, 9047, 9027, 9002, 9057, 9063, 9035, 9083, 8809, 8813, 6000, 6001, 6028 );
		$public_events = $this->_plugin->alerts->get_public_events(); // Get public events.
		?>
		<p>
			<form method="post" id="wsal-alerts-level">
				<?php wp_nonce_field( 'wsal-log-level', 'wsal-log-level-nonce' ); ?>
				<fieldset>
					<label for="wsal-log-level"><?php esc_html_e( 'Log Level: ', 'wp-security-audit-log' ); ?></label>
					<select name="wsal-log-level" id="wsal-log-level" onchange="this.form.submit()">
						<option value="basic"
							<?php echo ( ! empty( $log_details ) && 'basic' === $log_details ) ? esc_attr( 'selected' ) : false; ?>
						>
							<?php esc_html_e( 'Basic', 'wp-security-audit-log' ); ?>
						</option>
						<option value="geek"
							<?php echo ( ! empty( $log_details ) && 'geek' === $log_details ) ? esc_attr( 'selected' ) : false; ?>
						>
							<?php esc_html_e( 'Geek', 'wp-security-audit-log' ); ?>
						</option>
						<option value="custom" <?php echo ( $is_custom ) ? esc_attr( 'selected' ) : false; ?>>
							<?php esc_html_e( 'Custom', 'wp-security-audit-log' ); ?>
						</option>
					</select>
					<p class="description">
						<?php echo wp_kses( __( 'Use the Log level drop down menu above to use one of our preset log levels. Alternatively you can enable or disable any of the individual events from the below tabs. Refer to <a href="https://www.wpsecurityauditlog.com/support-documentation/list-wordpress-audit-trail-alerts/" target="_blank">the complete list of WordPress activity log event IDs</a> for reference on all the events the plugin can keep a log of.', 'wp-security-audit-log' ), $this->_plugin->allowed_html_tags ); ?>
					</p>
				</fieldset>
			</form>
		</p>
		<h2 id="wsal-tabs" class="nav-tab-wrapper">
			<?php
			foreach ( $safe_names as $name => $safe ) :
				if ( __( 'Third Party Plugins', 'wp-security-audit-log' ) === $name ) :
					?>
					<a href="#tab-visitor-events" class="nav-tab">
						<?php esc_html_e( 'Visitor Events', 'wp-security-audit-log' ); ?>
					</a>
				<?php endif; ?>
				<a href="#tab-<?php echo esc_attr( $safe ); ?>" class="nav-tab"><?php echo esc_html( $name ); ?></a>
			<?php endforeach; ?>
		</h2>
		<form id="audit-log-viewer" method="post">
			<input type="hidden" name="page" value="<?php echo isset( $_GET['page'] ) ? esc_attr( sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) : false; ?>" />
			<?php wp_nonce_field( 'wsal-togglealerts' ); ?>

			<div class="nav-tabs">
				<?php foreach ( $grouped_alerts as $name => $group ) : ?>
					<div class="wsal-tab" id="tab-<?php echo esc_attr( $safe_names[ $name ] ); ?>">
						<h2 class="nav-tab-wrapper wsal-sub-tabs">
							<?php
							foreach ( $group as $subname => $alerts ) :
								// Skip Pages and CPTs section.
								if ( __( 'Custom Post Types', 'wp-security-audit-log' ) === $subname || __( 'Pages', 'wp-security-audit-log' ) === $subname ) {
									continue;
								}
								?>
								<a href="#tab-<?php echo esc_attr( $this->GetSafeCatgName( $subname ) ); ?>"
									class="nav-tab"
									data-parent="tab-<?php echo esc_attr( $safe_names[ $name ] ); ?>">
									<?php echo esc_html( $subname ); ?>
								</a>
							<?php endforeach; ?>
						</h2>
						<?php
						foreach ( $group as $subname => $alerts ) {
							$active    = array();
							$allactive = true;
							foreach ( $alerts as $alert ) {
								if ( $alert->type <= 0006 ) {
									continue; // <- Ignore php alerts.
								}
								if ( 9999 === $alert->type ) {
									continue; // <- Ignore promo alerts.
								}
								$active[ $alert->type ] = $this->_plugin->alerts->IsEnabled( $alert->type );
								if ( ! $active[ $alert->type ] ) {
									$allactive = false;
								}
							}

							// Disabled alerts.
							$disabled = '';

							// Skip Pages and CPTs section.
							if ( __( 'Custom Post Types', 'wp-security-audit-log' ) === $subname || __( 'Pages', 'wp-security-audit-log' ) === $subname ) {
								continue;
							} elseif (
								__( 'BBPress Forum', 'wp-security-audit-log' ) === $subname
								|| __( 'WooCommerce', 'wp-security-audit-log' ) === $subname
								|| __( 'WooCommerce Products', 'wp-security-audit-log' ) === $subname
								|| __( 'Yoast SEO', 'wp-security-audit-log' ) === $subname
								|| __( 'MultiSite', 'wp-security-audit-log' ) === $subname
							) {
								switch ( $subname ) {
									case __( 'BBPress Forum', 'wp-security-audit-log' ):
										// Check if BBPress plugin exists.
										if ( ! is_plugin_active( 'bbpress/bbpress.php' ) ) {
											$disabled = 'disabled';
										}
										break;

									case __( 'WooCommerce', 'wp-security-audit-log' ):
										// Check if WooCommerce plugin exists.
										if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
											$disabled = 'disabled';
										}
										break;

									case __( 'WooCommerce Products', 'wp-security-audit-log' ):
										// Check if WooCommerce plugin exists.
										if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
											$disabled = 'disabled';
										}
										break;

									case __( 'Yoast SEO', 'wp-security-audit-log' ):
										// Check if Yoast SEO plugin exists.
										if ( is_plugin_active( 'wordpress-seo/wp-seo.php' ) || is_plugin_active( 'wordpress-seo-premium/wp-seo-premium.php' ) ) {
											$disabled = '';
										} else {
											$disabled = 'disabled';
										}
										break;

									case __( 'MultiSite', 'wp-security-audit-log' ):
										// Disable if not multisite.
										if ( ! is_multisite() ) {
											$disabled = 'disabled';
										}
										break;

									default:
										break;
								}
							}
							?>
							<table class="wp-list-table wsal-tab widefat fixed wsal-sub-tab" cellspacing="0" id="tab-<?php echo esc_attr( $this->GetSafeCatgName( $subname ) ); ?>">
								<thead>
									<tr>
										<th width="48"><input type="checkbox" <?php checked( $allactive ); ?> <?php echo esc_attr( $disabled ); ?> /></th>
										<th width="80"><?php esc_html_e( 'Code', 'wp-security-audit-log' ); ?></th>
										<th width="100"><?php esc_html_e( 'Severity', 'wp-security-audit-log' ); ?></th>
										<th><?php esc_html_e( 'Description', 'wp-security-audit-log' ); ?></th>
									</tr>
								</thead>
								<tbody id="<?php echo ( __( 'File Changes', 'wp-security-audit-log' ) === $subname ) ? 'alerts-file-changes' : false; ?>">
									<?php if ( __( 'Content', 'wp-security-audit-log' ) === $subname ) : ?>
										<tr>
											<td colspan="4">
												<p class="wsal-tab-help description"><?php echo wp_kses( __( '<strong>Note:</strong> Post refers to any type of content, i.e. blog post, page or a post with a custom post type.', 'wp-security-audit-log' ), $this->_plugin->allowed_html_tags ); ?></p>
											</td>
										</tr>
									<?php elseif ( __( 'BBPress Forum', 'wp-security-audit-log' ) === $subname ) : ?>
										<?php if ( ! empty( $disabled ) ) : ?>
											<tr>
												<td colspan="4">
													<p class="wsal-tab-help wsal-tab-notice description"><?php esc_html_e( 'The plugin BBPress is not installed on your website so these events have been disabled.', 'wp-security-audit-log' ); ?></p>
												</td>
											</tr>
										<?php endif; ?>
										<tr>
											<td colspan="4">
												<h3 class="sub-category"><?php esc_html_e( 'Forums', 'wp-security-audit-log' ); ?></h3>
											</td>
										</tr>
									<?php elseif ( __( 'WooCommerce', 'wp-security-audit-log' ) === $subname || __( 'WooCommerce Products', 'wp-security-audit-log' ) === $subname ) : ?>
										<?php if ( ! empty( $disabled ) ) : ?>
											<tr>
												<td colspan="4">
													<p class="wsal-tab-help wsal-tab-notice description"><?php esc_html_e( 'The plugin WooCommerce is not installed on your website so these events have been disabled.', 'wp-security-audit-log' ); ?></p>
												</td>
											</tr>
										<?php endif; ?>
										<?php if ( __( 'WooCommerce Products', 'wp-security-audit-log' ) === $subname ) : ?>
											<tr>
												<td colspan="4">
													<h3 class="sub-category"><?php esc_html_e( 'Products', 'wp-security-audit-log' ); ?></h3>
												</td>
											</tr>
										<?php endif; ?>
									<?php elseif ( __( 'Yoast SEO', 'wp-security-audit-log' ) === $subname ) : ?>
										<?php if ( ! empty( $disabled ) ) : ?>
											<tr>
												<td colspan="4">
													<p class="wsal-tab-help wsal-tab-notice description"><?php esc_html_e( 'The plugin Yoast SEO is not installed on your website so these events have been disabled.', 'wp-security-audit-log' ); ?></p>
												</td>
											</tr>
										<?php endif; ?>
										<tr>
											<td colspan="4">
												<h3 class="sub-category"><?php esc_html_e( 'Post Changes', 'wp-security-audit-log' ); ?></h3>
											</td>
										</tr>
									<?php elseif ( __( 'MultiSite', 'wp-security-audit-log' ) === $subname ) : ?>
										<?php if ( ! empty( $disabled ) ) : ?>
											<tr>
												<td colspan="4">
													<p class="wsal-tab-help wsal-tab-notice description"><?php esc_html_e( 'Your website is a single site so the multisite events have been disabled.', 'wp-security-audit-log' ); ?></p>
												</td>
											</tr>
										<?php endif; ?>
										<tr>
											<td colspan="4">
												<h3 class="sub-category"><?php esc_html_e( 'User Profiles', 'wp-security-audit-log' ); ?></h3>
											</td>
										</tr>
									<?php elseif ( __( 'Other User Activity', 'wp-security-audit-log' ) === $subname ) : ?>
										<tr>
											<td colspan="4">
												<h3 class="sub-category"><?php esc_html_e( 'Logins & Logouts', 'wp-security-audit-log' ); ?></h3>
											</td>
										</tr>
									<?php endif; ?>

									<?php
									// Events sections loop.
									foreach ( $alerts as $alert ) {
										if ( $alert->type <= 0006 ) {
											continue; // <- Ignore php alerts.
										}
										if ( 9999 === $alert->type ) {
											continue; // <- Ignore promo alerts.
										}
										$attrs = '';
										switch ( true ) {
											case ! $alert->mesg:
												$attrs = 'title="' . __( 'Not Implemented', 'wp-security-audit-log' ) . '" class="alert-incomplete"';
												break;
											case false:
												$attrs = 'title="' . __( 'Not Available', 'wp-security-audit-log' ) . '" class="alert-unavailable"';
												break;
										}
										if ( in_array( $alert->type, $subcat_alerts, true ) ) {
											?>
											<tr>
												<td colspan="4">
													<h3 class="sub-category">
														<?php
														if ( 1004 === $alert->type ) {
															esc_html_e( 'User Sessions', 'wp-security-audit-log' );
														} elseif ( 2010 === $alert->type ) {
															esc_html_e( 'Files', 'wp-security-audit-log' );
														} elseif ( 6007 === $alert->type ) {
															esc_html_e( 'System', 'wp-security-audit-log' );
														} elseif ( 2111 === $alert->type ) {
															esc_html_e( 'Post Settings', 'wp-security-audit-log' );
														} elseif ( 2119 === $alert->type ) {
															esc_html_e( 'Tags', 'wp-security-audit-log' );
														} elseif ( 2016 === $alert->type ) {
															esc_html_e( 'Categories', 'wp-security-audit-log' );
														} elseif ( 2053 === $alert->type ) {
															esc_html_e( 'Custom Fields', 'wp-security-audit-log' );
														} elseif ( 7000 === $alert->type ) {
															esc_html_e( 'Sites', 'wp-security-audit-log' );
														} elseif ( 8009 === $alert->type ) {
															esc_html_e( 'Settings', 'wp-security-audit-log' );
														} elseif ( 8014 === $alert->type ) {
															esc_html_e( 'Topics', 'wp-security-audit-log' );
														} elseif ( 4013 === $alert->type ) {
															esc_html_e( 'User Profile', 'wp-security-audit-log' );
														} elseif ( 9007 === $alert->type ) {
															esc_html_e( 'Product Admin', 'wp-security-audit-log' );
														} elseif ( 9047 === $alert->type ) {
															esc_html_e( 'Product Attribute', 'wp-security-audit-log' );
														} elseif ( 9027 === $alert->type ) {
															esc_html_e( 'Store Admin', 'wp-security-audit-log' );
														} elseif ( 9002 === $alert->type ) {
															esc_html_e( 'Categories', 'wp-security-audit-log' );
														} elseif ( 9057 === $alert->type ) {
															esc_html_e( 'Attributes', 'wp-security-audit-log' );
														} elseif ( 9063 === $alert->type ) {
															esc_html_e( 'Coupons', 'wp-security-audit-log' );
														} elseif ( 9035 === $alert->type ) {
															esc_html_e( 'Orders', 'wp-security-audit-log' );
														} elseif ( 9083 === $alert->type ) {
															esc_html_e( 'User Profile', 'wp-security-audit-log' );
														} elseif ( 8809 === $alert->type ) {
															esc_html_e( 'Website Changes', 'wp-security-audit-log' );
														} elseif ( 8813 === $alert->type ) {
															esc_html_e( 'Plugin Settings', 'wp-security-audit-log' );
														} elseif ( 6000 === $alert->type ) {
															esc_html_e( 'System', 'wp-security-audit-log' );
														} elseif ( 6001 === $alert->type ) {
															esc_html_e( 'Settings', 'wp-security-audit-log' );
														} elseif ( 6028 === $alert->type ) {
															esc_html_e( 'File Changes Scanning', 'wp-security-audit-log' );
														}
														?>
													</h3>
												</td>
											</tr>
											<?php
										}
										?>
										<tr <?php echo esc_attr( $attrs ); ?>>
											<th>
												<input
													name="alert[]"
													type="checkbox"
													class="alert"
													<?php checked( $active[ $alert->type ] ); ?>
													value="<?php echo esc_attr( (int) $alert->type ); ?>"
													<?php
													if ( ! empty( $disabled ) ) {
														echo esc_attr( $disabled );
													} elseif ( 'no' !== $this->_plugin->GetGlobalOption( 'disable-visitor-events', 'no' ) && in_array( $alert->type, $public_events, true ) ) {
														echo 'disabled';
													}
													?>
													<?php echo ( __( 'File Changes', 'wp-security-audit-log' ) === $subname ) ? 'onclick="wsal_toggle_file_changes(this)"' : false; ?>
												/>
											</th>
											<td><?php echo esc_html( str_pad( $alert->type, 4, '0', STR_PAD_LEFT ) ); ?></td>
											<td>
												<?php
												$severity_obj = $this->_plugin->constants->GetConstantBy( 'value', $alert->code );
												if ( 'E_CRITICAL' === $severity_obj->name ) {
													esc_html_e( 'Critical', 'wp-security-audit-log' );
												} elseif ( 'E_WARNING' === $severity_obj->name ) {
													esc_html_e( 'Warning', 'wp-security-audit-log' );
												} elseif ( 'E_NOTICE' === $severity_obj->name ) {
													esc_html_e( 'Notification', 'wp-security-audit-log' );
												} else {
													esc_html_e( 'Notification', 'wp-security-audit-log' );
												}
												?>
											</td>
											<td><?php echo esc_html( $alert->desc ); ?></td>
										</tr>
										<?php
										if ( 6007 === $alert->type ) {
											$log_404          = $this->_plugin->GetGlobalOption( 'log-404' );
											$purge_log        = $this->_plugin->GetGlobalOption( 'purge-404-log' );
											$log_404_referrer = $this->_plugin->GetGlobalOption( 'log-404-referrer', 'on' );
											?>
											<tr>
												<td></td>
												<td>
													<input name="log_404" type="checkbox" class="check_log" value="1"
														<?php checked( $log_404, 'on' ); ?> />
												</td>
												<td colspan="2"><?php esc_html_e( 'Capture 404 requests to file (the log file are created in the /wp-content/uploads/wp-security-audit-log/404s/ directory)', 'wp-security-audit-log' ); ?></td>
											</tr>
											<tr>
												<td></td>
												<td>
													<input name="purge_log" type="checkbox" class="check_log" value="1"
														<?php checked( $purge_log, 'on' ); ?> />
												</td>
												<td colspan="2"><?php esc_html_e( 'Purge log files older than one month', 'wp-security-audit-log' ); ?></td>
											</tr>
											<tr>
												<td></td>
												<td colspan="1"><input type="number" id="user_404Limit" name="user_404Limit" value="<?php echo esc_attr( $this->_plugin->settings->Get404LogLimit() ); ?>" /></td>
												<td colspan="2"><?php esc_html_e( 'Number of 404 Requests to Log. By default the plugin keeps up to 99 requests to non-existing pages from the same IP address. Increase the value in this setting to the desired amount to keep a log of more or less requests.', 'wp-security-audit-log' ); ?></td>
											</tr>
											<tr>
												<td></td>
												<td><input name="log_404_referrer" type="checkbox" class="check_log" value="1" <?php checked( $log_404_referrer, 'on' ); ?>></td>
												<td colspan="2"><?php esc_html_e( 'Record the referrer that generated the 404 error.', 'wp-security-audit-log' ); ?></td>
											</tr>
											<?php
										}
										if ( 6023 === $alert->type ) {
											$log_visitor_404          = $this->_plugin->GetGlobalOption( 'log-visitor-404' );
											$purge_visitor_log        = $this->_plugin->GetGlobalOption( 'purge-visitor-404-log' );
											$log_visitor_404_referrer = $this->_plugin->GetGlobalOption( 'log-visitor-404-referrer', 'on' );
											?>
											<tr>
												<td></td>
												<td>
													<input name="log_visitor_404" type="checkbox" class="check_visitor_log" value="1"
														<?php checked( $log_visitor_404, 'on' ); ?> />
												</td>
												<td colspan="2"><?php esc_html_e( 'Capture 404 requests to file (the log file are created in the /wp-content/uploads/wp-security-audit-log/404s/ directory)', 'wp-security-audit-log' ); ?></td>
											</tr>
											<tr>
												<td></td>
												<td>
													<input name="purge_visitor_log" type="checkbox" class="check_visitor_log" value="1"
														<?php checked( $purge_visitor_log, 'on' ); ?> />
												</td>
												<td colspan="2"><?php esc_html_e( 'Purge log files older than one month', 'wp-security-audit-log' ); ?></td>
											</tr>
											<tr>
												<td></td>
												<td colspan="1"><input type="number" id="visitor_404Limit" name="visitor_404Limit" value="<?php echo esc_attr( $this->_plugin->settings->GetVisitor404LogLimit() ); ?>" /></td>
												<td colspan="2"><?php esc_html_e( 'Number of 404 Requests to Log. By default the plugin keeps up to 99 requests to non-existing pages from the same IP address. Increase the value in this setting to the desired amount to keep a log of more or less requests. Note that by increasing this value to a high number, should your website be scanned the plugin will consume more resources to log all the requests.', 'wp-security-audit-log' ); ?></td>
											</tr>
											<tr>
												<td></td>
												<td><input name="log_visitor_404_referrer" type="checkbox" class="check_log" value="1" <?php checked( $log_visitor_404_referrer, 'on' ); ?>></td>
												<td colspan="2"><?php esc_html_e( 'Record the referrer that generated the 404 error.', 'wp-security-audit-log' ); ?></td>
											</tr>
											<?php
										}
										if ( 1002 === $alert->type ) {
											$log_failed_login_limit = (int) $this->_plugin->GetGlobalOption( 'log-failed-login-limit', 10 );
											$log_failed_login_limit = ( -1 === $log_failed_login_limit ) ? '0' : $log_failed_login_limit;
											?>
											<tr>
												<td></td>
												<td><input name="log_failed_login_limit" type="number" class="check_visitor_log" value="<?php echo esc_attr( $log_failed_login_limit ); ?>"></td>
												<td colspan="2">
													<?php esc_html_e( 'Number of login attempts to log. Enter 0 to log all failed login attempts. (By default the plugin only logs up to 10 failed login because the process can be very resource intensive in case of a brute force attack)', 'wp-security-audit-log' ); ?>
												</td>
											</tr>
											<?php
										}
										if ( 1003 === $alert->type ) {
											$log_visitor_failed_login_limit = (int) $this->_plugin->GetGlobalOption( 'log-visitor-failed-login-limit', 10 );
											$log_visitor_failed_login_limit = ( -1 === $log_visitor_failed_login_limit ) ? '0' : $log_visitor_failed_login_limit;
											?>
											<tr>
												<td></td>
												<td><input name="log_visitor_failed_login_limit" type="number" class="check_visitor_log" value="<?php echo esc_attr( $log_visitor_failed_login_limit ); ?>"></td>
												<td colspan="2">
													<p><?php esc_html_e( 'Number of login attempts to log. Enter 0 to log all failed login attempts. (By default the plugin only logs up to 10 failed login because the process can be very resource intensive in case of a brute force attack)', 'wp-security-audit-log' ); ?></p>
												</td>
											</tr>
											<?php
										}
										if ( 9019 === $alert->type ) {
											$wc_all_stock_changes = $this->_plugin->GetGlobalOption( 'wc-all-stock-changes', 'on' );
											?>
											<tr>
												<td></td>
												<td>
													<input name="wc_all_stock_changes" type="checkbox" id="wc_all_stock_changes" value="1" <?php checked( $wc_all_stock_changes, 'on' ); ?> />
												</td>
												<td colspan="2"><?php esc_html_e( 'Log all stock changes. Disable this setting to only keep a log of stock changes done manually via the WooCommerce dashboard. Therefore automated stock changes typically done via customers placing orders or via other plugins will not be logged.', 'wp-security-audit-log' ); ?></td>
											</tr>
											<?php
										}
									}

									// File integrity scan link.
									if ( __( 'File Changes', 'wp-security-audit-log' ) === $subname ) :
										$wsal_settings_page = '';
										$redirect_args      = array(
											'page' => 'wsal-settings',
											'tab'  => 'file-changes',
										);
										if ( ! is_multisite() ) {
											$wsal_settings_page = add_query_arg( $redirect_args, admin_url( 'admin.php' ) );
										} else {
											$wsal_settings_page = add_query_arg( $redirect_args, network_admin_url( 'admin.php' ) );
										}
										?>
										<tr>
											<td colspan="4">
												<a href="<?php echo esc_url( $wsal_settings_page ); ?>" class="wsal-tab-help">
													<?php esc_html_e( 'Configure the file integrity scan settings.', 'wp-security-audit-log' ); ?>
												</a>
											</td>
										</tr>
										<?php
									endif;
									?>
								</tbody>
							</table>
							<?php
						}
						?>
					</div>
				<?php endforeach; ?>
				<div class="wsal-tab" id="tab-visitor-events">
					<h4><?php esc_html_e( 'The plugin also keeps a log of some events that website visitors (non-logged in users) do because it is typically required by site admins. You can disable these events from here:', 'wp-security-audit-log' ); ?></h4>
					<table class="form-table">
						<th><label for="enable-visitor-events"><?php esc_html_e( 'Enable website visitors events', 'wp-security-audit-log' ); ?></label></th>
						<td>
							<fieldset>
								<?php $disable_visitor_events = $this->_plugin->GetGlobalOption( 'disable-visitor-events', 'no' ); ?>
								<label for="disable-visitor-events">
									<input type="checkbox" id="disable-visitor-events" name="disable-visitor-events" <?php checked( $disable_visitor_events, 'no' ); ?> value="no" />
									<?php esc_html_e( 'Enable', 'wp-security-audit-log' ); ?>
								</label>
							</fieldset>
						</td>
					</table>
					<p class="description"><?php esc_html_e( 'Below is the list of the events which are disabled when the above option is disabled:', 'wp-security-audit-log' ); ?></p>
					<ul>
						<?php
						$wsal_alerts = $this->_plugin->alerts->GetAlerts(); // Get alerts list.
						foreach ( $public_events as $public_event ) :
							if ( isset( $wsal_alerts[ $public_event ] ) ) :
								?>
								<li><?php echo esc_html( $wsal_alerts[ $public_event ]->type . ' — ' . $wsal_alerts[ $public_event ]->desc ); ?></li>
								<?php
							endif;
						endforeach;
						?>
					</ul>
				</div>
			</div>
			<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="<?php echo esc_attr( __( 'Save Changes', 'wp-security-audit-log' ) ); ?>"></p>
		</form>

		<?php if ( ! empty( $log_level ) ) : ?>
			<!-- Log level updated modal -->
			<div class="remodal" data-remodal-id="wsal-log-level-updated">
				<button data-remodal-action="close" class="remodal-close"></button>
				<h3><?php esc_html_e( 'Log Level Updated', 'wp-security-audit-log' ); ?></h3>
				<p>
					<?php
					/* translators: Alerts log level. */
					echo sprintf( esc_html__( 'The %s log level has been successfully loaded and applied.', 'wp-security-audit-log' ), $log_level );
					?>
				</p>
				<br>
				<button data-remodal-action="confirm" class="remodal-confirm"><?php esc_html_e( 'OK', 'wp-security-audit-log' ); ?></button>
			</div>
			<script type="text/javascript">
				jQuery( document ).ready( function() {
					var wsal_log_level_modal = jQuery( '[data-remodal-id="wsal-log-level-updated"]' );
					wsal_log_level_modal.remodal().open();
				} );
			</script>
			<?php
		endif;
		?>

		<!-- Terminal all sessions modal -->
		<div class="remodal" data-remodal-id="wsal-toggle-file-changes-scan">
			<button data-remodal-action="close" class="remodal-close"></button>
			<h3><?php esc_html_e( 'Enable File Integrity Scanner', 'wp-security-audit-log' ); ?></h3>
			<p>
				<?php esc_html_e( 'The file integrity scanner is switched off. To enable this event it has to be switched on.', 'wp-security-audit-log' ); ?>
			</p>
			<br>
			<input type="hidden" id="wsal-toggle-file-changes-nonce" value="<?php echo esc_attr( wp_create_nonce( 'wsal-toggle-file-changes' ) ); ?>">
			<button data-remodal-action="confirm" class="remodal-confirm"><?php esc_html_e( 'SWITCH ON', 'wp-security-audit-log' ); ?></button>
			<button data-remodal-action="cancel" class="remodal-cancel"><?php esc_html_e( 'DISABLE EVENT', 'wp-security-audit-log' ); ?></button>
		</div>
		<?php
	}

	/**
	 * Method: Get View Header.
	 */
	public function Header() {
		// Remodal styles.
		wp_enqueue_style( 'wsal-remodal', WSAL_BASE_URL . 'css/remodal.css', array(), '1.1.1' );
		wp_enqueue_style( 'wsal-remodal-theme', WSAL_BASE_URL . 'css/remodal-default-theme.css', array(), '1.1.1' );
		?>
		<style type="text/css">
			.wsal-tab {
				display: none;
			}
			.wsal-tab tr.alert-incomplete td {
				color: #9BE;
			}
			.wsal-tab tr.alert-unavailable td {
				color: #CCC;
			}
			.wsal-sub-tabs {
				padding-left: 20px;
			}
			.wsal-sub-tabs .nav-tab-active {
				background-color: #fff;
				border-bottom: 1px solid #fff;
			}
			.wsal-tab td input[type=number] {
				width: 100%;
			}
			.widefat td .wsal-tab-help {
				margin: 0 8px;
			}
			.widefat td .wsal-tab-notice {
				color: red;
			}
			.widefat .sub-category {
				margin: 0.5em 0;
				margin-left: 8px;
			}
		</style>
		<?php
	}

	/**
	 * Method: Get View Footer.
	 */
	public function Footer() {
		// Remodal script.
		wp_enqueue_script(
			'wsal-remodal-js',
			WSAL_BASE_URL . 'js/remodal.min.js',
			array(),
			'1.1.1',
			true
		);
		?>
		<script type="text/javascript">
			jQuery(document).ready(function(){
				var scrollHeight = jQuery(document).scrollTop();
				// tab handling code
				jQuery('#wsal-tabs>a').click(function(){
					jQuery('#wsal-tabs>a').removeClass('nav-tab-active');
					jQuery('.wsal-tab').hide();
					jQuery(jQuery(this).addClass('nav-tab-active').attr('href')).show();
					jQuery(jQuery(this).attr('href')+' .wsal-sub-tabs>a:first').click();
					setTimeout(function() {
						jQuery(window).scrollTop(scrollHeight);
					}, 1);
				});
				// sub tab handling code
				jQuery('.wsal-sub-tabs>a').click(function(){
					jQuery('.wsal-sub-tabs>a').removeClass('nav-tab-active');
					jQuery('.wsal-sub-tab').hide();
					jQuery(jQuery(this).addClass('nav-tab-active').attr('href')).show();
					setTimeout(function() {
						jQuery(window).scrollTop(scrollHeight);
					}, 1);
				});
				// checkbox handling code
				jQuery('.wsal-tab>thead>tr>th>:checkbox').change(function(){
					jQuery(this).parents('table:first').find('tbody>tr>th>:checkbox').attr('checked', this.checked);
				});
				jQuery('.wsal-tab>tbody>tr>th>:checkbox').change(function(){
					var allchecked = jQuery(this).parents('tbody:first').find('th>:checkbox:not(:checked)').length === 0;
					jQuery(this).parents('table:first').find('thead>tr>th:first>:checkbox:first').attr('checked', allchecked);
				});

				var hashlink = jQuery('#wsal-tabs>a[href="' + location.hash + '"]');
				var hashsublink = jQuery('.wsal-sub-tabs>a[href="' + location.hash + '"]');
				if (hashlink.length) {
					// show relevant tab
					hashlink.click();
				} else if (hashsublink.length) {
					// show relevant sub tab
					jQuery('#wsal-tabs>a[href="#' + hashsublink.data('parent') + '"]').click();
					hashsublink.click();
				} else {
					jQuery('#wsal-tabs>a:first').click();
					jQuery('.wsal-sub-tabs>a:first').click();
				}

				// Specific for alert 6007
				jQuery("input[value=6007]").on("change", function(){
					var check = jQuery("input[value=6007]").is(":checked");
					if(check) {
						jQuery(".check_log").attr ( "checked" ,"checked" );
					} else {
						jQuery(".check_log").removeAttr('checked');
					}
				});

				// Specific for alert 6023
				jQuery("input[value=6023]").on("change", function(){
					var check = jQuery("input[value=6023]").is(":checked");
					if(check) {
						jQuery(".check_visitor_log").attr ( "checked" ,"checked" );
					} else {
						jQuery(".check_visitor_log").removeAttr('checked');
					}
				});

				// Specific for alert 9019
				jQuery("input[value=9019]").on("change", function(){
					var check = jQuery("input[value=9019]").is(":checked");
					if(check) {
						jQuery("#wc_all_stock_changes").attr ( "checked" ,"checked" );
					} else {
						jQuery("#wc_all_stock_changes").removeAttr('checked');
					}
				});
			});

			var alerts = jQuery( '#alerts-file-changes .alert' ); // File change alerts.
			var toggle_modal = jQuery( '[data-remodal-id=wsal-toggle-file-changes-scan]' ); // File change toggle modal.

			function wsal_toggle_file_changes( element ) {
				if ( jQuery( element ).is( ':checked' ) ) {
					var alert_count = 0;

					for ( var index = 0; index < alerts.length; index++ ) {
						if ( jQuery( alerts[ index ] ).is( ':checked' ) ) {
							alert_count++;
						}
					}

					if ( alert_count === 1 ) {
						var modal = toggle_modal.remodal();
						modal.open();
					}
				}
			}

			jQuery(document).on('closed', toggle_modal, function (event) {
				if (event.reason && event.reason === 'cancellation') {
					for ( var index = 0; index < alerts.length; index++ ) {
						jQuery( alerts[ index ] ).prop( 'checked', false );
					}
				}
			});
		</script>
		<?php
	}
}
