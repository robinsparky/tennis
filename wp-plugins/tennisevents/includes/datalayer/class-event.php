<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
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
	private static $datetimeformat = "Y-m-d H:i:s";
	private static $dateformat = "!Y-m-d";
	private static $storageformat = "Y-m-d";

	private $name; //name or description of the event
	private $parent_ID; //parent Event ID
	private $parent; //parent Event
	private $event_type; //tournament, league, ladder, round robin
	private $format; //single elim, double elim, games won, sets won
	private $signup_by; //Cut off date for signing up
	private $start_date; //Start date of this event
	private $end_date; //End date of this event
    
	private $clubs; //array of related clubs for this root event
	private $childEvents; //array of child events
	private $draw; //array of entrants for this leaf event
	private $matches; //array of matches in a round for this leaf event

	private $clubsToBeDeleted = array(); //array of club Id's to be removed from relations with this Event
	private $childEventsToBeDeleted = array(); //array of child ID's events to be deleted
	private $entrantsToBeDeleted = array(); //array of Entrants to be removed from the draw
	private $matchesToBeDeleted = array(); //array of round Id's to be deleted
    
    /**
     * Search for Events have a name 'like' the provided criteria
     */
    public static function search( $criteria ) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		
		$criteria .= strpos($criteria,'%') ? '' : '%';
		
		$sql = "SELECT `ID`,`event_type`,`name`,`format`,`parent_ID`,`signup_by`,`start_date`, `end_date` 
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
			$sql = "SELECT ce.ID, ce.event_type, ce.name, ce.format, ce.parent_ID
			 			  ,ce.signup_by,ce.start_date,ce.end_date  
					FROM $table ce
					WHERE ce.parent_ID = %d;";
		}
		elseif( array_key_exists( 'club', $fk_criteria ) ) {
			//All events belonging to specified club
			$col_value = $fk_criteria["club"];
			error_log( "Event::find using club_ID=$col_value" );
			$sql = "SELECT e.ID, e.event_type, e.name, e.format, e.parent_ID 
						  ,e.signup_by,e.start_date,e.end_date 
					from $table e 
					INNER JOIN $joinTable AS j ON j.event_ID = e.ID 
					INNER JOIN $clubTable AS c ON c.ID = j.club_ID 
					WHERE c.ID = %d;";
		} elseif( !isset( $fk_criteria ) ) {
			//All events
			error_log( "Event::find all events" );
			$col_value = 0;
			$sql = "SELECT `ID`,`event_type`,`name`,`format`,`parent_ID`,`signup_by`,`start_date`,`end_date` 
					FROM $table;";
		}
		else {
			return $col;
		}

		$safe = $wpdb->prepare( $sql, $col_value );
		$rows = $wpdb->get_results( $safe, ARRAY_A );

		//error_log( "Event::find $wpdb->num_rows rows returned." );

		foreach( $rows as $row ) {
            $obj = new Event;
            self::mapData($obj,$row);
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
		$sql = "SELECT ID,event_type,`name`,format,parent_ID,`signup_by`,`start_date`,`end_date` 
		        FROM $table WHERE ID=%d";
		$safe = $wpdb->prepare($sql,$pks);
		$rows = $wpdb->get_results($safe, ARRAY_A);

		$id = $pks[0];
		//error_log( "Event::get($id) $wpdb->num_rows rows returned." );

		$obj = NULL;
		if( count( $rows ) === 1 ) {
			$obj = new Event;
			self::mapData( $obj, $rows[0] );
		}
		return $obj;
	}
	
	static public function deleteEvent( int $eventId ) {
		$result = 0;
		if(isset($eventId)) {
			global $wpdb;
			$table = $wpdb->prefix . self::$tablename;
			$wpdb->delete( $table,array( 'ID'=>$eventId ), array( '%d' ) );
			$result = $wpdb->rows_affected;
		}
		error_log( "Event.deleteEvent: deleted $result" );
		return $result;
	}

	/*************** Instance Methods ****************/
	public function __construct( string $name=null ) {
		$this->isnew = TRUE;
		$this->name = $name;
		$this->init();
	}
	
    public function __destruct() {
		$this->parent = null;
			if(isset($this->childEvents)) {
			foreach($this->childEvents as &$event){
				$event = null;
			}
		}
	
		if(isset( $this->clubs ) ) {
			foreach($this->clubs as &$club) {
				$club = null;
			}
		}

		if( isset( $this->draw ) ) {
			foreach($this->draw as &$draw) {
				$draw = null;
			}
		}

		if( isset( $this->matches ) ) {
			foreach($this->matches as &$match) {
				$match = null;
			}
		}

		if( isset( $this->matchesToBeDeleted ) ) {
			foreach($this->matchesToBeDeleted as &$match) {
				$match = null;
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

	public function isLeaf() {
		$p = $this->getParent();
		return ( isset( $p ) && count( $this->getChildEvents() ) === 0 );
	}

	public function getRoot() {
		if($this->isRoot()) return $this;
		return $this->parent->getRoot();
	}

    public function setDirty() {
        if(!$this->isRoot()) {
			$this->getParent()->setDirty();
		}
        $id=$this->getID();
        //error_log(__CLASS__. " $id set Dirty ");
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
     * Get the name of this object
     */
    public function getName():string {
        return $this->name;
    }

	/**
	 * Set the type of event
	 * Applies only to a root event
	 */
	public function setEventType( string $type = null ) {
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
		return $this->setDirty();
	}

	public function getEventType() {
		return $this->event_type;
	}
	
	/**
	 * Set the format
	 * Applies only to the lowest child event
	 */
	public function setFormat( string $format = null ) {
		$result = false;
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
		return $result;
	}

	public function getFormat() {
		return $this->format;
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
		$test = DateTime::createFromFormat( '!Y/m/d', $signup );
		if(false === $test) $test = DateTime::createFromFormat( '!Y/n/j', $signup );
		if(false === $test) $test = DateTime::createFromFormat( '!Y-m-d', $signup );
		if(false === $test) $test = DateTime::createFromFormat( '!Y-n-j', $signup );
		$last = DateTIme::getLastErrors();
		if( $last['error_count'] > 0 ) {
			$arr = $last['errors'];
			$mess = '';
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
		$test = DateTime::createFromFormat( '!Y/m/d', $start );
		if(false === $test) $test = DateTime::createFromFormat( '!Y/n/j', $start );
		if(false === $test) $test = DateTime::createFromFormat( '!Y-m-d', $start );
		if(false === $test) $test = DateTime::createFromFormat( '!Y-n-j', $start );
		$last = DateTIme::getLastErrors();
		if( $last['error_count'] > 0 ) {
			$arr = $last['errors'];
			$mess = '';
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
		$test = DateTime::createFromFormat('!Y/m/d',$end);
		if(false === $test) $test = DateTime::createFromFormat( '!Y/n/j', $end );
		if(false === $test) $test = DateTime::createFromFormat( '!Y-m-d', $end );
		if(false === $test) $test = DateTime::createFromFormat( '!Y-n-j', $end );
		$last = DateTIme::getLastErrors();
		if( $last['error_count'] > 0 ) {
			$arr = $last['errors'];
			$mess = '';
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
				//unset($this->childEvents[$i]);
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
	public function addToDraw ( string $name, int $seed = null ) {
		$result = false;
		if( isset( $name ) && $this->isLeaf() ) {
			$found = false;
			foreach( $this->getDraw() as $d ) {
				if( $name === $d->getName() ) {
					$found = true;
				}
			}
			if( !$found ) {
				$ent = new Entrant( $this->getID(), $name, $seed );
				$this->draw[] = $ent;
				$result = $this->setDirty();
			}
		}
		return $result;
	}

	/**
	 * Get a contestant in the Draw by name
	 */
	public function getNamedEntrant( string $name ) {
		$result = null;

		foreach( $this->getDraw() as $draw ) {
			if( $name === $draw->getName() ) {
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
	public function removeFromDraw( string $name ) {
		$result = false;
		$temp = array();
		for( $i = 0; $i < count( $this->getDraw() ); $i++) {
			if( $name === $this->draw[$i]->getName() ) {
				$this->entrantsToBeDeleted[] = $this->draw[$i]->getPosition();
				$result = $this->setDirty();
			}
			else {
				$temp[] = $this->draw[$i];
			}
		}
		$this->draw = $temp;

		return $result;
	}

	/**
	 * Destroy the existing draw.
	 */
	public function removeDraw() {
		foreach( $this->getDraw() as &$dr ) {
			$this->entrantsToBeDeleted[] = $dr->getPosition();
			unset( $dr );
		}
		$this->draw = array();
		$this->removeAllMatches();
		return $this->setDirty();
	}

	/**
	 * Get the draw for this Event
	 * @param $force When set to true will force loading of entrants from db
	 *               This will cause unsaved entrants to be lost.
	 */
	public function getDraw( $force=false ) {
		if(!isset( $this->draw ) || $force) $this->fetchDraw();
		return $this->draw;
	}

	public function drawSize() {
		return isset( $this->draw ) ? sizeof( $this->draw) : 0;
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

	private function sortByMatchNumberAsc( $a, $b ) {
		if($a->getMatchNumber() === $b->getMatchNumber()) return 0; return ($a->getMatchNumber() < $b->getMatchNumber()) ? -1 : 1;
	}

    /**
     * Create a new Match and add it to this Event.
	 * The Match must pass validity checks
	 * @param $round The round number for this match
     * @param $home
	 * @param $matchType The type of match @see MatchType class
     * @param $visitor
	 * @param $matchnum The match number if known
     * @return Match if successful; null otherwise
     */
    public function addNewMatch( int $round, Entrant $home, float $matchType, Entrant $visitor = null, $matchnum = 0 ) {
		$result = null;
		
        if( isset( $home ) ) {
			$this->getMatches();
			$match = new Match( $this->getID(), $round, $matchnum );
			$match->setEvent( $this );
			$match->setHomeEntrant( $home );
			if( isset( $visitor ) ) {
				$match->setVisitorEntrant( $visitor );
			} 
			else {
				$match->setIsBye( true );
			}
			$match->setMatchType( $matchType );
			if( $match->isValid() ) {
				$this->matches[] = $match;
				$this->setDirty();
				$result = &$match;
			}
        }

        return $result;
    }

    /**
     * Add a Match to this Round
	 * The Match must pass validity checks
     * @param $match
     */
    public function addMatch( Match &$match ) {
        $result = false;

        if( isset( $match ) && $match->isValid() ) {
			$this->getMatches();
			$match->setEvent( $this );
            $this->matches[] = $match;
			$result = $this->setDirty();
        }
        
        return $result;
	}

    /**
     * Access all Matches in this Event
	 * @param $force When set to true will force loading of matches
	 *               This will cause unsaved matches to be lost.
     */
    public function getMatches( $force = false ):array {
        if( !isset( $this->matches ) || $force ) $this->fetchMatches();
        usort( $this->matches, array( 'Event', 'sortByMatchNumberAsc' ) );
        return $this->matches;
	}
	
    /**
     * Access all Matches in this Event for a specific round
	 * @param $rndnum The round number of interest
     */
	public function getMatchesByRound( int $rndnum ) {
		$result = array();
		foreach( $this->getMatches() as $match ) {
			if( $match->getRoundNumber() === $rndnum ) {
				$result[] = $match;
			}
		}
        usort( $result, array( 'Event', 'sortByMatchNumberAsc' ) );
		return $result;
	}

    /**
     * Get the number of matches in this Round
     */
    public function numMatches():int {
        return count( $this->getMatches() );
	}
	
    /**
     * Get the number of matches in this Round
     */
    public function numMatchesByRound( int $round ):int {		
		return array_reduce( function ($sum,$m) use( $round ) { if( $m->getRound() === $round ) ++$sum; }, $this->getMatches(), 0);
	}
	
	/**
	 * Remove the collection of Matches
	 */
	public function removeAllMatches() {
		if( isset( $this->matches ) ) {
			$i=0;
			foreach( $this->matches as $match ) {
				$this->matchesToBeDeleted[] = $match;
				unset( $this->matches[$i] );
				$i++;
			}
		}
		return $this->setDirty();
	}

	public function getMatchType() {
		if( $this->numMatches() > 0 ) {
			return $this->matches[0]->getMatchType();
		}
		else {
			return 0.0;
		}
	}
	
	/**
	 * Check to see if this Event has valid data
	 */
	public function isValid() {
		$mess = '';

		if( !isset( $this->name ) ) {
			$mess = __('Event must have a name.');
		}
		elseif( !isset( $this->event_type ) && $this->isRoot() ) {
			$mess = __('Root Events must have a type.');
		}
		elseif( !isset( $this->format ) && $this->isLeaf() ) {
			$mess = __('Leaf events must have a format.');
		}
		elseif( $this->isRoot() && count( $this->getClubs() ) < 1 ) {
			$mess = __('Root event must be associated with at least one club');
		}

		if(strlen( $mess ) > 0) throw new InvalidEventException( $mess );

		return true;
	}

	/**
	 * Delete this event
	 * All child objects will be deleted via DB Cascade
	 */
	public function delete() {
		$result = 0;
		$id = $this->getID();
		if( isset( $id ) ) {
			global $wpdb;
			$table = $wpdb->prefix . self::$tablename;

			$where = array( 'ID'=>$id );
			$formats_where = array( '%d' );
			$wpdb->delete( $table, $where, $formats_where );
			$result = $wpdb->rows_affected;
			error_log( "Event.delete: deleted $result rows" );
		}
		return $result;
	}

	/**
	 * Fetch all children of this event.
	 */
	private function fetchChildEvents() {
		$this->childEvents =  Event::find( array( 'parent_ID' => $this->getID() ) );
	}

	/**
	 * Fetch all Entrants for this event.
	 * Otherwise known as the draw.
	 */
	private function fetchDraw() {
		if( $this->isParent() ) $this->draw = array();
		$this->draw = Entrant::find( $this->getID() );
	}

	/**
	 * Fetch all related clubs for this Event
	 * Root-level Events can be associated with one or more clubs
	 */
	private function fetchClubs() {
		$this->clubs = Club::find( $this->getID() );
	}

    /**
     * Fetch Matches all Matches from the database
     */
    private function fetchMatches() {
		$this->matches =  Match::find( $this->getID() );
    }

	protected function create() {
        global $wpdb;
        
        parent::create();

		$this->parent_ID = isset( $this->parent ) ? $this->parent->getID() : null;

        $values = array( 'name'       => $this->getName()
						,'parent_ID'  => $this->parent_ID
						,'event_type' => $this->getEventType()
						,'format'     => $this->getFormat()
						,'signup_by'  => $this->getSignupBy_Str()
						,'start_date' => $this->getStartDate_Str()
						,'end_date'   => $this->getEndDate_Str()
					    );
		$formats_values = array( '%s','%d','%s','%s','%s','%s','%s' );
		$wpdb->insert( $wpdb->prefix . self::$tablename, $values, $formats_values );
		$this->ID = $wpdb->insert_id;
		$result = $wpdb->rows_affected;
		$this->isnew = FALSE;
		$this->isdirty = FALSE;

		$result += $this->manageRelatedData();
		
		error_log( "Event::create: $result rows affected." );

		return $result;
	}

	protected function update() {
		global $wpdb;

        parent::update();

		$this->parent_ID = isset( $this->parent ) ? $this->parent->getID() : null;

        $values = array( 'name'       => $this->getName()
						,'parent_ID'  => $this->parent_ID
						,'event_type' => $this->getEventType()
						,'format'     => $this->getFormat()
						,'signup_by'  => $this->getSignupBy_Str()
						,'start_date' => $this->getStartDate_Str()
						,'end_date'   => $this->getEndDate_Str()
					    );
		$formats_values = array( '%s', '%d', '%s', '%s', '%s', '%s', '%s' );
		$where          = array( 'ID' => $this->ID );
		$formats_where  = array( '%d ');
		$check = $wpdb->update( $wpdb->prefix . self::$tablename,$values,$where,$formats_values,$formats_where );
		$this->isdirty = false;
		$result = $wpdb->rows_affected;

		$result += $this->manageRelatedData();
		
		error_log( "Event::update: $result rows affected." );
		
		return $result;
	}

    /**
     * Map incoming data to an instance of Event
     */
    protected static function mapData($obj,$row) {
		parent::mapData($obj,$row);
        $obj->name       = $row["name"];
        $obj->event_type = $row["event_type"];
        $obj->parent_ID  = $row["parent_ID"];
		$obj->format     = $row["format"];
		$obj->signup_by  = isset( $row['signup_by'] )  ? new DateTime( $row['signup_by'] ) : null;
		$obj->start_date = isset( $row['start_date'] ) ? new DateTime( $row['start_date'] ) : null;
		$obj->end_date   = isset( $row["end_date"] )   ? new DateTime( $row["end_date"] ) : null;
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
					$result += Event::deleteEvent($id);
				}
			}
			$this->childEventsToBeDeleted = array();
		}

		if( isset( $this->draw ) ) {
			foreach($this->draw as $draw) {
				$draw->setEventID($this->getID());
				$result += $draw->save();
			}
		}

		//Delete Entrants that were removed from the draw for this Event
		if(count($this->entrantsToBeDeleted) > 0 ) {
			$entrantIds = array_map(function($e){return $e->getID();},$this->getDraw());
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

		//Create relation between this Matches(round_num,match_num,position) and this Event
		if( isset( $this->matches ) ) {
			foreach( $this->matches as $match ) {
				$result += $match->save();
			}
		}

		//Delete ALL Matches removed from this Event
		foreach( $this->matchesToBeDeleted as &$match ) {
			$result += $match->delete();
			unset( $match );			
		}
		$this->matchesToBeDeleted = array();


		return $result;
	}

} //end class