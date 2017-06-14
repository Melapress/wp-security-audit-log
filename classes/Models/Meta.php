<?php
/**
 * @package Wsal
 *
 * Metadata model is the model for the Metadata adapter,
 * used for save and update the metadata.
 */
class WSAL_Models_Meta extends WSAL_Models_ActiveRecord
{
    public $id = 0;
    public $occurrence_id = 0;
    public $name = '';
    public $value = array(); // force mixed type
    protected $adapterName = "Meta";

    /**
     * Save Metadata into Adapter.
     * @see WSAL_Adapters_MySQL_ActiveRecord::Save()
     * @return integer|boolean Either the number of modified/inserted rows or false on failure.
     */
    public function SaveMeta()
    {
        $this->_state = self::STATE_UNKNOWN;
        $updateId = $this->getId();
        $result = $this->getAdapter()->Save($this);

        if ($result !== false) {
            $this->_state = (!empty($updateId))?self::STATE_UPDATED:self::STATE_CREATED;
        }
        return $result;
    }

    /**
     * Update Metadata by name and occurrence_id.
     * @see WSAL_Adapters_MySQL_Meta::LoadByNameAndOccurenceId()
     * @param string $name meta name
     * @param mixed $value meta value
     * @param integer $occurrenceId occurrence_id
     */
    public function UpdateByNameAndOccurenceId($name, $value, $occurrenceId)
    {
        $meta = $this->getAdapter()->LoadByNameAndOccurenceId($name, $occurrenceId);
        if (!empty($meta)) {
            $this->id = $meta['id'];
            $this->occurrence_id = $meta['occurrence_id'];
            $this->name = $meta['name'];
            $this->value = $value;
            $this->saveMeta();
        } else {
            $this->occurrence_id = $occurrenceId;
            $this->name = $name;
            $this->value = $value;
            $this->SaveMeta();
        }
    }
}
