<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require('abstract-class-data.php');
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
    private $game_num;

    private $homescore;
    private $visitorscore;
    
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
    public static function find(... $fk_criteria) {
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
        $sql = "select event_ID,round_num,match_num,set_num,game_num,home_score,visitor_score
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
    static public function get(... $pks) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$sql = "select * from $table where ID=%d";
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
	public function _construct() {
        $this->isnew = TRUE;
        $this->init();
    }

    public function setSetNumber($set) {
        if(!is_numeric($set) || $set < self::MINSETS || $set > self::MAXSETS) return;
        $this->set_number = $set;
        $this->isdirty = TRUE;
    }

    public function getSetNumber() {
        return $this->set_number;
    }
    
    public function setGameNumber($n) {
        if(!is_numeric($n)) return;
        $this->game_num = $n;
        $this->isdirty = TRUE;
    }
    public function getGameNumber(){
        return $this->game_num;
    }

    public function setHomeScore($score) {
        if(!is_numeric($score) || $score < 0) return;
        $this->homescore = $score;
        $this->isdirty = TRUE;
    }

    public function getHomeScore() {
        return $this->homescore;
    }

    public function setVisitorScore($score) {
        if(!is_numeric($score) || $score < 0) return;
        $this->visitorscore = $score;
        $this->isdirty = TRUE;
    }

    public function getVisitorScore() {
        return $this->visitorscore;
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
        if(!$this->isNew() && !isset($this->game_num)) $isvalid = FALSE;
        if(!isset($this->homescore))  $isvalid = FALSE;
        if(!isset($this->visitorscore))  $isvalid = FALSE;

        return $isvalid;
    }

	protected function create() {
        global $wpdb;
        
        parent::create();
        
		$table = $wpdb->prefix . self::$tablename;
        $wpdb->query("LOCK TABLES $table LOW_PRIORITY WRITE;");
        
		$sql = "select max(set_num),max(game_num) from $table where event_ID=%d and round_num=%d and match_num=%d;";
        $safe = $wpdb->prepare($sql,$this->event_ID,$this->round_num,$this->match_num);
        $this->set_num = $wpdb->get_var($safe,0,0) + 1;
        
        if($this->set_num < self::MINSETS) $this->set_num = self::MINSETS;
        if($this->set_num > self::MAXSETS) $this->set_num = self::MAXSETS;
        
        $this->game_num =  $wpdb->get_var($safe,0,1) + 1;

        $values = array( 'event_ID' => $this->event_ID
                        ,'round_num' => $this->round_num
                        ,'match_num' => $this->match_num
                        ,'set_num' => $this->set_number
                        ,'game_num' => $this->game_num
                        ,'homescore' => $this->homescore
                        ,'visitorscore' => $this->visitorscore);
		$formats_values = array('%d','%d','%d','%d','%d','%d');
		$wpdb->insert($wpdb->prefix . self::$tablename, $values, $formats_values);

		$this->isnew = FALSE;

		error_log("Game::create $wpdb->rows_affected rows affected.");

		return $wpdb->rows_affected;
	}

	private function update() {
		global $wpdb;

        if($this->isValid()) return;

        $values = array( 'homescore' => $this->homescore
                        ,'visitorscore' => $this->visitorscore);
		$formats_values = array('%d','%d');
        $where = array('event_ID'  => $this->event_ID
                      ,'round_num' => $this->round_num
                      ,'match_num' => $this->match_num
                      ,'set_num'   => $this->set_num
                      ,'game_num'  => $this->game_num);
		$formats_where  = array('%d','%d','%d','%d');
		$wpdb->update($wpdb->prefix . self::$tablename, $values, $where, $formats_values, $formats_where);
		$this->isdirty = FALSE;

		error_log("Game::update $wpdb->rows_affected rows affected.");

		return $wpdb->rows_affected;
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
        $obj->game_num  = $row["game_num"];
        $obj->homescore = $row["homescore"];
        $obj->visitorscore = $row["visitorscore"];
    }
 
    private function getIndex($obj) {
        return $obj->getPosition();
    }

    private function init() {
        $this->event_ID     = NULL;
        $this->round_num    = NULL;
        $this->match_num    = NULL;
        $this->set_number   = NULL;
        $this->game_num     = NULL;
        $this->homescore    = NULL;
        $this->visitorscore = NULL;
    }


} //end class