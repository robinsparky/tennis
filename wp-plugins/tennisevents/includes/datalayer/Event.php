<?php
namespace datalayer;
use commonlib\GW_Debug;
use \TennisEvents;
use \DateTime;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 
 * Data and functions for Event(s)
 * A tennis Event is organization of tennis matches.
 * For example, a tennis tournament which may have sub-events
 * for Men's Singles, Women's Singles, Mixed Doubles, etc.
 * which are most often organized as single elimination format.
 * Event's can also be regular tennis matches organized as Ladders
 * or as Leagues which are of Round Robin format.
 * Events are organized into a 2 level hierarchy.
 * @class  Event
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
*/
class Event extends AbstractData
{ 
	public static $tablename = 'tennis_event';

	private static $datetimeformat = "Y-m-d H:i:s";
	private static $dateformat = "Y-m-d";
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
	private $num_brackets; //number of brackets for this child event
    
	private $clubs; //array of related clubs for this root event
	private $childEvents; //array of child events
	private $brackets; //array of 1 or 2 brackets for this leaf event
	private $external_refs; //array of external reference to something (e.g. custom post type in WordPress)
    
    /**
     * Search for Events have a name 'like' the provided criteria
     */
    public static function search( $criteria ) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		
		$criteria .= strpos($criteria,'%') ? '' : '%';
		
		$sql = "SELECT `ID`,`event_type`,`name`
		       ,`format`, `match_type`,`score_type`,`gender_type`
			   ,`age_min`,`age_max`
			   ,`parent_ID`,`num_brackets`
			   ,`signup_by`,`start_date`, `end_date` 
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
     * Get all parent Events
     */
    public static function getAllParentEvents( ) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
				
		$sql = "SELECT `ID`,`event_type`,`name`
		       ,`format`, `match_type`,`score_type`,`gender_type`
			   ,`age_min`,`age_max`
			   ,`parent_ID`,`num_brackets`
			   ,`signup_by`,`start_date`, `end_date` 
		        FROM $table WHERE `parent_ID` is null";
		$rows = $wpdb->get_results($sql, ARRAY_A);
		
		//error_log("Event::allParentEvents $wpdb->num_rows rows returned using criteria: $criteria");

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
		// $strTrace = GW_Debug::get_debug_trace_Str(3);	
		// error_log("{$loc}: {$strTrace}");

		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$joinTable = "{$wpdb->prefix}tennis_club_event";
		$clubTable = "{$wpdb->prefix}tennis_club";
		$col = array();
		$col_value = '';
		
		if(isset( $fk_criteria[0] ) && is_array( $fk_criteria[0]) ) $fk_criteria = $fk_criteria[0];

