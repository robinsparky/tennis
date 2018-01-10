<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require('abstract-class-data.php');
require('class-event.php');
require('class-court.php');

/** 
 * Data and functions for Tennis Club(s)
 * @class  Club
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
*/
class Club extends AbstractData
{ 
	//table name
	private static $tablename = 'tennis_club';
	private $name;
	private $ID;

	private $courts;
	private $events;
	
	/*************** Static methods ******************/
	/**
	 * Search for Clubs using club name
	 */
	static public function search($criteria) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$sql = "select * from $table where name like '%%s%'";
		$safe = $wpdb->prepare($sql,$criteria);
		$rows = $wpdb->get_results($safe, ARRAY_A);
		
		error_log("Club::search $wpdb->num_rows rows returned using criteria: $criteria");

		$col = array();
		foreach($rows as $row) {
			$obj = new Club;
			self::mapData($obj,$row);
			$obj->isnew = FALSE;
			$col[] = $obj;
		}
		return $col;
	}

	static public function find($fk_id) {
		return array();
	}

	/**
	 * Get instance of a Club using it's ID
	 */
    static public function get($id) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$sql = "select * from $table where ID=%d";
		$safe = $wpdb->prepare($sql,$id);
		$rows = $wpdb->get_results($safe, ARRAY_A);

		error_log("Club::get(id) $wpdb->num_rows rows returned.");

		if($rows.length !== 1) {
			$obj = NULL;
		} else {
			$obj = new Club;
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
	}

	public function setName($name) {
		if(strlen($name) < 2) return;
		
		$this->name = $name;
		$this->isdirty = TRUE;
	}
	
    /**
     * Get the name of this object
     */
    public function getName() {
        return $this->name;
    }

	/**
	 * Get all my children!
	 * 1. Events
	 * 2. Courts
	 */
    public function getChildren() {
		$this->events = $this->getEvents();
		$this->courts = $this->getCourts();
	}

	/**
	 * Get all events for this club.
	 */
	public function getEvents() {
		if(count($this->events) === 0) $this->events = Event::find($this->ID);
		return $this->events;
	}

	/**
	 * Get all courts in this club.
	 */
	public function getCourts() {
		if(count($this->courts) === 0) $this->events = Court::find($this->ID);
		return $this->courts;
	}

    public function save() {
		if($this->isnew) $this->create();
		elseif ($this->isdirty) $this->update();
	}

	private function create() {
		global $wpdb;

		$values         = array('name'=>$this->name);
		$formats_values = array('%s');
		$wpdb->insert($wpdb->prefix . self::$tablename, $values, $formats_values);
		$this->ID = $wpdb->insert_id;
		$this->isnew = FALSE;

		error_log("Club::create $wpdb->rows_affected rows affected.");

		return $wpdb->rows_affected;
	}

	/**
	 * Update the Club in the database
	 */
	private function update() {
		global $wpdb;

		$values         = array('name'=>$this->name);
		$formats_values = array('%s');
		$where          = array('ID' => $this->ID);
		$formats_where  = array('%d');
		$wpdb->update($wpdb->prefix . self::$tablename,$values,$where,$formats_values,$formats_where);
		$this->isdirty = FALSE;

		error_log("Club::update $wpdb->rows_affected rows affected.");
		return $wpdb->rows_affected;
	}

    public function delete() {

	}
	
    /**
     * Map incoming data to an instance of Club
     */
    protected static function mapData($obj,$row) {
        $obj->ID = $row["ID"];
        $obj->name = $row["name"];
    }

} //end class
 