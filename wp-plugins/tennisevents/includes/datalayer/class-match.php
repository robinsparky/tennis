<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// require_once('class-abstractdata.php');
// require_once('class-entrant.php');
// require_once('class-game.php');

/** 
 * Data and functions for Tennis Event Match(es)
     * Match types:
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
 * @class  Match
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
*/
class Match extends AbstractData
{ 
    private static $tablename = 'tennis_match';
    
    private $match_type; //No ad, Pro set, match tie-break (or super-tie break), fast4, canadian doubles, australian doubles, jordache
    private $event;

    //Primary key---
    private $event_ID;
    private $round_num;
    private $match_num;
    //---

    private $match_date;
    private $match_time;

    //Match needs 2 entrants: home and visitor
    private $home_ID;
    private $home;
    private $visitor_ID;
    private $visitor;
    
    //Games
    private $games;
    private $gamesToBeDeleted = array();
    
    /**
     * Search for Matches that have a name 'like' the provided criteria
     */
    public static function search($criteria) {
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
        if( count($fk_criteria) === 2 ) {
            $eventID = $fk_criteria[0];
            $roundID = $fk_criteria[1];
        } 
        else {
            return $col;
        }
        
        $sql = "select event_ID,round_num,match_num,match_type,match_date,match_time 
                from $table where event_ID = %d and round_ID = %d";
		$safe = $wpdb->prepare($sql,$eventID,$roundnum);
		$rows = $wpdb->get_results($safe, ARRAY_A);

		foreach($rows as $row) {
            $obj = new Match($eventID, $roundnum);
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
        
        $sql = "select event_ID,round_num,match_num,match_type,match_date,match_time 
                from $table where event_ID=%d and round_num=%d and match_num=%d;";
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
	public function __construct(Event $event, int $round, int $match=0) {
        $this->isnew = TRUE;
        $this->setEvent($event);
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
    
    public function setIdentifiers(...$pks) {
        if(!$this->isNew()) return false;

        if(2 === count($pks)) {
            $this->event_ID  = $pks[0];
            $this->round_num = $pks[1];
        }
        elseif(3 === count($pks)) {
            $this->event_ID  = $pks[0];
            $this->round_num = $pks[1];
            $this->match_num = $pks[2];
        }
        return true;
    }

    public function getIdentifiers():array {
        $ids = array();
        $ids[] = $this->event_ID;
        $ids[] = $this->round_num;
        $ids[] = $this->match_num;

        return $ids;
    }
    
    /**
     * Assign this Match to an Event
     */
    public function setEvent(Event $event) {
        $result = false;
        if(!$event->isParentEvent()) {
            $this->event = $event;
            $this->event_ID = $event->ID;
            $result = $this->isdirty = TRUE;
        }
        return $result;
    }

    /**
     * Remove a Game from this Match
     */
    public function removeGame(Event $game) {
		$result = false;
		if(isset($game)) {
			$i=0;
			foreach($this->getGames() as $gm) {
				if($game == $cm) {
					$this->gamesToBeDeleted[] = $game->getID();
					unset($this->games[$i]);
					$result = $this->isdirty = true;
				}
				$i++;
			}
		}
		return $result;
    }

    /**
     * Get this Match's Event.
     */
    public function getEvent():Event {
        return $this->event;
    }

    public function getRoundNumber():int {
        return $this->round_num;
    }

    public function getMatchNumber():int {
        return $this->match_num;
    }

    public function setMatchType(String $type) {
        $this->match_type = $type;
        $result = $this->isdirty = true;

        return $result;
    }

    public function getMatchType() {
        return $this->match_type;
    }

    /**
     * Set the date of the match
     * @param $date is a string in Y-m=d format
     */
    public function setMatchDate(string $date) {
        $mdt = strtotime($date);
        $this->match_date = $mdt;
        $result = $this->isdirty = true;

        return $result;
    }

    public function getMatchDate():string {
        return date("F d, Y",$this->match_date);
    }

    /**
     * Set the time of the match
     * @param $time is a string in hh-mm-ss format
     */
    public function setMatchTime(string $time) {
        $mt = strtotime($time);
        $this->match_time = $mt;
        $result = $this->isdirty = true;

        return $result;
    }

    public function getMatchTime():string {
        return date("h:i:s",$this->match_time);
    }

    
    /**
     * Get the Games for this Match
     */
    public function getGames() {
        if(!isset($this->games)) $this->games = array();
        return $this->games;
    }

    /**
     * Add a Game to this Match
     */
    public function addGame(Game $game) {
        $result = false;
        if(isset($game)) {
            $found = false;
            foreach($this->getGames() as $gm) {
                if($game == $gm) {
                    $found = true;
                    break;
                }
            }
            if(!$found) {
                $this->games[] = $game;
                $result = $this->isdirty = true;
            }
        }
        return $result;
    }

    /**
     * Set the Home opponent for this match
     */
    public function setHomeEntrant(Entrant $h=null) {
        $result = false;
        if(isset($h)) {
            $this->home = $h;
            $this->home_ID = $h->getID();
            $result = $this->isdirty = true;
        }
        else {
            $this->home = null;
            $this->home_ID = null;
        }
        return $result;
    }

    public function getHomeEntrant():Entrant {
        return $this->home;
    }
    
    /**
     * Set the Visitor opponent for this match
     */
    public function setVisitorEntrant(Entrant $v) {
        $result = false;
        if(isset($v)) {
            $this->visitor = $v;
            $this->visitor_ID = $v->getID();
            $result = $this->isdirty = TRUE;
        }
        else {
            $this->visitor = null;
            $this->visitor_ID = null;
        }
        return $result;
    }

    public function getVisitorEntrant():Entrant {
        return $this->visitor;
    }

    /**
     * Set a score for a given Set of tennis.
     * Updates a Game if already a child of this Match
     * or creates a new Game and adds it to the Match's array of Games
     * 
     * @param $set Identifies the set by number
     * @param int $home_wins is the number of wins for the home entrant
     * @param int @visitor_wins is the number of wins for the visitor entrant
     * @throws nothing
     * @return true if successful false otherwise
     */
    public function setScore(int $set,int $home_wins=0,int $visitor_wins=0) {
        $result = false;

        $setfound = FALSE;
        foreach($this->getGames() as $game) {
            if($game->getSetNumber() === $set) {
                $setfound = TRUE;
                $game->setHomeScore($home_wins);
                $game->setVisitorScore($visitor_wins);
                break;
            }
        }
        if(!$setfound) {
            $game = new Game($this->event_ID,$this->round_num,$this->match_num);
            if($game->setSetNumber($set)) {
                $game->setHomeScore($home_wins);
                $game->setVisitorScore($visitor_wins);
                $this->games[] = $game;
            }
        }
        $result = $this->isdirty = true;

        return $result;
    }

	/**
	 * Get all my children!
	 * 1. Entrants
     * 2. Games
	 */
    public function getChildren($force=FALSE) {
        $this->fetchEntrants($force);
        $this->fetchGames($force);
    }

    private function fetchEntrants($force) {
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
                $this->visitor = $contestant[1];
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

    private function fetchGames($force) {
        if(count($this->games) === 0 || $force) $this->games = Game::find($this->getIdentifiers());
    }
    
    public function isValid() {
        $isvalid = TRUE;
        if(!isset($this->event_ID)) $isvalid = FALSE;
        if(!isset($this->round_num)) $isvalid = FALSE;
        if(!$this->isNew() && (!isset($this->match_num) || $this->match_num === 0)) $isvalid = FALSE;
        if(!isset($this->home_ID)) $isvalid = FALSE;
        if(!isset($this->visitor_ID)) $isvalid = FALSE;
        if(!isset($this->match_type)) $isvalid = FALSE;

        return $isvalid;
    }

	protected function create() {
        global $wpdb;
        
        parent::create();

        $table = $wpdb->prefix . self::$tablename;

        $wpdb->query("LOCK TABLES $table LOW_PRIORITY WRITE;");
        
		$sql = "SELECT IFNULL(MAX(match_num),0) FROM $table WHERE event_ID=%d AND round_num=%d;";
        $safe = $wpdb->prepare($sql,$this->event_ID,$this->round_num);
        $this->match_num = $wpdb->get_var($safe) + 1;

        $values = array( 'event_ID'    => $this->event_ID
                        ,'round_num'   => $this->round_num
                        ,'match_num'   => $this->match_num
                        ,'match_type'  => $this->match_type
                        ,'match_date'  => $this->match_date
                        ,'match_time'  => $this->match_time
                        ,'home_ID'     => $this->home_ID
                        ,'visitor_ID'  => $this->visitor_ID);
        $formats_values = array('%d','%d','%d','%s','%s','%s','%d','%d');
		$wpdb->insert($wpdb->prefix . self::$tablename, $values, $formats_values);
        $this->isnew = FALSE;
		$this->isdirty = FALSE;
        $result = $wpdb->rows_affected;
        
		$wpdb->query("UNLOCK TABLES;");

        error_log("Match::create $wpdb->rows_affected rows affected.");
        
        foreach($this->getGames() as $game) {
            $result += $game->save();
        }

		return $result;
	}

	protected function update() {
		global $wpdb;

        parent::update();

        $values = array( 'match_type'  => $this->match_type
                        ,'match_date'  => $this->getMatchDate()
                        ,'match_time'  => $this->getMatchTime()
                        ,'home_ID'     => $this->home_ID
                        ,'visitor_ID'  => $this->visitor_ID);
		$formats_values = array('%s','%s','%s','%d','%d');
        $where          = array( 'event_ID'  => $this->event_ID
                                ,'round_num' => $this->round_num
                                ,'match_num' => $this->match_num);
		$formats_where  = array('%d'.'%d','%d');
		$wpdb->update($wpdb->prefix . self::$tablename, $values, $where, $formats_values, $formats_where);
        $this->isdirty = FALSE;
        $result = $wpdb->rows_affected;

        error_log("Match::update $wpdb->rows_affected rows affected.");
        
        foreach($this->getGames() as $game) {
            $result += $game->save();
        }

		return $result;
	}

    //TODO: Complete the delete logic
    public function delete() {

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
        $obj->match_date = $row["match_date"];
        $obj->match_time = $row["match_time"];
    }
    
    private function getIndex($obj) {
        return $obj->getPosition();
    }

    private function init() {
        // $this->event_ID   = NULL;
        // $this->round_num  = NULL;
        $this->match_num  = NULL;
        $this->match_type = NULL;
        $this->match_date = NULL;
        $this->match_time = NULL;
        $this->home_ID    = NULL;
        $this->home       = NULL;
        $this->visitor_ID = NULL;
        $this->visitor    = NULL;
        $this->games      = NULL;
    }

} //end class