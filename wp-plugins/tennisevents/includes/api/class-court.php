<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require('abstract-class-data.php');
//require('class-tennis-court-booking.php');

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
    private $ID;
    private $club_ID;
	private $court_type;
    
	private $bookings;

	public static $Hard = 'hard';
	public static $Clay = 'clay';
	public static $HardTrue = 'true';

    public static function search($criteria) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$sql = "select * from $table where court_type like '%%s%'";
		$safe = $wpdb->prepare($sql,$criteria);
		$rows = $wpdb->get_results($safe, ARRAY_A);
		
		error_log("Court::search $wpdb->num_rows rows returned using criteria: $criteria");

		$col = array();
		foreach($rows as $row) {
            $obj = new CourtBooking;
            self::mapData($obj,$row);
			$obj->isnew = FALSE;
			$col[] = $obj;
		}
		return $col;

    }
    
    public static function find($fk_id) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$sql = "select * from $table where club_ID = %d";
		$safe = $wpdb->prepare($sql,$fk_id);
		$rows = $wpdb->get_results($safe, ARRAY_A);
		
		error_log("Court::find $wpdb->num_rows rows returned using club_ID=$id");

		$col = array();
		foreach($rows as $row) {
            $obj = new Court;
            self::mapData($obj,$row);
			$obj->isnew = FALSE;
			$col[] = $obj;
		}
		return $col;
    }

	/**
	 * Get instance of a Court using it's ID
	 */
    static public function get($id) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$sql = "select * from $table where ID=%d";
		$safe = $wpdb->prepare($sql,$id);
		$rows = $wpdb->get_results($safe, ARRAY_A);

		error_log("Court::get(id) $wpdb->num_rows rows returned.");

		if($rows.length !== 1) {
			$obj = NULL;
		} else {
			$obj = new Court;
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

	public function setCourtType($courtType) {
		if(!is_string($courtType)) return;
		if($courtType === Court::$Hard || $courtType === Court::$Clay || $courType = Court::$HardTrue) {
			$this->court_type = $courtType;
			$this->isdirty = TRUE;
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
	 * 1. Events
	 * 2. Courts
	 */
    public function getChildren() {
        if(count($this->bookings) === 0) $this->bookings = array(); //CourtBooking::find($this->ID);
	}

	public function isValid() {
		$isvalid = TRUE;
		if(!isset($this->club_ID)) $isvalid = FALSE;


		return $isvalid;
	}

    public function save() {
		if($this->isnew) $this->create();
		elseif ($this->isdirty) $this->update();
	}

	private function create() {
		global $wpdb;

        if(!$this->isValid()) return;

        $values  = array('court_type' => $this->court_type
                        ,'club_ID'    => $this->club_ID);
		$formats_values = array('%s','%d');
		$wpdb->insert($wpdb->prefix . self::$tablename, $values, $formats_values);
		$this->ID = $wpdb->insert_id;
		$this->isnew = FALSE;

		error_log("Court::create $wpdb->rows_affected rows affected.");

		return $wpdb->rows_affected;
	}

	private function update() {
        global $wpdb;
        
        if(!$this->isValid()) return;

        $values         = array('name'    => $this->name
                               ,'club_ID' => $this->club_ID);
		$formats_values = array('%s','%d');
		$where          = array('ID' => $this->ID);
		$formats_where  = array('%d');
		$wpdb->update($wpdb->prefix . self::$tablename,$values,$where,$formats_values,$formats_where);
		$this->isdirty = FALSE;

		error_log("Court::update $wpdb->rows_affected rows affected.");

		return $wpdb->rows_affected;
	}

    public function delete() {

	}
	
	private function init() {
		$this->club_ID = NULL;
		$this->court_type = Court::$Hard;
	}
    
    /**
     * Map incoming data to an instance of Court
     */
    protected static function mapData($obj,$row) {
        $obj->ID = $row["ID"];
        $obj->club_ID = $row["club_ID"];
        $obj->court_type = $row["court_type"];
    }

} //end class