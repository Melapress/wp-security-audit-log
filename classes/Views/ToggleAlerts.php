<?php
/**
 * Settings Page
 *
 * Settings page of the plugin.
 *
 * @since   1.0.0
 * @package wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enable/Disable Alerts Page.
 *
 * @package wsal
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
		return 9;
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

		// Assume front end events are disabled unless we are told otherwise.
		$frontend_events = array(
			'register'    => false,
			'login'       => false,
			'woocommerce' => false,
		);

		// Check for enabled front end events and merge result with above array.
		if ( isset( $post_array['frontend-events'] ) ) {
			$frontend_events = array_merge( $frontend_events, $post_array['frontend-events'] );
		}

		// Save enabled front end events.
		WSAL_Settings::set_frontend_events( $frontend_events );

		// Ensure we attempt to save even if eveything is disabled.
		$post_array['alert'] = ( isset( $post_array['alert'] ) ) ? $post_array['alert'] : [];

		$enabled           = array_map( 'intval', $post_array['alert'] );
		$disabled          = array();
		$registered_alerts = $this->_plugin->alerts->GetAlerts();
		$disabled          = apply_filters( 'wsal_save_settings_disabled_events', $disabled, $registered_alerts, $frontend_events, $enabled );

		// Save the disabled events.
		$this->_plugin->alerts->SetDisabledAlerts( $disabled ); // Save the disabled events.

		// Update failed login limits.
		$this->_plugin->settings()->set_failed_login_limit( $post_array['log_failed_login_limit'] );
		$this->_plugin->settings()->set_visitor_failed_login_limit( $post_array['log_visitor_failed_login_limit'] );

		// Allow 3rd parties to process and save more of the posted data.
		do_action( 'wsal_togglealerts_process_save_settings', $post_array );

	}

	/**
	 * Method: Get View.
	 */
	public function Render() {
		// Die if user does not have permission to view.
		if ( ! $this->_plugin->settings()->CurrentUserCan( 'edit' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-security-audit-log' ) );
		}

		// Filter $_POST array.
		$post_array = filter_input_array( INPUT_POST );

		if ( isset( $post_array['submit'] ) ) {
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
		$log_level_to_set       = isset( $post_array['wsal-log-level'] ) ? sanitize_text_field( $post_array['wsal-log-level'] ) : false;
		$log_level_nonce = isset( $post_array['wsal-log-level-nonce'] ) ? sanitize_text_field( $post_array['wsal-log-level-nonce'] ) : false;

		if ( wp_verify_nonce( $log_level_nonce, 'wsal-log-level' ) ) {
			$this->_plugin->SetGlobalSetting( 'details-level', $log_level_to_set );

			if ( 'basic' === $log_level_to_set ) {
				$this->_plugin->settings()->set_basic_mode();
			} elseif ( 'geek' === $log_level_to_set ) {
				$this->_plugin->settings()->set_geek_mode();
			}
		}

		$alert          = new WSAL_Alert(); // IDE type hinting.
		$grouped_alerts = $this->_plugin->alerts->GetCategorizedAlerts( false );
		$safe_names     = array_map( array( $this, 'GetSafeCatgName' ), array_keys( $grouped_alerts ) );
		$safe_names     = array_combine( array_keys( $grouped_alerts ), $safe_names );

		$disabled_events = $this->_plugin->GetGlobalSetting( 'disabled-alerts' ); // Get disabled events.
		$disabled_events = explode( ',', $disabled_events );

		//  check if the log level is custom
		$log_level = $this->get_log_level_based_on_events( $disabled_events );
		$log_level_options = [
			'basic'  => esc_html__( 'Basic', 'wp-security-audit-log' ),
			'geek'   => esc_html__( 'Geek', 'wp-security-audit-log' ),
			'custom' => esc_html__( 'Custom', 'wp-security-audit-log' ),
		];

		$subcat_alerts   = array( 1000, 1004, 2010, 2111 );

		// Allow further items to be added externally.
		$subcat_alerts = apply_filters( 'wsal_togglealerts_sub_category_events', $subcat_alerts );

		$obsolete_events = array( 9999, 2126, 99999 );
		$obsolete_events = apply_filters( 'wsal_togglealerts_obsolete_events', $obsolete_events );

        //  check if the disabled events are enforced from the MainWP master site
        $settings = $this->_plugin->settings();
        $enforced_settings = $settings->get_mainwp_enforced_settings();
        $disabled_events_enforced_by_mainwp = array_key_exists( 'disabled_events', $enforced_settings ) && ! empty( $enforced_settings[ 'disabled_events' ]);
		?>
	
		<h2 id="wsal-tabs" class="nav-tab-wrapper">
			<a href="#tab-all" class="nav-tab"><?php esc_html_e( 'Enable/Disable alerts', 'wp-security-audit-log' ); ?></a>
			<a href="#tab-third-party-plugins" class="nav-tab">
				<?php esc_html_e( 'Third party plugins', 'wp-security-audit-log' ); ?>
			</a>
		</h2>	

		<div class="nav-tabs">
				
			<div id="tab-all" class="wsal-tab widefat fixed">

				<form method="post" id="wsal-alerts-level">
					<?php wp_nonce_field( 'wsal-log-level', 'wsal-log-level-nonce' ); ?>
					<fieldset>
						<label for="wsal-log-level"><?php esc_html_e( 'Log Level: ', 'wp-security-audit-log' ); ?></label>
						<select name="wsal-log-level" id="wsal-log-level" onchange="this.form.submit()"<?php if ( $disabled_events_enforced_by_mainwp ): ?> disabled="disabled"<?php endif; ?>>
							<?php foreach ( $log_level_options as $log_level_id => $log_level_label ): ?>
								<option value="<?php echo $log_level_id; ?>" <?php echo selected( $log_level, $log_level_id ); ?>><?php echo $log_level_label; ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php echo wp_kses( __( 'Use the Log level drop down menu above to use one of our preset log levels. Alternatively you can enable or disable any of the individual events from the below tabs. Refer to <a href="https://wpactivitylog.com/support/kb/list-wordpress-activity-log-event-ids/" target="_blank">the complete list of WordPress activity log event IDs</a> for reference on all the events the plugin can keep a log of.', 'wp-security-audit-log' ), $this->_plugin->allowed_html_tags ); ?>
						</p>
					</fieldset>
				</form>

				<form id="audit-log-viewer" method="post">

					<input type="hidden" name="page" value="<?php echo isset( $_GET['page'] ) ? esc_attr( sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) : false; ?>" />
					<?php wp_nonce_field( 'wsal-togglealerts' ); ?>

							<div id="flitering-options-wrapper">
								<div class="search">
									<span class="search-title"><?php esc_html_e( 'Choose your query and enter your search term', 'wp-security-audit-log' ); ?></span>
									<select name="searchingFor" id="search-subject" class="search-input-style">
										<option value="code"><?php esc_html_e( 'Event code', 'wp-security-audit-log' ); ?></option>
										<option value="severity"><?php esc_html_e( 'Severity', 'wp-security-audit-log' ); ?></option>
										<option value="desc"><?php esc_html_e( 'Description', 'wp-security-audit-log' ); ?></option>
									</select>
									<input type="text" id="query-input" class="search-input-style" onkeyup="filterEventTable()" placeholder="Enter search term">
								</div>
								<div class="center-bit"><span><?php esc_html_e( 'OR', 'wp-security-audit-log' ); ?></span></div>
								<div class="choose">
									<span class="search-title"><?php esc_html_e( 'Choose a category', 'wp-security-audit-log' ); ?></span>
									<select name="catFilter" id="filter-cat" class="search-input-style">
										<option value="all"><?php esc_html_e( 'All categories', 'wp-security-audit-log' ); ?></option>
									</select>
								</div>
							</div>

							<table id="event-toggle-table">
								<thead>
									<tr>
										<?php
										foreach ( $grouped_alerts as $name => $group ) {
											foreach ( $group as $subname => $alerts ) {
												$active    = array();
												$allactive = true;
												/** @var WSAL_Alert $alert */
												foreach ( $alerts as $alert ) {
													if ( $alert->code <= 0006 ) {
														continue; // <- Ignore php alerts.
													}
													if ( in_array( $alert->code, $obsolete_events, true ) ) {
														continue; // <- Ignore promo alerts.
													}
													$active[ $alert->code ] = $this->_plugin->alerts->IsEnabled( $alert->code );
													if ( ! $active[ $alert->code ] ) {
														$allactive = false;
													}
												}
											}
										}
											?>
										<th width="48"><input type="checkbox" <?php checked( $allactive ); ?> /></th>
										<th width="80"><?php esc_html_e( 'Code', 'wp-security-audit-log' ); ?></th>
										<th width="100"><?php esc_html_e( 'Severity', 'wp-security-audit-log' ); ?></th>
										<th><?php esc_html_e( 'Description', 'wp-security-audit-log' ); ?></th>
										<th style="display: none;"></th>
										<th style="display: none;"></th>
									</tr>
								</thead>
								<tbody>
									<?php

									foreach ( $grouped_alerts as $name => $group ) {
										foreach ( $group as $subname => $alerts ) {
											$active    = array();
											$allactive = true;
											/** @var WSAL_Alert $alert */
											foreach ( $alerts as $alert ) {
												$active[ $alert->code ] = $this->_plugin->alerts->IsEnabled( $alert->code );
												if ( ! $active[ $alert->code ] ) {
													$allactive = false;
												}
											}

											// Disabled alerts.
											$disable_inputs = '';
											$disabled_message = '';

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
																$disable_inputs = 'disabled';
															}
														}
														break;

													case 'WooCommerce':
													case 'WooCommerce Products':
														// Check if WooCommerce plugin exists.
														if ( ! WpSecurityAuditLog::is_woocommerce_active() ) {
															$disable_inputs = 'disabled';
															$disabled_message = esc_html__( 'Please activate WooCommerce to enable this alert', 'wp-security-audit-log' );
														}
														break;

													case 'Yoast SEO':
														// Check if Yoast SEO plugin exists.
														if ( ! WpSecurityAuditLog::is_wpseo_active() ) {
															$disable_inputs = 'disabled';
															$disabled_message = esc_html__( 'Please activate the Yoast SEO Plugin', 'wp-security-audit-log' );
														}
														break;

													case 'Multisite Network Sites':
														// Disable if not multisite.
														if ( ! is_multisite() ) {
															$disable_inputs = 'disabled';
															$disabled_message = esc_html__( 'Your website is a single site so the multisite events have been disabled.', 'wp-security-audit-log' );
														}
														break;

													default:
														break;
												}
											}

											if ( $disabled_events_enforced_by_mainwp ) {
												$disable_inputs = 'disabled';
											}

											foreach ( $alerts as $alert ) {

												if ( $alert->code <= 0006 ) {
													continue; // <- Ignore php alerts.
												}
												if ( in_array( $alert->code, $obsolete_events, true ) ) {
													continue; // <- Ignore promo alerts.
												}

												$disable_inputs_needed = ( ! empty( $disable_inputs ) ) ? esc_attr( $disable_inputs ) : '';
												$disabled_tooltip      = ( $disabled_message ) ? 'data-tooltip="' . $disabled_message . '"' : '';
												$checkbox_markup       = '<input name="alert[]" type="checkbox" class="alert"'. checked( $this->_plugin->alerts->IsEnabled( $alert->code ), true, false ) .' value="'. esc_attr( (int) $alert->code ) .'" '. $disable_inputs_needed .' />';
												$severity              = '';
												$severity_obj		   = $this->_plugin->constants->GetConstantBy( 'value', $alert->severity );

												if ( is_object( $severity_obj ) ) {
													if ( 'E_CRITICAL' === $severity_obj->name ) {
														$severity = esc_html__( 'Critical', 'wp-security-audit-log' );
													} elseif ( 'E_WARNING' === $severity_obj->name ) {
														$severity = esc_html__( 'Warning', 'wp-security-audit-log' );
													} elseif ( 'E_NOTICE' === $severity_obj->name ) {
														$severity = esc_html__( 'Notification', 'wp-security-audit-log' );
													} elseif ( 'WSAL_CRITICAL' === $severity_obj->name ) {
														$severity = esc_html__( 'Critical', 'wp-security-audit-log' );
													} elseif ( 'WSAL_HIGH' === $severity_obj->name ) {
														$severity = esc_html__( 'High', 'wp-security-audit-log' );
													} elseif ( 'WSAL_MEDIUM' === $severity_obj->name ) {
														$severity = esc_html__( 'Medium', 'wp-security-audit-log' );
													} elseif ( 'WSAL_LOW' === $severity_obj->name ) {
														$severity = esc_html__( 'Low', 'wp-security-audit-log' );
													} elseif ( 'WSAL_INFORMATIONAL' === $severity_obj->name ) {
														$severity = esc_html__( 'Informational', 'wp-security-audit-log' );
													} else {
														$severity = esc_html__( 'Notification', 'wp-security-audit-log' );
													}
												}	

												echo '<tr class="alert-wrapper '. $disable_inputs_needed .'" data-alert-cat="' . $alert->catg . '" data-alert-subcat="' . $alert->subcatg . '" ' . $disabled_tooltip . '>';
													echo '<th>' . $checkbox_markup . '</th>';
													echo '<td>' . $alert->code . '</td>';
													echo '<td>' . $severity . '</td>';
													echo '<td>' . $alert->desc . '</td>';
													echo '<td style="display: none;">' . $alert->catg . '</td>';
													echo '<td style="display: none;">' . $alert->subcatg . '</td>';
												echo '</tr>';

												if ( 4000 === $alert->code ) {
													$frontend_events        = WSAL_Settings::get_frontend_events();
													?>
													<tr class="alert-wrapper" data-alert-cat="Users Logins & Sessions Events" data-alert-subcat="User Activity">													
														<td></td>
														<td><input type="checkbox" name="frontend-events[register]" id="frontend-events-register" value="1" <?php checked( $frontend_events['register'] ); ?>></td>
														<td colspan="2">
															<label for="frontend-events-register"><?php esc_html_e( 'Keep a log when a visitor registers a user on the website. Only enable this if you allow visitors to register as users on your website. User registration is disabled by default in WordPress.', 'wp-security-audit-log' ); ?></label>
														</td>
													</tr>
													<?php
												}

												if ( 1002 === $alert->code ) {
													$log_failed_login_limit = (int) $this->_plugin->GetGlobalSetting( 'log-failed-login-limit', 10 );
													$log_failed_login_limit = ( -1 === $log_failed_login_limit ) ? '0' : $log_failed_login_limit;
													?>
													<tr class="alert-wrapper" data-alert-cat="Users Logins & Sessions Events" data-alert-subcat="User Activity">
														<td></td>
														<td><input name="log_failed_login_limit" type="number" class="check_visitor_log" value="<?php echo esc_attr( $log_failed_login_limit ); ?>"></td>
														<td colspan="2">
															<?php esc_html_e( 'Number of login attempts to log. Enter 0 to log all failed login attempts. (By default the plugin only logs up to 10 failed login because the process can be very resource intensive in case of a brute force attack)', 'wp-security-audit-log' ); ?>
														</td>
													</tr>
													<?php
												}
												if ( 1003 === $alert->code ) {
													$log_visitor_failed_login_limit = (int) $this->_plugin->GetGlobalSetting( 'log-visitor-failed-login-limit', 10 );
													$log_visitor_failed_login_limit = ( -1 === $log_visitor_failed_login_limit ) ? '0' : $log_visitor_failed_login_limit;
													?>
													<tr class="alert-wrapper" data-alert-cat="Users Logins & Sessions Events" data-alert-subcat="User Activity">
														<td></td>
														<td><input name="log_visitor_failed_login_limit" type="number" class="check_visitor_log" value="<?php echo esc_attr( $log_visitor_failed_login_limit ); ?>"></td>
														<td colspan="2">
															<p><?php esc_html_e( 'Number of login attempts to log. Enter 0 to log all failed login attempts. (By default the plugin only logs up to 10 failed login because the process can be very resource intensive in case of a brute force attack)', 'wp-security-audit-log' ); ?></p>
														</td>
													</tr>
													<?php
												}

												if ( 1000 === $alert->code ) {
													$frontend_events        = WSAL_Settings::get_frontend_events();
													?>
													<tr class="alert-wrapper" data-alert-cat="Users Logins & Sessions Events" data-alert-subcat="User Activity">
														<td></td>
														<td><input type="checkbox" name="frontend-events[login]" id="frontend-events-login" value="1" <?php checked( $frontend_events['login'] ); ?>></td>														
														<td colspan="3">
															<label for="frontend-events-login"><?php esc_html_e( 'Keep a log of user log in activity on custom login forms (such as WooCommerce & membership plugins)', 'wp-security-audit-log' ); ?></label>
														</td>
													</tr>
													<?php
												}

												do_action( 'wsal_togglealerts_append_content_to_toggle', $alert->code );
											}
										}
									}

									?>
								</tbody>
							</table>

					<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="<?php echo esc_attr( __( 'Save Changes', 'wp-security-audit-log' ) ); ?>"></p>
				</form>

			</div>
			<?php
				$addons = new WSAL_PluginInstallAndActivate();
				$addons->render();
			?>			
		</div>


		<?php if ( ! empty( $log_level_to_set ) ) : ?>
			<!-- Log level updated modal -->
			<div class="remodal" data-remodal-id="wsal-log-level-updated">
				<button data-remodal-action="close" class="remodal-close"></button>
				<h3><?php esc_html_e( 'Log Level Updated', 'wp-security-audit-log' ); ?></h3>
				<p>
					<?php
					/* translators: Alerts log level. */
					echo sprintf( esc_html__( 'The %s log level has been successfully loaded and applied.', 'wp-security-audit-log' ), $log_level_to_set );
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


		// Darktooltip styles.
		wp_enqueue_style(
			'darktooltip',
			WSAL_BASE_URL . 'css/darktooltip.css',
			array(),
			'0.4.0'
		);

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
			/* Extensions tab */
			[href="#tab-0" i], [data-parent="tab-wpforms"], [data-parent="tab-gravity-forms"] {
				display: none;
			}
			#extension-wrapper {
				display: flex;
				flex-wrap: wrap;
				flex-direction: row;
				justify-content: space-between;
			}
			.addon-wrapper img {
				max-width: 200px;
			}
			.addon-wrapper {
				flex: 1 0 30%;
				margin: 0 5px 11px 5px;
				border: 1px solid #eee;
				padding: 20px;
			}
			.addon-wrapper:hover {
				border: 1px solid #ccc;
			}
			.addon-wrapper .button-primary {
				position: relative;
				padding: 13px 26px !important;
				font-weight: 500;
				font-size: 16px;
				line-height: 1;
				color: white;
				background: #007cba;
				border: none;
				outline: none;
				overflow: hidden;
				cursor: pointer;
				filter: drop-shadow(0 2px 8px rgba(39, 94, 254, 0.32));
				transition: 0.3s cubic-bezier(0.215, 0.61, 0.355, 1);
				line-height: 1 !important;
			}

			#flitering-options-wrapper {
				display: flex;
			}
			#flitering-options-wrapper .search, #flitering-options-wrapper .choose  {
				padding: 10px 0;
			}
			#flitering-options-wrapper > div {
				min-width: 80px;
			}
			.search-title {
				display: flex;
				margin-bottom: 10px;
			}
			.center-bit {
				text-align: center;
				line-height: 82px;
			}
			.center-bit span {
				background: #2271b1;
				color: #fff;
				width: 48px;
				height: 48px;
				border-radius: 50%;
				display: inline-block;
				line-height: 47px;
				margin-top: 20px;
			}
			.search-input-style {
				background-position: 10px 12px;
				background-repeat: no-repeat;
				font-size: 16px;
				margin-bottom: 12px;
			}
			#query-input {
				width: 450px;
				position: relative;
				top: -3px;
			}
			#search-subject {
				width: 190px;
				padding: 2px 8px;
			}

			#filter-cat {			
				width: 400px;
				padding: 2px 8px;
			}

			#event-toggle-table {
				border-collapse: collapse;
				width: 100%;
				border: 1px solid #ddd;
				font-size: 18px;
			}

			#event-toggle-table th, #event-toggle-table td {
				text-align: left;
				padding: 12px;
			}

			#event-toggle-table tr {
				border-bottom: 1px solid #ddd;
			}

			#event-toggle-table tr.disabled {
				opacity: 0.5;
			}

			#event-toggle-table tr.header, #event-toggle-table tr:hover {
				background-color: #f1f1f1;
			}
			#tab-all {
				background: #fff;
				margin-top: 10px;
				padding: 10px;
				border: 1px solid #c3c4c7;
   				box-shadow: 0 1px 1px rgb(0 0 0 / 4%);
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
			array( 'jquery-ui-tooltip' ),
			'1.1.1',
			true
		);

		// Darktooltip js.
		wp_enqueue_script(
			'darktooltip', // Identifier.
			WSAL_BASE_URL . 'js/jquery.darktooltip.js', // Script location.
			array( 'jquery' ), // Depends on jQuery.
			'0.4.0', // Script version.
			true
		);

		?>
		<script type="text/javascript">
			function filterEventTable() {
				// Declare variables
				var input, filter, table, tr, td, i, txtValue;
				input = document.getElementById("query-input");
				filter = input.value.toUpperCase();
				table = document.getElementById("event-toggle-table");
				tr = table.getElementsByTagName("tr");

				var e = document.getElementById("search-subject");
				var strSearch = e.options[e.selectedIndex].value;

				// Loop through all table rows, and hide those who don't match the search query
				for (i = 0; i < tr.length; i++) {
					if ( strSearch == 'code' ) {
						td = tr[i].getElementsByTagName("td")[0];
					} else if ( strSearch == 'severity' ) {
						td = tr[i].getElementsByTagName("td")[1];
					} else if ( strSearch == 'desc' ) {
						td = tr[i].getElementsByTagName("td")[2];
					}
					if (td) {
						txtValue = td.textContent || td.innerText;
						if (txtValue.toUpperCase().indexOf(filter) > -1) {
							tr[i].style.display = "";
						} else {
							tr[i].style.display = "none";
						}
					}
				}
			}

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
				jQuery('#event-toggle-table thead>tr>th>:checkbox').change(function(){
					jQuery(this).parents('table:first').find('tbody>tr:visible>th>:checkbox').attr('checked', this.checked);
				});
				jQuery('#event-toggle-table tr checkbox').change(function(){
					var allchecked = jQuery(this).parents('tbody:first').find('th>:checkbox:not(:checked)').length === 0;
					jQuery(this).parents('table:first').find('thead>tr:visible>th:first>:checkbox:first').attr('checked', allchecked);
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

				// Add options to category select box.
				var categories = [];
				jQuery('[data-alert-cat]').each( function( index, value ) {
					var title = jQuery( this ).attr( 'data-alert-cat');
					var found = jQuery.inArray(title, categories);
					if (found >= 0) {
						// Nothing needed.
					} else { 
						categories.push( title );
					}
				});				
				jQuery( categories ).each( function( index, value ) {
					jQuery('#filter-cat').append('<option value="'+ value +'">'+ value +'</option>');
				});

				// Display a specific category based on browser hash.
				var hash = window.location.hash;
				jQuery('ul'+hash+':first').show();

				if (window.location.href.indexOf( '#cat-' ) > -1) {
					var hash = window.location.hash.toUpperCase();
					hash =  hash.replace('#CAT-', '');
					jQuery('[data-alert-cat]').each( function( index, value ) {
						var title = jQuery( this ).attr( 'data-alert-cat').toUpperCase();
						if ( title == hash  ) {
							jQuery( this  ).css( 'display', '' );
						} else {
							jQuery( this ).css( 'display', 'none' );
						}
					});
				}

				// Filter items based on category.
				jQuery('#filter-cat').on('change', function() {
					var filter = jQuery( "#filter-cat option:selected" ).text().toUpperCase();
					var val = jQuery("#filter-cat").val();
					if ( 'all' == val ) {
						jQuery( '#event-toggle-table tr' ).css( 'display', '' );
					} else {
						jQuery('[data-alert-cat]').each( function( index, value ) {
							var title = jQuery( this ).attr( 'data-alert-cat').toUpperCase();
							if ( title == filter ) {
								jQuery( this  ).css( 'display', '' );
							} else {
								jQuery( this ).css( 'display', 'none' );
							}
						});
					}
				});

				// Lovely tooltop.s
				jQuery( '#event-toggle-table tr' ).darkTooltip( {
					animation: 'fadeIn',
					gravity: 'north',
					size: 'large',
				} );
			});

			if (typeof toggle_modal !== 'undefined') {
				jQuery(document).on('closed', toggle_modal, function (event) {
					if (event.reason && event.reason === 'cancellation') {
						for ( var index = 0; index < alerts.length; index++ ) {
							jQuery( alerts[ index ] ).prop( 'checked', false );
						}
					}
				});
			}
		</script>
		<?php
	}

	/**
	 * Determine the log level setting by checking the list of enabled/disabled events.
	 *
	 * @param int[] $disabled_events Currently disabled events.
	 *
	 * @return string Log level setting value.
	 * @since 4.2.0
	 */
	private function get_log_level_based_on_events( $disabled_events ) {
		$events_to_cross_check = $this->_plugin->settings()->always_disabled_alerts;
		$events_diff           = array_diff( $disabled_events, $events_to_cross_check );
		$events_diff           = array_filter( $events_diff ); // Remove empty values.
		if ( empty( $events_diff ) ) {
			return 'geek';
		}

		$events_to_cross_check = array_merge( $events_to_cross_check, $this->_plugin->settings()->geek_alerts );
		$events_diff           = array_diff( $disabled_events, $events_to_cross_check );
		$events_diff           = array_filter( $events_diff ); // Remove empty values.
		if ( empty( $events_diff ) ) {
			return 'basic';
		}

		return 'custom';
	}
}
