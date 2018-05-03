<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dir = plugin_dir_path( __DIR__ );
include_once($dir . '/gw-support.php' );

/** 
 * Data and functions for a Tennis Event Match
 * @class  Match
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
*/
class Match extends AbstractData
{ 
    private const MAX_ROUNDS = 7;

    private static $tablename = 'tennis_match';
	private static $datetimeformat = "Y-m-d H:i:s";
    private static $indateformat = "!Y-m-d";
    private static $outdateformat = "Y-m-d";
    private static $intimeformat = "H:i";
    private static $outtimeformat = "H:i";
    
    private $match_type; 
    private $event;

    //Primary key---
    private $event_ID;
    private $round_num;
    private $match_num;
    //---

    private $match_date;
    private $match_time;
    private $is_bye = false;

    //pointers for linked list
    private $next_round_num = 0;
    private $next_match_num = 0;

    //Match needs 2 entrants: home and visitor
    private $home_ID;
    private $home;
    private $visitor_ID;
    private $visitor;

    private $comments;
    
    //Sets in this Match
    private $sets;
    private $setsToBeDeleted = array();
    
    /**
     * Search for Matches that have a name 'like' the provided criteria
     */
    public static function search($criteria) {
        
		$criteria .= strpos($criteria,'%') ? '' : '%';
		$col = array();
		return $col;
    }
    
    /**
     * Find all Matches belonging to a specific Event and Round;
     */
    public static function find( ...$fk_criteria ) {
		global $wpdb;
        $table = $wpdb->prefix . self::$tablename;
        $col = array();
        $eventID = 0;
        $roundnum = 0;
        $safe;

        if(isset($fk_criteria[0]) && is_array( $fk_criteria[0]) ) $fk_criteria = $fk_criteria[0];
        
        if( array_key_exists( 'event_ID', $fk_criteria ) && array_key_exists( 'round_num', $fk_criteria ) ) {
            $eventID = $fk_criteria["event_ID"];
            $roundnum = $fk_criteria["round_num"];
            $sql = "SELECT event_ID,round_num,match_num,match_type,match_date,match_time,is_bye,next_round_num,next_match_num,comments 
                    FROM $table WHERE event_ID = %d AND round_ID = %d;";
            $safe = $wpdb->prepare( $sql, $eventID, $roundnum );
        }
        elseif( 2 === count( $fk_criteria ) ) {
            list($eventID,$roundnum) = $fk_criteria;
            $sql = "SELECT event_ID,round_num,match_num,match_type,match_date,match_time,is_bye,next_round_num,next_match_num,comments 
                    FROM $table WHERE event_ID = %d AND round_ID = %d;";
            $safe = $wpdb->prepare( $sql, $eventID, $roundnum );
        }
        else {
            $eventID = $fk_criteria[0];
            error_log( "Match::find using eventID=$eventID" );
            $sql = "SELECT event_ID,round_num,match_num,match_type,match_date,match_time,is_bye,next_round_num,next_match_num,comments 
                    FROM $table WHERE event_ID = %d;";
            $safe = $wpdb->prepare( $sql, $eventID );
        }
        
		$rows = $wpdb->get_results( $safe, ARRAY_A );

		foreach( $rows as $row ) {
            $obj = $roundnum > 0 ? new Match( $eventID, $roundnum ) : new Match( $eventID );
            self::mapData( $obj, $row );
			$col[] = $obj;
		}
		return $col;
    }

	/**
	 * Get instance of a Match using primary key: event_id, round_num, match_num
	 */
    static public function get( int ...$pks ) {
		global $wpdb;
        $table = $wpdb->prefix . self::$tablename;
        $obj = NULL;
        if( count( $pks ) !== 3 ) return $obj;
        
        $sql = "SELECT event_ID,round_num,match_num,match_type,match_date,match_time,is_bye,next_round_num,next_match_num,comments  
                FROM $table WHERE event_ID=%d AND round_num=%d AND match_num=%d;";
		$safe = $wpdb->prepare( $sql, $pks );
		$rows = $wpdb->get_results( $safe, ARRAY_A );

		error_log( "Match::get(id) $wpdb->num_rows rows returned." );

		if( count($rows) === 1 ) {
			$obj = new Match( ...$pks );
            self::mapData( $obj, $rows[0] );
		}
		return $obj;
    }


