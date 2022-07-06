<?php
/**
 * Entity: Occurrences.
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

if ( ! class_exists( '\WSAL\Entities\Occurrences_Entity' ) ) {

	/**
	 * Responsible for the events occurrences.
	 */
	class Occurrences_Entity extends Abstract_Entity {

		/**
		 * Contains the table name.
		 *
		 * @var string
		 */
		protected static $table = 'wsal_occurrences';

		/**
		 * Builds an upgrade query for the occurrence table.
		 *
		 * @return string
		 */
		public static function get_upgrade_query() {
			return 'ALTER TABLE `' . self::get_table_name() . '`'
			. ' DROP COLUMN is_read, '
			. ' DROP COLUMN is_migrated, '
			. " ADD client_ip VARCHAR(255) NOT NULL DEFAULT '',"
			. ' ADD severity BIGINT NOT NULL DEFAULT 0,'
			. " ADD object VARCHAR(255) NOT NULL DEFAULT '',"
			. " ADD event_type VARCHAR(255) NOT NULL DEFAULT '',"
			. " ADD user_agent VARCHAR(255) NOT NULL DEFAULT '',"
			. " ADD user_roles VARCHAR(255) NOT NULL DEFAULT '',"
			. ' ADD username VARCHAR(255) NULL,'
			. ' ADD user_id BIGINT NULL ,'
			. " ADD session_id VARCHAR(255) NOT NULL DEFAULT '',"
			. " ADD post_status VARCHAR(255) NOT NULL DEFAULT '',"
			. " ADD post_type VARCHAR(255) NOT NULL DEFAULT '',"
			. ' ADD post_id BIGINT NOT NULL DEFAULT 0;';
		}

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
					`id` bigint NOT NULL AUTO_INCREMENT,
					`site_id` bigint NOT NULL,
					`alert_id` bigint NOT NULL,
					`created_on` double NOT NULL,
					`client_ip` varchar(255) NOT NULL,
					`severity` varchar(255) NOT NULL,
					`object` varchar(255) NOT NULL,
					`event_type` varchar(255) NOT NULL,
					`user_agent` varchar(255) NOT NULL,
					`user_roles` varchar(255) NOT NULL,
					`username` varchar(255) DEFAULT NULL,
					`user_id` bigint DEFAULT NULL,
					`session_id` varchar(255) NOT NULL,
					`post_status` varchar(255) NOT NULL,
					`post_type` varchar(255) NOT NULL,
					`post_id` bigint NOT NULL,
				PRIMARY KEY (`id`),
				KEY `site_alert_created` (`site_id`,`alert_id`,`created_on`),
				KEY `created_on` (`created_on`)
				)
			  ' . self::get_connection()->get_charset_collate() . ';';

			return self::maybe_create_table( $table_name, $wp_entity_sql );
		}
	}
}
