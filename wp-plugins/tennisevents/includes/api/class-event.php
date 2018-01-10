<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require('abstract-class-data.php');
require('class-draw.php');
require('class-team.php');

/** 
 * Data and functions for Tennis Event(s)
 * @class  Event
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
*/
class Event extends AbstractData
{ 
    private static $tablename = 'tennis_event';
    
	private $name;
    private $club_ID;
    
	private $draws;
    private $teams;
    
    /**
     * Search for Events have a name 'like' the provided criteria
     */
    public static function search($criteria) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$sql = "select * from $table where name like '%%s%'";
		$safe = $wpdb->prepare($sql,$criteria);
		$rows = $wpdb->get_results($safe, ARRAY_A);
		
		error_log("Event::search $wpdb->num_rows rows returned using criteria: $criteria");

		$col = array();
		foreach($rows as $row) {
            $obj = new Event;
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
		$sql = "select * from $table where club_ID = %d";
		$safe = $wpdb->prepare($sql,$fk_id);
		$rows = $wpdb->get_results($safe, ARRAY_A);
		
		error_log("Event::find $wpdb->num_rows rows returned using club_ID=$fk_id");

		$col = array();
		foreach($rows as $row) {
            $obj = new Event;
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

		error_log("Event::get(id) $wpdb->num_rows rows returned.");

		if($rows.length !== 1) {
			$obj = NULL;
		} else {
			$obj = new Event;
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
        $this->club_ID = -1;
	}

    /**
     * Set a new value for a name of an Event
     */
	public function setName($name) {
        if(strlen($name) < 2) return;
		$this->name = $name;
		$this->dirty = TRUE;
    }
    
    /**
     * Get the name of this object
     */
    public function getName() {
        return $this->name;
    }

    /**
     * Assign this Event to a Tennis Club
     */
    public function setClubId($club) {
        if(!is_numeric($club) || $club < 1) return;
        $this->club_ID = $club;
        $this->isdirty = TRUE;
    }

    public function getClubId() {
        return $this->club_ID;
    }

	/**
	 * Get all my children!
	 * 1. Draws
	 * 2. Teams
	 */
    public function getChildren() {
		$this->getDraws();
		$this->getTeams();
	}

	/**
	 * Get all events for this club.
	 */
	public function getDraws() {
        if(count($this->draws) === 0) $this->draws = Draw::find($this->ID);
	}

	/**
	 * Get all courts in this club.
	 */
	public function getTeams() {
        if(count($this->teams) === 0) $this->teams = Team::find($this->ID);
	}

    /**
     * Save this Event to the daatabase
     */
    public function save() {
		if($this->isnew) $this->create();
		elseif ($this->isdirty) $this->update();
	}

	private function create() {
        global $wpdb;
        
        if($this->club_ID < 1) return;

        $values         = array('name'=>$this->name
                               ,'club_ID'=>$this->club_ID);
		$formats_values = array('%s','%d');
		$wpdb->insert($wpdb->prefix . self::$tablename, $values, $formats_values);
		$this->ID = $wpdb->insert_id;
		$this->isnew = FALSE;

		error_log("Event::create $wpdb->rows_affected rows affected.");

		return $wpdb->rows_affected;
	}

	private function update() {
		global $wpdb;

        if($this->club_ID <= 0) return;

        $values         = array('name' => $this->name
                               ,'club_ID' => $this->club_ID);
		$formats_values = array('%s','%d');
		$where          = array('ID' => $this->ID);
		$formats_where  = array('%d');
		$wpdb->update($wpdb->prefix . self::$tablename,$values,$where,$formats_values,$formats_where);
		$this->isdirty = FALSE;

		error_log("Event::update $wpdb->rows_affected rows affected.");

		return $wpdb->rows_affected;
	}

    public function delete() {

    }
    
    /**
     * Map incoming data to an instance of Event
     */
    protected static function mapData($obj,$row) {
        $obj->ID = $row["ID"];
        $obj->name = $row["name"];
        $obj->club_ID = $row["club_ID"];
    }


} //end class