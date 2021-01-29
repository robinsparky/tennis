<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
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
	private static $datetimeformat = "Y-m-d H:i:s";
	private static $dateformat = "!Y-m-d";
	private static $storageformat = "Y-m-d";

	private $name; //name or description of the event
	private $parent_ID; //parent Event ID
	private $parent; //parent Event
	private $event_type; //tournament, league, ladder, round robin
	private $format; //single elim, double elim, games won, sets won
	private $match_type; //see class MatchType
	private $score_type; //see class ScoreType
	private $signup_by; //Cut off date for signing up
	private $start_date; //Start date of this event
	private $end_date; //End date of this event
	private $gender_type; //male, female, mixed
	private $age_max = 99; //maximum age allowed
	private $age_min = 1; //minimum age allowed
    
	private $clubs; //array of related clubs for this root event
	private $childEvents; //array of child events
	private $signup; //array of entrants who signed up for this leaf event
	private $brackets; //array of 1 or 2 brackets for this leaf event
	private $external_refs; //array of external reference to something (e.g. custom post type in WordPress)

	private $clubsToBeDeleted = array(); //array of club Id's to be removed from relations with this Event
	private $childEventsToBeDeleted = array(); //array of child ID's events to be deleted
	private $entrantsToBeDeleted = array(); //array of Entrants to be removed from the draw
	private $bracketsToBeDeleted = array(); //array of bracket Id's to be deleted
	private $external_refsToBeDeleted = array(); //array of external references to be deleted
    
    /**
     * Search for Events have a name 'like' the provided criteria
     */
    public static function search( $criteria ) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		
		$criteria .= strpos($criteria,'%') ? '' : '%';
		
		$sql = "SELECT `ID`,`event_type`,`name`,`format`, `match_type`,`score_type`,`gender_type`,`age_min`,`age_max`,`parent_ID`,`signup_by`,`start_date`, `end_date` 
		        FROM $table WHERE `name` like '%s'";
		$safe = $wpdb->prepare($sql,$criteria);
		$rows = $wpdb->get_results($safe, ARRAY_A);
		
		//error_log("Event::search $wpdb->num_rows rows returned using criteria: $criteria");

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
    public static function find( ...$fk_criteria ) {
		$loc = __CLASS__ . '::' . __FUNCTION__;		
		$calledBy = isset(debug_backtrace()[1]['class']) ? debug_backtrace()[1]['class']. '::'. debug_backtrace()[1]['function'] : debug_backtrace()[1]['function'];
		error_log("{$loc} ... called by {$calledBy}");

		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$joinTable = "{$wpdb->prefix}tennis_club_event";
		$clubTable = "{$wpdb->prefix}tennis_club";
		$col = array();
		$col_value;
		
		if(isset( $fk_criteria[0] ) && is_array( $fk_criteria[0]) ) $fk_criteria = $fk_criteria[0];

		if( array_key_exists( 'parent_ID', $fk_criteria ) ) {
			//All events who are children of specified Event
			$col_value = $fk_criteria["parent_ID"];
			error_log("Event::find using parent_ID=$col_value");
			$sql = "SELECT ce.ID, ce.event_type, ce.name, ce.format, ce.match_type, ce.score_type, ce.gender_type, ce.age_max, ce.age_min, ce.parent_ID
			 			  ,ce.signup_by,ce.start_date,ce.end_date  
					FROM $table ce
					WHERE ce.parent_ID = %d;";
		}
		elseif( array_key_exists( 'club', $fk_criteria ) ) {
			//All events belonging to specified club
			$col_value = $fk_criteria["club"];
			error_log( "Event::find using club_ID=$col_value" );
			$sql = "SELECT e.ID, e.event_type, e.name, e.format, e.match_type, e.score_type, e.gender_type, e.age_max, e.age_min, e.parent_ID 
						  ,e.signup_by,e.start_date,e.end_date 
					from $table e 
					INNER JOIN $joinTable AS j ON j.event_ID = e.ID 
					INNER JOIN $clubTable AS c ON c.ID = j.club_ID 
					WHERE c.ID = %d;";
		} elseif( !isset( $fk_criteria ) ) {
			//All events
			error_log( "Event::find all events" );
			$col_value = 0;
			$sql = "SELECT `ID`,`event_type`,`name`,`format`, `match_type`, `score_type`,`gender_type`, `age_max`, `age_min`,`parent_ID`,`signup_by`,`start_date`,`end_date` 
					FROM $table;";
		}
		else {
			return $col;
		}

		$safe = $wpdb->prepare( $sql, $col_value );
		$rows = $wpdb->get_results( $safe, ARRAY_A );

		//error_log( "Event::find $wpdb->num_rows rows returned." );

		foreach( $rows as $row ) {
            $obj = new Event();
            self::mapData( $obj, $row );
			$col[] = $obj;
		}
		return $col;
	}

	/**
	 * Fetches one or more Events from the db with the given external reference
	 * @param $extReference Alphanumeric up to 100 chars
	 * @return Event  matching external reference.
	 *         Or an array of events matching reference
	 *         Or Null if not found
	 */
	static public function getEventByExtRef( $extReference ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tennis_external_event';		
		$sql = "SELECT `event_ID`
				FROM $table WHERE `external_ID`='%s'";
		$safe = $wpdb->prepare( $sql, $extReference );
		$rows = $wpdb->get_results( $safe, ARRAY_A );
		error_log( sprintf("Event::getEventByExtRef(%d) -> %d rows returned.", $extReference, $wpdb->num_rows ) );
		
		$result = null;
		if( count( $rows ) > 1) {
			$result = array();
			foreach( $rows as $row ) {
				$result[] = Event::get( $row['event_ID'] );
			}
		}
		elseif( count( $rows ) === 1 ) {
			$result = Event::get( $rows[0]['event_ID'] );
		}
		return $result;
	}
	
	/**
	 * Fetches one or more Event ids from the db with the given external reference
	 * @param $extReference Alphanumeric up to 100 chars
	 * @return int Event ID matching external reference.
	 *         Or an array of event ids matching reference
	 *         Or 0 if not found
	 */
	static public function getEventIdByExtRef( $extReference ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tennis_external_event';		
		$sql = "SELECT `event_ID`
				FROM $table WHERE `external_ID`='%s'";
		$safe = $wpdb->prepare( $sql, $extReference );
		$rows = $wpdb->get_results( $safe, ARRAY_A );
		error_log( sprintf("Event::getEventIdByExtRef(%d) -> %d rows returned.", $extReference, $wpdb->num_rows ) );
		
		$result = 0;
		if( count( $rows ) > 1) {
			$result = array();
			foreach( $rows as $row ) {
				$result[] = $row['event_ID'];
			}
		}
		elseif( count( $rows ) === 1 ) {
			$result = $rows[0]['event_ID'];
		}
		return $result;
	}

	/**
	 * Get instance of a Event using it's primary key: ID
	 */
    static public function get( int ...$pks ) {
		$loc = __CLASS__ . '::' . __FUNCTION__;		
		$calledBy = debug_backtrace()[1]['function'];
		error_log("{$loc} ... called by {$calledBy}");
		
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$sql = "SELECT `ID`,`event_type`,`name`,`format`,`match_type`,`score_type`, `gender_type`, `age_max`, `age_min`,`parent_ID`,`signup_by`,`start_date`,`end_date` 
		        FROM $table WHERE `ID`=%d";
		$safe = $wpdb->prepare( $sql, $pks );
		$rows = $wpdb->get_results( $safe, ARRAY_A );

		//error_log( sprintf("Event::get(%d) -> %d rows returned.", $pks, $wpdb->num_rows ) );

		$obj = NULL;
		if( count( $rows ) === 1 ) {
			$obj = new Event;
			self::mapData( $obj, $rows[0] );
		}
		return $obj;
	}
	
	static public function deleteEvent( int $eventId ) {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		$result = 0;
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$wpdb->delete( $table,array( 'ID'=>$eventId ), array( '%d' ) );
		$result = $wpdb->rows_affected;
		error_log( sprintf("%s(%d) -> deleted %d row(s)",$loc, $eventId, $result ) );
		return $result;
	}
	
	/**
	 * Get a child event from its ancestor
	 * @param Event $evt The ancestor Event
	 * @param int   $descendantId The id of the descendant event
	 * @return Event which is the descendant or null if not found
	 */
	public static function getEventRecursively( Event $evt, int $descendantId ) {
		$loc = __CLASS__ . ":" . __FUNCTION__;

		error_log("$loc: comparing {$evt->getID()} to {$descendantId}");

		if( $descendantId === $evt->getID() ) return $evt;

		foreach( $evt->getChildEvents() as $child ) {
			$event =  self::getEventRecursively( $child, $descendantId );
			if( !is_null( $event ) && $event->getID() === $descendantId ) {
				error_log("$loc: found descendant with Id = $descendantId");
				return $event;
			}
		}
		return null;		
	}
    

	/******************************* Instance Methods **************************************/
	public function __construct( string $name = null, string $eventType = EventType::TOURNAMENT) {
        $loc = __CLASS__ . "::" . __FUNCTION__;

		parent::__construct( true );

        // static $numEvents= 0;
		// ++$numEvents;
		// $calledBy = debug_backtrace()[1]['function'];		
		// error_log("{$loc} ... {$numEvents} ... called by {$calledBy}");
		// error_log( gw_shortCallTrace() );
		
		$this->isnew = true;
		$this->name = $name;

		if( EventType::isValid( $eventType ) ) {
			$this->event_type = $eventType;
		}
	}
	
    public function __destruct() {
		static $numEvents = 0;
		$loc = __CLASS__ . '::' . __FUNCTION__;
		++$numEvents;
		// error_log("{$loc} ... {$numEvents}");
		
		$this->parent = null;
			if(isset($this->childEvents)) {
			foreach($this->childEvents as &$event){
				unset( $event );
			}
		}
	
		if(isset( $this->clubs ) ) {
			foreach( $this->clubs as &$club ) {
				unset( $club );
			}
		}

		if( isset( $this->signup ) ) {
			foreach($this->signup as &$draw) {
				unset( $draw );
			}
		}

		if( isset( $this->brackets ) ) {
			foreach($this->brackets as &$bracket) {
				unset( $bracket );
			}
		}

		if( isset( $this->bracketsToBeDeleted ) ) {
			foreach($this->bracketsToBeDeleted as &$bracket) {
				unset( $bracket );
			}
		}

		if( isset( $this->external_refs ) ) {
			foreach( $this->external_refs as &$er ) {
				unset( $er );
			}
		}

		if( isset( $this->external_refsToBeDeleted ) ) {
			foreach( $this->external_refsToBeDeleted as &$er ) {
				unset( $er );
			}
		}
	}
	
	/**
	 * Is this Event the hierarchy root?
	 */
	public function isRoot() {
		$p = $this->getParent();
		return !isset( $p );
	}

	/**
	 * Is this event a leaf in the hierarchy of events?
	 * Only leaves can hold brackets
	 */
	public function isLeaf() {
		$p = $this->getParent();
		return ( isset( $p ) && count( $this->getChildEvents() ) === 0 );
	}

	/**
	 * Get the root event in the event hierarchy
	 */
	public function getRoot() {
		if($this->isRoot()) return $this;
		return $this->parent->getRoot();
	}

	/**
	 * Mark this event and all ancestor events as having been modified
	 */
    public function setDirty() {
        if(!$this->isRoot()) {
			$this->getParent()->setDirty();
		}
        //error_log(sprintf("%s(%d) set dirty", __CLASS__, $this->getID() ) );
        return parent::setDirty();
	}
	
    /**
     * Set a new value for a name of an Event
     */
	public function setName( string $name ) {
		$this->name = $name;
		$this->setDirty();
    }
    
    /**
     * Get the name of this Event
     */
    public function getName():string {
        return $this->name;
    }

	/**
	 * Set the type of event: e.g. League, Tournament, Ladder
	 * Applies only to a root event
	 * @param string $type
	 * @return boolean True if successfull false otherwise
	 */
	public function setEventType( string $type ) {
		$result = false;
		if( $this->isRoot() ) {
			if( EventType::isValid( $type ) ) {
				$this->event_type = $type;
				$result = $this->setDirty();
			}
		}
		return $result;
	}

	/**
	 * Get the Event Type for this event
	 * @return string The event type or empty string if not set
	 */
	public function getEventType():string {
		return  $this->event_type ?? '';
	}

	/**
	 * Set the type of matches in this event: Singles, or Doubles
	 * @param $matchType
	 * @see MatchType class
	 */
	public function setMatchType( $matchType ) {
		$loc = __CLASS__ . "::" . __FUNCTION__;
		$this->log->error_log("{$loc}({$matchType})");

		$result = false;
		if( $this->isLeaf() ) {
			if( MatchType::isValid( $matchType ) ) {
				$this->match_type = $matchType;
				$result = $this->setDirty();
			}
		}
		return $result;
	}

	/**
	 * Get the type of match for this event
	 */
	public function getMatchType() {
		return isset( $this->match_type ) ? $this->match_type : '';
	}
	
	/**
	 * Set the format which specifies Elimination rounds or Round Robin style.
	 * Applies only to the lowest child event
	 * @param string $format
	 * @return bool True if successful false otherwise
	 */
	public function setFormat( string $format ) {
		$result = false;
		if( $this->isLeaf() ) {
			if( Format::isValid( $format ) ) {
				$this->format = $format;
				$result = $this->setDirty();
			}
		}
		return $result;
	}

	public function getFormat():string {
		return isset( $this->format ) ? $this->format : '';
	}

	/**
	 * Set the score type for this leaf events
	 * @param string $scoreType
	 * @return bool True if successful false otherwise
	 */
	public function setScoreType( string $scoreType ) {
		$result = false;
		if( $this->isLeaf() ) {
			if( ScoreType::get_instance()->isValid( $scoreType ) ) {
				$this->score_type = $scoreType;
				$result = $this->setDirty();
			}
		}
		return $result;
	}

	/**
	 * Get the type of scoring for this event
	 */
	public function getScoreType( ) : string {
		return $this->score_type ?? '';
	}

	/**
	 * Set the minimum age for this event's eligibility
	 * @param int $age_min
	 */
	public function setMinAge( int $age_min ) {
		$result = false;
		if( $age_min > 1 ) {
			$this->age_min = $age_min;
			$result = $this->setDirty();
		}
		return $result;
	}

	/**
	 * Get the minimum age for this event's eligibility
	 */
	public function getMinAge() : int {
		return $this->age_min ?? 1;
	}
	
	/**
	 * Set the maximum age for this event's eligibility
	 * @param int $age_max
	 */
	public function setMaxAge( int $age_max ) {
		$result = false;
		if( $age_max > 1 && $age_max < 100 ) {
			$this->age_max = $age_max;
			$result = $this->setDirty();
		}
		return $result;
	}

	/**
	 * Get the maximum age for this event's eligibility
	 * Default's to 99.
	 */
	public function getMaxAge() : int {
		return $this->age_max ?? 99;
	}

    /**
     * Assign a Parent event to this child Event
	 * @param $parent Reference to an Event; can set to null
	 * @return true if succeeds false otherwise
     */
    public function setParent( Event &$parent = null ) {
		$result = false;
		// if($parent->isNew() || !$parent->isValid()) {
		// 	return false;
		// }
		if( isset( $parent ) ) {
			$this->parent = $parent;
			$this->parent_ID = $parent->getID();
			$parent->addChild( $this );
			$result = $this->setDirty();
		}
		return $result;
    }

    public function getParent($force=false) {
        if( ( isset( $this->parent_ID ) && !isset( $this->parent ) ) || $force ) {
            $this->parent = Event::get( $this->parent_ID );
		}
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
	 * @param string $signup The sign up deadline in YYYY/mm/dd format
	 * @return bool True if successful, false otherwise
	 */
	public function setSignupBy( string $signup ) {
		$result = false;
		if( empty( $signup ) ) return $result;

		$test = DateTime::createFromFormat( '!Y/m/d', $signup );
		if(false === $test) $test = DateTime::createFromFormat( '!Y/n/j', $signup );
		if(false === $test) $test = DateTime::createFromFormat( '!Y-m-d', $signup );
		if(false === $test) $test = DateTime::createFromFormat( '!Y-n-j', $signup );
		$last = DateTIme::getLastErrors();
		if( $last['error_count'] > 0 ) {
			$arr = $last['errors'];
			$mess = 'SignupBy: ';
			foreach($arr as $err) {
				$mess .= $err.':';
			}
			throw new InvalidEventException( $mess );
		}
		elseif( $test instanceof DateTime ) {
			$this->signup_by = $test;
			$result = $this->setDirty();
		}

        return $result;
	}

	/**
	 * Get the date by which players must signup for this event
	 */
	public function getSignupBy_Str() {
		if( !isset( $this->signup_by ) ) return null;
		else return $this->signup_by->format( self::$datetimeformat );
	}

	/**
	 * Get the date by which players must signup for this event 
	 * in ISO 8601 format
	 */
	public function getSignupBy_ISO() {
		if( !isset( $this->signup_by ) ) return '';
		else return $this->signup_by->format( DateTime::ISO8601 );
	}

	public function getSignupBy() {
		return $this->signup_by;
	}
	
	public function setStartDate( string $start ) {
		$result = false;
		if( empty( $start ) ) return $result;

		$test = DateTime::createFromFormat( '!Y/m/d', $start );
		if(false === $test) $test = DateTime::createFromFormat( '!Y/n/j', $start );
		if(false === $test) $test = DateTime::createFromFormat( '!Y-m-d', $start );
		if(false === $test) $test = DateTime::createFromFormat( '!Y-n-j', $start );
		$last = DateTIme::getLastErrors();
		if( $last['error_count'] > 0 ) {
			$arr = $last['errors'];
			$mess = 'Start Date: ';
			foreach( $arr as $err ) {
				$mess .= $err.':';
			}
			throw new InvalidEventException( $mess );
		}
		elseif( $test instanceof DateTime ) {
			$this->start_date = $test;
			$result = $this->setDirty();
		}

        return $result;
	}

	/**
	 * Get the start date for this event as a string
	 */
	public function getStartDate_Str() {
		if( !isset( $this->start_date ) ) return null;
		else return $this->start_date->format( self::$datetimeformat );
	}

	/**
	 * Get the start date of this event
	 * as a DateTime object
	 */
	public function getStartDate() {
		return $this->start_date;
	}
	
	/**
	 * Get the start date for this event in ISO 8601 format
	 */
	public function getStartDate_ISO() {
		if( !isset( $this->start_date ) ) return null;
		else return $this->start_date->format( DateTime::ISO8601 );
	}

	/**
	 * Get the season (i.e. year) in which this event was held
	 * @return string Year of start date. Defaults to current year.
	 */
	public function getSeason() {
		if( !isset( $this->start_date ) ) return date( 'Y' );
		else return $this->start_date->format( 'Y' );
	}
	
	/**
	 * Set the end date for this event
	 * @param $end End date in string format
	 */
	public function setEndDate( string $end ) {
		$result = false;
		if( empty( $end ) ) return $result;

		$test = DateTime::createFromFormat('!Y/m/d',$end);
		if(false === $test) $test = DateTime::createFromFormat( '!Y/n/j', $end );
		if(false === $test) $test = DateTime::createFromFormat( '!Y-m-d', $end );
		if(false === $test) $test = DateTime::createFromFormat( '!Y-n-j', $end );
		$last = DateTIme::getLastErrors();
		if( $last['error_count'] > 0 ) {
			$arr = $last['errors'];
			$mess = 'End Date: ';
			foreach( $arr as $err ) {
				$mess .= $err . ':';
			}
			throw new InvalidEventException( $mess );
		}
		elseif( $test instanceof DateTime ) {
			$this->end_date = $test;
			$result = $this->setDirty();
		}

        return $result;
	}

	/**
	 * Get the end date for this event in string format
	 */
	public function getEndDate_Str() {
		if( !isset( $this->end_date ) ) return null;
		else return $this->end_date->format( self::$datetimeformat );
	}
	
	/**
	 * Get the end date for this event in ISO 8601 format
	 */
	public function getEndDate_ISO() {
		if( !isset( $this->end_date ) ) return null;
		else return $this->end_date->format( DateTime::ISO8601 );
	}

	/**
	 * Get the end date for this event as a DateTime object
	 */
	public function getEndDate() {
		return $this->end_date;
	}

	public function setGenderType( $gender ) {
		$result = false;
		if( GenderType::isValid( $gender ) ) {
			$this->gender_type = $gender;
			$result = $this->setDirty();
		}
		return $result;		
	}

	public function getGenderType() :string {
		return $this->gender_type ?? '';
	}

	/**
	 * Add a child event to this Parent Event
	 * This method ensures that the same child event is not added more than once.
	 * 
	 * @param $child child Event
	 * @return true if succeeds false otherwise
	 */
	public function addChild( Event &$child ) {
		$result = false;
		if( isset( $child ) ) {
			$found = false;
			foreach( $this->getChildEvents() as $ch ) {
				if( $child == $ch ) {
					$found = true;
					break;
				}
			}
			if(!$found) {
				$this->childEvents[] = $child;
				$child->setParent( $this );
				$result = $this->setDirty();
			}
		}
		return $result;
	}

	/**
	 * Remove an child Event from this Event
	 * @param $child Event
	 * @return true if succeeds false otherwise
	 */
	public function removeChild( Event &$child )  {
		$result = false;
		$temp = array();
		$i=0;
		foreach( $this->getChildEvents() as $ch ) {
			if( $child === $ch ) {
				$this->childEventsToBeDeleted[] = $child->getID();
				$result = $this->setDirty();
			}
			else {
				$temp[] = $ch;
			}
			$i++;
		}
		$this->childEvents = $temp;
		return $result;
	}

	/**
	 * Get all Events belonging to this Event
	 * @param $force When set to true will force loading of child events
	 *               This will cause unsaved child events to be lost.
	 */
	public function getChildEvents( $force = false ) {
		if( !isset( $this->childEvents ) || $force ) {
			$this->fetchChildEvents();
			foreach( $this->childEvents as $child ) {
				$child->parent = $this;
			}
		}
		return $this->childEvents;
	}

	/**
	 * Get an Event with a specific name belonging to this Event
	 */
	public function getNamedEvent( string $name ) {
		$result = null;

		foreach( $this->getChildEvents() as $evt ) {
			if( $name === $evt->getName() ) {
				$result = $evt;
				break;
			}
		}
		return $result;
	}
	
	/**
	 * Get a specific descendant event
	 * Uses recursion up to 10 levels deep
	 * @param $descendantId The id of the descendant event
	 */
    public function getDescendant( int $descendantId ) {
		$loc = __CLASS__ . ":" . __FUNCTION__;

        if( $descendantId === $this->getID() ) {
			return $this;
		}

		foreach( $this->getChildEvents() as $child ) {
			$event = $this->getDescendant( $child, $descendantId );
			if( !is_null( $event )  && $descendantId === $event->getID() ) {
				$this->log->error_log("$loc: found descendant  with Id = $descendantId");
				return $event;
			}
		}
        return null;
    }

	/**
	 * A root level Event can be associated with one or more clubs. 
	 * With the exception of inter-club leagues most of the time an event is only associated with one club.
	 * @param $club the Club to be added to this Event
	 */
	public function addClub( Club &$club ) {
		$result = false;
		if( isset( $club ) && $this->isRoot() ) {
			$found = false;
			foreach( $this->getClubs() as $cl ) {
				if( $club === $cl ) {
					$found = true;
					break;
				}
			}
			if(!$found) {
				$this->clubs[] = $club;
				$result = $this->setDirty();
			}
		}
		return $result;
	}

	public function removeClub( Club &$club ) {
		$result = false;
		if( isset( $club ) ) {
			$i=0;
			foreach( $this->getClubs() as $cl ) {
				if( $club === $cl ) {
					$this->clubsToBeDeleted[] = $club->getID();
					unset( $this->clubs[$i] );
					$result = $this->setDirty();
				}
				$i++;
			}
		}
		return $result;
	}
	
	/**
	 * Get all Clubs associated with this event
	 * @param $force When set to true will force loading of related clubs
	 *               This will cause unsaved clubs to be lost.
	 */
	public function getClubs( $force = false ) {
		if( !isset( $this->clubs ) || $force ) $this->fetchClubs();
		return $this->clubs;
	}
	
	/**
	 * Get an entrant by name from the specified bracket in this Event
	 * @param string $name
	 * @param string $bracketname
	 * @return Entrant
	 */
	public function getNamedEntrant( string $name, string $bracketname = Bracket::WINNERS ) {
		$result = null;

		$bracket = $this->getBracket( $bracketname );
		$result = $bracket->getNamedEntrant( $name );

		return $result;
	}
	
	/**
	 * Get all the brackets for this Event
	 */
	public function getBrackets( $force = false ) {
		if( $this->isLeaf() ) {
			if( !isset( $this->brackets ) || $force ) $this->fetchBrackets();
		}
		else {
			return array();
		}
		return $this->brackets;
	}

	/**
	 * Get a bracket by its name
	 * Ignores case
	 * @param $bracketName The name of the bracket
	 * @return object Bracket object or null if not found
	 */
	public function getBracket( string $bracketName = Bracket::WINNERS ) {
		$result = null;

		foreach( $this->getBrackets() as $bracket ) {
			if( strcasecmp( $bracketName, $bracket->getName() ) === 0 ) {
				$result = $bracket;
				break;
			}
		}
		return $result;
	}
	
	/**
	 * Get the winners bracket for this event.
	 * @return object Bracket
	 */
	public function getWinnersBracket( ) {
		$loc = __CLASS__ . "::" . __FUNCTION__;
		$this->log->error_log($loc);

		$bracket = null;
		if( $this->isLeaf() ) {
			$bracket = $this->getBracket( Bracket::WINNERS );
			if( is_null( $bracket ) ) {
				$bracket = $this->createBracket( Bracket::WINNERS );
				if( is_null( $bracket ) ) {
					throw new InvalidEventException(__("Could not create Winners bracket.",TennisEvents::TEXT_DOMAIN) );
				}
				$bracket->save();
			}
		}
		return $bracket;
	}

	/**
	 * Get the losers bracket for this event.
	 * @return object Bracket
	 */
	public function getLosersBracket( ) {
		$loc = __CLASS__ . "::" . __FUNCTION__;
		$this->log->error_log($loc);

		$bracket = null;
		if( $this->isLeaf() ) {
			$bracket = $this->getBracket( Bracket::LOSERS );
			if( is_null( $bracket ) ) {
				$bracket = $this->createBracket( Bracket::LOSERS );
				if( is_null( $bracket ) ) {
					throw new InvalidEventException(__("Could not create Losers bracket.",TennisEvents::TEXT_DOMAIN) );
				}
				$bracket->save();
			}
		}
		return $bracket;
	}
	
	/**
	 * Get the consolation bracket for this event.
	 * @return object Bracket
	 */
	public function getConsolationBracket( ) {
		$loc = __CLASS__ . "::" . __FUNCTION__;
		$this->log->error_log($loc);

		$bracket = null;
		if( $this->isLeaf() ) {
			$bracket = $this->getBracket( Bracket::CONSOLATION );
			if( is_null( $bracket ) ) {
				$bracket = $this->createBracket( Bracket::CONSOLATION );
				if( is_null( $bracket ) ) {
					throw new InvalidEventException(__("Could not create Consolation bracket.",TennisEvents::TEXT_DOMAIN) );
				}
				$bracket->save();
			}
		}
		return $bracket;
	}
	
    /**
     * Add a Bracket to this Event
	 * For regulation single elimination tournaments there should be only 1 bracket
	 * For regulation double elimination tournaments there should be only 2 brackets
	 * For all other situations it depends on the nature of the event
	 * @param object $bracket The bracket to be added
	 * @return bool  True if added false otherwise
     */
    public function addBracket( Bracket &$bracket ) {
		$loc = __CLASS__ . "::" . __FUNCTION__;
		$this->log->error_log($loc);

		$result = false;
		$found = false;
		if( $this->isLeaf() ) {
			if( $this->isLeaf() && $bracket->getEvent()->getID() === $this->getID() && $bracket->isValid() ) {
				foreach( $this->getBrackets() as $b ) {
					if($b->getBracketNumber() === $bracket->getBracketNumber() ) {
						$found = true;
						break;
					}
				}
				if( !$found ) {
					$this->brackets[] = $bracket;
					$bracket->setEvent( $this );
					$result = $this->setDirty();
				}
			}
		}
        return $result;
	}

	/**
	 * Create a bracket with the given name.
	 * If a bracket with that name already exists it is returned.
	 * @param string $name The name of the bracket
	 * @return object The bracket or null if this event is not a leaf
	 */
	public function createBracket( string $name ) {
		$loc = __CLASS__ . "::" . __FUNCTION__;
		$this->log->error_log($loc);

		$num = 0;
		$found = false;
		$result = null;
		if( $this->isLeaf() ) {
			foreach( $this->getBrackets() as $b ) {
				++$num;
				if( $b->getName() === $name ) {
					$found = true;
					$result = $b;
				}
			}
			if( !$found ) {
				$result = new Bracket;
				$result->setName( $name );
				$this->brackets[] = $result;
				$result->setEvent( $this );
				$this->setDirty();
			}
		}
		return $result;
	}
	
	/**
	 * Remove a bracket with the given name.
	 * @param $name
	 */
	public function removeBracket( string $name ) {
		$loc = __CLASS__ . "::" . __FUNCTION__;
		$this->log->error_log($loc);

		$num = 0;
		$result = false;
		if( isset( $this->brackets ) ) {
			foreach( $this->getBrackets() as &$bracket ) {
				if( $bracket->getName() === $name ) {
					$result = true;
					$this->bracketsToBeDeleted[] = $bracket;
					unset( $this->brackets[$num] );
				}
				++$num;
			}
		}
		return $result;
	}
	
	/**
	 * Remove this Event's collection of Brackets
	 * @return bool True if successful false otherwise
	 */
	public function removeBrackets() {
		$loc = __CLASS__ . "::" . __FUNCTION__;
		$this->log->error_log($loc);

		$this->fetchBrackets();
		if( isset( $this->brackets ) ) {
			foreach( $this->brackets as $bracket ) {
				//Unnecssary because of cascading deletes??
				$bracket->removeSignup();
				$bracket->removeAllMatches(); 

				$this->bracketsToBeDeleted[] = $bracket;
				$bracket = null;
			}
		}
		$this->brackets = array();
		return $this->setDirty();
	}
	
	/**
	 * An Event can have zero or more external references associated with it.
	 * How these are usesd is up to the developer. 
	 * For example, a custom post type in WordPress
	 * @param string $extRef the external reference to be added to this event
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

	/**
	 * Check to see if this Event has valid data
	 */
	public function isValid() {
		$mess = '';

		if( !isset( $this->name ) ) {
			$mess = __('Event must have a name.', TennisEvents::TEXT_DOMAIN );
		}
		elseif( !isset( $this->event_type ) && $this->isRoot() ) {
			$mess = __('Root Events must have a type.', TennisEvents::TEXT_DOMAIN );
		}
		elseif( !isset( $this->format ) && $this->isLeaf() ) {
			$mess = __('Leaf events must have a format.', TennisEvents::TEXT_DOMAIN );
		}
		elseif( !isset( $this->match_type ) && $this->isLeaf() ) {
			$mess = __('Leaf events must have a match type.', TennisEvents::TEXT_DOMAIN );
		}
		elseif( !isset( $this->score_type ) && $this->isLeaf() ) {
			$mess = __('Leaf events must have a score type.', TennisEvents::TEXT_DOMAIN );
		}
		elseif( $this->isRoot() && count( $this->getClubs() ) < 1 ) {
			$mess = __('Root event must be associated with at least one club', TennisEvents::TEXT_DOMAIN );
		}
		elseif( $this->isLeaf() && isset( $this->score_type ) && isset( $this->format ) ) {
			$acceptable = ScoreType::get_instance()->validScoringRules($this->format);
			if( count($acceptable) > 0 && !in_array( $this->score_type, array_keys($acceptable) ) ) {
				$mess = __("Score Type '{$this->score_type }' is invalid for the assigned Format '{$this->format}'");
			}
		}

		if(strlen( $mess ) > 0) throw new InvalidEventException( $mess );

		return true;
	}

	/**
	 * Delete this event
	 * All child objects will be deleted via DB Cascade
	 */
	public function delete() {
		$loc = __CLASS__ . '::' . __FUNCTION__;

		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$where = array( 'ID'=>$this->getID() );
		$formats_where = array( '%d' );
		$wpdb->delete( $table, $where, $formats_where );
		$result = $wpdb->rows_affected;
		$this->log->error_log( sprintf("%s(%s): deleted %d row(s)", $loc, $this->toString(), $result ) );

		return $result;
	}

	public function toString() {
		return sprintf("E(%d)", $this->getID() );
	}

	public function save():int {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log( sprintf("%s called ...", $loc) );
		return parent::save();
	}

	/**
	 * Fetch all children of this event.
	 */
	private function fetchChildEvents() {
		$this->childEvents =  Event::find( array( 'parent_ID' => $this->getID() ) );
	}

	/**
	 * Fetch all related clubs for this Event
	 * Root-level Events can be associated with one or more clubs
	 */
	private function fetchClubs() {
		$this->clubs = Club::find( $this->getID() );
	}

	/**
	 * Fetch the brackets for this event from the database
	 */
	private function fetchBrackets() {
		$this->brackets = Bracket::find( $this->getID() );
		foreach( $this->brackets as $bracket ){
			$bracket->setEvent( $this );
		}
	}

	/**
	 * Fetch the external references to this event from the database
	 */
	private function fetchExternalRefs() {	
		$loc = __CLASS__ . '::' . __FUNCTION__;
		$this->external_refs = ExternalRefRelations::fetchExternalRefs('event', $this->getID() );
	}

	/*
    private function sortBySeedDesc( $a, $b ) {
        if($a->getSeed() === $b->getSeed()) return 0; return ($a->getSeed() < $b->getSeed()) ? 1 : -1;
    }
    
    private function sortBySeedAsc( $a, $b ) {
        if($a->getSeed() === $b->getSeed()) return 0; return ($a->getSeed() < $b->getSeed()) ? -1 : 1;
    }
    
    private function sortByPositionAsc( $a, $b ) {
        if($a->getPosition() === $b->getPosition()) return 0; return ($a->getPosition() < $b->getPosition()) ? 1 : -1;
    }
	*/

	/**
	 * Create a new Event record in the database
	 */
	protected function create() {
		$loc = __CLASS__ . '::' . __FUNCTION__;

        global $wpdb;
        
        parent::create();

		$this->parent_ID = isset( $this->parent ) ? $this->parent->getID() : null;

        $values = array( 'name'       => $this->getName()
						,'parent_ID'  => $this->parent_ID
						,'event_type' => $this->getEventType()
						,'format'     => $this->getFormat()
						,'match_type' => $this->getMatchType()
						,'score_type' => $this->getScoreType()
						,'gender_type'=> $this->getGenderType()
						,'age_min'    => $this->getMinAge()
						,'age_max'    => $this->getMaxAge()
						,'signup_by'  => $this->getSignupBy_Str()
						,'start_date' => $this->getStartDate_Str()
						,'end_date'   => $this->getEndDate_Str()
					    );
		$formats_values = array( '%s', '%d', '%s', '%s', '%s', '%s','%s', '%d','%d', '%s', '%s', '%s' );
		$wpdb->insert( $wpdb->prefix . self::$tablename, $values, $formats_values );
		$this->ID = $wpdb->insert_id;
		$result = $wpdb->rows_affected;
		$this->isnew = FALSE;
		$this->isdirty = FALSE;

		$result += $this->manageRelatedData();
		
		error_log( sprintf( "%s(%s) -> %d rows affected.", $loc, $this->toString(), $result ) );

		return $result;
	}

	/**
	 * Update the record in the database
	 */
	protected function update() {
		$loc = __CLASS__ . '::' . __FUNCTION__;

		global $wpdb;

        parent::update();

		$this->parent_ID = isset( $this->parent ) ? $this->parent->getID() : null;

        $values = array( 'name'       => $this->getName()
						,'parent_ID'  => $this->parent_ID
						,'event_type' => $this->getEventType()
						,'format'     => $this->getFormat()
						,'match_type' => $this->getMatchType()
						,'score_type' => $this->getScoreType()
						,'gender_type'=> $this->getGenderType()
						,'age_min'    => $this->getMinAge()
						,'age_max'    => $this->getMaxAge()
						,'signup_by'  => $this->getSignupBy_Str()
						,'start_date' => $this->getStartDate_Str()
						,'end_date'   => $this->getEndDate_Str()
					    );
		$formats_values = array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s' );
		$where          = array( 'ID' => $this->ID );
		$formats_where  = array( '%d ');
		$check = $wpdb->update( $wpdb->prefix . self::$tablename,$values,$where,$formats_values,$formats_where );
		$this->isdirty = false;
		$result = $wpdb->rows_affected;

		$result += $this->manageRelatedData();
		
		error_log( sprintf( "%s(%s) -> %d rows affected.",$loc, $this->toString(), $result ) );
		
		return $result;
	}

    /**
     * Map incoming data to an instance of Event
     */
    protected static function mapData( $obj, $row ) {
		parent::mapData( $obj, $row );
        $obj->name       = $row["name"];
        $obj->parent_ID  = $row["parent_ID"];
		$obj->event_type = $row["event_type"];
		$obj->match_type = $row["match_type"];
		$obj->format     = $row["format"];
		$obj->score_type = $row["score_type"];
		$obj->gender_type = $row["gender_type"];
		$obj->age_max    = $row["age_max"];
		$obj->age_min    = $row["age_min"];
		$obj->signup_by  = isset( $row['signup_by'] )  ? new DateTime( $row['signup_by'] ) : null;
		$obj->start_date = isset( $row['start_date'] ) ? new DateTime( $row['start_date'] ) : null;
		$obj->end_date   = isset( $row["end_date"] )   ? new DateTime( $row["end_date"] ) : null;
	}

	private function manageRelatedData():int {
		$result = 0;

		//Save each child event
		$evtIds = array();
		if( isset( $this->childEvents ) ) {
			foreach( $this->childEvents as $evt ) {
				$result += $evt->save();
				$evtIds[] = $evt->getID();
			}
		}

		//Delete Events removed from being a child of this Event
		if( count( $this->childEventsToBeDeleted ) > 0 ) {
			foreach( $this->childEventsToBeDeleted as $id ) {
				if(!in_array($id,$evtIds)) {
					$result += Event::deleteEvent( $id );
				}
			}
			$this->childEventsToBeDeleted = array();
		}
		
		//Save brackets
		if( isset( $this->brackets ) ) {
			foreach( $this->brackets as $bracket ) {
				$result += $bracket->save();
			}
		}

		//Delete Brackets removed from this Event
		foreach( $this->bracketsToBeDeleted as &$bracket ) {
			//$bracketnums = array_map( function($e){return $e->getBracketNumber();}, $this->getBrackets() );
			//if( !in_array( $bracket->getBracketNumber(), $bracketnums ) ) {
				$result += $bracket->delete();
				unset( $bracket );		
			//}	
		}
		$this->bracketsToBeDeleted = array();

		//Save the Clubs related to this Event
		if( isset( $this->clubs) ) {
			foreach($this->clubs as $cb) {
				$result += $cb->save();
				//Create relation between this Event and its Clubs
				$result += ClubEventRelations::add( $cb->getID(), $this->getID() );
			}
		}

		//Remove relation between this Event and Clubs
		if( count( $this->clubsToBeDeleted ) > 0 ) {
			$clubIds = array_map(function($e){return $e->getID();},$this->getClubs());
			foreach( $this->clubsToBeDeleted as $clubId ) {
				if( !in_array( $clubId, $clubIds ) ) {
					$result += ClubEventRelations::remove( $clubId, $this->getID() );
				}
			}
			$this->clubsToBeDeleted = array();
		}
		
		//Save the External references related to this Event
		if( isset( $this->external_refs ) ) {
			foreach($this->external_refs as $er) {
				//Create relation between this Event and its external references
				$result += ExternalRefRelations::add('event', $this->getID(), $er );
			}
		}

		//Remove relation between this Event and external referenceds
		if( count( $this->external_refsToBeDeleted ) > 0 ) {
			foreach( $this->external_refsToBeDeleted as $er ) {
				if( !in_array( $er, $this->external_refs ) ) {
					$result += ExternalRefRelations::remove('event', $this->getID(), $er );
				}
			}
			$this->external_refsToBeDeleted = array();
		}

		return $result;
	}

} //end class