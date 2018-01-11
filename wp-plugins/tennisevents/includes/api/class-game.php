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
    
    private $match_ID;
    private $entry_ID;
    private $entrant;
    private $score;
    private $set_number;
    
    /**
     * Search for Matches that have a name 'like' the provided criteria
     */
    public static function search($criteria) {
		$col = array();
		return $col;
    }
    
    /**
     * Find all Games belonging to a specific Match or Entrant;
     */
    public static function find($fk_id, $context) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$col = array();

        if(!isset($context) || !is_string($context)) return $col;

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

		error_log("Game::get(id) $wpdb->num_rows rows returned.");

		if($rows.length !== 1) {
			$obj = NULL;
		} else {
			$obj = new Game;
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
    
    public function getGameNumber(){
        return $this->getID();
    }

    /**
     * Set the Entrant for this Game
     */
    public function setEntrant($h) {
        if($h instanceof Entrant ) {
            $this->entrant = $h;
            $this->entry_ID = $h->getID();
            $this->isdirty = TRUE;
        }
    }

    public function geEntrant() {
        return $this->entrant;
    }
    
	/**
	 * Get all my children!
	 */
    public function getChildren() {

    }
    
    public function isValid() {
        $isvalid = TRUE;
        if(!isset($this->match_ID)) $isvalid = FALSE;
        if(!isset($this->entry_ID)) $isvalid = FALSE;
        if(!isset($this->score))  $isvalid = FALSE;
        if(!isset($this->set_number)) $isvalid = FALSE;

        return $isvalid;
    }

	private function create() {
        global $wpdb;
        
        if(!$this->isValid()) return;

        $values         = array('match_ID' => $this->match_ID
                               ,'entry_ID' => $this->entry_ID
                               ,'set_number' => $this->set_number
                               ,'score' => $this->score);
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
                               ,'entry_ID' => $this->entry_ID
                               ,'set_number' => $this->set_number
                               ,'score' => $this->score);
		$formats_values = array('%d','%d','%d','%d');
		$where          = array('ID' => $this->ID);
		$formats_where  = array('%d');
		$wpdb->update($wpdb->prefix . self::$tablename, $values, $where, $formats_values, $formats_where);
		$this->isdirty = FALSE;

		error_log("Game::update $wpdb->rows_affected rows affected.");

		return $wpdb->rows_affected;
	}

    public function delete() {

    }
    
    /**
     * Map incoming data to an instance of Game
     */
    protected static function mapData($obj,$row) {
        $obj->ID = $row["ID"];
        $obj->match_ID = $row["match_ID"];
        $obj->entry_ID = $row["entry_ID"];
        $obj->set_number = $row["set_number"];
        $obj->score = $row["score"];
    }
 
    private function getIndex($obj) {
        return $obj->getPosition();
    }

    private function init() {
        $this->match_ID = NULL;
        $this->entry_ID = NULL;
        $this->entrant = NULL;
        $this->set_number = NULL;
        $this->score = NULL;
    }


} //end class