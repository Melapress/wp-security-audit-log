<?php
/**
 * Sensor: Public Activity
 *
 * Public/Visitor activity sensor class file.
 *
 * @since 3.3
 * @package Wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * System Activity sensor.
 *
 * @package Wsal
 */
class WSAL_Sensors_Public extends WSAL_AbstractSensor {

	/**
	 * 404 Visitor Transient.
	 *
	 * WordPress will prefix the name with "_transient_"
	 * or "_transient_timeout_" in the options table.
	 */
	const TRANSIENT_VISITOR_404 = 'wsal-visitor-404-attempts';

	/**
	 * Visitor Events.
	 *
	 * @var boolean
	 */
	protected $visitor_events;

	/**
	 * Listening to events using WP hooks.
	 */
	public function HookEvents() {
		// Set if visitor events is enabled/disabled.
		$disabled_visitor_events = $this->plugin->GetGlobalOption( 'disable-visitor-events', 'no' );

		// Viewing post event.
		add_action( 'wp_head', array( $this, 'viewing_post' ), 10 );

		// If user is visitor & visitor events are not disabled then hook the following events.
		if ( ! is_user_logged_in() && 'no' === $disabled_visitor_events ) {
			add_action( 'user_register', array( $this, 'event_user_register' ) );
			add_action( 'comment_post', array( $this, 'event_comment' ), 10, 2 );
			add_filter( 'template_redirect', array( $this, 'event_404' ) );
		} elseif ( is_user_logged_in() ) {
			add_action( 'user_register', array( $this, 'event_user_register' ) );
			add_action( 'comment_post', array( $this, 'event_comment' ), 10, 2 );
			add_filter( 'template_redirect', array( $this, 'event_404' ) );
		}

		// Check if WooCommerce plugin exists.
		if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
			add_action( 'wp_head', array( $this, 'viewing_product' ), 10 );
		}
	}

	/**
	 * Triggered when a user is registered.
	 *
	 * @param int $user_id - User ID of the registered user.
	 */
	public function event_user_register( $user_id ) {
		$user         = get_userdata( $user_id );
		$ismu         = function_exists( 'is_multisite' ) && is_multisite();
		$event        = $ismu ? 4012 : ( is_user_logged_in() ? 4001 : 4000 );
		$current_user = wp_get_current_user();
		$this->plugin->alerts->Trigger(
			$event, array(
				'NewUserID'   => $user_id,
				'UserChanger' => ! empty( $current_user ) ? $current_user->user_login : '',
				'NewUserData' => (object) array(
					'Username'  => $user->user_login,
					'FirstName' => $user->user_firstname,
					'LastName'  => $user->user_lastname,
					'Email'     => $user->user_email,
					'Roles'     => is_array( $user->roles ) ? implode( ', ', $user->roles ) : $user->roles,
				),
			), true
		);
	}

	/**
	 * Post View Event.
	 *
	 * Alerts for Viewing of Posts and Custom Post Types.
	 */
	public function viewing_post() {
		// Retrieve the current post object.
		$post = get_queried_object();
		if ( is_user_logged_in() && ! is_admin() ) {
			if ( $this->check_other_sensors( $post ) ) {
				return $post->post_title;
			}

			// Filter $_SERVER array for security.
			$server_array = filter_input_array( INPUT_SERVER );

			$current_path = isset( $server_array['REQUEST_URI'] ) ? $server_array['REQUEST_URI'] : false;
			if ( ! empty( $server_array['HTTP_REFERER'] )
				&& ! empty( $current_path )
				&& strpos( $server_array['HTTP_REFERER'], $current_path ) !== false ) {
				// Ignore this if we were on the same page so we avoid double audit entries.
				return;
			}

			if ( ! empty( $post->post_title ) ) {
				$this->plugin->alerts->Trigger(
					2101, array(
						'PostID'         => $post->ID,
						'PostType'       => $post->post_type,
						'PostTitle'      => $post->post_title,
						'PostStatus'     => $post->post_status,
						'PostDate'       => $post->post_date,
						'PostUrl'        => get_permalink( $post->ID ),
						'EditorLinkPost' => get_edit_post_link( $post->ID ),
					)
				);
			}
		}
	}

	/**
	 * Ignore post from BBPress, WooCommerce Plugin
	 * Triggered on the Sensors
	 *
	 * @param WP_Post $post - The post.
	 */
	private function check_other_sensors( $post ) {
		if ( empty( $post ) || ! isset( $post->post_type ) ) {
			return false;
		}
		switch ( $post->post_type ) {
			case 'forum':
			case 'topic':
			case 'reply':
			case 'product':
				return true;
			default:
				return false;
		}
	}

	/**
	 * Fires immediately after a comment is inserted into the database.
	 *
	 * @param int   $comment_id       – The comment ID.
	 * @param mixed $comment_approved – 1 if the comment is approved, 0 if not, 'spam' if spam.
	 */
	public function event_comment( $comment_id, $comment_approved = null ) {
		// @codingStandardsIgnoreStart
		$post_comment = isset( $_POST['comment'] ) ? sanitize_text_field( wp_unslash( $_POST['comment'] ) ) : false;
		// @codingStandardsIgnoreEnd

		if ( $post_comment ) {
			$comment = get_comment( $comment_id );
			if ( ! empty( $comment ) ) {
				if ( 'spam' !== $comment->comment_approved ) {
					$post         = get_post( $comment->comment_post_ID );
					$comment_link = get_permalink( $post->ID ) . '#comment-' . $comment_id;
					$fields       = array(
						'Date'        => $comment->comment_date,
						'CommentLink' => '<a target="_blank" href="' . $comment_link . '">' . $comment->comment_date . '</a>',
					);

					// Get user data.
					$user_data = get_user_by( 'email', $comment->comment_author_email );

					if ( ! $user_data ) {
						// Set the fields.
						/* Translators: 1: Post Title, 2: Comment Author */
						$fields['CommentMsg'] = sprintf( esc_html__( 'A comment was posted in response to the post %1$s. The comment was posted by %2$s', 'wp-security-audit-log' ), '<strong>' . $post->post_title . '</strong>', '<strong>' . $this->check_author( $comment ) . '</strong>' );
						$fields['Username']   = 'Website Visitor';
						$this->plugin->alerts->Trigger( 2126, $fields );
					}
				}
			}
		}
	}

	/**
	 * Shows the username if the comment is owned by a user
	 * and the email if the comment was posted by a non WordPress user
	 *
	 * @param WP_Comment $comment – WP Comment object.
	 * @return string – Author username or email.
	 */
	private function check_author( $comment ) {
		if ( username_exists( $comment->comment_author ) ) {
			return $comment->comment_author;
		} else {
			return $comment->comment_author_email;
		}
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
		$ip                = $this->plugin->settings->GetMainClientIP();

		if ( ! is_user_logged_in() ) {
			$username = 'Website Visitor';
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

		if ( 'Website Visitor' === $username ) {
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
	 * Method: Return true if URL is excluded otherwise false.
	 *
	 * @param string $url - 404 URL.
	 * @return boolean
	 */
	public function is_excluded_url( $url ) {
		if ( empty( $url ) ) {
			return false;
		}

		if ( in_array( $url, $this->plugin->settings->get_excluded_urls() ) ) {
			return true;
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
		return $this->plugin->settings->GetVisitor404LogLimit();
	}

	/**
	 * Write Log.
	 *
	 * Write a new line on 404 log file.
	 * Folder: /uploads/wp-security-audit-log/404s/
	 *
	 * @param int    $attempts - Number of attempt.
	 * @param string $ip       - IP address.
	 * @param string $username - Username.
	 * @param string $url      - 404 URL.
	 */
	private function write_log( $attempts, $ip, $username = '', $url ) {
		$name_file = null;

		if ( 'on' === $this->plugin->GetGlobalOption( 'log-visitor-404', 'off' ) ) {
			// Get option to log referrer.
			$log_referrer = $this->plugin->GetGlobalOption( 'log-visitor-404-referrer' );

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

			$username         = '';
			$upload_dir       = wp_upload_dir();
			$uploads_url      = trailingslashit( $upload_dir['baseurl'] ) . 'wp-security-audit-log/404s/';
			$uploads_dir_path = trailingslashit( $upload_dir['basedir'] ) . 'wp-security-audit-log/404s/';

			// Check directory.
			if ( $this->CheckDirectory( $uploads_dir_path ) ) {
				$filename  = '6023_' . date( 'Ymd' ) . '.log';
				$fp        = $uploads_dir_path . $filename;
				$name_file = $uploads_url . $filename;
				if ( ! $file = fopen( $fp, 'a' ) ) {
					$i           = 1;
					$file_opened = false;
					do {
						$fp2 = substr( $fp, 0, -4 ) . '_' . $i . '.log';
						if ( ! file_exists( $fp2 ) ) {
							if ( $file = fopen( $fp2, 'a' ) ) {
								$file_opened = true;
								$name_file   = $uploads_url . substr( $name_file, 0, -4 ) . '_' . $i . '.log';
							}
						} else {
							$latest_filename = $this->GetLastModified( $uploads_dir_path, $filename );
							$fp_last         = $uploads_dir_path . $latest_filename;
							if ( $file = fopen( $fp_last, 'a' ) ) {
								$file_opened = true;
								$name_file   = $uploads_url . $latest_filename;
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

	/**
	 * Viewing Product Event.
	 *
	 * Alerts for viewing of product post type for WooCommerce.
	 */
	public function viewing_product() {
		// Retrieve the current post object.
		$product = get_queried_object();

		// Check product post type.
		if ( ! empty( $product ) && $product instanceof WP_Post && 'product' !== $product->post_type ) {
			return $product;
		}

		if ( is_user_logged_in() && ! is_admin() ) {
			// Filter $_SERVER array for security.
			$server_array = filter_input_array( INPUT_SERVER );

			$current_path = isset( $server_array['REQUEST_URI'] ) ? $server_array['REQUEST_URI'] : false;
			if ( ! empty( $server_array['HTTP_REFERER'] )
				&& ! empty( $current_path )
				&& strpos( $server_array['HTTP_REFERER'], $current_path ) !== false ) {
				// Ignore this if we were on the same page so we avoid double audit entries.
				return;
			}
			if ( ! empty( $product->post_title ) ) {
				$editor_link = $this->get_product_editor_link( $product );
				$this->plugin->alerts->Trigger(
					9073, array(
						'PostID'             => $product->ID,
						'PostType'           => $product->post_type,
						'ProductStatus'      => $product->post_status,
						'ProductTitle'       => $product->post_title,
						'ProductUrl'         => get_permalink( $product->ID ),
						$editor_link['name'] => $editor_link['value'],
					)
				);
			}
		}
	}

	/**
	 * Get editor link.
	 *
	 * @param WP_Post $post        - Product post object.
	 * @return array  $editor_link - Name and value link.
	 */
	private function get_product_editor_link( $post ) {
		// Meta value key.
		$name = 'EditorLinkProduct';

		// Get editor post link URL.
		$value = get_edit_post_link( $post->ID );

		// If the URL is not empty then set values.
		if ( ! empty( $value ) ) {
			$editor_link = array(
				'name'  => $name, // Meta key.
				'value' => $value, // Meta value.
			);
		} else {
			// Get post object.
			$post = get_post( $post->ID );

			// Set URL action.
			if ( 'revision' === $post->post_type ) {
				$action = '';
			} else {
				$action = '&action=edit';
			}

			// Get and check post type object.
			$post_type_object = get_post_type_object( $post->post_type );
			if ( ! $post_type_object ) {
				return;
			}

			// Set editor link manually.
			if ( $post_type_object->_edit_link ) {
				$link = admin_url( sprintf( $post_type_object->_edit_link . $action, $post->ID ) );
			} else {
				$link = '';
			}

			$editor_link = array(
				'name'  => $name, // Meta key.
				'value' => $link, // Meta value.
			);
		}

		return $editor_link;
	}
}
