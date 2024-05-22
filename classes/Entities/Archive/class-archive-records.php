<?php
/**
 * Archiving records class.
 *
 * @package wsal
 * @author Stoil Dobrev <sdobreff@gmail.com>
 */

declare(strict_types=1);

namespace WSAL\Entities\Archive;

use WSAL\Entities\Abstract_Entity;
use WSAL\Entities\Metadata_Entity;
use WSAL\Entities\Occurrences_Entity;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\Entities\Archive\Archive_Records' ) ) {

	/**
	 * Responsible for the common entity operations.
	 *
	 * @since 4.6.0
	 */
	class Archive_Records {

		/**
		 * Archiving Occurrences Table.
		 * Read from current DB and copy into Archive DB.
		 *
		 * @param array   $args - Archive Database and limit by count OR by date.
		 * @param integer $count - If count is presented, there will be the number of occurrences affected by this method.
		 *
		 * @return bool
		 *
		 * @since 4.6.0
		 */
		public static function archive( $args, &$count = 0 ) {
			$_wpdb      = Abstract_Entity::get_connection();
			$archive_db = $args['archive_db'];

			$sql = 'SELECT * FROM ' . Occurrences_Entity::get_table_name();

			if ( ! empty( $args['by_date'] ) ) {
				$sql = 'SELECT * FROM ' . Occurrences_Entity::get_table_name() . ' WHERE created_on <= ' . $args['by_date'];
			}

			if ( ! empty( $args['by_limit'] ) ) {
				$sql = 'SELECT occ.* FROM ' . Occurrences_Entity::get_table_name() . ' occ
			LEFT JOIN (SELECT id FROM ' . Occurrences_Entity::get_table_name() . ' order by created_on DESC limit ' . $args['by_limit'] . ') as ids
			on ids.id = occ.id
			WHERE ids.id IS NULL';
			}

			if ( ! empty( $args['last_created_on'] ) ) {
				$sql .= ' AND created_on > ' . $args['last_created_on'];
			}
			$sql .= ' ORDER BY created_on ASC';
			if ( ! empty( $args['limit'] ) ) {
				$sql .= ' LIMIT ' . $args['limit'];
			}

			$_wpdb->suppress_errors( true );

			$occurrences = $_wpdb->get_results( $sql, ARRAY_A );

			if ( '' !== $_wpdb->last_error ) {
				if ( 1146 === Occurrences_Entity::get_last_sql_error( $_wpdb ) ) {
					if ( Occurrences_Entity::create_table() ) {
						$occurrences = $_wpdb->get_results( $sql, ARRAY_A );
					}
				}
			}

			$_wpdb->suppress_errors( false );

			$occurrences_ids        = '';
			$delete_occurrences_sql = 'DELETE FROM ' . Occurrences_Entity::get_table_name() . ' WHERE id IN (';
			$delete_meta_sql        = 'DELETE FROM ' . Metadata_Entity::get_table_name() . ' WHERE occurrence_id IN (';
			$select_meta_data_sql   = 'SELECT * FROM ' . Metadata_Entity::get_table_name() . ' WHERE occurrence_id IN (';

			// Insert data to Archive DB.
			if ( ! empty( $occurrences ) ) {
				$count = count( $occurrences );
				$sql   = 'REPLACE INTO ' . Occurrences_Entity::get_table_name( $archive_db ) . ' ( id, site_id, alert_id, created_on, client_ip, severity, object, event_type, user_agent, user_roles, username, user_id, session_id, post_status, post_type, post_id ) VALUES ';
				foreach ( $occurrences as $entry ) {

					if ( in_array( $entry['alert_id'], array( 1000, 1001, 1002, 1003 ) ) ) {
						if ( empty( $entry['user_id'] ) && empty( $entry['username'] ) ) {
							$entry['user_id']  = 0;
							$entry['username'] = 'Unknown user';
						} elseif ( empty( $entry['username'] ) ) {
							if ( 0 === (int) $entry['user_id'] ) {
								$entry['username'] = 'Unknown User';
							} else {
								$user = \get_user_by( 'ID', $entry['user_id'] );
								if ( $user ) {
									$entry['username'] = $user->user_login;
								} else {
									$entry['username'] = 'Deleted';
								}
							}
						} elseif ( empty( (int) $entry['user_id'] ) ) {
							if ( 0 === (int) $entry['user_id'] ) {
								$entry['username'] = 'Unknown User';
							} else {
								$user = \get_user_by( 'login', $entry['username'] );
								if ( $user ) {
									$entry['user_id'] = $user->ID;
								} else {
									$entry['user_id'] = 0;
								}
							}
						}
					}

					$format_array = array(
						'id'          => '%d',
						'site_id'     => '%d',
						'alert_id'    => '%d',
						'created_on'  => '%f',
						'client_ip'   => '%s',
						'severity'    => '%d',
						'object'      => '%s',
						'event_type'  => '%s',
						'user_agent'  => '%s',
						'user_roles'  => '%s',
						'username'    => '%s',
						'user_id'     => '%d',
						'session_id'  => '%s',
						'post_status' => '%s',
						'post_type'   => '%s',
						'post_id'     => '%d',
					);

					$values_array = array(
						'id'          => intval( $entry['id'] ),
						'site_id'     => intval( $entry['site_id'] ),
						'alert_id'    => intval( $entry['alert_id'] ),
						'created_on'  => $entry['created_on'],
						'client_ip'   => $entry['client_ip'],
						'severity'    => $entry['severity'],
						'object'      => $entry['object'],
						'event_type'  => $entry['event_type'],
						'user_agent'  => $entry['user_agent'],
						'user_roles'  => $entry['user_roles'],
						'username'    => $entry['username'],
						'user_id'     => intval( $entry['user_id'] ),
						'session_id'  => $entry['session_id'],
						'post_status' => $entry['post_status'],
						'post_type'   => $entry['post_type'],
						'post_id'     => intval( $entry['post_id'] ),
					);

					foreach ( \array_keys( $format_array ) as $key ) {
						if ( null === $entry[ $key ] ) {
							$format_array[ $key ] = 'NULL';
							unset( $values_array[ $key ] );
						}
					}

					$sql .= $archive_db->prepare(
						'( ' . \implode( ', ', $format_array ) . ' ), ',
						$values_array
					);

					$occurrences_ids .= $entry['id'] . ', ';
				}

				$delete_occurrences_sql .= rtrim( $occurrences_ids, ', ' ) . ')';
				$select_meta_data_sql   .= rtrim( $occurrences_ids, ', ' ) . ')';

				$sql = rtrim( $sql, ', ' );

				$archive_db->suppress_errors( true );

				$data = $archive_db->query( $sql );
				if ( '' !== $archive_db->last_error ) {
					if ( 1146 === Occurrences_Entity::get_last_sql_error( $archive_db ) ) {
						if ( Occurrences_Entity::create_table( $archive_db ) ) {
							$data = $archive_db->query( $sql );
						}
					}
				}
				$archive_db->suppress_errors( false );

				// Metadata archiving.
				$_wpdb->suppress_errors( true );

				$metadata = $_wpdb->get_results( $select_meta_data_sql, ARRAY_A );

				if ( '' !== $_wpdb->last_error ) {
					if ( 1146 === Metadata_Entity::get_last_sql_error( $_wpdb ) ) {
						if ( Metadata_Entity::create_table( $_wpdb ) ) {
							$metadata = $_wpdb->get_results( $sql, ARRAY_A );
						}
					}
				}

				$_wpdb->suppress_errors( false );

				// Insert data to Archive DB.
				if ( ! empty( $metadata ) ) {

					$sql = 'INSERT INTO ' . Metadata_Entity::get_table_name( $archive_db ) . ' (occurrence_id, name, value) VALUES ';
					foreach ( $metadata as $entry ) {
						$sql .= $archive_db->prepare(
							'( %d, %s, %s ), ',
							intval( $entry['occurrence_id'] ),
							$entry['name'],
							$entry['value']
						);

						$delete_meta_sql .= $entry['occurrence_id'] . ', ';
					}
					$sql             = rtrim( $sql, ', ' );
					$delete_meta_sql = rtrim( $delete_meta_sql, ', ' ) . ')';

					$archive_db->suppress_errors( true );

					$data = $archive_db->query( $sql );
					if ( '' !== $archive_db->last_error ) {
						if ( 1146 === Metadata_Entity::get_last_sql_error( $archive_db ) ) {
							if ( Metadata_Entity::create_table( $archive_db ) ) {
								$data = $archive_db->query( $sql );
							}
						}
					}
					$archive_db->suppress_errors( false );

					// Delete the records.
					$_wpdb->query( $delete_meta_sql );
				}
				$_wpdb->query( $delete_occurrences_sql );

				return true;
			} else {
				return false;
			}
		}
	}
}