    /**
     * Move a match from its current spot to the target match number.
     * If successful, the change is cacaded to the dependent tables (such as Set and EntrantMatch tables)
     * @param $eventId The event id of this match
     * @param $round The round number of this match
     * @param $fromMatchNum The match's current number (i.e. place in the lineup)
     * @param $toMatchNum The intended place for this match
     * @param $cmts Comments, if any, associated with the move
     * @return The rows affected by this cascading update
     */
    static public function move( int $eventId, int $round, int $fromMatchNum, int $toMatchNum, string $cmts = null ) {
		global $wpdb;
        $table = $wpdb->prefix . self::$tablename;
        $fromId = "Match($eventId,$round,$fromMatchNum)";
        $toId = "Match($eventId,$round,$toMatchNum)";
        $tempMatchNum = 99999;
        
        date_default_timezone_set("America/Toronto");
        $stamp = date("Y-m-d h:i:sa");
        $comments = isset( $cmts ) ? $cmts : "Moved from $fromId to $toId on $stamp";
        $result = 0;

        //Check match numbers for appropriate ranges
        if( $fromMatchNum < 1 || $toMatchNum < 1 || $toMatchNum >= $tempMatchNum || ( $fromMatchNum === $toMatchNum ) ) {
            error_log("Match::move $fromId to $toId: match number(s) out of range.");
            return $result; //early return
        }

        error_log( "Match::move: attempting to move from $fromId to $toId with comments='$comments'" );
        $sql = "SELECT count(*) 
                FROM $table WHERE event_ID = %d AND round_num = %d AND match_num = %d;";
                
        $safe = $wpdb->prepare( $sql, array( $eventId, $round, $fromMatchNum ) );
        $sourceExists = (int) $wpdb->get_var( $safe );
        error_log("Move $fromId to $toId: sourceExists=$sourceExists");

        if( $sourceExists === 1 ) {
            //Source match exists
            //Check if target (i.e. the toMatchNum) exists             
            $safe = $wpdb->prepare( $sql, array( $eventId, $round, $toMatchNum ) );
            $targetExists = (int) $wpdb->get_var( $safe );
            if( $targetExists === 0 ) {
                //Target match number does not exist, so just update the match number to the target number
                $values = array( 'match_num' => $toMatchNum
                                , 'comments'  => $comments );

                $formats_values = array( '%d', '%s' );
                $where          = array( 'event_ID'  => $eventId
                                        , 'round_num' => $round
                                        , 'match_num' => $fromMatchNum );
                $formats_where  = array( '%d', '%d', '%d' );
        
                $check = $wpdb->update( $table, $values, $where, $formats_values, $formats_where );
                $result = $wpdb->rows_affected;

                if( $wpdb->last_error ) {
                    $mess = "Moving $fromId to $toId: update open match number encountered error: '$wpdb->last_error'";
                    error_log("Match.move: $mess");
                    throw new InvalidMatchException( $mess ); 
                }
                error_log( "Match::move to open match number $toMatchNum: $result rows affected." );
            }
            else {   
                //Source and target match numbers exist ...
                //First we have to move the source match to a safe place 
                // ... give it a temporary match number
                $values = array( 'match_num' => $tempMatchNum
                                , 'comments'  => $comments );

                $formats_values = array( '%d', '%s' );
                $where          = array( 'event_ID'  => $eventId
                                        , 'round_num' => $round
                                        , 'match_num' => $fromMatchNum );
                $formats_where  = array( '%d', '%d', '%d' );

                $check = $wpdb->update( $table, $values, $where, $formats_values, $formats_where );

                if( $wpdb->last_error ) {
                    $mess = "Moving $fromId to temporary number $tempMatchNum: encountered error: '$wpdb->last_error'";
                    error_log("Match.move: $mess");
                    throw new InvalidMatchException( $mess ); 
                }
                error_log( "Match::move $fromId to temporary match number $tempMatchNum: $check rows affected." );

                //Target exists so update match_num by 1 starting from highest to lowest 
                // i.e. from the highest match_num (but less than temp number) down to the target match_num
                //Need to start a transaction (default isolation level)
                $wpdb->query( "start transaction;" );

                $sql = "SELECT event_ID,round_num,match_num,match_type,match_date,match_time 
                        FROM $table WHERE event_ID = %d AND round_num = %d AND match_num >= %d and match_num < %d 
                        ORDER BY match_num DESC FOR UPDATE;";
                $safe = $wpdb->prepare( $sql, array( $eventId, $round, $toMatchNum, $tempMatchNum ) );
                $trows = $wpdb->get_results( $safe );

                if( $wpdb->last_error ) {
                    $mess = "Moving $fromId to $toId: select for update encountered error: '$wpdb->last_error'";
                    error_log( "Match.move: $mess" );
                    $wpdb->query( "rollback;" ); 
                    throw new InvalidMatchException( $mess ); 
                }
                
                foreach( $trows as $trow ) {
                    $oldNum = $trow->match_num;
                    $newNum = $trow->match_num + 1;

                    $values = array( 'match_num' => $newNum );
                    $formats_values = array( '%d' );
                    $where          = array( 'event_ID'  => $eventId
                                            , 'round_num' => $round
                                            , 'match_num' => $oldNum );
                    $formats_where  = array( '%d', '%d', '%d' );
                    $check = $wpdb->update( $table, $values, $where, $formats_values, $formats_where );

                    if( $wpdb->last_error ) {
                        $mess = "Moving $fromId to $toId: updating $oldNum to $newNum encountered error: '$wpdb->last_error'";
                        error_log("Match.move: $mess");
                        $wpdb->query( "rollback;" ); 
                        throw new InvalidMatchException( $mess ); 
                    }

                    $result += $wpdb->rows_affected;
                    error_log( "Match::move making room -> moved match number $oldNum to $newNum:  $result cumulative rows affected." );
                }

                //Now update the source's temporary match number to the target number
                $values = array( 'match_num' => $toMatchNum
                                , 'comments'  => $comments );
                $formats_values = array( '%d', '%s' );
                $where          = array( 'event_ID'  => $eventId
                                        , 'round_num' => $round
                                        , 'match_num' => $tempMatchNum );
                $formats_where  = array( '%d', '%d', '%d' );
                $check = $wpdb->update( $table, $values, $where, $formats_values, $formats_where );

                if( $wpdb->last_error ) {
                    $mess = "Moving $fromId to $toId: updating $tempMatchNum to $toMatchNum encountered error: '$wpdb->last_error'";
                    error_log("Match.move: $mess");
                    $wpdb->query( "rollback;" ) ; 
                    throw new InvalidMatchException( $mess ); 
                }
                $result += $wpdb->rows_affected;
                
                $wpdb->query( "commit;" );  
                error_log( "Match::move from $tempMatchNum to $toMatchNum: $result cumulative rows affected." );
            }
        }
        elseif( $sourceExists > 1 ) {
            //Error condition
            $mess = __( "$fromId: multiple matches found." );
            error_log( $mess );
            throw new InvalidMatchException( $mess, 500 );
        }
        elseif( $sourceExists === 0 ) {
            $mess = __( "$fromId: does not exist." );
            error_log("Match::move $mess" );
        }

        return $result;
    }

