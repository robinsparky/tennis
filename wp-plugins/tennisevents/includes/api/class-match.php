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
    
    private $round_ID;
    
    //Games
    private $games;

    //Match needs 2 entrants: home and visitor
    private $home_ID;
    private $home;
    private $visitor_ID;
    private $visitor;
    
    /**
     * Search for Matches that have a name 'like' the provided criteria
     */
    public static function search($criteria) {
		$col = array();
		return $col;
    }
    
    /**
     * Find all Matches belonging to a specific Round;
     */
    public static function find($fk_id, $context=NULL) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$sql = "select * from $table where round_ID = %d";
		$safe = $wpdb->prepare($sql,$fk_id);
		$rows = $wpdb->get_results($safe, ARRAY_A);
		
		error_log("Match::find $wpdb->num_rows rows returned using round_ID=$fk_id");

		$col = array();
		foreach($rows as $row) {
            $obj = new Match;
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

		error_log("Match::get(id) $wpdb->num_rows rows returned.");

        $obj = NULL;
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
    
    public function getMatchNumber(){
        return $this->getID();
    }

    /**
     * Set the Home opponent for this match
     */
    public function setHomeEntrant($h) {
        if($h instanceof Entrant ) {
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
        if($v instanceof Entrant ) {
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
        if(!isset($this->home_ID) || !isset($this->visitor_ID)) return;
        
        if(!isset($this->home) || !isset($this->visitor) || $force) {
            $this->home = Entrant::get($this->home_ID,'match');
            $this->visitor = Entrant::get($this->visitor_ID,'match');
        }
    }

    private function getGames($force) {
        if(count($this->games) === 0 || $force) $this->games = Game::find($this->ID);
    }
    
    public function isValid() {
        $isvalid = TRUE;
        if(!isset($this->round_ID)) $isvalid = FALSE;
        if(!isset($this->home)) $isvalid = FALSE;
        if(!isset($this->visitor)) $isvalid = FALSE;

        return $isvalid;
    }

	private function create() {
        global $wpdb;
        
        if(!$this->isValid()) return;

        $values         = array('round_ID'   => $this->round_ID
                               ,'home_ID'    => $this->home->ID
                               ,'visitor_ID' => $this->visitor->ID);
		$formats_values = array('%d','%d','%d');
		$wpdb->insert($wpdb->prefix . self::$tablename, $values, $formats_values);
		$this->ID = $wpdb->insert_id;
		$this->isnew = FALSE;

		error_log("Match::create $wpdb->rows_affected rows affected.");

		return $wpdb->rows_affected;
	}

	private function update() {
		global $wpdb;

        if($this->isValid()) return;

        $values         = array('round_ID'    => $this->owner_ID
                                ,'home_ID'    => $this->home->ID
                                ,'visitor_ID' => $this->visitor->ID);
		$formats_values = array('%d','%d','%d');
		$where          = array('ID' => $this->ID);
		$formats_where  = array('%d');
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
        $obj->round_ID = $row["round_ID"];
        $obj->home_ID = $row["home_ID"];
        $obj->visitor_ID = $row["visitor_ID"];
    }
    
    private function getIndex($obj) {
        return $obj->getPosition();
    }

    private function init() {
        $this->round_ID = NULL;
        $this->home_ID = NULL;
        $this->home = NULL;
        $this->visitor_ID = NULL;
        $this->visitor = NULL;
        $this->games = NULL;
    }


} //end class