<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require('abstract-class-data.php');
require('class-entrant.php');

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
    
    //Foreign Keys
    private $match_ID;

    private $set_number;
    private $homescore;
    private $visitorscore;
    
    /**
     * Search for Matches that have a name 'like' the provided criteria
     */
    public static function search($criteria) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$sql = "select * from $table where $criteria";
		$safe = $wpdb->prepare($sql,$criteria);
		$rows = $wpdb->get_results($safe, ARRAY_A);
		
		error_log("Round::search $wpdb->num_rows rows returned where $criteria");

		$col = array();
		foreach($rows as $row) {
            $obj = new Game;
            self::mapData($obj,$row);
			$col[] = $obj;
		}
		return $col;
    }
    
    /**
     * Find all Games belonging to a specific Match or Entrant;
     */
    public static function find($fk_id, $context) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$col = array();

        if(!is_string($context)) return $col;

        if($context === 'match') $column = 'match_ID';
        elseif($context === 'entrant') $column = 'entrant_ID';
        else return $col;

		$sql = "select * from $table where $column = %d";
		$safe = $wpdb->prepare($sql,$fk_id);
		$rows = $wpdb->get_results($safe, ARRAY_A);
		
		error_log("Game::find $wpdb->num_rows rows returned using $column = $fk_id");

		foreach($rows as $row) {
            $obj = new Game;
            self::mapData($obj,$row);
			$col[] = $obj;
		}
		return $col;
    }

	/**
	 * Get instance of a Match using it's ID
	 */
    static public function get($id) {
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
    
    public function getGameNumber(){
        return $this->getID();
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
        $this->getEntrant($force);
    }

    private function getEntrant($force) {
        if(!isset($this->entrant_ID)) return;

        if(!isset($this->entrant) || $force) $this->entrant = Entrant::get($this->entrant_ID);
    }
    
    public function isValid() {
        $isvalid = TRUE;
        if(!isset($this->match_ID)) $isvalid = FALSE;
        if(!isset($this->homescore))  $isvalid = FALSE;
        if(!isset($this->visitorscore))  $isvalid = FALSE;
        if(!isset($this->set_number)) $isvalid = FALSE;

        return $isvalid;
    }

	private function create() {
        global $wpdb;
        
        if(!$this->isValid()) return;

        $values         = array('match_ID' => $this->match_ID
                               ,'set_number' => $this->set_number
                               ,'homescore' => $this->homescore
                               ,'visitorscore' => $this->visitorscore);
		$formats_values = array('%d','%d','%d','%d');
		$wpdb->insert($wpdb->prefix . self::$tablename, $values, $formats_values);
		$this->ID = $wpdb->insert_id;
		$this->isnew = FALSE;

		error_log("Game::create $wpdb->rows_affected rows affected.");

		return $wpdb->rows_affected;
	}

	private function update() {
		global $wpdb;

        if($this->isValid()) return;

        $values         = array('match_ID' => $this->match_ID
                               ,'set_number' => $this->set_number
                               ,'homescore' => $this->homescore
                               ,'visitorscore' => $this->visitorscore);
		$formats_values = array('%d','%d','%d','%d');
		$where          = array('ID' => $this->ID);
		$formats_where  = array('%d');
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
        $obj->match_ID = $row["match_ID"];
        $obj->set_number = $row["set_number"];
        $obj->homescore = $row["homescore"];
        $obj->visitorscore = $row["visitorscore"];
    }
 
    private function getIndex($obj) {
        return $obj->getPosition();
    }

    private function init() {
        $this->match_ID = NULL;
        $this->set_number = NULL;
        $this->homescore = NULL;
        $this->visitorscore = NULL;
    }


} //end class