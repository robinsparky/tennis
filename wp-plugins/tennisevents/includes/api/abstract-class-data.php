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
    abstract static public function find($fk_id,$context=NULL);
    abstract static public function get($id);
    abstract static protected function mapData($obj,$row);

    abstract public function isValid();
    abstract public function getChildren();
    abstract public function delete();
    
    /**
     * Save this Object to the database
     */
    public function save() {
		if($this->isnew) $this->create();
		elseif ($this->isdirty) $this->update();
    }

    public function isDirty() {
        return $this->isdirty;
    }

    public function isNew() {
        return $isnew;
    }

    public function getID() {
        return $this->ID;
    }

    protected function objSort(&$objArray,$indexFunction,$sort_flags=0) {
        $indices = array();
        foreach($objArray as $obj) {
            $indeces[] = $indexFunction($obj);
        }
        return array_multisort($indeces,$objArray,$sort_flags);
    }  

	private $isdirty = FALSE;
    private $isnew   = TRUE;
    private $ID;

} //end class
 