<?php
namespace datalayer;
use \TennisEvents;
use commonlib\GW_Support;
use commonlib\GW_Debug;
use utilities\CleanJsonSerializer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 
 * Data and functions for a Tennis Event Match
 * Note that all dates and times are held in UTC time zone
 * @class  Match
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
*/
class Match extends AbstractData
{ 
    public const VISITOR = "visitor";
    public const HOME    = "home";

    private const MAX_ROUNDS = 99;//NOT sure if this is useful

    public static $tablename = 'tennis_match';

    //Date and time output formats
    private static $outdatetimeformat1 = "Y-m-d G:i"; 
    private static $outdatetimeformat2 = "Y-m-d g:i a"; 

    //Date only output formats
    private static $outdateformat = "Y-m-d";

    //Time only output formats
    private static $outtimeformat1 = "G:i"; 
    private static $outtimeformat2 = "g:i a";
    
    private $match_type; 
    private $bracket;

    //Primary key---
    private $event_ID; //references a leaf event
    private $bracket_num; //references a bracket within the leaf event
    private $round_num;
    private $match_num;
    //---

    private $match_datetime;
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

    /**
     * Search not used
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
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$calledBy = debug_backtrace()[1]['function'];
        error_log("{$loc} ... called by {$calledBy}");

        //$args = print_r( $fk_criteria, true );
        //error_log("$loc: args=$args");

		global $wpdb;
        $table = $wpdb->prefix . self::$tablename;
        $col = array();
        $eventId = 0;
        $round = 0;
        $bracket = 0;
        $safe;

        if( isset( $fk_criteria[0] ) && is_array( $fk_criteria[0]) ) $fk_criteria = $fk_criteria[0];
        
        if( array_key_exists( 'event_ID', $fk_criteria ) && array_key_exists( 'bracket_num', $fk_criteria ) && array_key_exists( 'round_num', $fk_criteria ) ) {
            $eventId = $fk_criteria["event_ID"];
            $bracket = $fk_criteria["bracket_num"];
            $round = $fk_criteria["round_num"];
            $sql = "SELECT event_ID,bracket_num,round_num,match_num,match_type,match_date,is_bye,next_round_num,next_match_num,comments 
                    FROM $table WHERE event_ID = %d AND bracket_num = %d AND round_ID = %d;";
            $safe = $wpdb->prepare( $sql, $eventId, $bracketnum, $round );
        }
        elseif( array_key_exists( 'event_ID', $fk_criteria ) && array_key_exists( 'bracket_num', $fk_criteria ) ) {
            $eventId = $fk_criteria["event_ID"];
            $bracket = $fk_criteria["bracket_num"];
            $sql = "SELECT event_ID,bracket_num,round_num,match_num,match_type,match_date,is_bye,next_round_num,next_match_num,comments 
                    FROM $table WHERE event_ID = %d AND bracket_num = %d;";
            $safe = $wpdb->prepare( $sql, $eventId, $bracket );
        }
        elseif( 3 === count( $fk_criteria ) ) {
            list( $eventId, $bracketnum, $round ) = $fk_criteria;
            $sql = "SELECT event_ID,bracket_num,round_num,match_num,match_type,match_date,is_bye,next_round_num,next_match_num,comments 
                    FROM $table WHERE event_ID = %d AND bracket_num = %d AND round_num = %d;";
            $safe = $wpdb->prepare( $sql, $eventId, $bracketnum, $round );
        }
        elseif( 2 === count( $fk_criteria ) ) {
            list( $eventId, $bracket ) = $fk_criteria;
            $sql = "SELECT event_ID,bracket_num,round_num,match_num,match_type,match_date,is_bye,next_round_num,next_match_num,comments 
                    FROM $table WHERE event_ID = %d AND bracket_num = %d;";
            $safe = $wpdb->prepare( $sql, $eventId, $bracket );
        }
        else {
            return $col;
        }
        
        $rows = $wpdb->get_results( $safe, ARRAY_A );
        
        error_log("$loc: found {$wpdb->num_rows} matches");

		foreach( $rows as $row ) {
            $obj = new Match( $eventId, $bracket, $round );
            self::mapData( $obj, $row );
			$col[] = $obj;
		}
		return $col;
    }

	/**
	 * Get instance of a Match using primary key: event_id, bracket_num, round_num, match_num
	 */
    static public function get( int ...$pks ) {
		$loc = __CLASS__ . '::' . __FUNCTION__;		
		$calledBy = debug_backtrace()[1]['function'];
        error_log("{$loc} ... called by {$calledBy}");
        
		global $wpdb;
        $table = $wpdb->prefix . self::$tablename;
        $obj = NULL;
        if( count( $pks ) !== 4 ) return $obj;
        
        $sql = "SELECT event_ID,bracket_num,round_num,match_num,match_type,match_date,is_bye,next_round_num,next_match_num,comments  
                FROM $table WHERE event_ID=%d AND bracket_num=%d AND round_num=%d AND match_num=%d;";
		$safe = $wpdb->prepare( $sql, $pks );
		$rows = $wpdb->get_results( $safe, ARRAY_A );

		//error_log( sprintf("Match::get(%d,%d,%d,%d) returned %d rows.",$pks[0],$pks[1],$pks[2],$pks[3], $wpdb->num_rows ) );

		if( count($rows) === 1 ) {
			$obj = new Match( ...$pks );
            self::mapData( $obj, $rows[0] );
		}
		return $obj;
    }

