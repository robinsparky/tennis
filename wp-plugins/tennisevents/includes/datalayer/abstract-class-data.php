<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 
 * This is an abstract class from which all other 
 * data-based classes should inherit
 * @class  AbstractData
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
*/
abstract class AbstractData
{ 
    abstract static public function search($criteria);
    abstract static public function find(... $fk_criteria);

    abstract public function isValid();
    abstract public function getChildren($force=FALSE);
    abstract public function delete();

    
    /**
     * Map incoming row of data to an object
     * This function s/b called from child object's
     * implemetation as parent::mapData
     */
    static protected function mapData($obj,$row) {
        if(isset($row["ID"])) {
            $obj->ID = $row["ID"];
        }
        $obj->isnew = FALSE;
    }
    
    /**
     * Save this Object to the database
     */
    public function save() {
		if($this->isnew) $this->create();
		elseif ($this->isdirty) $this->update();
    }

    /**
     * Have there been changes to this object?
     */
    public function isDirty() {
        return $this->isdirty;
    }

    /**
     * Is this a new object?
     */
    public function isNew() {
        return $this->isnew;
    }

    /**
     * Get the ID of this object
     */
    public function getID() {
        return $this->ID;
    }

    protected function create() {
        if(!$this->isNew()) return;
        if(!$this->isValid()) return;
    }

    protected function update() {
        if(!$this->isDirty()) return;
        if(!$this->isValid()) return;
    }

    /**
     * Support for sorting array of objects
     * The object needs to supply the "indexFunction"
     */
    protected function objSort(&$objArray,$indexFunction,$sort_flags=0) {
        $indices = array();
        foreach($objArray as $obj) {
            $indeces[] = $indexFunction($obj);
        }
        return array_multisort($indeces,$objArray,$sort_flags);
    }  

	protected $isdirty = FALSE;
    protected $isnew   = TRUE;
    protected $ID;

} //end class
 