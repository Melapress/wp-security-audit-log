<?php
/**
 * Adapter: Meta data.
 *
 * MySQL database Metadata class.
 *
 * @package wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MySQL database TmpUser class.
 *
 * This class is used for create a temporary table to store the WP users
 * when the External DB Add-On is activated and the Alerts are stored on an external DB
 * because the query between plugin tables and the internal wp_uses table is not possible.
 *
 * @see WSAL_Adapters_MySQL_ActiveRecord->GetReportGrouped()
 * @package wsal
 */
class WSAL_Adapters_MySQL_TmpUser extends WSAL_Adapters_MySQL_ActiveRecord {

	/**
	 * Contains the table name.
	 *
	 * @var string
	 */
	protected $table = 'wsal_tmp_users';

	/**
	 * {@inheritDoc}
	 *
	 * @return WSAL_Models_TmpUser
	 */
	public function get_model() {
		return new WSAL_Models_TmpUser();
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_install_query( $prefix = false ) {
		$_wpdb      = $this->connection;
		$table_name = ( $prefix ) ? $this->get_wp_table() : $this->get_table();
		$sql        = 'CREATE TABLE IF NOT EXISTS ' . $table_name . ' (' . PHP_EOL;
		$sql       .= 'ID BIGINT NOT NULL,' . PHP_EOL;
		$sql       .= 'user_login VARCHAR(60) NOT NULL,' . PHP_EOL;
		$sql       .= 'INDEX (ID)' . PHP_EOL;
		$sql       .= ') ' . $_wpdb->get_charset_collate();

		return $sql;
	}
}
