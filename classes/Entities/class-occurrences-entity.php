<?php
/**
 * Entity: Occurrences.
 *
 * User Sessions class.
 *
 * @package wsal
 *
 * @since 4.5.0
 */

declare(strict_types=1);

namespace WSAL\Entities;

use WSAL\Controllers\Alert;
use WSAL\Controllers\Connection;
use WSAL\Helpers\Settings_Helper;
use WSAL\Helpers\Plugin_Settings_Helper;
use WSAL\Entities\Archive\Delete_Records;

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
		 *
		 * @since 4.5.0
		 */
		protected static $table = 'wsal_occurrences';

		/**
		 * List of migrated metadata fields.
		 *
		 * @var string[]
		 *
		 * @since 4.5.0
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

		/**
		 * Holds all the default values for the columns.
		 *
		 * @var array
		 *
		 * @since 4.6.0
		 */
		protected static $fields_values = array(
			'id'          => 0,
			'site_id'     => 0,
			'alert_id'    => 0,
			'created_on'  => 0.0,
			'client_ip'   => '',
			'severity'    => '',
			'object'      => '',
			'event_type'  => '',
			'user_agent'  => '',
			'user_roles'  => '',
			'username'    => null,
			'user_id'     => null,
			'session_id'  => '',
			'post_status' => '',
			'post_type'   => '',
			'post_id'     => 0,
		);

		/**
		 * Builds an upgrade query for the occurrence table.
		 *
		 * @return string
		 *
		 * @since 4.5.0
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
		 * @param \wpdb $connection - \wpdn connection to be used for name extraction.
		 *
		 * @return bool
		 *
		 * @since 4.4.2.1
		 * @since 4.6.0 - Added $connection parameter
		 */
		public static function create_table( $connection = null ): bool {
			$collate = self::get_connection()->get_charset_collate();
			if ( null !== $connection ) {
				if ( $connection instanceof \wpdb ) {
					$collate = $connection->get_charset_collate();
				}
			}
			$table_name    = self::get_table_name( $connection );
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
			  ' . $collate . ';';

			return self::maybe_create_table( $table_name, $wp_entity_sql, $connection );
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
							} elseif ( ! empty( $value ) ) {
								$data_to_store[ self::$migrated_meta[ $name ] ] = $value;
							}
						} else {
							$data_to_store[ self::$migrated_meta[ $name ] ] = $value;
						}

						unset( $data[ $name ] );
					}
				}
				if ( 'CurrentUserID' === $name && ! \is_object( $value ) && 0 === (int) $value ) {
					$data_to_store[ self::$migrated_meta[ $name ] ] = $value;
				}
			}

			if ( ! empty( $data_to_store ) ) {
				$data_to_store['created_on'] = $date;
				$data_to_store['alert_id']   = $type;
				$data_to_store['site_id']    = ! is_null( $site_id ) ? $site_id : ( function_exists( 'get_current_blog_id' ) ? get_current_blog_id() : 0 );

				if ( in_array( $type, array( 1000, 1001, 1002, 1003 ) ) ) {
					if ( empty( $data_to_store['user_id'] ) && empty( $data_to_store['username'] ) ) {
						$data_to_store['user_id']  = 0;
						$data_to_store['username'] = 'Unknown user';
					} elseif ( empty( $data_to_store['username'] ) ) {
						if ( 0 === (int) $data_to_store['user_id'] ) {
							$data_to_store['username'] = 'Unknown User';
						} else {
							$user = \get_user_by( 'ID', $data_to_store['user_id'] );
							if ( $user ) {
								$data_to_store['username'] = $user->user_login;
							} else {
								$data_to_store['username'] = 'Deleted';
							}
						}
					} elseif ( empty( (int) $data_to_store['user_id'] ) ) {
						if ( 0 === (int) $data_to_store['user_id'] ) {
							$data_to_store['username'] = 'Unknown User';
						} else {
							$user = \get_user_by( 'login', $data_to_store['username'] );
							if ( $user ) {
								$data_to_store['user_id'] = $user->ID;
							} else {
								$data_to_store['user_id'] = 0;
							}
						}
					}
				}

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
		 * @param \wpdb $connection - \wpdb connection to be used for name extraction.
		 *
		 * @return void
		 *
		 * @since 4.5.1
		 * @since 4.6.0 - Added connection parameter
		 */
		public static function create_indexes( $connection = null ) {
			$index_exists  = false;
			$db_connection = self::get_connection();
			if ( null !== $connection ) {
				if ( $connection instanceof \wpdb ) {
					$db_connection = $connection;
				}
			}
			// check if an index exists.
			if ( $db_connection->query( 'SELECT COUNT(1) IndexIsThere FROM INFORMATION_SCHEMA.STATISTICS WHERE table_schema=DATABASE() AND table_name="' . self::get_table_name( $connection ) . '" AND index_name="created_on"' ) ) {
				// query succeeded, does index exist?
				$index_exists = ( isset( $db_connection->last_result[0]->IndexIsThere ) ) ? $db_connection->last_result[0]->IndexIsThere : false;
			}
			// if no index exists then make one.
			if ( ! $index_exists ) {
				$db_connection->query( 'CREATE INDEX created_on ON ' . self::get_table_name( $connection ) . ' (created_on)' );
			}
		}

		/**
		 * Gets alert message.
		 *
		 * @param array  $item    - Occurrence meta array.
		 * @param string $context Message context.
		 *
		 * @return string Full-formatted message.
		 * @see WSAL_Alert::get_message()
		 *
		 * @since 4.6.0
		 */
		public static function get_alert_message( $item = null, $context = 'default' ) {
			// $alert          = Alert_Manager::get_alert(
			// $item['alert_id'],
			// (object) array(
			// 'mesg' => esc_html__( 'Alert message not found.', 'wp-security-audit-log' ),
			// 'desc' => esc_html__( 'Alert description not found.', 'wp-security-audit-log' ),
			// )
			// );
			// $cached_message = $alert->mesg;

			if ( ! isset( $item['meta_values'] ) ) {
				return '';
			}

			// Fill variables in message.
			$meta_array = $item['meta_values'];
			// $alert_object = $alert;

			$message = Alert::get_message( $meta_array, null, $item['alert_id'], $item['id'], $context );
			if ( false === $message ) {
				$cached_message = Alert::get_original_alert_message( $item['alert_id'] );
			}
			if ( false !== $message ) {
				$cached_message = $message;
			} else {

				$cached_message = isset( $cached_message ) ? $cached_message : sprintf(
				/* Translators: 1: html that opens a link, 2: html that closes a link. */
					__( 'This type of activity / change is no longer monitored. You can create your own custom event IDs to keep a log of such change. Read more about custom events %1$shere%2$s.', 'wp-security-audit-log' ),
					'<a href="https://melapress.com/support/kb/create-custom-events-wordpress-activity-log/" rel="noopener noreferrer" target="_blank">',
					'</a>'
				);
			}

			return $cached_message;
		}

		/**
		 * Gets alert meta.
		 *
		 * @param array $item    - Occurrence meta array.
		 *
		 * @return string Raw meta stored.
		 *
		 * @since 5.1.0
		 */
		public static function get_alert_meta( $item = \null ) {

			if ( ! isset( $item['meta_values'] ) ) {
				return '';
			}

			// Fill variables in message.
			$meta_array     = $item['meta_values'];
			$cached_message = '';

			foreach ( $meta_array as $name => $value ) {
				if ( \is_array( $value ) || is_object( $value ) ) {
					$value = \print_r( (array) $value, \true );
				}
				$cached_message .= $name . ' : ' . $value . '<br>';
			}

			return $cached_message;
		}

		/**
		 * Retrieves occurrences that have metadata that needs to be migrated to the occurrences table. This relates to the
		 * database schema change done in version 4.4.0.
		 *
		 * @param int $limit Limits the number of results.
		 *
		 * @return WSAL_Models_Occurrence[]
		 *
		 * @since 4.6.0
		 */
		public static function get_all_with_meta_to_migrate( $limit ) {

			$meta_keys = array_map(
				function ( $value ) {
					return '"' . $value . '"';
				},
				array_keys( self::$migrated_meta )
			);

			return self::load_multi_query(
				'SELECT o.* FROM `' . self::get_table_name() . '` o '
				. ' INNER JOIN `' . Metadata_Entity::get_table_name() . '` m '
				. ' ON m.occurrence_id = o.id '
				. ' WHERE m.name IN (' . implode( ',', $meta_keys ) . ') '
				. ' GROUP BY o.id '
				. ' ORDER BY created_on DESC '
				. ' LIMIT 0, %d;',
				array( $limit )
			);
		}

		/**
		 * Returns the value of a meta item.
		 *
		 * @param array  $data_collected - The array with all of the items, must contain at least the id of the occurrence.
		 * @param string $name    - Name of meta item.
		 * @param mixed  $default - Default value returned when meta does not exist.
		 *
		 * @return mixed The value, if meta item does not exist $default returned.
		 * @see WSAL_Adapters_MySQL_Occurrence::get_named_meta()
		 *
		 * @throws \Exception - if the id is not part of the $data_collected array.
		 *
		 * @since 4.6.0
		 */
		public static function get_meta_value( array $data_collected, $name, $default = array() ) {
			$result = $default;

			if ( ! isset( $data_collected['id'] ) ) {
				throw new \Exception( 'The id of the Occurrence is not present in the array', 1 );
			}

			// Check if the meta is part of the occurrences table.
			if ( in_array( $name, array_keys( self::$migrated_meta ), true ) ) {
				$property_name = self::$migrated_meta[ $name ];
				if ( isset( $data_collected[ $property_name ] ) ) {
					$result = $data_collected[ $property_name ];
				}
			} else {
				// Get meta adapter.
				$meta = Metadata_Entity::load_by_name_and_occurrence_id( $name, $data_collected['id'] );
				if ( is_null( $meta ) || ! array_key_exists( 'value', $meta ) ) {
					return $default;
				}

				$result = $meta['value'];
			}

			$result = maybe_unserialize( $result );
			if ( 'CurrentUserRoles' === $name && is_string( $result ) ) {
				$result = preg_replace( '/[\[\]"]/', '', $result );
				$result = explode( ',', $result );
			}

			return $result;
		}

		/**
		 * Saves the given data into the table
		 * The data should be in following format:
		 * field name => value
		 *
		 * It checks the given data array against the table fields and determines the types based on that, it stores the values in the table then.
		 *
		 * @param array $data - The data to be saved (check above about the format).
		 *
		 * @return int
		 *
		 * @since 4.6.0
		 */
		public static function save( $data ) {

			// Use today's date if not set up.
			if ( ! isset( $data['created_on'] ) ) {
				$data['created_on'] = microtime( true );

			}

			return parent::save( $data );
		}

		/**
		 * Returns a key-value pair of metadata.
		 *
		 * @param int   $occurrence_id - The ID of the occurrence to extract data from.
		 * @param array $record_data -  Array with the already prepared event data.
		 * @param \wpdb $connection - \wpdb connection to be used for name extraction.
		 *
		 * @return array
		 *
		 * @since 4.6.0
		 */
		public static function get_meta_array( int $occurrence_id, array $record_data = array(), $connection = null ) {
			$result = array();

			if ( null !== $connection ) {
				if ( $connection instanceof \wpdb ) {
					$_wpdb = $connection;
				}
			} else {
				$_wpdb = self::get_connection();
			}

			$metas = Metadata_Entity::load_array( 'occurrence_id = %d', array( $occurrence_id ), $_wpdb );
			foreach ( $metas as $meta ) {
				$result[ $meta['name'] ] = maybe_unserialize( $meta['value'] );
			}

			if ( empty( $record_data ) ) {
				$record_data = self::load_array( 'id = %d', array( $occurrence_id ), $_wpdb );
				$record_data = \reset( $record_data );
			}

			if ( isset( $record_data ) && ! empty( $record_data ) ) {
				foreach ( self::$migrated_meta as $meta_key => $column_name ) {
					$result[ $meta_key ] = $record_data[ $column_name ];
				}
			}

			return $result;
		}

		/**
		 * Returns a key-value pair of metadata.
		 *
		 * @param array $events -  Array with all the events to extract meta data for.
		 * @param \wpdb $connection - \wpdb connection to be used for name extraction.
		 *
		 * @return array
		 *
		 * @since 4.6.0
		 */
		public static function get_multi_meta_array( array &$events, $connection = null ) {

			$event_ids = array();

			foreach ( $events as &$event ) {
				if ( ! isset( $event['id'] ) ) {
					$events = array();
					return $events;
				}
				$event_ids[] = (int) $event['id'];
			}
			unset( $event );

			$events = \array_combine( $event_ids, $events );

			if ( null !== $connection ) {
				if ( $connection instanceof \wpdb ) {
					$_wpdb = $connection;
				}
			} else {
				$_wpdb = self::get_connection();
			}

			$metas = Metadata_Entity::load_by_occurrences_ids( $event_ids, $_wpdb );

			foreach ( $metas as $meta ) {
				$events[ $meta['occurrence_id'] ]['meta_values'][ $meta['name'] ] = maybe_unserialize( $meta['value'] );
			}

			foreach ( $events as &$event ) {
				foreach ( self::$migrated_meta as $meta_key => $column_name ) {
					$event['meta_values'][ $meta_key ] = $event[ $column_name ];
				}
			}
			unset( $event );

			return $events;
		}

		/**
		 * Get distinct values of IPs.
		 *
		 * @param int $limit - (Optional) Limit.
		 *
		 * @return array - Distinct values of IPs.
		 *
		 * @since 4.6.0
		 */
		public static function get_matching_ips( $limit = null ) {
			$_wpdb = self::get_connection();
			$sql   = 'SELECT DISTINCT client_ip FROM ' . self::get_table_name();
			if ( ! is_null( $limit ) ) {
				$sql .= ' LIMIT ' . $limit;
			}
			$ips    = $_wpdb->get_col( $sql );
			$result = array();
			foreach ( $ips as $ip ) {
				if ( 0 === strlen( trim( (string) $ip ) ) ) {
					continue;
				}
				array_push( $result, $ip );
			}

			$_wpdb = null;

			return array_unique( $result );
		}

		/**
		 * Search IPs for a specific ip text search
		 *
		 * @param string     $search - The IP search string.
		 * @param int|string $limit - The limit results.
		 *
		 * @return array
		 *
		 * @since 5.0.0
		 */
		public static function get_ips_logged_search( string $search = '', $limit = null ) {
			$_wpdb = self::get_connection();

			$sql = 'SELECT DISTINCT client_ip FROM ' . self::get_table_name();
			if ( ! empty( $search ) ) {
				$sql .= ' WHERE 1 AND client_ip LIKE "%' . $_wpdb->esc_like( $search ) . '%"';
			}
			if ( ! is_null( $limit ) ) {
				$sql .= ' LIMIT ' . $limit;
			}
			$ips    = $_wpdb->get_col( $sql );
			$result = array();
			foreach ( $ips as $ip ) {
				if ( 0 === strlen( trim( (string) $ip ) ) ) {
					continue;
				}
				array_push( $result, $ip );
			}

			return $result;
		}

		/**
		 * Collects and prepares all the metadata for the given array of the events.
		 *
		 * @param array $results - Array with all the data with events collected.
		 *
		 * @return array
		 *
		 * @since 4.6.0
		 */
		public static function prepare_with_meta_data( array &$results ): array {
			$prepared_array = array();

			$table_name      = self::get_table_name();
			$meta_table_name = Metadata_Entity::get_table_name();

			if ( is_array( reset( $results[0] ) ) || ( is_array( $results[0] ) && ! empty( $results[0] ) && ! isset( $results[0][0] ) ) ) {
				foreach ( $results as $result_row ) {
					if ( ! isset( $prepared_array[ $result_row[ $table_name . 'id' ] ] ) ) {
						foreach ( array_keys( self::$fields ) as $field ) {
							$prepared_array[ $result_row[ $table_name . 'id' ] ][ $field ] = $result_row[ $table_name . $field ];

							$prepared_array[ $result_row[ $table_name . 'id' ] ][ $field ] = self::cast_to_correct_type( $field, $prepared_array[ $result_row[ $table_name . 'id' ] ][ $field ] );
						}

						foreach ( self::$migrated_meta as $name => $new_name ) {
							$prepared_array[ $result_row[ $table_name . 'id' ] ]['meta_values'][ $name ] = \maybe_unserialize( $result_row[ $table_name . $new_name ] );
						}
					}

					$prepared_array[ $result_row[ $table_name . 'id' ] ]['meta_values'][ $result_row[ $meta_table_name . 'name' ] ] = \maybe_unserialize( $result_row[ $meta_table_name . 'value' ] );
				}
			}

			return $prepared_array;
		}

		/**
		 * Executes and returns the result of the given query.
		 *
		 * @param string $sql - The SQL query to execute.
		 * @param \wpdb  $connection - The database connection.
		 *
		 * @return array
		 *
		 * @since 5.0.0
		 */
		public static function load_query( string $sql, $connection = null ): array {

			if ( null !== $connection ) {
				if ( $connection instanceof \wpdb ) {
					$_wpdb = $connection;
				}
			} else {
				$_wpdb = self::get_connection();
			}

			$_wpdb->suppress_errors( true );

			$res = $_wpdb->get_results( $sql, ARRAY_A );
			if ( '' !== $_wpdb->last_error ) {
				if ( 1146 === self::get_last_sql_error( $_wpdb ) ) {
					if ( ( static::class )::create_table( $_wpdb ) ) {
						$res = $_wpdb->get_results( $sql, ARRAY_A );
					}
				}
			}
			$_wpdb->suppress_errors( false );

			return $res;
		}

		/**
		 * Cleans up the database, based on pruning date selected and enabled.
		 *
		 * @return void
		 *
		 * @since 5.0.0
		 */
		public static function prune_records() {

			$prune_enabled = Settings_Helper::get_boolean_option_value( 'pruning-date-e', false );

			if ( ! $prune_enabled ) {
				return;
			}

			$now       = time();
			$max_sdate = Plugin_Settings_Helper::get_pruning_date(); // Pruning date.
			$archiving = Settings_Helper::is_archiving_set_and_enabled();

		// phpcs:disable
		// phpcs:enable

			// Calculate limit timestamp.
			$max_stamp = $now - ( strtotime( $max_sdate ) - $now );

			$items = array();

			if ( $archiving ) {
				$connection_name = Settings_Helper::get_option_value( 'archive-connection' );

				$wsal_db = Connection::get_connection( $connection_name );

				$items = Delete_Records::delete( array(), 0, array( 'created_on <= %s' => intval( $max_stamp ) ), $wsal_db );
			}

			$main_items = Delete_Records::delete( array(), 0, array( 'created_on <= %s' => intval( $max_stamp ) ) );
		}
	}
}
