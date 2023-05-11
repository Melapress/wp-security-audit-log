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
		 * List of migrated metadata fields.
		 *
		 * @var string[]
		 */
		public static $migrated_meta = array(
			'ClientIP'         => 'client_ip',
			'Severity'         => 'severity',
			'Object'           => 'object',
			'EventType'        => 'event_type',
			'UserAgent'        => 'user_agent',
			'CurrentUserRoles' => 'user_roles',
			'Username'         => 'username',
			'CurrentUserID'    => 'user_id',
			'SessionID'        => 'session_id',
			'PostStatus'       => 'post_status',
			'PostType'         => 'post_type',
			'PostID'           => 'post_id',
		);

		/**
		 * Keeps the info about the columns of the table - name, type.
		 *
		 * @var array
		 *
		 * @since 4.5.0
		 */
		protected static $fields = array(
			'id'          => 'int',
			'site_id'     => 'int',
			'alert_id'    => 'int',
			'created_on'  => 'float',
			'client_ip'   => 'string',
			'severity'    => 'string',
			'object'      => 'string',
			'event_type'  => 'string',
			'user_agent'  => 'string',
			'user_roles'  => 'string',
			'username'    => 'string',
			'user_id'     => 'int',
			'session_id'  => 'string',
			'post_status' => 'string',
			'post_type'   => 'string',
			'post_id'     => 'int',
		);

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
		 * @since 4.4.2.1
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

		/**
		 * Returns the column name for a given table
		 *
		 * @return array
		 *
		 * @since 4.5.0
		 */
		public static function get_column_names(): array {
			return array(
				'id'          => 'bigint',
				'site_id'     => 'bigint',
				'alert_id'    => 'bigint',
				'created_on'  => 'double',
				'client_ip'   => 'varchar(255)',
				'severity'    => 'varchar(255)',
				'object'      => 'varchar(255)',
				'event_type'  => 'varchar(255)',
				'user_agent'  => 'varchar(255)',
				'user_roles'  => 'varchar(255)',
				'username'    => 'varchar(255)',
				'user_id'     => 'bigint',
				'session_id'  => 'varchar(255)',
				'post_status' => 'varchar(255)',
				'post_type'   => 'varchar(255)',
				'post_id'     => 'bigint',
			);
		}

		/**
		 * Responsible for storing the information in both occurrences table and metadata table.
		 * That one is optimized for DB performance
		 *
		 * @param array $data - The data to be stored.
		 * @param int   $type - The event ID.
		 * @param float $date - Formatted to UNIX timestamp date.
		 * @param int   $site_id - The site ID to store data for.
		 *
		 * @return void
		 *
		 * @since 4.5.0
		 */
		public static function store_record( $data, $type, $date, $site_id ) {
			$data_to_store = array();
			foreach ( (array) $data as $name => $value ) {
				if ( '0' === $value || ! empty( $value ) ) {
					if ( isset( self::$migrated_meta[ $name ] ) ) {
						if ( 'CurrentUserRoles' === $name ) {
							$value = maybe_unserialize( $value );
							if ( is_array( $value ) && ! empty( $value ) ) {
								$data_to_store[ self::$migrated_meta[ $name ] ] = implode( ',', $value );
							}
						} else {
							$data_to_store[ self::$migrated_meta[ $name ] ] = $value;
						}

						unset( $data[ $name ] );
					}
				}
			}

			if ( ! empty( $data_to_store ) ) {
				$data_to_store['created_on'] = $date;
				$data_to_store['alert_id']   = $type;
				$data_to_store['site_id']    = ! is_null( $site_id ) ? $site_id : ( function_exists( 'get_current_blog_id' ) ? get_current_blog_id() : 0 );

				$occurrences_id = self::save( $data_to_store );

				if ( 0 !== $occurrences_id && ! empty( $data ) ) {
					$sqls = '';
					foreach ( (array) $data as $name => $value ) {
						$meta_insert = array(
							'occurrence_id' => $occurrences_id,
							'name'          => $name,
							'value'         => maybe_serialize( $value ),
						);

						$data_prepared = Metadata_Entity::prepare_data( $meta_insert );

						$fields  = '`' . implode( '`, `', array_keys( $data_prepared[0] ) ) . '`';
						$formats = implode( ', ', $data_prepared[1] );

						$sql = "($formats),";

						$sqls .= self::get_connection()->prepare( $sql, $data_prepared[0] );
					}

					if ( ! empty( $sqls ) ) {
						$sqls = 'INSERT INTO `' . Metadata_Entity::get_table_name() . "` ($fields) VALUES " . rtrim( $sqls, ',' );

						self::get_connection()->suppress_errors( true );
						self::get_connection()->query( $sqls );

						if ( '' !== self::get_connection()->last_error ) {
							if ( 1146 === Metadata_Entity::get_last_sql_error( self::get_connection() ) ) {
								if ( Metadata_Entity::create_table() ) {
									self::get_connection()->query( $sqls );
								}
							}
						}
						self::get_connection()->suppress_errors( false );
					}
				}
			}
		}

		/**
		 * Sets an index (if not there already)
		 *
		 * @return void
		 *
		 * @since 4.5.1
		 */
		public static function create_indexes() {
			$index_exists  = false;
			$db_connection = self::get_connection();
			// check if an index exists.
			if ( $db_connection->query( 'SELECT COUNT(1) IndexIsThere FROM INFORMATION_SCHEMA.STATISTICS WHERE table_schema=DATABASE() AND table_name="' . self::get_table_name() . '" AND index_name="created_on"' ) ) {
				// query succeeded, does index exist?
				$index_exists = ( isset( $db_connection->last_result[0]->IndexIsThere ) ) ? $db_connection->last_result[0]->IndexIsThere : false;
			}
			// if no index exists then make one.
			if ( ! $index_exists ) {
				$db_connection->query( 'CREATE INDEX created_on ON ' . self::get_table_name() . ' (created_on)' );
			}
		}
	}
}
