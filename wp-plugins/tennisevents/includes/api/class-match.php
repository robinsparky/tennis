<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require('abstract-class-data.php');
require('class-entry.php');

/** 
 * Data and functions for Tennis Event Round(s)
 * @class  Round
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
*/
class Match extends AbstractData
{ 
    private static $tablename = 'tennis_match';
    
    private $round_ID;
    
    private $home;
    private $visitor;
    
    /**
     * Search for Draws that have a name 'like' the provided criteria
     */
    public static function search($criteria) {
		$col = array();

		return $col;

    }
    
    /**
     * Find all Matches belonging to a specific Round;
     */
    public static function find($fk_id) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$sql = "select * from $table where round_ID = %d";
		$safe = $wpdb->prepare($sql,$fk_id);
		$rows = $wpdb->get_results($safe, ARRAY_A);
		
		error_log("Match::find $wpdb->num_rows rows returned using event_ID=$fk_id");

		$col = array();
		foreach($rows as $row) {
            $obj = new Match;
            self::mapData($obj,$row);
			$obj->isnew = FALSE;
			$col[] = $obj;
		}
		return $col;
    }

	/**
	 * Get instance of a Match using it's ID
	 */
    static public function get($id) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$sql = "select * from $table where ID=%d";
		$safe = $wpdb->prepare($sql,$id);
		$rows = $wpdb->get_results($safe, ARRAY_A);

		error_log("Match::get(id) $wpdb->num_rows rows returned.");

		if($rows.length !== 1) {
			$obj = NULL;
		} else {
			$obj = new Match;
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
        $this->round_ID = -1;
    }
    
    public function getMatchNumber(){
        return $this->ID;
    }

    /**
     * Set the Home opponent for this match
     */
    public function setHomeEntrant($h) {
        if($h instanceof Entry ) {
            $this->home = $h;
            $this->isdirty = TRUE;
        }
    }

    public function getHomeEntrant() {
        return $this->home;
    }
    
    /**
     * Set the Visitor opponent for this match
     */
    public function setVisitorEntrant($v) {
        if($v instanceof Entry ) {
            $this->visitor = $h;
            $this->isdirty = TRUE;
        }
    }

    public function getVisitorEntrant() {
        return $this->visitor;
    }

    public function getOwnerType() {
        return $this->owner_type;
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