    public static function deleteMatch( int $eventId = 0, int $bracketNum = 0, int $roundNum = 0, int $matchNum = 0 ) : int {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $result = 0;

        if( 0 === $eventId 
        ||  0 === $bracketNum
        ||  0 === $roundNum
        ||  0 === $matchNum ) {
            return $result;
        }

        //First delete the intersection of entrants with this match
        $result += EntrantMatchRelations::removeAllFromMatch( $eventId
                                                            , $bracketNum
                                                            , $roundNum
                                                            , $matchNum );

        global $wpdb;		
        //Now delete all the sets for this match
        $table = $wpdb->prefix . Set::$tablename;
        $where = array( 'event_ID' => $eventId
                      , 'bracket_num' => $bracketNum
                      , 'round_num' => $roundNum
                      , 'match_num' => $matchNum );
        $formats_where = array( '%d', '%d', '%d', '%d' );
        $wpdb->delete( $table, $where, $formats_where );
        $result += $wpdb->rows_affected;

        //Now delete this match
        $table = $wpdb->prefix . self::$tablename;
        $where = array( 'event_ID' => $SeventId
                      , 'bracket_num' => $bracketNum
                      , 'round_num' => $roundNum
                      , 'match_num' => $matchNum );
        $formats_where = array( '%d', '%d', '%d', '%d' );

        $wpdb->delete( $table, $where, $formats_where );
        $result += $wpdb->rows_affected;
        error_log( sprintf( "%s -> deleted %d row(s)", $loc, $result ) );

        return $result;
    }

    public static function deleteSet( int $eventId, int $bracketNum, int $roundNum, int $matchNum, int $setNum ) : int {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        
        global $wpdb;		
        
        $table = $wpdb->prefix . Set::$tablename;
        $where = array( 'event_ID' => $eventId
                      , 'bracket_num' => $bracketNum
                      , 'round_num' => $roundNum
                      , 'match_num' => $matchNum
                      , 'set_num' => $setNum );
        $formats_where = array( '%d', '%d', '%d', '%d', '%d' );

        $wpdb->delete( $table, $where, $formats_where );
        $result = $wpdb->rows_affected;
        error_log( sprintf( "%s -> deleted %d row(s)", $loc, $result ) );

        return $result;
    }

	/*************** Instance Methods ****************/
	public function __construct( int $eventId, int $bracket = 1, int $round = 0, int $match = 0 ) {
        parent::__construct( true );

        $this->isnew = true;
        $this->event_ID = $eventId;
        $this->bracket_num = $bracket;
        $this->round_num = $round;
        $this->match_num = $match;
    }

    public function __destruct() {
        static $numMatches = 0;
        $loc = __CLASS__ . '::' . __FUNCTION__;
        ++$numMatches;
        // $this->log->error_log("{$loc} ... {$numMatches}");

        unset( $this->event );

        if(isset( $this->sets ) ) {
            foreach($this->sets as &$set) {
                unset( $set );
            }
        }
    }

    public function setDirty() {
        if( isset( $this->bracket) ) $this->bracket->setDirty();
        //$this->log->error_log( sprintf("%s(%d) set dirty", __CLASS__, $this->getID() ) );
        return parent::setDirty();
    }

    public function getBracket( $force = false ) {
        if( !isset( $this->bracket ) || $force ) $this->fetchBracket();
        return $this->bracket;
    }

