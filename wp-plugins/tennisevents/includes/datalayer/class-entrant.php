<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// require_once('class-abstract.php');
// require_once('class-player.php');

/** 
 * Data and functions for Tennis Event Entrant(s)
 * @class  Entrant
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
*/
class Entrant extends AbstractData
{ 
    public static $tablename = 'tennis_entrant';
	
	//Foreign keys
	private $event_ID;
	private $bracket_num;

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
	 * When participating in a match, must determine if entrant is visitor or not
	 */
	private $is_visitor;
	
	/**
	 * 1 player for singles; 2 players for doubles;
	 */
	private $players;
    
    /**
     * Search for Entrants that have a name 'like' the provided criteria
     */
    public static function search( $criteria ) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		
		$criteria .= strpos($criteria,'%') ? '' : '%';

		$sql = "select * from $table where name like '%s'";
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
		$loc = __CLASS__ . '::' . __FUNCTION__;

		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$col = array();
		$where = array();
		
		if(isset($fk_criteria[0]) && is_array($fk_criteria[0])) $fk_criteria = $fk_criteria[0];

		if(array_key_exists( 'event_ID', $fk_criteria )
		&& array_key_exists( 'bracket_num', $fk_criteria )
		&& array_key_exists( 'round_num', $fk_criteria )
		&& array_key_exists( 'match_num', $fk_criteria ) ) {
			$where[] = $fk_criteria["event_ID"];
			$where[] = $fk_criteria["bracket_num"];
			$where[] = $fk_criteria["round_num"];
			$where[] = $fk_criteria["match_num"];
			$bracketTable = $wpdb->prefix . "tennis_bracket";
			$matchEntrantTable = $wpdb->prefix . "tennis_match_entrant";
			
			$sql = "SELECT   me.match_event_ID as event_ID
							,me.match_bracket_num as bracket_num
							,me.match_round_num as round_num 
							,me.match_num as match_num 
							,me.is_visitor as is_visitor 
							,ent.position as position 
							,ent.name as name 
							,ent.seed as seed 
					FROM $table ent 
					INNER JOIN $matchEntrantTable me on me.entrant_position=ent.position AND me.match_event_ID=ent.event_ID
					INNER JOIN $bracketTable b ON b.event_ID=me.match_event_ID AND b.bracket_num=me.match_bracket_num  
					WHERE ent.event_ID=%d 
					and   b.bracket_num=%d 
					AND   me.match_round_num=%d 
					AND   me.match_num=%d 
					ORDER BY ent.position;";
			$format = "%s(%d,%d,%d,%d) -> %d rows returned.";
		} 
		elseif(count($fk_criteria) === 4) {
				$where[] = $fk_criteria[0]; //Event
				$where[] = $fk_criteria[1]; //Bracket
				$where[] = $fk_criteria[2]; //Round
				$where[] = $fk_criteria[3]; //Match
				$bracketTable = $wpdb->prefix . "tennis_bracket";
				$matchEntrantTable = $wpdb->prefix . "tennis_match_entrant";
				
			$sql = "SELECT   me.match_event_ID as event_ID
							,me.match_bracket_num as bracket_num
							,me.match_round_num as round_num 
							,me.match_num as match_num 
							,me.is_visitor as is_visitor 
							,ent.position as position 
							,ent.name as name 
							,ent.seed as seed 
				FROM $table ent 
				INNER JOIN $matchEntrantTable me on me.entrant_position=ent.position AND me.match_event_ID=ent.event_ID
				INNER JOIN $bracketTable b ON b.event_ID=me.match_event_ID AND b.bracket_num=me.match_bracket_num  
				WHERE ent.event_ID=%d 
				and   b.bracket_num=%d 
				AND   me.match_round_num=%d 
				AND   me.match_num=%d 
				ORDER BY ent.position;";

			$format = "%s(%d,%d,%d,%d) -> %d rows returned.";
		}
		elseif( count($fk_criteria) === 2 ) {
			$where[] = $fk_criteria[0];
			$where[] = $fk_criteria[1];
			$sql = "select event_ID,bracket_num,position,name,seed 
					from $table where event_ID=%d and bracket_num=%d order by position;";
			$format = "%s(%d,%d) -> %d rows returned.";
		}
		else {
			error_log( sprintf("%s -> incorrect number of args=%d, so 0 rows returned.", $loc, $count( $fk_criteria) ) );
			return $col;
		}

		$safe = $wpdb->prepare($sql,$where);
		$rows = $wpdb->get_results($safe, ARRAY_A);
		
		if( count( $where) > 2 ) {
			error_log( sprintf($format, $loc, $where[0], $where[1], $where[2], $where[3], $wpdb->num_rows ) );
		}
		else {
			error_log( sprintf($format, $loc, $where[0], $where[1], $wpdb->num_rows ) );
		}
		
