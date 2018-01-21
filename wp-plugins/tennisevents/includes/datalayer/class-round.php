<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require('abstract-class-data.php');
require('class-match.php');

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
		$sql = "select ID,event_ID,round_num,comments from $table where comments like '%%s%'";
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
     */
    public static function find(... $fk_criteria) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$sql = "select event_ID,round_num,comments from $table where event_ID = %d";
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
	 * Get instance of a Round using it's primary key: event_ID, round_num
	 */
    static public function get(... $pks) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$sql = "select event_ID,round_num,comments from $table where event_ID=%d and round_num=%d;";
		$safe = $wpdb->prepare($sql,$pks);
		$rows = $wpdb->get_results($safe, ARRAY_A);

		error_log("Round::get(id) $wpdb->num_rows rows returned.");

        $obj = NULL;
		if($rows.length === 1) {
            $obj = new Round;
            self::mapData($obj,$rows[0]);
		}
		return $obj;
	}

	/*************** Instance Methods ****************/
	public function _construct() {
        $this->isnew = TRUE;
        $this->init();
    }
    
    /**
     * Assign this Round to an Event
     */
    public function setEvent($owner) {
        if(! $owner instanceof Event) return;
        $this->event = $owner;
        $this->event_ID = $owner->ID;
        $this->isdirty = TRUE;
    }

    public function getEvent() {
        return $this->event;
    }

    /**
     * Get this Round's Event id.
     */
    public function getEventId() {
        return $this->event_ID;
    }

    public function getRoundNumber(){
        return $this->round_num;
    }

    /**
     * Set this Round's comments
     */
    public function setComments($ot) {
        if(!is_string($ot)) return;
        $this->comments = $ot;
        $this->isdirty = TRUE;
    }

    public function getComments() {
        return $this->comments;
    }

	/**
	 * Get all my children!
	 * 1. Matches
	 */
    public function getChildren($force) {
        if(!isset($this->round_num)) return;
        if(count($this->matches) === 0  || $force) $this->matches = Match::find($this->event_ID,$this->round_num);
    }
    
    /**
     * Save this Round to the database
     */
    public function save() {
		if($this->isnew) $this->create();
		elseif ($this->isdirty) $this->update();
	}

	protected function create() {
        global $wpdb;
        
        parent::create();
        
		$table = $wpdb->prefix . self::$tablename;
        $wpdb->query("LOCK TABLES $table LOW_PRIORITY WRITE;");
        
		$sql = "select max(round_num) from $table where event_ID=%d;";
        $safe = $wpdb->prepare($sql,$this->event_ID);
        $this->round_num = $wpdb->get_var($safe) + 1;

        $values = array( 'event_ID' => $this->event_ID
                        ,'round_num' => $this->round_num
                        ,'comments' => $this->comments);
		$formats_values = array('%d','%d','%s');
		$wpdb->insert($wpdb->prefix . self::$tablename, $values, $formats_values);
		error_log("Round::create $wpdb->rows_affected rows affected.");
		
		$wpdb->query("UNLOCK TABLES;");
		$this->isnew = FALSE;

		return $wpdb->rows_affected;
	}

	protected function update() {
		global $wpdb;

        parent::update();

        $values = array( 'comments' => $this->comments);
		$formats_values = array('%s');
        $where          = array('event_ID' => $this->event_ID
                               ,'round_num' => $this->round_num);
		$formats_where  = array('%d','%d');
		$wpdb->update($wpdb->prefix . self::$tablename, $values, $where, $formats_values, $formats_where);
		$this->isdirty = FALSE;

		error_log("Round::update $wpdb->rows_affected rows affected.");

		return $wpdb->rows_affected;
	}

    //TODO: Add delete logic
    public function delete() {

    }

    public function isValid() {
        $isvalid = TRUE;
        if(!isset($this->evemt_ID)) $isvalid = FALSE;
        if(!$this->isNew() && !isset($this->round_num)) $isvalid = FALSE;
        
        return $isvalid;
    }
    
    /**
     * Map incoming data to an instance of Round
     */
    protected static function mapData($obj,$row) {
        parent::mapData($obj,$row);
        $obj->event_ID = $row["event_ID"];
        $obj->round_num = $row["round_num"];
        $obj->comments = $row["comments"];
        $obj->getChildren(TRUE);
    }

    private function init() {
        $this->event = NULL;
        $this->event_ID = NULL;
        $this->round_num = NULL;
        $this->comments = NULL;
    }


} //end class