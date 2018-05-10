<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// require_once('class-abstractdata.php');
// require_once('class-event.php');
// require_once('class-court.php');

/** 
 * Data and functions for Tennis Brackets
 * @class  Bracket
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
*/
class Bracket extends AbstractData
{ 
    //Known names of brackets for Single and Double Elimination brackets
    public const WINNERS = "Winners";
    public const LOSERS  = "Losers";
    public const CONSOLATION = "Consolation";

	//table name
	private static $tablename = 'tennis_bracket';

	//Attributes
    private $event_ID;
    private $bracket_num = 0;
    private $is_approved = false;
    private $name;

    //Event to which this bracket belongs ( fetched using $event_ID )
    private $event;

    //Matches in this bracket
    private $matches;
    private $matchesToBeDeleted = array();
	
	/*************** Static methods ******************/
	/**
	 * Search not used
	 */
	static public function search( $criteria ) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$col = array();

		return $col;
	}

	/**
	 * Find Brackets referenced in a given Event
	 */
	static public function find( ...$fk_criteria ) {
		global $wpdb;
        $loc = __CLASS__ . '::' .  __FUNCTION__;

		$table = $wpdb->prefix . self::$tablename;
		$col = array();
		$rows;

        //All clubs belonging to specified Event
        $eventId = $fk_criteria[0];            
        $sql = "SELECT event_ID, bracket_num, is_approved, `name` 
                FROM $table 
                WHERE event_ID = %d;";
        $safe = $wpdb->prepare( $sql, $eventId );
        $rows = $wpdb->get_results( $safe, ARRAY_A );

		error_log( sprintf("%s(E(%d)) -> %d rows returned.", $loc, $eventId, $wpdb->num_rows ) );

		foreach( $rows as $row ) {
            $obj = new Bracket;
            self::mapData( $obj, $row );
			$col[] = $obj;
		}
		return $col;

	}

	/**
	 * Get instance of a Bracket using it's primary key (event_ID, bracket_num)
	 */
    static public function get( int ... $pks ) {
        global $wpdb;
        $loc = __CLASS__ . '::' .  __FUNCTION__;

		$table = $wpdb->prefix . self::$tablename;
		$sql = "select event_ID, bracket_num, is_approved, name from $table where event_ID=%d and bracket_num=%d";
		$safe = $wpdb->prepare( $sql, $pks );
		$rows = $wpdb->get_results( $safe, ARRAY_A );

        error_log( sprintf( "%s(B(%d,%d)) -> %d rows returned.", $loc, $pks[0], $pks[1], $wpdb->num_rows ) );
        
		$obj = NULL;
		if( count($rows) === 1 ) {
			$obj = new Bracket;
			self::mapData( $obj, $rows[0] );
		}
		return $obj;
	}

	/*************** Instance Methods ****************/

	public function __destruct() {

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
    
    public function setDirty() {
        if( isset( $this->event ) ) $this->event->setDirty();
        //error_log( sprintf("%s(%s) set dirty", __CLASS__, $this->toString() ) );
        return parent::setDirty();
    }


    public function setBracketNumber( int $bracketnum ) {
        $this->bracket_num = $bracket;
        return $this->setDirty();
    }

    public function getBracketNumber() {
        return $this->bracket_num;
    }

    /**
     * Set the name of this bracket
     */
	public function setName( string $name ) {
		$this->name = $name;
		return $this->setDirty();
	}
	
    /**
     * Get the name of this object
     */
    public function getName() {
        return isset( $this->name ) ? $this->name : sprintf( "Bracket %d", $this->bracket_num );
    }
    
    /**
     * Approve this bracket
     */
    public function approve() {
        $this->is_approved = true;
    }

    /**
     * Is this bracket approved?
     */
    public function is_approved() {
        return $this->is_approved;
    }

    /**
     * Returns ths associated event's ID
     * Defaults to 0
     */
    public function getEventId() {
        return isset( $this->event_ID ) ? $this->event_ID : 0;
    }

    /**
     * Set the event objecyt that owns this bracket
     */
    public function setEvent( Event &$event ) {
        $this->event = $event;
        $this->event_ID = $event->getID();
        $this->setDirty();
    }

    /**
     * Get the event for this bracket
     */
    public function getEvent( $force = false ) {
        if( !isset( $this->event ) || $force ) $this->fetchEvent();
        return $this->event;
    }

    /**
     * Determine the size of the signup
     * For the main draw, this is the actual signup for the event
     * For Losers or Consolation draw, the signup is one-half of the event signup
     * TODO: For other ... ???????????
     */
    public function signupSize() {
        $size =  $this->getEvent()->signupSize();
        if( self::WINNERS === $this->getName() ) {
            return $size;
        }
        elseif( self::LOSERS === $this->getName() ) {
            return $size / 2;
        }
        elseif( self::CONSOLATION === $this->getName() ) {
            return $size / 2;
        }
        return size;
    }
    
    /**
     * Create a new Match and add it to this Event.
	 * The Match must pass validity checks
	 * @param $round The round number for this match
	 * @param $matchType The type of match @see MatchType class
	 * @param $matchnum The match number if known
     * @param $home
     * @param $visitor
     * @param $bye Set to true if the new match is a bye
     * @return Match if successful; null otherwise
     */
    public function addNewMatch( int $round, float $matchType, $matchnum = 0, Entrant $home = null, Entrant $visitor = null, bool $bye = false ) {
		$result = null;

        if( isset( $home ) ) {
            $this->getMatches();
            $match = new Match( $this->getEvent()->getID(), $this->bracket_num, $round, $matchnum );
            $match->setIsBye( $bye );				
            $match->setMatchType( $matchType );
            $match->setBracket( $this );
            if( isset( $home ) ) {
                $match->setHomeEntrant( $home );
            }
            if( isset( $visitor ) ) {
                $match->setVisitorEntrant( $visitor );
            } 
            
            if( $match->isValid() ) {
                $this->matches[] = $match;
                $match->setBracket( $this );
                $this->setDirty();
                $result = $match;
            }
        }
        return $result;
    }

    /**
     * Add a Match to this Round
	 * The Match must pass validity checks
     * @param $match
	 * @return true if successful, false otherwise
     */
    public function addMatch( Match &$match ) {
        $result = false;

        if( isset( $match ) ) {
            $matches = $this->getMatches();
            foreach( $matches as $m ) {
                //Using the compare attributes version of object equivalance
                if( $match == $m ) break;
            }
            $match->setBracket( $this );
            $match->isValid();
            $this->matches[] = $match;
            $match->setBracket( $this );
            $result = $this->setDirty();
		}
        
        return $result;
	}

    /**
     * Access all Matches in this Event sorted by round number then match number
	 * @param $force When set to true will force loading of matches
	 *               This will cause unsaved matches to be lost.
	 * @return Array of all matches for this event
     */
    public function getMatches( $force = false ):array {
        if( !isset( $this->matches ) || $force ) $this->fetchMatches();
        foreach( $this->matches as $match ) {
            $match->setBracket( $this );
        }
        usort( $this->matches, array( __CLASS__, 'sortByRoundMatchNumberAsc' ) );
        return $this->matches;
	}
	
    /**
     * Access all Matches in this Event for a specific round
	 * @param $rndnum The round number of interest
	 * @return Array of matches belonging to the round
     */
	public function getMatchesByRound( int $rndnum, $force = false ) {
		$result = array();
		foreach( $this->getMatches( $force ) as $match ) {
			if( $match->getRoundNumber() === $rndnum ) {
				$result[] = $match;
			}
		}
        usort( $result, array( __CLASS__, 'sortByMatchNumberAsc' ) );
		return $result;
    }
    
    /**
     * Get a specific match in this Event
	 * @param $rndnum The round number
	 * @param $matchnum The match number
	 * @return Match if successful, null otherwise
     */
	public function getMatch( int $rndnum, int $matchnum, $force = false ) {
		$result = null;
		foreach( $this->getMatches( $force ) as $match ) {
			if( $match->getRoundNumber() === $rndnum  && $match->getMatchNumber === $matchnum ) {
				$result = $match;
			}
		}
		return $result;
	}

    /**
     * Get the number of matches in this total
     */
    public function numMatches():int {
        return count( $this->getMatches() );
	}
	
    /**
     * Get the number of matches in this Round
	 * @param $round The round number of the desired matches
	 * @return Count of matches in the given round
     */
    public function numMatchesByRound( int $round ):int {		
		return array_reduce( function ( $sum, $m ) use( $round ) { if( $m->getRound() === $round ) ++$sum; }, $this->getMatches(), 0);
	}

    /**
     * Get the highest match number used in the given round
	 * in a tournament
     * @param $rn the round number of interest
	 * @return The maximum of all the match numbers in the round
     */
    public function maxMatchNumber( int $rn ):int {
        global $wpdb;

        $sql = "SELECT IFNULL(MAX(match_num),0) FROM $table WHERE event_ID=%d AND bracket_num=%d AND round_num=%d;";
        $safe = $wpdb->prepare( $sql, $this->getID(), $this->bracket_num, $rn );
        $max = (int)$wpdb->get_var( $safe );

        return $max;
    }
	
	/**
	 * Remove the collection of Matches
	 */
	public function removeAllMatches() {
		if( isset( $this->matches ) ) {
			$i=0;
			foreach( $this->matches as $match ) {
				$this->matchesToBeDeleted[] = $match;
				unset( $this->matches[$i++] );
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

	
	public function isValid() {
		$isvalid = true;
        $mess = '';
        
        if( $this->event_ID < 1 ) {
            $mess = __( "Bracket must have an event id." );
        }
        elseif( !$this->isNew() &&  $this->bracket_num < 1 ) {
            $mess = __( "Bracket must have a bracket number." );
        }

		if( strlen( $mess ) > 0 ) {
			throw new InvalidBracketException( $mess );
		}

		return $isvalid;
	}

	/**
	 * Delete this Bracket
	 */
	public function delete() {
		$result = 0;
        global $wpdb;
        $table = $wpdb->prefix . self::$tablename;
        $where = array( 'event_ID' => $this->event_ID, 'bracket_num' => $this->bracket_num  );
        $formats_where = array( '%d', '%d' );
        $wpdb->delete( $table, $where, $formats_where );
        $result = $wpdb->rows_affected;

		error_log( sprintf( "Bracket.delete(%s) -> deleted %d row(s)", $this->toString(), $result ) );
		return $result;
    }
    
    public function toString() {
        return sprintf( "B(%d,%d)", $this->event_ID, $this->bracket_num );
    }

    public function title() {
        return sprintf( "%s-%s", $this->toString(), $this->getName() );
    }

	/**
	 * Fetch event for this bracket
	 */
	private function fetchEvent() {
		$this->event = Event::get( $this->event_ID );
		
    }
    
    /**
     * Fetch Matches all Matches for this bracket from the database
     */
    private function fetchMatches() {
		$this->matches = Match::find( $this->getEvent()->getID(), $this->bracket_num );
	}
    
	private function sortByMatchNumberAsc( $a, $b ) {
		if($a->getMatchNumber() === $b->getMatchNumber()) return 0; return ($a->getMatchNumber() < $b->getMatchNumber()) ? -1 : 1;
	}

    /**
     * Sort matches by round number then match number in ascending order
     * Assumes that across all matches, the max match number is less than $max
     */
	private function sortByRoundMatchNumberAsc( $a, $b, $max = 1000 ) {
        if($a->getRoundNumber() === $b->getRoundNumber() && $a->getMatchNumber() === $b->getMatchNumber()) return 0; 
        $compa = $a->getRoundNumber() * $max + $a->getMatchNumber();
        $compb = $b->getRoundNumber() * $max + $b->getMatchNumber();
        return ( $compa < $compb  ? -1 : 1 );
	}

	protected function create() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		global $wpdb;

        parent::create();
        
        $table = $wpdb->prefix . self::$tablename;
        $wpdb->query("LOCK TABLES $table LOW_PRIORITY WRITE;");

        if( $this->bracket_num > 0 ) {
            $sql = "SELECT COUNT(*) FROM $table WHERE event_ID=%d AND bracket_num=%d;";
            $exists = (int) $wpdb->get_var( $wpdb->prepare( $sql,$this->event_ID, $this->bracket_num ), 0, 0 );
            
            //If this bracket arleady exists throw exception
            if( $exists > 0 ) {
                $wpdb->query( "UNLOCK TABLES;" );
                $rnd = $this->bracket_num;
                $evtId = $this->event_ID;
                $code = 870;
                throw new InvalidBracketException( "Cannot create Bracket($evtId,$rnd) because it already exists.", $code );
            }
        }
        else {
            //If bracket_num is zero, then use the next largest value from the db
            $sql = "SELECT IFNULL(MAX(bracket_num),0) FROM $table WHERE event_ID=%d;";
            $safe = $wpdb->prepare( $sql, $this->event_ID );
            $this->bracket_num = $wpdb->get_var( $safe ) + 1;
            error_log( sprintf("%s(%s) bracket number assigned.", $loc, $this->toString() ) );
        }

        $values  = array('event_ID' => $this->event_ID
                        ,'bracket_num' => $this->bracket_num
                        ,'is_approved' => ($this->is_approved ? 1 : 0 )
                        ,'name'=>$this->name );
		$formats_values = array( '%d', '%d', '%d', '%s' );
		$wpdb->insert( $table, $values, $formats_values );
		$result = $wpdb->rows_affected;
        $wpdb->query( "UNLOCK TABLES;" );
		$this->isnew = false;
		$this->isdirty = false;
		
        error_log( sprintf("%s(%s) -> %d rows affected.", $loc, $this->toString(), $result) );

		return $result;
	}

	/**
	 * Update the Bracket in the database
	 */
	protected function update() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		global $wpdb;

		parent::update();

		$values         = array( 'name'        =>$this->name
                               , 'is_approved' => ($this->is_approved ? 1 : 0 ) );
		$formats_values = array( '%s', '%d');
        $where          = array( 'event_ID'    => $this->event_ID
                               , 'bracket_num' => $this->bracket_num );
		$formats_where  = array( '%d', '%d');
		$wpdb->update($wpdb->prefix . self::$tablename,$values,$where,$formats_values,$formats_where);
		$this->isdirty = false;
		$result = $wpdb->rows_affected;

		$result += $this->manageRelatedData();

        error_log( sprintf("%s(%s) -> %d rows affected.", $loc, $this->toString(), $result) );
		return $result;
    }
    
	private function manageRelatedData():int {
		$result = 0;

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
	
    /**
     * Map incoming data to an instance of Bracket
     */
    protected static function mapData( $obj, $row ) {
        parent::mapData( $obj, $row );
        $obj->event_ID      = (int) $row["event_ID"];
        $obj->bracket_num   = (int) $row["bracket_num"];
        $obj->is_approved   = (int) $row["is_approved"];
        $obj->name          = $row["name"];
		$obj->is_approved   = $obj->is_approved === 0 ? false : true;
	}
	
	private function init() {
	}

} //end class
 