    /**
     * Resequence the matches for an event. NOT IMPLEMENTED YET.
     * @param $evtId The event id
     * @param $start the value of the starting match number
     * @parram $inc the increment to use in generating the match numbers
     */
    static public function resequence( int $evtId, int $start = 1, $inc = 1 ) {
        $result = 0;
        if( $start > 0 && $inc > 0 ) {
            $result = 0;
        }
        return $result;
    }

	/*************** Instance Methods ****************/
	public function __construct( int $eventId, int $round = 0, int $match = 0 ) {
        $this->isnew = true;
        $this->event_ID = $eventId;
        $this->getEvent(true);
        $this->round_num = $round;
        $this->match_num = $match;
        $this->init();
    }

    public function __destruct() {
        $this->event = null;

        foreach($this->getSets() as &$set) {
            $set = null;
        }
    }

    public function setDirty() {
        $this->getEvent()->setDirty();
        error_log(sprintf("%s(%d) set dirty", __CLASS__, $this->getID() ) );
        return parent::setDirty();
    }
    
    /**
     * Assign this Match to an Event
     */
    public function setEvent( Event &$event ) {
        $result = false;
        if($event->isLeaf()) {
            $this->event = $event;
            $this->event_ID = $event->getID();
            $result = $this->setDirty();
        }
        return $result;
    }
    
    /**
     * Get this Match's Event.
     */
    public function getEvent( $force = false ):Event {
        if((isset($this->event_ID) && !isset($this->event)) || $force) {
            $this->event = Event::get($this->event_ID);
        }
        return $this->event;
    }

    /**
     * Set the Match's Round Number
     */
    public function setRoundNumber( int $rn ) {
        $result = false;
        if($rn > -1 && $rn <= self::MAX_ROUNDS) {
            $this->round_num = $rn;
            $result = $this->setDirty();
        }
        return $result;
    }

    /**
     * Get the Match's Round Number
     */
    public function getRoundNumber():int {
        return $this->round_num;
    }

    /**
     * Set the Match's Number
     */
    public function setMatchNumber( int $mn ) {
        $result = false;
        if( $mn > 0 ) {
            $this->match_num = $mn;
            $result = $this->setDirty();
        }
        return $result;
    }

