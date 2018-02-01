<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once('abstract-class-data.php');
require_once('class-entrant.php');
//require_once('class-team.php');

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
		$sql = "select ID,event_type,name,format,parent_ID from $table where name like '%s'";
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
    public static function find(...$fk_criteria) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$joinTable = "{$wpdb->prefix}tennis_club_event";
		$clubTable = "{$wpdb->prefix}tennis_club";
		$col = array();
		$col_value;

		if(array_key_exists('parent_ID',$fk_criteria)) {
			//All events who are children of specified Event
			$col_value = $fk_criteria["parent_ID"];
			$sql = "select ce.ID,ce.event_type,ce.name,ce.format,ce.parent_ID 
					from $table ce
					inner join $table pe on pe.ID = ce.parent_ID
					where ce.parent_ID = %d;";
		}
		elseif(array_key_exists('club_ID',$fk_criteria)) {
			//All events belonging to specified club
			$col_value = $fk_criteria["club_ID"];
			$sql = "select e.ID,e.event_type,e.name,e.format,e.parent_ID 
					from $table e
					inner join $joinTable as j on j.event_ID = e.ID
					inner join $clubTable as c on c.ID = j.club_ID
					where c.ID = %d;";
		} elseif(count($fk_criteria) > 0) {
			//All events belonging to a specified club
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
	 * Get instance of a Event using it's primary key: ID
	 */
    static public function get(int ...$pks) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$sql = "select ID,event_type,name,format,parent_ID from $table where ID=%d";
		$safe = $wpdb->prepare($sql,$pks);
		$rows = $wpdb->get_results($safe, ARRAY_A);

		error_log("Event::get(id) $wpdb->num_rows rows returned.");

		$obj = NULL;
		if(count($rows) === 1) {
			$obj = new Event;
			self::mapData($obj,rows[0]);
		}
		return $obj;
	}

	public static function equals( Event $e1, Event $e2 ){
		return $e1 == $e2;
	}

	/*************** Instance Methods ****************/
	public function __construct() {
		$this->isnew = TRUE;
		$this->init();
	}
	
    public function __destruct() {
		$this->parent = null;
		if(is_array($this->childEvents)) {
			foreach($this->childEvents as $event){
				$event = null;
			}
		}
		
		if(is_array($this->draw)) {
			foreach($this->draw as $draw) {
				$draw = NULL;
			}
		}
    }

    /**
     * Set a new value for a name of an Event
     */
	public function setName(string $name) {
		$this->name = $name;
		$this->isdirty = TRUE;
    }
    
    /**
     * Get the name of this object
     */
    public function getName():string {
        return $this->name;
    }

	
	/**
	 * Set the type of event
	 * Applies only to a parent event
	 */
	public function setEventType(string $type) {
		if(!$this->isParent()) return;
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
	public function setFormat(string $format) {
		if($this->isParent()) return;
		switch($format) {
			CASE self::SINGLE_ELIM:
			CASE self::DOUBLE_ELIM:
			CASE self::ROUND_ROBIN:
				$this->format = $format;
				$this->isdirty = TRUE;
				break;
		}
	}

	public function getFormat():string {
		return $this->format;
	}

    /**
     * Assign this Child Event to a Parent Event
     */
    public function setParent(Event $parent=null) {
		$this->parent = $parent;
		$this->parent_ID = isset($this->parent) ? $parent->getID() : null;
		if(isset($this->parent)) $parent->addChild($this);
        $this->isdirty = TRUE;
    }

    public function getParent() {
        return $this->parent;
	}

	/**
	 * Is this event a parent EVent?
	 */
	public function isParent() {
		return !isset($this->parent);
	}

	/**
	 * Add a child event to this Parent Event
	 * This method ensures that child events ate not added more than once.
	 * 
	 * @param $child A child event
	 */
	public function addChild(Event $child) {
		if(!isset($child)) return;

		if($this->isParent() && !$child->isParent()) {
			$found = false;
			foreach($this->childEvents() as $ch) {
				if($child->getID() === $ch->getID()) {
					$found = true;
					break;
				}
			}
			if(!$found) {
				$this->childEvents[] = $child;
				$child->setParent($child);
			}
			$this->isdirty = true;
		}
	}

	public function getChildEvents() {
		if(!isset($this->childEvents)) $this->childEvents = array();
		return $this->childEvents;
	}

	/**
	 * Add an Entrant to the draw for this Child Event
	 * This method ensures that Entrants are not added ore than once.
	 * 
	 * @param $player An Entrant to this event
	 */
	public function addToDraw(Entrant $player) {
		if(!isset($player)) return;

		$found = false;
		foreach($this->Draw() as $d) {
			if($player->getEventID() === $d->getEventID()
			&& $player->getPosition() === $d->getPosition()) {
				$found = true;
			}
		}
		if(!$found) {
			$this->draw[] = $player;
		}
		$this->isdirty = true;
	}

	public function getDraw() {
		if(!isset($this->draw)) $this->draw = array();
		return $this->draw;
	}

	/**
	 * Get all my children!
	 * 1. Child events
	 * 2. Entrants in a draw
	 */
    public function getChildren($force=FALSE) {
		$this->retrieveChildEvents($force);
		$this->retrieveDraw($force);
	}

	/**
	 * Get all child events for this event.
	 */
	private function retrieveChildEvents($force) {
        if(count($this->getChildEvents()) === 0 || $force) {
			$this->childEvents = Event::find(array("parent_ID" => $this->ID));
		}
	}

	/**
	 * Retrieve all Entrants for this event from the database.
	 */
	private function retrieveDraw($force) {
		if($this->isParent()) return;
        if(count($this->draw) === 0 || $force) $this->draw = Entrant::find($this->ID);
	}


	protected function create() {
        global $wpdb;
        
        parent::create();

        $values = array( 'name'=>$this->name
						,'parent_ID'=>$this->parent_ID
						,'event_type'=>$this->event_type
						,'format'=>$this->format);
		$formats_values = array('%s','%d','%s','%s');
		$wpdb->insert($wpdb->prefix . self::$tablename, $values, $formats_values);
		$this->ID = $wpdb->insert_id;
		$this->isnew = FALSE;
		$this->isdirty = FALSE;
		$result = $wpdb->rows_affected;
		error_log("Event::create $wpdb->rows_affected rows affected.");

		foreach($this->getChildEvents() as $child) {
			$child->save();
		}

		foreach($this->getDraw() as $draw) {
			$draw->save();
		}

		return $result;
	}

	protected function update() {
		global $wpdb;

        parent::update();

        $values = array( 'name' => $this->name
						,'parent_ID' => $this->parent_ID
						,'event_type'=>$this->event_type
						,'format'=>$this->format);
		$formats_values = array('%s','%d','%s','%s');
		$where          = array('ID' => $this->ID);
		$formats_where  = array('%d');
		$wpdb->update($wpdb->prefix . self::$tablename,$values,$where,$formats_values,$formats_where);
		$this->isdirty = FALSE;
		$result = $wpdb->rows_affected;

		error_log("Event::update $wpdb->rows_affected rows affected.");

		foreach($this->getChildEvents() as $child) {
			$child->save();
		}

		foreach($this->getDraw() as $draw) {
			$draw->save();
		}
		
		return $result;
	}

	//TODO: Complete the delete logic
    public function delete() {

	}
	
	public function isValid() {
		$isvalid = TRUE;
		if(!isset($this->name)) $isvalid = FALSE;
		if(!isset($this->event_type) && $this->isParent()) $isvalid = FALSE;
		if(!isset($this->format) && !$this->isParent()) $isvalid = FALSE;
		$evs = Event::search($this->getName().'%');
		foreach($evs as $ev) {
			if($this->getID() !== $ev->getID() 
			&& $this->getName() === $ev->getName()) {
				$isvalid=FALSE;
			}
		}

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