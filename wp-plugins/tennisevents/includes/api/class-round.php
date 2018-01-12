<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require('abstract-class-data.php');
require('class-match.php');

/** 
 * Data and functions for Tennis Event Round(s)
 * @class  Round
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
*/
class Round extends AbstractData
{ 
    private static $tablename = 'tennis_round';
    
    private $owner_ID;
    private $owner_type;
    
	private $matches;
    
    /**
     * Search for Rounds that have a name 'like' the provided criteria
     */
    public static function search($criteria) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$sql = "select * from $table where owner_type like '%%s%'";
		$safe = $wpdb->prepare($sql,$criteria);
		$rows = $wpdb->get_results($safe, ARRAY_A);
		
		error_log("Round::search $wpdb->num_rows rows returned using criteria: $criteria");

		$col = array();
		foreach($rows as $row) {
            $obj = new Round;
            self::mapData($obj,$row);
			$col[] = $obj;
		}
		return $col;

    }
    
    /**
     * Find all Rounds belonging to a specific Draw;
     */
    public static function find($fk_id, $context=NULL) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$sql = "select * from $table where draw_ID = %d";
		$safe = $wpdb->prepare($sql,$fk_id);
		$rows = $wpdb->get_results($safe, ARRAY_A);
		
		error_log("Round::find $wpdb->num_rows rows returned using draw_ID=$fk_id");

		$col = array();
		foreach($rows as $row) {
            $obj = new Round;
            self::mapData($obj,$row);
			$col[] = $obj;
		}
		return $col;
    }

	/**
	 * Get instance of a Round using it's ID
	 */
    static public function get($id) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$sql = "select * from $table where ID=%d";
		$safe = $wpdb->prepare($sql,$id);
		$rows = $wpdb->get_results($safe, ARRAY_A);

		error_log("Round::get(id) $wpdb->num_rows rows returned.");

        $obj = NULL;
		if($rows.length === 1) {
            $obj = new Round;
            self::mapData($obj,$rows[0]);
		}
		return $obj;
	}

	/*************** Instance Methods ****************/
	public function _construct() {
        $this->isnew = TRUE;
        $this->init();
    }
    
    public function getRoundNumber(){
        return $this->getID();
    }


    /**
     * Set this Round's owner type: draw or robin
     */
    public function setOwnerType($ot) {
        if(!is_string($ot)) return;
        if($ot === 'draw' || $ot === 'robin'){
            $this->owner_type = $ot;
            $this->isdirty = TRUE;
        }
    }

    public function getOwnerType() {
        return $this->owner_type;
    }

    /**
     * Assign this Round to a Tennis Draw or a Round Robin
     */
    public function setOwnerId($owner) {
        if(!is_numeric($owner) || $owner < 1) return;
        $this->owner_ID = $owner;
        $this->isdirty = TRUE;
    }

    /**
     * Get this Round's owner id.
     */
    public function getOwnerId() {
        return $this->owner_ID;
    }


	/**
	 * Get all my children!
	 * 1. Matches
	 */
    public function getChildren($force) {
        if(count($this->matches) === 0  || $force) $this->matches = Match::find($this->ID);
    }
    
    /**
     * Save this Round to the database
     */
    public function save() {
		if($this->isnew) $this->create();
		elseif ($this->isdirty) $this->update();
	}

	private function create() {
        global $wpdb;
        
        if(!$this->isValid()) return;

        $values         = array('owner_type' => $this->owner_type
                               ,'owner_ID' => $this->owner_ID);
		$formats_values = array('%s','%d');
		$wpdb->insert($wpdb->prefix . self::$tablename, $values, $formats_values);
		$this->ID = $wpdb->insert_id;
		$this->isnew = FALSE;

		error_log("Round::create $wpdb->rows_affected rows affected.");

		return $wpdb->rows_affected;
	}

	private function update() {
		global $wpdb;

        if(!$this->isValid()) return;

        $values         = array('owner_type' => $this->owner_type
                               ,'owner_ID' => $this->owner_ID);
		$formats_values = array('%s','%d');
		$where          = array('ID' => $this->ID);
		$formats_where  = array('%d');
		$wpdb->update($wpdb->prefix . self::$tablename, $values, $where, $formats_values, $formats_where);
		$this->isdirty = FALSE;

		error_log("Round::update $wpdb->rows_affected rows affected.");

		return $wpdb->rows_affected;
	}

    public function delete() {

    }

    protected function isValid() {
        $isvalid = TRUE;
        if(!isset($this->owner_ID)) $isvalid = FALSE;
        if(!isset($this->owner_type)) $isvalid = FALSE;
        
        return $isvalid;
    }
    
    /**
     * Map incoming data to an instance of Round
     */
    protected static function mapData($obj,$row) {
        parent::mapData($obj,$row);
        $obj->owner_type = $row["owner_type"];
        $obj->owner_ID = $row["owner_ID"];
    }

    private function init() {
        $this->owner_type = NULL;
        $this->owner_ID = NULL;
    }


} //end class