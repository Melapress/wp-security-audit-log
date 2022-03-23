<?php
/**
 * Class: Meta Model Class
 *
 * Metadata model is the model for the Metadata adapter,
 * used for save and update the metadata.
 *
 * @package wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Metadata model is the model for the Metadata adapter,
 * used for save and update the metadata.
 *
 * @package wsal
 */
class WSAL_Models_Meta extends WSAL_Models_ActiveRecord {

	/**
	 * Meta ID.
	 *
	 * @var integer
	 */
	public $id = 0;

	/**
	 * Occurrence ID.
	 *
	 * @var integer
	 */
	public $occurrence_id = 0;

	/**
	 * Meta Name.
	 *
	 * @var string
	 */
	public $name = '';

	/**
	 * Meta Value.
	 *
	 * @var array
	 */
	public $value = array(); // Force mixed type.

	/**
	 * Model Name.
	 *
	 * @var string
	 */
	protected $adapter_name = 'Meta';

	/**
	 * Save Metadata into Adapter.
	 *
	 * @return integer|boolean Either the number of modified/inserted rows or false on failure.
	 * @see WSAL_Adapters_MySQL_ActiveRecord::save()
	 */
	public function save_meta() {
		$this->state = self::STATE_UNKNOWN;
		$update_id   = $this->get_id();
		$result      = $this->get_adapter()->save( $this );

		if ( false !== $result ) {
			$this->state = ( ! empty( $update_id ) ) ? self::STATE_UPDATED : self::STATE_CREATED;
		}
		return $result;
	}

	/**
	 * Update Metadata by name and occurrence_id.
	 *
	 * @param string  $name          - Meta name.
	 * @param mixed   $value         - Meta value.
	 * @param integer $occurrence_id - Occurrence_id.
	 *
	 * @see WSAL_Adapters_MySQL_Meta::load_by_name_and_occurrence_id()
	 */
	public function update_by_name_and_occurrence_id( $name, $value, $occurrence_id ) {
		$meta = $this->get_adapter()->load_by_name_and_occurrence_id( $name, $occurrence_id );
		if ( ! empty( $meta ) ) {
			$this->id            = $meta['id'];
			$this->occurrence_id = $meta['occurrence_id'];
			$this->name          = $meta['name'];
			$this->value         = maybe_serialize( $value );
			$this->save_meta();
		} else {
			$this->occurrence_id = $occurrence_id;
			$this->name          = $name;
			$this->value         = maybe_serialize( $value );
			$this->save_meta();
		}
	}
}
