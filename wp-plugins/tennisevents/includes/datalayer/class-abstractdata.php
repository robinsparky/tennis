<?php
use \commonlib\BaseLogger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once('data-exceptions.php');

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
    abstract static public function find(...$fk_criteria);
    abstract static public function get(int ...$pks);
    abstract public function isValid();

	protected $isdirty = FALSE;
    protected $isnew   = TRUE;
    protected $ID;
    protected $log;
    
   //Default constructor
   public function __construct( $logger=false ) {
        $this->isnew = true;
        $this->log = new BaseLogger( $logger );
    }
    
    /**
     * Map incoming row of data to an object
     * This function s/b called from child object's
     * implemetation as parent::mapData
     */
    static protected function mapData( $obj, $row ) {
        $obj->ID = NULL;
        if( isset($row["ID"]) ) {
            $obj->ID = $row["ID"];
        }
        $obj->isnew = false;
    }
    
    /**
     * Save this Object to the database
     */
    public function save():int {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$calledBy = debug_backtrace()[1]['function'];
        error_log("{$loc} ... called by {$calledBy}");
        
        $result = 0;
		if($this->isNew()) $result = $this->create();
        elseif ($this->isDirty()) $result = $this->update();
        return $result;
    }

    /**
     * Have there been changes to this object?
     */
    public function isDirty() {
        return $this->isdirty;
    }

    public function setDirty() {
        return $this->isdirty = true;
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
    public function getID():int {
        if(isset($this->ID)) return $this->ID;
        else return 0;
    }

    protected function create() {
        if(!$this->isNew()) return 0;
        if(!$this->isValid()) return 0;
    }

    protected function update() {
        if(!$this->isDirty()) return 0;
        if(!$this->isValid()) return 0;
    }

    /**
     * Support for sorting array of objects
     * The object needs to supply the "indexFunction"
     */
    protected function objSort( &$objArray, $indexFunction, $sort_flags=0) {
        $indices = array();
        foreach( $objArray as $obj ) {
            $indices[] = $indexFunction( $obj );
        }
        return array_multisort( $indices, $objArray, $sort_flags );
    }  

} //end class
 