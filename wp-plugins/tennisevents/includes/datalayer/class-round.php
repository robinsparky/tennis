<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 
 * Data and functions for Tennis Event Round(s)
 * @class  Round
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
*/
class Round extends AbstractData
{ 
    private static $tablename = 'tennis_round';
    
    private $event_ID;
    private $event;
    private $round_num;
    private $comments;
    
	private $matches;
    
    /**
     * Search for Rounds that have a name 'like' the provided criteria
     */
    public static function search($criteria) {
		global $wpdb;
        $table = $wpdb->prefix . self::$tablename;
        $criteria .= strpos($criteria,'%') ? '' : '%';

        $sql = "SELECT event_ID,round_num,comments 
                FROM $table WHERE comments LIKE '%s'";
		$safe = $wpdb->prepare($sql,$criteria);
		$rows = $wpdb->get_results($safe, ARRAY_A);
		
		error_log("Round::search $wpdb->num_rows rows returned using comments like: $criteria");

		$col = array();
		foreach($rows as $row) {
            $obj = new Round;
            self::mapData($obj,$row);
			$col[] = $obj;
		}
		return $col;

    }
    
    /**
     * Find all Rounds belonging to a specific Event;
     * @param $fk_criteria array of foreign keys identifying an Event
     */
    public static function find(...$fk_criteria) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$sql = "SELECT event_ID,round_num,comments FROM $table WHERE event_ID = %d";
		$safe = $wpdb->prepare($sql,$fk_criteria);
		$rows = $wpdb->get_results($safe, ARRAY_A);
		
		error_log("Round::find $wpdb->num_rows rows returned using event_ID={$fk_criteria[0]}");