    /**
     * Get the Match's Number
     */
    public function getMatchNumber():int {
        return $this->match_num;
    }

    /**
     * Set the next Round number
     * This is the number of the next round that follows
     * This and the next match number comprise the pointer
     * for the linked list of matches
     */
    public function setNextRoundNumber( int $rn ) {
        $result = false;
        if( $rn > -1 && $rn <= self::MAX_ROUNDS) {
            $this->next_round_num = $rn;
            $result = $this->setDirty();
        }
        return $result;
    }

    /**
     * Get the next Round Number
     * This and the next match number comprise the pointer
     * for the linked list of matches
     */
    public function getNextRoundNumber():int {
        return $this->next_round_num;
    }

    /**
     * Set the next Match number
     * This is the number of the match that follows
     * this one in the next round (i.e. the winner plays this one next)
     * This and the next round number comprise the pointer
     * for the linked list of matches
     */
    public function setNextMatchNumber( int $mn ) {
        $result = false;
        if( $mn > -1 ) {
            $this->next_match_num = $mn;
            $result = $this->setDirty();
        }
        return $result;
    }

    /**
     * Get the next Match Number
     * This and the next round number comprise the pointer
     * for the linked list of matches
     */
    public function getNextMatchNumber():int {
        return $this->next_match_num;
    }
    
	/**
	 * Choose whether this mmatch is a mens, ladies or mixed event.
	 * @param $mtype 1.1=mens singles, 1.2=ladies singles, 2.1=mens dodubles, 2.2=ladies doubles, 2.3=mixed douibles
	 * @return true if successful; false otherwise
	 */
	public function setMatchType( float $mtype ) {
		$result = false;
        switch($mtype) {
            case MatchType::MENS_SINGLES:
            case MatchType::WOMENS_SINGLES:
            case MatchType::MENS_DOUBLES:
            case MatchType::WOMENS_DOUBLES:
            case MatchType::MIXED_DOUBLES:
                $this->match_type = $mtype;
                $result = $this->setDirty();
                break;
        }
		return $result;
    }
    
    /**
     * Get this Match's match type
     */
    public function getMatchType():float {
        return $this->match_type;
    }

    /**
     * Set the date of the match
     * @param $date is a string in Y-m-d format
     */
    public function setMatchDate_Str( string $date ) {
		$result = false;
		$test = DateTime::createFromFormat( self::$indateformat, $end );
		if(false === $test) $test = DateTime::createFromFormat( '!Y-m-d', $end );
		if(false === $test) $test = DateTime::createFromFormat( '!j/n/Y', $end );
		if(false === $test) $test = DateTime::createFromFormat( '!d/m/Y', $end );
		if(false === $test) $test = DateTime::createFromFormat( '!d-m-Y', $end );
		$last = DateTIme::getLastErrors();
		if( $last['error_count'] > 0 ) {
			$arr = $last['errors'];
			$mess = '';
			foreach( $arr as $err ) {
				$mess .= $err.':';
			}
			throw new InvalidMatchException( $mess );
		}
		elseif( $test instanceof DateTime ) {
			$this->match_date = $test;
			$result = $this->setDirty();
		}

        return $result;
    }

    public function setMatchDate( int $year, int $month, int $day ) {
        if( !isset( $this->match_date ) ) $this->match_date = new DateTime();
        $this->match_date->setDate( $year, $month, $day );
        $this->match_date->setTime( 0, 0, 0 );
    }

	/**
	 * Get the Match date in string format
	 */
	public function getMatchDate_Str() {
		if( !isset( $this->match_date ) ) return null;
		else return $this->match_date->format( self::$outdateformat );
	}
	
	/**
	 * Get the Match date in ISO 8601 format
	 */
	public function getMatchDate_ISO() {
		if( !isset( $this->match_date ) ) return null;
		else return $this->match_date->format (DateTime::ISO8601 );
	}

    /**
     * Set the time of the match
     * @param $time is a string in hh-mm-ss format
     */
    public function setMatchTime_Str( string $time ) {
		$result = false;
		$test = DateTime::createFromFormat( self::$intimeformat, $end );
		$last = DateTIme::getLastErrors();
		if($last['error_count'] > 0) {
			$arr = $last['errors'];
			$mess = '';
			foreach($arr as $err) {
				$mess .= $err.':';
			}
			throw new InvalidMatchException( $mess );
		}
		elseif($test instanceof DateTime) {
			$this->match_time = $test;
			$result = $this->setDirty();
		}

        return $result;
    }

