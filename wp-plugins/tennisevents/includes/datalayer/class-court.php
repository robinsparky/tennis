<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

//require_once('class-abstractdata.php');
//require_once('class-court-booking.php');

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

	private $court_num;
    private $club_ID;
	private $court_type;
    
	private $bookings;

	const HARD = 'hardcourt'; //plexicushion, decoturf, rebound ace, green set
	const CLAY = 'claycourt';
	const HARDTRUE = 'hardtrue';

    public static function search($criteria) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$sql = "select court_num,club_ID,court_type from $table where court_type like '%%s%'";
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
    
    public static function find(...$fk_criteria) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$col = array();
		$sql = "select court_num,club_ID,court_type from $table where club_ID = %d";
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
    static public function get(int ...$pks) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$sql = "select court_num,club_ID,court_type from $table where club_ID=%d and court_num=%d";
		$safe = $wpdb->prepare($sql,$pks);
		$rows = $wpdb->get_results($safe, ARRAY_A);

		error_log("Court::get(id) $wpdb->num_rows rows returned.");

		$obj = NULL;
		if(count($rows) === 1) {
			$obj = new Court;
			self::mapData($obj,$rows[0]);
		}
		return $obj;
	}
	
	/**
	 * Delete the given Court from its Club
	 */
    static public function deleteCourt(int $clubId, int $courtNum):int {
		global $wpdb;
		$result = 0;
		if(isset($clubId) && isset($courtNum)) {
			$table = $wpdb->prefix . self::$tablename;
			
			$wpdb->delete($table,array('club_ID'=>$clubId,'court_num'=>$courtNum),array('%d','%d'));
			$result = $wpdb->rows_affected;
		}
		error_log("Court.delete: deleted $result");
		return $result;
	}

	/*************** Instance Methods ****************/
	public function __construct() {
		$this->isnew = TRUE;
        $this->init();
	}

	public function getCourtNum():int {
		return isset($this->court_num) ? $this->court_num : 0;
	}

	public function setCourtType($courtType) {
		switch($courtype) {
			case self::HARD:
			case self::HARDTRUE:
			case self::CLAY:
			$this->court_type = $courtType;
			return $this->isdirty = TRUE;
		}
		return false;
    }

    public function getCourtType() {
        return $this->court_type;
    }
    
    public function setClubId(int $club) {
        if($club < 1) return false;
        $this->club_ID = $club;
        return $this->isdirty = TRUE;
    }

    public function getClubId():int {
        return $this->club_ID;
	}
	
	/**
	 * Check to see if this Court has valid data
	 */
	public function isValid() {
		$isvalid = true;
		if(!isset($this->club_ID)) $isvalid = false;
		if(!$this->isNew() && !isset($this->court_num)) $isvalid = false;
		if(!isset($this->court_type)) $this->court_type = self::HARD;
		return $isvalid;
	}

	//TODO: Add court bookings...
	private function getBookings($force) {
        if(count($this->bookings) === 0 || $force) {
			$this->bookings = array(); //CourtBooking::find($this->ID);
		}
	}

	protected function create() {
		global $wpdb;

		parent::create();
		
		$table = $wpdb->prefix . self::$tablename;
		$sql = "SELECT IFNULL(MAX(court_num),0)
				FROM $table 
				WHERE club_ID=%d;";
		$safe = $wpdb->prepare($sql,$this->getClubId());
		$max = $wpdb->get_var($safe);
		error_log("Max court number=$max");
		$this->court_num = $max + 1;

        $values  = array('club_ID'    => $this->getClubId()
						,'court_num'  => $this->getCourtNum()
						,'court_type' => $this->getCourtType());
		$formats_values = array('%d','%d','%s');
		$wpdb->insert($wpdb->prefix . self::$tablename, $values, $formats_values);
		$result = $wpdb->rows_affected;
		$this->isnew = FALSE;
		$this->isdirty = FALSE;

		//TODO Add saving of court bookings

		error_log("Court::create $result rows affected.");

		return $result;
	}

	protected function update() {
        global $wpdb;
        
        parent::update();

        $values         = array( 'court_type' => $this->getCourtType());
		$formats_values = array( '%s' );
		$where          = array( 'club_ID' => $this->getClubId()
								,'court_num' => $this->getCourtNum() );
		$formats_where  = array( '%d','%d' );
		$wpdb->update($wpdb->prefix . self::$tablename,$values,$where,$formats_values,$formats_where);
		$result = $wpdb->rows_affected;
		$this->isdirty = FALSE;

		//TODO Add saving of court bookings

		error_log("Court::update $result rows affected.");

		return $result;
	}
	
	/**
	 * Delete the given Court from its Club
	 */
    public function delete():int {
		global $wpdb;
		$result = 0;
		$clubId = $this->getClubId();
		$crtnum = $this->getCourtNum();
		if(isset($clubId) && isset($crtnum)) {
			$table = $wpdb->prefix . self::$tablename;
			
			$wpdb->delete($table,array('club_ID'=>$clubId,'court_num'=>$crtnum),array('%d','%d'));
			$result = $wpdb->rows_affected;
		}
		error_log("Court.delete: deleted $result");
		return $result;
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
		$obj->court_num = $row["court_num"];
        $obj->club_ID = $row["club_ID"];
        $obj->court_type = $row["court_type"];
	}

} //end class