		$col = array();
		foreach($rows as $row) {
            $obj = new Round;
            self::mapData($obj,$row);
			$col[] = $obj;
		}
		return $col;
    }

	/**
	 * Fetch instance of a Round using it's primary key: event_ID, round_num
	 */
    static public function get(int ...$pks) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
        $sql = "SELECT event_ID,round_num,comments 
                FROM $table WHERE event_ID=%d AND round_num=%d;";
		$safe = $wpdb->prepare($sql,$pks);
		$rows = $wpdb->get_results($safe, ARRAY_A);

		error_log("Round::get(id) $wpdb->num_rows rows returned.");

        $obj = NULL;
		if(count($rows) === 1) {
            $obj = new Round(...$pks);
            self::mapData($obj,$rows[0]);
		}
		return $obj;
	}

	/*************** Instance Methods ****************/
	public function __construct(int $event=null, int $round=null) {
        $this->isnew = TRUE;
        $this->event_ID = $event;
        $this->round_num = $round;
        $this->init();
    }

    public function __destruct() {
        $this->event = null;

        if(isset($this->matches)) {
            foreach($this->matches as &$match){
                $match = null;
            }
        }
    }
    
    /**
     * Assign this Round to an Event
     */
    public function setEvent(Event &$event) {
        $result = false;
        if(!$event->isParent()) {
            $this->event = $event;
            $this->event_ID = $event->getID();
            $result  = $this->setDirty();
        }
        return $result ;
    }

    /**
     * Get the Event to which this Round belongs
     */
    public function getEvent():Event {
        return $this->event;
    }

    /**
     *  Get the ID of the Event to which this Round belongs
     */
    public function getEventId():int {
        return $this->event_ID;
    }

    public function getRoundNumber():int {
        return $this->round_num;
    }

    /**
     * Set this Round's comments
     * Usually the "name" for the Round such as "Round 'n'"
     */
    public function setComments(string $comment) {
        $this->comments = $comment;
        $result = $this->isdirty = TRUE;
        return $result;
    }

    public function getComments():string {
        return $this->comments;
    }

    /**
     * Create a new Match and add itz to this Round using the home and visitor Entrants
     * @param $home
     * @param $visitor
     * @return true if successful; false otherwise
     */
    public function addNewMatch(Entrant $home, Entrant $visitor) {
        $result = false;
        if(isset($home) && isset($visitor)){
            $this->getMatches();
            $match = new Match($this->event_ID, $this->round_num);
            $match->setHomeEntrant($home);
            $match->setVisitorEntrant($visitor);
            $this->matches[] = $match;
            $result = $this->setDirty();
        }

        return $result;
    }

    /**
     * Add a Match to this Round
     * @param $match
     */
    public function addMatch(Match &$match) {
        $result = false;

        if(isset($match) && $match->isValid()) {
            $this->getMatches();
            $this->matches[] = $match;
            $result = $this->setDirty();
        }
        
        return $result;
    }

    /**
     * Access all Matches in this Round
     */
    public function getMatches():array {
        if(!isset($this->matches)) $this->fetchMatches();
        return $this->matches;
    }

    /**
     * Get the number of matches in this Round
     */
    public function numMatches():int {
        return count($this->getMatches());
    }

    public function isValid() {
        $mess = '';
        if(!isset($this->event_ID)) $mess = __('Event ID is missing.');
        if(!$this->isNew() && !isset($this->round_num)) $mess = __('Round number is missing.');

        if(strlen($mess) > 0) throw new InvalidRoundException($mess);
        
        return true;
    }

    /**
     * Insert this Event into the database
     */
	protected function create() {
        global $wpdb;
        
        parent::create();
        
        $table = $wpdb->prefix . self::$tablename;
        
        $wpdb->query("LOCK TABLES $table LOW_PRIORITY WRITE;");
        
		$sql = "SELECT IFNULL(MAX(round_num),0) FROM $table WHERE event_ID=%d;";
        $safe = $wpdb->prepare($sql,$this->event_ID);
        $this->round_num = $wpdb->get_var($safe) + 1;

        $values = array( 'event_ID' => $this->event_ID
                        ,'round_num' => $this->round_num
                        ,'comments' => $this->comments);
		$formats_values = array('%d','%d','%s');
		$wpdb->insert($wpdb->prefix . self::$tablename, $values, $formats_values);
        error_log("Round::create $wpdb->rows_affected rows affected.");
        $result = $wpdb->rows_affected;
		
		$wpdb->query("UNLOCK TABLES;");
        $this->isnew = FALSE;
		$this->isdirty = FALSE;
        
        foreach($this->getMatches() as $match) {
            $match->save();
        }

		return $result;
	}

    /**
     * Update this Event in the database
     */
	protected function update() {
		global $wpdb;

        parent::update();

        $values = array( 'comments' => $this->comments);
		$formats_values = array('%s');
        $where          = array('event_ID'  => $this->event_ID
                               ,'round_num' => $this->round_num);
		$formats_where  = array('%d','%d');
		$wpdb->update($wpdb->prefix . self::$tablename, $values, $where, $formats_values, $formats_where);
		$this->isdirty = FALSE;
        $result = $wpdb->rows_affected;
		error_log("Round::update $result rows affected.");

        foreach($this->getMatches() as $match) {
            $match->save();
        }

		return $result;
	}

    /**
     * Delete this round
     * Matches are deleted automatically via database Cascade
     */
    public function delete() {
		$result = 0;
		if(isset($this->event_ID) && isset($this->round_num)) {
			global $wpdb;
			$table = $wpdb->prefix . self::$tablename;

            $where = array( 'event_ID'=>$this->event_ID
                          , 'round_num=>' => $this->round_num );
			$formats_where = array( '%d','%d' );
			$wpdb->delete($table, $where, $formats_where);
			$result = $wpdb->rows_affected;
			error_log("Round.delete: deleted $result rows");
		}
		return $result;
	}
    
    /**
     * Map incoming data to an instance of Round
     */
    protected static function mapData($obj,$row) {
        parent::mapData($obj,$row);
        $obj->event_ID = $row["event_ID"];
        $obj->round_num = $row["round_num"];
        $obj->comments = $row["comments"];
    }
    
    /**
     * Fetch Matches for this Round from the database
     */
    private function fetchMatches($force=false) {
        if(!isset($this->matches) || $force) $this->matches = Match::find($this->getIdentifiers());
    }

    /**
     * Initialize attributes of this object
     */
    private function init() {
        // $this->event_ID = NULL;
        // $this->round_num = NULL;
        $this->event = NULL;
        $this->comments = NULL;
    }

} //end class