    public function setMatchTime( int $hour, int $minutes ) {
        if( !isset( $this->match_time ) ) {
            $this->match_time = new DateTime();
        }
        $this->match_time->setTime( $hour, $minutes );
        $this->match_time->setDate ( 0, 1, 1 );
        
        return $this->setDirty();
    }

    public function getMatchTime_Str() {
        if( !isset( $this->match_time ) ) return null;
        else return $this->match_time->format( self::$outtimeformat );
    }

    public function getMatchTime() {
        return $this->match_time;
    }

    public function setIsBye( bool $by = false ) {
        $this->is_bye = $by;
        return $this->setDirty();
    }

    public function isBye() {
        return $this->is_bye;
    }

    public function isWaiting() {
        $result = false;
        $noVisitor = !isset( $this->visitor ) || $this->visitor->getName() === 'tba';
        if( isset( $this->home ) && $noVisitor && !$this->isBye() ) $result = true;
        return $result;
    }
    
    /**
     * Set this Match's comments
     */
    public function setComments( string $comment ) {
        $this->comments = $comment;
        $result = $this->setDirty();
        return $result;
    }

    public function getComments():string {
        return isset( $this->comments ) ? $this->comments : '';
    }

    /**
     * Get the Sets for this Match
	 * @param $force When set to true will force loading of Sets from db
	 *               This will cause unsaved Sets to be lost.
     */
    public function getSets( $force = false ) {
        //var_dump(debug_backtrace());
        if( !isset( $this->sets ) || $force ) $this->fetchSets();
        return $this->sets;
    }

    /**
     * Get a specific numbered Set for this match
     */
    public function getSetByNumber( int $setnum ) {
        $result = null;
        $sets = $this->getSets();
        foreach ($sets as $set ) {
            if( $set->getSetNumber() === $setnum ) {
                $result = $set;
                break;
            }
        }
        return $result;
    }
    
    /**
     * Set a score for a given Set of tennis.
     * Updates a Set if already a child of this Match
     * or creates a new Set and adds it to the Match's array of Sets
     * 
     * @param int $set Identifies the set by number
     * @param int $home_wins is the number of wins for the home entrant
     * @param int @visitor_wins is the number of wins for the visitor entrant
     * @throws nothing
     * @return true if successful false otherwise
     */
    public function setScore( int $setnum, int $home_wins, int $visitor_wins, int $hometb = 0, int $visitortb = 0, $hometies = 0, $visitorties = 0 ) {
        $result = false;
        $found = false;

        $loc = __CLASS__ . "::" . __FUNCTION__;
        $mess = sprintf( "%s(%s) starting", $loc, $this->toString() );
        error_log( $mess );

        $this->isValid();

        foreach( $this->getSets() as $set ) {
            if( $set->getSetNumber() === $setnum ) {
                $found = true;
                $set->setHomeScore( $home_wins, $hometb, $hometies );
                $set->setVisitorScore( $visitor_wins, $visitortb, $visitorties );
                $set->isValid();
                $result = $this->setDirty();
                $mess = sprintf( "%s(%s)-> modified scores"
                               , $loc, $set->toString() );
                error_log( $mess );
            }
        }
    
        if( !$found ) {
            $set = new Set( $this->event_ID, $this->round_num, $this->match_num, $setnum );
            if( $set->setSetNumber( $setnum ) ) {
                $set->setHomeScore( $home_wins, $hometb, $hometies );
                $set->setVisitorScore( $visitor_wins, $visitortb, $visitorties );
                $set->isValid();
                $this->sets[] = $set;
                $result = $this->setDirty();

                $mess = sprintf( "%s(%s)-> set added with scores"
                               , $loc, $set->toString() );
                error_log( $mess );
            }
        }
        
        $mess = sprintf( "%s(%s) returning with result=%s", $loc, $this->toString(), $result );
        error_log( $mess );

        return $result;
    }

    /**
     * Add a Set to this Match
     * @param $set Set contains the games won/lost for the Entrants
     */
    public function addSet( Set $set ) {
        $result = false;
        if(isset($set)) {
            $found = false;
            foreach($this->getSets() as $s) {
                if($set->getSetNumber() === $s->getSetNumber()) {
                    $found = true;
                    break;
                }
            }
            if(!$found) {
                $this->sets[] = $set;
                $result = $this->setDirty();
            }
        }
        return $result;
    }
    
    /**
     * Remove a Set from this Match
     */
    public function removeSet( int $set ) {
		$result = false;
		if(isset($set)) {
			$i=0;
			foreach($this->getSets() as $s) {
				if($set == $s->getSetNumber()) {
					$this->setsToBeDeleted[] = clone $this->sets[$i];
                    unset($this->sets[$i]);
					$result = $this->setDirty();
				}
				$i++;
			}
		}
		return $result;
    }

