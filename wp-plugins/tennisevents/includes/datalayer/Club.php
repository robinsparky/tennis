<?php
namespace datalayer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
	private $courtsToBeDeleted=array(); //array of court ID's needing deletion
	
	/**
	 * External references
	 */
	private $external_refsToBeDeleted = array(); //array of external references to be deleted
	private $external_refs; //array of external reference to something (e.g. custom post type in WordPress)


	/**
	 * Collection of tennis events
	 * such as Leagues, Tournaments and Round Robins
	 */
	private $events;
	private $eventsToBeDeleted=array(); //array of event ID's needing join records deleted
	
	/*************** Static methods ******************/
	/**
	 * Search for Clubs using club name
	 */
	static public function search($criteria) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$sql = "select ID,name from $table where name like '%s'";

		$criteria .= strpos($criteria,'%') ? '' : '%';
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
			$eventId = $fk_criteria[0];
			error_log("Club::find using eventId=$eventId");
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
			error_log("Club:find all clubs");
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
    static public function get(int ...$pks) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$sql = "select ID,name from $table where ID=%d";
		$safe = $wpdb->prepare($sql,$pks);
		$rows = $wpdb->get_results($safe, ARRAY_A);

		$id = $pks[0];
		error_log("Club::get($id) $wpdb->num_rows rows returned.");
		$obj = NULL;
		if( count($rows) === 1 ) {
			$obj = new Club;
			self::mapData($obj,$rows[0]);
		}
		return $obj;
	}
	
	/**
	 * Fetches one or more Clubs from the db with the given external reference
	 * @param $extReference Alphanumeric up to 100 chars
	 * @return Club matching external reference.
	 *         Or an array of clubs matching reference
	 *         Or Null if not found
	 */
	static public function getClubByExtRef( $extReference ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tennis_external_club';		
		$sql = "SELECT `club_ID`
				FROM $table WHERE `external_ID`='%s'";
		$safe = $wpdb->prepare( $sql, $extReference );
		$rows = $wpdb->get_results( $safe, ARRAY_A );
		error_log( sprintf("Club::getClubByExtRef(%d) -> %d rows returned.", $extReference, $wpdb->num_rows ) );
		
		$result = null;
		if( count( $rows ) > 1) {
			$result = array();
			foreach( $rows as $row ) {
				$result[] = Club::get( $row['club_ID'] );
			}
		}
		elseif( count( $rows ) === 1 ) {
			$result = Club::get( $rows[0]['club_ID'] );
		}
		return $result;
	}

	/*************** Instance Methods ****************/
	public function __construct(string $cname=null) {
		$this->isnew = TRUE;
		$this->init();
		if(isset($cname)) $this->name = $cname;
		
		parent::__construct( true );
	}

	public function __destruct() {

		//destroy related events
		if(isset($this->events)) {
			foreach($this->events as &$evt) {
				$evt = null;
			}
		}
		
		//destroy related courts
		if(isset($this->courts)) {
			foreach($this->courts as &$crt) {
				$crt = null;
			}
		}
	}

	public function setName($name) {
		if(!is_string($name) || strlen($name) < 1) return;
		$this->name = $name;
		return $this->setDirty();
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
	public function getCourts($force=false) {
		if(!isset($this->courts) || $force) $this->fetchCourts();
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
				if($court === $cl) {
					$found = true;
					break;
				}
			}
			if(!$found) {
				$this->courts[] = $court;
				$court->setClub($this);
				$result = $this->setDirty();
			}
		}
		return $result;
	}

	/**
	 * Return the Court by its court number
	 * @param $crtnum the Court Number to find
	 */
	public function getCourtByNumber(int $crtnum) {
		$result = null;
		foreach($this->getCourts() as $crt) {
			if($crtnum === $crt->getCourtNumber()) {
				$result = $crt;
				break;
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
					$this->courtsToBeDeleted[] = $court->getCourtNumber();
					unset($this->courts[$i]);
					$result =  $this->setDirty();
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
	public function getEvents($force=false) {
		if(!isset($this->events) || $force) $this->fetchEvents();
		return $this->events;
	}
	
	/**
	 * Add a Event to this Club
	 */
	public function addEvent( $event ) {
		$result = false;
		if(isset($event) && $event->isRoot()) {
			$found = false;
			foreach($this->getEvents() as $ev) {
				if($event->getID() == $ev->getID()) {
					$found = true;
					break;
				}
			}
			if(!$found) {
				$this->events[] = $event;
				$result = $this->setDirty();
			}
		}
		return $result;
	}

	/**
	 * Remove an Event from this Club
	 */
	public function removeEvent($event) {
		if( !isset( $event ) ) return false;
		
		$i=0;
		foreach( $this->getEvents() as $ev ) {
			if($event == $ev) {
				$this->eventsToBeDeleted[] = $event->getID();
				unset( $this->events[$i] );
				return $this->setDirty();
			}
			$i++;
		}
		return false;
	}
	
	/**
	 * A Club can have zero or more external references associated with it.
	 * How these are usesd is up to the developer. 
	 * For example, a custom post type in WordPress
	 * @param string $extRef the external reference to be added to this club
	 * @return bool True if successful; false otherwise
	 */
	public function addExternalRef( string $extRef ) {
		$loc = __CLASS__ . "::" . __FUNCTION__;
		$this->log->error_log( $extRef, "$loc: External Reference Value");

		$result = false;
		if( !empty( $extRef ) ) {
			$found = false;
			foreach( $this->getExternalRefs( true ) as $er ) {
				if( $extRef === $er ) {
					$found = true;
					break;
				}
			}
			if(!$found) {
				$this->external_refs[] = $extRef;
				$result = $this->setDirty();
			}
		}
		return $result;
	}

	/**
	 * Remove the external reference
	 * @param string $extRef The external reference to be removed	 
	 * @return True if successful; false otherwise
	 */
	public function removeExternalRef( string $extRef ) {
		$loc = __CLASS__ . "::" . __FUNCTION__;
		$this->log->error_log("$loc: extRef='{$extRef}'");

		$result = false;
		if( !empty( $extRef ) ) {
			$i=0;
			foreach( $this->getExternalRefs() as $er ) {
				if( $extRef === $er ) {
					$this->external_refsToBeDeleted[] = $extRef;
					unset( $this->external_refs[$i] );
					$result = $this->setDirty();
				}
				$i++;
			}
		}
		return $result;
	}
	
	/**
	 * Get all external references associated with this event
	 * @param $force When set to true will force loading of related external references
	 *               This will cause unsaved external refernces to be lost.
	 */
	public function getExternalRefs( $force = false ) {
		$loc = __CLASS__ . "::" . __FUNCTION__;
		$this->log->error_log($loc);
		
		if( !isset( $this->external_refs ) 
		   || (0 === count( $this->external_refs))  || $force ) {
			$this->fetchExternalRefs();
		}
		return $this->external_refs;
	}

	
	public function isValid() {
		$isvalid = true;
		$mess = '';
		if( !isset( $this->name ) ) {
			$mess = "Club must have a name.";
		}

		if( strlen( $mess ) > 0 ) {
			throw new InvalidClubException( $mess );
		}

		return $isvalid;
	}

	/**
	 * Delete this Event
	 * NOTE: All child events, entrants and club-relations
	 *       will be deleted by DB Cascade
	 */
	public function delete() {
		$result = 0;
		$clubId = $this->getID();
		if(isset($clubId)) {
			global $wpdb;
			$table = $wpdb->prefix . self::$tablename;
			$where = array( 'ID'=>$clubId );
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
	private function fetchEvents() {
		$this->events = Event::find(array('club'=>$this->ID));
		
	}

	/**
	 * Get all courts in this club.
	 */
	private function fetchCourts() {
		$this->courts = Court::find($this->ID);
	}
	
	/**
	 * Fetch the external references to this event from the database
	 */
	private function fetchExternalRefs() {	
		$loc = __CLASS__ . '::' . __FUNCTION__;
		$this->external_refs = ExternalRefRelations::fetchExternalRefs('club', $this->getID() );
	}
	
	protected function create() {
		global $wpdb;

		parent::create();

		$values         = array('name'=>$this->name);
		$formats_values = array('%s');
		$res = $wpdb->insert($wpdb->prefix . self::$tablename, $values, $formats_values);
		
		if( $res === false || $res === 0 ) {
			$mess = "$loc: wpdb->insert returned false or inserted 0 rows.";
			$err = empty($wpdb->last_error) ? '' : $wpdb->last_error;
			$mess .= " : Err='$err'";
			throw new InvalidClubException($mess);
		}
		
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
			$result += $crt->save();
		}
		
		//Delete Courts removed from this Club
		$crtNums = array_map(function($e){return $e->getCourtNumber();},$this->getCourts());
		foreach($this->courtsToBeDeleted as $crtnum) {
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
		foreach($this->eventsToBeDeleted as $evtId) {
			if(!in_array($evtId,$evtIds)) {
				$result += ClubEventRelations::remove($this->getID(),$evtId);
			}
		}

		//Create join between Events and this Club
		foreach($this->getEvents() as $evt) {
			$result += ClubEventRelations::add($this->getID(),$evt->getID());
		}
		
		//Save the External references related to this Club
		if( isset( $this->external_refs ) ) {
			foreach($this->external_refs as $er) {
				//Create relation between this Club and its external references
				$result += ExternalRefRelations::add( 'club', $this->getID(), $er );
			}
		}

		//Remove relation between this Club and external referenceds
		if( count( $this->external_refsToBeDeleted ) > 0 ) {
			foreach( $this->external_refsToBeDeleted as $er ) {
				if( !in_array( $er, $this->external_refs ) ) {
					$result += ExternalRefRelations::remove( 'club', $this->getID(), $er );
				}
			}
			$this->external_refsToBeDeleted = array();
		}

		return $result;
	}

} //end class
 