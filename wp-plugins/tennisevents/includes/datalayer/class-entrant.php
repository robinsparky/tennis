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
    private static $tablename = 'tennis_entrant';
	
	//Foreign keys
    private $event_ID;

	/**
	 * Position in the draw (not to be confused with seeding)
	 */
	private $position;

	/** 
	 * Name of the single player or the doubles pair
	 */
	private $name;


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
			$col[] = $obj;
		}
		return $col;
    }
    
    /**
     * Find all Entrants in order of position 
	 * belonging to a specific Event (draw)
	 * Or all Entrants in order of position
	 * belonging to a specific Event and
	 * assigned to any match
     */
    public static function find(... $fk_criteria) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$col = array();
		$where = array();
		if(count($fk_criteria.keys) === 0) {
			if(count($fk_criteria) === 1) {
			$where[] = $fk_criteria[0];
			$sql = "select event_ID,position,name,seed 
					from $table where event_ID = %d order by position;";
			}
			elseif(count($fk_criteria) === 3) {
				$where[] = $fk_criteria[0];
				$where[] = $fk_criteria[1];
				$where[] = $fk_criteria[2];
				$joinTable = $wpdb->prefix . "tennis_match_entrant";
				
				$sql = "select   j.match_event_ID
								,j.match_round_num
								,j.match_num
								,e.position
								,e.name
								,e.seed
						from $table e 
						inner join $joinTable j on j.match_event_ID = e.event_ID 
												and j.entrant_position = e.position 
						where e.event_ID=%d 
						and   j.match_round_num=%d 
						and   j.match_num=%d 
						order by e.position;";
			}
		}
		else {
			$where[] = $fk_criteria["event_ID"];
			$where[] = $fk_criteria["round_num"];
			$where[] = $fk_criteria["match_num"];
			$joinTable = $wpdb->prefix . "tennis_match_entrant";
			
			$sql = "select   j.match_event_ID
							,j.match_round_num
							,j.match_num
							,e.position
							,e.name
							,e.seed
					from $table e 
					inner join $joinTable j on j.match_event_ID = e.event_ID 
											and j.entrant_position = e.position 
					where e.event_ID=%d 
					and   j.match_round_num=%d 
					and   j.match_num=%d 
					order by e.position;";
		}

		$safe = $wpdb->prepare($sql,$where);
		$rows = $wpdb->get_results($safe, ARRAY_A);
		
		foreach($rows as $row) {
            $obj = new Entrant;
            self::mapData($obj,$row);
			$col[] = $obj;
		}
		return $col;
    }

	/**
	 * Get instance of a Entrant using it's primary key: event_ID, position
	 */
    static public function get(... $pks) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$obj = NULL;
		if(count($pks) !== 2) return $obj;

		$sql = "select event_ID,position,name,seed from $table where event_ID=%d and position=%d;";
		$safe = $wpdb->prepare($sql,$pks);
		$rows = $wpdb->get_results($safe, ARRAY_A);

		error_log("Entrant::get(id) $wpdb->num_rows rows returned.");

		if($rows.length === 1) {
			$obj = new Entrant;
			self::mapData($obj,$rows[0]);
		}
		return $obj;
	}

	/*************** Instance Methods ****************/
	public function __construct() {
		$this->isnew = TRUE;
		$this->init();
	}

    /**
     * Set a new value for a name of this Draw
     */
	public function setName($name) {
        if(!is_string($name)) return;
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
     * Assign this Entrant to an Event
     */
    public function setEventId($draw) {
        if(!is_numeric($draw) || $draw < 1) return;
        $this->event_ID = $draw;
        $this->isdirty = TRUE;
    }

    /**
     * Get this Entrant's Draw id.
     */
    public function getEventId() {
        return $this->event_ID;
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
    public function getChildren($force=FALSE) {
		$this->getPlayers($force);
	}

	/**
	 * Get all Players for this Entrant.
	 */
	public function getPlayers($force) {
        if(count($this->players) === 0 || $force) $this->players = Player::find($this->ID);
	}

	public function isValid() {
		$isvalid = TRUE;
		if(!isset($this->event_ID)) $invalid = FALSE;
		if(!issest($this->position))  $invalid = FALSE;
		if(!isset($this->name))  $invalid = FALSE;

		return $isvalid;
	}

	protected function create() {
		global $wpdb;

		parent::create();

		
		$table = $wpdb->prefix . self::$tablename;
		$wpdb->query("LOCK TABLES $table LOW_PRIORITY WRITE");
		
		$sql = "select max(position) from $table where club_ID=%d;";
		$safe = $wpdb->prepare($sql,$this->club_ID);
		$this->position = $wpdb->get_var($safe) + 1;

		$values = array( 'event_ID' => $this->event_ID
						,'position' => $this->position
						,'name' => $this->name
						,'seed' => $this->seed);
		$formats_values = array('%d','%d','%s','%d');

		$wpdb->insert($wpdb->prefix . self::$tablename, $values, $formats_values);
		
		$wpdb->query("UNLOCK TABLES");

		$this->isnew = FALSE;

		error_log("Entrant::create $wpdb->rows_affected rows affected.");

		return $wpdb->rows_affected;
	}

	protected function update() {
		global $wpdb;
		$values;
		$formats_values;

		parent::update();
		
		$where = array( 'event_ID' => $this->ID
					   ,'position' => $this->position);
		$formats_where  = array('%d','%d');


		$values = array( 'name' => $this->name
						,'seed' => $this->seed);
		$formats_values = array('%s','%d');
		
		$wpdb->update($wpdb->prefix . self::$tablename, $values, $where, $formats_values, $formats_where);
		$this->isdirty = FALSE;

		error_log("Entrant::update $wpdb->rows_affected rows affected.");

		return $wpdb->rows_affected;
	}

	//TODO: Complete the delete logic
    public function delete() {

	}
	
	private function init() {
		$this->event_ID = NULL;
		$this->position = NULL;
		$this->name = NULL;
		$this->seed = NULL;
	}
    
    /**
     * Map incoming data to an instance of Entrant
     */
    protected static function mapData($obj,$row) {
		parent::mapData($obj,$row);
		$obj->event_ID = $row["event_ID"];
		$obj->position = $row["position"];
        $obj->name = $row["name"];
        $obj->seed = $row["seed"];		
    }

} //end class