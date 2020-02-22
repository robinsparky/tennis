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
	private $signup_by; //Cut off date for signing up
	private $start_date; //Start date of this event
	private $end_date; //End date of this event
    
	private $clubs; //array of related clubs for this root event
	private $childEvents; //array of child events
	private $signup; //array of entrants who signed up for this leaf event
	private $brackets; //array of 1 or 2 brackets for this leaf event

	private $clubsToBeDeleted = array(); //array of club Id's to be removed from relations with this Event
	private $childEventsToBeDeleted = array(); //array of child ID's events to be deleted
	private $entrantsToBeDeleted = array(); //array of Entrants to be removed from the draw
	private $bracketsToBeDeleted = array(); //array of bracket Id's to be deleted
    
    /**
     * Search for Events have a name 'like' the provided criteria
     */
    public static function search( $criteria ) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		
		$criteria .= strpos($criteria,'%') ? '' : '%';
		
		$sql = "SELECT `ID`,`event_type`,`name`,`format`,`match_type`,`parent_ID`,`signup_by`,`start_date`, `end_date` 
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
			$sql = "SELECT ce.ID, ce.event_type, ce.name, ce.format, ce.match_type, ce.parent_ID
			 			  ,ce.signup_by,ce.start_date,ce.end_date  
					FROM $table ce
					WHERE ce.parent_ID = %d;";
		}
		elseif( array_key_exists( 'club', $fk_criteria ) ) {
			//All events belonging to specified club
			$col_value = $fk_criteria["club"];
			error_log( "Event::find using club_ID=$col_value" );
			$sql = "SELECT e.ID, e.event_type, e.name, e.format, e.match_type, e.parent_ID 
						  ,e.signup_by,e.start_date,e.end_date 
					from $table e 
					INNER JOIN $joinTable AS j ON j.event_ID = e.ID 
					INNER JOIN $clubTable AS c ON c.ID = j.club_ID 
					WHERE c.ID = %d;";
		} elseif( !isset( $fk_criteria ) ) {
			//All events
			error_log( "Event::find all events" );
			$col_value = 0;
			$sql = "SELECT `ID`,`event_type`,`name`,`format`,`match_type`,`parent_ID`,`signup_by`,`start_date`,`end_date` 
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
	 * Get instance of a Event using it's primary key: ID
	 */
    static public function get( int ...$pks ) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$sql = "SELECT `ID`,`event_type`,`name`,`format`,`match_type`,`parent_ID`,`signup_by`,`start_date`,`end_date` 
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
		parent::__construct( true );
		$this->isnew = true;
		$this->name = $name;
		$this->format = Format::SINGLE_ELIM;

		switch( $eventType ) {
			case EventType::TOURNAMENT:
			case EventType::LEAGUE:
			case EventType::LADDER:
			case EventType::ROUND_ROBIN:
				$this->event_type = $eventType;
				break;
		}
	}
	
    public function __destruct() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		error_log("$loc ... ");
		
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
	 * Set the type of event
	 * Applies only to a root event
	 */
	public function setEventType( string $type ) {
		$result = false;
		if( $this->isRoot() ) {
			switch($type) {
				case EventType::TOURNAMENT:
				case EventType::LEAGUE:
				case EventType::LADDER:
				case EventType::ROUND_ROBIN:
					$this->event_type = $type;
					$result = $this->setDirty();
					break;
				default:
					$result = false;
			}
		}
		return $result;
	}

	public function getEventType():string {
		return isset( $this->event_type) ? $this->event_type : '';
	}

	/**
	 * Set the type of event such as Mens Singles, or Womens Doubles, etc.
	 * @param $matchType
	 * @see MatchType class
	 */
	public function setMatchType( float $matchType ) {
		$result = false;
		if( $this->isLeaf() ) {
			switch( $matchType ) {
				case MatchType::MENS_SINGLES:
				case MatchType::MENS_DOUBLES:
				case MatchType::WOMENS_SINGLES:
				case MatchType::WOMENS_DOUBLES:
					$this->match_type = $matchType;
					$result = $this->setDirty();
					break;
				default:
					$result = false;
			}
		}
		return $result;
	}

	/**
	 * Get the type of match for this event
	 */
	public function getMatchType():float {
		return isset( $this->match_type ) ? $this->match_type : 0.0;
	}
	
	/**
	 * Set the format
	 * Applies only to the lowest child event
	 */
	public function setFormat( string $format ) {
		$result = false;
		if( $this->isLeaf() ) {
			switch($format) {
				case Format::SINGLE_ELIM:
				case Format::DOUBLE_ELIM:
				case Format::GAMES:
				case Format::SETS:
					$this->format = $format;
					$result = $this->setDirty();
					break;
				default:
					$result = false;
					break;
			}
		}
		return $result;
	}

	public function getFormat():string {
		return isset( $this->format ) ? $this->format : '';
	}

    /**
     * Assign a Parent event to this child Event
	 * @param $parent Reference to an Event; can set to null
	 * @return true if succeeds false otherwise
     */
    public function setParent( Event &$parent ) {
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
	 * @param $signup The sign up deadline in YYYY/mm/dd format
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
		if( !isset( $this->signup_by ) ) return null;
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
	 * Add an Entrant to the draw for this Child Event
	 * This method ensures that Entrants are not added ore than once.
	 * 
	 * @param $name The name of a player in this event
	 * @param $seed The seeding of this player
	 * @return true if succeeds false otherwise
	 */
	public function addToSignup ( string $name, int $seed = null ) {
		$result = false;
		if( isset( $name ) && $this->isLeaf() ) {
			$found = false;
			foreach( $this->getSignup() as $d ) {
				if( $name === $d->getName() ) {
					$found = true;
				}
			}
			if( !$found ) {
				$ent = new Entrant( $this->getID(), $name, $seed );
				$this->signup[] = $ent;
				$result = $this->setDirty();
			}
		}
		return $result;
	}
	
	/**
	 * Remove an Entrant from the signup
	 * @param $entrant Entrant in the draw
	 * @return true if succeeds false otherwise
	 */
	public function removeFromSignup( string $name ) {
		$result = false;
		$temp = array();
		for( $i = 0; $i < count( $this->getSignup() ); $i++) {
			if( $name === $this->signup[$i]->getName() ) {
				$this->entrantsToBeDeleted[] = $this->signup[$i]->getPosition();
				$result = $this->setDirty();
			}
			else {
				$temp[] = $this->signup[$i];
			}
		}
		$this->signup = $temp;

		return $result;
	}

	/**
	 * Destroy the existing signup and all related brackets.
	 */
	public function removeSignup() {
		foreach( $this->getSignup() as &$dr ) {
			$this->entrantsToBeDeleted[] = $dr->getPosition();
			unset( $dr );
		}
		$this->signup = array();
		$this->removeBrackets(); //With no signups, must get rid of brackets and matches
		return $this->setDirty();
	}
	
	/**
	 * Get the signup for this Event
	 * @param $force When set to true will force loading of entrants from db
	 *               This will cause unsaved entrants to be lost.
	 */
	public function getSignup( $force=false ) {
		if( !isset( $this->signup ) || $force ) $this->fetchSignup();
		return $this->signup;
	}
	
	/**
	 * Get the size of the signup for this event
	 */
	public function signupSize() {
		$this->getSignup();
		return isset( $this->signup ) ? sizeof( $this->signup ) : 0;
	}
	
	/**
	 * Get a contestant in the Draw by name
	 */
	public function getNamedEntrant( string $name ) {
		$result = null;

		foreach( $this->getSignup() as $draw ) {
			if( $name === $draw->getName() ) {
				$result = $draw;
				break;
			}
		}
		return $result;
	}
	
    /**
     * Move an entrant from its current position to a new position.
     * @param $fromPos The entrant's current position (i.e. place in the lineup)
     * @param $toPos The intended position in the signup
     * @return The rows affected by this update
     */
	public function moveEntrant( int $fromPos, int $toPos ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$eventId = $this->getID();
		error_log("$loc:Event=$eventId; $fromPos to $toPos");
		
		global $wpdb;
        $table = $wpdb->prefix . Entrant::$tablename;
        $fromId = "Entrant($eventId,$fromPos)";
        $toId = "Entrant($eventId,$toPos)";
        $tempPos = 99999;
 
		$result = 0;

        //Check match numbers for appropriate ranges
        if( $fromPos < 1 || $toPos < 1 || $toPos >= $tempPos || ( $fromPos === $toPos ) ) {
			$mess = __("Entrant::move $fromId to $toId: match number(s) out of range.", TennisEvents::TEXT_DOMAIN);
			throw new InvalidEventException( $mess );
        }

        error_log( "Entrant::move: attempting to move from $fromId to $toId" );
        $sql = "SELECT count(*) 
                FROM $table WHERE event_ID = %d AND position = %d;";
                
        $safe = $wpdb->prepare( $sql, array( $eventId, $fromPos ) );
        $sourceExists = (int) $wpdb->get_var( $safe );
        $this->log->error_log("Move $fromId to $toId: sourceExists=$sourceExists");

        if( $sourceExists === 1 ) {
            //Source entrant exists
            //Check if target (i.e. the toPos) exists             
            $safe = $wpdb->prepare( $sql, array( $eventId, $toPos ) );
            $targetExists = (int) $wpdb->get_var( $safe );
            if( $targetExists === 0 ) {
                //Target match number does not exist, so just update the match number to the target number
                $values = array( 'position' => $toPos);
				$formats_values = array( '%d' );
				
				$where          = array( 'event_ID'  => $eventId
                                        ,'position'  => $fromPos );
                $formats_where  = array( '%d', '%d' );
        
                $check = $wpdb->update( $table, $values, $where, $formats_values, $formats_where );
                $result = $wpdb->rows_affected;

                if( $wpdb->last_error ) {
                    $mess = "Moving $fromId to $toId: update open position encountered error: '$wpdb->last_error'";
                    error_log("Entrant.move: $mess");
                    throw new InvalidEntrantException( $mess ); 
                }
                error_log( "Entrant::move to open postion $toPos: $result rows affected." );
            }
            else {   
                //Source and target position numbers exist ...
                //First we have to move the source entrant to a safe place 
                // ... give it a temporary position number
                $values = array( 'position' => $tempPos);
				$formats_values = array( '%d' );
				
                $where          = array( 'event_ID'  => $eventId
                                        ,'position' => $fromPos );
                $formats_where  = array( '%d', '%d' );

                $check = $wpdb->update( $table, $values, $where, $formats_values, $formats_where );

                if( $wpdb->last_error ) {
                    $mess = "Moving $fromId to temporary position $tempPos: encountered error: '$wpdb->last_error'";
                    error_log("Entrant.move: $mess");
                    throw new InvalidEntrantException( $mess ); 
                }
                error_log( "Entrant::move $fromId to temporary position $tempPos: $check rows affected." );

                //Target exists so update match_num by 1 starting from highest to lowest 
                // i.e. from the highest match_num (but less than temp number) down to the target match_num
                //Need to start a transaction (default isolation level)
                $wpdb->query( "start transaction;" );

                $sql = "SELECT `event_ID`,`position`,`name`,`seed` 
                        FROM $table WHERE event_ID = %d AND position >= %d and position < %d 
                        ORDER BY position DESC FOR UPDATE;";
                $safe = $wpdb->prepare( $sql, array( $eventId, $toPos, $tempPos ) );
                $trows = $wpdb->get_results( $safe );

                if( $wpdb->last_error ) {
                    $mess = "Moving $fromId to $toId: select for update encountered error: '$wpdb->last_error'";
                    error_log( "Entrant.move: $mess" );
                    $wpdb->query( "rollback;" ); 
                    throw new InvalidEntrantException( $mess ); 
                }
                
                foreach( $trows as $trow ) {
                    $oldNum = $trow->position;
                    $newNum = $trow->position + 1;

                    $values = array( 'position' => $newNum );
                    $formats_values = array( '%d' );
                    $where          = array( 'event_ID'  => $eventId
                                            ,'position'  => $oldNum );
                    $formats_where  = array( '%d', '%d' );
                    $check = $wpdb->update( $table, $values, $where, $formats_values, $formats_where );

                    if( $wpdb->last_error ) {
                        $mess = "Moving $fromId to $toId: updating $oldNum to $newNum encountered error: '$wpdb->last_error'";
                        error_log("Entrant.move: $mess");
                        $wpdb->query( "rollback;" ); 
                        throw new InvalidEntrantException( $mess ); 
                    }

                    $result += $wpdb->rows_affected;
                    error_log( "Entrant::move making room -> moved position $oldNum to $newNum:  $result cumulative rows affected." );
                }

                //Now update the source's temporary position to the target position
                $values = array( 'position' => $toPos );
                $formats_values = array( '%d' );
                $where          = array( 'event_ID'  => $eventId
                                        ,'position'  => $tempPos );
                $formats_where  = array( '%d', '%d' );
                $check = $wpdb->update( $table, $values, $where, $formats_values, $formats_where );

                if( $wpdb->last_error ) {
                    $mess = "Moving $fromId to $toId: updating $tempPos to $toPos encountered error: '$wpdb->last_error'";
                    error_log("Entrant.move: $mess");
                    $wpdb->query( "rollback;" ) ; 
                    throw new InvalidEntrantException( $mess ); 
                }
                $result += $wpdb->rows_affected;
                
                $wpdb->query( "commit;" );  
                error_log( "Entrant::move from $tempPos to $toPos: $result cumulative rows affected." );
            }
        }
        elseif( $sourceExists > 1 ) {
            //Error condition
            $mess = __( "$fromId: multiple positions found." );
            error_log( $mess );
            throw new InvalidEntrantException( $mess, 500 );
        }
        elseif( $sourceExists === 0 ) {
            $mess = __( "$fromId: position does not exist." );
            error_log("Entrant::move $mess" );
        }

        return $result;
    }
	
    /**
     * Resequence the signup for the given event
     */
    public function resequenceSignup() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		error_log($loc);

		$result = 0;
		if( $this->isParent() ) {
			$mess = __("Parent events do not have signups", TennisEvents::TEXT_DOMAIN );
			throw new InvalidEventException( $mess );
		}

		global $wpdb;
        $table = $wpdb->prefix . Entrant::$tablename;

		$wpdb->query( "start transaction;" );
		
		$sql = "DROP TEMPORARY TABLE IF EXISTS temp_entrant;";
		$affected = (int) $wpdb->get_var( $sql );
		if( $wpdb->last_error ) {
			$mess = "drop temp table encountered error: '$wpdb->last_error'";
			error_log( "$loc: $mess" );
			$wpdb->query( "rollback;" ); 
			throw new InvalidSignupException( $mess ); 
		}

		$sql = "CREATE TEMPORARY TABLE temp_entrant as 
					SELECT * 
					FROM $table 
					WHERE event_ID = %d
					ORDER BY position ASC;";
		$safe = $wpdb->prepare( $sql, array( $this->getID() ) );
		$affected = (int) $wpdb->get_var( $safe );
		if( $wpdb->last_error ) {
			$mess = "create temp table encountered error: '$wpdb->last_error'";
			error_log( "$loc: $mess" );
			$wpdb->query( "rollback;" ); 
			throw new InvalidSignupException( $mess ); 
		}
		
		$where = array( "event_ID" => $this->getID() );
		$affected = $wpdb->delete( $table, $where );
		if( false === $affected ) {
			$mess = "delete from table '$table' encountered error: '$wpdb->last_error'";
			error_log( "$loc: $mess" );
			$wpdb->query( "rollback;" ); 
			throw new InvalidSignupException( $mess ); 
		}
		$this->log->error_log("$loc: deleted $affected rows from $table" );
		
		$sql = "SELECT `event_ID`,`position`,`name`,`seed` 
				FROM temp_entrant 
				ORDER BY event_ID, position ASC;";
		$trows = $wpdb->get_results( $sql );
		$pos = 1;
		foreach( $trows as $trow ) {
			$values = array( 'event_ID' => $trow->event_ID
						   , 'position' => $pos++ 
						   , 'name' => $trow->name
						   , 'seed' => $trow->seed );

			$this->log->error_log( $values, "$loc: inserting..." );

			$formats_values = array( '%d', '%d', '%s', '%d' );
			$check = $wpdb->insert( $table, $values, $formats_values );

			if( $wpdb->last_error ) {
				$mess = "$loc: inserting $trow->name at postion $pos encountered error: '$wpdb->last_error'";
				error_log("$loc: $mess");
				$wpdb->query( "rollback;" ); 
				throw new InvalidSignupException( $mess ); 
			}

			$this->log->error_log("$loc: inserted $check row(s) into $table" );
			$result += $wpdb->rows_affected;
			error_log( "$loc: inserted last position $pos:  $result cumulative rows affected." );
		}
		
		$wpdb->query( "commit;" );  
		error_log( "$loc: $result rows affected." );
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
	 * @return Bracket object or null if not found
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
	 */
	public function getWinnersBracket( ) {
		$loc = __CLASS__ . "::" . __FUNCTION__;
		$this->log->error_log($loc);

		$bracket = $this->getBracket( Bracket::WINNERS );
		if( is_null( $bracket ) ) {
			$bracket = $this->createBracket( Bracket::WINNERS );
			if( is_null( $bracket ) ) {
				throw new InvalidEventException(__("Could not create Winners bracket.",TennisEvents::TEXT_DOMAIN) );
			}
			$bracket->save();
		}
		return $bracket;
	}

	/**
	 * Get the losers bracket for this event.
	 */
	public function getLosersBracket( ) {
		$bracket = $this->getBracket( Bracket::LOSERS );
		if( is_null( $bracket ) ) {
			$bracket = $this->createBracket( Bracket::LOSERS );
			if( is_null( $bracket ) ) {
				throw new InvalidEventException(__("Could not create Losers bracket.",TennisEvents::TEXT_DOMAIN) );
			}
			$bracket->save();
		}
		return $bracket;
	}
	
	/**
	 * Get the consolation bracket for this event.
	 */
	public function getConsolationBracket( ) {
		$bracket = $this->getBracket( Bracket::CONSOLATION );
		if( is_null( $bracket ) ) {
			$bracket = $this->createBracket( Bracket::CONSOLATION );
			if( is_null( $bracket ) ) {
				throw new InvalidEventException(__("Could not create Consolation bracket.",TennisEvents::TEXT_DOMAIN) );
			}
			$bracket->save();
		}
		return $bracket;
	}
	
    /**
     * Add a Bracket to this Event
	 * For regulation single elimination tournaments there should be only 1 bracket
	 * For regulation double elimination tournaments there should be only 2 brackets
	 * For all other situations it depends on the nature of the event
     */
    public function addBracket( Bracket &$bracket ) {
		$result = false;
		$found = false;
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
        return $result;
	}

	/**
	 * Create a bracket with the given name.
	 * If a bracket with that name already exists it is returned.
	 * @param $name
	 */
	public function createBracket( string $name ) {
		$num = 0;
		$found = false;
		$result = null;
		foreach( $this->getBrackets() as $b ) {
			++$num;
			if( $b->getName() === $name ) {
				$found = true;
				$result = $b;
			}
		}
		if( !$found ) {
			$result = new Bracket( );
			$result->setName( $name );
			$this->brackets[] = $result;
			$result->setEvent( $this );
			$this->setDirty();
		}
		return $result;
	}
	
	/**
	 * Remove a bracket with the given name.
	 * @param $name
	 */
	public function removeBracket( string $name ) {
		$num = 0;
		$result = false;
		foreach( $this->getBrackets() as &$bracket ) {
			if( $bracket->getName() === $name ) {
				$result = true;
				$this->bracketsToBeDeleted[] = $bracket;
				unset( $this->brackets[$num] );
			}
			++$num;
		}
		return $result;
	}
	
	/**
	 * Remove this Event's collection of Brackets
	 */
	public function removeBrackets() {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		$this->fetchBrackets();
		if( isset( $this->brackets ) ) {
			foreach( $this->brackets as $bracket ) {
				$bracket->removeAllMatches(); //Unnecssary because of cascading deletes??
				$this->bracketsToBeDeleted[] = $bracket;
				$bracket = null;
			}
		}
		$this->brackets = array();
		return $this->setDirty();
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
		elseif( $this->isRoot() && count( $this->getClubs() ) < 1 ) {
			$mess = __('Root event must be associated with at least one club', TennisEvents::TEXT_DOMAIN );
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
		error_log( sprintf("%s(%s): deleted %d row(s)", $loc, $this->toString(), $result ) );

		return $result;
	}

	public function toString() {
		return sprintf("E(%d)", $this->getID() );
	}

	public function save():int {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		error_log( sprintf("%s called ...", $loc) );
		return parent::save();
	}

	/**
	 * Fetch all children of this event.
	 */
	private function fetchChildEvents() {
		$this->childEvents =  Event::find( array( 'parent_ID' => $this->getID() ) );
	}

	/**
	 * Fetch all Entrants for this event.
	 */
	private function fetchSignup() {
		if( $this->isParent() ) $this->signup = array();
		$this->signup = Entrant::find( $this->getID() );
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
	
    private function sortBySeedDesc( $a, $b ) {
        if($a->getSeed() === $b->getSeed()) return 0; return ($a->getSeed() < $b->getSeed()) ? 1 : -1;
    }
    
    private function sortBySeedAsc( $a, $b ) {
        if($a->getSeed() === $b->getSeed()) return 0; return ($a->getSeed() < $b->getSeed()) ? -1 : 1;
    }
    
    private function sortByPositionAsc( $a, $b ) {
        if($a->getPosition() === $b->getPosition()) return 0; return ($a->getPosition() < $b->getPosition()) ? 1 : -1;
    }


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
						,'signup_by'  => $this->getSignupBy_Str()
						,'start_date' => $this->getStartDate_Str()
						,'end_date'   => $this->getEndDate_Str()
					    );
		$formats_values = array( '%s', '%d', '%s', '%s', '%f', '%s', '%s', '%s' );
		$wpdb->insert( $wpdb->prefix . self::$tablename, $values, $formats_values );
		$this->ID = $wpdb->insert_id;
		$result = $wpdb->rows_affected;
		$this->isnew = FALSE;
		$this->isdirty = FALSE;

		$result += $this->manageRelatedData();
		
		error_log( sprintf( "%s(%s) -> %d rows affected.", $loc, $this->toString(), $result ) );

		return $result;
	}

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
						,'signup_by'  => $this->getSignupBy_Str()
						,'start_date' => $this->getStartDate_Str()
						,'end_date'   => $this->getEndDate_Str()
					    );
		$formats_values = array( '%s', '%d', '%s', '%s', '%f', '%s', '%s', '%s' );
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
		$obj->event_type = $row["event_type"];
		$obj->match_type = $row["match_type"];
        $obj->parent_ID  = $row["parent_ID"];
		$obj->format     = $row["format"];
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

		//Save signups
		if( isset( $this->signup ) ) {
			foreach($this->signup as $ent) {
				$ent->setEventID( $this->getID() );
				$result += $ent->save();
			}
		}

		//Delete signups (Entrants) that were removed from the draw for this Event
		if(count($this->entrantsToBeDeleted) > 0 ) {
			$entrantIds = array_map( function($e){return $e->getID();}, $this->getSignup() );
			foreach( $this->entrantsToBeDeleted as $entId )  {
				if( !in_array( $entId, $entrantIds ) ) {
					$result += Entrant::deleteEntrant( $this->getID(), $entId );
				}
			}
			$this->entrantsToBeDeleted = array();
		}

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

		return $result;
	}

} //end class