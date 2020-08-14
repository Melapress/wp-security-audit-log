<?php
/**
 * Frontend system sensor.
 *
 * @package wsal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Frontend system sensor to detect 404 requests.
 */
class WSAL_Sensors_FrontendSystem extends WSAL_AbstractSensor {

	/**
	 * 404 Visitor Transient.
	 *
	 * WordPress will prefix the name with "_transient_"
	 * or "_transient_timeout_" in the options table.
	 */
	const TRANSIENT_VISITOR_404 = 'wsal-visitor-404-attempts';

	/**
	 * Listening to events using WP hooks.
	 */
	public function HookEvents() {
		add_filter( 'template_redirect', array( $this, 'event_404' ) );
	}

	/**
	 * Event 404 Not found.
	 */
	public function event_404() {
		$attempts = 1;

		global $wp_query;
		if ( ! $wp_query->is_404 ) {
			return;
		}

		$msg               = 'times';
		list( $y, $m, $d ) = explode( '-', date( 'Y-m-d' ) );
		$site_id           = function_exists( 'get_current_blog_id' ) ? get_current_blog_id() : 0;
		$ip                = $this->plugin->settings()->GetMainClientIP();

		if ( ! is_user_logged_in() ) {
			$username = 'Unregistered user';
		} else {
			$username = wp_get_current_user()->user_login;
		}

		// Request URL.
		$request_uri = filter_input( INPUT_SERVER, 'REQUEST_URI', FILTER_SANITIZE_STRING );
		if ( ! empty( $request_uri ) ) {
			$url_404 = home_url() . $request_uri;
		} elseif ( isset( $_SERVER['REQUEST_URI'] ) && ! empty( $_SERVER['REQUEST_URI'] ) ) {
			$url_404 = home_url() . sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		}

		// Remove forward slash from the URL.
		$url_404 = untrailingslashit( $url_404 );

		// Check for excluded 404 URls.
		if ( $this->is_excluded_url( $url_404 ) ) {
			return;
		}

		if ( 'Website Visitor' === $username || 'Unregistered user' === $username ) {
			// Check if the alert is disabled from the "Enable/Disable Alerts" section.
			if ( ! $this->plugin->alerts->IsEnabled( 6023 ) ) {
				return;
			}

			if ( $this->is_past_visitor_404_limit( $site_id, $username, $ip ) ) {
				return;
			}

			$obj_occurrence = new WSAL_Models_Occurrence();
			$occurrence     = $obj_occurrence->CheckAlert404(
				array(
					$ip,
					$username,
					6023,
					$site_id,
					mktime( 0, 0, 0, $m, $d, $y ),
					mktime( 0, 0, 0, $m, $d + 1, $y ) - 1,
				)
			);

			$occurrence = count( $occurrence ) ? $occurrence[0] : null;
			if ( ! empty( $occurrence ) ) {
				// Update existing record.
				$this->increment_visitor_404( $site_id, $username, $ip );
				$new = ( (int) $occurrence->GetMetaValue( 'Attempts', 0 ) ) + 1;

				if ( $new > $this->get_visitor_404_log_limit() ) {
					$new  = 'more than ' . $this->get_visitor_404_log_limit();
					$msg .= ' This could possible be a scan, therefore keep an eye on the activity from this IP Address';
				}

				$link_file = $this->write_log( $new, $ip, $username, $url_404 );

				$occurrence->UpdateMetaValue( 'Attempts', $new );
				$occurrence->UpdateMetaValue( 'Username', $username );
				$occurrence->UpdateMetaValue( 'Msg', $msg );
				$occurrence->UpdateMetaValue( 'URL', $url_404 );
				if ( ! empty( $link_file ) ) {
					$occurrence->UpdateMetaValue( 'LinkFile', $link_file );
				}
				$occurrence->created_on = null;
				$occurrence->Save();
			} else {
				$link_file = $this->write_log( 1, $ip, $username, $url_404 );
				// Create a new record.
				$fields = array(
					'Attempts' => 1,
					'Username' => $username,
					'Msg'      => $msg,
					'URL'      => $url_404,
				);
				if ( ! empty( $link_file ) ) {
					$fields['LinkFile'] = $link_file;
				}
				$this->plugin->alerts->Trigger( 6023, $fields );
			}
		}
	}

	/**
	 * Check visitor 404 limit.
	 *
	 * @param integer $site_id - Blog ID.
	 * @param string  $username - Username.
	 * @param string  $ip - IP address.
	 * @return boolean passed limit true|false
	 */
	protected function is_past_visitor_404_limit( $site_id, $username, $ip ) {
		$get_fn = $this->plugin->IsMultisite() ? 'get_site_transient' : 'get_transient';
		$data   = $get_fn( self::TRANSIENT_VISITOR_404 );
		return ( false !== $data ) && isset( $data[ $site_id . ':' . $username . ':' . $ip ] ) && ( $data[ $site_id . ':' . $username . ':' . $ip ] > $this->get_visitor_404_log_limit() );
	}

