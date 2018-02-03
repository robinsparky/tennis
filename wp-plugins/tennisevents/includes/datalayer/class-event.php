<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// require_once('class-abstractdata.php');
// require_once('class-entrant.php');
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
	const SINGLE_ELIM = 'selim';
	const DOUBLE_ELIM = 'delim';
	const ROUND_ROBIN = 'robin';

	private $isroot; //specifies this Event as the root of a hierarchy
	private $name;
	private $parent_ID; //parent Event ID
	private $parent; //parent Event
	private $event_type; //tournament, league, ladder
	private $format; //single elim, double elim, round robin
    
	private $childEvents; //array of child events
	private $draw; //array of entrants
	private $clubs; //array of related clubs

	private $childEventsToBeDeleted; //array of child ID's events to be deleted
	private $entrantsToBeDeleted; //array of Entrants to be removed from the draw
	private $clubsToBeDeleted; //array of club Id's to be removed from relations with this Event
    
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
	
		foreach($this->getClubs() as $club) {
			$club = null;
		}

		foreach($this->getDraw() as $draw) {
			$draw = null;
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
		return $this->isdirty = true;
	}

	public function getFormat() {
		return $this->format;
	}

    /**
     * Assign a Parent event to this child Event
	 * @param $parent Event; can set to null
	 * @return true if succeeds false otherwise
     */
    public function setParent(Event $parent=null) {
		$result = false;
		if(!$this->isRoot()) {
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
		if($this->isRoot()) return true;
		else return count($this->getChildEvents()) > 0;
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
			foreach($this->getChildEvents() as $ch) {
				if($child == $ch) {
					$this->getEventsToBeDeleted()[] = $ch->getID();
					unset($this->childEvents[$i]);
					$result = $this->isdirty = true;
				}
				$i++;
			}
		}
		return $result;
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
	 * @return true if succeeds false otherwise
	 */
	public function addToDraw(Entrant $ent) {
		$result = false;
		if(isset($player)) {
			$found = false;
			foreach($this->Draw() as $d) {
				if($ent->getEventId() === $d->getEventId()
				&& $ent->getPosition() === $d->getPosition()) {
					$found = true;
				}
			}
			if(!$found) {
				$player->setEventId($this->getID());
				$this->draw[] = $player;
				$result = $this->isdirty = true;
			}
		}
		return $result;
	}
	
	/**
	 * Remove an Entrant from Draw
	 * @param $entrant Entrant in the draw
	 * @return true if succeeds false otherwise
	 */
	public function removeFromDraw(Entrant $entrant) {
		$result = false;
		if($isset($entrant)) {
			$i=0;
			foreach($this->getDraw() as $dr) {
				if($entrant == $dr) {
					$this->getEntrantsToBeDeleted()[] = $entrant->getPosition();
					unset($this->draw[$i]);
					$result = $this->isdirty = true;
				}
				$i++;
			}
		}
		return $result;
	}

	public function getDraw() {
		if(!isset($this->draw)) $this->draw = array();
		return $this->draw;
	}

	public function getClubs() {
		if(!isset($this->clubs)) $this->clubs=array();
		return $this->clubs;
	}

	public function addClub($club) {
		$result = false;
		if(isset($club)) {
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
		return $results;
	}

	public function removeClub($club) {
		if(!isset($club)) return false;
		$result = false;
		$i=0;
		foreach($this->getClubs() as $cl) {
			if($club == $cl) {
				$this->getClubsToBeDeleted()[] = $cl->getID();
				unset($this->clubs[$i]);
				$result = $this->isdirty = true;
			}
			$i++;
		}
		return $result;
	}
	
	/**
	 * Check to see if this Event has valid data
	 */
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
	 * Fetch all Entrants for this event.
	 * Otherwise known as the draw.
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

	protected function create() {
        global $wpdb;
        
        parent::create();

        $values = array( 'name'=>$this->getName()
						,'parent_ID'=>$this->parent_ID
						,'event_type'=>$this->getEventType()
						,'format'=>$this->getFormat());
		$formats_values = array('%s','%d','%s','%s');
		$wpdb->insert($wpdb->prefix . self::$tablename, $values, $formats_values);
		$this->ID = $wpdb->insert_id;
		$result = $wpdb->rows_affected;
		$this->isnew = FALSE;
		$this->isdirty = FALSE;

		foreach($this->getChildEvents() as $child) {
			$child->setParent($this);
			$result += $child->save();
		}

		foreach($this->getEventsToBeDeleted() as $evt) {
			$result += $evt->delete();
		}

		foreach($this->getDraw() as $draw) {
			$draw->setEventID($this->getID());
			$result += $draw->save();
		}

		foreach($this->getEntrantsToBeDeleted() as $entPosition) {
			$result += Entrant::deleteEntrant($this->getID(),$entPosition);
		}

		//Create relation between this Event and Clubs
		foreach($this->getClubs() as $cb) {
			$result += $cb->save();
		}

		//Remove relation between this Event and Clubs
		foreach($this->getClubsToBeDeleted() as $clubId) {
			$result += ClubEventRelations::remove($clubId,$this->getID());
		}

		//Create relation between this Event and Clubs
		foreach($this->getClubs() as $cb) {
			$result += ClubEventRelations::add($cb->getID(),$this->getID());
		}

		error_log("Event::create: $result rows affected.");

		return $result;
	}

	protected function update() {
		global $wpdb;

        parent::update();

        $values = array( 'name' => $this->getName()
						,'parent_ID' => $this->parent_ID
						,'event_type'=>$this->getEventType()
						,'format'=>$this->getFormat());
		$formats_values = array('%s','%d','%s','%s');
		$where          = array('ID' => $this->ID);
		$formats_where  = array('%d');
		$wpdb->update($wpdb->prefix . self::$tablename,$values,$where,$formats_values,$formats_where);
		$this->isdirty = FALSE;
		$result = $wpdb->rows_affected;

		foreach($this->getChildEvents() as $child) {
			$child->setParent($this);
			$result += $child->save();
		}

		foreach($this->getEventsToBeDeleted() as $evt) {
			$result += $evt->delete();
		}

		foreach($this->getDraw() as $draw) {
			$draw->setEventID($this->getID());
			$result += $draw->save();
		}

		foreach($this->getEntrantsToBeDeleted() as $ent) {
			$result += $ent->delete();
		}

		//Create relation between this Event and Clubs
		foreach($this->getClubs() as $cb) {
			$result += $cb->save();
		}

		//Remove relation between this Event and Clubs
		foreach($this->getClubsToBeDeleted() as $clubId) {
			$result += ClubEventRelations::remove($clubId,$this->getID());
		}

		//Create relation between this Event and Clubs
		foreach($this->getClubs() as $cb) {
			$result += ClubEventRelations::add($cb->getID(),$this->getID());
		}

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