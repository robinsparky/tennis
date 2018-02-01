<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once('abstract-class-data.php');
/** 
 * Data and functions for Tennis Game(s)
 * @class  Match
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
*/
class Game extends AbstractData
{ 
    private static $tablename = 'tennis_game';

    const MAXSETS = 5;
    const MINSETS = 1;
    
    //Primary Keys
    private $event_ID;
    private $round_num;
    private $match_num;
    private $set_num;

    private $home_wins;
    private $visitor_wins;
    
    /**
     * Search for Matches that have a name 'like' the provided criteria
     */
    public static function search($criteria) {
		global $wpdb;
		return array();
    }
    
    /**
     * Find all Games belonging to a specific Match;
     */
    public static function find(...$fk_criteria) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
        $col = array();
        $where = array();

        if(count($fk_criteria.keys) === 0) {
            if(count($fk_criteria) === 3) {
                $where[] = $fk_criteria[0];
                $where[] = $fk_criteria[1];
                $where[] = $fk_criteria[2];
            }
            else {
                return $col;
            }
        }
        else {
            return $col;
        }
        $sql = "select event_ID,round_num,match_num,set_num,home_wins,visitor_wins
                 from $table 
                 where event_ID = %d 
                 and   round_num = %d 
                 and   match_num = %d;";
		$safe = $wpdb->prepare($sql,$where);
		$rows = $wpdb->get_results($safe, ARRAY_A);
		
		error_log("Game::find $wpdb->num_rows rows returned");

