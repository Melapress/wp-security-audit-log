<?php
/**
 * Entity: Metadata.
 *
 * User Sessions class.
 *
 * @package wsal
 */

declare(strict_types=1);

namespace WSAL\Entities;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\Entities\Metadata_Entity' ) ) {
	/**
	 * Responsible for the events metadata.
	 */
	class Metadata_Entity extends Abstract_Entity {
		/**
		 * Contains the table name.
		 *
		 * @var string
		 */
		protected static $table = 'wsal_metadata';

		/**
		 * Keeps the info about the columns of the table - name, type.
		 *
		 * @var array
		 *
		 * @since 4.5.0
		 */
		protected static $fields = array(
			'id'            => 'int',
			'occurrence_id' => 'int',
			'name'          => 'string',
			'value'         => 'string',
		);

		/**
		 * Creates table functionality.
		 *
		 * @since 4.4.2.1
		 */
		public static function create_table(): bool {
			$table_name    = self::get_table_name();
			$wp_entity_sql = '
				CREATE TABLE `' . $table_name . '` (
					`id` bigint(20) NOT NULL AUTO_INCREMENT,
					`occurrence_id` bigint(20) NOT NULL,
					`name` varchar(100) NOT NULL,
					`value` longtext NOT NULL,
				PRIMARY KEY (`id`),
				KEY `occurrence_name` (`occurrence_id`,`name`),
				KEY `name_value` (`name`,`value`(64))
				)
			  ' . self::get_connection()->get_charset_collate() . ';';

			return self::maybe_create_table( $table_name, $wp_entity_sql );
		}

		/**
		 * Sets an index (if not there already)
		 *
		 * @return void
		 *
		 * @since 4.5.1
		 */
		public static function create_indexes() {
			$db_connection = self::get_connection();
			// check if an index exists.
			$index_exists = false;
			if ( $db_connection->query( 'SELECT COUNT(1) IndexIsThere FROM INFORMATION_SCHEMA.STATISTICS WHERE table_schema=DATABASE() AND table_name="' . self::get_table_name() . '" AND index_name="name_value"' ) ) {
				// query succeeded, does index exist?
				$index_exists = ( isset( $db_connection->last_result[0]->IndexIsThere ) ) ? $db_connection->last_result[0]->IndexIsThere : false;
			}
			// if no index exists then make one.
			if ( ! $index_exists ) {
				$db_connection->query( 'CREATE INDEX name_value ON ' . self::get_table_name() . ' (name, value(64))' );
			}
		}
	}
}
