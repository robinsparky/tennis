<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// require_once('class-abstractdata.php');
// require_once('class-event.php');
// require_once('class-court.php');

/** 
 * Data and functions for Tennis Club(s)
 * @class  Club
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
*/
class Club extends AbstractData
{ 
	//table name
	private static $tablename = 'tennis_club';

	//Attributes
	private $name;

	/**
	 * Collection of tennis courts
	 */
	private $courts;
	private $courtsToBeDeleted; //array of court ID's needing deletion

	/**
	 * Collection of tennis events
	 * such as Leagues, Tournaments and Round Robins
	 */
	private $events;
	private $eventsToBeDeleted; //array of event ID's needing join records deleted
	
	/*************** Static methods ******************/
	/**
	 * Search for Clubs using club name
	 */
	static public function search($criteria) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$sql = "select ID,name from $table where name like '%s'";

		$safe = $wpdb->prepare($sql,$criteria);
		$rows = $wpdb->get_results($safe, ARRAY_A);
		
		error_log("Club::search $wpdb->num_rows rows returned using criteria: $criteria");

		$col = array();
		foreach($rows as $row) {
			$obj = new Club;
			self::mapData($obj,$row);
			$col[] = $obj;
		}
		return $col;
	}

	/**
	 * Find Clubs referenced in a given Event
	 * NOTE: This is neded for inter-club events
	 */
	static public function find(...$fk_criteria) {
		global $wpdb;

		$table = $wpdb->prefix . self::$tablename;
		$joinTable = "{$wpdb->prefix}tennis_club_event";
		$eventTable = "{$wpdb->prefix}tennis_event";
		$col = array();
		$rows;

		if(is_array($fk_criteria) && count($fk_criteria) === 1) {
			//All clubs belonging to specified Event
			$sql = "SELECT c.ID, c.name, e.ID as event_ID, e.name as Event_Name 
					FROM $table c 
					INNER JOIN $joinTable as j on j.club_ID = c.ID 
					INNER JOIN $eventTable as e on e.ID = j.event_ID 
					WHERE e.ID = %d;";
			$safe = $wpdb->prepare($sql,$fk_criteria);
			$rows = $wpdb->get_results($safe, ARRAY_A);
		}
		else {
			//All clubs
			$sql = "SELECT `ID`, `name` FROM $table;";
			$rows = $wpdb->get_results($sql, ARRAY_A);
		}

		error_log("Club::find $wpdb->num_rows rows returned.");

		foreach($rows as $row) {
            $obj = new Club;
            self::mapData($obj,$row);
			$col[] = $obj;
		}
		return $col;

	}

	/**
	 * Get instance of a Club using it's primary key: ID
	 */
    static public function get(int ... $pks) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$sql = "select ID,name from $table where ID=%d";
		$safe = $wpdb->prepare($sql,$pks);
		$rows = $wpdb->get_results($safe, ARRAY_A);

		error_log("Club::get(id) $wpdb->num_rows rows returned.");
		$obj = NULL;
		if( count($rows) === 1 ) {
			$obj = new Club;
			self::mapData($obj,$rows[0]);
		}
		return $obj;
	}

	/*************** Instance Methods ****************/
	public function __construct() {
		$this->isnew = TRUE;
		$this->init();
	}

	public function setName($name) {
		if(!is_string($name) || strlen($name) < 1) return;
		$this->name = $name;
		$this->isdirty = TRUE;
	}
	
    /**
     * Get the name of this object
     */
    public function getName() {
        return $this->name;
	}
	
	/**
	 * Get array of Courts for this Club
	 */
	public function getCourts() {
		if(!isset($this->courts)) $this->courts = array();
		return $this->courts;
	}
	
	/**
	 * Add a Court to this Club
	 */
	public function addCourt($court) {
		$result = false;
		if(isset($court)) {
			$found = false;
			foreach($this->getCourts() as $cl) {
				if($court == $cl) {
					$found = true;
					break;
				}
			}
			if(!$found) {
				$this->courts[] = $court;
				$court->setClubID($this->getID());
				$result = $this->isdirty = true;
			}
		}
		return $result;
	}

	/**
	 * Remove a Court from this Club
	 */
	public function removeCourt($court) {
		$result = false;
		if(isset($court)) {
			$i=0;
			foreach($this->getCourts() as $cl) {
				if($court == $cl) {
					$this->getCourtsForDeletion()[] = $court->getCourtNum();
					unset($this->courts[$i]);
					$result =  $this->isdirty = true;
					break;
				}
				$i++;
			}
		}
		return $result;
	}
	
	/**
	 * Get array of Events for this Club
	 */
	public function getEvents() {
		if(!isset($this->events)) $this->events = array();
		return $this->events;
	}
	
	/**
	 * Add a Event to this Club
	 */
	public function addEvent($event) {
		if(!isset($event)) return false;
		$found = false;
		foreach($this->getEvents() as $ev) {
			if($event == $ev) {
				$found = true;
				break;
			}
		}
		if(!$found) {
			$this->events[] = $event;
			return $this->isdirty = true;
		}
		return false;
	}

	/**
	 * Remove an Event from this Club
	 */
	public function removeEvent($event) {
		if(!isset($event)) return false;
		
		$i=0;
		foreach($this->getEvents() as $ev) {
			if($event == $ev) {
				$this->getEventsForDeletion()[] = $event->getID();
				unset($this->events[$i]);
				return $this->isdirty = true;
			}
			$i++;
		}
		return false;
	}

	/**
	 * Get all my children!
	 * 1. Events
	 * 2. Courts
	 */
    public function getChildren($force=FALSE) {
		$this->events = $this->fetchEvents($force);
		$this->courts = $this->fetchCourts($force);
	}
	
	public function isValid() {
		$isvalid = TRUE;
		if(!isset($this->name)) $isvalid = false;

		return $isvalid;
	}

	/**
	 * Delete this Event
	 * NOTE: All child events, entrants and club-relations
	 *       will be deleted by DB Cascade
	 */
	public function delete() {
		$result = 0;
		$eventId = $this->getID();
		if(isset($eventId)) {
			global $wpdb;
			$table = $wpdb->prefix . self::$tablename;
			$where = array( 'ID'=>$eventId );
			$formats_where = array( '%d' );
			$wpdb->delete($table, $where, $formats_where);
			$result = $wpdb->rows_affected;
		}

		error_log("Club.delete: deleted $result rows");
		return $result;
	}

	/**
	 * Get all events for this club.
	 */
	private function fetchEvents($force) {
		if(count($this->events) === 0 || $force) $this->events = Event::find(array('club'=>$this->ID));
	}

	/**
	 * Get all courts in this club.
	 */
	private function fetchCourts($force) {
		if(count($this->courts) === 0 || $force) $this->courts = Court::find($this->ID);
	}
	
	private function getEventsForDeletion() {
		if(!isset($this->eventsToBeDeleted)) $this->eventsToBeDeleted = array();
		return $this->eventsToBeDeleted;
	}
	
	private function getCourtsForDeletion() {
		if(!isset($this->courtsToBeDeleted)) $this->courtsToBeDeleted = array();
		return $this->courtsToBeDeleted;
	}
	
	protected function create() {
		global $wpdb;

		parent::create();

		$values         = array('name'=>$this->name);
		$formats_values = array('%s');
		$wpdb->insert($wpdb->prefix . self::$tablename, $values, $formats_values);
		$this->ID = $wpdb->insert_id;
		$result = $wpdb->rows_affected;
		$this->isnew = FALSE;
		$this->isdirty = FALSE;

		$result += $this->manageRelatedData();
		
		error_log("Club::create $result rows affected.");

		return $result;
	}

	/**
	 * Update the Club in the database
	 */
	protected function update() {
		global $wpdb;

		parent::update();

		$values         = array('name'=>$this->name);
		$formats_values = array('%s');
		$where          = array('ID' => $this->ID);
		$formats_where  = array('%d');
		$wpdb->update($wpdb->prefix . self::$tablename,$values,$where,$formats_values,$formats_where);
		$this->isdirty = FALSE;
		$result = $wpdb->rows_affected;

		$result += $this->manageRelatedData();

		error_log("Club::update $result rows affected.");
		return $result;
	}
	
    /**
     * Map incoming data to an instance of Club
     */
    protected static function mapData($obj,$row) {
        parent::mapData($obj,$row);
        $obj->name = $row["name"];
	}
	
	private function init() {
		$this->name = NULL;
	}

	private function manageRelatedData():int {
		$result = 0;

		foreach($this->getCourts() as $crt) {
			$crt->setClubId($this->getID());
			$result += $crt->save();
		}
		
		//Delete Courts removed from this Club
		$crtNums = array_map(function($e){return $e->getCourtNum();},$this->getCourts());
		foreach($this->getCourtsForDeletion() as $crtnum) {
			if(!in_array($crtnum,$crtNums)) {
				$result += Court::deleteCourt($this->getID(),$crtnum);
			}
		}

		//Save any new events added to this club
		foreach($this->getEvents() as $ev) {
			$result += $ev->save();
		}

		//Remove some Events related to this Club
		$evtIds = array_map(function($e){return $e->getID();},$this->getEvents());
		foreach($this->getEventsForDeletion() as $evtId) {
			if(!in_array($evtId,$evtIds)) {
				$result += ClubEventRelations::remove($this->getID(),$evtId);
			}
		}

		//Create join between Events and this Club
		foreach($this->getEvents() as $evt) {
			$result += ClubEventRelations::add($this->getID(),$evt->getID());
		}

		return $result;
	}

} //end class
 