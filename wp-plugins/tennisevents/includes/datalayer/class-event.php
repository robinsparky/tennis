<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require('abstract-class-data.php');
require('class-entrant.php');
require('class-team.php');

/** 
 * Data and functions for Event(s)
 * Events are organized into a hierarchy (1 level for now)
 * @class  Event
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
*/
class Event extends AbstractData
{ 
	private static $tablename = 'tennis_event';
	
	/**
	 * Event types
	 */
	const TOURNAMENT = 'tournament';
	const LEAGUE     = 'league';
	const LADDER     = 'ladder';

	/**
	 * Formats
	 */
	const SINGLE_ELIM = 'single';
	const DOUBLE_ELIM = 'double';
	const ROUND_ROBIN = 'robin';

	private $name;
	private $parent_ID; //parent Event ID
	private $parent; //parent Event
	private $event_type; //tournament, league, ladder
	private $format; //single elim, double elim, round robin
    
	private $draw; //array of entrants
    private $childEvents; //array
    
    /**
     * Search for Events have a name 'like' the provided criteria
     */
    public static function search($criteria) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$sql = "select ID,event_type,name,format,parent_ID from $table where name like '%%s%'";
		$safe = $wpdb->prepare($sql,$criteria);
		$rows = $wpdb->get_results($safe, ARRAY_A);
		
		error_log("Event::search $wpdb->num_rows rows returned using criteria: $criteria");