		if( array_key_exists( 'parent_ID', $fk_criteria ) ) {
			//All events who are children of specified Event
			$col_value = $fk_criteria["parent_ID"];
			error_log("Event::find using parent_ID=$col_value");
			$sql = "SELECT ce.ID, ce.event_type, ce.name, ce.format, ce.match_type, ce.score_type, ce.gender_type, ce.age_max, ce.age_min, ce.parent_ID, ce.num_brackets 
			 			  ,ce.signup_by,ce.start_date,ce.end_date  
					FROM $table ce
					WHERE ce.parent_ID = %d;";
		}
		elseif( array_key_exists( 'club', $fk_criteria ) ) {
			//All events belonging to specified club
			$col_value = $fk_criteria["club"];
			error_log( "Event::find using club_ID=$col_value" );
			$sql = "SELECT e.ID, e.event_type, e.name, e.format, e.match_type, e.score_type, e.gender_type, e.age_max, e.age_min, e.parent_ID, e.num_brackets 
						  ,e.signup_by,e.start_date,e.end_date 
					from $table e 
					INNER JOIN $joinTable AS j ON j.event_ID = e.ID 
					INNER JOIN $clubTable AS c ON c.ID = j.club_ID 
					WHERE c.ID = %d;";
		} elseif( !isset( $fk_criteria ) ) {
			//All events
			error_log( "Event::find all events" );
			$col_value = 0;
			$sql = "SELECT `ID`,`event_type`,`name`,`format`, `match_type`, `score_type`,`gender_type`, `age_max`, `age_min`,`parent_ID`,`num_brackets`,`signup_by`,`start_date`,`end_date` 
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
	 * Fetches one or more Event external refs from the db with the given an event id
	 * @param int $id 
	 * @return string external reference or array of external refs or '' if not found
	 */
	static public function getExtEventRefByEventId( int $id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tennis_external_event';		
		$sql = "SELECT `external_ID`
				FROM $table WHERE `event_ID`='%d'";
		$safe = $wpdb->prepare( $sql, $id );
		$rows = $wpdb->get_results( $safe, ARRAY_A );
		error_log( sprintf("Event::getExtEventRefByEventId(%d) -> %d rows returned.", $id, $wpdb->num_rows ) );
		
		$result = '';
		if( count( $rows ) > 1) {
			$result = array();
			foreach( $rows as $row ) {
				$result[] = $row['external_ID'];
			}
		}
		elseif( count( $rows ) === 1 ) {
			$result = $rows[0]['external_ID'];
		}
		return $result;
	}

	/**
	 * Get instance of a Event using it's primary key: ID
	 */
    static public function get( int ...$pks ) {
		$loc = __CLASS__ . '::' . __FUNCTION__;		
        error_log("{$loc}: pks ... ");
        error_log(print_r($pks,true));	

		// $strTrace = GW_Debug::get_debug_trace_Str(3);
		// error_log("{$loc}: Trace {$strTrace}");
		
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$sql = "SELECT `ID`,`event_type`,`name`,`format`,`match_type`,`score_type`, `gender_type`, `age_max`, `age_min`,`parent_ID`,`num_brackets`,`signup_by`,`start_date`,`end_date` 
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

	/**
	 * Remove all events that fall outside of the history retention period.
	 * NOT USED YET
	 * @return int the number of events removed
	 */
	static public function removeOldEvents() {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		
		$history_retention = esc_attr( get_option( TennisEvents::OPTION_HISTORY_RETENTION,  TennisEvents::OPTION_HISTORY_RETENTION_DEFAULT ));
		$currentYear = date('Y');
		$cutoff = $currentYear - $history_retention + 1;
		error_log( sprintf("%s -> cutoff year is %d ", $loc, $cutoff ) );
		$numDeleted = 0;
		$rowsDeleted = 0;
		
		$parentEvents = Event::getAllParentEvents();
		error_log( sprintf("%s -> %d Parent events ", $loc, count($parentEvents) ) );

		foreach( $parentEvents as $evt ) {
			error_log( sprintf("%s -> evt->ID=%d season=%d ", $loc, $evt->getID(),  $evt->getSeason()) );
			if( $evt->getSeason() < $cutoff ) {
				++$numDeleted;
				$numDeleted  += count( $evt->getChildEvents() );
				$rowsDeleted += Event::deleteEvent( $evt->getID() );
			}
		}

		error_log( sprintf("%s -> %d event(s) deleted; %d row(s) deleted", $loc, $numDeleted, $rowsDeleted ) );
		return $numDeleted;
	}
	
	/**
	 * Delete an Event and all it's sub-events from the database
	 * @param int $eventId the primary key for the event
	 */
	static public function deleteEvent( int $eventId ) {
		$loc = __CLASS__ . '::' . __FUNCTION__;

		$result = 0;
		global $wpdb;

		//Delete all external references
		$result += ExternalRefRelations::remove( 'event' , $eventId );

		//Delete all relationships to clubs'
		$result += ClubEventRelations::removeAllForEvent( $eventId );

		//Delete all brackets for the event		
		$bracketTable = $wpdb->prefix . Bracket::$tablename;		
		$sql = "SELECT `bracket_num`
		FROM $bracketTable WHERE `event_ID`='%d'";
		$safe = $wpdb->prepare( $sql, $eventId );
		$rows = $wpdb->get_results( $safe, ARRAY_N );
		foreach ($rows as $bracket_num ) {
			$result += Bracket::deleteBracket( $eventId, $bracket_num[0] );
		}

		$table = $wpdb->prefix . self::$tablename;

		//Delete sub-events
		$wpdb->delete($table, array('parent_ID'=>$eventId), array( '%d' ) );
		$result = $wpdb->rows_affected;

		//Delete the event
		$wpdb->delete( $table, array( 'ID'=>$eventId ), array( '%d' ) );
		$result += $wpdb->rows_affected;

		error_log( sprintf("%s(%d) -> deleted %d row(s)", $loc, $eventId, $result ) );
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
	/**
	 * Constructor ... including sneaky copy constructor
	 */
	public function __construct( string $name = null, string $eventType = EventType::TOURNAMENT, Event $copyMe = null ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;

		parent::__construct( true );
		
		$this->isnew = true;

		if( empty( $copyMe ) ) {
			$this->name = $name ?? 'unknown name';
			if( EventType::isValid( $eventType ) ) {
				$this->event_type = $eventType;
			}
		}
		elseif( $copyMe->IsParent() ) {
			throw new \InvalidArgumentException( __("Cannot copy parent events", TennisEvents::TEXT_DOMAIN) );
		}
		else {
			$this->name = $copyMe->getName() . " Copy";
			$this->setEventType( $copyMe->getEventType() );
			$cparent = $copyMe->getParent();
			$this->setParent( $cparent );
			$this->setEventType( $copyMe->getEventType() );
			$this->setFormat( $copyMe->getFormat() );
			$this->setMatchType( $copyMe->getMatchType() );
			$this->setScoreType( $copyMe->getScoreType() );
			$this->setSignupBy( $copyMe->getSignupBy_Str() );
			$this->setStartDate( $copyMe->getStartDate_Str() );
			$this->setEndDate( $copyMe->getEndDate_Str() );
			$this->setGenderType( $copyMe->getGenderType() );
			$this->setMaxAge( $copyMe->getMaxAge() );
			$this->setMinAge( $copyMe->getMinAge() );
			$bracketCol = $copyMe->getBrackets();
			$numBrackets = count($bracketCol);
			//$this->log->error_log("$loc: {$copyMe->getName()} has '{$numBrackets}' brackets");
			$bracket = null;
			foreach( $copyMe->getBrackets() as $brackToCopy ) {
				//$this->log->error_log("$loc: adding bracket '{$brackToCopy->getName()}'");
				$bracket = $this->createBracket($brackToCopy->getName());
				$this->copySignup( $brackToCopy, $bracket );
			}
			//$this->setNumberOfBrackets( $copyMe->getNumberOfBrackets() );
		}
	}

	/**
	 * Copy the signup from one Bracket to another Bracket
	 * @param Bracket $from the bracket to copy signup from
	 * @param Bracket $to the bracket to receive the signup
	 */
	private function copySignup( Bracket $from, Bracket &$to ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;
		$this->log->error_log("{$loc}");
		if(empty($to)) return;

		foreach( $from->getSignup() as $entrant ) {
			$name = $entrant->getName();
			$seed = $entrant->getSeed();
			$this->log->error_log("{$loc}: Adding {$name}");
			$to->addToSignup( $name, $seed );
		}
	}
	
    public function __destruct() {
		static $numEvents = 0;
		$loc = __CLASS__ . '::' . __FUNCTION__;
		++$numEvents;
		$this->log->error_log("{$loc} ... event #{$numEvents}");
		
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

		// if( isset( $this->signup ) ) {
		// 	foreach($this->signup as &$draw) {
		// 		unset( $draw );
		// 	}
		// }

		if( isset( $this->brackets ) ) {
			foreach($this->brackets as &$bracket) {
				unset( $bracket );
			}
		}

		if( isset( $this->external_refs ) ) {
			foreach( $this->external_refs as &$er ) {
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
		return $this->setDirty();
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
		return $this->getScoreRule();
	}

	/**
	 * Get the type of scoring for this event
	 */
	public function getScoreRule( ) : string {
		return $this->score_type ?? '';
	}

	public function getScoreRuleDescription() {
		$result = '';
		$st = ScoreType::get_instance();
		if( array_key_exists($this->getScoreRule(), $st->getRuleDescriptions())) {
			$result = $st->getRuleDescriptions()[$this->getScoreRule()]; 
		}
		return $result;
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

		$test = \DateTime::createFromFormat( '!Y/m/d', $signup );
		if(false === $test) $test = \DateTime::createFromFormat( 'Y/n/j', $signup );
		if(false === $test) $test = \DateTime::createFromFormat( 'Y-m-d', $signup );
		if(false === $test) $test = \DateTime::createFromFormat( 'Y-n-j', $signup );
		if(false === $test) $test = \DateTime::createFromFormat( 'Y-n-j H:i:s', $signup );
		if(false === $test) $test = \DateTime::createFromFormat( 'Y-m-j H:i:s', $signup );
		$last = DateTIme::getLastErrors();
		if( $last['error_count'] > 0 && false === $test ) {
			$arr = $last['errors'];
			$mess = 'SignupBy: ';
			foreach($arr as $err) {
				$mess .= $err.':';
			}
			throw new InvalidEventException( $mess );
		}
		elseif( $test instanceof \DateTime ) {
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
		else return $this->signup_by->format( \DateTime::ISO8601 );
	}

	/**
	 * Get the signup by date of this event as a DateTime object
	 * @return DateTime
	 */
	public function getSignupBy() {
		return $this->signup_by;
	}
	
	public function setStartDate( string $start ) {
		$result = false;
		if( empty( $start ) ) return $result;

		$test = \DateTime::createFromFormat( '!Y/m/d', $start );
		if(false === $test) $test = \DateTime::createFromFormat( 'Y/n/j', $start );
		if(false === $test) $test = \DateTime::createFromFormat( 'Y-m-d', $start );
		if(false === $test) $test = \DateTime::createFromFormat( 'Y-n-j', $start );
		if(false === $test) $test = \DateTime::createFromFormat( 'Y-n-j H:i:s', $start );
		if(false === $test) $test = \DateTime::createFromFormat( 'Y-m-j H:i:s', $start );
		$last = DateTIme::getLastErrors();
		if( $last['error_count'] > 0 ) {
			$arr = $last['errors'];
			$mess = 'Start Date: ';
			foreach( $arr as $err ) {
				$mess .= $err.':';
			}
			throw new InvalidEventException( $mess );
		}
		elseif( $test instanceof \DateTime ) {
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
	 * @return DateTime
	 */
	public function getStartDate() {
		return $this->start_date;
	}
	
	/**
	 * Get the start date for this event in ISO 8601 format
	 */
	public function getStartDate_ISO() {
		if( !isset( $this->start_date ) ) return null;
		else return $this->start_date->format( \DateTime::ISO8601 );
	}

	/**
	 * Get the season (i.e. year) in which this event was held
	 * @return int Year of start date. Defaults to current year.
	 */
	public function getSeason() : int {
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

		$test = \DateTime::createFromFormat('!Y/m/d',$end);
		if(false === $test) $test = \DateTime::createFromFormat( 'Y/n/j', $end );
		if(false === $test) $test = \DateTime::createFromFormat( 'Y-m-d', $end );
		if(false === $test) $test = \DateTime::createFromFormat( 'Y-n-j', $end );
		if(false === $test) $test = \DateTime::createFromFormat( 'Y-n-j H:i:s', $end );
		if(false === $test) $test = \DateTime::createFromFormat( 'Y-m-j H:i:s', $end );
		$last = DateTIme::getLastErrors();
		if( $last['error_count'] > 0 ) {
			$arr = $last['errors'];
			$mess = 'End Date: ';
			foreach( $arr as $err ) {
				$mess .= $err . ':';
			}
			throw new InvalidEventException( $mess );
		}
		elseif( $test instanceof \DateTime ) {
			$this->end_date = $test;
			$result = $this->setDirty();
		}

        return $result;
	}

	/**
	 * If an end data has been set and the current date 
	 * is after the end date then the event is considered closed.
	 * @return boolean
	 */
	public function isClosed() : bool {
		$result = false;
		if( !is_null( $this->end_date ) ) {
			if( $this->end_date < new \DateTime() ) $result = true;
		}

		return $result;
	}

	/**
	 * Set the number of brackets that this leaf event should have
	 * @param int $numBrackets
	 * @return bool true if modified; false otherwise
	 */
	public function setNumberOfBrackets( int $numBrackets = 0 ) {
		$loc = __CLASS__ . "::" . __FUNCTION__;
		$result = false;
		if( $this->isParent() ) return $result;

		if( $numBrackets >= 0 ) {
			$this->num_brackets = $numBrackets;
			$result = $this->setDirty();

			$this->generateBrackets( $this->num_brackets );
	
		}
		return $result;
	}

	/**
	 * Get the number of brackets that this child event should have
	 * @return int The number of brackets.
	 */
	public function getNumberOfBrackets() :int {
		return $this->num_brackets ?? 0;
	}

	/**
	 * Generate collection of brackets for this leaf event
	 * @param int $numBrackets is the number of brackets to generate
	 * @return int the number of brackets created
	 */
	private function generateBrackets( int $numBrackets = 0 ) {
		$loc = __CLASS__ . "::" . __FUNCTION__;

		$result = 0;

		if( $this->isParent() || ($numBrackets <= 0) ) return $result;
		
		if( count($this->getBrackets()) > 0 ) {
			$this->log->error_log("$loc: Cannot generate brackets when they already exist.");
			return $result;
		}

		$prefix = "Bracket";
		$parentEvt = $this->getParent();
		if(!is_null($parentEvt)) {

			if( EventType::LADDER === $parentEvt->getEventType() ) $prefix = "Box";

			foreach(range(1,$numBrackets) as $bracket_num ) {
				$br_name = "{$prefix}{$bracket_num}";
				$bracket = $this->createBracket($br_name);
				++$result;
				$this->setDirty();
			}
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
		else return $this->end_date->format( \DateTime::ISO8601 );
	}

	/**
	 * Get the end date for this event as a DateTime object
	 * @return DateTime 
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
				$result = $this->setDirty();
				self::deleteEvent( $ch->getID() );
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
					ClubEventRelations::remove($this->getID(), $club->getID() );
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
	 * Get a bracket by its name or its number
	 * Ignores case
	 * @param $bracketId The name or number of the bracket
	 * @return object Bracket object or null if not found
	 */
	public function getBracket( $bracketId = Bracket::WINNERS ) {
		$loc = __CLASS__ . "::" . __FUNCTION__;
		$this->log->error_log("$loc({$bracketId})");
		$result = null;

		if( is_numeric( $bracketId ) ) {
			foreach( $this->getBrackets() as $bracket ) {
				if( $bracketId == $bracket->getBracketNumber() ) {
					$result = $bracket;
					break;
				}	
			}	
		}
		elseif( is_string( $bracketId ) ) {
			foreach( $this->getBrackets() as $bracket ) {
				if( strcasecmp( $bracketId, $bracket->getName() ) === 0 ) {
					$result = $bracket;
					break;
				}
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
	 * This method can only be used when the Event already has an ID
	 * @param object $bracket The bracket to be added
	 * @return bool  True if added false otherwise
     */
    public function addBracket( Bracket &$bracket ) {
		$loc = __CLASS__ . "::" . __FUNCTION__;
		$this->log->error_log($loc);

		$result = false;
		$found = false;
		if( $this->isLeaf() ) {
			//if( $this->isLeaf() && $bracket->getEvent()->getID() === $this->getID() && $bracket->isValid() ) {
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
			//}
		}
        return $result;
	}

	/**
	 * Create a bracket with the given name.
	 * If a bracket with that name already exists it is returned.
	 * This method must be used when add brackets to a new Event which has no ID
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
					unset( $this->brackets[$num] );
					Bracket::deleteBracket( $this->getID(), $bracket->getBracketNumber() );
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
				Bracket::deleteBracket( $this->getID(), $bracket->getBracketNumber() );
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
					unset( $this->external_refs[$i] );
					$result = $this->setDirty();
					EventExternalRefRelations::remove($this->getID(), $er );
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
			$acceptable = ScoreType::get_instance()->validFormatScoringRules($this->format);
			if( count($acceptable) > 0 && !in_array( $this->score_type, array_keys($acceptable) ) ) {
				$mess = __("Score Type '{$this->score_type }' is invalid for the assigned Format '{$this->format}'");
			}
		}

		if(strlen( $mess ) > 0) throw new InvalidEventException( $mess );

		return true;
	}

	/**
	 * Delete this event
	 * All child objects will be deleted
	 */
	public function delete() {
		$loc = __CLASS__ . '::' . __FUNCTION__;

		$result = self::deleteEvent( $this->getID() );
		
        $this->log->error_log("{$loc}: {$this->toString()} Deleted {$result} rows from db.");

		return $result;
	}

	public function toString() {
		return sprintf("E(%d)", $this->getID() );
	}

	public function save():int {
		$loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("{$loc}({$this->toString()})");
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
	
    /**
     * Make sure the dates have the correct order and spacing
	 * NOT USED yet
     */
    private function validateEventDates() {
        $loc = __CLASS__. "::" .__FUNCTION__;
        $this->log->error_log("$loc");
		if(!isset($this->start_date)) return;
		if(!($this->start_date instanceof \DateTime)) return;

        if($this->signup_by instanceof \DateTime ) {
			if( $this->signup_by >= $this->start_date ) {
				$this->signup_by = new \DateTime($this->start_date->format("Y-m-d"));
				$leadTime = TennisEvents::getLeadTime();
				$this->signup_by->modify("-{$leadTime} days");
			}
		}

		if($this->end_date instanceof \DateTime) {
			if( $this->start_date >= $this->end_date ) {
				$this->end_date  = new \DateTime($this->start_date->format("Y-m-d"));
				$this->end_date ->modify("+2 days");
			}
		}
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
        $this->log->error_log("{$loc}({$this->toString()})");

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
						,'num_brackets' => $this->getNumberOfBrackets()
					    );
		$formats_values = array( '%s','%d','%s','%s','%s','%s','%s','%d','%d','%s','%s','%s','%d' );

		//$this->log->error_log($values,"$loc: Values:");
		$res = $wpdb->insert( $wpdb->prefix . self::$tablename, $values, $formats_values );

		if( $res === false || $res === 0 ) {
			$mess = "$loc: wpdb->insert returned false or inserted 0 rows.";
			$err = empty($wpdb->last_error) ? '' : $wpdb->last_error;
			$mess .= " : Err='$err'";
			throw new InvalidEventException($mess);
		}

		$this->ID = $wpdb->insert_id;

		$result = $wpdb->rows_affected;
		$this->isnew = FALSE;
		$this->isdirty = FALSE;

		$result += $this->manageRelatedData();
		
		error_log( sprintf( "%s(%s) -> %d rows inserted.", $loc, $this->toString(), $result ) );

		return $result;
	}

	/**
	 * Update the record in the database
	 */
	protected function update() {
		$loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("{$loc}({$this->toString()})");

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
						,'num_brackets' => $this->getNumberOfBrackets()
					    );
		$formats_values = array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%d' );
		$where          = array( 'ID' => $this->ID );
		$formats_where  = array( '%d ');
		$check = $wpdb->update( $wpdb->prefix . self::$tablename,$values,$where,$formats_values,$formats_where );
		$this->isdirty = false;
		$result = $wpdb->rows_affected;

		$result += $this->manageRelatedData();
		
		error_log( sprintf( "%s(%s) -> %d rows updated.",$loc, $this->toString(), $result ) );
		
		return $result;
	}

    /**
     * Map incoming data to an instance of Event
     */
    protected static function mapData( $obj, $row ) {
		parent::mapData( $obj, $row );
        $obj->name       = str_replace("\'", "'",$row["name"]);
        $obj->parent_ID  = $row["parent_ID"];
		$obj->event_type = $row["event_type"];
		$obj->match_type = $row["match_type"];
		$obj->format     = $row["format"];
		$obj->score_type = $row["score_type"];
		$obj->gender_type = $row["gender_type"];
		$obj->age_max    = $row["age_max"];
		$obj->age_min    = $row["age_min"];
		$obj->num_brackets = $row["num_brackets"];
		$obj->signup_by  = isset( $row['signup_by'] )  ? new \DateTime( $row['signup_by'] ) : null;
		$obj->start_date = isset( $row['start_date'] ) ? new \DateTime( $row['start_date'] ) : null;
		$obj->end_date   = isset( $row["end_date"] )   ? new \DateTime( $row["end_date"] ) : null;
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
		
		//Save brackets
		if( isset( $this->brackets ) ) {
			foreach( $this->brackets as $bracket ) {
				if( $bracket->getEventId() <= 0 || is_null($bracket->getEvent()) ) {
					$bracket->setEvent( $this );
				}
				if( $bracket->isValid() ) {
					$result += $bracket->save();
				}
			}
		}

		//Save the Clubs related to this Event
		if( isset( $this->clubs) ) {
			foreach($this->clubs as $cb) {
				$result += $cb->save();
				//Create relation between this Event and its Clubs
				$result += ClubEventRelations::add( $cb->getID(), $this->getID() );
			}
		}
		
		//Save the External references related to this Event
		if( isset( $this->external_refs ) ) {
			foreach($this->external_refs as $er) {
				//Create relation between this Event and its external references
				$result += ExternalRefRelations::add('event', $this->getID(), $er );
			}
		}

		return $result;
	}

} //end class