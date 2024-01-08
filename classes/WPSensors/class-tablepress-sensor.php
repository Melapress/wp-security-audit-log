<?php
/**
 * Custom Sensors for TablePress
 * Class file for alert manager.
 *
 * @since   1.0.0
 *
 * @package Wsal
 */

declare(strict_types=1);

namespace WSAL\WP_Sensors;

use WSAL\Controllers\Alert_Manager;
use WSAL\WP_Sensors\Helpers\TablePress_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\Plugin_Sensors\TablePress_Sensor' ) ) {
	/**
	 * Custom sensor for TablePress plugin.
	 *
	 * @since 4.6.0
	 */
	class TablePress_Sensor {
		/**
		 * Holds a cached value if the checked alert has recently fired.
		 *
		 * @var int
		 */
		private static $_old_column_count = null;

		/**
		 * Holds a cached value if the checked alert has recently fired.
		 *
		 * @var Object
		 */
		private static $_old_table = null;

		/**
		 * Holds a cached value if the checked alert has recently fired.
		 *
		 * @var mixed
		 */
		private static $_old_meta = null;

		/**
		 * Holds a cached value if the checked alert has recently fired.
		 *
		 * @var int
		 */
		private static $_old_row_count = null;

		/**
		 * Holds the table ID assigned to the table during import.
		 *
		 * @var int|null
		 */
		private static $imported_table_id = null;

		/**
		 * Holds the post ID assigned to the table during deketion.
		 *
		 * @var int|null
		 */
		private static $deleted_table_title = null;

		/**
		 * Hook events related to sensor.
		 *
		 * @since 1.0.0
		 */
		public static function init() {
			if ( TablePress_Helper::is_tablepress_active() ) {
				if ( is_user_logged_in() ) {
					// Gather up old data where we can.
					add_action( 'pre_post_update', array( __CLASS__, 'get_before_post_edit_data' ), 10, 2 );

					add_action( 'tablepress_event_added_table', array( __CLASS__, 'event_table_added' ) );

					add_action( 'deleted_post', array( __CLASS__, 'event_table_pre_deleted' ) );
					add_action( 'tablepress_event_deleted_table', array( __CLASS__, 'event_table_deleted' ) );

					add_action( 'tablepress_event_copied_table', array( __CLASS__, 'event_table_copied' ), 10, 2 );
					add_action( 'tablepress_event_changed_table_id', array( __CLASS__, 'event_table_id_change' ), 10, 2 );
					add_action( 'wp_insert_post', array( __CLASS__, 'event_table_imported' ) );
					add_action( 'post_updated', array( __CLASS__, 'event_table_updated' ), 10, 3 );
				}
			}
		}

		/**
		 * Loads all the plugin dependencies early.
		 *
		 * @return void
		 *
		 * @since 4.6.0
		 */
		public static function early_init() {
			add_filter(
				'wsal_event_objects',
				array( '\WSAL\WP_Sensors\Helpers\TablePress_Helper', 'wsal_tablepress_add_custom_event_objects' ),
				10,
				2
			);

			if ( TablePress_Helper::is_tablepress_active() ) {

				add_filter(
					'wsal_event_type_data',
					array( '\WSAL\WP_Sensors\Helpers\TablePress_Helper', 'wsal_tablepress_add_custom_event_type' ),
					10,
					2
				);
				add_filter(
					'wsal_ignored_custom_post_types',
					array( '\WSAL\WP_Sensors\Helpers\TablePress_Helper', 'wsal_tablepress_add_custom_ignored_cpt' )
				);

			}
		}


		/**
		 * Repoort changes to tables such as row changes, setting changes etc.
		 *
		 * @since 1.0.0
		 *
		 * @param int            $post_ID - Id of the post.
		 * @param object WP_Post $post_after - Post after object.
		 * @param object WP_Post $post_before - Post before object.
		 */
		public static function event_table_updated( $post_ID, $post_after, $post_before ) {
			if ( isset( $_POST['action'] ) && 'tablepress_save_table' == $_POST['action'] && isset( $_POST['tablepress'] ) && isset( $_POST['tablepress']['id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$editor_link = esc_url(
					add_query_arg(
						array(
							'table_id' => \sanitize_text_field( \wp_unslash( $_POST['tablepress']['id'] ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
							'action'   => 'edit',
						),
						admin_url( 'admin.php?page=tablepress' )
					)
				);

				$table_id = \sanitize_text_field( \wp_unslash( $_POST['tablepress']['id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

				$old_table_details = ( isset( self::$_old_meta['_tablepress_table_options'][0] ) ) ? json_decode( self::$_old_meta['_tablepress_table_options'][0], true ) : array();

				// Remove part we are not interested in.
				if ( isset( $old_table_details['last_editor'] ) ) {
					unset( $old_table_details['last_editor'] );
				}

				$new_table_options = ( isset( $_POST['tablepress']['options'] ) ) ? json_decode( \sanitize_text_field( wp_unslash( $_POST['tablepress']['options'] ) ), true ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing

				// Remove part we are not interested in.
				if ( isset( $new_table_options['last_editor'] ) ) {
					unset( $new_table_options['last_editor'] );
				}

				$changed      = array_diff_assoc( $new_table_options, $old_table_details );
				$bool_options = array( 'table_head', 'table_foot', 'alternating_row_colors', 'row_hover', 'use_datatables', 'print_name', 'print_description', 'datatables_sort', 'datatables_filter', 'datatables_paginate', 'datatables_lengthchange', 'datatables_info', 'datatables_scrollx' );
				$alert_needed = false;

				if ( ! class_exists( '\TablePress_Table_Model' ) ) {
					return;
				}

				$tablepress    = new \TablePress_Table_Model();
				$table_details = $tablepress->load( \sanitize_text_field( \wp_unslash( $_POST['tablepress']['id'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

				if ( $post_after->post_content != $post_before->post_content || $post_after->post_title != $post_before->post_title || $post_after->post_excerpt != $post_before->post_excerpt ) {
					$explode_to_rows   = explode( '],', $post_after->post_content );
					$number_of_rows    = count( $explode_to_rows );
					$number_of_columns = count( explode( ',', reset( $explode_to_rows ) ) );

					$alert_id     = 8905;
					$variables    = array(
						'table_name'  => $post_after->post_title,
						'table_id'    => \sanitize_text_field( \wp_unslash( $_POST['tablepress']['id'] ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
						'columns'     => ( $number_of_columns ) ? intval( $number_of_columns ) : 0,
						'rows'        => ( isset( $number_of_rows ) ) ? intval( $number_of_rows ) : 0,
						'old_columns' => self::$_old_column_count,
						'old_rows'    => self::$_old_row_count,
						'EditorLink'  => $editor_link,
					);
					$alert_needed = true;
				}

				$updated_name = '';
				// Detect and report setting changes.
				if ( ! empty( $changed ) ) {
					foreach ( $changed as $updated_table_setting => $value ) {
						// Tidy up name to something useful.
						if ( 'table_foot' === $updated_table_setting ) {
							$updated_name = esc_html__( 'The last row of the table is the table footer', 'wp-security-audit-log' );
						} elseif ( 'table_head' === $updated_table_setting ) {
							$updated_name = esc_html__( 'The first row of the table is the table header', 'wp-security-audit-log' );
						} elseif ( 'alternating_row_colors' === $updated_table_setting ) {
							$updated_name = esc_html__( 'The background colors of consecutive rows shall alternate', 'wp-security-audit-log' );
						} elseif ( 'row_hover' === $updated_table_setting ) {
							$updated_name = esc_html__( 'Highlight a row while the mouse cursor hovers above it by changing its background color', 'wp-security-audit-log' );
						} elseif ( 'use_datatables' === $updated_table_setting ) {
							$updated_name = esc_html__( 'Use the following features of the DataTables JavaScript library with this table', 'wp-security-audit-log' );
						} elseif ( 'print_name' === $updated_table_setting ) {
							$updated_name = esc_html__( 'Show the table name', 'wp-security-audit-log' );
						} elseif ( 'print_description' === $updated_table_setting ) {
							$updated_name = esc_html__( 'Show the table description', 'wp-security-audit-log' );
						} elseif ( 'datatables_sort' === $updated_table_setting ) {
							$updated_name = esc_html__( 'Enable sorting of the table by the visitor.', 'wp-security-audit-log' );
						} elseif ( 'datatables_filter' === $updated_table_setting ) {
							$updated_name = esc_html__( 'Enable the visitor to filter or search the table.' );
						} elseif ( 'datatables_paginate' === $updated_table_setting ) {
							$updated_name = esc_html__( 'Enable pagination of the table', 'wp-security-audit-log' );
						} elseif ( 'datatables_lengthchange' === $updated_table_setting ) {
							$updated_name = esc_html__( 'Allow the visitor to change the number of rows shown when using pagination.', 'wp-security-audit-log' );
						} elseif ( 'datatables_info' === $updated_table_setting ) {
							$updated_name = esc_html__( 'Enable the table information display', 'wp-security-audit-log' );
						} elseif ( 'datatables_scrollx' === $updated_table_setting ) {
							$updated_name = esc_html__( 'Enable horizontal scrolling', 'wp-security-audit-log' );
						} elseif ( 'print_name_position' === $updated_table_setting ) {
							$updated_name = esc_html__( 'Table name position', 'wp-security-audit-log' );
						} elseif ( 'print_description_position' === $updated_table_setting ) {
							$updated_name = esc_html__( 'Table description position', 'wp-security-audit-log' );
						} elseif ( 'extra_css_classes' === $updated_table_setting ) {
							$updated_name = esc_html__( 'Extra CSS Classes', 'wp-security-audit-log' );
						} elseif ( 'datatables_paginate_entries' === $updated_table_setting ) {
							$updated_name = esc_html__( 'Table pagignation length', 'wp-security-audit-log' );
						} elseif ( 'datatables_custom_commands' === $updated_table_setting ) {
							$updated_name = esc_html__( 'Custom table commands', 'wp-security-audit-log' );
						}

						$alert_id = 8908;
						if ( in_array( $updated_table_setting, $bool_options, true ) ) {
							$value = ( empty( $value ) ) ? 'disabled' : 'enabled';
						}
						if ( in_array( $updated_table_setting, $bool_options, true ) ) {
							$old_value = ( empty( $new_table_options[ $updated_table_setting ] ) ) ? 'enabled' : 'disabled';
						} else {
							$old_value = $old_table_details[ $updated_table_setting ];
						}

						$variables = array(
							'table_name'  => sanitize_text_field( $table_details['name'] ),
							'table_id'    => $table_id,
							'option_name' => $updated_name,
							'new_value'   => $value,
							'old_value'   => $old_value,
							'EventType'   => ( $new_table_options[ $updated_table_setting ] ) ? 'enabled' : 'disabled',
							'EditorLink'  => $editor_link,
						);
						Alert_Manager::trigger_event( $alert_id, $variables );
					}
				}

				// Detect new or removed columns.
				if ( isset( $_POST['tablepress']['number']['columns'] ) && intval( $_POST['tablepress']['number']['columns'] ) != self::$_old_column_count ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
					$event_type   = ( self::$_old_column_count > intval( $_POST['tablepress']['number']['columns'] ) ) ? 'removed' : 'added'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
					$alert_id     = 8907;
					$variables    = array(
						'table_name' => sanitize_text_field( $table_details['name'] ),
						'table_id'   => $table_id,
						'count'      => ( isset( $_POST['tablepress']['number']['columns'] ) ) ? intval( $_POST['tablepress']['number']['columns'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Missing
						'old_count'  => self::$_old_column_count,
						'EventType'  => $event_type,
						'EditorLink' => $editor_link,
					);
					$alert_needed = true;

					// Detect new or removed rows.
				} elseif ( isset( $_POST['tablepress']['number']['rows'] ) && intval( $_POST['tablepress']['number']['rows'] ) != self::$_old_row_count ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
					$event_type   = ( self::$_old_row_count > intval( $_POST['tablepress']['number']['rows'] ) ) ? 'removed' : 'added'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
					$alert_id     = 8906;
					$variables    = array(
						'table_name' => sanitize_text_field( $table_details['name'] ),
						'table_id'   => $table_id,
						'count'      => ( isset( $_POST['tablepress']['number']['rows'] ) ) ? intval( $_POST['tablepress']['number']['rows'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Missing
						'old_count'  => self::$_old_row_count,
						'EventType'  => $event_type,
						'EditorLink' => $editor_link,
					);
					$alert_needed = true;
				}

				if ( $alert_needed ) {
					// Do alert.
					Alert_Manager::trigger_event( $alert_id, $variables );
				}

				return;
			}
		}

		/**
		 * Get Post Data.
		 *
		 * Collect old post data before post update event.
		 *
		 * @since 1.0.0
		 *
		 * @param int $table_id - Table ID.
		 */
		public static function get_before_post_edit_data( $table_id ) {
			$table_id   = absint( $table_id ); // Making sure that the post id is integer.
			$table      = get_post( $table_id ); // Get post.
			$table_meta = get_post_meta( $table_id ); // Get post.

			$explode_to_rows   = explode( '],', $table->post_content );
			$number_of_rows    = count( $explode_to_rows );
			$number_of_columns = count( explode( ',', reset( $explode_to_rows ) ) );

			// If post exists.
			if ( ! empty( $table ) ) {
				self::$_old_table        = $table;
				self::$_old_row_count    = $number_of_rows;
				self::$_old_column_count = $number_of_columns;
				self::$_old_meta         = $table_meta;
			}
		}

		/**
		 * Report new Tables being created.
		 *
		 * Collect old post data before post update event.
		 *
		 * @since 1.0.0
		 *
		 * @param int $table_id - Table ID.
		 */
		public static function event_table_added( $table_id ) {
			$editor_link = esc_url(
				add_query_arg(
					array(
						'table_id' => $table_id,
						'action'   => 'edit',
					),
					admin_url( 'admin.php?page=tablepress' )
				)
			);

			$event_id = ( isset( $_POST['action'] ) && 'tablepress_import' === $_POST['action'] ) ? 8903 : 8900; // phpcs:ignore WordPress.Security.NonceVerification.Missing

			$variables = array(
				'table_name' => sanitize_text_field( get_the_title( self::$imported_table_id ) ),
				'table_id'   => $table_id,
				'columns'    => ( isset( $_POST['table'] ) ) ? intval( $_POST['table']['columns'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Missing
				'rows'       => ( isset( $_POST['table'] ) ) ? intval( $_POST['table']['rows'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Missing
				'EditorLink' => $editor_link,
			);

			Alert_Manager::trigger_event( $event_id, $variables );
		}

		/**
		 * Grab correct table name before deletion.
		 *
		 * @since 1.0.0
		 *
		 * @param int $table_id - Table ID.
		 */
		public static function event_table_pre_deleted( $table_id ) {
			self::$deleted_table_title = sanitize_text_field( get_the_title( $table_id ) );
		}

		/**
		 * Report table deletions.
		 *
		 * Collect old post data before post update event.
		 *
		 * @since 1.0.0
		 *
		 * @param int $table_id - Table ID.
		 */
		public static function event_table_deleted( $table_id ) {
			$variables = array(
				'table_name' => self::$deleted_table_title,
				'table_id'   => $table_id,
			);

			Alert_Manager::trigger_event( 8901, $variables );
		}

		/**
		 * Report duplication of a table.
		 *
		 * Collect old post data before post update event.
		 *
		 * @since 1.0.0
		 *
		 * @param int $new_table_id - Table ID.
		 * @param int $table_id     - Original Table ID.
		 */
		public static function event_table_copied( $new_table_id, $table_id ) {
			$editor_link = esc_url(
				add_query_arg(
					array(
						'table_id' => $new_table_id,
						'action'   => 'edit',
					),
					admin_url( 'admin.php?page=tablepress' )
				)
			);

			if ( ! class_exists( 'TablePress_Table_Model' ) ) {
				return;
			}

			$tablepress = new \TablePress_Table_Model();
			$old_table  = $tablepress->load( $table_id );
			$new_table  = $tablepress->load( $new_table_id );

			$variables = array(
				'table_name'     => sanitize_text_field( $old_table['name'] ),
				'new_table_name' => sanitize_text_field( $new_table['name'] ),
				'table_id'       => $new_table_id,
				'EditorLink'     => $editor_link,
			);

			Alert_Manager::trigger_event( 8902, $variables );
		}

		/**
		 * Report change in a Table's ID.
		 *
		 * Collect old post data before post update event.
		 *
		 * @since 1.0.0
		 *
		 * @param int $new_id - Table ID.
		 * @param int $old_id - Old Table ID.
		 */
		public static function event_table_id_change( $new_id, $old_id ) {
			$editor_link = esc_url(
				add_query_arg(
					array(
						'table_id' => $new_id,
						'action'   => 'edit',
					),
					admin_url( 'admin.php?page=tablepress' )
				)
			);

			if ( ! class_exists( '\TablePress_Table_Model' ) ) {
				return;
			}

			$tablepress    = new \TablePress_Table_Model();
			$table_details = $tablepress->load( $new_id );

			$variables = array(
				'table_name'   => sanitize_text_field( $table_details['name'] ),
				'old_table_id' => $old_id,
				'table_id'     => $new_id,
				'EditorLink'   => $editor_link,
			);

			Alert_Manager::trigger_event( 8904, $variables );
		}

		/**
		 * Detect table changes.
		 *
		 * Collect old post data before post update event.
		 *
		 * @since 1.0.0
		 *
		 * @param int $table_id - Table ID.
		 */
		public static function event_table_saved( $table_id ) {
		}

		/**
		 * Detect imported tabled.
		 *
		 * @since 1.0.0
		 *
		 * @param int $table_id - Table ID.
		 */
		public static function event_table_imported( $table_id ) {
			self::$imported_table_id = $table_id;
		}
	}
}