	/**
	 * Increment visitor 404 limit.
	 *
	 * @param integer $site_id - Blog ID.
	 * @param string  $username - Username.
	 * @param string  $ip - IP address.
	 */
	protected function increment_visitor_404( $site_id, $username, $ip ) {
		$get_fn = $this->plugin->IsMultisite() ? 'get_site_transient' : 'get_transient';
		$set_fn = $this->plugin->IsMultisite() ? 'set_site_transient' : 'set_transient';
		$data   = $get_fn( self::TRANSIENT_VISITOR_404 );

		if ( ! $data ) {
			$data = array();
		}

		if ( ! isset( $data[ $site_id . ':' . $username . ':' . $ip ] ) ) {
			$data[ $site_id . ':' . $username . ':' . $ip ] = 1;
		}
		$data[ $site_id . ':' . $username . ':' . $ip ]++;
		$set_fn( self::TRANSIENT_VISITOR_404, $data, DAY_IN_SECONDS );
	}

	/**
	 * 404 visitor limit count.
	 *
	 * @return integer limit
	 */
	protected function get_visitor_404_log_limit() {
		return $this->plugin->settings()->GetVisitor404LogLimit();
	}

	/**
	 * Method: Return true if URL is excluded otherwise false.
	 *
	 * @param string $url - 404 URL.
	 * @return boolean
	 */
	public function is_excluded_url( $url ) {
		if ( empty( $url ) ) {
			return false;
		}

		if ( in_array( $url, $this->plugin->settings()->get_excluded_urls() ) ) {
			return true;
		}
	}

	/**
	 * Write Log.
	 *
	 * Write a new line on 404 log file.
	 * Folder: {plugin working folder}/404s/
	 *
	 * @param int $attempts - Number of attempt.
	 * @param string $ip - IP address.
	 * @param string $username - Username.
	 * @param string $url - 404 URL.
	 *
	 * @return string|null
	 */
	private function write_log( $attempts, $ip, $username = '', $url = null ) {
		$name_file = null;

		if ( $this->plugin->GetGlobalBooleanSetting( 'log-visitor-404', false ) ) {
			// Get option to log referrer.
			$log_referrer = $this->plugin->GetGlobalBooleanSetting( 'log-visitor-404-referrer' );

			// Check localhost.
			if ( '127.0.0.1' == $ip || '::1' == $ip ) {
				$ip = 'localhost';
			}

			if ( 'on' === $log_referrer ) {
				// Get the referer.
				$referrer = filter_input( INPUT_SERVER, 'HTTP_REFERER', FILTER_SANITIZE_STRING );
				if ( empty( $referrer ) && isset( $_SERVER['HTTP_REFERER'] ) && ! empty( $_SERVER['HTTP_REFERER'] ) ) {
					$referrer = sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
				}

				// Data to write.
				$data = '';

				// Append IP if it exists.
				$data = ( $ip ) ? $ip . ',' : '';

				// Create/Append to the log file.
				$data = $data . 'Request URL ' . $url . ',Referer ' . $referrer . ',';
			} else {
				// Data to write.
				$data = '';

				// Append IP if it exists.
				$data = ( $ip ) ? $ip . ',' : '';

				// Create/Append to the log file.
				$data = $data . 'Request URL ' . $url . ',';
			}

			// don't store username in a public viewable logfile.
			$username = '';

			// get the custom logging path from settings.
			$custom_logging_path = $this->plugin->settings()->get_working_dir_path( '404s' );
			if ( ! is_wp_error( $custom_logging_path ) ) {
				if ( ! file_exists( $custom_logging_path . 'index.php' ) ) {
					// make an empty index.php in the directory.
					@file_put_contents( $custom_logging_path . 'index.php', '<?php // Silence is golden' );
				}

				$filename  = '6023_' . date( 'Ymd' ) . '.log';
				$fp        = $custom_logging_path . $filename;
				$custom_logging_url  = $this->plugin->settings()->get_working_dir_url( '404s' );
				$name_file = $custom_logging_url . $filename;
				if ( ! $file = fopen( $fp, 'a' ) ) {
					$i           = 1;
					$file_opened = false;
					do {
						$fp2 = substr( $fp, 0, -4 ) . '_' . $i . '.log';
						if ( ! file_exists( $fp2 ) ) {
							if ( $file = fopen( $fp2, 'a' ) ) {
								$file_opened = true;
								$name_file   = $custom_logging_url . substr( $name_file, 0, -4 ) . '_' . $i . '.log';
							}
						} else {
							$latest_filename = $this->GetLastModified( $custom_logging_path, $filename );
							$fp_last         = $custom_logging_path . $latest_filename;
							if ( $file = fopen( $fp_last, 'a' ) ) {
								$file_opened = true;
								$name_file   = $custom_logging_url . $latest_filename;
							}
						}
						$i++;
					} while ( ! $file_opened );
				}
				fwrite( $file, sprintf( "%s\n", $data ) );
				fclose( $file );
			}
		}
		return $name_file;
	}
}