    /**
     * Set the Home opponent for this match
     * @param $h The home entrant
     */
    public function setHomeEntrant( Entrant $h ) {
        $result = false;
        if( isset( $h ) ) {
            $this->home = $h;
            $this->home_ID = $h->getPosition();
            $result = $this->setDirty();
        }
        return $result;
    }

    /**
     * Get the home Entrant
     * @param $force IF true then Entrants will be fetched and overwrite existing.
     */
    public function getHomeEntrant( $force = false ) {
        if( !isset( $this->home ) || $force ) $this->fetchEntrants();
        return $this->home;
    }
    
    /**
     * Set the Visitor opponent for this match
     * @param $v The visitor entrant
     */
    public function setVisitorEntrant( Entrant $v ) {
        $result = false;
        if( isset( $v ) ) {
            $this->visitor = $v;
            $this->visitor_ID = $v->getPosition();
            $result = $this->setDirty();
        }
        return $result;
    }

    /**
     * Get the visitor Entrant
     * @param $force IF true then Entrants will be fetched and overwrite existing.
     */
    public function getVisitorEntrant( $force = false ) {
        $fetch = false;
        if( !isset( $this->home ) && !isset( $this->visitor ) ) {
            $fetch = true;
        }
        else if( $force ) {
            $fetch = true;
        }
        
        if( $fetch ) $this->fetchEntrants();

        return $this->visitor;
    }
    
    /**
     * Check if match is valid (ready to go)
     * @throws InvalidMatchException
     */
    public function isValid() {
        $mess = '';

        $evtId = isset( $this->event_ID ) ? $this->event_ID : '???';
        $mn = $this->match_num;
        $rn = $this->round_num;
        $home = $this->getHomeEntrant();
        $hname = isset($home) ? $home->getName() : 'unknown';
        // error_log("Match($mn).isValid: home=$hname");
        $visitor = $this->getVisitorEntrant();
        $vname = isset($visitor) ? $visitor->getName() : 'unknown';
        $id = "Event($evtId) Round($rn) Match ($mn)->$vname vs $hname: ";
        $code = 0;

        if( !isset( $this->event_ID ) ) {
            $mess = __( "$id must have an event id." );
            $code = 505;
            error_log( $mess );
        } 
        else if( !isset( $this->round_num ) ) {
            $mess = __( "$id must have a round number." );
            $code = 510;
            error_log( $mess );
        }
        else if( !$this->isNew() && ( !isset( $this->match_num )  || $this->match_num === 0 ) ) {
             $mess = __( "$id existing match must have a match number." );
             $code = 520;
             error_log( $mess );
        }
        else if( !isset( $home ) ) {
            $mess = __( "$id must have a home entrant." );
            $code = 530;
            error_log( $mess );
        }
        // else if( !isset( $visitor ) && !$this->isBye()) {
        //     $mess = __( "Match ($mn) is not a bye so must have a visitor entrant." );
        // }
        else if( !isset( $this->match_type ) ) {
            $mess = __( "$id must have a match type." );
            $code = 540;
            error_log( $mess );
        }
        else if( $this->round_num < 0 || $this->round_num > self::MAX_ROUNDS ) {
            $max = self::MAX_ROUNDS;
            $mess = __( "$id round number not between 1 and $max (inclusive)." );
            $code = 1;
            error_log( $mess );
        }
        
        switch( $this->match_type ) {
            case MatchType::MENS_SINGLES:
            case MatchType::WOMENS_SINGLES:
            case MatchType::MENS_DOUBLES:
            case MatchType::WOMENS_DOUBLES:
            case MatchType::MIXED_DOUBLES:
                break;
            default:
            $mess = __( "$id - Match Type is invalid: $this->match_type" );
            $code = 560;
            error_log( $mess );
        }

        if( strlen( $mess ) > 0 ) throw new InvalidMatchException( $mess, $code );

        return true;
    }
    
    /**
     * Delete this match
     */
    public function delete() {
        global $wpdb;		
        
        // $result = EntrantMatchRelations::remove($this->getEventID(),$this->getRoundNumber(),$this->getMatchNumber(),$this->getHomeEntrant()->getPosition());
        // $result += EntrantMatchRelations::remove($this->getEventID(),$this->getRoundNumber(),$this->getMatchNumber(),$this->getVisitorEntrant()->getPosition());

        $table = $wpdb->prefix . self::$tablename;
        $where = array( 'event_ID' => $this->event_ID
                      , 'round_num' => $this->round_num
                      , 'match_num' => $this->match_num );
        $formats_where = array( '%d', '%d', '%d' );

        $wpdb->delete( $table,$where, $formats_where );
        $result = $wpdb->rows_affected;

        error_log("Match.delete: deleted $result rows");
        return $result;
    }

