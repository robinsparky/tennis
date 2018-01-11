<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require('abstract-class-data.php');
require('class-player.php');

/** 
 * Data and functions for Tennis Event Entrant(s)
 * @class  Entrant
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
*/
class Entrant extends AbstractData
{ 
    private static $tablename = 'tennis_entry';
	
	//Foreign keys
    private $draw_ID;
	private $match_ID;

	/** 
	 * Name of the single player or the doubles team
	 */
	private $name;

	/**
	 * Position in the draw (not to be confused with seeding)
	 */
	private $position;

	/**
	 * The seeding of this player or team.
	 * Must be unique
	 */
	private $seed;
	
	/**
	 * 1 player for singles; 2 players for doubles;
	 */
	private $players;
    
    /**
     * Search for Draws that have a name 'like' the provided criteria
     */
    public static function search($criteria) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$sql = "select * from $table where name like '%%s%'";
		$safe = $wpdb->prepare($sql,$criteria);
		$rows = $wpdb->get_results($safe, ARRAY_A);
		
		error_log("Entrant::search $wpdb->num_rows rows returned using criteria: $criteria");

		$col = array();
		foreach($rows as $row) {
            $obj = new Entrant;
            self::mapData($obj,$row);
			$obj->isnew = FALSE;
			$col[] = $obj;
		}
		return $col;
    }
    
    /**
     * Find all Entrants belonging to a specific Draw or Match
     */
    public static function find($fk_id, $context) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$col = array();

		if(!isset($context) || !is_string($context)) return $col;
		if($context === 'draw') $column = 'draw_ID';
		elseif($context === 'match') $column = 'match_ID';
		elseif($context === 'game') $column = 'game_ID';
		else return $col;

		$sql = "select * from $table where $column = %d";
		$safe = $wpdb->prepare($sql,$fk_id);
		$rows = $wpdb->get_results($safe, ARRAY_A);
		
		error_log("Draw::find $wpdb->num_rows rows returned using draw_ID=$fk_id");

		foreach($rows as $row) {
            $obj = new Entrant;
            self::mapData($obj,$row);
			$obj->isnew = FALSE;
			$col[] = $obj;
		}
		return $col;
    }

	/**
	 * Get instance of a Entrant using it's ID
	 */
    static public function get($id) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$sql = "select * from $table where ID=%d";
		$safe = $wpdb->prepare($sql,$id);
		$rows = $wpdb->get_results($safe, ARRAY_A);

		error_log("Draw::get(id) $wpdb->num_rows rows returned.");

		if($rows.length !== 1) {
			$obj = NULL;
		} else {
			$obj = new Entrant;
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

    /**
     * Set a new value for a name of this Draw
     */
	public function setName($name) {
        if(!is_string($name) || strlen($name) < 1) return;
		$this->name = $name;
		$this->dirty = TRUE;
    }
    
    /**
     * Get the name of this Draw
     */
    public function getName() {
        return $this->name;
    }

    /**
     * Assign this Entrant to a Draw
     */
    public function setDrawId($draw) {
        if(!is_numeric($draw) || $draw < 1) return;
        $this->draw_ID = $draw;
        $this->isdirty = TRUE;
    }

    /**
     * Get this Entrant's Draw id.
     */
    public function getDrawId() {
        return $this->draw_ID;
    }
	
    /**
     * Assign this Entrant to a Match
     */
    public function setMatchId($match) {
        if(!is_numeric($match) || $match < 1) return;
        $this->match_ID = $match;
        $this->isdirty = TRUE;
    }

    /**
     * Get this Entrant's Match id.
     */
    public function getMatchId() {
        return $this->match_ID;
	}
	
	/**
	 * Assign a position
	 */
	public function setPosition($pos) {
		if(!is_numeric($pos) || $pos < 1) return;
		$this->position = $pos;
        $this->isdirty = TRUE;
	}

	/**
	 * Get Position in Draw
	 */
	public function getPosition() {
		return $this->position;
	}

	/**
	 * Seed this player(s)
	 */
	public function setSeed($seed) {
		if(!is_numeric($seed) || $seed < 0) return;
		$this->seed = $seed;
        $this->isdirty = TRUE;
	}

	/**
	 * Get the seeding of this Entrant (player(s))
	 */
	public function getSeed() {
		return $this->seed;
	}

	/**
	 * Get all my children!
	 * 1. Players
	 */
    public function getChildren() {
		$this->getPlayers();
	}

	/**
	 * Get all Players for this Entrant.
	 */
	public function getPlayers() {
        if(count($this->players) === 0) $this->players = Player::find($this->ID);
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
		if(!isset($this->draw_ID)) $invalid = FALSE;
		if(!isset($this->name))  $invalid = FALSE;
		if(!issest($this->position))  $invalid = FALSE;

		return $isvalid;
	}

	private function create() {
		global $wpdb;
		$where          = array('ID' => $this->ID);
		$formats_where  = array('%d');
        
        if($this->isValid()) return;

		$values         = array('name' => $this->name
							,'draw_ID' => $this->draw_ID
							,'match_ID' => $this->match_ID
							,'position' => $this->position
							,'seed' => $this->seed);
		$formats_values = array('%s','%d','%d','%d','%d');

		$wpdb->insert($wpdb->prefix . self::$tablename, $values, $formats_values);
		$this->ID = $wpdb->insert_id;
		$this->isnew = FALSE;

		error_log("Entrant::create $wpdb->rows_affected rows affected.");

		return $wpdb->rows_affected;
	}

	private function update() {
		global $wpdb;
		$values;
		$formats_values;

        if($this->draw_ID < 1) return;

		if($this->match_ID > 0) {
			$values         = array('name' => $this->name
									,'draw_ID' => $this->draw_ID
									,'match_ID' => $this->match_ID
									,'position' => $this->position
									,'seed' => $this->seed);
			$formats_values = array('%s','%d','%d','%d','%d');
			$where          = array('ID' => $this->ID);
			$formats_where  = array('%d');
		}
		else {
			$values         = array('name' => $this->name
								,'draw_ID' => $this->draw_ID
								,'position' => $this->position
								,'seed' => $this->seed);
			$formats_values = array('%s','%d','%d','%d');
		}
		$wpdb->update($wpdb->prefix . self::$tablename, $values, $where, $formats_values, $formats_where);
		$this->isdirty = FALSE;

		error_log("Entrant::update $wpdb->rows_affected rows affected.");

		return $wpdb->rows_affected;
	}

    public function delete() {

	}
	
	private function init() {
		$this->draw_ID = NULL;
		$this->match_ID = NULL;
		$this->position = NULL;
		$this->seed = NULL;
	}
    
    /**
     * Map incoming data to an instance of Entrant
     */
    protected static function mapData($obj,$row) {
		error_log("Entrant::mapData:");
		error_log(var_dump($row));

        $obj->ID = $row["ID"];
        $obj->name = $row["name"];
		$obj->draw_ID = $row["draw_ID"];
		$obj->match_ID = $row["match_ID"];
		$obj->position = $row["position"];
        $obj->seed = $row["seed"];		
    }


} //end class