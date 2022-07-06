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
		 * Creates table functionality
		 *
		 * @return bool
		 *
		 * @since      4.4.2.1
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
				KEY `occurrence_name` (`occurrence_id`,`name`)
				)
			  ' . self::get_connection()->get_charset_collate() . ';';

			return self::maybe_create_table( $table_name, $wp_entity_sql );
		}
	}
}
