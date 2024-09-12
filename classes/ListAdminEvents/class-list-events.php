<?php
/**
 * Responsible for the Showing the list of the events collected.
 *
 * @package    WSAL
 * @subpackage helpers
 *
 * @since      4.6.0
 *
 * @copyright  2024 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 *
 * @see       https://wordpress.org/plugins/wp-2fa/
 */

declare(strict_types=1);

namespace WSAL\ListAdminEvents;

use WSAL\Helpers\WP_Helper;
use WSAL\Helpers\User_Utils;
use WSAL\Controllers\Constants;
use WSAL\Controllers\Connection;
use WSAL\Helpers\Settings_Helper;
use WSAL\Entities\Metadata_Entity;
use WSAL\Controllers\Alert_Manager;
use WSAL\Entities\Occurrences_Entity;
use WSAL\Helpers\DateTime_Formatter_Helper;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/template.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table-compat.php';
	require_once ABSPATH . 'wp-admin/includes/list-table.php';
}

/*
 * Base list table class
 */
if ( ! class_exists( '\WSAL\ListAdminEvents\List_Events' ) ) {
	/**
	 * Responsible for rendering base table for manipulation.
	 *
	 * @since 4.6.0
	 */
	class List_Events extends \WP_List_Table {
		public const SCREEN_OPTIONS_SLUG = 'wsal_list_view';

		/**
		 * Current screen.
		 *
		 * @var \WP_Screen
		 *
		 * @since 4.6.0
		 */
		protected $wp_screen;

		/**
		 * The table to show.
		 *
		 * @var \WSAL\Entities\Abstract_Entity
		 *
		 * @since 4.6.0
		 */
		protected $table;

		/**
		 * Name of the table to show.
		 *
		 * @var string
		 *
		 * @since 4.6.0
		 */
		protected $table_name;

		/**
		 * How many.
		 *
		 * @var int
		 *
		 * @since 4.6.0
		 */
		protected $count;

		/**
		 * How many records to show per page - that is a fall back option, it will try to extract that first from the stored user data, then from the settings and from here as a last resort.
		 *
		 * @var int
		 *
		 * @since 4.6.0
		 */
		protected $records_per_page = 10;

		/**
		 * Holds the array with all of the column names and their representation in the table header.
		 *
		 * @var array
		 *
		 * @since 4.6.0
		 */
		private static $columns = array();

		/**
		 * Events Query Arguments.
		 *
		 * @since 4.6.0
		 * @since 5.0.0 Transformed to array
		 *
		 * @var array
		 */
		private static $query_args;

		/**
		 * Holds the DB connection (if it is external), null otherwise.
		 *
		 * @var \wpdb
		 *
		 * @since 4.6.0
		 */
		private static $wsal_db = null;

		/**
		 * Holds the current query arguments.
		 *
		 * @var array
		 *
		 * @since 4.6.1
		 */
		private static $query_occ = array();

		/**
		 * Holds the current query order.
		 *
		 * @var array
		 *
		 * @since 4.6.1
		 */
		private static $query_order = array();

		/**
		 * Default class constructor.
		 *
		 * @param stdClass $query_args Events query arguments.
		 *
		 * @since 4.6.0
		 * @since 5.0.0 - Removed $plugin parameter - there is no longer main class dependency.
		 */
		public function __construct( $query_args ) {
			self::$query_args = $query_args;

			parent::__construct(
				array(
					'singular' => 'log',
					'plural'   => 'logs',
					'ajax'     => true,
					'screen'   => $this->get_wp_screen(),
				)
			);

			self::$columns = self::manage_columns( array() );

			self::$wsal_db = null;

			$this->table_name = Occurrences_Entity::get_table_name( self::$wsal_db );
			$this->table      = Occurrences_Entity::class;

			/* @free:start */
			add_action(
				'wsal_search_filters_list',
				function ( $which ) {
					if ( 'top' === $which ) {
						echo '<div style="clear:both; float:right">';
						$this->search_box(
							__( 'Search', 'wp-security-audit-log' ),
							strtolower( $this->table_name ) . '-find'
						);
						echo '</div>';
						$try_free_search = Settings_Helper::get_boolean_option_value( 'free-search-try' );

						if ( $try_free_search ) {
							global $wpdb;
							echo '<style>
							#darktooltip-' . $wpdb->prefix . 'wsal_occurrences-find-search-input {
								padding: 15px !important;
								font-size: 1.2em !important;
							}
							</style>';
							echo '<script>
								document.addEventListener("readystatechange", event => { 

									if (event.target.readyState === "complete") {
										jQuery(".wsal_search_input").darkTooltip({
														animation: "flipIn",
														gravity: "east",
														size: "medium",
														trigger: "show",
														autoClose: true,
														autoCloseDuration: 5000,
														autoPosition: true,
													});
									}
								});
							</script>';
						}
					}
				},
				999
			);
			/* @free:end */
		}

		/**
		 * Returns the current wsal_db connection.
		 *
		 * @return \wpdb
		 *
		 * @since 4.6.1
		 */
		public static function get_wsal_db() {
			return self::$wsal_db;
		}

		/**
		 * Displays the search box.
		 *
		 * @since 4.6.0
		 *
		 * @param string $text     The 'submit' button label.
		 * @param string $input_id ID attribute value for the search input field.
		 */
		public function search_box( $text, $input_id ) {
			if ( empty( $_REQUEST['s'] ) && ! $this->has_items() ) {
				return;
			}

			$input_id = $input_id . '-search-input';

			if ( ! empty( $_REQUEST['orderby'] ) ) {
				echo '<input type="hidden" name="orderby" value="' . esc_attr( \sanitize_text_field( \wp_unslash( $_REQUEST['orderby'] ) ) ) . '" />';
			}
			if ( ! empty( $_REQUEST['order'] ) ) {
				echo '<input type="hidden" name="order" value="' . esc_attr( \sanitize_text_field( \wp_unslash( $_REQUEST['order'] ) ) ) . '" />';
			}
			if ( ! empty( $_REQUEST['post_mime_type'] ) ) {
				echo '<input type="hidden" name="post_mime_type" value="' . esc_attr( \sanitize_text_field( \wp_unslash( $_REQUEST['post_mime_type'] ) ) ) . '" />';
			}
			if ( ! empty( $_REQUEST['detached'] ) ) {
				echo '<input type="hidden" name="detached" value="' . esc_attr( \sanitize_text_field( \wp_unslash( $_REQUEST['detached'] ) ) ) . '" />';
			}
			?>
	<p class="search-box" style="position:relative">
		<label class="screen-reader-text" for="<?php echo esc_attr( $input_id ); ?>"><?php echo $text; ?>:</label>
			<?php
			$try_free_search = false;
			// phpcs:ignore
			/* @free:start */
			$try_free_search = Settings_Helper::get_boolean_option_value( 'free-search-try' );
			// phpcs:ignore
			/* @free:end */

			if ( $try_free_search ) {
				?>
					<span id="wsal_try_search"><?php echo esc_attr( ' Try the new search functionality' ); ?><span><input type="search" id="<?php echo esc_attr( $input_id ); ?>" name="s" value="<?php _admin_search_query(); ?>" /><span><span>
					<?php
			} else {
				?>
					<input type="search" id="<?php echo esc_attr( $input_id ); ?>" class="wsal_search_input" name="s" value="<?php _admin_search_query(); ?>" />
			<?php } ?>
			<?php submit_button( $text, '', '', false, array( 'id' => 'search-submit' ) ); ?>
	</p>
			<?php
		}

		/**
		 * Adds columns to the screen options screed.
		 *
		 * @param array $columns - Array of column names.
		 *
		 * @since 4.6.0
		 */
		public static function manage_columns( $columns ): array {
			$admin_fields = array(
				'cb'         => '<input type="checkbox" />', // to display the checkbox.
				'type'       => __( 'ID', 'wp-security-audit-log' ),
				'code'       => __( 'Severity', 'wp-security-audit-log' ),
				'crtd'       => __( 'Date', 'wp-security-audit-log' ),
				'user'       => __( 'User', 'wp-security-audit-log' ),
				'scip'       => __( 'IP', 'wp-security-audit-log' ),
				'object'     => __( 'Object', 'wp-security-audit-log' ),
				'event_type' => __( 'Event Type', 'wp-security-audit-log' ),
			);

			// If multisite then add "Site" column to the view.
			if ( WP_Helper::is_multisite() && WP_Helper::is_main_blog() && ! WP_Helper::is_specific_view() ) {
				$admin_fields['site'] = __( 'Site', 'wp-security-audit-log' );
			}

			if ( ! isset( $admin_fields['site'] ) ) {
				$add_site = \apply_filters( 'wsal_add_site_filter', false );

				if ( $add_site ) {
					$admin_fields['site'] = __( 'Site', 'wp-security-audit-log' );
				}
			}

			$admin_fields['mesg'] = __( 'Message', 'wp-security-audit-log' );
			$admin_fields['data'] = '';

			$screen_options = $admin_fields;

			$table_columns = array();

			return \array_merge( $table_columns, $screen_options, $columns );
		}

		/**
		 * Returns the table name.
		 *
		 * @since 4.6.0
		 */
		public function get_table_name(): string {
			return $this->table_name;
		}

		/**
		 * Returns the the wp_screen property.
		 *
		 * @since 4.6.0
		 */
		private function get_wp_screen() {
			if ( empty( $this->wp_screen ) ) {
				$this->wp_screen = get_current_screen();
			}

			return $this->wp_screen;
		}

		/**
		 * Prepares the list of items for displaying.
		 *
		 * Query, filter data, handle sorting, and pagination, and any other data-manipulation required prior to rendering
		 *
		 * @since 4.6.0
		 */
		public function prepare_items() {
			$columns               = $this->get_columns();
			$hidden                = array();
			$sortable              = $this->get_sortable_columns();
			$this->_column_headers = array( $columns, $hidden, $sortable );

			$this->handle_table_actions();

			$this->fetch_table_data();

			$hidden = get_user_option( 'manage' . $this->get_wp_screen()->id . 'columnshidden', false );
			if ( ! $hidden ) {
				$hidden = array();
			}

			$this->_column_headers = array( self::$columns, $hidden, $sortable );
			// phpcs:ignore
			// usort( $items, [ &$this, 'usort_reorder' ] ); // phpcs:ignore

			// Set the pagination.
			$this->set_pagination_args(
				array(
					'total_items' => $this->count,
					'per_page'    => $this->get_screen_option_per_page(),
					'total_pages' => ceil( $this->count / $this->get_screen_option_per_page() ),
				)
			);
		}

		/**
		 * Returns the currently hidden column headers for the current user
		 *
		 * @return array
		 *
		 * @since 4.6.1
		 */
		public static function get_hidden_columns() {
			return array_filter(
				(array) get_user_option( 'managetoplevel_page_wsal-auditlogcolumnshidden', false )
			);
		}

		/**
		 * Get a list of columns. The format is:
		 * 'internal-name' => 'Title'.
		 *
		 * @since 4.6.0
		 *
		 * @return array
		 */
		public function get_columns() {
			return self::$columns;
		}

		/**
		 * Get a list of sortable columns. The format is:
		 * 'internal-name' => 'orderby'
		 * or
		 * 'internal-name' => array( 'orderby', true ).
		 *
		 * The second format will make the initial sorting order be descending
		 *
		 * @since 4.6.0
		 *
		 * @return array
		 */
		protected function get_sortable_columns() {
			$first6_columns   = array_keys( self::get_column_names() );
			$sortable_columns = array();

			unset( $first6_columns[0], $first6_columns[9] ); // id column.
			// data column.

			/*
			 * Actual sorting still needs to be done by prepare_items.
			 * specify which columns should have the sort icon.
			 *
			 * The second bool param sets the colum sort order - true ASC, false - DESC or unsorted.
			 */
			foreach ( $first6_columns as $value ) {
				$sortable_columns[ $value ] = array( $value, false );
			}

			return $sortable_columns;
		}

		/**
		 * Text displayed when no user data is available.
		 *
		 * @since 4.6.0
		 *
		 * @return void
		 */
		public function no_items() {
			if ( null !== self::$wsal_db && isset( self::$wsal_db::$error_string ) && null !== self::$wsal_db::$error_string ) {
				echo self::$wsal_db::$error_string;
			} else {
				echo __( 'No logs found', 'wp-security-audit-log' );
			}
		}

		/**
		 * Fetch table data from the WordPress database.
		 *
		 * @since 4.6.0
		 *
		 * @return array
		 */
		public function fetch_table_data() {
			self::$query_occ = array();
			$bid             = self::$query_args['site_id'];
			if ( false !== $bid && -1 !== (int) $bid ) {
				if ( 0 === (int) $bid ) {
					self::$query_occ['AND'][] = array( ' site_id = 0 ' => $bid );
				} else {
					self::$query_occ['AND'][] = array( ' site_id = %s ' => $bid );
				}
			}

			// Set query order arguments.
			$order_by = isset( self::$query_args['order_by'] ) ? self::$query_args['order_by'] : false;
			$order    = isset( self::$query_args['order'] ) ? self::$query_args['order'] : false;

			self::$query_order = array();
			if ( ! $order_by ) {
				self::$query_order['created_on'] = 'DESC';
			} else {
				$is_descending = 'DESC';
				if ( $order && 'asc' === $order ) {
					$is_descending = 'ASC';
				}

				// TO DO: Allow order by meta values.
				if ( 'type' === $order_by ) {
					self::$query_order['alert_id'] = $is_descending;
				} elseif ( 'scip' === $order_by ) {
					self::$query_order['client_ip'] = $is_descending;
				} elseif ( 'user' === $order_by ) {
					self::$query_order['user_id'] = $is_descending;
				} elseif ( 'code' === $order_by ) {
					/*
					 * Handle the 'code' (Severity) column sorting.
					 */
					self::$query_order['severity'] = $is_descending;
				} elseif ( 'object' === $order_by ) {
					/*
					 * Handle the 'object' column sorting.
					 */
					self::$query_order['object'] = $is_descending;
				} elseif ( 'event_type' === $order_by ) {
					/*
					 * Handle the 'Event Type' column sorting.
					 */
					self::$query_order['event_type'] = $is_descending;
				} elseif ( isset( Occurrences_Entity::get_fields_values()[ $order_by ] ) ) {
					// TODO: We used to use a custom comparator ... is it safe to let MySQL do the ordering now?.
					self::$query_order[ $order_by ] = $is_descending;
				} elseif ( 'crtd' === $order_by ) {
					self::$query_order['created_on'] = $is_descending;
				} elseif ( 'site' === $order_by ) {
					self::$query_order['site_id'] = $is_descending;
				} else {
					self::$query_order['created_on'] = 'DESC';
				}
			}

			self::$query_occ = $this->search( self::$query_occ );

			// phpcs:ignore

			$events = Occurrences_Entity::build_query(
				array( 'COUNT(*)' => 'COUNT(*)' ),
				self::$query_occ,
				array(),
				array(),
				array(),
				self::$wsal_db
			);

			$this->count = reset( $events[0] );

			$per_page = $this->get_screen_option_per_page();
			$offset   = ( $this->get_pagenum() - 1 ) * $per_page;

			$events = Occurrences_Entity::build_query(
				array(),
				self::$query_occ,
				self::$query_order,
				array( $offset, $per_page ),
				array(),
				self::$wsal_db
			);

			$events = Occurrences_Entity::get_multi_meta_array( $events, self::$wsal_db );

			$this->items = $events;

			return $this->items;

			global $wpdb;


			$per_page = $this->get_screen_option_per_page();

			$current_page = $this->get_pagenum();
			if ( 1 < $current_page ) {
				$offset = $per_page * ( $current_page - 1 );
			} else {
				$offset = 0;
			}

			$search_string = ( isset( $_REQUEST['s'] ) ? \esc_sql( \sanitize_text_field( \wp_unslash( $_REQUEST['s'] ) ) ) : '' );

			$search_sql = '';

			if ( '' !== $search_string ) {
				$search_sql = 'AND (`id` LIKE "%' . $wpdb->esc_like( $search_string ) . '%"';
				foreach ( array_keys( Occurrences_Entity::get_fields() ) as $value ) {
					$search_sql .= ' OR ' . $value . ' LIKE "%' . esc_sql( $wpdb->esc_like( $search_string ) ) . '%" ';
				}
				$search_sql .= ') ';
			}

			// Set query order arguments.
			$order   = isset( self::$query_args['order'] ) ? self::$query_args['order'] : false;
			$orderby = ( isset( $_GET['orderby'] ) && '' != $_GET['orderby'] ) ? \esc_sql( \sanitize_text_field( \wp_unslash( $_GET['orderby'] ) ) ) : '`id`';
			$order   = ( isset( $_GET['order'] ) && '' != $_GET['orderby'] ) ? \esc_sql( \sanitize_text_field( \wp_unslash( $_GET['order'] ) ) ) : 'DESC';
			$query   = 'SELECT
			' . implode( ', ', array_keys( Occurrences_Entity::get_fields() ) ) . '
		  FROM ' . $this->table_name . '  WHERE 1=1 ' . $search_sql . ' ORDER BY ' . $orderby . ' ' . $order;

			$query .= Occurrences_Entity::get_connection()->prepare( ' LIMIT %d OFFSET %d;', $per_page, $offset );

			// query output_type will be an associative array with ARRAY_A.
			// phpcs:ignore
			$query_results = Occurrences_Entity::get_connection()->get_results($query, ARRAY_A);

			// phpcs:ignore
			$this->count = Occurrences_Entity::get_connection()->get_var('SELECT COUNT(`id`) FROM ' . $this->table_name . '  WHERE 1=1 ' . $search_sql);

			$this->items = $query_results;

			// return result array to prepare_items.
			return $query_results;
		}

		/**
		 * Returns the current query
		 *
		 * @return array
		 *
		 * @since 4.6.1
		 */
		public static function get_query_occ(): array {
			return self::$query_occ;
		}

		/**
		 * Render a column when no column specific method exists.
		 *
		 * Use that method for common rendering and separate columns logic in different methods. See below.
		 *
		 * @param array  $item        - Array with the current row values.
		 * @param string $column_name - The name of the currently processed column.
		 *
		 * @return mixed
		 *
		 * @since 4.6.0
		 */
		public function column_default( $item, $column_name ) {
			return self::format_column_value( $item, $column_name );
		}

		/**
		 * Render a column when no column specific method exists.
		 *
		 * Use that method for common rendering and separate columns logic in different methods. See below.
		 *
		 * @param array  $item        - Array with the current row values.
		 * @param string $column_name - The name of the currently processed column.
		 *
		 * @return mixed
		 *
		 * @since 4.6.1
		 */
		public static function format_column_value( $item, $column_name ) {
			switch ( $column_name ) {
				case 'type':
					if ( ! Settings_Helper::current_user_can( 'edit' ) ) {
						return '<span class="log-disable">' . str_pad( (string) $item['alert_id'], 4, '0', STR_PAD_LEFT ) . ' </span>';
					}

					$desc = Alert_Manager::get_alert_property( (int) $item['alert_id'], 'desc' );
					if ( false === $desc ) {
						$desc = __( 'Alert description not found.', 'wp-security-audit-log' );
					}
					$extra_msg           = '';
					$data_link           = '';
					$modification_alerts = array( 1002, 1003 );
					if ( in_array( (int) $item['alert_id'], $modification_alerts, true ) ) {
						$extra_msg = '. Modify this alert.';
						$data_link = add_query_arg( 'page', 'wsal-togglealerts#tab-users-profiles---activity', \network_admin_url( 'admin.php' ) );
					}

					$disabled_events = array_flip( Settings_Helper::get_disabled_alerts() );

					$disabled_event = ( isset( $disabled_events[ (int) $item['alert_id'] ] ) ? $disabled_events[ (int) $item['alert_id'] ] : '' );

					$id = ' id="alert_' . $item['alert_id'] . '"';

					if ( isset( $disabled_event ) && ! empty( $disabled_event ) ) {
						return '<span' . $id . ' class="log-disable" data-disabled="disabled" data-disable-alert-nonce="' . wp_create_nonce( 'disable-alert-nonce' . $item['alert_id'] ) . '" data-darktooltip="<strong>' . __( 'This event is disabled.', 'wp-security-audit-log' ) . '</strong><br>' . $item['alert_id'] . ' - ' . esc_html( $desc ) . $extra_msg . '" data-alert-id="' . $item['alert_id'] . '" ' . esc_attr( 'data-link=' . $data_link ) . ' >'
						. str_pad( (string) $item['alert_id'], 4, '0', STR_PAD_LEFT ) . ' </span>';
					}

					return '<span' . $id . ' class="log-disable" data-disable-alert-nonce="' . wp_create_nonce( 'disable-alert-nonce' . $item['alert_id'] ) . '" data-darktooltip="<strong>' . __( 'Disable this type of events.', 'wp-security-audit-log' ) . '</strong><br>' . $item['alert_id'] . ' - ' . esc_html( $desc ) . $extra_msg . '" data-alert-id="' . $item['alert_id'] . '" ' . esc_attr( 'data-link=' . $data_link ) . ' >'
						. str_pad( (string) $item['alert_id'], 4, '0', STR_PAD_LEFT ) . ' </span>';
				case 'code':
					$code = 0;
					if ( isset( $item['severity'] ) ) {
						$code = intval( $item['severity'] );
					}
					$const = Constants::get_severity_by_code( $code );

					$css_classes = array( 'log-type', 'log-type-' . $const['value'] );
					array_push( $css_classes, 'log-type-' . $const['css'] );

					return '<a class="tooltip" href="#" data-darktooltip="' . esc_html( $const['text'] ) . '"><span style="display:none; visibility:hidden">' . $const['text'] . '</span><span class="' . implode( ' ', $css_classes ) . '"></span></a>';
				case 'crtd':
					return $item['created_on']
						? DateTime_Formatter_Helper::get_formatted_date_time( $item['created_on'], 'datetime', true, true )
						: '<i>' . __( 'Unknown', 'wp-security-audit-log' ) . '</i>';
				case 'user':
					$username = User_Utils::get_username( $item['meta_values'] );
					$user     = \get_user_by( 'login', $username );
					$roles    = '';
					$image    = '<span class="dashicons dashicons-wordpress wsal-system-icon"></span>';

					// Check if there's a user with given username.
					if ( $user instanceof \WP_User ) {
						// Get user avatar.
						$image = get_avatar( $user->ID, 32 );

						$display_name   = User_Utils::get_display_label( $user );
						$user_edit_link = \network_admin_url( 'user-edit.php?user_id=' . $user->ID );

						// Additional user info tooltip.
						$tooltip = User_Utils::get_tooltip_user_content( $user );

						$uhtml = '<a class="tooltip" data-darktooltip="' . esc_attr( $tooltip ) . '" data-user="' . $user->user_login . '" href="' . $user_edit_link . '" target="_blank">' . esc_html( $display_name ) . '</a>';


						$roles = User_Utils::get_roles_label( $item['user_roles'] );
					} elseif ( 'Plugin' === $username ) {
						$uhtml = '<i style="display: inline-block;width: 65%;">' . __( 'Plugin', 'wp-security-audit-log' ) . '</i>';
					} elseif ( 'Plugins' === $username ) {
						$uhtml = '<i style="display: inline-block;width: 65%;">' . __( 'Plugins', 'wp-security-audit-log' ) . '</i>';
					} elseif ( 'Website Visitor' === $username || 'Unregistered user' === $username ) {
						$uhtml = '<i style="display: inline-block;width: 65%;">' . __( 'Unregistered user', 'wp-security-audit-log' ) . '</i>';
					} elseif ( isset( $username ) && ! empty( $username ) ) {
						$uhtml = '<i style="display: inline-block;width: 65%;">' . $username . '</i>';
						if ( isset( $item['user_id'] ) && $item['user_id'] ) {
							$uhtml .= '<div>' . __( 'Deleted user', 'wp-security-audit-log' ) . '</div>';
						}
					} else {
						$uhtml = '<i style="display: inline-block;width: 65%;">' . __( 'System', 'wp-security-audit-log' ) . '</i>';
					}

					$image = \apply_filters( 'wsal_get_user_image', $image, $item );
					$uhtml = \apply_filters( 'wsal_get_user_html', $uhtml, $item );

					$row_user_data = $image . $uhtml . '<br/>' . $roles;

					/*
					 * WSAL Filter: `wsal_auditlog_row_user_data`
					 *
					 * Filters user data before displaying on the audit log.
					 *
					 * @since 3.3.1
					 *
					 * @param string  $row_user_data          - User data to display in audit log row.
					 * @param integer $this->current_alert_id - Event database ID.
					 */
					return apply_filters( 'wsal_auditlog_row_user_data', $row_user_data, $item['id'] );
				case 'scip':
					$scip = $item['client_ip'];
					if ( is_string( $scip ) ) {
						$scip = str_replace( array( '"', '[', ']' ), '', $scip );
					}

					$oips = array();

					// If there's no IP...
					if ( is_null( $scip ) || '' === $scip ) {
						if ( isset( $item['meta_values'] ) && isset( $item['meta_values']['OtherIPs'] ) ) {
							if ( is_array( $item['meta_values']['OtherIPs'] ) ) {
								$scip = reset( $item['meta_values']['OtherIPs'] )[0];
							}
						} else {
							return '<i>unknown</i>';
						}
					}

					// If there's only one IP...
					$link = 'https://whatismyipaddress.com/ip/' . $scip . '?utm_source=plugin&utm_medium=referral&utm_campaign=wsal';
					if ( class_exists( 'WSAL_SearchExtension' ) ) {
						$tooltip = esc_attr__( 'Show me all activity originating from this IP Address', 'wp-security-audit-log' );

						if ( count( $oips ) < 2 ) {
							return "<a class='search-ip' data-darktooltip='$tooltip' data-ip='$scip' target='_blank' href='$link'>" . esc_html( $scip ) . '</a>';
						}
					} elseif ( count( $oips ) < 2 ) {
						return "<a target='_blank' href='$link'>" . esc_html( $scip ) . '</a>';
					}

					// If there are many IPs...
					if ( class_exists( 'WSAL_SearchExtension' ) ) {
						$tooltip = esc_attr__( 'Show me all activity originating from this IP Address', 'wp-security-audit-log' );

						$html = "<a class='search-ip' data-darktooltip='$tooltip' data-ip='$scip' target='_blank' href='https://whatismyipaddress.com/ip/$scip'>" . esc_html( $scip ) . '</a> <a href="javascript:;" onclick="jQuery(this).hide().next().show();">(more&hellip;)</a><div style="display: none;">';
						foreach ( $oips as $ip ) {
							if ($scip != $ip) { // phpcs:ignore
								$html .= '<div>' . $ip . '</div>';
							}
						}
						$html .= '</div>';

						return $html;
					} else {
						$html = "<a target='_blank' href='https://whatismyipaddress.com/ip/$scip'>" . esc_html( $scip ) . '</a> <a href="javascript:;" onclick="jQuery(this).hide().next().show();">(more&hellip;)</a><div style="display: none;">';
						foreach ( $oips as $ip ) {
							if ($scip != $ip) { // phpcs:ignore
								$html .= '<div>' . $ip . '</div>';
							}
						}
						$html .= '</div>';

						return $html;
					}

					// no break.
				case 'site':
					$info = null;
					if ( \function_exists( 'get_blog_details' ) ) {
						$info = get_blog_details( $item['site_id'], true );
					}

					$info = \apply_filters( 'wsal_get_site_details', $info, $item['site_id'], $item );

					return ! $info ? ( 'Unknown Site ' . $item['site_id'] )
						: ( '<a href="' . esc_attr( $info->siteurl ) . '">' . esc_html( $info->blogname ) . '</a>' );
				case 'mesg':
					$result = '<div id="Event' . $item['id'] . '">' . Occurrences_Entity::get_alert_message( $item ) . '</div>';

					return $result;
				case 'data':
					$url     = admin_url( 'admin-ajax.php' ) . '?action=AjaxInspector&amp;occurrence=' . $item['id'];
					$tooltip = esc_attr__( 'View all details of this change', 'wp-security-audit-log' );

					return '<a class="more-info button button-secondary data-event-inspector-link" data-darktooltip="' . $tooltip . '" data-inspector-active-text="' . __( 'Close inspector.', 'wp-security-audit-log' ) . '" title="' . __( 'Event data inspector', 'wp-security-audit-log' ) . '"'
						. ' href="' . $url . '">' . __( 'More details...', 'wp-security-audit-log' ) . '</a>';
				case 'object':
					$object = '';
					if ( isset( $item['object'] ) && ! empty( $item['object'] ) ) {
						$object = Alert_Manager::get_event_objects_data( $item['object'] );
					} else {
						$object = ( isset( $item['meta_values']['Object'] ) && ! empty( $item['meta_values']['Object'] ) ) ? Alert_Manager::get_event_objects_data( $item['meta_values']['Object'] ) : '';
					}
					return $object;
				case 'event_type':
					$event_type = '';
					if ( isset( $item['event_type'] ) && ! empty( $item['event_type'] ) ) {
						$event_type = Alert_Manager::get_event_type_data( $item['event_type'] );
					} else {
						$event_type = ( isset( $item['meta_values']['EventType'] ) && ! empty( $item['meta_values']['EventType'] ) ) ? Alert_Manager::get_event_type_data( $item['meta_values']['EventType'] ) : '';
					}

					return $event_type;
				default:
					return isset( $item[ $column_name ] )
						? esc_html( $item[ $column_name ] )
						: 'Column "' . esc_html( $column_name ) . '" not found';
			}
		}

		/**
		 * Responsible for common column rendering.
		 *
		 * NOTE: This method is not in use - for future reference.
		 *
		 * @param array  $item        - The current riw with data.
		 * @param string $column_name - The column name.
		 *
		 * @since 4.6.0
		 */
		private function common_column_render( array $item, $column_name ): string {
			global $pagenow, $current_screen;

			$admin_page_url = \network_admin_url( 'admin.php' );

			$paged = ( isset( $_GET['paged'] ) ) ? \sanitize_text_field( \wp_unslash( $_GET['paged'] ) ) : 1;

			$search  = ( isset( $_REQUEST['s'] ) ) ? '&s=' . \sanitize_text_field( \wp_unslash( $_REQUEST['s'] ) ) : '';
			$orderby = ( isset( $_REQUEST['orderby'] ) ) ? '&orderby=' . \sanitize_text_field( \wp_unslash( $_REQUEST['orderby'] ) ) : '';
			$order   = ( isset( $_REQUEST['order'] ) ) ? '&order=' . \sanitize_text_field( \wp_unslash( $_REQUEST['order'] ) ) : '';

			$actions = array();
			if ( 'plugin_id' === $column_name ) {
				// row actions to edit record.
				$query_args_view_data = array(
					'page'                    => ( isset( $_REQUEST['page'] ) ) ? \sanitize_text_field( \wp_unslash( $_REQUEST['page'] ) ) : 'wps-proxytron-sites',
					'action'                  => 'view_data',
					$this->table_name . '_id' => absint( $item[ $this->table::get_real_id_name() ] ),
					'_wpnonce'                => \wp_create_nonce( 'view_data_nonce' ),
					'get_back'                => urlencode( $pagenow . '?page=' . $current_screen->parent_base . '&paged=' . $paged . $search . $orderby . $order ),
				);
				$view_data_link       = esc_url( add_query_arg( $query_args_view_data, $admin_page_url ) );
				$actions['view_data'] = '<a href="' . $view_data_link . '">' . \esc_html( 'Show Info', 'wp-security-audit-log' ) . '</a>';
			}

			$row_value = '<strong>' . $item[ $column_name ] . '</strong>';

			return $row_value . $this->row_actions( $actions );
		}

		/**
		 * Get value for checkbox column.
		 *
		 * The special 'cb' column
		 *
		 * @param object $item - A row's data.
		 *
		 * @return string Text to be placed inside the column < td > .
		 *
		 * @since 4.6.0
		 */
		protected function column_cb( $item ) {
			return sprintf(
				'<label class="screen-reader-text" for="' . $this->table_name . '_' . $item['id'] . '">' . sprintf(
					// translators: The column name.
					__( 'Select %s' ),
					'id'
				) . '</label>'
				. '<input type="checkbox" name="' . $this->table_name . '[]" id="' . $this->table_name . '_' . $item['id'] . '" value="' . $item['id'] . '" />'
			);
		}
		/**
		 * Returns an associative array containing the bulk actions.
		 *
		 * @since 4.6.0
		 *
		 * @return array
		 */
		public function get_bulk_actions() {
			$actions = array();
			if ( Settings_Helper::current_user_can( 'view' ) ) {

				/**
				 * On hitting apply in bulk actions the url params are set as
				 * ?action=bulk-download&paged=1&action2=-1.
				 *
				 * Action and action2 are set based on the triggers above or below the table
				 */
				$actions = array(
					// phpcs:disable
					// phpcs:enable

					'disable_alerts' => __( 'Disable this type of event IDs', 'wp-security-audit-log' ),
					'disable_users'  => __( 'Do not keep a log of this user(s)\' actions', 'wp-security-audit-log' ),
					'disable_ips'    => __( 'Do not keep a log of these IP addresses', 'wp-security-audit-log' ),
				);
			}

			return $actions;
		}

		/**
		 * Process actions triggered by the user.
		 *
		 * @since 4.6.0
		 */
		public function handle_table_actions() {
			/**
			 * Note: Table bulk_actions can be identified by checking $_REQUEST['action'] and $_REQUEST['action2'].
			 *
			 * Action - is set if checkbox from top-most select-all is set, otherwise returns -1
			 * Action2 - is set if checkbox the bottom-most select-all checkbox is set, otherwise returns -1
			 */

			// check for individual row actions.
			$the_table_action = $this->current_action();

			if ( 'view_data' === $the_table_action ) {
				if ( ! isset( $_REQUEST['_wpnonce'] ) ) {
					$this->graceful_exit();
				}
				$nonce = \sanitize_text_field( \wp_unslash( $_REQUEST['_wpnonce'] ) );
				// verify the nonce.
				if ( ! wp_verify_nonce( $nonce, 'view_data_nonce' ) ) {
					$this->invalid_nonce_redirect();
				} else {
					$this->page_view_data( absint( $_REQUEST[ $this->table_name . '_id' ] ) );
					$this->graceful_exit();
				}
			}

			if ( 'add_data' === $the_table_action ) {
				if ( ! isset( $_REQUEST['_wpnonce'] ) ) {
					$this->graceful_exit();
				}
				$nonce = \sanitize_text_field( \wp_unslash( $_REQUEST['_wpnonce'] ) );

				// verify the nonce.
				if ( ! wp_verify_nonce( $nonce, 'add_' . $this->table_name . '_nonce' ) ) {
					$this->invalid_nonce_redirect();
				} else {
					$this->page_add_data( absint( $_REQUEST[ $this->table_name . '_id' ] ) );
					$this->graceful_exit();
				}
			}

			// phpcs:disable
			// phpcs:enable

			if ( ( ( isset( $_REQUEST['action'] ) && 'disable_alerts' === $_REQUEST['action'] ) || ( isset( $_REQUEST['action2'] ) && 'disable_alerts' === $_REQUEST['action2'] ) ) && Settings_Helper::current_user_can( 'edit' ) ) {
				if ( ! isset( $_REQUEST['_wpnonce'] ) ) {
					$this->graceful_exit();
				}
				$nonce = \sanitize_text_field( \wp_unslash( $_REQUEST['_wpnonce'] ) );
				// verify the nonce.
				/**
				 * Note: the nonce field is set by the parent class
				 * wp_nonce_field( 'bulk-' . $this->_args['plural'] );.
				 */
				if ( ! wp_verify_nonce( $nonce, 'bulk-' . $this->_args['plural'] ) ) {
					$this->invalid_nonce_redirect();
				} elseif ( isset( $_REQUEST[ $this->table_name ] ) && \is_array( $_REQUEST[ $this->table_name ] ) ) {

					$disabled_alerts = Settings_Helper::get_option_value( 'disabled-alerts' );

					if ( is_string( $disabled_alerts ) ) {
						$disabled_alerts = \array_filter( \array_map( 'trim', \explode( ',', $disabled_alerts ) ) );
					}

					$ids_array = \array_filter( (array) $_REQUEST[ $this->table_name ] );

					foreach ( $ids_array as $id ) {
						$row_data          = $this->table::load( 'id=%d', array( (int) $id ), self::$wsal_db );
						$disabled_alerts[] = esc_html( $row_data['alert_id'] );
					}

					Settings_Helper::set_option_value( 'disabled-alerts', \array_filter( $disabled_alerts ) );
				}
			}

			if ( ( ( isset( $_REQUEST['action'] ) && 'disable_users' === $_REQUEST['action'] ) || ( isset( $_REQUEST['action2'] ) && 'disable_users' === $_REQUEST['action2'] ) ) && Settings_Helper::current_user_can( 'edit' ) ) {
				if ( ! isset( $_REQUEST['_wpnonce'] ) ) {
					$this->graceful_exit();
				}
				$nonce = \sanitize_text_field( \wp_unslash( $_REQUEST['_wpnonce'] ) );
				// verify the nonce.
				/**
				 * Note: the nonce field is set by the parent class
				 * wp_nonce_field( 'bulk-' . $this->_args['plural'] );.
				 */
				if ( ! wp_verify_nonce( $nonce, 'bulk-' . $this->_args['plural'] ) ) {
					$this->invalid_nonce_redirect();
				} elseif ( isset( $_REQUEST[ $this->table_name ] ) && \is_array( $_REQUEST[ $this->table_name ] ) ) {

					$disabled_users = Settings_Helper::get_excluded_monitoring_users();

					$ids_array = \array_filter( (array) $_REQUEST[ $this->table_name ] );

					foreach ( $ids_array as $id ) {
						$row_data = $this->table::load( 'id=%d', array( (int) $id ), self::$wsal_db );
						if ( isset( $row_data['username'] ) && ! empty( $row_data['username'] ) && 'System' !== $row_data['username'] ) {
							$disabled_users[] = esc_html( $row_data['username'] );
						} elseif ( isset( $row_data['user_id'] ) && ! empty( $row_data['user_id'] ) ) {
							$user             = get_user_by( 'id', $row_data['user_id'] );
							$disabled_users[] = esc_html( $user->user_login );
						}
					}
					Settings_Helper::set_excluded_monitoring_users( \array_filter( $disabled_users ) );
				}
			}

			if ( ( ( isset( $_REQUEST['action'] ) && 'disable_ips' === $_REQUEST['action'] ) || ( isset( $_REQUEST['action2'] ) && 'disable_ips' === $_REQUEST['action2'] ) ) && Settings_Helper::current_user_can( 'edit' ) ) {
				if ( ! isset( $_REQUEST['_wpnonce'] ) ) {
					$this->graceful_exit();
				}
				$nonce = \sanitize_text_field( \wp_unslash( $_REQUEST['_wpnonce'] ) );
				// verify the nonce.
				/**
				 * Note: the nonce field is set by the parent class
				 * wp_nonce_field( 'bulk-' . $this->_args['plural'] );.
				 */
				if ( ! wp_verify_nonce( $nonce, 'bulk-' . $this->_args['plural'] ) ) {
					$this->invalid_nonce_redirect();
				} elseif ( isset( $_REQUEST[ $this->table_name ] ) && \is_array( $_REQUEST[ $this->table_name ] ) ) {

					$disabled_ips = Settings_Helper::get_excluded_monitoring_ips();

					$ids_array = \array_filter( (array) $_REQUEST[ $this->table_name ] );

					foreach ( $ids_array as $id ) {
						$row_data = $this->table::load( 'id=%d', array( (int) $id ), self::$wsal_db );
						if ( isset( $row_data['client_ip'] ) && ! empty( $row_data['client_ip'] ) ) {
							$disabled_ips[] = esc_html( $row_data['client_ip'] );
						}
					}
					Settings_Helper::set_excluded_monitoring_ips( \array_filter( $disabled_ips ) );
				}
			}
		}

		/**
		 * View a license information.
		 *
		 * @since 4.6.0
		 *
		 * @param int $table_id - Record ID.
		 */
		public function page_view_data( $table_id ) {
		}

		/**
		 * Stop execution and exit.
		 *
		 * @since 4.6.0
		 *
		 * @return void
		 */
		public function graceful_exit() {
			exit;
		}

		/**
		 * Die when the nonce check fails.
		 *
		 * @since 4.6.0
		 *
		 * @return void
		 */
		public function invalid_nonce_redirect() {
			wp_die(
				'Invalid Nonce',
				'Error',
				array(
					'response'  => 403,
					'back_link' => esc_url( add_query_arg( array( 'page' => \wp_unslash( $_REQUEST['page'] ) ), \network_admin_url( 'users.php' ) ) ),
				)
			);
		}

		/**
		 * Returns the records to show per page.
		 *
		 * @return int
		 *
		 * @since 4.6.0
		 */
		public function get_records_per_page() {
			return $this->records_per_page;
		}

		/**
		 * Get the screen option per_page.
		 *
		 * @return int
		 *
		 * @since 4.6.0
		 */
		private function get_screen_option_per_page() {
			$this->get_wp_screen();
			$option = $this->wp_screen->get_option( 'per_page', 'option' );
			if ( ! $option ) {
				$option = str_replace( '-', '_', "{$this->wp_screen->id}_per_page" );
			}

			$per_page = (int) get_user_option( $option );
			if ( empty( $per_page ) || $per_page < 1 ) {
				$per_page = $this->wp_screen->get_option( 'per_page', 'default' );
				if ( ! $per_page ) {
					$per_page = $this->get_records_per_page();
				}
			}

			return $per_page;
		}

		/**
		 * Returns the columns array (with column name).
		 *
		 * @return array
		 *
		 * @since 4.6.0
		 */
		private static function get_column_names() {
			return self::$columns;
		}

		/**
		 * Adds a screen options to the current screen table.
		 *
		 * @param \WP_Hook $hook - The hook object to attach to.
		 *
		 * @return void
		 *
		 * @since 4.6.0
		 */
		public static function add_screen_options( $hook ) {
			$screen_options = array( 'per_page' => __( 'Records per page', 'wp-security-audit-log' ) );

			$result = array();

			array_walk(
				$screen_options,
				function ( &$a, $b ) use ( &$result ) {
					$result[ self::SCREEN_OPTIONS_SLUG . '_' . $b ] = $a;
				}
			);
			$screen_options = $result;

			foreach ( $screen_options as $key => $value ) {
				add_action(
					"load-$hook",
					function () use ( $key, $value ) {
						$option = 'per_page';
						$args   = array(
							'label'   => $value,
							'default' => (int) Settings_Helper::get_option_value( 'items-per-page', 10 ),
							'option'  => $key,
						);
						add_screen_option( $option, $args );
					}
				);
			}
		}

		/**
		 * Form table per-page screen option value.
		 *
		 * @since 4.6.0
		 *
		 * @param bool   $keep   Whether to save or skip saving the screen option value. Default false.
		 * @param string $option The option name.
		 * @param int    $value  The number of rows to use.
		 *
		 * @return mixed
		 */
		public static function set_screen_option( $keep, $option, $value ) {
			if ( false !== \strpos( $option, self::SCREEN_OPTIONS_SLUG . '_' ) ) {
				return $value;
			}

			return $keep;
		}

		/**
		 * Table navigation.
		 *
		 * @param string $which - Position of the nav.
		 */
		public function extra_tablenav( $which ) {
			// If the position is not top then render.

			// Show site alerts widget.
			// NOTE: this is shown when the filter IS NOT true.
			if ( WP_Helper::is_multisite() && is_network_admin() ) {
				if (
					( 'top' === $which )
				) {
					$curr = WP_Helper::get_view_site_id();
					?>
				<div class="wsal-ssa wsal-ssa-<?php echo esc_attr( $which ); ?>">
					<?php if ( WP_Helper::get_site_count() > 15 ) { ?>
						<?php $curr = $curr ? get_blog_details( $curr ) : null; ?>
						<?php $curr = $curr ? ( $curr->blogname . ' (' . $curr->domain . ')' ) : 'All Sites'; ?>
						<input type="text" class="wsal-ssas" value="<?php echo esc_attr( $curr ); ?>"/>
					<?php } else { ?>
						<select class="wsal-ssas" onchange="WsalSsasChange(value);">
							<option value="0"><?php esc_html_e( 'All Sites', 'wp-security-audit-log' ); ?></option>
							<?php foreach ( WP_Helper::get_sites() as $info ) { ?>
								<option value="<?php echo esc_attr( $info->blog_id ); ?>" <?php selected( $info->blog_id, $curr ); ?>>
									<?php echo esc_html( $info->blogname ) . ' (' . esc_html( $info->domain ) . ')'; ?>
								</option>
							<?php } ?>
						</select>
					<?php } ?>
				</div>
					<?php
				}
			}

			if (
				( 'top' === $which )
			) {
				\do_action( 'wsal_list_view_top_navigation' );
			}

			// phpcs:disable
		}

		/**
		 * Alters the search query.
		 *
		 * @param array $query      - The current search query.
		 * @param array $connection - The connection (not in use).
		 *
		 * @since 4.6.0
		 */
		public function search( $query, $connection = null ): array {
			global $wpdb;

			$search_string = ( isset( $_REQUEST['s'] ) ? \esc_sql( \sanitize_text_field( \wp_unslash( $_REQUEST['s'] ) ) ) : '' );

			if ( '' !== $search_string ) {
				// phpcs:ignore
				/* @free:start */
				Settings_Helper::delete_option_value( 'free-search-try' );
				$column_names = $this->table::get_fields();
				unset( $column_names['user_roles'] );
				unset( $column_names['severity'] );
				unset( $column_names['object'] );
				// phpcs:ignore
				/* @free:end */
				// phpcs:ignore
				unset( $column_names['created_on'] );
				unset( $column_names['site_id'] );
				foreach ( array_keys( $column_names ) as $value ) {
					$search[] = array( $value . ' LIKE %s' => '%' . esc_sql( $wpdb->esc_like( $search_string ) ) . '%' );
				}

				$query['OR'] = $search;

				// phpcs:ignore
				/* @free:start */
				$query['OR'][] = array(
					$this->table::get_table_name( self::$wsal_db ) . '.id IN (
					SELECT DISTINCT occurrence_id
						FROM ' . Metadata_Entity::get_table_name( self::$wsal_db ) . '
						WHERE TRIM(BOTH "\"" FROM value) LIKE %s
					)' => '%' . $search_string . '%',
				);
				// phpcs:ignore
				/* @free:end */
			}

			return $query;
		}
	}
}