    /**
     * Move this match forward or backward in the round by given number of places
     * @param $places The number of places to move the match
     * @param $forward If true then move the match forward; otherwise move it backward
     * @return true if successful; false otherwise
     */
    public function moveBy( int $places, bool $forward = true ) {
        $result = 0;
        if( $places > 0 && $places < 257 ) {
            $from = $this->match_num;
            $to   = $forward ? $this->match_num + $places : $this->match_num - $places;
            $to   = max( 1, $to );
            $result =  Match::move( $this->event_ID, $this->round_num, $from, $to );
        }
        return $result > 0;
    }

    /**
     * Get the highest match number used in the given round
     * If no round number is given, then this match's round number is used
     * @param $rn the round number of interest
     */
    public function maxMatchNumber( int $rn = -1 ):int {
        global $wpdb;

        $sql = "SELECT IFNULL(MAX(match_num),0) FROM $table WHERE event_ID=%d AND round_num=%d;";
        if( $rn >= 0 ) {
            $safe = $wpdb->prepare( $sql, $this->event_ID, $rn );
        }
        else {
            $safe = $wpdb->prepare( $sql, $this->event_ID, $this->round_num );
        }
        $max = (int)$wpdb->get_var( $safe );

        return $max;
    }

    public function toString() {
        return sprintf("M(%d,%d,%d)",$this->event_ID, $this->round_num, $this->match_num );
    }

    /**
     * Fetch the zero, 1 or 2 Entrants from the database.
     */
    private function fetchEntrants() {
        $contestants = Entrant::find( $this->event_ID, $this->round_num, $this->match_num );
        switch( count( $contestants ) ) {
            case 0:
                $this->home = null;
                $this->home_ID = null;
                $this->visitor = null;
                $this->visitor_ID = null;
                break;
            case 1:
                $this->home = $contestants[0];
                $this->home_ID = $this->home->getPosition();
                $this->visitor = NULL;
                $this->visitor_ID = NULL;
                break;
            case 2:
                if( $contestants[0]->isVisitor() ) {
                    $this->visitor = $contestants[0];
                    $this->visitor_ID = $this->visitor->getPosition();
                    $this->home = $contestants[1];
                    $this->visitor_ID = $this->home->getPosition();
                }
                else {
                    $this->home = $contestants[0];
                    $this->home_ID = $this->home->getPosition();
                    $this->visitor = $contestants[1];
                    $this->visitor_ID = $this->visitor->getPosition();
                }
                break;
            default:
                throw new InvalidMatchException( sprintf("Match %s has %d entrants.", $this->toString(), count( $contestants ) ) );
            break;
        }
    }

	/**
	 * Fetch all Sets for this Match
	 */
    private function fetchSets() {
        //var_dump(debug_backtrace());
        $this->sets = Set::find( $this->event_ID, $this->round_num, $this->match_num );
    }