		$col = array();
		foreach($rows as $row) {
            $obj = new Event;
            self::mapData($obj,$row);
			$col[] = $obj;
		}
		return $col;
    }
    
    /**
     * Find all Events belonging to a specific club
	 * Or all child Events of a specific parent Events
     */
    public static function find(... $fk_criteria) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$joinTable = "{$wpdb->prefix}tennis_club_event";
		$clubTable = "{$wpdb->prefix}tennis_club";
		$col = array();
		$col_value;

		if(count($fk_criteria.keys) === 0) {
			//No column name specified
			if(count($fk_criteria) !== 0) {
				$col_value = $fk_criteria[0];
				$sql = "select e.ID,e.event_type,e.name,e.format,e.parent_ID 
						from $table e
						inner join $joinTable as j on j.event_ID = e.ID
						inner join $clubTable as c on c.ID = j.club_ID
						where c.ID = %d;";
			}
			else {
				return $col;
			}
		} //column name is specified
		else {
			//All events who are children of specified Event
			if(isset($fk_criteria["parent_ID"])) {
				$col_value = $fk_criteria["parent_ID"];
				$sql = "select ce.ID,ce.event_type,ce.name,ce.format,ce.parent_ID 
						from $table ce
						inner join $table pe on pe.ID = ce.parent_ID
						where ce.parent_ID = %d;";

			}
			//All events belonging to specified club
			elseif(isset($fk_criteris["club_ID"])){
				$col_value = $fk_criteria["club_ID"];
				$sql = "select e.ID,e.event_type,e.name,e.format,e.parent_ID 
						from $table e
						inner join $joinTable as j on j.event_ID = e.ID
						inner join $clubTable as c on c.ID = j.club_ID
						where c.ID = %d;";
			}
			else {
				return $col;
			}
		}

		$safe = $wpdb->prepare($sql,$col_value);
		$rows = $wpdb->get_results($safe, ARRAY_A);

		foreach($rows as $row) {
            $obj = new Event;
            self::mapData($obj,$row);
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
		$sql = "select ID,event_type,name,format,parent_ID from $table where ID=%d";
		$safe = $wpdb->prepare($sql,$id);
		$rows = $wpdb->get_results($safe, ARRAY_A);

		error_log("Event::get(id) $wpdb->num_rows rows returned.");

		$obj = NULL;
		if($rows.length === 1) {
			$obj = new Event;
			self::mapData($obj,rows[0]);
		}
		
		return $obj;
	}

	/*************** Instance Methods ****************/
	public function __construct() {
		$this->isnew = TRUE;
		$this->init();
	}

    /**
     * Set a new value for a name of an Event
     */
	public function setName($name) {
        if(!is_string($name) || strlen($name) < 2) return;
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
     * Assign this Child Event to a Parent Event
     */
    public function setParent($parent) {
        if(! $parent instanceof self) return;
		$this->parent = $parent;
		$this->parent_ID = $parent->ID;
        $this->isdirty = TRUE;
    }

    public function getParent() {
        return $this->parent;
	}
	
	/**
	 * Set the type of event
	 * Applies only to a parent event
	 */
	public function setEventType($type) {
		if(isset($this->parent_ID)) return;
		switch($type) {
			CASE self::TOURNAMENT:
			CASE self::LEAGUE:
			CASE self::LADDER:
				$this->event_type = $type;
				$this->isdirty = TRUE;
				break;
		}
	}

	public function getEventType() {
		return $this->event_type;
	}
	
	/**
	 * Set the format
	 * Applies only to the lowest child event
	 */
	public function setFormat($format) {
		if(!isset($this->parent_ID)) return;
		switch($format) {
			CASE self::SINGLE_ELIM:
			CASE self::DOUBLE_ELIM:
			CASE self::ROUND_ROBIN:
				$this->format = $format;
				$this->isdirty = TRUE;
				break;
		}
	}

	public function getFormat() {
		return $this->format;
	}

	/**
	 * Get all my children!
	 * 1. Child events
	 * 2. Entrants in a draw
	 */
    public function getChildren($force=FALSE) {
		$this->getChildEvents($force);
		$this->getDraw($force);
	}

	/**
	 * Get all child events for this event.
	 */
	public function getChildEvents($force) {
        if(count($this->childEvents) === 0 || $force) {
			$this->childEvents = Event::find(array("event_ID" => $this->ID));
		}
	}

	/**
	 * Get all Entrants for this event.
	 */
	public function getDraw($force) {
		if(!isset($this->parent_ID)) return;
        if(count($this->draw) === 0 || $force) $this->draw = Entrant::find($this->ID);
	}


	protected function create() {
        global $wpdb;
        
        if(!$this->isValid()) return;

        $values = array( 'name'=>$this->name
						,'parent_ID'=>$this->parent_ID
						,'event_type'=>$this->event_type
						,'format'=>$this->format);
		$formats_values = array('%s','%d','%s','%s');
		$wpdb->insert($wpdb->prefix . self::$tablename, $values, $formats_values);
		$this->ID = $wpdb->insert_id;
		$this->isnew = FALSE;

		error_log("Event::create $wpdb->rows_affected rows affected.");

		return $wpdb->rows_affected;
	}

	protected function update() {
		global $wpdb;

        if(!$this->isValid()) return;

        $values = array( 'name' => $this->name
						,'parent_ID' => $this->parent_ID
						,'event_type'=>$this->event_type
						,'format'=>$this->format);
		$formats_values = array('%s','%d','%s','%s');
		$where          = array('ID' => $this->ID);
		$formats_where  = array('%d');
		$wpdb->update($wpdb->prefix . self::$tablename,$values,$where,$formats_values,$formats_where);
		$this->isdirty = FALSE;

		error_log("Event::update $wpdb->rows_affected rows affected.");

		return $wpdb->rows_affected;
	}

	//TODO: Complete the delete logic
    public function delete() {

	}
	
	public function isValid() {
		$isvalid = TRUE;
		if(!isset($this->name)) $isvalid = FALSE;
		if(!isset($this->event_type) && !isset($this->parent_ID)) $isvalid = FALSE;
		if(!isset($this->format) && isset($this->parent_ID)) $isvalid = FALSE;

		return $isvalid;
	}

    /**
     * Map incoming data to an instance of Event
     */
    protected static function mapData($obj,$row) {
		parent::mapData($obj,$row);
        $obj->name = $row["name"];
        $obj->event_type = $row["event_type"];
        $obj->parent_ID = $row["parent_ID"];
		$obj->format = $row["format"];
		$obj->getChildren(TRUE);
		if(isset($obj->parent_ID)) {
			$p = self::get($obj->parent_ID);
			$obj->setParent($p);
		}
	}
	
	private function init() {
		$this->parent_ID = NULL;
		$this->parent = NULL;
		$this->name = NULL;
		$this->event_type = NULL;
		$this->format = NULL;
	}

} //end class