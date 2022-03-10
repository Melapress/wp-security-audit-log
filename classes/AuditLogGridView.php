<?php
/**
 * Audit Log List View
 *
 * CLass file for audit log list view.
 *
 * @since   1.0.0
 * @package wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once ABSPATH . 'wp-admin/includes/admin.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

/**
 * This view is included in Audit Log Viewer Page.
 *
 * @see     Views/AuditLog.php
 * @package wsal
 */
class WSAL_AuditLogGridView extends WP_List_Table {

	/**
	 * Instance of WpSecurityAuditLog.
	 *
	 * @var WpSecurityAuditLog
	 */
	protected $plugin;

	/**
	 * Current Alert ID
	 *
	 * This class member is used to store the alert ID
	 * of the alert which is being rendered.
	 *
	 * @var integer
	 */
	private $current_alert_id = 0;

	/**
	 * Selected Columns.
	 *
	 * @since 3.3.1
	 *
	 * @var array()
	 */
	private $selected_columns = '';

	/**
	 * Display Name Type.
	 *
	 * @since 3.3.1
	 *
	 * @var string
	 */
	private $name_type = '';

	/**
	 * Events Query Arguments.
	 *
	 * @since 3.3.1.1
	 *
	 * @var stdClass
	 */
	private $query_args;

	/**
	 * Event Items Meta Array.
	 *
	 * @since 3.4
	 *
	 * @var array
	 */
	private $item_meta = array();

	/**
	 * Audit log view.
	 *
	 * @var WSAL_Views_AuditLog
	 * @since 4.3.2
	 */
	private $audit_log_view;