	protected function create() {
        global $wpdb;
        
        parent::create();

        $table = $wpdb->prefix . self::$tablename;

        $wpdb->query("LOCK TABLES $table LOW_PRIORITY WRITE;");

        if( isset( $this->match_num ) && $this->match_num > 0 ) {
            //If match_num has a value then use it
            $sql = "SELECT COUNT(*) FROM $table WHERE event_ID=%d AND round_num=%d AND match_num=%d;";
            $exists = (int) $wpdb->get_var( $wpdb->prepare( $sql,$this->event_ID, $this->round_num, $this->match_num ), 0, 0 );
            
            //If this match arleady exists throw exception
            if( $exists > 0 ) {
                $wpdb->query( "UNLOCK TABLES;" );
                $mn = $this->match_num;
                $rnd = $this->round_num;
                $evtId = $this->event_ID;
                $code = 570;
                throw new InvalidMatchException( "Cannot create Match($evtId,$rnd,$mn) because it already exists.", $code );
            }
        }
        else {
            //IF match_num is null or zero, then use the next largest value from the db
            $sql = "SELECT IFNULL(MAX(match_num),0) FROM $table WHERE event_ID=%d AND round_num=%d;";
            $safe = $wpdb->prepare( $sql, $this->event_ID, $this->round_num );
            $this->match_num = $wpdb->get_var( $safe ) + 1;
            error_log( "Match::create: match number assigned = '$this->match_num' and match type = '$this->match_type'" );
        }

        $values = array( 'event_ID'    => $this->event_ID
                        ,'round_num'   => $this->round_num
                        ,'match_num'   => $this->match_num
                        ,'match_type'  => $this->match_type
                        ,'match_date'  => $this->getMatchDate_Str()
                        ,'match_time'  => $this->getMatchTime_Str()
                        ,'is_bye'      => $this->is_bye ? 1 : 0
                        ,'next_round_num' => $this->next_round_num
                        ,'next_match_num' => $this->next_match_num
                        ,'comments'    => $this->comments );
        $formats_values = array( '%d', '%d', '%d', '%f', '%s', '%s', '%d', '%d', '%d', '%s' );
		$wpdb->insert( $wpdb->prefix . self::$tablename, $values, $formats_values );
        $result = $wpdb->rows_affected;
        $wpdb->query( "UNLOCK TABLES;" );
        $this->isnew = FALSE;
		$this->isdirty = FALSE;

        if($wpdb->last_error) {
            error_log("Match::create: sql error: $wpdb->last_error");
        }

        error_log("Match::create: $result rows affected.");

        if( isset( $this->sets ) ) {
            foreach($this->sets as &$set) {
                $result += $set->save();
            }
        }
        
        foreach( $this->setsToBeDeleted as &$set ) {
            $result += $set->delete();
            unset( $set );
        }
        $this->setsToBeDeleted = array();
        
        $result += EntrantMatchRelations::add( $this->event_ID, $this->getRoundNumber(), $this->getMatchNumber(), $this->getHomeEntrant()->getPosition() );
        $result += isset( $this->visitor ) ? EntrantMatchRelations::add( $this->event_ID, $this->getRoundNumber(), $this->getMatchNumber(), $this->getVisitorEntrant()->getPosition() ) : 0;

		return $result;
	}

	protected function update() {
		global $wpdb;

        parent::update();

        $values = array( 'match_type'  => $this->match_type
                        ,'match_date'  => $this->getMatchDate_Str()
                        ,'match_time'  => $this->getMatchTime_Str()
                        ,'is_bye'      => $this->is_bye ? 1 : 0
                        ,'next_round_num' => $this->next_round_num
                        ,'next_match_num' => $this->next_match_num
                        ,'comments'    => $this->comments );
		$formats_values = array( '%f', '%s', '%s', '%d', '%d', '%d', '%s' );
        $where          = array( 'event_ID'  => $this->event_ID
                               , 'round_num' => $this->round_num
                               , 'match_num' => $this->match_num );
        $formats_where  = array( '%d', '%d', '%d' );

        error_log("Match::update: where=$this->event_ID, $this->round_num, $this->match_num");
        $check = $wpdb->update( $wpdb->prefix . self::$tablename, $values, $where, $formats_values, $formats_where );
        
        $this->isdirty = FALSE;
        $result = $wpdb->rows_affected;

        error_log( "Match::update $result rows affected." );
        
        if( isset( $this->sets ) ) {
            foreach( $this->sets as &$set ) {
                $result += $set->save();
            }
        }
        
        foreach($this->setsToBeDeleted as &$set) {
            $result += $set->delete();
            unset( $set );
        }
        $this->setsToBeDeleted = array();

        $result += EntrantMatchRelations::add( $this->event_ID, $this->getRoundNumber(), $this->getMatchNumber(), $this->getHomeEntrant()->getPosition() );
        $visitor = $this->getVisitorEntrant();
        if( isset( $visitor ) ) {
            $result += EntrantMatchRelations::add( $this->event_ID, $this->getRoundNumber(), $this->getMatchNumber(), $this->getVisitorEntrant()->getPosition(), 1 );
        }
        
		return $result;
	}
    
    /**
     * Map incoming data to an instance of Match
     */
    protected static function mapData( $obj, $row ) {
        parent::mapData( $obj, $row );
        $obj->event_ID   = (int) $row["event_ID"];
        $obj->round_num  = (int) $row["round_num"];
        $obj->match_num  = (int) $row["match_num"];
        $obj->match_type = (float) $row["match_type"];
		$obj->match_date = isset( $row["match_date"] ) ? new DateTime( $row["match_date"] ) : null;
		$obj->match_time = isset( $row["match_time"] ) ? new DateTime( $row["match_time"] ) : null;
        $obj->is_bye     = $row["is_bye"] == 1 ? true : false;
        $obj->comments   = $row["comments"];
        $obj->next_round_num = (int) $row["next_round_num"];
        $obj->next_match_num = (int) $row["next_match_num"];
    }
    
    private function getIndex( $obj ) {
        return $obj->getPosition();
    }

    private function init() {
    }

} //end class