		foreach($rows as $row) {
            $obj = new Game;
            self::mapData($obj,$row);
			$col[] = $obj;
		}
		return $col;
    }

	/**
	 * Get instance of a Match using it's primary key: event_ID,round_num,match_num,set_num,game_num
	 */
    static public function get(int ...$pks) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$sql = "select event_ID,round_num,match_num,set_num,home_wins,visitor_wins from $table where ID=%d";
		$safe = $wpdb->prepare($sql,$id);
		$rows = $wpdb->get_results($safe, ARRAY_A);

		error_log("Game::get(id) $wpdb->num_rows rows returned.");

        $obj = NULL;
		if($rows.length === 1) {
            $obj = new Game;
            self::mapData($obj,$rows[0]);
		}
		return $obj;
	}

	/*************** Instance Methods ****************/
	public function __construct(int $eventID, int $round, int $match,int $set=NULL) {
        $this->isnew = TRUE;
        $this->eventID   = $eventID;
        $this->round_num = $round;
        $this->match_num = $match;
        $this->set_num   = $set;
        $this->init();
    }

    public function __destruct() {

    }
    
    public function setIdentifiers(...$pks) {
        if(!$this->isNew()) return false;

        if(3 === count($pks)) {
            $this->event_ID  = $pks[0];
            $this->round_num = $pks[1];
            $this->match_num = $pks[2];
        }
        elseif(4 === count($pks)) {
            $this->event_ID  = $pks[0];
            $this->round_num = $pks[1];
            $this->match_num = $pks[2];
            $this->set_num   = $pks[3];
        }
        return true;
    }

    public function getIdentifiers() {
        $ids = array();
        $ids[] = $this->event_ID;
        $ids[] = $this->round_num;
        $ids[] = $this->match_num;
        $ids[] = $this->set_num;
        
        return $ids;
    }

    public function setSetNumber(int $set) {
        if($set < self::MINSETS) $set = self::MINSETS;
        if($set > self::MAXSETS) $set = self::MAXSETS;
        $this->set_number = $set;
        $this->isdirty = TRUE;
        return TRUE;
    }

    public function getSetNumber():int {
        return $this->set_number;
    }

    public function setHomeScore(int $wins,int $ties=0) {
        if($wins < 0) return;
        $this->home_wins = $wins;
        $this->isdirty = TRUE;
    }

    public function getHomeWins():int {
        return $this->home_wins;
    }

    public function setVisitorScore(int $wins,int $ties=0) {
        if($wins < 0) return;
        $this->visitor_wins = $wins;
        $this->isdirty = TRUE;
    }

    public function getVisitorWins():int {
        return $this->visitor_wins;
    }
    
	/**
	 * Get all my children!
	 */
    public function getChildren($force=FALSE) {
        
    }
    
    public function isValid() {
        $isvalid = TRUE;
        if(!isset($this->event_ID)) $isvalid = FALSE;
        if(!isset($this->round_num)) $isvalid = FALSE;
        if(!isset($this->match_num)) $isvalid = FALSE;
        if(!$this->isNew() && !isset($this->set_num)) $isvalid = FALSE;
        if(!isset($this->home_wins) && !isset($this->visitor_wins))  $isvalid = FALSE;

        return $isvalid;
    }

	protected function create() {
        global $wpdb;
        
        parent::create();
        
		$table = $wpdb->prefix . self::$tablename;
        $wpdb->query("LOCK TABLES $table LOW_PRIORITY WRITE;");
        
		$sql = "select max(set_num) from $table where event_ID=%d and round_num=%d and match_num=%d;";
        $safe = $wpdb->prepare($sql,$this->event_ID,$this->round_num,$this->match_num);
        $this->set_num = $wpdb->get_var($safe,0,0) + 1;
        
        if($this->set_num < self::MINSETS) $this->set_num = self::MINSETS;
        if($this->set_num > self::MAXSETS) {
            $this->set_num = self::MAXSETS;
            $wpdb->query("UNLOCK TABLES;");
            $this->isnew = FALSE;
            return 0;
        }
        
        $this->game_num =  $wpdb->get_var($safe,0,1) + 1;

        $values = array( 'event_ID' => $this->event_ID
                        ,'round_num' => $this->round_num
                        ,'match_num' => $this->match_num
                        ,'set_num' => $this->set_number
                        ,'home_wins' => $this->home_wins
                        ,'visitor_wins' => $this->visitor_wins);
		$formats_values = array('%d','%d','%d','%d','%d','%d');
		$wpdb->insert($wpdb->prefix . self::$tablename, $values, $formats_values);
        $result =  $wpdb->rows_affected;
        $wpdb->query("UNLOCK TABLES;");

		$this->isnew = FALSE;
		$this->isdirty = FALSE;

		error_log("Game::create $wpdb->rows_affected rows affected.");

		return $result;
	}

	protected function update() {
		global $wpdb;

        parent::update();

        $values = array( 'home_wins' => $this->homes_wins
                        ,'visitor_wins' => $this->visitor_wins);
		$formats_values = array('%d','%d');
        $where = array('event_ID'  => $this->event_ID
                      ,'round_num' => $this->round_num
                      ,'match_num' => $this->match_num
                      ,'set_num'   => $this->set_num);
		$formats_where  = array('%d','%d','%d','%d');
		$wpdb->update($wpdb->prefix . self::$tablename, $values, $where, $formats_values, $formats_where);
		$this->isdirty = FALSE;
        $result =  $wpdb->rows_affected;

		error_log("Game::update $wpdb->rows_affected rows affected.");

		return $result;
	}

    //TODO: Complete the delete logic
    public function delete() {

    }
    
    /**
     * Map incoming data to an instance of Game
     */
    protected static function mapData($obj,$row) {
        parent::mapData($obj,$row);
        $obj->event_ID  = $row["event_ID"];
        $obj->round_num = $row["round_num"];
        $obj->match_num = $row["match_num"];
        $obj->set_num   = $row["set_num"];
        $obj->homes_wins = $row["home_wins"];
        $obj->visitor_wins = $row["visitor_wins"];
    }
 
    private function getIndex($obj) {
        return $obj->getPosition();
    }

    private function init() {
        // $this->event_ID     = NULL;
        // $this->round_num    = NULL;
        // $this->match_num    = NULL;
        // $this->set_number   = NULL;
        $this->home_wins    = NULL;
        $this->visitor_wins = NULL;
    }


} //end class