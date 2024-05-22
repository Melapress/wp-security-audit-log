<?php
/**
 * Adapter: MainWP.
 *
 * MainWP users entity class.
 *
 * @package wsal
 */

declare(strict_types=1);

namespace WSAL\Entities;

use WSAL\Helpers\Settings_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\Entities\MainWP_Server_Users' ) ) {

	/**
	 * Responsible for the reports storage.
	 */
	class MainWP_Server_Users extends Abstract_Entity {

		/**
		 * Holds the DB records for the periodic reports
		 *
		 * @var \wpdb
		 *
		 * @since 5.0.0
		 */
		private static $connection = null;

		/**
		 * Inner class cache for inserted users.
		 *
		 * @var array
		 */
		private static $insert_users = array();

		/**
		 * Contains the table name.
		 *
		 * @var string
		 *
		 * @since 5.0.0
		 */
		protected static $table = 'wsal_mainwp_server_users';

		/**
		 * Keeps the info about the columns of the table - name, type.
		 *
		 * @var array
		 *
		 * @since 5.0.0
		 */
		protected static $fields = array(
			'ID'            => 'bigint',
			'site_id'       => 'bigint',
			'inner_id'      => 'bigint',
			'user_login'    => 'varchar(60)',
			'first_name'    => 'varchar(250)',
			'last_name'     => 'varchar(250)',
			'display_name'  => 'varchar(250)',
			'user_email'    => 'varchar(100)',
			'user_nicename' => 'varchar(50)',
			'user_roles'    => 'text',
			'created_on'    => 'bigint',
		);

		/**
		 * Creates table functionality
		 *
		 * @return bool
		 *
		 * @since 5.0.0
		 */
		public static function create_table(): bool {
			$table_name    = self::get_table_name();
			$wp_entity_sql = '
				CREATE TABLE `' . $table_name . '` (
					`ID` bigint UNSIGNED NOT NULL AUTO_INCREMENT,' . PHP_EOL . '
					`site_id` bigint NOT NULL,' . PHP_EOL . '
					`inner_id` bigint UNSIGNED NOT NULL,' . PHP_EOL . '
					`user_login` VARCHAR(60) NOT NULL,' . PHP_EOL . '
					`first_name` varchar(250) NOT NULL,' . PHP_EOL . '
					`last_name` varchar(250) NOT NULL,' . PHP_EOL . '
					`display_name` varchar(250) NOT NULL,' . PHP_EOL . '
					`user_email` varchar(100) NOT NULL,' . PHP_EOL . '
					`user_nicename` varchar(50) NOT NULL,' . PHP_EOL . '
					`user_roles` TEXT NOT NULL,' . PHP_EOL . '
					`created_on` bigint NOT NULL,' . PHP_EOL . '
				  PRIMARY KEY (`ID`, `site_id`),' . PHP_EOL . '
				  KEY `user_email` (`user_email`),' . PHP_EOL . '
				  KEY `user_login` (`user_login`)' . PHP_EOL . '
				)
			  ' . self::get_connection()->get_charset_collate() . ';';

			return self::maybe_create_table( $table_name, $wp_entity_sql );
		}

		/**
		 * Returns the current connection. Reports are always stored in local database - that is the reason for overriding this method
		 *
		 * @return \WPDB @see
		 *
		 * @since 5.0.0
		 */
		public static function get_connection() {
			if ( null === self::$connection ) {
				global $wpdb;
				self::$connection = $wpdb;
			}
			return self::$connection;
		}

		/**
		 * Saves record in the table
		 *
		 * @param array $active_record - An array with all the user data to insert.
		 * @param int   $site_id - The id of a site which has to be assigned to the user data stored.
		 *
		 * @return array|false
		 *
		 * @since 5.0.0
		 */
		public static function save_user( $active_record, int $site_id ) {

			if ( isset( self::$insert_users[ (string) $active_record['ID'] . (string) $site_id ] ) ) {

				return array(
					'last_insert' => self::$insert_users[ (string) $active_record['ID'] . (string) $site_id ]['last_insert'],
					'inner_id'    => self::$insert_users[ (string) $active_record['ID'] . (string) $site_id ]['inner_id'],
				);
			}

			$_wpdb  = self::get_connection();
			$format = array(
				'%d', // 'ID'            => 'bigint',.
				'%d', // 'site_id'       => 'bigint',.
				'%d', // 'inner_id'       => 'bigint',.
				'%s', // 'user_login'    => 'varchar(60)',.
				'%s', // 'first_name'    => 'varchar(250)',.
				'%s', // 'last_name'     => 'varchar(250)',.
				'%s', // 'display_name'  => 'varchar(250)',.
				'%s', // 'user_email'    => 'varchar(100)',.
				'%s', // 'user_nicename' => 'varchar(50)',.
				'%s', // 'user_roles' => 'text',.
			);

			$data = array();

			$data_collect = array(
				'ID'            => (int) $active_record['ID'],
				'site_id'       => $site_id,
				/**
				 * That is the tricky part - we need unique user IDs so we can check / generate reports.
				 * So here we get really big number, add the collected user ID to it and store it in the database.
				 * When we wont to extract that specific user (like in the stored reports) we will use that one so we can be assured that
				 * it has unique ID.
				 * The biggest number in PHP is 9223372036854775807
				 *
				 * We will use partial of it and hope that there will be no site that will reach that number of users and start generating duplicates.
				 * Of course we have to add the site_id to that as well because there could be more users with same IDs from different sites, which will
				 * cost us troubles.
				 *
				 * So the logic is as follows:
				 *  - Site ID (we expect that to be in the range of 10000 - 99999)
				 *  - Added suffix to that site ID so we could get number like 1000000000000000000
				 *  - Add the user ID to that - so at the end we will have 1000000000000000001 - this way we know that this user is of site
				 * with ID 10000 and user ID 1
				 */
				'inner_id'      => ( (int) str_pad( (string) $site_id, 18, '0', STR_PAD_RIGHT ) ) + ( (int) $active_record['ID'] ),
				'user_login'    => $active_record['user_login'],
				'first_name'    => $active_record['first_name'],
				'last_name'     => $active_record['last_name'],
				'display_name'  => $active_record['display_name'],
				'user_email'    => $active_record['user_email'],
				'user_nicename' => $active_record['user_nicename'],
				'user_roles'    => implode( ',', $active_record['user_roles'] ),
			);

			$data = \array_merge( $data, $data_collect );

			if ( ! isset( $active_record['created_on'] ) ) {
				$data['created_on'] = microtime( true );
			} else {
				$data['created_on'] = $active_record['created_on'];
			}

			$_wpdb->suppress_errors( true );

			$result = $_wpdb->replace( self::get_table_name(), $data, $format );

			if ( '' !== $_wpdb->last_error ) {
				if ( 1146 === self::get_last_sql_error( $_wpdb ) ) {
					if ( self::create_table() ) {
						$result = $_wpdb->replace( self::get_table_name(), $data, $format );
					}
				}
			}
			$_wpdb->suppress_errors( false );

			self::$insert_users[ (string) $active_record['ID'] . (string) $site_id ] = $data + array( 'last_insert' => $_wpdb->insert_id );

			return array(
				'last_insert' => $_wpdb->insert_id,
				'inner_id'    => $data['inner_id'],
			);
		}

		/**
		 * Load object data from variable.
		 *
		 * @param array|object $data Data array or object.
		 * @throws \Exception - Unsupported type.
		 *
		 * @since 5.0.0
		 */
		public static function load_data( $data ) {
			return $data;
		}
	}
}
