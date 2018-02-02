<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once('abstract-class-data.php');
require_once('class-player.php');

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
	 * assigned to a Match in a Round
     */
    public static function find(...$fk_criteria) {
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
				$where[] = $fk_criteria[0]; //Event
				$where[] = $fk_criteria[1]; //Round
				$where[] = $fk_criteria[2]; //Match
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
    static public function get(int ...$pks) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$obj = NULL;
		if(count($pks) !== 2) return $obj;

		$sql = "select event_ID,position,name,seed 
				from $table where event_ID=%d and position=%d;";
		$safe = $wpdb->prepare($sql,$pks);
		$rows = $wpdb->get_results($safe, ARRAY_A);

		error_log("Entrant::get(pks) $wpdb->num_rows rows returned.");

		if($rows.length === 1) {
			$obj = new Entrant(...$pks);
			self::mapData($obj,$rows[0]);
		}
		return $obj;
	}

	/*************** Instance Methods ****************/
	public function __construct(int $eventID, int $pos = NULL) {
		$this->isnew = TRUE;
		$this->event_ID = $eventID;
		$this->position = $pos;
		$this->init();
	}

    /**
     * Set a new value for a name of this Draw
     */
	public function setName(string $name) {
		$this->name = $name;
		$this->dirty = TRUE;
    }
    
    /**
     * Get the name of this Draw
     */
    public function getName():string {
        return $this->name;
    }

    /**
     * Assign this Entrant to an Event
     */
    public function setEventId(int $eventId) {
		if(!isset($eventID)) return false;

        if($eventId < 1) return false;
        $this->event_ID = $eventId;
        return $this->isdirty = TRUE;
    }

    /**
     * Get this Entrant's Draw id.
     */
    public function getEventId():int {
        return $this->event_ID;
    }
	
	/**
	 * Assign a position
	 */
	public function setPosition(int $pos) {
		if(!isset($pos)) return false;

		if($pos < 1) return;
		$this->position = $pos;
        return $this->isdirty = TRUE;
	}

	/**
	 * Get Position in Draw
	 */
	public function getPosition():int {
		return $this->position;
	}

	/**
	 * Seed this player(s)
	 */
	public function setSeed(int $seed) {
		if(!isset($seed) || $seed < 0) return false;

		$this->seed = $seed;
        return $this->isdirty = TRUE;
	}

	/**
	 * Get the seeding of this Entrant (player(s))
	 */
	public function getSeed():int {
		return $this->seed;
	}

	/**
	 * Get all my children!
	 * 1. Players
	 */
    public function getChildren($force=FALSE) {
		$this->retrievePlayers($force);
	}

	/**
	 * Get all Players for this Entrant.
	 */
	private function retrievePlayers($force) {
		if($this->isNew()) return;
        //if(count($this->players) === 0 || $force) $this->players = Player::find($this->event_ID);
	}

	public function isValid() {
		$isvalid = TRUE;
		if(!isset($this->event_ID)) $invalid = FALSE;
		if(!$this->isNew() && !isset($this->position))  $invalid = FALSE;
		if(!isset($this->name)) $invalid = FALSE;

		return $isvalid;
	}

	protected function create() {
		global $wpdb;

		parent::create();

		$table = $wpdb->prefix . self::$tablename;
		$wpdb->query("LOCK TABLES $table LOW_PRIORITY WRITE");
		
		$sql = "select max(position) from $table where event_ID=%d;";
		$safe = $wpdb->prepare($sql,$this->event_ID);
		$this->position = $wpdb->get_var($safe) + 1;

		$values = array( 'event_ID' => $this->event_ID
						,'position' => $this->position
						,'name' => $this->name
						,'seed' => $this->seed);
		$formats_values = array('%d','%d','%s','%d');

		$wpdb->insert($wpdb->prefix . self::$tablename, $values, $formats_values);
		$result = $wpdb->rows_affected;
		$wpdb->query("UNLOCK TABLES");

		$this->isnew = FALSE;
		$this->isdirty = FALSE;

		error_log("Entrant::create $wpdb->rows_affected rows affected.");

		return $result;
	}

	protected function update() {
		global $wpdb;
		$values;
		$formats_values;

		parent::update();
		
		$where = array( 'event_ID' => $this->event_ID
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
		//$this->event_ID = NULL;
		//$this->position = NULL;
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
		$obj->getChildren(true);	
    }

} //end class