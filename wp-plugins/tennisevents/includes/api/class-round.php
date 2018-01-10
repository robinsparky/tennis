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
    
    private $ID;
    private $owner_ID;
    private $owner_type;
    
	private $matches;
    
    /**
     * Search for Draws that have a name 'like' the provided criteria
     */
    public static function search($criteria) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$sql = "select * from $table where name like '%%s%'";
		$safe = $wpdb->prepare($sql,$criteria);
		$rows = $wpdb->get_results($safe, ARRAY_A);
		
		error_log("Draw::search $wpdb->num_rows rows returned using criteria: $criteria");

		$col = array();
		foreach($rows as $row) {
            $obj = new Draw;
            self::mapData($obj,$row);
			$obj->isnew = FALSE;
			$col[] = $obj;
		}
		return $col;

    }
    
    /**
     * Find all Events belonging to a specific club;
     */
    public static function find($fk_id) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$sql = "select * from $table where event_ID = %d";
		$safe = $wpdb->prepare($sql,$fk_id);
		$rows = $wpdb->get_results($safe, ARRAY_A);
		
		error_log("Draw::find $wpdb->num_rows rows returned using event_ID=$fk_id");

		$col = array();
		foreach($rows as $row) {
            $obj = new Draw;
            self::mapData($obj,$row);
			$obj->isnew = FALSE;
			$col[] = $obj;
		}
		return $col;
    }

	/**
	 * Get instance of a Event using it's ID
	 */
    static public function get($id) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$sql = "select * from $table where ID=%d";
		$safe = $wpdb->prepare($sql,$id);
		$rows = $wpdb->get_results($safe, ARRAY_A);

		error_log("Draw::get(id) $wpdb->num_rows rows returned.");

		if($rows.length !== 1) {
			$obj = NULL;
		} else {
			$obj = new Draw;
			foreach($rows as $row) {
                self::mapData($obj,$row);
				$obj->isnew = FALSE;
			}
		}
		return $obj;
	}

	/*************** Instance Methods ****************/
	public function _construct() {
        $this->isnew = TRUE;
        $this->owner_ID = -1;
        $this->owner_type = 'draw';
    }
    
    public function getRoundNumber(){
        return $this->ID;
    }

    /**
     * Set this Round's owner type: draw or robin
     */
    public function setOwnerType($ot) {
        if(!is_string($ot) || strlen($ot) < 1 ) return;
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
        $this->owner_ID = $event;
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
    public function getChildren() {
        if(count($this->matches) === 0) $this->matches = Match::find($this->ID);
    }
    
    /**
     * Save this Draw to the daatabase
     */
    public function save() {
		if($this->isnew) $this->create();
		elseif ($this->isdirty) $this->update();
	}

	private function create() {
        global $wpdb;
        
        if($this->event_ID < 1) return;

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

        if($this->club_ID <= 0) return;

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
    
    /**
     * Map incoming data to an instance of Round
     */
    protected static function mapData($obj,$row) {
        $obj->ID = $row["ID"];
        $obj->owner_type = $row["owner_type"];
        $obj->owner_ID = $row["owner_ID"];
    }


} //end class