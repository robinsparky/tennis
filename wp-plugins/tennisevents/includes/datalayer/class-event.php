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

	private $isroot;
	private $name;
	private $parent_ID; //parent Event ID
	private $parent; //parent Event
	private $event_type; //tournament, league, ladder
	private $format; //single elim, double elim, round robin
    
	private $draw; //array of entrants
	private $childEvents; //array of child events
	private $clubs; //array of related clubs
    
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
		
		if(isset($fk_criteria[0]) && is_array($fk_criteria[0])) $fk_criteria = $fk_criteria[0];

		if(array_key_exists('parent_ID',$fk_criteria)) {
			//All events who are children of specified Event
			$col_value = $fk_criteria["parent_ID"];
			$sql = "select ce.ID,ce.event_type,ce.name,ce.format,ce.parent_ID 
					from $table ce
					inner join $table pe on pe.ID = ce.parent_ID
					where ce.parent_ID = %d;";
		}
		elseif(array_key_exists('club',$fk_criteria)) {
			//All events belonging to specified club
			$col_value = $fk_criteria["club"];
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

		error_log("Event::find $wpdb->num_rows rows returned.");

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
			self::mapData($obj,$rows[0]);
		}
		return $obj;
	}

	static function delete(int $clubId, int $eventId):int {
		if(!isset($clubId) || !isset($eventId)) return 0;

        global $wpdb;
		$table = $wpdb->prefix . 'tennis_club_event';
        $sql = "delete from $table where club_ID=%d and event_ID=%d;";
		$safe = $wpdb->prepare($sql,$clubId,$eventId);
		$rows = $wpdb->get_results($safe, ARRAY_A);
		$result = $wpdb->rows_affected;

		error_log("Event.delete: deleted $result rows");
		return $result;
	}

	/*************** Instance Methods ****************/
	public function __construct(bool $root=false) {
		$this->isnew = TRUE;
		$this->isroot = $root;
		$this->init();
	}
	
    public function __destruct() {
		$this->parent = null;
		foreach($this->getChildEvents() as $event){
			$event = null;
		}
	
		foreach($this->getDraw() as $draw) {
			$draw = NULL;
		}
	}
	
	/**
	 * Is this Event the hierarchy root?
	 */
	public function isRoot():bool {
		return $this->isroot;
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
		switch($type) {
			case self::TOURNAMENT:
			case self::LEAGUE:
			case self::LADDER:
				$this->event_type = $type;
				break;
			default:
				return false;
		}
		return $this->isdirty = TRUE;
	}

	public function getEventType() {
		return $this->event_type;
	}
	
	/**
	 * Set the format
	 * Applies only to the lowest child event
	 */
	public function setFormat(string $format) {
		switch($format) {
			case self::SINGLE_ELIM:
			case self::DOUBLE_ELIM:
			case self::ROUND_ROBIN:
				$this->format = $format;
				break;
			default:
				return false;
		}
		return $this->isdirty = TRUE;
	}

	public function getFormat():string {
		return $this->format;
	}

    /**
     * Assign a Parent event to this child Event
     */
    public function setParent(Event $parent=null) {
		if($this->isRoot()) return false;

		// if($parent->isNew() || !$parent->isValid()) {
		// 	return false;
		// }
		if(!isset($parent)) {
			$this->parent = null;
			$this->parent_ID = null;
		}
		else {
			$this->parent = $parent;
			$this->parent_ID = $parent->getID();
			$parent->addChild($this);
		}

		return $this->isdirty = TRUE;
    }

    public function getParent() {
        return $this->parent;
	}

	/**
	 * Is this event a parent Event?
	 */
	public function isParent() {
		if($this->isRoot()) return true;
		else return count($this->getChildEvents()) > 0;
	}

	/**
	 * Add a child event to this Parent Event
	 * This method ensures that the same child event is not added more than once.
	 * 
	 * @param $child child Event
	 */
	public function addChild(Event $child) {
		if(!isset($child)) return false;

		$found = false;
		foreach($this->getChildEvents() as $ch) {
			if($child == $ch) {
				$found = true;
				break;
			}
		}

		if(!$found) {
			$this->childEvents[] = $child;
			$child->setParent($this);
			return $this->isdirty = true;
		}

		return false;
	}

	public function removeChild(Event $child) {
		if(!isset($child)) return false;
		
		$i=0;
		foreach($this->getChildEvents() as $ch) {
			if($child == $ch) {
				unset($this->childEvents[$i]);
				return $this->isdirty = true;
			}
			$i++;
		}

		return false;
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
		if(!isset($player)) return false;
		if(!$player->isValid()) return false;

		$found = false;
		foreach($this->Draw() as $d) {
			if($player->getEventID() === $d->getEventID()
			&& $player->getPosition() === $d->getPosition()) {
				$found = true;
			}
		}
		if(!$found) {
			$this->draw[] = $player;
			return $this->isdirty = true;
		}
		return false;
	}

	public function getDraw() {
		if(!isset($this->draw)) $this->draw = array();
		return $this->draw;
	}

	/**
	 * Remove an Entrant from Draw
	 * @param $entrant Entrant in the draw
	 */
	public function removeFromDraw(Entrant $entrant) {
		if(!isset($entrant)) return false;
		
		$i=0;
		foreach($this->getDraw() as $dr) {
			if($entrant == $dr) {
				unset($this->draw[$i]);
				return $this->isdirty = true;
			}
			$i++;
		}
		return false;
	}

	public function addClub($club) {
		if(!isset($club)) return false;
		$found = false;
		foreach($this->getClubs() as $cl) {
			if($club == $cl) {
				$found = true;
				break;
			}
		}
		if(!$found) {
			$this->clubs[] = $club;
			return $this->isdirty = true;
		}
		return false;
	}

	public function removeClub($club) {
		if(!isset($club)) return false;
		
		$i=0;
		foreach($this->getClubs() as $cl) {
			if($club == $cl) {
				unset($this->clubs[$i]);
				return $this->isdirty = true;
			}
			$i++;
		}
		return false;
	}

	/**
	 * Get all my children!
	 * 1. Child events
	 * 2. Entrants in the draw
	 */
    public function getChildren($force=FALSE) {
		$this->fetchChildEvents($force);
		$this->fetchDraw($force);
		$this->fetchClubs($force);
	}

	/**
	 * Fetch all child events for this event.
	 */
	private function fetchChildEvents($force) {
        if(count($this->getChildEvents()) === 0 || $force) {
			$this->childEvents = Event::find(array('parent_ID' => $this->getID()));
		}
	}

	/**
	 * Fetch all Entrants for this event from the database.
	 */
	private function fetchDraw($force) {
		if($this->isParent()) return;
        if(count($this->draw) === 0 || $force) $this->draw = Entrant::find($this->getID());
	}

	/**
	 * Fetch all related clubs for this Event
	 */
	private function fetchClubs($force) {
		if(count($this->getClubs()) === 0 || $force) $this->clubs = Club::find($this->getID());
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
		$result = $wpdb->rows_affected;
		$this->isnew = FALSE;
		$this->isdirty = FALSE;
		error_log("Event::create $wpdb->rows_affected rows affected.");

		foreach($this->getChildEvents() as $child) {
			$child->setParent($this);
			$result += $child->save();
		}

		foreach($this->getDraw() as $draw) {
			$draw->setEventID($this->getID());
			$result += $draw->save();
		}

		foreach($this->clubsForDeletion() as $clubId) {
			$result += Club::delete($clubId,$this->getID());
		}

		//Save any new events added to this club
		foreach($this->getEvents() as $ev) {
			$result += $ev->save();
		}

		//Create joins between Events and this Club
		$formats_value = array('%d','%d');
		foreach($this->getEvents() as $evt) {
			$values = array('club_ID'=>$this->getID()
							,'event_ID'=>$evt->getID());
			$wpdb->insert($wpdb->prefix . 'tennis_club_event',$values,$formats_values);
			$result += $wpdb->rows_affected;
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
			$child->setParent($this);
			$result += $child->save();
		}

		foreach($this->getDraw() as $draw) {
			$draw->setEventID($this->getID());
			$result += $draw->save();
		}
		
		return $result;
	}
	
	public function isValid() {
		$isvalid = TRUE;
		if(!isset($this->name)) $isvalid = FALSE;
		if(!isset($this->event_type) && $this->isParent()) $isvalid = FALSE;
		if(!isset($this->format) && !$this->isParent()) $isvalid = FALSE;
		$evs = Event::search($this->getName().'%');
		// foreach($evs as $ev) {
		// 	if($this->getID() !== $ev->getID() 
		// 	&& $this->getName() === $ev->getName()) {
		// 		$isvalid=FALSE;
		// 	}
		// }

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