    public function setBracket( Bracket &$bracket ) {
        $result = false;
        if( $bracket->isValid() ) {
            $this->bracket = $bracket;
            $this->event_ID = $bracket->getEvent()->getID();
            $this->bracket_num = $bracket->getBracketNumber();
            $result = $this->setDirty();
        }
        return $result;
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
	 * Choose whether this match is a mens, ladies or mixed event.
	 * @param $mtype singles or dodubles
	 * @return true if successful; false otherwise
	 */
	public function setMatchType( $mtype ) {
		$result = false;
        if( MatchType::isValid( $mtype ) ) {
            $this->match_type = $mtype;
            $result = $this->setDirty();
        }
		return $result;
    }
    
    /**
     * Get this Match's match type
     */
    public function getMatchType() {
        return $this->match_type ?? 'unknown';
    }

    /**
     * Set the date of the match. This date will be stored
     * in the db as a UTC date.
     * @param $date is local date in string in Y-m-d format
     */
    public function setMatchDate_Str( string $date = '' ) {
        $loc = __CLASS__ . ":" . __FUNCTION__;
        $result = false;
        $tz = TennisEvents::getTimeZone();
        $this->log->error_log("$loc:({$date})");

        if( empty( $date ) ) {
            $this->match_datetime = null;
			$result = $this->setDirty();
        }
        else {
            try {
                $dt_local = new \DateTime( $date, $tz );
                $this->match_datetime = $dt_local;
                $this->match_datetime->setTimezone(new \DateTimeZone('UTC'));
                $result = $this->setDirty();
                return $result; //early return
            }
            catch( Exception $ex ) {
                $this->log->error_log("$loc: failed to construct using '{$date}'");
            }

            $test = \DateTime::createFromFormat("Y-m-d G:i", $date );
            if(false === $test) $test = \DateTime::createFromFormat("Y-m-d H:i", $date, $tz );
            if(false === $test) $test = \DateTime::createFromFormat("Y-m-d g:i a", $date, $tz );
            if(false === $test) $test = \DateTime::createFromFormat("Y-m-d h:i a", $date, $tz );
            if(false === $test) $test = \DateTime::createFromFormat("Y/m/d G:i", $date, $tz );
            if(false === $test) $test = \DateTime::createFromFormat("Y/m/d H:i", $date, $tz );
            if(false === $test) $test = \DateTime::createFromFormat("Y/m/d g:i a", $date, $tz );
            if(false === $test) $test = \DateTime::createFromFormat("Y/m/d h:i a", $date, $tz );
            if(false === $test) $test = \DateTime::createFromFormat("d/m/Y G:i", $date, $tz );
            if(false === $test) $test = \DateTime::createFromFormat("d/m/Y H:i", $date, $tz );
            if(false === $test) $test = \DateTime::createFromFormat("d/m/Y g:i a", $date, $tz );
            if(false === $test) $test = \DateTime::createFromFormat("d/m/Y h:i a", $date, $tz );
            
            $last = DateTIme::getLastErrors();
            if( $last['error_count'] > 0 ) {
                $arr = $last['errors'];
                $mess = '';
                foreach( $arr as $err ) {
                    $mess .= $err.':';
                }
                throw new InvalidMatchException( $mess );
            }
            elseif( $test instanceof \DateTime ) {
                $this->match_datetime = $test;
                $this->match_datetime->setTimezone( new \DateTimeZone('UTC'));
                $result = $this->setDirty();
            }
        }

        return $result;
    }

    public function setMatchDate_TS( int $timestamp ) {
        $loc = __CLASS__ . ":" . __FUNCTION__;
        $this->log->error_log("$loc:{$this->toString()}($timestamp)");

        if( !isset( $this->match_datetime ) ) $this->match_datetime = new \DateTime('now', new \DateTimeZone('UTC'));
        $this->match_datetime->setTimeStamp( $timestamp );
    }

    /**
     * Get the localized DateTime object representing the start date/time of the match
     * @return object DateTime or null
     */
    public function getMatchDateTime() {
        if(empty($this->match_datetime ) ) return null;
        else {
            $temp = clone $this->match_datetime;
            return $temp->setTimezone(TennisEvents::getTimeZone());
        }
    } 
    
    /**
    * Get the UTC DateTime object representing the start time of the match
    * @return object DateTime or null
    */
   public function getMatchUTCDateTime() {
       return $this->match_datetime;
   }

	/**
	 * Get the local Match date in string format
	 */
	public function getMatchDate_Str() {
        $loc = __CLASS__ . ":" . __FUNCTION__;
        $this->log->error_log( $this->match_datetime, $loc);

		if( !isset( $this->match_datetime ) || is_null( $this->match_datetime ) ) {
            return '';
        }
		else {
            $temp = clone $this->match_datetime;
            return $temp->setTimezone(TennisEvents::getTimeZone())->format( self::$outdateformat );
        }
	}

    /**
	 * Get the UTC Match date in string format
	 */
	public function getMatchUTCDate_Str() {
        $loc = __CLASS__ . ":" . __FUNCTION__;
        $this->log->error_log( $this->match_datetime, $loc);

		if( !isset( $this->match_datetime ) || is_null( $this->match_datetime ) ) {
            return '';
        }
		else return $this->match_datetime->format( self::$outdateformat );
	}
    
	/**
	 * Get the local Match date AND time in string format
	 */
	public function getMatchDateTime_Str( int $formatNum=1) {
        $loc = __CLASS__ . ":" . __FUNCTION__;
        $this->log->error_log( $this->match_datetime, $loc);

        $result = '';
        $format = self::$outdatetimeformat1;
        switch($formatNum) {
            case 1:
                $format = self::$outdatetimeformat1;
                break;
            case 2:
                $format = self::$outdatetimeformat2;
                break;
            default:
                $format = self::$outdatetimeformat1;
        }
        
		if( isset( $this->match_datetime ) ) {
            $temp = clone $this->match_datetime;
            $result = $temp->setTimezone(TennisEvents::getTimeZone())->format($format);
        }
		
        $this->log->error_log("$loc: returning {$result}");
        return $result;
	}
	    
	/**
	 * Get the local Match date AND time in string format
	 */
	public function getMatchUTCDateTime_Str( int $formatNum=1) {
        $loc = __CLASS__ . ":" . __FUNCTION__;
        $this->log->error_log( $this->match_datetime, $loc);

        $result = '';
        $format = self::$outdatetimeformat1;
        switch($formatNum) {
            case 1:
                $format = self::$outdatetimeformat1;
                break;
            case 2:
                $format = self::$outdatetimeformat2;
                break;
            default:
                $format = self::$outdatetimeformat1;
        }
        
		if( isset( $this->match_datetime ) ) {
            $result = $this->match_datetime->format($format);
        }
		
        $this->log->error_log("$loc: returning {$result}");
        return $result;
	}
	
	/**
	 * Get the local Match date in ISO 8601 format
	 */
	public function getMatchDate_ISO() {
		if( !isset( $this->match_datetime ) ) return '';
		else {
            $temp = clone $this->match_datetime;
            return $temp->setTimezone(TennisEvents::getTimeZone())->format(\DateTime::ISO8601 );
        }
	}
    
	/**
	 * Get the UTC Match date in ISO 8601 format
	 */
	public function getUTCMatchDate_ISO() {
		if( !isset( $this->match_datetime ) ) return '';
		else return $this->match_datetime->format(\DateTime::ISO8601 );
	}

    /**
     * Set the time of the match
     * @param string $time is a string in local HH:mm or hh:mm am/pm format
     * @return bool true if successful; false otherwise
     */
    public function setMatchTime_Str( string $time ) {
        $loc = __CLASS__ . ":" . __FUNCTION__;
        $this->log->error_log("{$loc}('{$time}')");
        $tz = TennisEvents::getTimeZone();
        $tzs = wp_timezone_string();
        $this->log->error_log("$loc: tz name={$tz->getName()}");
        $this->log->error_log($tz, "$loc: tz...");


		$result = false;
        if( is_null( $time ) || empty( $time ) ) return $result;
        
        $intimeformat1 = "G:i";
        $intimeformat2 = "g:i a";

        $test = \DateTime::createFromFormat( "G:i", $time, $tz );		
        if(false === $test) $test = \DateTime::createFromFormat( "H:i", $time, $tz );
        if(false === $test) $test = \DateTime::createFromFormat( "g:i a", $time, $tz );
        if(false === $test) $test = \DateTime::createFromFormat( "h:i a", $time, $tz );

		$last = \DateTime::getLastErrors();
		if($last['error_count'] > 0) {
			$arr = $last['errors'];
			$mess = $this->toString() . ':';
			foreach($arr as $err) {
				$mess .= $err.':';
			}
			throw new InvalidMatchException( $mess );
		}
		elseif($test instanceof \DateTime) {
            $temp = null;
            if( isset($this->match_datetime) ) {
                $temp = $this->match_datetime;
                $temp->setTimezone($tz);
            }
            else {
                $temp = new \DateTime( 'now', $tz );
            }
            //$this->log->error_log($test, "$loc: test ...");
            $hours = (int)$test->format('H');
            $seconds = (int)$test->format('i');
            $temp->setTime($hours,$seconds);
            //$this->log->error_log($temp, "$loc: temp ...");
            $this->match_datetime = $temp;
            $this->match_datetime->setTimezone( new \DateTimeZone('UTC') );
            //$this->log->error_log($this->match_datetime, "$loc: match_datetime ...");
			$result = $this->setDirty();
		}
        return $result;
    }

    /**
     * Set the time of the match in UTC hours and minutes
     * @param int $hour UTC hour
     * @param int $minutes UTC minutes
     * @return bool true if successful; false otherwise
     */
    private function setMatchTime( int $hour, int $minutes ) {
        $loc = __CLASS__ . ":" . __FUNCTION__;
        $this->log->error_log("$loc($hour,$minutes)");

        if( !isset( $this->match_datetime ) ) {
            $this->match_datetime = new \DateTime('now', new \DateTimeZone('UTC'));
        }
        $this->match_datetime->setTime( $hour, $minutes );
        
        return $this->setDirty();
    }

    /**
     * Get the local match start time
     * @param int $formatNum Determines which output format to use
     * @return string local match start time as a string
     */
    public function getMatchTime_Str( int $formatNum = 1) {
        $loc = __CLASS__ . ":" . __FUNCTION__;
        $this->log->error_log("$loc: '{$this->title()}'");

        $format = self::$outtimeformat1;
        switch($formatNum) {
            case 1:
                $format = self::$outtimeformat1;
                break;
            case 2:
                $format = self::$outtimeformat2;
                break;
            default:
                $format = self::$outtimeformat1;
        }
        
        $result = '';
        if( isset( $this->match_datetime ) ) {
            $temp = clone $this->match_datetime;
            $result = $temp->setTimezone(TennisEvents::getTimeZone())->format( $format );
        }

        return $result;
    }
    
    /**
     * Get the match start time in UTC time zone
     * @param int $formatNum Determines which output format to use
     * @return string UTC match start time as a string
     */
    public function getUTCMatchTime_Str( int $formatNum = 1) {
        $loc = __CLASS__ . ":" . __FUNCTION__;
        $this->log->error_log("$loc: '{$this->title()}'");

        $format = self::$outtimeformat1;
        switch($formatNum) {
            case 1:
                $format = self::$outtimeformat1;
                break;
            case 2:
                $format = self::$outtimeformat2;
                break;
            default:
                $format = self::$outtimeformat1;
        }
        
        $result = '';
        if( isset( $this->match_datetime ) ) {
            $result = $this->match_datetime->format( $format );
        }

        $this->log->error_log("$loc: returning '{$result}'");
        return $result;
    }

    public function setIsBye( bool $by = false ) {
        $this->is_bye = $by;
        return $this->setDirty();
    }

    public function isBye() {
        return $this->is_bye;
    }

    public function isWaiting() {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        $result = false;
        $home = $this->getHomeEntrant();
        $visitor = $this->getVisitorEntrant();
        if( (!isset( $home ) || !isset( $visitor ))  && !$this->isBye() ) $result = true;
        $this->log->error_log(sprintf( "%s(%s) -> isset home=%d; isset visitor=%d; is bye=%d; is waiting=%d"
                                     ,$loc, $this->title(), isset( $home ), isset( $visitor ), $this->isBye(), $result ) );
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

    /**
     * Get the match's comments
     */
    public function getComments():string {
        return isset( $this->comments ) ? $this->comments : '';
    }

    public function getEarlyEnd() : int {
        foreach( $this->getSets() as $set ) {
            if( $set->earlyEnd() > 0 ) {
                return $set->earlyEnd();
            }
        }
        return 0;
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
        $this->log->error_log( sprintf( "%s(%s) -> starting", $loc, $this->toString() ) );

        $this->isValid();

        foreach( $this->getSets() as $set ) {
            if( $set->getSetNumber() === $setnum ) {
                $found = true;
                $set->setHomeScore( $home_wins, $hometb, $hometies );
                $set->setVisitorScore( $visitor_wins, $visitortb, $visitorties );
                $set->isValid();
                $result = $this->setDirty();

                $this->log->error_log( sprintf( "%s(%s) -> modified scores for %s", $loc, $this->toString(), $set->toString() ) );
            }
        }
    
        if( !$found ) {
            $set = new Set( $this, $setnum );
            if( $set->setSetNumber( $setnum ) ) {
                $set->setHomeScore( $home_wins, $hometb, $hometies );
                $set->setVisitorScore( $visitor_wins, $visitortb, $visitorties );
                $set->setMatch( $this );
                $set->isValid();
                $this->sets[] = $set;
                $result = $this->setDirty();

                $this->log->error_log( sprintf( "%s(%s) -> set added %s with scores", $loc, $this->toString(), $set->toString() ) );
            }
        }
        
        $this->log->error_log( sprintf( "%s(%s) -> returning with result=%d", $loc, $this->toString(), $result ) );

        return $result;
    }
    
    /**
     * Get the Sets for this Match
	 * @param $force When set to true will force loading of Sets from db
	 *               This will cause unsaved Sets to be lost.
     */
    public function getSets( $force = false ) {
        //var_dump(debug_backtrace());
        if( !isset( $this->sets ) || $force ) {
            $this->fetchSets();
            foreach( $this->sets as $set ) {
                $set->setMatch( $this );
            }
        }
        if(usort( $this->sets, array( __CLASS__, 'sortBySetNumberAsc' ) ) ) return $this->sets;
        throw new InvalidMatchException("Could not sort matches sets!");
    }

    /**
     * Remove all sets from this match
     */
    public function removeSets() {
        foreach( $this->getSets() as $set ) {
            $this->removeSet( $set->getSetNumber() );
            $this->setDirty();
        }
        $this->sets = null;
    }

    /**
     * Get a specific numbered Set for this match
     */
    public function getSet( int $setnum ) {
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
     * Add a Set to this Match
     * @param $set 
     */
    public function addSet( Set &$set ) {
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
                $set->setMatch( $this );
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
                    unset($this->sets[$i]);
                    self::deleteSet( $this->getBracket()->getEventId()
                                    , $this->getBracket()->getBracketNumber()
                                    , $this->getRoundNumber()
                                    , $this->getMatchNumber()
                                    , $s->getSetNumber() );
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
    public function setHomeEntrant( Entrant $h = null ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        // $tr = GW_Debug::get_debug_trace_Str(2);
        // $this->log->error_log("$loc:{$this->title()} $tr");

        $result = false;
        $existing = $this->getHomeEntrant();
        if( isset( $h ) ) {
            if( isset( $existing ) && ($existing->getName() === $h->getName()) ) {
                return $result;
            }
            elseif( isset( $existing ) ) {
                $mess = "$loc:{$this->toString()} Changing existing home entrant from '{$existing->getName()}' to '{$h->getName()}' should not happen?";
                $this->log->error_log( $mess );
                //throw new InvalidOperationException($mess);
            }
            $this->home = $h;
            $this->home_ID = $h->getPosition();
            $result = $this->setDirty();
        }
        else {
            if( isset($existing) ) {
                $bracket = $this->getBracket();
                EntrantMatchRelations::remove($bracket->getEventId()
                                            , $bracket->getBracketNumber()
                                            , $this->getRoundNumber()
                                            , $this->getMatchNumber()
                                            , $existing->getPosition() );
                $this->home = null;
                $this->home_ID = 0;
                $result = $this->setDirty();
            }
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
    public function setVisitorEntrant( Entrant $v = null ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        // $tr = GW_Debug::get_debug_trace_Str(2);
        // $this->log->error_log("$loc:{$this->title()} $tr");

        $result = false;
        $existing = $this->getVisitorEntrant();
        if( isset( $v ) ) {
            if( isset( $existing ) && ($existing->getName() === $v->getName()) ) {
                return $result;
            }
            elseif( isset( $existing ) ) {
                $mess = "$loc:{$this->toString()} Changing existing home entrant from '{$existing->getName()}' to '{$v->getName()}' should not happen?";
                $this->log->error_log( $mess );
                //throw new InvalidOperationException($mess);
            }
            $this->visitor = $v;
            $this->visitor_ID = $v->getPosition();
            $result = $this->setDirty();
        }
        else {
            if( isset($existing) ) {
                $bracket = $this->getBracket();
                EntrantMatchRelations::remove($bracket->getEventId()
                                            , $bracket->getBracketNumber()
                                            , $this->getRoundNumber()
                                            , $this->getMatchNumber()
                                            , $existing->getPosition() );
                $this->visitor = null;
                $this->visitor_ID = 0;
                $result = $this->setDirty();
            }
        }
        return $result;
    }

    /**
     * Get the visitor Entrant
     * @param $force If true then Entrants will be fetched from db.
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
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $mess = '';

        $evtId = isset( $this->event_ID ) ? $this->event_ID : '???';
        $mn = $this->match_num;
        $rn = $this->round_num;
        $home = $this->getHomeEntrant();
        $hname = isset($home) ? $home->getName() : 'unknown';
        $visitor = $this->getVisitorEntrant();
        $vname = isset($visitor) ? $visitor->getName() : 'unknown';
        $id = $this->title();
        $code = 0;

        if( !isset( $this->event_ID ) ) {
            $mess = __( "$id must have an event id." );
            $code = 500;
            $this->log->error_log( $mess );
        } 
        elseif ( !$this->isNew() &&  (!isset( $this->bracket_num ) || $this->bracket_num === 0 ) ) {
            $mess = __( "$id must have a bracket number." );
            $code = 510;
            $this->log->error_log( $mess );
        }
        elseif( !isset( $this->round_num ) ) {
            $mess = __( "$id must have a round number." );
            $code = 515;
            $this->log->error_log( $mess );
        }
        elseif( !$this->isNew() && ( !isset( $this->match_num )  || $this->match_num === 0 ) ) {
             $mess = __( "Existing match $id must have a match number." );
             $code = 520;
             $this->log->error_log( $mess );
        }
        elseif( !isset( $this->match_type ) ) {
            $mess = __( "$id must have a match type." );
            $code = 525;
            $this->log->error_log( $mess );
        }
        elseif( $this->round_num < 0 || $this->round_num > self::MAX_ROUNDS ) {
            $max = self::MAX_ROUNDS;
            $mess = __( "$id round number not between 1 and $max (inclusive)." );
            $code = 530;
            $this->log->error_log( $mess );
        }
        elseif( $this->round_num === 0 && ( !isset( $this->home ) || !isset( $this->visitor ) ) ) {
            $mess = __( "$id is a round 0 match and must have both home and visitor entrants." );
            $code = 535;
            $this->log->error_log( $mess );
        }
        elseif( $this->round_num === 1 && !isset( $this->home ) ) {
            $mess = __( "$id is a round 1 match and must have at least a home entrant." );
            $code = 540;
            $this->log->error_log( $mess );
        }

        if( false === MatchType::isValid( $this->match_type ) ) {
            $mess = __( "{$id} - Match Type is invalid: {$this->match_type}", TennisEvents::TEXT_DOMAIN );
            $code = 560;
            $this->log->error_log( $mess );
        }

        if( strlen( $mess ) > 0 ) throw new InvalidMatchException( $mess, $code );

        return true;
    }
    
    /**
     * Delete this match.
     * The Entrant relationships are deleted too.
     */
    public function delete() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $result = 0;
        $result = self::deleteMatch( $this->event_ID
                                    , $this->bracket_num
                                    , $this->round
                                    , $this->match_num );
        $this->log->error_log( sprintf( "%s(%s) -> deleted %d row(s)", $loc, $this->toString(), $result ) );

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
            $result =  Match::move( $this->event_ID, $this->bracket_num, $this->round_num, $from, $to );
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

        $sql = "SELECT IFNULL(MAX(match_num),0) FROM $table WHERE event_ID=%d AND bracket_num=%d AND round_num=%d;";
        if( $rn >= 0 ) {
            $safe = $wpdb->prepare( $sql, $this->event_ID, $this->bracket_num, $rn );
        }
        else {
            $safe = $wpdb->prepare( $sql, $this->event_ID, $this->bracket_num, $this->round_num );
        }
        $max = (int)$wpdb->get_var( $safe );

        return $max;
    }

    public function toString() {
        return sprintf( "M(%d,%d,%d,%d)", $this->event_ID, $this->bracket_num, $this->round_num, $this->match_num );
    }

    public function title() {
        $home = $this->getHomeEntrant();
        $visitor = $this->getVisitorEntrant();
        $hname = isset( $home ) ? $home->getName() : 'tba';
        $vname = isset( $visitor ) ? $visitor->getName() : 'tba';
        return sprintf( "%s:'%s' vs '%s'", $this->toString(), $hname, $vname );
    }

    public function toArray() {
 
        //$jsonSer = new CleanJsonSerializer();
        $home = $this->getHomeEntrant();
        $homeName = ' home tba';
        if( isset( $home ) ) {
            $homeName = $home->getName();
        }

        $visitor = $this->getVisitorEntrant();
        $visitorName = 'bye';
        if($this->getRoundNumber() > 1 ) $visitorName = 'visitor tba';
        if( isset( $visitor ) ) {
            $visitorName = $visitor->getName();
        }

        $arr = ["eventId"=>$this->event_ID
               ,"bracketNumber"=>$this->bracket_num
               ,"roundNumber"=>$this->getRoundNumber()
               ,"matchNumber"=>$this->getMatchNumber()
               ,"matchType" => $this->match_type
               ,"isBye"     => $this->isBye()
               ,"homeEntrant"=>$homeName
               ,"visitorEntrant"=>$visitorName
               ];

        $arrSets = array();
        foreach( $this->getSets() as $set ) {
            $arrSet = $set->toArray();
            $arrSets[] = $arrSet;
        }

        $arr["sets"] = $arrSets;

        return $arr;
    }
    
    /**
     * Helper to sort sets by ascending set number
     */
    private function sortBySetNumberAsc( $a, $b ) {
        if($a->getSetNumber() == $b->getSetNumber()) return 0; 
        return ($a->getSetNumber() > $b->getSetNumber()) ? 1 : -1;
    }

    /**
     * Fetch this match's bracket from the database
     */
    private function fetchBracket() {
        $this->bracket =  Bracket::get( $this->event_ID, $this->bracket_num );
    }

    /**
     * Fetch the zero, 1 or 2 Entrants from the database.
     */
    private function fetchEntrants() {
        $loc = __CLASS__ . '::' . __FUNCTION__;

        $contestants = Entrant::find( $this->event_ID, $this->bracket_num, $this->round_num, $this->match_num );
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
                throw new InvalidMatchException( sprintf( "Match %s has %d entrants.", $this->toString(), count( $contestants ) ) );
            break;
        }
        $this->log->error_log( sprintf( "%s(%s) has %d entrants.", $loc, $this->toString(), count( $contestants ) ) );
    }

	/**
	 * Fetch all Sets for this Match
	 */
    private function fetchSets() {
        //var_dump(debug_backtrace());
        $this->sets = Set::find( $this->event_ID, $this->bracket_num, $this->round_num, $this->match_num );
    }

	protected function create() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        global $wpdb;
        
        parent::create();

        $table = $wpdb->prefix . self::$tablename;

        $wpdb->query("LOCK TABLES $table LOW_PRIORITY WRITE;");

        if( isset( $this->match_num ) && $this->match_num > 0 ) {
            //If match_num has a value then use it
            $sql = "SELECT COUNT(*) FROM $table WHERE event_ID=%d AND bracket_num=%d AND round_num=%d AND match_num=%d;";
            $exists = (int) $wpdb->get_var( $wpdb->prepare( $sql,$this->event_ID, $this->bracket_num, $this->round_num, $this->match_num ), 0, 0 );
            
            //If this match arleady exists throw exception
            if( $exists > 0 ) {
                $wpdb->query( "UNLOCK TABLES;" );                
                $code = 570;
                throw new InvalidMatchException( sprintf("Cannot create '%s' because it already exists (%d)", $this->toString(), $exists ), $code );
            }
        }
        else {
            //If match_num is not provided, then use the next largest value from the db
            $sql = "SELECT IFNULL(MAX(match_num),0) FROM $table WHERE event_ID=%d AND bracket_num=%d AND round_num=%d;";
            $safe = $wpdb->prepare( $sql, $this->event_ID, $this->bracket_num, $this->round_num );
            $this->match_num = $wpdb->get_var( $safe ) + 1;
            $this->log->error_log( sprintf( "Match::create -> creating '%s' with match type = '%s'", $this->toString(), $this->match_type ) );
        }

        $values = array( 'event_ID'    => $this->event_ID
                        ,'bracket_num' => $this->bracket_num 
                        ,'round_num'   => $this->round_num
                        ,'match_num'   => $this->match_num
                        ,'match_type'  => $this->match_type
                        ,'match_date'  => $this->getMatchUTCDateTime_Str()
                        ,'is_bye'      => $this->is_bye ? 1 : 0
                        ,'next_round_num' => $this->next_round_num
                        ,'next_match_num' => $this->next_match_num
                        ,'comments'    => $this->comments );
        $formats_values = array( '%d', '%d', '%d', '%d', '%f', '%s', '%d', '%d', '%d', '%s' );
		$wpdb->insert( $wpdb->prefix . self::$tablename, $values, $formats_values );
        $result = $wpdb->rows_affected;
        $wpdb->query( "UNLOCK TABLES;" );
        $this->isnew = false;
		$this->isdirty = false;

        if($wpdb->last_error) {
            $this->log->error_log( sprintf("%s(%s) -> sql error: %s", $loc, $this->toString(), $wpdb->last_error )  );
        }

        $this->log->error_log( sprintf("%s(%s) -> %d rows affected.", $loc, $this->toString(), $result ) );

        if( isset( $this->sets ) ) {
            foreach($this->sets as &$set) {
                $result += $set->save();
            }
        }
        
        $result += isset( $this->home ) ? EntrantMatchRelations::add( $this->event_ID, $this->bracket_num, $this->getRoundNumber(), $this->getMatchNumber(), $this->getHomeEntrant()->getPosition() ): 0;
        $result += isset( $this->visitor ) ? EntrantMatchRelations::add( $this->event_ID, $this->bracket_num, $this->getRoundNumber(), $this->getMatchNumber(), $this->getVisitorEntrant()->getPosition(), 1 ) : 0;

		return $result;
	}

	protected function update() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        
		global $wpdb;
        parent::update();

        $values = array( 'match_type'  => $this->match_type
                        ,'match_date'  => $this->getMatchUTCDateTime_Str()
                        ,'is_bye'      => $this->is_bye ? 1 : 0
                        ,'next_round_num' => $this->next_round_num
                        ,'next_match_num' => $this->next_match_num
                        ,'comments'    => $this->comments );
		$formats_values = array( '%f', '%s', '%d', '%d', '%d', '%s' );
        $where          = array( 'event_ID'  => $this->event_ID
                               , 'bracket_num' => $this->bracket_num
                               , 'round_num' => $this->round_num
                               , 'match_num' => $this->match_num );
        $formats_where  = array( '%d', '%d', '%d', '%d' );
        $check = $wpdb->update( $wpdb->prefix . self::$tablename, $values, $where, $formats_values, $formats_where );
        
        $this->isdirty = FALSE;
        $result = $wpdb->rows_affected;

        $this->log->error_log( sprintf( "%s(%s) -> %d rows affected.", $loc, $this->toString(), $result ) );
        
        if( isset( $this->sets ) ) {
            foreach( $this->sets as &$set ) {
                $result += $set->save();
            }
        }

        $result += isset( $this->home ) ? EntrantMatchRelations::add( $this->getBracket()->getEvent()->getID(), $this->getBracket()->getBracketNumber(), $this->getRoundNumber(), $this->getMatchNumber(), $this->getHomeEntrant()->getPosition() ) : 0;
        $result += isset( $this->visitor ) ?  EntrantMatchRelations::add( $this->getBracket()->getEvent()->getID(), $this->getBracket()->getBracketNumber(), $this->getRoundNumber(), $this->getMatchNumber(), $this->getVisitorEntrant()->getPosition(), 1 ) : 0;
        
		return $result;
	}
    
    /**
     * Map incoming data to an instance of Match
     */
    protected static function mapData( $obj, $row ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        $mess = print_r($row, true);
        error_log("$loc: row ...");
        error_log($mess);

        parent::mapData( $obj, $row );
        $obj->event_ID     = (int) $row["event_ID"];
        $obj->bracket_num  = (int) $row["bracket_num"];
        $obj->round_num    = (int) $row["round_num"];
        $obj->match_num    = (int) $row["match_num"];
        $obj->match_type   = (float) $row["match_type"];
        
        if( !empty($row["match_date"]) && $row["match_date"] !== '0000-00-00 00:00:00') {
            $st = new \DateTime( $row["match_date"], new \DateTimeZone('UTC') );
            $mess = print_r($st,true);
            error_log("$loc: DateTime using match_date ...");
            error_log($mess);
            $obj->match_datetime = $st;
        }
        else {
            $obj->match_date = null;
        }

        $obj->is_bye       = $row["is_bye"] == 1 ? true : false;
        $obj->comments     = $row["comments"];
        $obj->next_round_num = (int) $row["next_round_num"];
        $obj->next_match_num = (int) $row["next_match_num"];
    }
    
    private function getIndex( $obj ) {
        return $obj->getPosition();
    }

    private function init() {
    }

} //end class