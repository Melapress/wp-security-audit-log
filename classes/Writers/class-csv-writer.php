<?php
/**
 * Writer: CSV writer.
 *
 * CSV file writer class.
 *
 * @since 4.6.1
 *
 * @package   wsal
 * @subpackage writers
 * @author Stoil Dobrev <stoil@melapress.com>
 */

declare(strict_types=1);

namespace WSAL\Writers;

use WSAL\Helpers\WP_Helper;
use WSAL\Helpers\User_Helper;
use WSAL\Controllers\Connection;
use WSAL\Helpers\Settings_Helper;
use WSAL\Entities\Occurrences_Entity;
use WSAL\ListAdminEvents\List_Events;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\Writers\CSV_Writer' ) ) {
	/**
	 * Provides logging functionality for the comments.
	 *
	 * @since 4.6.1
	 */
	class CSV_Writer {
		/**
		 * Holds the array with the events to store as CSV
		 *
		 * @var array
		 *
		 * @since 4.6.1
		 */
		private static $events = array();

		/**
		 * Holds the full file name for the CSV file
		 *
		 * @var string
		 *
		 * @since 4.6.1
		 */
		private static $file_name = '';

		/**
		 * Holds the columns for the CSV file
		 *
		 * @var array
		 *
		 * @since 5.0.0
		 */
		private static $columns_header = array();

		/**
		 * Inits the class hooks if necessary.
		 *
		 * @return void
		 *
		 * @since 5.0.0
		 */
		public static function init() {
			if ( \class_exists( '\WSAL\Extensions\Views\Reports', false ) ) {
				\add_action( 'wp_ajax_wsal_report_download', array( '\WSAL\Extensions\Views\Reports', 'process_report_download' ) );
			}
		}

		/**
		 * Writes the CSV file to the specified location.
		 *
		 * @param int   $step - Current step.
		 * @param array $data - If local variable is not set, data array could be passed to that method.
		 *
		 * @return void
		 *
		 * @since 4.6.1
		 */
		public static function write_csv( int $step, array &$data = array() ) {

			if ( 1 === $step ) {
				$output = fopen( self::get_file(), 'w' );
			} else {
				$output = fopen( self::get_file(), 'a+' );
			}
			if ( 1 === $step ) {
				fputcsv( $output, self::prepare_header() );
			}
			$enclosure            = '"';
			$csv_export_separator = ',';

			if ( ! empty( $data ) ) {

				foreach ( $data as $row ) {
					$current_row = array();
					foreach ( array_keys( self::prepare_header() ) as $column_name ) {
						if ( 'message' == $column_name ) {
							$html = htmlspecialchars_decode(
								\trim(
									\strip_tags(
										str_replace(
											array( '<br />', '<br>' ),
											"\n",
											wp_specialchars_decode( List_events::format_column_value( $row, $column_name ) )
										),
										array( '<a>' )
									)
								)
							);

							$split_text = explode( '<a', $html );

							$final_text = '';

							foreach ( $split_text as $line ) {
								if ( str_starts_with( trim( $line ), 'href' ) ) {
									$a_info = self::link_extractor( '<a' . $line );

									if ( '#' !== $a_info[0][0] ) {
										$final_text .= $a_info[0][1] . ': ' . $a_info[0][0];
									}
								} else {
									$final_text .= $line;
								}
							}
							$current_row[] = $final_text;
						} else {
							$current_row[] = htmlspecialchars_decode(
								\trim(
									\strip_tags(
										str_replace(
											array( '<br />', '<br>' ),
											"\n",
											wp_specialchars_decode( List_events::format_column_value( $row, $column_name ) )
										)
									)
								)
							);
						}
					}
					fputcsv( $output, $current_row, $csv_export_separator, $enclosure );
				}
			} else {

				foreach ( self::$events as $row ) {
					$current_row = array();
					foreach ( array_keys( self::prepare_header() ) as $column_name ) {
						if ( 'mesg' == $column_name ) {
							$html = htmlspecialchars_decode(
								\trim(
									\strip_tags(
										str_replace( array( '<br />', '<br>', '<br/>', '</br>' ), "\n", List_events::format_column_value( $row, $column_name ) ),
										array( 'a' )
									)
								)
							);

							$split_text = explode( '<a', $html );

							$final_text = '';

							foreach ( $split_text as $line ) {
								if ( str_starts_with( trim( $line ), 'href' ) ) {
									$a_info = self::link_extractor( '<a' . $line );

									if ( '#' !== $a_info[0][0] ) {
										$final_text .= $a_info[0][1] . ': ' . $a_info[0][0];
									} else {
										$final_text .= "\n";
									}
								} else {
									$final_text .= $line;
								}
							}
							$current_row[] = $final_text;
						} else {
							$current_row[] = htmlspecialchars_decode(
								\trim(
									\strip_tags(
										str_replace( array( '<br />', '<br>', '<br/>', '</br>' ), "\n", List_events::format_column_value( $row, $column_name ) )
									)
								)
							);
						}
					}
					fputcsv( $output, $current_row, $csv_export_separator, $enclosure );
				}
			}

			fclose( $output );
		}

		/**
		 * Extract link info from a given text
		 *
		 * @param string $html - The text to parse.
		 *
		 * @return array
		 *
		 * @since 5.0.0
		 */
		private static function link_extractor( $html ) {
			$link_array = array();
			if ( preg_match_all( '/<a\s+.*?href=[\"\']?([^\"\' >]*)[\"\']?[^>]*>(.*?)<\/a>/i', $html, $matches, PREG_SET_ORDER ) ) {
				foreach ( $matches as $match ) {
					array_push( $link_array, array( $match[1], $match[2] ) );
				}
			}

			return $link_array;
		}

		/**
		 * Accepts the AJAX requests and checks the params then calls the main function of the class responsible for writing the file
		 *
		 * @return void
		 *
		 * @since 4.6.1
		 */
		public static function write_csv_ajax() {
			if ( ! array_key_exists( 'nonce', $_POST ) || ! wp_verify_nonce( $_POST['nonce'], 'wsal-export-csv-nonce' ) ) { // phpcs:ignore
				wp_send_json_error( esc_html_e( 'nonce is not provided or incorrect', 'wp-security-audit-log' ) );
				die();
			}

			if ( ! array_key_exists( 'query', $_POST ) ) {
				wp_send_json_error( esc_html_e( 'query is not provided or incorrect', 'wp-security-audit-log' ) );
				die();
			} else {
				$query = unserialize( \base64_decode( \sanitize_text_field( \wp_unslash( $_POST['query'] ) ) ) );
			}

			if ( ! array_key_exists( 'order', $_POST ) ) {
				wp_send_json_error( esc_html_e( 'order is not provided or incorrect', 'wp-security-audit-log' ) );
				die();
			}

			if ( ! array_key_exists( 'step', $_POST ) ) {
				wp_send_json_error( esc_html_e( 'step is not provided or incorrect', 'wp-security-audit-log' ) );
				die();
			}

			if ( ! array_key_exists( 'records', $_POST ) ) {
				wp_send_json_error( esc_html_e( 'records is not provided or incorrect', 'wp-security-audit-log' ) );
				die();
			}

			self::$events = self::query_table( $query, \wp_unslash( $_POST['order'] ), (int) \wp_unslash( $_POST['step'] ), (int) \wp_unslash( $_POST['records'] ) ); // phpcs:ignore -- WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			$exports_path = Settings_Helper::get_working_dir_path_static( 'exports' );
			if ( $exports_path instanceof \WP_Error ) {
				wp_send_json_error( $exports_path->get_error_message() );
				die();
			}

			self::set_file( $exports_path . 'exports-user' . User_Helper::get_user()->ID . '.csv' );

			$url = add_query_arg(
				array(
					'action' => 'wsal_report_download',
					'f'      => base64_encode( \basename( self::$file_name ) ),
					'ctype'  => 'csv',
					'dir'    => 'exports',
					'nonce'  => wp_create_nonce( 'wpsal_reporting_nonce_action' ),
				),
				admin_url( 'admin-ajax.php' )
			);

			if ( ! empty( self::$events ) ) {
				$step = (int) \wp_unslash( $_POST['step'] ); // phpcs:ignore -- WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$progress = self::write_csv( $step );
				wp_send_json_success(
					array(
						'step'      => ++$step,
						'file_name' => \basename( self::$file_name ),
						'url'       => $url,
					)
				);
			} else {

				wp_send_json_success(
					array(
						'step'      => 0,
						'file_name' => \basename( self::$file_name ),
						'url'       => $url,
					)
				);
			}
		}

		/**
		 * Sets the writer file name.
		 *
		 * @param string $file - The name of the writer file to be used.
		 *
		 * @return void
		 *
		 * @since 5.0.0
		 */
		public static function set_file( string $file ) {
			self::$file_name = $file;
		}

		/**
		 * Returns the writer file name.
		 *
		 * @return string
		 *
		 * @since 5.0.0
		 */
		public static function get_file(): string {
			return (string) self::$file_name;
		}

		/**
		 * Sets the header_columns property to be used in csv generation
		 *
		 * @param array $columns - The column names.
		 *
		 * @return void
		 *
		 * @since 5.0.0
		 */
		public static function set_header_columns( array $columns ) {
			self::$columns_header = $columns;
		}

		/**
		 * Sends a query to the database and returns the results.
		 *
		 * @param array $query - The query array.
		 * @param array $order - The order used for the query.
		 * @param int   $step - The current step of the extracting data (limit).
		 * @param int   $per_page - How many records to be extracted in one hop.
		 *
		 * @return array
		 *
		 * @since 4.6.1
		 */
		private static function query_table( array $query, array $order, int $step = 0, int $per_page = 0 ) {
			$wsal_db = null;
			if ( Settings_Helper::is_archiving_enabled() ) {
				// Switch to Archive DB.
				$selected_db = WP_Helper::get_transient( 'wsal_wp_selected_db' );
				if ( $selected_db && 'archive' === $selected_db ) {
					if ( class_exists( '\WSAL_Extension_Manager' ) ) {
						if ( class_exists( '\WSAL_Ext_Plugin' ) ) {
							$connection_name = Settings_Helper::get_option_value( 'archive-connection' );

							$wsal_db = Connection::get_connection( $connection_name );
						}
					}
				}
			}

			if ( 1 === $step ) {
				$limit = array( $per_page );
			} else {
				$limit = array( $step * $per_page, $per_page );
			}

			$events = Occurrences_Entity::build_query(
				array(),
				$query,
				$order,
				$limit,
				array(),
				$wsal_db
			);

			$events = Occurrences_Entity::get_multi_meta_array( $events, $wsal_db );

			return $events;
		}

		/**
		 * Prepares the header columns for the CSV file
		 *
		 * @return array
		 *
		 * @since 4.6.1
		 */
		private static function prepare_header() {
			if ( empty( self::$columns_header ) ) {
				$all_columns = List_Events::manage_columns( array() );
				unset( $all_columns['cb'] );
				unset( $all_columns['data'] );
				$hidden_columns = List_Events::get_hidden_columns();

				if ( \is_array( $hidden_columns ) && ! empty( $hidden_columns ) ) {
					self::$columns_header = array_diff_key( $all_columns, array_flip( $hidden_columns ) );
				} else {
					self::$columns_header = $all_columns;
				}
			}

			return self::$columns_header;
		}
	}
}
