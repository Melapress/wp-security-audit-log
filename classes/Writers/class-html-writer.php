<?php
/**
 * Writer: HTML writer.
 *
 * HTML file writer class.
 *
 * @since 5.0.0
 *
 * @package   wsal
 * @subpackage writers
 * @author Stoil Dobrev <stoil@melapress.com>
 */

declare(strict_types=1);

namespace WSAL\Writers;

use WSAL\Helpers\User_Helper;
use WSAL\Helpers\Settings_Helper;
use WSAL\Extensions\Views\Reports;
use WSAL\Helpers\DateTime_Formatter_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\Writers\HTML_Writer' ) ) {
	/**
	 * Provides logging functionality for the comments.
	 *
	 * @since 5.0.0
	 */
	class HTML_Writer {

		/**
		 * Holds the full file name for the HTML file
		 *
		 * @var string
		 *
		 * @since 5.0.0
		 */
		private static $file_name = '';

		/**
		 * Holds the columns for the HTML file
		 *
		 * @var array
		 *
		 * @since 5.0.0
		 */
		private static $columns_header = array();

		/**
		 * Writes the HTML file to the specified location.
		 *
		 * @param int   $step - Current step.
		 * @param array $data - If local variable is not set, data array could be passed to that method.
		 * @param array $report_filters - Report criteria filters array.
		 *
		 * @return void
		 *
		 * @since 5.0.0
		 */
		public static function write_html( int $step, array &$data = array(), array $report_filters = array() ) {

			if ( 1 === $step ) {
				$output = fopen( self::get_file(), 'w' );
			} else {
				$output = fopen( self::get_file(), 'a+' );
			}
			if ( 1 === $step ) {
				self::prepare_header( '', $output, $report_filters );
			}

			if ( ! empty( $data ) ) {

				foreach ( $data as $i => $alert ) {
					$date = ( isset( $alert['created_on'] ) ) ? $alert['created_on'] : '';
					$r    = '<tr>';

					$processed_row_data = array();
					foreach ( self::$columns_header as $key => $label ) {
						$value = array_key_exists( $key, $alert ) ? $alert[ $key ] : '';
						if ( 'timestamp' === $key ) {
							$value = $date;
						}
						$processed_row_data[ $key ] = $value;
					}

					foreach ( $processed_row_data as $key => $label ) {
						$cell_styling = 'padding: 16px 7px;';
						if ( 'alert_id' === $key ) {
							$cell_styling .= ' text-align: center; font-weight: 700;';
						} elseif ( 'severity' === $key ) {
							$cell_styling = '" class="tooltip" title="' . $label;
							$label        = '<span title=""><svg class="icon"><use xlink:href="#' . \strtolower( $label ) . '-ico"></use></svg></span>';
						} elseif ( 'user_displayname' === $key ) {
							$cell_styling .= ' min-width: 100px;';
						} elseif ( 'message' === $key ) {
							$cell_styling .= ' min-width: 400px; word-break: break-all; line-height: 1.5;';
						}

						$r .= '<td style="' . $cell_styling . '">' . $label . '</td>';
					}

					$r .= '</tr>';
					fwrite( $output, $r );
				}
			}

			fclose( $output );
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
		 * Sets the header_columns property to be used in HTML generation
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
		 * Set the file footer - closes the HTML output.
		 *
		 * @return void
		 *
		 * @since 5.0.0
		 */
		public static function set_footer() {
			$output = fopen( self::get_file(), 'a+' );

			fwrite( $output, '</tbody></table>	</div><small><p>WP Activity Log is supported and developed by Melapress.</p></small></body></html>' );

			fclose( $output );
		}

		/**
		 * Prepares the header columns for the HTML file
		 *
		 * @param string   $title - The title of the report.
		 * @param resource $stream - The file stream to write to.
		 * @param array    $report_filters - The report filters criteria.

		 * @return void
		 *
		 * @since 5.0.0
		 */
		private static function prepare_header( string $title, $stream, array $report_filters = array() ) {
			fwrite( $stream, self::build_report_header( $report_filters ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
			fwrite( $stream, '<h3 style="font-size: 20px; margin: 25px 0;">' . $title . '</h3>' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
			fwrite( $stream, '<table>' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

			$header  = '<thead>';
			$header .= '<tr>';
			foreach ( self::$columns_header as $item ) {
				$header .= '<th>' . $item . '</th>';
			}
			$header .= '</tr>';
			$header .= '</thead>';
			fwrite( $stream, $header ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

			fwrite( $stream, '<tbody>' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		}

		/**
		 * Generate the HTML head of the Report.
		 *
		 * @param array $report_data - The report data array with values needed for generating the custom part of the report, and filters used (so we can show the report header along with the criteria used to generate it).
		 *
		 * @return string
		 *
		 * @since 5.0.0
		 */
		private static function build_report_header( $report_data ): string {
			$str = '<html lang="en">

			<head>
				<meta charset="UTF-8">
				<title>Periodic report | WP Activity Log</title>
				<meta content="Periodic report generated from WP Activity Log." name="description">
				<meta content="width=device-width, initial-scale=1" name="viewport">
				<style>
					body {
						background: #f2f3f4;
						color: #1a3060;
						font-family: "Helvetica Neue", Helvetica, Arial, "Lucida Grande", sans-serif;
					}
					h1 {
						border-top: 2px solid #D0D4DC;
						font-size: 18px;
						letter-spacing: 1px;
						opacity: .6;
						text-align: center;
						text-transform: uppercase;
					}
					p {
						text-align: center;
					}
					p small {
						font-size: 12px;
						opacity: .6;
					}
					table {
						border-collapse: separate;
						border-spacing: 0;
						font-size: 12px;
						line-height: 20px;
						overflow: auto;
						width: auto;
					}
					th,
					td {
						padding: 6px 12px;
						white-space: nowrap;
					}
					th {
						background: #020e26;
						color: #fff;
						text-align: left;
						white-space: nowrap;
					}
					td {
						border-bottom: 1px solid #D0D4DC;
						border-right: 1px solid #D0D4DC;
					}
					td:first-child {
						border-left: 1px solid #D0D4DC;
					}
					tr:nth-child(even) td {
						background: #e8eaee;
					}
					tr:last-child td:first-child {
						border-bottom-left-radius: 6px;
					}
					tr:last-child td:last-child {
						border-bottom-right-radius: 6px;
					}

					div:not(#section-2) tr td:last-child {
						min-width: 320px;
						white-space: normal;
					}
					
					/* Refinements */
					#section-1,
					#section-2,
					#section-3 {
						margin-bottom: 16px;
					}
					#section-1 {
						width: 200px;
						margin: 16px auto;
					}
					@media (min-width: 540px) {
						#section-1 {
							float: right;
							margin: 16px;
						}
					}
					#section-2 tr:first-child td:last-child {
						border-top-right-radius: 6px;
						border-top: 1px solid #D0D4DC;
					}
					#section-2 tr:first-child th:first-child {
						border-top-left-radius: 6px;
					}
					#section-2 tr:first-child th:last-child {
						border-top-right-radius: 6px;
					}

					/* Fixed Headers */
					thead th {
						position: sticky;
						top: 0;
						z-index: 2;
					}
					body {
						padding-bottom: 90vh;
					}

					/* Highlight rows */
					tr:hover,
					tr:hover:nth-child(even) td {
						background: #FCF6B0;
					}
					
					/* Severity icons */
					td[title*="Low"] svg.icon,
					td[title*="Medium"] svg.icon {
						height: 31px;
						width: 34.34px;
					}
					td[title*="High"] svg.icon,
					td[title*="Critical"] svg.icon,
					td[title*="Informational"] svg.icon {
						height: 31px;
						width: 31px;
					}
					.hidden {
						display: none;
					}
					.tooltip {
						text-align: center;
					}
					.tooltip:hover:after{
						border-radius: 4px;
						content: attr(title);
						padding: 6px 12px;
						position: absolute;
						color:#FFF;
					}
					td[title*="Informational"].tooltip:hover:after{
						background: #8B572A;
					}
					td[title*="Low"].tooltip:hover:after{
						background: #1BC31A;
					}
					td[title*="Medium"].tooltip:hover:after {
						background: #F5A623;
					}
					td[title*="High"].tooltip:hover:after{
						background: #F57823;
					}
					td[title*="Critical"].tooltip:hover:after{
						background: #C52F2E;
					}
							
				</style>
			</head>
			<body>
				<svg width="0" height="0" class="hidden">
				<symbol fill="none" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 31 31" id="informational-ico">
				<path fill-rule="evenodd" clip-rule="evenodd" d="M0 15.5C0 6.94 6.94 0 15.5 0C24.06 0 31 6.94 31 15.5C31 24.06 24.06 31 15.5 31C6.94 31 0 24.06 0 15.5ZM3.5 15.5C3.5 22.12 8.88 27.5 15.5 27.5C22.12 27.5 27.5 22.12 27.5 15.5C27.5 8.88 22.12 3.5 15.5 3.5C8.88 3.5 3.5 8.88 3.5 15.5ZM15.5 10.84C16.5107 10.84 17.33 10.0207 17.33 9.00999C17.33 7.99931 16.5107 7.17999 15.5 7.17999C14.4893 7.17999 13.67 7.99931 13.67 9.00999C13.67 10.0207 14.4893 10.84 15.5 10.84ZM13.67 13.14V23.82H17.33V13.14H13.67Z" fill="#8B572A"></path>
				</symbol>
				<symbol fill="none" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 35 31" id="low-ico">
				<path fill-rule="evenodd" clip-rule="evenodd" d="M33.6876 23.83L21.3076 2.39C20.3876 0.8 18.7776 0 17.1676 0C15.5576 0 13.9476 0.8 13.0276 2.39L0.647592 23.83C-1.19241 27.02 1.10759 31 4.78759 31H29.5476C33.2276 31 35.5276 27.02 33.6876 23.83ZM30.6476 26.86C30.5076 27.1 30.1776 27.5 29.5376 27.5H4.78759C4.14759 27.5 3.81759 27.1 3.67759 26.86C3.53759 26.62 3.35759 26.14 3.67759 25.58L16.0576 4.14C16.3776 3.58 16.8876 3.5 17.1676 3.5C17.4476 3.5 17.9576 3.58 18.2776 4.14L30.6576 25.58C30.9776 26.14 30.7976 26.62 30.6576 26.86H30.6476ZM17.1676 18.86C18.1776 18.86 18.9976 18.04 18.9976 17.03V10.01C18.9976 8.99999 18.1776 8.17999 17.1676 8.17999C16.1576 8.17999 15.3376 8.99999 15.3376 10.01V17.03C15.3376 18.04 16.1576 18.86 17.1676 18.86ZM18.9976 22.99C18.9976 24.0007 18.1783 24.82 17.1676 24.82C16.1569 24.82 15.3376 24.0007 15.3376 22.99C15.3376 21.9793 16.1569 21.16 17.1676 21.16C18.1783 21.16 18.9976 21.9793 18.9976 22.99Z" fill="#1BC31A"></path>
				</symbol>
				<symbol fill="none" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 35 31" id="medium-ico">
				<path d="M33.6876 23.83L21.3076 2.39C20.3876 0.8 18.7776 0 17.1676 0C15.5576 0 13.9476 0.8 13.0276 2.39L0.647592 23.83C-1.19241 27.02 1.10759 31 4.78759 31H29.5476C33.2276 31 35.5276 27.02 33.6876 23.83ZM15.3376 10.01C15.3376 9 16.1576 8.18 17.1676 8.18C18.1776 8.18 18.9976 9 18.9976 10.01V17.03C18.9976 18.04 18.1776 18.86 17.1676 18.86C16.1576 18.86 15.3376 18.04 15.3376 17.03V10.01ZM17.1676 24.82C16.1576 24.82 15.3376 24 15.3376 22.99C15.3376 21.98 16.1576 21.16 17.1676 21.16C18.1776 21.16 18.9976 21.98 18.9976 22.99C18.9976 24 18.1776 24.82 17.1676 24.82Z" fill="#F5A623"></path>
				</symbol>
				<symbol fill="none" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 31 31" id="high-ico">
					<path fill="#F57823" fill-rule="evenodd" d="M0 15.5C0 6.94 6.94 0 15.5 0 24.06 0 31 6.94 31 15.5 31 24.06 24.06 31 15.5 31 6.94 31 0 24.06 0 15.5Zm3.5 0c0 6.62 5.38 12 12 12s12-5.38 12-12-5.38-12-12-12-12 5.38-12 12Zm12.33 2.36c1.01 0 1.83-.82 1.83-1.83V9.01a1.83 1.83 0 0 0-3.66 0v7.02c0 1.01.82 1.83 1.83 1.83Zm0 5.96a1.83 1.83 0 1 0 0-3.66 1.83 1.83 0 0 0 0 3.66Z" clip-rule="evenodd"/>
				</symbol>
				<symbol fill="none" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 31 31" id="critical-ico">
				<path fill="#C52F2E" fill-rule="evenodd" d="M0 15.5C0 6.94 6.94 0 15.5 0 24.06 0 31 6.94 31 15.5 31 24.06 24.06 31 15.5 31 6.94 31 0 24.06 0 15.5Zm15.83 2.36c1.01 0 1.83-.82 1.83-1.83V9.01a1.83 1.83 0 0 0-3.66 0v7.02c0 1.01.82 1.83 1.83 1.83Zm0 5.96a1.83 1.83 0 1 0 0-3.66 1.83 1.83 0 0 0 0 3.66Z" clip-rule="evenodd"/>
				</symbol>
			</svg>';

			if ( \class_exists( 'WSAL\Extensions\Views\Reports' ) ) {
				$white_settings = Settings_Helper::get_option_value( Reports::REPORT_WHITE_LABEL_SETTINGS_NAME );
				$logo_link      = isset( $white_settings['logo_url'] ) ? (string) $white_settings['logo_url'] : '';

				$logo_src = isset( $white_settings['logo'] ) ? (string) $white_settings['logo'] : '';
				if ( 0 === strlen( $logo_link ) ) {
					$logo_link = 'https://melapress.com/?utm_source=plugin&utm_medium=referral&utm_campaign=wsal&utm_content=priodic-report';
				}
				if ( ! isset( $logo_src ) || empty( $logo_src ) ) {
					$logo_src = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 200 68">
						<g clip-path="url(#a)">
							<path fill="#384A2F" d="M16.647 61.2h-.124l-2.657-10.106H9.977L7.32 61.172h-.138L4.954 51.094H0L4.83 67.47h4.248l2.781-9.15h.125l2.781 9.15h4.248l4.83-16.376h-4.954l-2.242 10.105ZM34.968 51.814c-.913-.485-1.978-.72-3.182-.72h-7.058V67.47h4.442v-4.859h2.463c1.246 0 2.34-.235 3.266-.692a5.14 5.14 0 0 0 2.187-1.98c.526-.858.775-1.868.775-3.045 0-1.177-.25-2.173-.761-3.045a5.174 5.174 0 0 0-2.132-2.022v-.013Zm-2.048 6.27c-.193.346-.484.61-.858.79-.373.18-.816.276-1.342.276h-1.563v-4.512h1.563c.526 0 .969.083 1.343.263.373.18.65.429.857.775.194.332.305.733.305 1.204 0 .47-.097.858-.305 1.204ZM47.686 51.094 42.289 67.47h4.788l.94-3.129h5.397l.941 3.129h4.789l-5.397-16.376h-6.075.014Zm1.342 9.911 1.633-5.398h.124l1.633 5.398h-3.39ZM65.467 55.275c.512-.36 1.149-.54 1.882-.54.43 0 .803.056 1.135.18.332.125.623.291.858.513.235.221.429.484.554.789.138.304.221.636.249 1.01h4.51c-.082-1.024-.331-1.924-.719-2.713a6.22 6.22 0 0 0-1.577-1.994 6.729 6.729 0 0 0-2.283-1.232 9.392 9.392 0 0 0-2.851-.429c-1.467 0-2.795.319-4 .97-1.19.636-2.144 1.591-2.85 2.837-.706 1.246-1.052 2.783-1.052 4.61 0 1.827.346 3.35 1.038 4.596.692 1.246 1.633 2.2 2.823 2.852 1.19.636 2.532.968 4.04.968 1.205 0 2.256-.18 3.156-.553a6.948 6.948 0 0 0 2.283-1.454 6.65 6.65 0 0 0 1.425-1.98c.333-.72.526-1.425.582-2.117l-4.511-.028a2.79 2.79 0 0 1-.305.941 2.467 2.467 0 0 1-.581.72 2.747 2.747 0 0 1-.844.457c-.319.11-.692.152-1.107.152-.72 0-1.329-.166-1.855-.512-.512-.332-.899-.844-1.176-1.523-.277-.678-.401-1.509-.401-2.52 0-.94.138-1.757.387-2.435.263-.679.65-1.19 1.163-1.55h.027v-.015ZM75.68 54.68h4.912v12.79h4.387V54.68h4.926v-3.586H75.68v3.585ZM95.44 51.094h-4.442V67.47h4.442V51.094ZM104.947 62.639h-.138l-3.349-11.545h-5.023l5.41 16.376h6.075l5.397-16.376h-5.023l-3.349 11.545ZM118.757 51.094h-4.442V67.47h4.442V51.094ZM119.864 54.68h4.927v12.79h4.372V54.68h4.913v-3.586h-14.212v3.585ZM142.78 57.738h-.125l-3.182-6.644h-4.968l6.019 11.226v5.15h4.401v-5.15l6.019-11.226h-4.954l-3.21 6.644ZM160.174 51.094h-4.455V67.47h11.056v-3.585h-6.601V51.094ZM179.285 51.842c-1.204-.637-2.547-.969-4.027-.969-1.481 0-2.837.318-4.041.97-1.204.65-2.159 1.591-2.864 2.837-.706 1.246-1.052 2.782-1.052 4.61 0 1.827.36 3.35 1.052 4.596.705 1.245 1.66 2.2 2.864 2.851 1.204.65 2.546.97 4.041.97 1.494 0 2.823-.32 4.027-.97 1.203-.637 2.158-1.592 2.864-2.838s1.066-2.782 1.066-4.61c0-1.827-.36-3.363-1.066-4.61-.706-1.245-1.661-2.2-2.864-2.837Zm-1.024 9.925c-.263.679-.637 1.191-1.135 1.537-.498.346-1.121.526-1.854.526-.734 0-1.357-.18-1.855-.526-.498-.346-.885-.858-1.134-1.537-.263-.678-.388-1.495-.388-2.477 0-.983.125-1.8.388-2.478.262-.679.636-1.19 1.134-1.537.498-.346 1.121-.526 1.855-.526.733 0 1.356.166 1.854.526.498.346.885.858 1.135 1.537.249.678.387 1.495.387 2.478 0 .983-.125 1.813-.387 2.477ZM192.763 58.32v3.17h2.961c0 .457-.125.859-.332 1.205-.222.36-.554.637-1.01.844-.443.194-1.01.305-1.689.305-.774 0-1.411-.18-1.923-.54-.512-.36-.913-.886-1.162-1.564-.263-.678-.388-1.51-.388-2.464 0-.955.139-1.758.415-2.437.277-.664.678-1.19 1.204-1.55.526-.36 1.163-.54 1.91-.54.346 0 .664.042.955.11.29.084.553.195.775.347.221.152.401.332.553.554.152.221.263.47.332.747h4.484a5.819 5.819 0 0 0-.72-2.27 5.96 5.96 0 0 0-1.55-1.786 7.334 7.334 0 0 0-2.214-1.163c-.83-.276-1.73-.415-2.712-.415-1.107 0-2.131.18-3.1.554a7.38 7.38 0 0 0-2.546 1.633 7.35 7.35 0 0 0-1.716 2.658c-.415 1.052-.623 2.243-.623 3.6 0 1.73.333 3.21 1.011 4.47.678 1.26 1.605 2.216 2.823 2.894 1.204.678 2.601 1.01 4.192 1.01 1.426 0 2.699-.277 3.792-.844 1.107-.568 1.979-1.37 2.602-2.436.636-1.053.941-2.326.941-3.821v-2.27H192.763Z" />
							<path fill="#BDD63A" d="M118.162 18.19S110.026 30.8 99.979 30.8c-10.046 0-18.183-12.61-18.183-12.61V28.46l18.183 7.918 18.183-7.918V18.19Z" />
							<path fill="#009344" d="M99.98 5.579c10.046 0 18.182 12.61 18.182 12.61V7.92L99.979 0 81.796 7.918V18.19S89.933 5.579 99.98 5.579Z" />
							<path fill="#384A2F" d="M99.98 26.537a8.329 8.329 0 0 0 8.191-9.857c-.581-3.266-3.141-5.924-6.379-6.617-2.242-.47-4.345-.041-6.061.983a4.14 4.14 0 0 1 1.232 2.963c0 1.024-.485 1.966-1.204 2.685-1.66 1.662-2.948 1.634-4.096 1.302v.207c0 4.596 3.736 8.334 8.33 8.334h-.014Z" />
						</g>
						<defs>
							<clipPath id="a">
								<path fill="#fff" d="M0 0h200v67.692H0z" />
							</clipPath>
						</defs>
					</svg>';
				} else {
					$logo_src = '<img src="' . $logo_src . '" alt="Report_logo" style="max-height: 150px; max-width: 800px;" />';
				}

				$str .= '<div id="section-1">';
				$str .= '<a href="' . esc_url( $logo_link ) . '" rel="noopener noreferrer" target="_blank">';
				// Don't use esc_url here. Logo source can be something other than a URL.
				$str .= $logo_src;
				$str .= '</a>';

				$str .= '<h1 style="color: #059348;">';
				if ( array_key_exists( 'custom_title', $report_data ) ) {
					$str .= $report_data['custom_title'];

					unset( $report_data['custom_title'] );
				} else {
					$str .= esc_html__( 'Report from', 'wp-security-audit-log' ) . ' ' . \get_bloginfo( 'name' ) . ' ' . esc_html__( 'website', 'wp-security-audit-log' );
				}
				$str .= '</h1>';

				$str .= '</div>';

				$now  = time();
				$date = DateTime_Formatter_Helper::get_formatted_date_time( $now, 'date' );
				$time = DateTime_Formatter_Helper::get_formatted_date_time( $now, 'time' );

				$user = User_Helper::get_current_user();
				$str .= '<div id="section-2">';

				$report_attributes = array();

				$report_attributes[ esc_html__( 'Report Date', 'wp-security-audit-log' ) ] = $date;
				$report_attributes[ esc_html__( 'Report Time', 'wp-security-audit-log' ) ] = $time;

				$timezone = Settings_Helper::get_timezone();

				/**
				 * Transform timezone values.
				 *
				 * @since 3.2.3
				 */
				if ( '0' === $timezone ) {
					$timezone = 'UTC';
				} elseif ( '1' === $timezone ) {
					$timezone = wp_timezone_string();
				} elseif ( 'wp' === $timezone ) {
					$timezone = wp_timezone_string();
				} elseif ( 'utc' === $timezone ) {
					$timezone = 'UTC';
				}

				$report_attributes[ esc_html__( 'Timezone', 'wp-security-audit-log' ) ]     = $timezone;
				$report_attributes[ esc_html__( 'Generated by', 'wp-security-audit-log' ) ] = $user->user_login . ' — ' . $user->user_email;

				$contact_attributes = array(
					esc_html__( 'Business Name', 'wp-security-audit-log' ) => isset( $white_settings['business_name'] ) ? $white_settings['business_name'] : '',
					esc_html__( 'Contact Name', 'wp-security-audit-log' ) => isset( $white_settings['name_surname'] ) ? $white_settings['name_surname'] : '',
					esc_html__( 'Contact Email', 'wp-security-audit-log' ) => isset( $white_settings['email'] ) ? $white_settings['email'] : '',
					esc_html__( 'Contact Phone', 'wp-security-audit-log' ) => isset( $white_settings['phone_number'] ) ? $white_settings['phone_number'] : '',
				);

				foreach ( $contact_attributes as $label => $value ) {
					if ( strlen( (string) $value ) > 0 ) {
						$report_attributes[ $label ] = $value;
					}
				}

				$tbody  = '<table>';
				$tbody .= '<tbody>';
				foreach ( $report_attributes as $label => $value ) {
					$tbody .= '<tr>';
					$tbody .= '<th><strong>' . $label . ':</strong></th>';
					$tbody .= '<td>' . $value . '</td>';
					$tbody .= '</tr>';
				}

				if ( array_key_exists( 'comment', $report_data ) && strlen( $report_data['comment'] ) > 0 ) {
					$tbody .= '<tr>';
					$tbody .= '<td colspan="2"><strong>' . esc_html__( 'Comment', 'wp-security-audit-log' ) . ':</strong></td>';
					$tbody .= '</tr>';
					$tbody .= '<tr>';
					$tbody .= '<td colspan="2">' . $report_data['comment'] . '</td>';
					$tbody .= '</tr>';
				}

				unset( $report_data['comment'] );

				$tbody .= '<tr>';
				if ( isset( $report_data['no_meta'] ) && false === $report_data['no_meta'] ) {
					$tbody .= '<th colspan="2"><strong>' . esc_html__( 'Report Criteria', 'wp-security-audit-log' ) . '</strong></th>';
					$tbody .= '</tr>';

					unset( $report_data['no_meta'] );

					// $criteria = $this->get_criteria_list();
					foreach ( $report_data as $criteria_label => $criteria_values ) {
						$tbody .= '<tr>';
						$tbody .= '<td><em>' . $criteria_label . ':</em></td>';
						$tbody .= '<td>' . \implode( '<br>', (array) $criteria_values ) . '</td>';
						$tbody .= '</tr>';
					}
				}

				$tbody .= '</tbody>';
				$tbody .= '</table>';

				$str .= $tbody;
				$str .= '</div>';
			}

			$str .= '<div id="section-3">';

			return $str;
		}
	}
}
