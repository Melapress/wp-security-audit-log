<?php
/**
 * Entity: Metadata.
 *
 * User Sessions class.
 *
 * @package wsal
 *
 * @since 4.4.2.1
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
		 *
		 * @since 4.4.2.1
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
		 * Holds all the default values for the columns.
		 *
		 * @var array
		 *
		 * @since 4.6.0
		 */
		protected static $fields_values = array(
			'id'            => 0,
			'occurrence_id' => 0,
			'name'          => '',
			'value'         => '',
		);

		/**
		 * Creates table functionality.
		 *
		 * @param \wpdb $connection - \wpdb connection to be used for name extraction.
		 *
		 * @since 4.4.2.1
		 * @since 4.6.0 - Added $connection parameter
		 */
		public static function create_table( $connection = null ): bool {
			if ( null !== $connection ) {
				if ( $connection instanceof \wpdb ) {
					$collate = $connection->get_charset_collate();

				}
			} else {
				$collate = self::get_connection()->get_charset_collate();
			}
			$table_name    = self::get_table_name( $connection );
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
			  ' . $collate . ';';

			return self::maybe_create_table( $table_name, $wp_entity_sql, $connection );
		}

		/**
		 * Sets an index (if not there already)
		 *
		 * @param \wpdb $connection - \wpdb connection to be used for name extraction.
		 *
		 * @return void
		 *
		 * @since 4.5.1
		 * @since 4.6.0 - Added connection parameter
		 */
		public static function create_indexes( $connection = null ) {
			if ( null !== $connection ) {
				if ( $connection instanceof \wpdb ) {
					$_wpdb = $connection;
				}
			} else {
				$_wpdb = self::get_connection();
			}
			// check if an index exists.
			$index_exists = false;
			if ( $_wpdb->query( 'SELECT COUNT(1) IndexIsThere FROM INFORMATION_SCHEMA.STATISTICS WHERE table_schema=DATABASE() AND table_name="' . self::get_table_name( $connection ) . '" AND index_name="name_value"' ) ) {
				// query succeeded, does index exist?
				$index_exists = ( isset( $_wpdb->last_result[0]->IndexIsThere ) ) ? $_wpdb->last_result[0]->IndexIsThere : false;
			}
			// if no index exists then make one.
			if ( ! $index_exists ) {
				$_wpdb->query( 'CREATE INDEX name_value ON ' . self::get_table_name( $connection ) . ' (name, value(64))' );
			}
		}

		/**
		 * Load meta by name and occurrence id.
		 *
		 * @param string $meta_name - Meta name.
		 * @param int    $occurrence_id - Occurrence ID.
		 *
		 * @return array
		 *
		 * @since 4.6.0
		 */
		public static function load_by_name_and_occurrence_id( $meta_name, $occurrence_id ) {
			// Make sure to grab the migrated meta fields from the occurrence table.
			if ( in_array( $meta_name, array_keys( Occurrences_Entity::$migrated_meta ), true ) ) {
				$column_name = Occurrences_Entity::$migrated_meta[ $meta_name ];

				return Occurrences_Entity::get_fields_values()[ $column_name ];
			}

			return self::load( 'occurrence_id = %d AND name = %s', array( $occurrence_id, $meta_name ) );
		}

		/**
		 * Deletes records from metadata table using the Occurrences IDs
		 *
		 * @param array $occurrence_ids - The ids of the occurrences.
		 * @param \wpdb $connection - \wpdb connection to be used for name extraction.
		 *
		 * @return void
		 *
		 * @since 4.6.0
		 */
		public static function delete_by_occurrence_ids( $occurrence_ids, $connection = null ) {
			if ( null !== $connection ) {
				if ( $connection instanceof \wpdb ) {
					$_wpdb = $connection;
				}
			} else {
				$_wpdb = self::get_connection();
			}
			if ( ! empty( $occurrence_ids ) ) {
				$sql = 'DELETE FROM ' . self::get_table_name( $_wpdb ) . ' WHERE occurrence_id IN (' . implode( ',', $occurrence_ids ) . ')';
				// Execute query.
				self::delete_query( $sql, array(), $_wpdb );
			}
		}

		/**
		 * Update Metadata by name and occurrence_id.
		 *
		 * @param string  $name          - Meta name.
		 * @param mixed   $value         - Meta value.
		 * @param integer $occurrence_id - Occurrence_id.
		 *
		 * @since 4.6.0
		 */
		public static function update_by_name_and_occurrence_id( $name, $value, $occurrence_id ) {
			$meta = self::load_by_name_and_occurrence_id( $name, $occurrence_id );
			if ( empty( $meta ) ) {

				$meta_insert = array(
					'occurrence_id' => $occurrence_id,
					'name'          => $name,
					'value'         => maybe_serialize( $value ),
				);

				self::save( $meta_insert );
			} else {

				$meta_insert = array(
					'id'            => $meta['id'],
					'occurrence_id' => $occurrence_id,
					'name'          => $name,
					'value'         => maybe_serialize( $value ),
				);

				self::save( $meta_insert );
			}
		}

		/**
		 * Loads all the meta records for the given occurrences ids
		 *
		 * @param array $occurrence_ids - Array with Occurrences IDs to search for.
		 * @param \wpdb $connection - \wpdb connection to be used for name extraction.
		 *
		 * @return array
		 *
		 * @since 4.6.0
		 */
		public static function load_by_occurrences_ids( array $occurrence_ids, $connection = null ): array {
			$results = array();

			if ( null !== $connection ) {
				if ( $connection instanceof \wpdb ) {
					$_wpdb = $connection;
				}
			} else {
				$_wpdb = self::get_connection();
			}

			$sql = 'SELECT * FROM ' . self::get_table_name( $_wpdb ) . ' WHERE occurrence_id in (' . implode( ',', $occurrence_ids ) . ')';

			$_wpdb->suppress_errors( true );
			$results = $_wpdb->get_results( $sql, \ARRAY_A );
			if ( '' !== $_wpdb->last_error ) {
				if ( 1146 === self::get_last_sql_error( $_wpdb ) ) {
					if ( ( static::class )::create_table( $_wpdb ) ) {
						$results = $_wpdb->get_results( $sql, \ARRAY_A );
					}
				}
			}
			$_wpdb->suppress_errors( false );

			return $results;
		}

		/**
		 * Extracts the user data from the migration table based on given occurrences
		 *
		 * @param string $occurrence_ids - String with Occurrences IDs to search for.
		 * @param \wpdb  $connection - \wpdb connection to be used for name extraction.
		 *
		 * @return array
		 *
		 * @since 5.0.0
		 */
		public static function get_user_data_by_occ_ids( string $occurrence_ids, $connection = null ): array {
			$results = array();

			if ( null !== $connection ) {
				if ( $connection instanceof \wpdb ) {
					$_wpdb = $connection;
				}
			} else {
				$_wpdb = self::get_connection();
			}

			$sql = 'SELECT value FROM ' . self::get_table_name( $_wpdb ) . ' WHERE occurrence_id in (' . $occurrence_ids . ') AND ( name = "NewUserData" )';

			// $sql = 'SELECT value FROM ' . self::get_table_name( $_wpdb ) . ' WHERE occurrence_id in (' . $occurrence_ids . ') AND ( name = "NewUserData" OR name = "NewUserID" )';

			$_wpdb->suppress_errors( true );
			$results = $_wpdb->get_results( $sql, \ARRAY_A );
			if ( '' !== $_wpdb->last_error ) {
				if ( 1146 === self::get_last_sql_error( $_wpdb ) ) {
					if ( ( static::class )::create_table( $_wpdb ) ) {
						$results = $_wpdb->get_results( $sql, \ARRAY_A );
					}
				}
			}
			$_wpdb->suppress_errors( false );

			return $results;
		}
	}
}
