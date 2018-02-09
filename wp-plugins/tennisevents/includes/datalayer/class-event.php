<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Event types
 */
class EventType {

	const TOURNAMENT  = 'tournament';
	const LEAGUE      = 'league';
	const LADDER      = 'ladder';
	const ROUND_ROBIN = 'robin';
}

/**
 * Formats
 */
class Format {
	const SINGLE_ELIM = 'selim';
	const DOUBLE_ELIM = 'delim';
	const GAMES       = 'games';
	const SETS        = 'sets';
}

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

	private $name; //name or description of the event
	private $parent_ID; //parent Event ID
	private $parent; //parent Event
	private $event_type; //tournament, league, ladder, round robin
	private $format; //single elim, double elim, games won, sets won
	private $signup_by; //Cut off date for signing up
	private $start_date; //Start date of this event
	private $end_date; //End date of this event
    
	private $childEvents; //array of child events
	private $draw; //array of entrants for this leaf event
	private $clubs; //array of related clubs for this root event
	private $rounds; //array of rounds for this leaf event

	private $childEventsToBeDeleted; //array of child ID's events to be deleted
	private $entrantsToBeDeleted; //array of Entrants to be removed from the draw
	private $clubsToBeDeleted; //array of club Id's to be removed from relations with this Event
	private $roundsToBeDeleted; //array of round Id's to be deleted
    
    /**
     * Search for Events have a name 'like' the provided criteria
     */
    public static function search($criteria) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		
		$criteria .= strpos($criteria,'%') ? '' : '%';
		
		$sql = "SELECT `ID`,`event_type`,`name`,`format`,`parent_ID`
		              ,`signup_by`,`start_date`, `end_date` 
		        FROM $table WHERE `name` like '%s'";
		$safe = $wpdb->prepare($sql,$criteria);
		$rows = $wpdb->get_results($safe, ARRAY_A);
		
		error_log("Event::search $wpdb->num_rows rows returned using criteria: $criteria");

		$col = array();
		foreach($rows as $row) {
			$r = (bool)$row["isroot"]; 	
			error_log("Isroot=$r");
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
			$sql = "SELECT ce.ID, ce.event_type, ce.name, ce.format, ce.parent_ID
			 			  ,ce.signup_by,ce.start_date,ce.end_date  
					FROM $table ce
					INNER JOIN $table pe ON pe.ID = ce.parent_ID
					WHERE ce.parent_ID = %d;";
		}
		elseif(array_key_exists('club',$fk_criteria)) {
			//All events belonging to specified club
			$col_value = $fk_criteria["club"];
			$sql = "SELECT e.ID, e.event_type, e.name, e.format, e.parent_ID
						  ,e.signup_by,e.start_date,e.end_date 
					from $table e
					INNER JOIN $joinTable AS j ON j.event_ID = e.ID
					INNER JOIN $clubTable AS c ON c.ID = j.club_ID
					WHERE c.ID = %d;";
		} elseif(!isset($fk_criteria)) {
			//All events belonging to a specified club
			$col_value = 0;
			$sql = "SELECT `ID`,`event_type`,`name`,`format`,`parent_ID`
				   		  ,`signup_by`,`start_date`,`end_date` 
					FROM $table;";
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
		$sql = "select ID,isroot,event_type,name,format,parent_ID from $table where ID=%d";
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
	
	static public function deleteEvent(int $eventId) {
		$result = 0;
		if(isset($eventId)) {
			global $wpdb;
			$table = $wpdb->prefix . self::$tablename;
			$wpdb->delete($table,array('ID'=>$eventId),array('%d'));
			$result = $wpdb->rows_affected;
		}
		error_log("Event.deleteEvent: deleted $result");
		return $result;
	}

	/*************** Instance Methods ****************/
	public function __construct(string $name=null) {
		$this->isnew = TRUE;
		$this->name = $name;
		$this->init();
	}
	
    public function __destruct() {
		$this->parent = null;
			if(isset($this->childEvents)) {
			foreach($this->childEvents as $event){
				$event = null;
			}
		}
	
		if(isset($this->clubs)) {
			foreach($this->clubs as $club) {
				$club = null;
			}
		}

		if(isset($this->draw)) {
			foreach($this->draw as $draw) {
				$draw = null;
			}
		}

		if(isset($this->rounds)) {
			foreach($this->rounds as $round) {
				$round = null;
			}
		}

		if(isset($this->roundsToBeDeleted)) {
			foreach($this->roundsTpBeDeleted as $round) {
				$round = null;
			}
		}
	}
	
	/**
	 * Is this Event the hierarchy root?
	 */
	public function isRoot() {
		return !isset($this->parent);
	}

	public function isLeaf() {
		return (isset($this->parent) && count($this->getChildEvents()) === 0);
	}

	public function getRoot() {
		if($this->isRoot()) return $this;
		return $this->parent->getRoot();
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
	public function setEventType(string $type=null) {
		switch($type) {
			case EventType::TOURNAMENT:
			case EventType::LEAGUE:
			case EventType::LADDER:
			case EventType::ROUND_ROBIN:
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
	public function setFormat(string $format=null) {
		$result = false;
		switch($format) {
			case Format::SINGLE_ELIM:
			case Format::DOUBLE_ELIM:
			case Format::GAMES:
			case Format::SETS:
				$this->format = $format;
				$result = $this->isdirty = true;
				break;
			default:
				$result = false;
				break;
		}
		return $result;
	}

	public function getFormat() {
		return $this->format;
	}

    /**
     * Assign a Parent event to this child Event
	 * @param $parent Event; can set to null
	 * @return true if succeeds false otherwise
     */
    public function setParent(Event $parent) {
		$result = false;
		// if($parent->isNew() || !$parent->isValid()) {
		// 	return false;
		// }
		if(isset($parent)) {
			$this->parent = $parent;
			$this->parent_ID = $parent->getID();
			$parent->addChild($this);
			$result = $this->isdirty = TRUE;
		}
		return $result;
    }

    public function getParent() {
        return $this->parent;
	}

	/**
	 * Is this event a parent Event?
	 */
	public function isParent() {
		return count($this->getChildEvents()) > 0;
	}

	/**
	 * Set the date by which players must signup for this event
	 * @param $signup The sign up deadline
	 */
	public function setSignupBy(string $signup) {
		$result = false;
        $mdt = strtotime($signup);
        $this->signup_by = $mdt;
        $result = $this->isdirty = true;

        return $result;
	}

	/**
	 * Get the date by which players must signup for this event
	 */
	public function getSignupBy() {
        return isset($this->signup_by) ? date("F d, Y",$this->signup_by) : null;
	}

	public function rawSignupBy() {
		return $this->signup_by;
	}
	
	public function setStartDate(string $start) {
		$result = false;
        $mdt = strtotime($start);
        $this->start_date = $mdt;
        $result = $this->isdirty = true;

        return $result;
	}
	public function getStartDate() {
        return isset($this->start_date) ? date("F d, Y",$this->start_date) : null;
	}

	public function rawStartDate() {
		return $this->start_date;
	}
	
	public function setEndDate(string $end) {
		$result = false;
        $mdt = strtotime($end);
        $this->end_date = $mdt;
        $result = $this->isdirty = true;

        return $result;
	}

	public function getEndDate() {
        return isset($this->end_date) ? date("F d, Y",$this->end_date) : null;
	}

	public function rawEndDate() {
		return $this->end_date;
	}

	/**
	 * Add a child event to this Parent Event
	 * This method ensures that the same child event is not added more than once.
	 * 
	 * @param $child child Event
	 * @return true if succeeds false otherwise
	 */
	public function addChild(Event $child) {
		$result = false;
		if(isset($child)) {
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
				$result = $this->isdirty = true;
			}
		}
		return $result;
	}

	/**
	 * Remove an child Event from this Event
	 * @param $child Event
	 * @return true if succeeds false otherwise
	 */
	public function removeChild(Event $child) {
		$result = false;
		if(isset($child)) {
			$i=0;
			if(isset($this->childEventsToBeDeleted)) $this->childEventsToBeDeleted = array();
			foreach($this->getChildEvents() as $ch) {
				if($child == $ch) {
					$this->childEventsToBeDeleted[] = $child->getID();
					unset($this->childEvents[$i]);
					$result = $this->isdirty = true;
				}
				$i++;
			}
		}
		return $result;
	}

	/**
	 * Get all Events belonging to this Event
	 */
	public function getChildEvents() {
		if(!isset($this->childEvents)) {
			$this->fetchChildEvents();
			foreach($this->childEvents as $child) {
				$child->parent = $this;
			}
		}
		return $this->childEvents;
	}

	/**
	 * Get an Event with a specific name belonging to this Event
	 */
	public function getNamedEvent(string $name) {
		$result = null;

		foreach($this->getChildEvents() as $evt) {
			if($name === $evt->getName()) {
				$result = $evt;
				break;
			}
		}
		return $result;
	}

	/**
	 * A root level Event can be associated with 4
	 * one or more clubs. 
	 * With the exception of inter-club leagues
	 * most of the time an event is only associated with one club.
	 * @param $club the Club to be added to this Event
	 */
	public function addClub($club) {
		$result = false;
		if(isset($club) && $this->isRoot()) {
			$found = false;
			foreach($this->getClubs() as $cl) {
				if($club == $cl) {
					$found = true;
					break;
				}
			}
			if(!$found) {
				$this->clubs[] = $club;
				$result = $this->isdirty = true;
			}
		}
		return $result;
	}

	public function removeClub($club) {
		if(!isset($club)) return false;
		$result = false;
		$i=0;
		if(!isset($this->clubsToBeDeleted)) $this->clubsToBeDeleted = array();
		foreach($this->getClubs() as $cl) {
			if($club == $cl) {
				$this->clubsToBeDeleted[] = $club->getID();
				unset($this->clubs[$i]);
				$result = $this->isdirty = true;
			}
			$i++;
		}
		return $result;
	}
	
	public function getClubs() {
		if(!isset($this->clubs)) $this->fetchClubs();
		return $this->clubs;
	}

	/**
	 * Add an Entrant to the draw for this Child Event
	 * This method ensures that Entrants are not added ore than once.
	 * 
	 * @param $name The name of a player in this event
	 * @param $seed The seeding of this player
	 * @return true if succeeds false otherwise
	 */
	public function addToDraw(string $name,int $seed=null) {
		$result = false;
		if(isset($name) && $this->isLeaf()) {
			$found = false;
			foreach($this->getDraw() as $d) {
				if($name === $d->getName()) {
					$found = true;
				}
			}
			if(!$found) {
				$ent = new Entrant($this->getID(),$name,$seed);
				$this->draw[] = $ent;
				$result = $this->isdirty = true;
			}
		}
		return $result;
	}

	/**
	 * Get a contestant in the Draw by name
	 */
	public function getNamedEntrant(string $name) {
		$result = null;

		foreach($this->getDraw() as $draw) {
			if($name === $draw->getName()) {
				$result = $draw;
				break;
			}
		}
		return $result;
	}
	
	/**
	 * Remove an Entrant from Draw
	 * @param $entrant Entrant in the draw
	 * @return true if succeeds false otherwise
	 */
	public function removeFromDraw(string $name) {
		$result = false;
		if(isset($name)) {
			$i=0;
			if(!isset($this->entrantsToBeDeleted)) $this->entrantsToBeDeleted = array();
			foreach($this->getDraw() as $dr) {
				if($name == $dr->getName()) {
					$this->entrantsToBeDeleted[] = $dr->getPosition();
					unset($this->draw[$i]);
					$result = $this->isdirty = true;
				}
				$i++;
			}
		}
		return $result;
	}

	/**
	 * Append a collection of rounds to existing collection of rounds
	 */
	public function appendRounds(array $rounds) {
		$result = false;
		$this->getRounds();
		foreach($rounds as $rnd) {
			if($rnd instanceof Round) {
				$rnd->setEvent($this);
				$this->rounds[] = $rnd;
				$result = true;
			}
		}
		return $result;
	}

	/**
	 * Remove the collection of Rounds
	 */
	public function removeRounds() {
		if(isset($this->rounds)) {
			$i=0;
			foreach($this->rounds as $round) {
				$this->getRoundsToBeDeleted()[] = clone $round;
				unset($this->rounds[$i]);
				$i++;
			}
		}
		$this->rounds = array();
	}

	public function getRounds() {
		if(!isset($this->rounds)) $this->fetchRounds();
		return $this->rounds;
	}

	public function numberOfRounds() {
		return count($this->getRounds());
	}

	public function getDraw() {
		if(!isset($this->draw)) $this->fetchDraw();
		return $this->draw;
	}

	public function getDrawSize() {
		return sizeof($this->getDraw());
	}

	
	/**
	 * Check to see if this Event has valid data
	 */
	public function isValid() {
		$isvalid = TRUE;
		if(!isset($this->name)) $isvalid = FALSE;
		if(!isset($this->event_type) && $this->isParent()) $isvalid = FALSE;
		if(!isset($this->format) && !$this->isParent()) $isvalid = FALSE;
		if($this->isRoot() && count($this->getClubs()) < 1) $isvalid = false;

		return $isvalid;
	}

	/**
	 * Delete this object
	 * NOTE all child objects will 
	 *      deleted via DB Cascade
	 */
	public function delete() {
		$result = 0;
		$id = $this->getID();
		if(isset($id)) {
			global $wpdb;
			$table = $wpdb->prefix . self::$tablename;

			$where = array( 'ID'=>$id );
			$formats_where = array( '%d' );
			$wpdb->delete($table, $where, $formats_where);
			$result = $wpdb->rows_affected;
			error_log("Event.delete: deleted $result rows");
		}
		return $result;
	}

	/**
	 * Fetch all child events for this event.
	 */
	private function fetchChildEvents($force=false) {
        if(!isset($this->childEvents) || $force) {
			$this->childEvents = Event::find(array('parent_ID' => $this->getID()));
		}
	}

	/**
	 * Fetch all Entrants for this event.
	 * Otherwise known as the draw.
	 */
	private function fetchDraw($force=false) {
		if($this->isParent()) $this->draw = array();
        if(!isset($this->draw) || $force) $this->draw = Entrant::find($this->getID());
	}

	/**
	 * Fetch all related clubs for this Event
	 * Root-level Events can be associated with one or more clubs
	 */
	private function fetchClubs($force=false) {
		if(!isset($this->clubs) || $force) $this->clubs = Club::find($this->getID());
	}

	private function fetchRounds($force=false) {
		if(!isset($this->rounds) || $force) $this->rounds = Round::find($this->getID());
	}

	private function getEventsToBeDeleted() {
		if(!isset($this->childEventsToBeDeleted)) $this->childEventsToBeDeleted = array();
		return $this->childEventsToBeDeleted;
	}

	private function getEntrantsToBeDeleted() {
		if(!isset($this->entrantsToBeDeleted)) $this->entrantsToBeDeleted = array();
		return $this->entrantsToBeDeleted;
	}

	private function getClubsToBeDeleted() {
		if(!isset($this->clubsToBeDeleted)) $this->clubsToBeDeleted = array();
		return $this->clubsToBeDeleted;
	}

	private function getRoundsToBeDeleted() {
		if(!isset($this->roundsToBeDeleted)) $this->roundsToBeDeleted = array();
		return $this->roundsToBeDeleted;
	}

	protected function create() {
        global $wpdb;
        
        parent::create();

		$this->parent_ID = isset($this->parent) ? $this->parent->getID() : null;

        $values = array( 'name'       => $this->getName()
						,'parent_ID'  => $this->parent_ID
						,'event_type' => $this->getEventType()
						,'format'     => $this->getFormat()
						,'signup_by'  => $this->getSignup()
						,'start_date' => $this->getStartDate()
						,'end_date'   => $this->getEndDate()
					    );
		$formats_values = array('%s','%d','%s','%s','%s');
		$wpdb->insert($wpdb->prefix . self::$tablename, $values, $formats_values);
		$this->ID = $wpdb->insert_id;
		$result = $wpdb->rows_affected;
		$this->isnew = FALSE;
		$this->isdirty = FALSE;

		$result += $this->manageRelatedData();
		
		error_log("Event::create: $result rows affected.");

		return $result;
	}

	protected function update() {
		global $wpdb;

        parent::update();

		$this->parent_ID = isset($this->parent) ? $this->parent->getID() : null;

        $values = array( 'name'       => $this->getName()
						,'parent_ID'  => $this->parent_ID
						,'event_type' => $this->getEventType()
						,'format'     => $this->getFormat()
						,'signup_by'  => $this->getSignup()
						,'start_date' => $this->getStartDate()
						,'end_date'   => $this->getEndDate()
					    );
		$formats_values = array('%s','%d','%s','%s','%s');
		$where          = array('ID' => $this->ID);
		$formats_where  = array('%d');
		$check = $wpdb->update($wpdb->prefix . self::$tablename,$values,$where,$formats_values,$formats_where);
		$this->isdirty = false;
		$result = $wpdb->rows_affected;

		$result += $this->manageRelatedData();
		
		error_log("Event::update: $result rows affected.");
		
		return $result;
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
		$obj->signup_by = isset($row["signup_by"]) ? date("F d, Y",$row["signup_by"]) : null;
		$obj->start_date = isset($row["start_date"]) ? date("F d, Y",$row["start_date"]) : null;
		$obj->end_date = isset($row["end_date"]) ? date("F d, Y",$row["end_date"]) : null;
	}
	
	private function init() {
		$this->parent_ID = NULL;
		$this->parent = NULL;
		$this->signup_by = null;
		$this->start_date = null;
		$this->end_date = null;
		// $this->name = NULL;
		// $this->event_type = NULL;
		// $this->format = NULL;
	}

	private function manageRelatedData():int {
		$result = 0;

		//Save each child event
		foreach($this->getChildEvents() as $evt) {
			$result += $evt->save();
		}

		//Delete Events removed from being a child of this Event
		$evtIds = array_map(function($e){return $e->getID();},$this->getChildEvents());
		foreach($this->getEventsToBeDeleted() as $id) {
			if(!in_array($id,$evtIds)) {
				$result += Event::deleteEvent($id);
			}
		}

		//Delete ALL Rounds removed from this Event
		foreach($this->getRoundsToBeDeleted() as $round) {
			$round->delete();
		}

		foreach($this->getDraw() as $draw) {
			$draw->setEventID($this->getID());
			$result += $draw->save();
		}

		//Delete Entrants that were removed from this draw
		$entrantIds = array_map(function($e){return $e->getID();},$this->getDraw());
		foreach($this->getEntrantsToBeDeleted() as $entId) {
			if(!in_array($entId,$entrantIds)) {
				$result += Entrant::deleteEntrant($this->getID(),$entId);
			}
		}

		//Save the Clubs related to this Event
		foreach($this->getClubs() as $cb) {
			$result += $cb->save();
		}

		//Remove relation between this Event and Clubs
		$clubIds = array_map(function($e){return $e->getID();},$this->getClubs());
		foreach($this->getClubsToBeDeleted() as $clubId) {
			if(!in_array($clubId,$clubIds)) {
				$result += ClubEventRelations::remove($clubId,$this->getID());
			}
		}

		//Create relation between this Event and Clubs
		foreach($this->getClubs() as $cb) {
			$result += ClubEventRelations::add($cb->getID(),$this->getID());
		}

		return $result;
	}

} //end class