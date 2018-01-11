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
    private $home;
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
    public static function find($fk_id) {
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
			$obj->isnew = FALSE;
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

		if($rows.length !== 1) {
			$obj = NULL;
		} else {
			$obj = new Match;
			foreach($rows as $row) {
                self::mapData($obj,$row);
				$obj->isnew = FALSE;
			}
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
    public function getChildren() {
        $this->getEntrants();
        $this->getGames();
    }

    private function getEntrants() {
        $entrants = Entrant::find($this->ID,'match');
        $ents = count($entrants);
        error_log("Match::getEntrants found $ents");
        if($ents === 2) {
            $entrants = $this->objSort($entrants,$this->getIndex());
            $this->setHomeEntrant($entrants[0]);
            $this->setVisitorEntrant($entrants[1]);
        }
    }

    private function getGames() {
        $this->games = Game::find($this->ID);
    }
    
    /**
     * Save this Draw to the daatabase
     */
    public function save() {
		if($this->isnew) $this->create();
		elseif ($this->isdirty) $this->update();
    }
    
    public function isValid() {
        $isvalid = TRUE;
        if(!isset($this->round_ID)) $isvalid = FALSE;

        return $isvalid;
    }

	private function create() {
        global $wpdb;
        
        if(!$this->isValid()) return;

        $values         = array('round_ID' => $this->round_ID);
		$formats_values = array('%d');
		$wpdb->insert($wpdb->prefix . self::$tablename, $values, $formats_values);
		$this->ID = $wpdb->insert_id;
		$this->isnew = FALSE;

		error_log("Match::create $wpdb->rows_affected rows affected.");

		return $wpdb->rows_affected;
	}

	private function update() {
		global $wpdb;

        if($this->isValid()) return;

        $values         = array('round_ID' => $this->owner_ID);
		$formats_values = array('%d');
		$where          = array('ID' => $this->ID);
		$formats_where  = array('%d');
		$wpdb->update($wpdb->prefix . self::$tablename, $values, $where, $formats_values, $formats_where);
		$this->isdirty = FALSE;

		error_log("Match::update $wpdb->rows_affected rows affected.");

		return $wpdb->rows_affected;
	}

    public function delete() {

    }
    
    /**
     * Map incoming data to an instance of Match
     */
    protected static function mapData($obj,$row) {
        $obj->ID = $row["ID"];
        $obj->round_ID = $row["round_ID"];
    }
    
    private function getIndex($obj) {
        return $obj->getPosition();
    }

    private function init() {
        $this->round_ID = NULL;
        $this->home = NULL;
        $this->visitor = NULL;
        $this->games = NULL;
    }


} //end class