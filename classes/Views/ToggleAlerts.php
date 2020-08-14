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

		$frontend_events = array(
			'register'    => false,
			'login'       => false,
			'system'      => false,
			'woocommerce' => false,
		);

		if ( isset( $post_array['frontend-events'] ) ) {
			$frontend_events = array_merge( $frontend_events, $post_array['frontend-events'] );
		}
		$this->_plugin->settings()->set_frontend_events( $frontend_events );

		$enabled  = array_map( 'intval', $post_array['alert'] );
		$disabled = array();
		foreach ( $this->_plugin->alerts->GetAlerts() as $alert ) {
			if ( 6023 === $alert->type && ! $frontend_events['system'] ) {
				$disabled[] = $alert->type;
				continue;
			} elseif ( 6023 === $alert->type ) {
				continue;
			} elseif ( 9036 === $alert->type ) {
				$frontend_events = WSAL_Settings::get_frontend_events();
				$frontend_events = array_merge( $frontend_events, array( 'woocommerce' => true ) );
				$this->_plugin->settings()->set_frontend_events( $frontend_events );
			}

			if ( ! in_array( $alert->type, $enabled, true ) ) {
				if ( 9036 === $alert->type ) {
					$frontend_events = WSAL_Settings::get_frontend_events();
					$frontend_events = array_merge( $frontend_events, array( 'woocommerce' => false ) );
					$this->_plugin->settings()->set_frontend_events( $frontend_events );
				}
				$disabled[] = $alert->type;
			}
		}

		// Save the disabled events.
		$this->_plugin->alerts->SetDisabledAlerts( $disabled );

		$this->_plugin->SetGlobalBooleanSetting( 'log-404', isset( $post_array['log_404'] ) );
		$this->_plugin->SetGlobalBooleanSetting( 'purge-404-log', isset( $post_array['purge_log'] ) );
		$this->_plugin->SetGlobalBooleanSetting( 'log-404-referrer', isset( $post_array['log_404_referrer'] ) );

		$this->_plugin->SetGlobalBooleanSetting( 'log-visitor-404', isset( $post_array['log_visitor_404'] ) );
		$this->_plugin->SetGlobalBooleanSetting( 'purge-visitor-404-log', isset( $post_array['purge_visitor_log'] ) );
		$this->_plugin->SetGlobalBooleanSetting( 'log-visitor-404-referrer', isset( $post_array['log_visitor_404_referrer'] ) );
		$this->_plugin->SetGlobalBooleanSetting( 'wc-all-stock-changes', isset( $post_array['wc_all_stock_changes'] ) );

		$this->_plugin->settings()->Set404LogLimit( $post_array['user_404Limit'] );
		$this->_plugin->settings()->SetVisitor404LogLimit( $post_array['visitor_404Limit'] );

		$this->_plugin->settings()->set_failed_login_limit( $post_array['log_failed_login_limit'] );
		$this->_plugin->settings()->set_visitor_failed_login_limit( $post_array['log_visitor_failed_login_limit'] );
	}

	/**
	 * Method: Get View.
	 */
	public function Render() {
		// Die if user does not have permission to view.
		if ( ! $this->_plugin->settings()->CurrentUserCan( 'edit' ) ) {
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
			$this->_plugin->SetGlobalSetting( 'details-level', $log_level );

			if ( 'basic' === $log_level ) {
				$this->_plugin->settings()->set_basic_mode();
			} elseif ( 'geek' === $log_level ) {
				$this->_plugin->settings()->set_geek_mode();
			}
		}

		$disabled_events = $this->_plugin->GetGlobalSetting( 'disabled-alerts' ); // Get disabled events.
		$disabled_events = explode( ',', $disabled_events );
		$events_diff     = array_diff( $disabled_events, $this->_plugin->settings()->geek_alerts ); // Calculate the difference of events.
		$events_diff     = array_filter( $events_diff ); // Remove empty values.
		$is_custom       = ! empty( $events_diff ) ? true : false; // If difference is not empty then mode is custom.
		$log_details     = $this->_plugin->GetGlobalSetting( 'details-level', false ); // Get log level option.

		$subcat_alerts   = array( 1004, 2010, 2111, 9007, 9105, 9047 );
		$obsolete_events = array( 9999, 2126, 6023, 9011, 9070, 9075, 4013 );
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
						<?php echo wp_kses( __( 'Use the Log level drop down menu above to use one of our preset log levels. Alternatively you can enable or disable any of the individual events from the below tabs. Refer to <a href="https://wpactivitylog.com/support/kb/list-wordpress-activity-log-event-ids/" target="_blank">the complete list of WordPress activity log event IDs</a> for reference on all the events the plugin can keep a log of.', 'wp-security-audit-log' ), $this->_plugin->allowed_html_tags ); ?>
					</p>
				</fieldset>
			</form>
		</p>
		<h2 id="wsal-tabs" class="nav-tab-wrapper">
			<?php foreach ( $safe_names as $name => $safe ) : ?>
				<a href="#tab-<?php echo esc_attr( $safe ); ?>" class="nav-tab"><?php echo esc_html( $name ); ?></a>
				<?php if ( __( 'Yoast SEO', 'wp-security-audit-log' ) === $name ) : ?>
					<a href="#tab-frontend-events" class="nav-tab">
						<?php esc_html_e( 'Front-end Events', 'wp-security-audit-log' ); ?>
					</a>
				<?php endif; ?>
			<?php endforeach; ?>
			<a href="#tab-third-party-plugins" class="nav-tab">
				<?php esc_html_e( 'Third party plugins', 'wp-security-audit-log' ); ?>
			</a>
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
								if ( in_array( $alert->type, $obsolete_events, true ) ) {
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
								'bbPress Forums' === $name
								|| 'WooCommerce' === $name
								|| 'Yoast SEO' === $name
								|| 'Multisite Network Sites' === $name
								|| 'User Accounts' === $name
							) {
								switch ( $name ) {
									case 'User Accounts':
										if ( 'Multisite User Profiles' === $subname ) {
											// Check if this is a multisite.
											if ( ! is_multisite() ) {
												$disabled = 'disabled';
											}
										}
										break;

									case 'WooCommerce':
									case 'WooCommerce Products':
										// Check if WooCommerce plugin exists.
										if ( ! WpSecurityAuditLog::is_woocommerce_active() ) {
											$disabled = 'disabled';
										}
										break;

									case 'Yoast SEO':
										// Check if Yoast SEO plugin exists.
										if ( ! WpSecurityAuditLog::is_wpseo_active() ) {
											$disabled = 'disabled';
										}
										break;

									case 'Multisite Network Sites':
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
								<tbody>
									<?php if ( __( 'Content', 'wp-security-audit-log' ) === $subname ) : ?>
										<tr>
											<td colspan="4">
												<p class="wsal-tab-help description"><?php echo wp_kses( __( '<strong>Note:</strong> Post refers to any type of content, i.e. blog post, page or a post with a custom post type.', 'wp-security-audit-log' ), $this->_plugin->allowed_html_tags ); ?></p>
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

										if ( __( 'Monitor File Changes', 'wp-security-audit-log' ) === $subname && ! defined( 'WFCM_PLUGIN_FILE' ) ) {
											break;
										}

										if ( in_array( $alert->type, $obsolete_events, true ) ) {
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
														} elseif ( 2111 === $alert->type ) {
															esc_html_e( 'Post Settings', 'wp-security-audit-log' );
														} elseif ( 9007 === $alert->type ) {
															esc_html_e( 'Product Admin', 'wp-security-audit-log' );
														} elseif ( 9105 === $alert->type ) {
															esc_html_e( 'Product Stock Changes', 'wp-security-audit-log' );
														} elseif ( 9047 === $alert->type ) {
															esc_html_e( 'Product Attributes', 'wp-security-audit-log' );
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
												} elseif ( 'WSAL_CRITICAL' === $severity_obj->name ) {
													esc_html_e( 'Critical', 'wp-security-audit-log' );
												} elseif ( 'WSAL_HIGH' === $severity_obj->name ) {
													esc_html_e( 'High', 'wp-security-audit-log' );
												} elseif ( 'WSAL_MEDIUM' === $severity_obj->name ) {
													esc_html_e( 'Medium', 'wp-security-audit-log' );
												} elseif ( 'WSAL_LOW' === $severity_obj->name ) {
													esc_html_e( 'Low', 'wp-security-audit-log' );
												} elseif ( 'WSAL_INFORMATIONAL' === $severity_obj->name ) {
													esc_html_e( 'Informational', 'wp-security-audit-log' );
												} else {
													esc_html_e( 'Notification', 'wp-security-audit-log' );
												}
												?>
											</td>
											<td><?php echo esc_html( $alert->desc ); ?></td>
										</tr>
										<?php
										if ( 6007 === $alert->type ) {
											$log_404          = $this->_plugin->GetGlobalBooleanSetting( 'log-404' );
											$purge_log        = $this->_plugin->GetGlobalBooleanSetting( 'purge-404-log' );
											$log_404_referrer = $this->_plugin->GetGlobalBooleanSetting( 'log-404-referrer', true );
											?>
											<tr>
												<td></td>
												<td>
													<input name="log_404" type="checkbox" class="check_log" value="1"
														<?php checked( $log_404 ); ?> />
												</td>
												<td colspan="2"><?php esc_html_e( 'Capture 404 requests to file (the log file are created in the /wp-content/uploads/wp-activity-log/404s/ directory)', 'wp-security-audit-log' ); ?></td>
											</tr>
											<tr>
												<td></td>
												<td>
													<input name="purge_log" type="checkbox" class="check_log" value="1"
														<?php checked( $purge_log ); ?> />
												</td>
												<td colspan="2"><?php esc_html_e( 'Purge log files older than one month', 'wp-security-audit-log' ); ?></td>
											</tr>
											<tr>
												<td></td>
												<td colspan="1"><input type="number" id="user_404Limit" name="user_404Limit" value="<?php echo esc_attr( $this->_plugin->settings()->Get404LogLimit() ); ?>" /></td>
												<td colspan="2"><?php esc_html_e( 'Number of 404 Requests to Log. By default the plugin keeps up to 99 requests to non-existing pages from the same IP address. Increase the value in this setting to the desired amount to keep a log of more or less requests.', 'wp-security-audit-log' ); ?></td>
											</tr>
											<tr>
												<td></td>
												<td><input name="log_404_referrer" type="checkbox" class="check_log" value="1" <?php checked( $log_404_referrer ); ?>></td>
												<td colspan="2"><?php esc_html_e( 'Record the referrer that generated the 404 error.', 'wp-security-audit-log' ); ?></td>
											</tr>
											<?php
										}
										if ( 1002 === $alert->type ) {
											$log_failed_login_limit = (int) $this->_plugin->GetGlobalSetting( 'log-failed-login-limit', 10 );
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
											$log_visitor_failed_login_limit = (int) $this->_plugin->GetGlobalSetting(  'log-visitor-failed-login-limit', 10 );
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
											$wc_all_stock_changes = $this->_plugin->GetGlobalBooleanSetting( 'wc-all-stock-changes', true );
											?>
											<tr>
												<td></td>
												<td>
													<input name="wc_all_stock_changes" type="checkbox" id="wc_all_stock_changes" value="1" <?php checked( $wc_all_stock_changes ); ?> />
												</td>
												<td colspan="2"><?php esc_html_e( 'Log all stock changes. Disable this setting to only keep a log of stock changes done manually via the WooCommerce dashboard. Therefore automated stock changes typically done via customers placing orders or via other plugins will not be logged.', 'wp-security-audit-log' ); ?></td>
											</tr>
											<?php
										}
									}

									// File integrity scan link.
									if ( __( 'Monitor File Changes', 'wp-security-audit-log' ) === $subname && ! defined( 'WFCM_PLUGIN_FILE' ) ) :
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
											<td>
												<div class="addon-wrapper">
													<img src="<?php echo trailingslashit( WSAL_BASE_URL ) . 'img/help/website-file-changes-monitor.jpg'; ?>">
													<h4><?php echo esc_html__( 'Website File Changes Monitor', 'wp-security-audit-log' ); ?></h4>
													<p><?php echo esc_html__( 'To keep a log of file changes please install Website File Changes Monitor, a plugin which is also developed by us.', 'wp-security-audit-log' ); ?></p><br>
													<p><button class="install-addon button button-primary" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wsal-install-addon' ) ); ?>" data-plugin-slug="website-file-changes-monitor/website-file-changes-monitor.php" data-plugin-download-url="https://downloads.wordpress.org/plugin/website-file-changes-monitor.latest-stable.zip"><?php _e( 'Install plugin now', 'wp-security-audit-log' ); ?></button><span class="spinner" style="display: none; visibility: visible; float: none; margin: 0 0 0 8px;"></span> <a href="https://wpactivitylog.com/support/kb/wordpress-files-changes-warning-activity-logs/?utm_source=plugin&utm_medium=referral&utm_campaign=WSAL&utm_content=settings+pages" target="_blank" style="margin-left: 15px;"><?php echo esc_html__( 'Learn More', 'wp-security-audit-log' ); ?></a></p>
												</div>
											</td>
										</tr>
										<style type="text/css">
										#tab-monitor-file-changes thead {
											display: none;
										}
										</style>
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
				<?php
				$frontend_events = WSAL_Settings::get_frontend_events();
				?>
				<table id="tab-frontend-events" class="form-table wp-list-table wsal-tab widefat fixed"  style="display: table;" cellspacing="0">
					<tbody>
						<tr>
							<td>
								<p><?php esc_html_e( 'This plugin keeps a log of what your website users are doing when they are logged in. On top of that it can also keep a log of some important events of (non logged in) website visitors. Use the below settings to enable / disable any of the front-end sensors:', 'wp-security-audit-log' ); ?></p>
							</td>
						</tr>
						<tr>
							<td>
								<h3 style="margin:0"><?php esc_html_e( 'Front-end users registrations', 'wp-security-audit-log' ); ?></h3>
							</td>
						</tr>
						<tr>
							<th><input type="checkbox" name="frontend-events[register]" id="frontend-events-register" value="1" <?php checked( $frontend_events['register'] ); ?>></th>
							<td>
								<label for="frontend-events-register"><?php esc_html_e( 'Keep a log when a visitor registers a user on the website. Only enable this if you allow visitors to register as users on your website. User registration is disabled by default in WordPress.', 'wp-security-audit-log' ); ?></label>
							</td>
						</tr>
						<tr>
							<td>
								<h3 style="margin:0"><?php esc_html_e( 'Front-end users logins', 'wp-security-audit-log' ); ?></h3>
							</td>
						</tr>
						<tr>
							<th><input type="checkbox" name="frontend-events[login]" id="frontend-events-login" value="1" <?php checked( $frontend_events['login'] ); ?>></th>
							<td>
								<label for="frontend-events-login"><?php esc_html_e( 'Keep a log when users login to the website from other login pages / forms other than the default WordPress login page.', 'wp-security-audit-log' ); ?></label>
							</td>
						</tr>
						<tr>
							<td>
								<h3 style="margin:0"><?php esc_html_e( 'Website visitors 404 errors', 'wp-security-audit-log' ); ?></h3>
							</td>
						</tr>
						<tr>
							<th><input type="checkbox" name="frontend-events[system]" id="frontend-events-system" value="1" <?php checked( $frontend_events['system'] ); ?>></th>
							<td >
								<label for="frontend-events-system"><?php esc_html_e( 'Event ID 6023: Keep a log when a website visitor requests a non-existing URL (HTTP 404 response error).', 'wp-security-audit-log' ); ?></label>
							</td>
						</tr>
						<?php
						$log_visitor_404          = $this->_plugin->GetGlobalBooleanSetting( 'log-visitor-404' );
						$purge_visitor_log        = $this->_plugin->GetGlobalBooleanSetting( 'purge-visitor-404-log' );
						$log_visitor_404_referrer = $this->_plugin->GetGlobalBooleanSetting( 'log-visitor-404-referrer', 'on' );
						?>
						<tr>
							<td><input name="log_visitor_404" type="checkbox" class="check_visitor_log" value="1" <?php checked( $log_visitor_404 ); ?> /></td>
							<td><?php esc_html_e( 'Capture 404 requests to file (the log file are created in the /wp-content/uploads/wp-activity-log/404s/ directory)', 'wp-security-audit-log' ); ?></td>
						</tr>
						<tr>
							<td><input name="purge_visitor_log" type="checkbox" class="check_visitor_log" value="1" <?php checked( $purge_visitor_log ); ?> /></td>
							<td><?php esc_html_e( 'Purge log files older than one month', 'wp-security-audit-log' ); ?></td>
						</tr>
						<tr>
							<td><input type="number" id="visitor_404Limit" name="visitor_404Limit" value="<?php echo esc_attr( $this->_plugin->settings()->GetVisitor404LogLimit() ); ?>" /></td>
							<td><?php esc_html_e( 'Number of 404 Requests to Log. By default the plugin keeps up to 99 requests to non-existing pages from the same IP address. Increase the value in this setting to the desired amount to keep a log of more or less requests. Note that by increasing this value to a high number, should your website be scanned the plugin will consume more resources to log all the requests.', 'wp-security-audit-log' ); ?></td>
						</tr>
						<tr>
							<td><input name="log_visitor_404_referrer" type="checkbox" class="check_log" value="1" <?php checked( $log_visitor_404_referrer ); ?>></td>
							<td><?php esc_html_e( 'Record the referrer that generated the 404 error.', 'wp-security-audit-log' ); ?></td>
						</tr>
					</tbody>
				</table>
				<?php
					$addons = new WSAL_PluginInstallAndActivate();
					$addons->render();
				?>
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

			#tab-frontend-events tr input[type=number]::-webkit-inner-spin-button,
			#tab-frontend-events tr input[type=number]::-webkit-outer-spin-button {
				-webkit-appearance: none;
				margin: 0;
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
			table#tab-frontend-events {
				margin-top: 0;
			}
			table#tab-frontend-events tr {
				display: table;
			}

			table#tab-frontend-events tr th {
				width: 20px;
				padding-left: 10px;
			}

			table#tab-frontend-events tr td:first-child {
				padding-left: 55px;
			}

			table#tab-frontend-events tr:first-child td:first-child {
				padding-left: 10px;
			}

			table#tab-frontend-events tr:nth-child(2) td:first-child,
			table#tab-frontend-events tr:nth-child(4) td:first-child,
			table#tab-frontend-events tr:nth-child(6) td:first-child,
			table#tab-frontend-events tr:nth-child(12) td:first-child {
				padding-left: 10px;
			}
			[href="#tab-0" i], [data-parent="tab-wpforms"] {
				display: none;
			}
			.addon-wrapper img {
				max-width: 200px;
			}
			.addon-wrapper {
				max-width: 25%;
				display: inline-block;
				border: 1px solid #eee;
				padding: 20px;
				text-align: center;
			}
			.addon-wrapper:hover {
				border: 1px solid #ccc;
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
