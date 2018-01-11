<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require('abstract-class-data.php');
require('class-entrant.php');
require('class-round.php');

/** 
 * Data and functions for Tennis Event Draw(s)
 * @class  Draw
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
*/
class Draw extends AbstractData
{ 
    private static $tablename = 'tennis_draw';
    
    private $event_ID;
    private $name;
    private $elimination;
    
	private $entries;
    private $rounds;
    
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
		//Context not really used here
		$col = 'event_ID';
		$sql = "select * from $table where $col = %d";
		$safe = $wpdb->prepare($sql,$fk_id);
		$rows = $wpdb->get_results($safe, ARRAY_A);
		
		error_log("Draw::find $wpdb->num_rows rows returned using $col=$fk_id");

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
        $this->event_ID = -1;
	}

    /**
     * Set a new value for a name of this Draw
     */
	public function setName($name) {
        if(strlen($name) < 1) return;
		$this->name = $name;
		$this->dirty = TRUE;
    }
    
    /**
     * Get the name of this Draw
     */
    public function getName() {
        return $this->name;
    }

    /**
     * Assign this Draw to a Tennis Event
     */
    public function setEventId($event) {
        if(!is_numeric($event) || $event < 1) return;
        $this->event_ID = $event;
        $this->isdirty = TRUE;
    }

    /**
     * Get this Draw's event id.
     */
    public function getEventId() {
        return $this->event_ID;
    }

    /**
     * Set the elimination type for this Draw
     */
    public function setEliminationType($elim) {
        if(strlen($elim) < 2) return;
		$this->elimination = $elim;
		$this->dirty = TRUE;

    }

    public function getEliminationType() {
        return $this->elimination;
    }

	/**
	 * Get all my children!
	 * 1. Draws
	 * 2. Courts
	 */
    public function getChildren() {
		$this->getEntries();
		$this->getRounds();
	}

	/**
	 * Get all entries for this Draw.
	 */
	public function getEntries() {
        if(count($this->entries) === 0) $this->entries = Entrant::find($this->ID,'draw');
	}

	/**
	 * Get all Rounds in this Draw.
	 */
	public function getRounds() {
        if(count($this->rounds) === 0) $this->rounds = Round::find($this->ID);
	}

    /**
     * Save this Draw to the database
     */
    public function save() {
		if($this->isnew) $this->create();
		elseif ($this->isdirty) $this->update();
	}

	private function create() {
        global $wpdb;
        
        if($this->event_ID < 1) return;

        $values         = array('name' => $this->name
                               ,'event_ID' => $this->event_ID
                               ,'elimination' => $this->elimination);
		$formats_values = array('%s','%d','%s');
		$wpdb->insert($wpdb->prefix . self::$tablename, $values, $formats_values);
		$this->ID = $wpdb->insert_id;
		$this->isnew = FALSE;

		error_log("Draw::create $wpdb->rows_affected rows affected.");

		return $wpdb->rows_affected;
	}

	private function update() {
		global $wpdb;

        if($this->club_ID <= 0) return;

        $values         = array('name' => $this->name
                               ,'event_ID' => $this->event_ID
                               ,'elimination' => $this->elimination);
		$formats_values = array('%s','%d','%s');
		$where          = array('ID' => $this->ID);
		$formats_where  = array('%d');
		$wpdb->update($wpdb->prefix . self::$tablename,$values,$where,$formats_values,$formats_where);
		$this->isdirty = FALSE;

		error_log("Draw::update $wpdb->rows_affected rows affected.");

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
        $obj->event_ID = $row["event_ID"];
        $obj->elimination = $row["elimination"];
    }


} //end class