		foreach($rows as $row) {
            $obj = new Entrant;
            self::mapData( $obj, $row );
			$col[] = $obj;
		}
		return $col;
    }

	/**
	 * Get instance of a Entrant using it's primary key: event_ID, bracket_num, position
	 */
    static public function get( int ...$pks ) {
		$loc = __CLASS__ . '::' . __FUNCTION__;

		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$obj = NULL;

		if( count( $pks ) !== 3 ) return $obj;

		$sql = "select event_ID,bracket_num,position,name,seed 
				from $table where event_ID=%d and bracket_num=%d and position=%d;";
		$safe = $wpdb->prepare( $sql, $pks );
		$rows = $wpdb->get_results( $safe, ARRAY_A );

		error_log( sprintf("%s(%d,%d,%d) -> %d rows returned.", $loc, $pks[0], $pks[1], $pks[2], $wpdb->num_rows ) );

		if(count($rows) === 1) {
			$obj = new Entrant( ...$pks );
			self::mapData( $obj, $rows[0] );
		}
		return $obj;
	}

	static public function deleteEntrant( int $eventId, int $bracket_num, int $pos ) {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		
		global $wpdb;
		$result = 0;
		$table = $wpdb->prefix . self::$tablename;
		$wpdb->delete( $table, array( 'event_ID'=>$eventId, 'bracket_num'=>$bracket_num, 'position'=>$pos)
							 , array( '%d', '%d', '%d' ) );
		$result = $wpdb->rows_affected;

		error_log( sprintf( "%s(%d,%d,%d) -> deleted %d row(s)", $loc, $eventId, $bracket_num, $pos, $result ) );
		return $result;
	}


	/*************** Instance Methods ****************/
	public function __construct( int $eventID=null, int $bracket=null, string $pname=null,int $seed=null ) {
		parent::__construct ( false );
		$this->isnew = TRUE;
		$this->event_ID = $eventID;
		$this->bracket_num = $bracket;
		$this->name = $pname;
		$this->seed = $seed;
		$this->init();
	}

	public function __destruct() {
		//destroy players
		if( isset( $this->players ) ) {
			foreach( $this->players as &$player ) {
				$player = null;
			}
		}
	}

    /**
     * Set a new value for a name of this Entrant
     */
	public function setName( string $name ) {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log("$loc: setName('$name')" );

		$this->name = $name;
		$this->setDirty();
    }
    
    /**
     * Get the name of this Draw
     */
    public function getName() {
        return $this->name;
	}
	
    /**
     * Get the name of this Draw
     */
    public function getSeededName() {
        return $this->seed > 0 ? sprintf("%s(%d)",$this->name, $this->seed) : $this->name;
	}
	
	public function isVisitor() {
		$result = false;
		if( isset( $this->is_visitor ) && 1 === $this->is_visitor ) $result = true;
		return $result;
	}

    /**
     * Assign this Entrant to an Event
     */
    public function setEventId( int $eventId ) {
		$result = false;
		if(isset( $eventID )) {
			if($eventId < 1) return false;
			$this->event_ID = $eventId;
			$result = $this->setDirty();
		}
		return $result;
    }

    /**
     * Get this Entrant's Draw id.
     */
    public function getEventId():int {
        return $this->event_ID;
	}
	
    /**
     * Set the bracket number for this bracket
     */
    public function setBracketNumber( int $b ) {
		$result = false;
		if(isset($b) && $b > 0 ) {
			$this->bracket_num = $b;
			$result = $this->setDirty();
		}
		return $result;
    }

    /**
     * Get this Entrant's Draw id.
     */
    public function getBracketNumber():int {
        return $this->bracket_num;
    }
	
	/**
	 * Assign a position
	 */
	public function setPosition( int $pos ) {
		$result = false;
		if(isset($pos) && $pos > 0 ) {
			$this->position = $pos;
			$result = $this->setDirty();
		}
		return $result;
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
	public function setSeed( int $seed = 0 ) {
		$result = false;
		if( isset( $seed ) && $seed > 0 ) {
			$this->seed = $seed;
			$result = $this->setDirty();
		}
		return $result;
	}

	/**
	 * Get the seeding of this Entrant (player(s))
	 */
	public function getSeed() {
		return $this->seed;
	}
	
	public function delete() {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		global $wpdb;
		$result = 0;
		$eventId = $this->getEventId();
		$brac = $this->getBracketNumber();
		$pos = $this->getPosition();
		if( isset( $eventId ) && isset( $pos ) ) {
			$table = $wpdb->prefix . self::$tablename;
			
			$wpdb->delete( $table,array( 'event_ID'=>$eventId,'bracket_num'=>$brac,'position'=>$pos )
			                     ,array( '%d', '%d', '%d' ) );
			$result = $wpdb->rows_affected;

			$this->log->error_log( sprintf("%s(%s): deleted %d rows", $loc, $this->toString(), $result ) );
		}
		$this->setDirty();
		return $result;
	}

	/**
	 * Check to see if this Entrant has valid data
	 */
	public function isValid() {
		$pos   = isset( $this->position ) ? $this->position : '???';
		$evtId = isset( $this->event_ID ) ? $this->event_ID : '???';
		$brac  = isset( $this->bracket_num ) ? $this->bracket_num : '???';
		$name  = isset( $this->name ) ? $this->name : '???';
		$id    = sprintf("P(%d,%d,%d,%s)",$evtId,$brac, $pos, $name );
		$mess = '';
		$code = 0;

		if( !isset( $this->event_ID ) ) {
			$mess = __( "$id entrant must have an event id." );
			$code = 400;
		}
		
		if( !isset( $this->bracket_num ) ) {
			$mess = __( "$id entrant must have a bracket number within the event." );
			$code = 405;
		}

		if( !$this->isNew() && !isset( $this->position ) ) {
			$mess = __( "$id existing entrant must have a position." );
			$code = 410;
		}

		if( !isset( $this->name ) ) {
			$mess = __( "$id entrant must have a unique name." );
			$code = 420;
		}

		if( strlen( $mess ) > 0 ) throw new InvalidEntrantException( $mess, $code );

		return true;
	}

	public function toString() {
		return sprintf("P(%d,%d,%d,%s)",$this->event_ID, $this->bracket_num, $this->position, $this->name );
	}
	
	/**
	 * Return this object as an associative array
	 */
	public function toArray() {
		return ["eventId"=>$this->getEventId(),"bracket_num"=>$this->getBracketNumber(), "position"=>$this->getPosition(), "name"=>$this->getName(), "seed"=>$this->getSeed()];
	}

	/**
	 * Get all Players for this Entrant.
	 */
	private function retrievePlayers($force) {
		if($this->isNew()) return;
        //if(count($this->players) === 0 || $force) $this->players = Player::find($this->event_ID);
	}

	protected function create() {
		global $wpdb;

		parent::create();

		$table = $wpdb->prefix . self::$tablename;
		$wpdb->query("LOCK TABLES $table LOW_PRIORITY WRITE");
		
		$sql = "SELECT IFNULL(MAX(position),0) FROM $table WHERE event_ID=%d and bracket_num=%d;";
		$safe = $wpdb->prepare($sql,$this->event_ID,$this->getBracketNumber());
		$this->position = $wpdb->get_var($safe) + 1;

		$values = array( 'event_ID' => $this->getEventId()
						,'bracket_num' => $this->getBracketNumber()
						,'position' => $this->getPosition()
						,'name' => $this->getName()
						,'seed' => $this->getSeed());
		$formats_values = array('%d','%d','%d','%s','%d');

		$wpdb->insert($wpdb->prefix . self::$tablename, $values, $formats_values);
		$result = $wpdb->rows_affected;
		$wpdb->query("UNLOCK TABLES");

		$this->isnew = FALSE;
		$this->isdirty = FALSE;

		$this->log->error_log("Entrant::create $result rows affected.");

		return $result;
	}

	protected function update() {
		global $wpdb;
		$values;
		$formats_values;

		parent::update();
		
		$where = array( 'event_ID' => $this->getEventId()
					   ,'bracket_num' => $this->getBracketNumber()
					   ,'position' => $this->getPosition());
		$formats_where  = array('%d','%d','%d');


		$values = array( 'name' => $this->getName()
						,'seed' => $this->getSeed());
		$formats_values = array('%s','%d');
		
		$wpdb->update($wpdb->prefix . self::$tablename, $values, $where, $formats_values, $formats_where);
		$this->isdirty = FALSE;
		$result = $wpdb->rows_affected;

		$this->log->error_log("Entrant::update $result rows affected.");

		return $result;
	}

	private function init() {
		//$this->event_ID = NULL;
		//$this->position = NULL;
		//$this->name = NULL;
		//$this->seed = NULL;
	}
    
    /**
     * Map incoming data to an instance of Entrant
     */
    protected static function mapData($obj,$row) {
		parent::mapData($obj,$row);
		$obj->event_ID = (int)$row["event_ID"];
		$obj->bracket_num = (int)$row["bracket_num"];
		$obj->position = (int)$row["position"];
        $obj->name = $row["name"];
		$obj->seed = (int)$row["seed"];
		if( isset( $row["is_visitor"] ) ) {
			$obj->is_visitor = (int)$row["is_visitor"];
		}
    }

} //end class