<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require('abstract-class-data.php');
require('class-entrant.php');
require('class-game.php');

/** 
 * Data and functions for Tennis Event Match(es)
 * @class  Match
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
*/
class Match extends AbstractData
{ 
    private static $tablename = 'tennis_match';
    

    /**
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
     */
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
    public static function find(... $fk_criteria) {
		global $wpdb;
        $table = $wpdb->prefix . self::$tablename;
        $col = array();
        $eventID = 0;
        $roundnum = 0;
        if( count($fk_criteria.keys) === 0 ) {
            if(count($fk_criteria) < 2) return $col;
            $eventID = $fk_criteria[0];
            $roundID = $fk_criteria[1];
        } 
        else {
            $eventID = $fk_criteria["event_ID"];
            $roundnum = $fk_criteria["round_num"];
        }
        
        $sql = "select event_ID,round_num,match_num,match_type,match_date,match_time 
                from $table where event_ID = %d and round_ID = %d";
		$safe = $wpdb->prepare($sql,$eventID,$roundnum);
		$rows = $wpdb->get_results($safe, ARRAY_A);

		foreach($rows as $row) {
            $obj = new Match;
            self::mapData($obj,$row);
			$col[] = $obj;
		}
		return $col;
    }

	/**
	 * Get instance of a Match using primary key: event_id, round_num, match_num
	 */
    static public function get(... $pks) {
		global $wpdb;
        $table = $wpdb->prefix . self::$tablename;
        $obj = NULL;
        if(count($pks) !== 3) return $obj;
        
        $sql = "select event_ID,round_num,match_num,match_type,match_date,match_time 
                from $table where event_ID=%d and round_num=%d and match_num=%d;";
		$safe = $wpdb->prepare($sql,$pks);
		$rows = $wpdb->get_results($safe, ARRAY_A);

		error_log("Match::get(id) $wpdb->num_rows rows returned.");

		if($rows.length === 1) {
			$obj = new Match;
            self::mapData($obj,$rows[0]);
		}
		return $obj;
	}

	/*************** Instance Methods ****************/
	public function _construct() {
        $this->isnew = TRUE;
        $this->init();
    }
    
    /**
     * Assign this Match to an Event
     */
    public function setEvent($event) {
        if(! $event instanceof Event) return;
        $this->event = $event;
        $this->event_ID = $event->ID;
        $this->isdirty = TRUE;
    }

    /**
     * Get this Match's Event.
     */
    public function getEvent() {
        return $this->event;
    }

    /**
     * Set the Round number
     */
    public function setRoundNumber($num) {
        if(!is_numeric($num) || $num < 1 ) return;
        $this->round_num = $num;
        $this->isdirty = TRUE;
    }

    public function getRoundNumber(){
        return $this->round_num;
    }

    public function getMatchNumber() {
        return $this->match_num;
    }

    /**
     * Set the Home opponent for this match
     */
    public function setHomeEntrant($h) {
        if( $h instanceof Entrant ) {
            $this->home = $h;
            $this->home_ID = $h->getID();
            $this->isdirty = TRUE;
        }
    }

    public function getHomeEntrant() {
        return $this->home;
    }
    
    /**
     * Set the Visitor opponent for this match
     */
    public function setVisitorEntrant($v) {
        if( $v instanceof Entrant ) {
            $this->visitor = $v;
            $this->visitor_ID = $v->getID();
            $this->isdirty = TRUE;
        }
    }

    public function getVisitorEntrant() {
        return $this->visitor;
    }

	/**
	 * Get all my children!
	 * 1. Entrants
     * 2. Games
	 */
    public function getChildren($force=FALSE) {
        $this->getEntrants($force);
        $this->getGames($force);
    }

    private function getEntrants($force) {
        if(isset($this->home) && isset($this->visitor) && !force) return;
        
        $contestants = Entrant::find($this->event_ID, $this->round_num, $this->match_num);
        switch(count($contestants)) {
            case 1:
                $this->setHomeEntrant($contestants[0]);
                $this->visitor = NULL;
                $this->visitor_ID = NULL;
                break;
            case 2:
                $this->setHomeEntrant($contestants[0]);
                $this->setVisitorEntrant($contestant[1]);
                break;
            default:
                $this->home = NULL;
                $this->home_ID = NULL;
                $this->visitor = NULL;
                $this->visitor_ID = NULL;
            break;
        }
    }

    private function getGames($force) {
        if(count($this->games) === 0 || $force) $this->games = Game::find($this->event_ID,$this->round_num,$this->match_num);
    }
    
    public function isValid() {
        $isvalid = TRUE;
        if(!isset($this->event_ID)) $isvalid = FALSE;
        if(!isset($this->round_num)) $isvalid = FALSE;
        if(!$this->isNew() && !isset($this->match_num)) $isvalid = FALSE;
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
        
		$sql = "select max(match_num) from $table where event_ID=%d and round_num=%d;";
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
        
		$wpdb->query("UNLOCK TABLES;");

		error_log("Match::create $wpdb->rows_affected rows affected.");

		return $wpdb->rows_affected;
	}

	protected function update() {
		global $wpdb;

        parent::update();

        $values = array( 'match_type'  => $this->match_type
                        ,'match_date'  => $this->match_date
                        ,'match_time'  => $this->match_time
                        ,'home_ID'     => $this->home_ID
                        ,'visitor_ID'  => $this->visitor_ID);
		$formats_values = array('%s','%s','%s','%d','%d');
        $where          = array( 'event_ID'  => $this->event_ID
                                ,'round_num' => $this->round_num
                                ,'match_num' => $this->match_num);
		$formats_where  = array('%d'.'%d','%d');
		$wpdb->update($wpdb->prefix . self::$tablename, $values, $where, $formats_values, $formats_where);
		$this->isdirty = FALSE;

		error_log("Match::update $wpdb->rows_affected rows affected.");

		return $wpdb->rows_affected;
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
        $obj->getChildren(TRUE);
    }
    
    private function getIndex($obj) {
        return $obj->getPosition();
    }

    private function init() {
        $this->event_ID   = NULL;
        $this->round_num  = NULL;
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