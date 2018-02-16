<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dir = plugin_dir_path( __DIR__ );
include_once($dir . '/gw-support.php' );

/**
* Match scoring:
* No ad
    * 'No advantage'. Scoring method created by Jimmy Van Alen. 
    * The first player or doubles team to win four points wins the game, regardless of whether the player or team is ahead by two points. 
    * When the game score reaches three points each, the receiver chooses which side of the court (advantage court or deuce court) the service is to be delivered
    *  on the seventh and game-deciding point. 
    * Utilized by World Team Tennis professional competition, ATP tours, WTA tours, ITF Pro Doubles and ITF Junior Doubles.
* Pro set
    * Instead of playing multiple sets, players may play one "pro set". A pro set is first to 8 (or 10) games by a margin of two games, instead of first to 6 games. 
    * A 12-point tie-break is usually played when the score is 8–8 (or 10–10). These are often played with no-ad scoring.
* Match tie-break
    * This is sometimes played instead of a third set. A match tie-break (also called super tie-break) is played like a regular tie-break, 
    * but the winner must win ten points instead of seven. Match tie-breaks are used in the Hopman Cup, Grand Slams (excluding Wimbledon) and the Olympic Games for mixed doubles; 
    * on the ATP (since 2006), WTA (since 2007) and ITF (excluding four Grand Slam tournaments and the Davis Cup) tours for doubles and as a player's choice in USTA league play.
* Fast4
    * Fast4 is a shortened format that offers a "fast" alternative, with four points, four games and four rules: 
    * there are no advantage scores, lets are played, tie-breakers apply at three games all and the first to four games wins the set.
 */
class MatchScoring {
    public const NO_AD           = "no ad";
    public const PRO_SET         = "pro set";
    public const MATCH_TIE_BREAK = "match tie break";
    public const FAST4           = "fast4";
}

class MatchType {
    public const MENS_SINGLES   = 1.1;
    public const WOMENS_SINGLES = 1.2;
    public const MENS_DOUBLES   = 2.1;
    public const WOMENS_DOUBLES = 2.2;
    public const MIXED_DOUBLES  = 2.3;
}

// require_once('class-abstractdata.php');
// require_once('class-entrant.php');
// require_once('class-game.php');

