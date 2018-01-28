<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once('abstract-class-data.php');
require_once('class-event.php');
require_once('class-court.php');

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

	//Attributes
	private $name;

	/**
	 * Collection of tennis courts
	 */
	private $courts;

	/**
	 * Collection of tennis events
	 * such as Leagues, Tournaments and Round Robins
	 */
	private $events;
	
	/*************** Static methods ******************/
	/**
	 * Search for Clubs using club name
	 */
	static public function search($criteria) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$sql = "select ID,name from $table where name like '%%s%'";
		$escd = $wpdb->esc_like( $sql );
		error_log("Escaped=$escd");
		$safe = $wpdb->prepare($sql,$criteria);
		$rows = $wpdb->get_results($safe, ARRAY_A);
		
		error_log("Club::search $wpdb->num_rows rows returned using criteria: $criteria");

		$col = array();
		foreach($rows as $row) {
			$obj = new Club;
			self::mapData($obj,$row);
			$col[] = $obj;
		}
		return $col;
	}

	/**
	 * Find Clubs referenced 
	 * as a foreign key in some other object
	 */
	static public function find(int ...$fk_criteria) {
		return array();
	}

	/**
	 * Get instance of a Club using it's primary key: ID
	 */
    static public function get(int ... $pks) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$sql = "select ID,name from $table where ID=%d";
		$safe = $wpdb->prepare($sql,$pks);
		$rows = $wpdb->get_results($safe, ARRAY_A);

		error_log("Club::get(id) $wpdb->num_rows rows returned.");
		$obj = NULL;
		if( $rows.length === 1 ) {
			$obj = new Club;
			self::mapData($obj,$rows[0]);
		}
		return $obj;
	}

	/*************** Instance Methods ****************/
	public function __construct() {
		$this->isnew = TRUE;
		$this->init();
	}

	public function setName($name) {
		if(!is_string($name) || strlen($name) < 1) return;
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
    public function getChildren($force=FALSE) {
		$this->events = $this->getEvents($force);
		$this->courts = $this->getCourts($force);
	}

	/**
	 * Get all events for this club.
	 */
	public function getEvents($force) {
		if(count($this->events) === 0 || $force) $this->events = Event::find($this->ID);
	}

	/**
	 * Get all courts in this club.
	 */
	public function getCourts($force) {
		if(count($this->courts) === 0 || $force) $this->courts = Court::find($this->ID);
	}

	public function isValid() {
		$isvalid = TRUE;
		if(!isset($this->name)) $isvalid = TRUE;

		return $isvalid;
	}
	
	protected function create() {
		global $wpdb;

		parent::create();

		$values         = array('name'=>$this->name);
		$formats_values = array('%s');
		$wpdb->insert($wpdb->prefix . self::$tablename, $values, $formats_values);
		$this->ID = $wpdb->insert_id;
		$this->isnew = FALSE;
		$this->isdirty = FALSE;

		error_log("Club::create $wpdb->rows_affected rows affected.");

		return $wpdb->rows_affected;
	}

	/**
	 * Update the Club in the database
	 */
	protected function update() {
		global $wpdb;

		parent::update();

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
        parent::mapData($obj,$row);
        $obj->name = $row["name"];
	}
	
	private function init() {
		$this->name = NULL;
	}

} //end class
 