	/**
	 * Method: Constructor.
	 *
	 * @param WpSecurityAuditLog  $plugin         Instance of WpSecurityAuditLog.
	 * @param WSAL_Views_AuditLog $audit_log_view Audit log view.
	 * @param stdClass            $query_args     Events query arguments.
	 */
	public function __construct( $plugin, $audit_log_view, $query_args ) {
		$this->plugin         = $plugin;
		$this->audit_log_view = $audit_log_view;
		$this->query_args     = $query_args;

		parent::__construct(
			array(
				'singular' => 'log',
				'plural'   => 'logs',
				'ajax'     => true,
				'screen'   => 'interval-grid',
			)
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function no_items() {
		esc_html_e( 'No events so far.', 'wp-security-audit-log' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_table_classes() {
		return array( 'widefat', 'fixed', 'striped', $this->_args['plural'], 'wsal-table', 'wsal-table-grid' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function display_tablenav( $which ) {
		if ( 'top' === $which ) {
			wp_nonce_field( 'bulk-' . $this->_args['plural'] );
		}
		?>
		<div class="tablenav <?php echo esc_attr( $which ); ?>">
			<?php
			$this->extra_tablenav( $which );

			/**
			 * Action: `wsal_search_filters_list`
			 *
			 * Display list of search filters of WSAL.
			 *
			 * @param string $which – Navigation position; value is either top or bottom.
			 * @since 3.2.3
			 */
			do_action( 'wsal_search_filters_list', $which );
			?>
			<div class="display-type-buttons">
				<?php
				$user_selected_view = $this->plugin->views->views[0]->detect_view_type();
				?>
				<a id ="wsal-list-view-toggle" href="<?php echo esc_url( add_query_arg( 'view', 'list' ) ); ?>" class="button wsal-button dashicons-before dashicons-list-view" <?php echo ( 'list' === $user_selected_view ) ? esc_attr( 'disabled' ) : ''; ?>><?php esc_html_e( 'List View', 'wp-security-audit-log' ); ?></a>
				<a id ="wsal-grid-view-toggle" href="<?php echo esc_url( add_query_arg( 'view', 'grid' ) ); ?>" class="button wsal-button dashicons-before dashicons-grid-view" <?php echo ( 'grid' === $user_selected_view ) ? esc_attr( 'disabled' ) : ''; ?>><?php esc_html_e( 'Grid View', 'wp-security-audit-log' ); ?></a>
			</div>
			<?php
			$this->pagination( $which );
			?>
		</div>
		<?php
	}

	/**
	 * {@inheritDoc}
	 */
	public function extra_tablenav( $which ) {
		// If the position is not top then render.
		if ( 'top' !== $which && ! $this->plugin->settings()->is_infinite_scroll() ) :
			// Items-per-page widget.
			$p     = $this->plugin->settings()->get_views_per_page();
			$items = array( 5, 10, 15, 30, 50 );
			if ( ! in_array( $p, $items, true ) ) {
				$items[] = $p;
			}
			?>
			<div class="wsal-ipp wsal-ipp-<?php echo esc_attr( $which ); ?>">
				<?php esc_html_e( 'Show ', 'wp-security-audit-log' ); ?>
				<select class="wsal-ipps" onfocus="WsalIppsFocus(value);" onchange="WsalIppsChange(value);">
					<?php foreach ( $items as $item ) : ?>
						<option value="<?php echo is_string( $item ) ? '' : esc_attr( $item ); ?>" <?php echo ( $item === $p ) ? 'selected="selected"' : false; ?>>
							<?php echo esc_html( $item ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<?php esc_html_e( ' Items', 'wp-security-audit-log' ); ?>
			</div>
			<?php
		endif;

		if ( 'top' !== $which && $this->plugin->settings()->is_infinite_scroll() ) :
			?>
			<div id="wsal-auditlog-end"><p><?php esc_html_e( '— End of Activity Log —', 'wp-security-audit-log' ); ?></p></div>
			<div id="wsal-event-loader"><div class="wsal-lds-ellipsis"><div></div><div></div><div></div><div></div></div></div>
			<?php
		endif;

		// Show site alerts widget.
		// NOTE: this is shown when the filter IS NOT true.
		if ( $this->is_multisite() && $this->is_main_blog() && ! apply_filters( 'search_extensition_active', false ) ) {
			if (
				( 'top' === $which && $this->plugin->settings()->is_infinite_scroll() )
				|| ! $this->plugin->settings()->is_infinite_scroll()
			) {
				$curr = $this->plugin->settings()->get_view_site_id();
				?>
				<div class="wsal-ssa wsal-ssa-<?php echo esc_attr( $which ); ?>">
					<?php if ( $this->get_site_count() > 15 ) : ?>
						<?php $curr = $curr ? get_blog_details( $curr ) : null; ?>
						<?php $curr = $curr ? ( $curr->blogname . ' (' . $curr->domain . ')' ) : 'All Sites'; ?>
						<input type="text" class="wsal-ssas" value="<?php echo esc_attr( $curr ); ?>"/>
					<?php else : ?>
						<select class="wsal-ssas" onchange="WsalSsasChange(value);">
							<option value="0"><?php esc_html_e( 'All Sites', 'wp-security-audit-log' ); ?></option>
							<?php foreach ( $this->get_sites() as $info ) : ?>
								<option value="<?php echo esc_attr( $info->blog_id ); ?>" <?php echo ( $info->blog_id === $curr ) ? 'selected="selected"' : false; ?>>
									<?php echo esc_html( $info->blogname ) . ' (' . esc_html( $info->domain ) . ')'; ?>
								</option>
							<?php endforeach; ?>
						</select>
					<?php endif; ?>
				</div>
				<?php
			}
		}

	}

	/**
	 * Method: Object with keys: blog_id, blogname, domain.
	 *
	 * @param int|null $limit - Maximum number of sites to return (null = no limit).
	 * @return object
	 */
	public function get_sites( $limit = null ) {
		global $wpdb;
		// Build query.
		$sql = 'SELECT blog_id, domain FROM ' . $wpdb->blogs;
		if ( ! is_null( $limit ) ) {
			$sql .= ' LIMIT ' . $limit;
		}

		// Execute query.
		$res = $wpdb->get_results( $sql ); // phpcs:ignore

		// Modify result.
		foreach ( $res as $row ) {
			$row->blogname = get_blog_option( $row->blog_id, 'blogname' );
		}

		// Return result.
		return $res;
	}

	/**
	 * Method: The number of sites on the network.
	 *
	 * @return int
	 */
	public function get_site_count() {
		global $wpdb;
		$sql = 'SELECT COUNT(*) FROM ' . $wpdb->blogs;
		return (int) $wpdb->get_var( $sql ); // phpcs:ignore
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_columns() {

		$cols = array(
			'type' => __( 'ID', 'wp-security-audit-log' ),
			'code' => __( 'Severity', 'wp-security-audit-log' ),
			'info' => __( 'Info', 'wp-security-audit-log' ),
		);

		// If multisite then add "Site" column to the view.
		if ( $this->is_multisite() && $this->is_main_blog() && ! $this->is_specific_view() ) {
			$cols['site'] = __( 'Site', 'wp-security-audit-log' );
		}

		$cols['mesg'] = __( 'Message', 'wp-security-audit-log' );

		// Get selected columns from settings.
		if ( empty( $this->selected_columns ) && ! is_array( $this->selected_columns ) ) {
			$this->selected_columns = $this->plugin->settings()->get_columns_selected();
		}

		// If selected columns are not empty, then unset default columns.
		if ( ! empty( $this->selected_columns ) ) {
			unset( $cols );
			$this->selected_columns = is_string( $this->selected_columns ) ? (array) json_decode( $this->selected_columns ) : $this->selected_columns;
			foreach ( $this->selected_columns as $key => $value ) {
				switch ( $key ) {
					case 'alert_code':
						$cols['type'] = __( 'ID', 'wp-security-audit-log' );
						break;
					case 'type':
						$cols['code'] = __( 'Severity', 'wp-security-audit-log' );
						break;
					case 'info':
						$cols['info'] = __( 'Grid', 'wp-security-audit-log' );
						break;
					case 'message':
						$cols['mesg'] = __( 'Message', 'wp-security-audit-log' );
						break;
					default:
						// Fallback for any new columns would go here.
						break;
				}
			}
		}
		$cols['data'] = '';
		return $cols;
	}

	/**
	 * {@inheritDoc}
	 */
	public function column_cb( $item ) {
		return '<input type="checkbox" value="' . $item->id . '" name="' . esc_attr( $this->_args['singular'] ) . '[]" />';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_sortable_columns() {
		return array(
			'type' => array( 'alert_id', false ),
			'info' => array( 'info', false ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function column_default( $item, $column_name ) {
		// Store meta if not set.
		if ( ! isset( $this->item_meta[ $item->get_id() ] ) ) {
			$this->item_meta[ $item->get_id() ] = $item->get_meta_array();
		}

		// Store current alert id.
		$this->current_alert_id = $item->id;

		switch ( $column_name ) {
			case 'type':
				$code                = $this->plugin->alerts->get_alert( $item->alert_id );
				$extra_msg           = '';
				$data_link           = '';
				$modification_alerts = array( 1002, 1003 );
				if ( in_array( $item->alert_id, $modification_alerts, true ) ) {
					$extra_msg = '. Modify this alert.';
					$data_link = add_query_arg( 'page', 'wsal-togglealerts#tab-users-profiles---activity', admin_url( 'admin.php' ) );
				}

				if ( ! $this->plugin->settings()->current_user_can( 'edit' ) ) {
					return '<span class="log-disable">' . str_pad( $item->alert_id, 4, '0', STR_PAD_LEFT ) . ' </span>';
				}
				// add description to $extra_msg only if one is available.
				$extra_msg = ( isset( $code->desc ) ) ? ' - ' . esc_html( $code->desc ) . $extra_msg : $extra_msg;
				return '<span class="log-disable" data-disable-alert-nonce="' . wp_create_nonce( 'disable-alert-nonce' . $item->alert_id ) . '" data-tooltip="<strong>' . __( 'Disable this type of events.', 'wp-security-audit-log' ) . '</strong><br>' . $item->alert_id . $extra_msg . '" data-alert-id="' . $item->alert_id . '" ' . esc_attr( 'data-link=' . $data_link ) . ' >'
					. str_pad( $item->alert_id, 4, '0', STR_PAD_LEFT ) . ' </span>';

			case 'code':
				$code  = $this->plugin->alerts->get_alert( $item->alert_id );
				$code  = $code ? $code->severity : 0;
				$const = $this->plugin->constants->get_constant_to_display( $code );

				$css_classes = array( 'log-type', 'log-type-' . $const->value );
				if ( property_exists( $const, 'css' ) ) {
					array_push( $css_classes, 'log-type-' . $const->css );
				}
				return '<a class="tooltip" href="#" data-tooltip="' . esc_html( $const->name ) . '"><span class="' . implode( ' ', $css_classes ) . '"></span></a>';
			case 'site':
				$info = get_blog_details( $item->site_id, true );
				return ! $info ? ( 'Unknown Site ' . $item->site_id )
					: ( '<a href="' . esc_attr( $info->siteurl ) . '">' . esc_html( $info->blogname ) . '</a>' );
			case 'mesg':
				// login, logout and failed login have no message attached.
				if ( ! in_array( $item->alert_id, array( 1000, 1001, 1002 ), true ) ) {
					$event_meta = $this->item_meta[ $item->get_id() ];
					$result     = '<table id="Event' . absint( $item->id ) . '">';
					$result    .= '<td class="wsal-grid-text-header">' . esc_html__( 'Message:', 'wp-security-audit-log' ) . '</td>';
					$result    .= '<td class="wsal-grid-text-data">' . $item->get_message( $event_meta ) . '</td>';
					$result    .= '</table>';
					$result    .= $this->audit_log_view->maybe_build_teaser_html( $event_meta );
					return $result;
				}
				return '';
			case 'info':
				$eventdate = $item->created_on
					? WSAL_Utilities_DateTimeFormatter::instance()->get_formatted_date_time( $item->created_on, 'date' )
					: '<i>' . __( 'Unknown', 'wp-security-audit-log' ) . '</i>';

				$eventtime = $item->created_on
					? WSAL_Utilities_DateTimeFormatter::instance()->get_formatted_date_time( $item->created_on, 'time' )
					: '<i>' . __( 'Unknown', 'wp-security-audit-log' ) . '</i>';

				$username = WSAL_Utilities_UsersUtils::get_username( $this->item_meta[ $item->get_id() ] ); // Get username.
				$user     = get_user_by( 'login', $username ); // Get user.
				if ( empty( $this->name_type ) ) {
					$this->name_type = $this->plugin->settings()->get_type_username();
				}

				// Check if the username and user exists.
				if ( $username && $user ) {

					$display_name   = WSAL_Utilities_UsersUtils::get_display_label( $this->plugin, $user );
					$user_edit_link = admin_url( 'user-edit.php?user_id=' . $user->ID );

					// Additional user info tooltip.
					$tooltip = WSAL_Utilities_UsersUtils::get_tooltip_user_content( $user );
					$uhtml   = '<a class="tooltip" data-tooltip="' . esc_attr( $tooltip ) . '" data-user="' . $user->user_login . '" href="' . $user_edit_link . '" target="_blank">' . esc_html( $display_name ) . '</a>';


					$roles = WSAL_Utilities_UsersUtils::get_roles_label( $item->get_user_roles() );
				} elseif ( 'Plugin' === $username ) {
					$uhtml = '<i>' . __( 'Plugin', 'wp-security-audit-log' ) . '</i>';
					$roles = '';
				} elseif ( 'Plugins' === $username ) {
					$uhtml = '<i>' . __( 'Plugins', 'wp-security-audit-log' ) . '</i>';
					$roles = '';
				} elseif ( 'Website Visitor' === $username || 'Unregistered user' === $username ) {
					$uhtml = '<i>' . __( 'Unregistered user', 'wp-security-audit-log' ) . '</i>';
					$roles = '';
				} else {
					$uhtml = '<i>' . __( 'System', 'wp-security-audit-log' ) . '</i>';
					$roles = '';
				}
				$row_user_data = $uhtml . '<br/>' . $roles;

				/**
				 * WSAL Filter: `wsal_auditlog_row_user_data`
				 *
				 * Filters user data before displaying on the audit log.
				 *
				 * @since 3.3.1
				 *
				 * @param string  $row_user_data          - User data to display in audit log row.
				 * @param integer $this->current_alert_id - Event database ID.
				 */
				$eventuser = apply_filters( 'wsal_auditlog_row_user_data', $row_user_data, $this->current_alert_id );

				$scip = $item->get_source_ip();
				if ( is_string( $scip ) ) {
					$scip = str_replace( array( '"', '[', ']' ), '', $scip );
				}

				$oips = array();

				// If there's no IP...
				if ( is_null( $scip ) || '' === $scip ) {
					return '<i>unknown</i>';
				}

				// If there's only one IP...
				$link = 'https://whatismyipaddress.com/ip/' . $scip . '?utm_source=plugin&utm_medium=referral&utm_campaign=WPSAL';
				if ( class_exists( 'WSAL_SearchExtension' ) ) {
					$tooltip = esc_attr__( 'Show me all activity originating from this IP Address', 'wp-security-audit-log' );

					if ( count( $oips ) < 2 ) {
						$oips_html = "<a class='search-ip' data-tooltip='$tooltip' data-ip='$scip' target='_blank' href='$link'>" . esc_html( $scip ) . '</a>';
					}
				} else {
					if ( count( $oips ) < 2 ) {
						$oips_html = "<a target='_blank' href='$link'>" . esc_html( $scip ) . '</a>';
					}
				}

				// If there are many IPs...
				if ( class_exists( 'WSAL_SearchExtension' ) ) {
					$tooltip = esc_attr__( 'Show me all activity originating from this IP Address', 'wp-security-audit-log' );

					$ip_html = "<a class='search-ip' data-tooltip='$tooltip' data-ip='$scip' target='_blank' href='https://whatismyipaddress.com/ip/$scip'>" . esc_html( $scip ) . '</a> <a href="javascript:;" onclick="jQuery(this).hide().next().show();">(more&hellip;)</a><div style="display: none;">';
					foreach ( $oips as $ip ) {
						if ( $scip != $ip ) { // phpcs:ignore
							$ip_html .= '<div>' . $ip . '</div>';
						}
					}
					$ip_html .= '</div>';
				} else {
					$ip_html = "<a target='_blank' href='https://whatismyipaddress.com/ip/$scip'>" . esc_html( $scip ) . '</a> <a href="javascript:;" onclick="jQuery(this).hide().next().show();">(more&hellip;)</a><div style="display: none;">';
					foreach ( $oips as $ip ) {
						if ( $scip != $ip ) { // phpcs:ignore
							$ip_html .= '<div>' . $ip . '</div>';
						}
					}
					$ip_html .= '</div>';
				}

				$eventobj = isset( $this->item_meta[ $item->get_id() ]['Object'] ) ? $this->plugin->alerts->get_event_objects_data( $this->item_meta[ $item->get_id() ]['Object'] ) : '';

				$eventtypeobj = isset( $this->item_meta[ $item->get_id() ]['EventType'] ) ? $this->plugin->alerts->get_event_type_data( $this->item_meta[ $item->get_id() ]['EventType'] ) : '';

				ob_start();
				?>
				<table>
					<tr>
						<td class="wsal-grid-text-header"><?php esc_html_e( 'Date:' ); ?></td>
						<td class="wsal-grid-text-data"><?php echo $eventdate;  // phpcs:ignore ?></td>
					</tr>
					<tr>
						<td class="wsal-grid-text-header"><?php esc_html_e( 'Time:' ); ?></td>
						<td class="wsal-grid-text-data"><?php echo $eventtime; // phpcs:ignore ?></td>
					</tr>
					<tr>
						<td class="wsal-grid-text-header"><?php esc_html_e( 'User:' ); ?></td>
						<td class="wsal-grid-text-data"><?php echo $eventuser; // phpcs:ignore ?></td>
					</tr>
					<tr>
						<td class="wsal-grid-text-header"><?php esc_html_e( 'IP:' ); ?></td>
						<td class="wsal-grid-text-data"><?php echo ( isset( $oips_html ) && ! empty( $oips_html ) ) ? $oips_html : $ip_html; // phpcs:ignore ?></td>
					</tr>
					<tr>
						<td class="wsal-grid-text-header"><?php esc_html_e( 'Object:' ); ?></td>
						<td class="wsal-grid-text-data"><?php echo $eventobj; // phpcs:ignore ?></td>
					</tr>
					<tr>
						<td class="wsal-grid-text-header"><?php esc_html_e( 'Event Type:' ); ?></td>
						<td class="wsal-grid-text-data"><?php echo $eventtypeobj; // phpcs:ignore ?></td>
					</tr>
				</table>
				<?php
				return ob_get_clean();
			case 'data':
				$url     = admin_url( 'admin-ajax.php' ) . '?action=AjaxInspector&amp;occurrence=' . $item->id;
				$tooltip = esc_attr__( 'View all details of this change', 'wp-security-audit-log' );
				return '<a class="more-info thickbox" data-tooltip="' . $tooltip . '" title="' . __( 'Alert Data Inspector', 'wp-security-audit-log' ) . '"'
					. ' href="' . $url . '&amp;TB_iframe=true&amp;width=600&amp;height=550">&hellip;</a>';
			default:
				return isset( $item->$column_name )
					? esc_html( $item->$column_name )
					: 'Column "' . esc_html( $column_name ) . '" not found';
		}
	}

	/**
	 * Method: Reorder string items.
	 *
	 * @param object $a - Item to compare.
	 * @param object $b - Item to compare.
	 * @return int
	 */
	public function reorder_items_str( $a, $b ) {
		$result = strcmp( $a->{$this->_orderby}, $b->{$this->_orderby} );
		return ( 'asc' === $this->_order ) ? $result : -$result;
	}

	/**
	 * Method: Reorder items.
	 *
	 * @param object $a - Item to compare.
	 * @param object $b - Item to compare.
	 * @return int
	 */
	public function reorder_items_int( $a, $b ) {
		$result = $a->{$this->_orderby} - $b->{$this->_orderby};
		return ( 'asc' === $this->_order ) ? $result : -$result;
	}

	/**
	 * Method: Check if multisite.
	 *
	 * @return bool
	 */
	protected function is_multisite() {
		return $this->plugin->is_multisite();
	}

	/**
	 * Method: Check if the blog is main blog.
	 *
	 * @return bool
	 */
	protected function is_main_blog() {
		return get_current_blog_id() === 1;
	}

	/**
	 * Method: Check if it is a specific view.
	 *
	 * @return bool
	 */
	protected function is_specific_view() {
		return isset( $this->query_args->site_id ) && '0' != $this->query_args->site_id; // phpcs:ignore
	}

	/**
	 * Method: Get a specific view.
	 *
	 * @return int
	 */
	protected function get_specific_view() {
		return isset( $this->query_args->site_id ) ? (int) $this->query_args->site_id : 0;
	}

	/**
	 * Method: Get view site id.
	 *
	 * @return int
	 */
	protected function get_view_site_id() {
		switch ( true ) {
			// Non-multisite.
			case ! $this->is_multisite():
				return 0;
			// Multisite + main site view.
			case $this->is_main_blog() && ! $this->is_specific_view():
				return 0;
			// Multisite + switched site view.
			case $this->is_main_blog() && $this->is_specific_view():
				return $this->get_specific_view();
			// Multisite + local site view.
			default:
				return get_current_blog_id();
		}
	}

	/**
	 * Set Events for Audit Log Viewer.
	 */
	public function prepare_items() {
		$columns               = $this->get_columns();
		$hidden                = array();
		$sortable              = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		$query_events = $this->query_events();
		$this->items  = isset( $query_events['items'] ) ? $query_events['items'] : false;
		$total_items  = isset( $query_events['total_items'] ) ? $query_events['total_items'] : false;
		$per_page     = isset( $query_events['per_page'] ) ? $query_events['per_page'] : false;

		if ( ! $this->plugin->settings()->is_infinite_scroll() ) {
			$this->set_pagination_args(
				array(
					'total_items' => $total_items,
					'per_page'    => $per_page,
					'total_pages' => ceil( $total_items / $per_page ),
				)
			);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function single_row( $item ) {
		if ( 9999 === $item->alert_id ) {
			echo '<tr style="background-color: #D5E46E">';
			$this->single_row_columns( $item );
			echo '</tr>';
		} else {
			parent::single_row( $item );
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function print_column_headers( $with_id = true ) {
		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();

		$current_url = set_url_scheme( esc_url_raw( wp_unslash( $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] ) ) ); // phpcs:ignore
		$current_url = remove_query_arg( 'paged', $current_url );

		// Set order by query arg.
		if ( isset( $this->query_args->order_by ) ) {
			$current_orderby = $this->query_args->order_by;
		} else {
			$current_orderby = '';
		}

		if ( isset( $this->query_args->order ) && 'desc' === $this->query_args->order ) {
			$current_order = 'desc';
		} else {
			$current_order = 'asc';
		}

		if ( ! empty( $columns['cb'] ) ) {
			static $cb_counter = 1;
			$columns['cb']     = '<label class="screen-reader-text" for="cb-select-all-' . $cb_counter . '">' . __( 'Select All' ) . '</label>'
				. '<input id="cb-select-all-' . $cb_counter . '" type="checkbox" />';
			$cb_counter++;
		}

		foreach ( $columns as $column_key => $column_display_name ) {
			$class = array( 'manage-column', "column-$column_key" );

			if ( in_array( $column_key, $hidden, true ) ) {
				$class[] = 'hidden';
			}

			if ( 'cb' === $column_key ) {
				$class[] = 'check-column';
			} elseif ( in_array( $column_key, array( 'posts', 'comments', 'links' ), true ) ) {
				$class[] = 'num';
			}

			if ( $column_key === $primary ) {
				$class[] = 'column-primary';
			}

			if ( isset( $sortable[ $column_key ] ) ) {
				list( $orderby, $desc_first ) = $sortable[ $column_key ];

				if ( $current_orderby === $orderby ) {
					$order   = 'asc' === $current_order ? 'desc' : 'asc';
					$class[] = 'sorted';
					$class[] = $current_order;
				} else {
					$order   = $desc_first ? 'desc' : 'asc';
					$class[] = 'sortable';
					$class[] = $desc_first ? 'asc' : 'desc';
				}

				$column_display_name = '<a class="wsal-column-name" href="' . esc_url( add_query_arg( compact( 'orderby', 'order' ), $current_url ) ) . '"><span>' . $column_display_name . '</span><span class="sorting-indicator"></span></a>';
			}

			$tag   = ( 'cb' === $column_key ) ? 'td' : 'th';
			$scope = ( 'th' === $tag ) ? 'scope="col"' : '';
			$id    = $with_id ? "id='$column_key'" : '';

			if ( ! empty( $class ) ) {
				$class = "class='" . implode( ' ', $class ) . "'";
			}

			echo "<$tag $scope $id $class>"; // phpcs:ignore
			echo ! in_array( $column_key, array( 'code', 'data', 'site' ), true ) ? '<div class="wsal-filter-wrap">' : '';

			if ( $with_id ) {
				/**
				 * Action: `wsal_audit_log_column_header`
				 *
				 * Action hook to add search filters in the audit log
				 * column headers.
				 *
				 * @param string $column_key – Column key.
				 */
				do_action( 'wsal_audit_log_column_header', $column_key );
			}

			echo $column_display_name; // phpcs:ignore
			echo ! in_array( $column_key, array( 'code', 'data', 'site' ), true ) ? '</div>' : '';
			echo "</$tag>"; // phpcs:ignore
		}
	}

	/**
	 * Returns total events in the Audit Log.
	 *
	 * @return int
	 */
	public function get_total_items() {
		return isset( $this->_pagination_args['total_items'] ) ? $this->_pagination_args['total_items'] : false;
	}

	/**
	 * Query Events from WSAL DB.
	 *
	 * @since 3.3.1.1
	 *
	 * @param integer $paged - Page number.
	 * @return array
	 */
	public function query_events( $paged = 0 ) {

		// TO DO: Get rid of OccurrenceQuery and use the Occurrence Model.
		$query = new WSAL_Models_OccurrenceQuery();

		$bid = (int) $this->query_args->site_id;
		if ( $bid ) {
			$query->add_condition( 'site_id = %s ', $bid );
		}

		/**
		 * Hook: `wsal_auditlog_query`
		 *
		 * This hook is used to filter events query object.
		 * It is used to support search by filters.
		 *
		 * @see WSAL_SearchExtension()->__construct()
		 * @param WSAL_Models_OccurrenceQuery $query - Audit log events query object.
		 */
		$query = apply_filters( 'wsal_auditlog_query', $query );

		if ( ! $this->plugin->settings()->is_infinite_scroll() ) {
			$total_items = $query->get_adapter()->count( $query );
			$per_page    = $this->plugin->settings()->get_views_per_page();
			$offset      = ( $this->get_pagenum() - 1 ) * $per_page;
		} else {
			$total_items = false;
			$per_page    = apply_filters( 'wsal_infinite_scroll_events', 25 ); // Manually set per page events for infinite scroll.
			$offset      = ( max( 1, $paged ) - 1 ) * $per_page;
		}

		// Set query order arguments.
		$order_by = isset( $this->query_args->order_by ) ? $this->query_args->order_by : false;
		$order    = isset( $this->query_args->order ) ? $this->query_args->order : false;

		if ( ! $order_by ) {
			$query->add_order_by( 'created_on', true );
		} else {
			$is_descending = true;
			if ( $order && 'asc' === $order ) {
				$is_descending = false;
			}

			if ( 'code' === $order_by ) {
				/*
				 * Handle the 'code' (Severity) column sorting.
				 */
				$query->add_meta_join(); // Since LEFT JOIN clause causes the result values to duplicate.
				$query->add_condition( 'meta.name = %s', 'Severity' ); // A where condition is added to make sure that we're only requesting the relevant meta data rows from metadata table.
				$query->add_order_by( 'CASE WHEN meta.name = "Severity" THEN meta.value END', $is_descending );
			} elseif ( 'info' === $order_by ) {
				// for the info col we are ordering just by dates.
				$query->add_order_by( 'created_on', $is_descending );
			} else {
				$tmp = new WSAL_Models_Occurrence();
				// Making sure the field exists to order by.
				if ( isset( $tmp->{$order_by} ) ) {
					$query->add_order_by( $order_by, $is_descending );
				} else {
					$query->add_order_by( 'created_on', true );
				}
			}
		}

		$query->set_offset( $offset );  // Set query offset.
		$query->set_limit( $per_page ); // Set number of events per page.

		return array(
			'total_items' => $total_items,
			'per_page'    => $per_page,
			'items'       => $query->get_adapter()->execute_query( $query ),
		);
	}
}
