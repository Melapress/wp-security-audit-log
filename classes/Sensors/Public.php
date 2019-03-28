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
	 * Old Product Stock Quantity.
	 *
	 * @var int
	 */
	protected $_old_stock = null;

	/**
	 * Old Product Stock Status.
	 *
	 * @var string
	 */
	protected $_old_stock_status = null;

	/**
	 * Listening to events using WP hooks.
	 */
	public function HookEvents() {
		if ( $this->plugin->load_wsal_on_frontend() ) {
			add_action( 'user_register', array( $this, 'event_user_register' ) );
			add_action( 'comment_post', array( $this, 'event_comment' ), 10, 3 );
			add_filter( 'template_redirect', array( $this, 'event_404' ) );

			// Check if WooCommerce plugin exists.
			if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
				add_action( 'woocommerce_new_order', array( $this, 'event_new_order' ), 10, 1 );
				add_filter( 'woocommerce_order_item_quantity', array( $this, 'set_old_stock' ), 10, 3 );
				add_action( 'woocommerce_product_set_stock', array( $this, 'product_stock_changed' ), 10, 1 );
				add_action( 'woocommerce_variation_set_stock', array( $this, 'product_stock_changed' ), 10, 1 );
			}
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
	 * Fires immediately after a comment is inserted into the database.
	 *
	 * @param int   $comment_id       - The comment ID.
	 * @param mixed $comment_approved - 1 if the comment is approved, 0 if not, 'spam' if spam.
	 * @param array $comment_data     - Comment data.
	 */
	public function event_comment( $comment_id, $comment_approved, $comment_data ) {
		if ( ! $comment_id ) {
			return;
		}
		// Check if the comment is response to another comment.
		if ( isset( $comment_data['comment_parent'] ) && $comment_data['comment_parent'] ) {
			$this->event_generic( $comment_id, 2092 );
			return;
		}

		$comment = get_comment( $comment_id );
		if ( $comment ) {
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
				} else {
					// Get user roles.
					$user_roles = $user_data->roles;
					if ( function_exists( 'is_super_admin' ) && is_super_admin() ) { // Check if superadmin.
						$user_roles[] = 'superadmin';
					}

					// Set the fields.
					$fields['Username']         = $user_data->user_login;
					$fields['CurrentUserRoles'] = $user_roles;
					$fields['CommentMsg']       = sprintf( 'Posted a comment in response to the post <strong>%s</strong>', $post->post_title );
					$this->plugin->alerts->Trigger( 2099, $fields );
				}
			}
		}
	}

	/**
	 * Trigger generic event.
	 *
	 * @since 3.4
	 *
	 * @param integer $comment_id - Comment ID.
	 * @param integer $alert_code - Event code.
	 */
	private function event_generic( $comment_id, $alert_code ) {
		$comment = get_comment( $comment_id );
		if ( $comment ) {
			$post         = get_post( $comment->comment_post_ID );
			$comment_link = get_permalink( $post->ID ) . '#comment-' . $comment_id;
			$fields       = array(
				'PostTitle'   => $post->post_title,
				'Author'      => $comment->comment_author,
				'Date'        => $comment->comment_date,
				'CommentLink' => '<a target="_blank" href="' . $comment_link . '">' . $comment->comment_date . '</a>',
			);

			if ( 'shop_order' !== $post->post_type ) {
				$this->plugin->alerts->Trigger( $alert_code, $fields );
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
	private function write_log( $attempts, $ip, $username = '', $url = null ) {
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
	 * Get editor link.
	 *
	 * @since 3.3.1
	 *
	 * @param WP_Post $post        - Product post object.
	 * @return array  $editor_link - Name and value link.
	 */
	private function get_editor_link( $post ) {
		// Meta value key.
		if ( 'shop_order' === $post->post_type ) {
			$name = 'EditorLinkOrder';
		} else {
			$name = 'EditorLinkProduct';
		}

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

	/**
	 * Formulate Order Title as done by WooCommerce.
	 *
	 * @since 3.3.1
	 *
	 * @param int|WC_Order $order - Order id or WC Order object.
	 * @return string
	 */
	private function get_order_title( $order ) {
		if ( ! $order ) {
			return false;
		}
		if ( is_int( $order ) ) {
			$order = new WC_Order( $order );
		}
		if ( ! $order instanceof WC_Order ) {
			return false;
		}

		if ( $order->get_billing_first_name() || $order->get_billing_last_name() ) {
			$buyer = trim( sprintf( '%1$s %2$s', $order->get_billing_first_name(), $order->get_billing_last_name() ) );
		} elseif ( $order->get_billing_company() ) {
			$buyer = trim( $order->get_billing_company() );
		} elseif ( $order->get_customer_id() ) {
			$user  = get_user_by( 'id', $order->get_customer_id() );
			$buyer = ucwords( $user->display_name );
		}
		return '#' . $order->get_order_number() . ' ' . $buyer;
	}

	/**
	 * New WooCommerce Order Event.
	 *
	 * @since 3.3.1
	 *
	 * @param integer $order_id – Order id.
	 */
	public function event_new_order( $order_id ) {
		if ( empty( $order_id ) ) {
			return;
		}

		// Get order object.
		$new_order = new WC_Order( $order_id );

		if ( $new_order && $new_order instanceof WC_Order ) {
			$order_post  = get_post( $order_id ); // Get order post object.
			$order_title = ( null !== $order_post && $order_post instanceof WP_Post ) ? $order_post->post_title : false;
			$editor_link = $this->get_editor_link( $order_post );

			$this->plugin->alerts->Trigger(
				9035, array(
					'OrderID'            => $order_id,
					'OrderTitle'         => $this->get_order_title( $new_order ),
					'OrderStatus'        => $new_order->get_status(),
					$editor_link['name'] => $editor_link['value'],
				)
			);
		}
	}

	/**
	 * Triggered before updating stock quantity on customer order.
	 *
	 * @since 3.3.1
	 *
	 * @param int           $order_quantity - Order quantity.
	 * @param WC_Order      $order          - Order object.
	 * @param WC_Order_Item $item           - Order item object.
	 * @return int - Order quantity.
	 */
	public function set_old_stock( $order_quantity, $order, $item ) {
		// Get product from order item.
		$product = $item->get_product();

		// Get product id.
		$product_id_with_stock = $product->get_stock_managed_by_id();

		// Get product with stock.
		$product_with_stock = wc_get_product( $product_id_with_stock );

		// Set stock attributes of the product.
		$this->_old_stock        = $product_with_stock->get_stock_quantity();
		$this->_old_stock_status = $product_with_stock->get_stock_status();

		// Return original stock quantity.
		return $order_quantity;
	}

	/**
	 * Triggered when stock of a product is changed.
	 *
	 * @since 3.3.1
	 *
	 * @param WC_Product $product - WooCommerce product object.
	 */
	public function product_stock_changed( $product ) {
		// Get product data.
		$product_status = false;
		if ( $product->is_type( 'variation' ) ) {
			$product_id     = $product->get_parent_id();
			$product_title  = $product->get_name(); // Get product title.
			$product_status = $product->get_status();
		} else {
			$product_id    = $product->get_id();
			$product_title = $product->get_title(); // Get product title.
		}

		// Return if current screen is edit post page.
		global $pagenow;
		if ( is_admin() && ( 'post.php' === $pagenow || defined( 'DOING_AJAX' ) ) ) {
			return;
		}

		// Get global $_POST array.
		$post_array = filter_input_array( INPUT_POST );

		// Special conditions for WooCommerce Bulk Stock Management.
		if ( 'edit.php' === $pagenow && isset( $post_array['page'] ) && 'woocommerce-bulk-stock-management' === $post_array['page'] ) {
			$old_acc_stock = isset( $post_array['current_stock_quantity'] ) ? $post_array['current_stock_quantity'] : false;
			$new_acc_stock = isset( $post_array['stock_quantity'] ) ? $post_array['stock_quantity'] : false;

			// Get old stock quantity.
			$old_stock = ! empty( $this->_old_stock ) ? $this->_old_stock : $old_acc_stock[ $product_id ];

			// Following cases handle the stock status.
			if ( '0' === $old_acc_stock[ $product_id ] && '0' !== $new_acc_stock[ $product_id ] ) {
				$old_stock_status = 'outofstock';
			} elseif ( '0' !== $old_acc_stock[ $product_id ] && '0' === $new_acc_stock[ $product_id ] ) {
				$old_stock_status = 'instock';
			} elseif ( '0' === $old_acc_stock[ $product_id ] && '0' === $new_acc_stock[ $product_id ] ) {
				$old_stock_status = 'outofstock';
			} elseif ( '0' !== $old_acc_stock[ $product_id ] && '0' !== $new_acc_stock[ $product_id ] ) {
				$old_stock_status = 'instock';
			} else {
				$old_stock_status = '';
			}
		} else {
			$old_stock        = $this->_old_stock; // Get old stock quantity.
			$old_stock_status = $this->_old_stock_status; // Get old stock status.
		}

		$new_stock        = $product->get_stock_quantity(); // Get new stock quantity.
		$new_stock_status = $product->get_stock_status(); // Get new stock status.

		// Set post object.
		$post = get_post( $product_id );

		// Set username.
		$username = '';
		if ( ! is_user_logged_in() ) {
			$username = 'Website Visitor';
		} else {
			$username = wp_get_current_user()->user_login;
		}

		// If stock status has changed then trigger the alert.
		if ( ( $old_stock_status && $new_stock_status ) && ( $old_stock_status !== $new_stock_status ) ) {
			$editor_link = $this->get_editor_link( $post );
			$this->plugin->alerts->Trigger(
				9018, array(
					'ProductTitle'       => $product_title,
					'ProductStatus'      => ( ! $product_status ) ? $post->post_status : $product_status,
					'OldStatus'          => $this->get_stock_status( $old_stock_status ),
					'NewStatus'          => $this->get_stock_status( $new_stock_status ),
					'Username'           => $username,
					$editor_link['name'] => $editor_link['value'],
				)
			);
		}

		$wc_all_stock_changes = $this->plugin->GetGlobalOption( 'wc-all-stock-changes', 'on' );

		// If stock has changed then trigger the alert.
		if ( ( $old_stock !== $new_stock ) && ( 'on' === $wc_all_stock_changes ) ) {
			$editor_link = $this->get_editor_link( $post );
			$this->plugin->alerts->Trigger(
				9019, array(
					'ProductTitle'       => $product_title,
					'ProductStatus'      => ( ! $product_status ) ? $post->post_status : $product_status,
					'OldValue'           => ( ! empty( $old_stock ) ? $old_stock : 0 ),
					'NewValue'           => $new_stock,
					'Username'           => $username,
					$editor_link['name'] => $editor_link['value'],
				)
			);
		}
	}

	/**
	 * Get Stock Status Name.
	 *
	 * @since 3.3.1
	 *
	 * @param string $slug - Stock slug.
	 * @return string
	 */
	private function get_stock_status( $slug ) {
		if ( 'instock' === $slug ) {
			return __( 'In stock', 'wp-security-audit-log' );
		} elseif ( 'outofstock' === $slug ) {
			return __( 'Out of stock', 'wp-security-audit-log' );
		} elseif ( 'onbackorder' === $slug ) {
			return __( 'On backorder', 'wp-security-audit-log' );
		}
	}
}
