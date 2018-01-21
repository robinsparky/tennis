<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require('abstract-class-data.php');
//require('class-court-booking.php');

/** 
 * Data and functions for Tennis Court(s)
 * @class  Court
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
*/
class Court extends AbstractData
{ 
	//table name
	private static $tablename = 'tennis_court';

    private $club_ID;
	private $court_type;
    
	private $bookings;

	const HARD = 'hardcourt'; //plexicushion, decoturf, rebound ace, green set
	const CLAY = 'claycourt';
	const HARDTRUE = 'hardtrue';

    public static function search($criteria) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$sql = "select ID,club_ID,court_type from $table where court_type like '%%s%'";
		$safe = $wpdb->prepare($sql,$criteria);
		$rows = $wpdb->get_results($safe, ARRAY_A);
		
		error_log("Court::search $wpdb->num_rows rows returned using criteria: $criteria");

		$col = array();
		foreach($rows as $row) {
            $obj = new Court;
            self::mapData($obj,$row);
			$obj->isnew = FALSE;
			$col[] = $obj;
		}
		return $col;
    }
    
    public static function find(... $fk_criteria) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$col = array();
		$sql = "select ID,club_ID,court_type from $table where club_ID = %d";
		$safe = $wpdb->prepare($sql,$fk_criteria);
		$rows = $wpdb->get_results($safe, ARRAY_A);
		
		error_log("Court::find $wpdb->num_rows rows returned");

		foreach($rows as $row) {
            $obj = new Court;
            self::mapData($obj,$row);
			$obj->isnew = FALSE;
			$col[] = $obj;
		}
		return $col;
    }

	/**
	 * Get instance of a Court using it's primary key: ID
	 */
    static public function get(... $pks) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$sql = "select ID,club_ID,court_type from $table where ID=%d";
		$safe = $wpdb->prepare($sql,$pks);
		$rows = $wpdb->get_results($safe, ARRAY_A);

		error_log("Court::get(id) $wpdb->num_rows rows returned.");

		$obj = NULL;
		if($rows.length === 1) {
			$obj = new Court;
			self::mapData($obj,$rows[0]);
		}
		return $obj;
	}

	/*************** Instance Methods ****************/
	public function __construct() {
        $this->isnew = TRUE;
        $this->init();
	}

	public function setCourtType($courtType) {
		switch($courtype) {
			case self::HARD:
			case self::HARDTRUE:
			case self::CLAY:
			$this->court_type = $courtType;
			$this->isdirty = TRUE;
			break;
		}
    }

    public function getCourtType() {
        return $this->court_type;
    }

    public function getCourtNumber() {
        return $this->ID;
    }
    
    public function setClubId($club) {
        if(!is_numeric($club) || $club < 1) return;
        $this->club_ID = $club;
        $this->isdirty = TRUE;
    }

    public function getClubId() {
        return $this->club_ID;
    }

	/**
	 * Get all my children!
	 * 1. Bookings
	 */
    public function getChildren($force=FALSE) {
		$this->getBookings($force);
	}

	//TODO: Add court bookings...
	private function getBookings($force) {
        if(count($this->bookings) === 0 || $force) {
			$this->bookings = array(); //CourtBooking::find($this->ID);
		}
	}

	public function isValid() {
		$isvalid = TRUE;
		if(!isset($this->club_ID)) $isvalid = FALSE;
		if(!$this->isNew() && !isset($this->court_num)) $isvalid = FALSE;
		if(!isset($this->court_type)) $this->court_type = self::HARD;

		return $isvalid;
	}

	protected function create() {
		global $wpdb;

		parent::create();
		
		$table = $wpdb->prefix . self::$tablename;
		$sql = "select max(court_num) + 1
				from $table 
				where club_ID=%d;";
		$safe = $wpdb->prepare($sql,$this->club_ID);
		$this->court_num = $wpdb->get_var($safe);

        $values  = array('club_ID'    => $this->club_ID
						,'court_num' => $this->court_num
						,'court_type' => $this->court_type);
		$formats_values = array('%s','%d');
		$wpdb->insert($wpdb->prefix . self::$tablename, $values, $formats_values);
		
		$this->isnew = FALSE;

		error_log("Court::create $wpdb->rows_affected rows affected.");

		return $wpdb->rows_affected;
	}

	protected function update() {
        global $wpdb;
        
        parent::update();

        $values         = array('court_type' => $this->court_type);
		$formats_values = array('%s');
		$where          = array( 'club_ID' => $this->club_ID
								,'court_num' => $this->court_num);
		$formats_where  = array('%d','%d');
		$wpdb->update($wpdb->prefix . self::$tablename,$values,$where,$formats_values,$formats_where);
		$this->isdirty = FALSE;

		error_log("Court::update $wpdb->rows_affected rows affected.");

		return $wpdb->rows_affected;
	}

	//TODO: Add delete logic
    public function delete() {

	}
	
	private function init() {
		$this->club_ID = NULL;
		$this->court_type = self::HARD;
	}
    
    /**
     * Map incoming data to an instance of Court
     */
    protected static function mapData($obj,$row) {
        parent::mapData($obj,$row);
        $obj->club_ID = $row["club_ID"];
        $obj->court_type = $row["court_type"];
	}
	
	private function nextAvailableCourtNum() {
		global $wpdb;

	}

} //end class