/** 
 * Data and functions for Tennis Event Match(es)
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

    //Match needs 2 entrants: home and visitor
    private $home_ID;
    private $home;
    private $visitor_ID;
    private $visitor;

    private $comments;
    
    //Games
    private $games;
    private $gamesToBeDeleted = array();
    
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
    public static function find(...$fk_criteria) {
		global $wpdb;
        $table = $wpdb->prefix . self::$tablename;
        $col = array();
        $eventID = 0;
        $roundnum = 0;
        
        if(isset($fk_criteria[0]) && is_array($fk_criteria[0])) $fk_criteria = $fk_criteria[0];
        
        if(array_key_exists('event_ID',$fk_criteria) && array_key_exists('round_num',$fk_criteria)) {
            $eventID = $fk_criteria["event_ID"];
            $roundnum = $fk_criteria["round_num"];
        }

        else {
            list($eventID,$roundnum) = $fk_criteria;
        }
        
        $sql = "SELECT event_ID,round_num,match_num,match_type,match_date,match_time,is_bye,comments 
                FROM $table WHERE event_ID = %d AND round_ID = %d;";
        if(!isset($roundnum)) {
            $sql = "SELECT event_ID,round_num,match_num,match_type,match_date,match_time,is_bye,comments 
                    FROM $table WHERE event_ID = %d;";
        }
		$safe = $wpdb->prepare($sql,$eventID,$roundnum);
		$rows = $wpdb->get_results($safe, ARRAY_A);

		foreach($rows as $row) {
            $obj = isset($roundnum) ? new Match($eventID, $roundnum) : new Match($eventID);
            self::mapData($obj,$row);
			$col[] = $obj;
		}
		return $col;
    }

	/**
	 * Get instance of a Match using primary key: event_id, round_num, match_num
	 */
    static public function get(int ...$pks) {
		global $wpdb;
        $table = $wpdb->prefix . self::$tablename;
        $obj = NULL;
        if(count($pks) !== 3) return $obj;
        
        $sql = "SELECT event_ID,round_num,match_num,match_type,match_date,match_time 
                FROM $table WHERE event_ID=%d AND round_num=%d AND match_num=%d;";
		$safe = $wpdb->prepare($sql,$pks);
		$rows = $wpdb->get_results($safe, ARRAY_A);

		error_log("Match::get(id) $wpdb->num_rows rows returned.");

		if(count($rows) === 1) {
			$obj = new Match(...$pks);
            self::mapData($obj,$rows[0]);
		}
		return $obj;
	}

	/*************** Instance Methods ****************/
	public function __construct(int $eventId, int $round=null, int $match=null) {
        $this->isnew = TRUE;
        $this->event_ID = $eventId;
        $this->getEvent(true);
        $this->round_num = $round;
        $this->match_num = $match;
        $this->init();
    }

    public function __destruct() {
        $this->event = null;
        foreach($this->getGames() as $game) {
            $game = null;
        }
    }

    public function setDirty() {
        $this->getEvent()->setDirty();
        return parent::setDirty();
    }
    
    /**
     * Assign this Match to an Event
     */
    public function setEvent( Event $event ) {
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

    public function setRoundNumber( int $rn ) {
        $result = false;
        if($rn > -1 & $rn <= self::MAX_ROUNDS) {
            $this->round_num = $rn;
            $result = $this->setDirty();
        }
        return $result;
    }

    public function getRoundNumber():int {
        return $this->round_num;
    }

    public function setMatchNumber( int $mn ) {
        $result = false;
        if($mn > 0) {
            $this->match_num = $mn;
            $result = $this->setDirty();
        }
        return $result;
    }

    public function getMatchNumber():int {
        return $this->match_num;
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
    
    public function getMatchType() {
        return $this->match_type;
    }

    /**
     * Set the date of the match
     * @param $date is a string in Y-m-d format
     */
    public function setMatchDate_Str( string $date ) {
		$result = false;
		$test = DateTime::createFromFormat(self::$indateformat,$end);
		if(false === $test) $test = DateTime::createFromFormat('!Y-m-d',$end);
		if(false === $test) $test = DateTime::createFromFormat('!j/n/Y',$end);
		if(false === $test) $test = DateTime::createFromFormat('!d/m/Y',$end);
		if(false === $test) $test = DateTime::createFromFormat('!d-m-Y',$end);
		$last = DateTIme::getLastErrors();
		if($last['error_count'] > 0) {
			$arr = $last['errors'];
			$mess = '';
			foreach($arr as $err) {
				$mess .= $err.':';
			}
			throw new InvalidMatchException($mess);
		}
		elseif($test instanceof DateTime) {
			$this->match_date = $test;
			$result = $this->setDirty();
		}

        return $result;
    }

    public function setMatchDate(int $year, int $month, int $day) {
        if(!isset($this->match_date)) $this->match_date = new DateTime();
        $this->match_date->setDate($year,$month,$day);
        $this->match_date->setTime(0,0,0);
    }

	/**
	 * Get the Match date in string format
	 */
	public function getMatchDate_Str() {
		if(!isset($this->match_date)) return null;
		else return $this->match_date->format(self::$outdateformat);
	}
	
	/**
	 * Get the Match date in ISO 8601 format
	 */
	public function getMatchDate_ISO() {
		if(!isset($this->match_date)) return null;
		else return $this->match_date->format(DateTime::ISO8601);
	}

    /**
     * Set the time of the match
     * @param $time is a string in hh-mm-ss format
     */
    public function setMatchTime_Str(string $time) {
		$result = false;
		$test = DateTime::createFromFormat(self::$intimeformat,$end);
		$last = DateTIme::getLastErrors();
		if($last['error_count'] > 0) {
			$arr = $last['errors'];
			$mess = '';
			foreach($arr as $err) {
				$mess .= $err.':';
			}
			throw new InvalidMatchException($mess);
		}
		elseif($test instanceof DateTime) {
			$this->match_time = $test;
			$result = $this->setDirty();
		}

        return $result;
    }

    public function setMatchTime(int $hour, int $minutes) {
        if(!isset($this->match_time)) {
            $this->match_time = new DateTime();
        }
        $this->match_time->setTime($hour,$minutes);
        $this->match_time->setDate(0,1,1);
        
        return $this->setDirty();
    }

    public function getMatchTime_Str() {
        if(!isset($this->match_time)) return null;
        else return $this->match_time->format(self::$outtimeformat);
    }

    public function getMatchTime() {
        return $this->match_time;
    }

    public function setIsBye(bool $by=false) {
        $this->is_by = $by;
        return $this->setDirty();
    }

    public function isBye() {
        return $this->is_by;
    }
    
    /**
     * Set this Match's comments
     */
    public function setComments(string $comment) {
        $this->comments = $comment;
        $result = $this->setDirty();
        return $result;
    }

    public function getComments():string {
        return $this->comments;
    }

    /**
     * Get the Games for this Match
     */
    public function getGames() {
        //var_dump(debug_backtrace());
        if(!isset($this->games)) $this->fetchGames();
        return $this->games;
    }
    
    /**
     * Set a score for a given Set of tennis.
     * Updates a Game if already a child of this Match
     * or creates a new Game and adds it to the Match's array of Games
     * 
     * @param int $set Identifies the set by number
     * @param int $home_wins is the number of wins for the home entrant
     * @param int @visitor_wins is the number of wins for the visitor entrant
     * @throws nothing
     * @return true if successful false otherwise
     */
    public function setScore( int $set, int $home_wins=0, int $visitor_wins=0 ) {
        $result = false;
        $found = false;

        foreach($this->getGames() as $game) {
            if($game->getSetNumber() === $set) {
                $found = true;
                $game->setHomeScore($home_wins);
                $game->setVisitorScore($visitor_wins);
                $result = $this->setDirty();
            }
        }
        if(!$found) {
            $game = new Game( $this->event_ID, $this->round_num, $this->match_num );
            if($game->setSetNumber($set)) {
                $game->setHomeScore($home_wins);
                $game->setVisitorScore($visitor_wins);
                $this->games[] = $game;
                $result = $this->setDirty();
                error_log("Match::setScore: added game with params:$this->event_ID, $this->round_num, $this->match_num in set $set");
            }
        }
        
        return $result;
    }

    /**
     * Add a Game to this Match
     * @param $game Game contains the games won/lost for the Entrants
     * NOTE that games are the games won/lost for a whole set.
     */
    public function addGame( Game $game ) {
        $result = false;
        if(isset($game)) {
            $found = false;
            foreach($this->getGames() as $gm) {
                if($game->getSetNumber() === $gm->getSetNumber()) {
                    $found = true;
                    break;
                }
            }
            if(!$found) {
                $this->games[] = $game;
                $result = $this->setDirty();
            }
        }
        return $result;
    }
    
    /**
     * Remove a Game from this Match
     */
    public function removeGame( Game $game ) {
		$result = false;
		if(isset($game)) {
			$i=0;
			foreach($this->getGames() as $gm) {
				if($game->getSetNumber() == $gm->getSetNumber()) {
					$this->gamesToBeDeleted[] = clone $game;
                    unset($this->games[$i]);
					$result =  $this->setDirty();
				}
				$i++;
			}
		}
		return $result;
    }

    /**
     * Set the Home opponent for this match
     */
    public function setHomeEntrant( Entrant $h = null ) {
        $result = false;
        if(isset($h)) {
            $this->home = $h;
            $this->home_ID = $h->getID();
            $result = $this->setDirty();
        }
        else {
            $this->home = null;
            $this->home_ID = null;
        }
        return $result;
    }

    public function getHomeEntrant():Entrant {
        if(!isset($this->home)) $this->fetchEntrants();
        return $this->home;
    }
    
    /**
     * Set the Visitor opponent for this match
     */
    public function setVisitorEntrant( Entrant $v ) {
        $result = false;
        if(isset($v)) {
            $this->visitor = $v;
            $this->visitor_ID = $v->getID();
            $result = $this->setDirty();
        }
        else {
            $this->visitor = null;
            $this->visitor_ID = null;
        }
        return $result;
    }

    /**
     * Get the visitor Entrant
     */
    public function getVisitorEntrant():Entrant {
        if(!isset($this->visitor)) $this->fetchEntrants();
        return $this->visitor;
    }
    
    /**
     * Check if match is valid (ready to go)
     * @throws InvalidMatchException
     */
    public function isValid() {
        $mess = '';
        $this->getHomeEntrant();
        $this->getVisitorEntrant();
        if(!isset($this->event_ID)) $mess = __('Match must have an event id.');
        if(!isset($this->round_num)) $mess = __('Match must have a round number.');
        if(!$this->isNew() && (!isset($this->match_num) || $this->match_num === 0)) $mess=__('Existing match must have a match number.');
        if(!isset($this->home_ID)) $mess = __('Match must have a home entrant id.');
        if(!isset($this->visitor_ID) && !$this->isBye()) $mess = __('Match is not a bye so must have a visitor entrant id.');
        if(!isset($this->match_type)) $mess = __('Match must have a match type');
        switch($this->match_type) {
            case MatchType::MENS_SINGLES:
            case MatchType::WOMENS_SINGLES:
            case MatchType::MENS_DOUBLES:
            case MatchType::WOMENS_DOUBLES:
            case MatchType::MIXED_DOUBLES:
                break;
            default:
            $mess = __("Match Type is invalid: $this->match_type");
        }

        if(strlen($mess) > 0) throw new InvalidMatchException($mess);

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
        $where = array('event_ID' => $this->event_ID
                ,'round_num' => $this->round_num
                ,'match_num' => $this->match_num);
        $formats_where = array('%d','%d','%d');

        $wpdb->delete($table,$where,$formats_where);
        $result += $wpdb->rows_affected;

        error_log("Match.delete: deleted $result rows");
        return $result;
    }

    /**
     * Fetch the 1,2 or zero Entrants from the database.
     * @param $force IF true then Entrants will be fetched and overwrite existing.
     */
    private function fetchEntrants($force=false) {
        if(isset($this->home) && isset($this->visitor) && !force) return;
        
        $contestants = Entrant::find($this->event_ID, $this->round_num, $this->match_num);
        switch(count($contestants)) {
            case 1:
                $this->home = $contestants[0];
                $this->home_ID = $this->home->getID();
                $this->visitor = NULL;
                $this->visitor_ID = NULL;
                break;
            case 2:
                $this->home = $contestants[0];
                $this->home_ID = $this->home->getID();
                $this->visitor = $contestants[1];
                $this->visitor_ID = $this->visitor->getID();
                break;
            default:
                $this->home = NULL;
                $this->home_ID = NULL;
                $this->visitor = NULL;
                $this->visitor_ID = NULL;
            break;
        }
    }

	/**
	 * Fetch all Games for this Match
	 * Root-level Events can be associated with one or more clubs
	 * @param $force When set to true will force loading of Games from db
	 *               This will cause unsaved games to be lost.
	 */
    private function fetchGames($force=false) {
        //var_dump(debug_backtrace());
        if(!isset($this->games) || $force) {
            $this->games = Game::find($this->event_ID,$this->round_num,$this->match_num);
        }
    }

	protected function create() {
        global $wpdb;
        
        parent::create();

        $table = $wpdb->prefix . self::$tablename;

        $wpdb->query("LOCK TABLES $table LOW_PRIORITY WRITE;");
        
		$sql = "SELECT IFNULL(MAX(round_num),0) FROM $table WHERE event_ID=%d;";
        $safe = $wpdb->prepare($sql,$this->event_ID,$this->round_num);
        $nextRound = $wpdb->get_var($safe) + 1;
        if($nextRound > self::MAX_ROUNDS) {
            $this->round_num = self::MAX_ROUNDS;
            $this->isnew = FALSE;
            error_log("Match::create: Round number limited to '$this->round_num'");
        }
        

		$sql = "SELECT IFNULL(MAX(match_num),0) FROM $table WHERE event_ID=%d AND round_num=%d;";
        $safe = $wpdb->prepare($sql,$this->event_ID,$this->round_num);
        $this->match_num = $wpdb->get_var($safe) + 1;
        error_log("Match::create: match number assigned is '$this->match_num'");

        error_log("Match::create: match type is '$this->match_type'");

        $values = array( 'event_ID'    => $this->event_ID
                        ,'round_num'   => $this->round_num
                        ,'match_num'   => $this->match_num
                        ,'match_type'  => $this->match_type
                        ,'match_date'  => $this->getMatchDate_Str()
                        ,'match_time'  => $this->getMatchTime_Str()
                        ,'is_bye'      => $this->is_bye ? 1 : 0
                        ,'comments'    => $this->comments);
        $formats_values = array('%d','%d','%d','%f','%s','%s','%d','%s');
		$wpdb->insert($wpdb->prefix . self::$tablename, $values, $formats_values);
        $this->isnew = FALSE;
		$this->isdirty = FALSE;
        $result = $wpdb->rows_affected;

        if($wpdb->last_error) {
            error_log("Match::create: sql error: $wpdb->last_error");
        }
        
		$wpdb->query("UNLOCK TABLES;");

        error_log("Match::create: $result rows affected.");
        
        foreach($this->getGames() as $game) {
            $result += $game->save();
        }
        
        foreach($this->gamesToBeDeleted as $game) {
            $game->delete();
        }
        
        $result += EntrantMatchRelations::add($this->event_ID,$this->getRoundNumber(),$this->getMatchNumber(),$this->getHomeEntrant()->getPosition());
        $result += EntrantMatchRelations::add($this->event_ID,$this->getRoundNumber(),$this->getMatchNumber(),$this->getVisitorEntrant()->getPosition());

		return $result;
	}

	protected function update() {
		global $wpdb;

        parent::update();

        $md = $this->getMatchDate_Str();
        $mt = $this->getMatchTime_Str();
        error_log("Match::update values=<$this->match_type,'$md','$mt','$this->is_bye','$this->comments'>");

        $values = array( 'match_type'  => $this->match_type
                        ,'match_date'  => $this->getMatchDate_Str()
                        ,'match_time'  => $this->getMatchTime_Str()
                        ,'is_bye'      => $this->is_bye ? 1 : 0
                        ,'comments'    => $this->comments );
		$formats_values = array( '%f', '%s', '%s', '%d', '%s' );
        $where          = array( 'event_ID'  => $this->event_ID
                                ,'round_num' => $this->round_num
                                ,'match_num' => $this->match_num );
        $formats_where  = array( '%d', '%d', '%d' );

        error_log("Match::update: where=$this->event_ID, $this->round_num, $this->match_num");
        $check = $wpdb->update( $wpdb->prefix . self::$tablename, $values, $where, $formats_values, $formats_where );
        
        $this->isdirty = FALSE;
        $result = $wpdb->rows_affected;

        error_log( "Match::update $result rows affected." );
        
        foreach($this->getGames() as $game) {
            $result += $game->save();
        }
        
        foreach($this->gamesToBeDeleted as $game) {
            $game->delete();
        }

        $result += EntrantMatchRelations::add($this->event_ID,$this->getRoundNumber(),$this->getMatchNumber(),$this->getHomeEntrant()->getPosition());
        $result += EntrantMatchRelations::add($this->event_ID,$this->getRoundNumber(),$this->getMatchNumber(),$this->getVisitorEntrant()->getPosition());
        
		return $result;
	}
    
    /**
     * Map incoming data to an instance of Match
     */
    protected static function mapData($obj,$row) {
        parent::mapData($obj,$row);
        $obj->event_ID   = $row["event_ID"];
        $obj->round_num  = $row["round_num"];
        $obj->match_num  = $row["match_num"];
        $obj->match_type = $row["match_type"];
		$obj->match_date = isset($row["match_date"]) ? new DateTime($row["match_date"]) : null;
		$obj->match_time = isset($row["match_time"]) ?  new DateTime($row["match_time"]) : null;
        $obj->is_bye     = $row["is_bye"] == 1 ? true : false;
        $obj->comments   = $row["comments"];
    }
    
    private function getIndex($obj) {
        return $obj->getPosition();
    }

    private function init() {
        // $this->event_ID   = NULL;
        // $this->round_num  = NULL;
        // $this->match_num  = NULL;
        // $this->match_type = NULL;
        // $this->match_date = NULL;
        // $this->match_time = NULL;
        // $this->home_ID    = NULL;
        // $this->home       = NULL;
        // $this->visitor_ID = NULL;
        // $this->visitor    = NULL;
        // $this->games      = NULL;
    }

} //end class