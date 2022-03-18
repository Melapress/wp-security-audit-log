<?php
/**
 * Class: Abstract Connector.
 *
 * Abstract class used as a class loader.
 *
 * @package wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once 'wp-db-custom.php';

/**
 * Adapter Classes loader class.
 *
 * Abstract class used as a class loader.
 *
 * @package wsal
 */
abstract class WSAL_Connector_AbstractConnector {

	/**
	 * Connection Variable.
	 *
	 * @var null
	 */
	protected $connection = null;

	/**
	 * Adapter Base Path.
	 *
	 * @var null
	 */
	protected $adapters_base_path = null;

	/**
	 * Adapter Directory Name.
	 *
	 * @var null
	 */
	protected $adapters_dir_name = null;

	/**
	 * Method: Constructor.
	 *
	 * @param  string $adapters_dir_name - Adapter directory name.
	 */
	public function __construct( $adapters_dir_name = null ) {
		$this->adapters_base_path = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'Adapters' . DIRECTORY_SEPARATOR;
		if ( ! empty( $adapters_dir_name ) ) {
			$this->adapters_dir_name = $adapters_dir_name;
			$adapters_directory      = $this->get_adapters_directory();
			require_once $adapters_directory . DIRECTORY_SEPARATOR . 'ActiveRecordAdapter.php';
			require_once $adapters_directory . DIRECTORY_SEPARATOR . 'MetaAdapter.php';
			require_once $adapters_directory . DIRECTORY_SEPARATOR . 'OccurrenceAdapter.php';
			require_once $adapters_directory . DIRECTORY_SEPARATOR . 'QueryAdapter.php';
			require_once $adapters_directory . DIRECTORY_SEPARATOR . 'TmpUserAdapter.php';
			do_action( 'wsal_require_additional_adapters' );
		}
	}

	/**
	 * Method: Get adapters directory.
	 */
	public function get_adapters_directory() {
		if ( ! empty( $this->adapters_base_path ) && ! empty( $this->adapters_dir_name ) ) {
			return $this->adapters_base_path . $this->adapters_dir_name;
		} else {
			return false;
		}
	}
}
