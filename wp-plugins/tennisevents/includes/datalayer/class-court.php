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

	private $club;
    private $club_ID;
	private $court_num;
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
	public function __construct( string $courtType = self::HARD ) {
		$this->isnew = TRUE;
		$this->init();
		
		switch( $courtType ) {
			case self::HARD:
			case self::HARDTRUE:
			case self::CLAY:
				$this->court_type = $courtType;
				break;
			default:
				$this->court_tyupe = self::HARD;
		}
	}

	public function setDirty(){
		if(isset($this->club)) $this->club->setDirty();
		return parent::setDirty();
	}

	public function getCourtNumber():int {
		return isset($this->court_num) ? $this->court_num : 0;
	}

	public function setCourtType($courtType) {
		switch($courtType) {
			case self::HARD:
			case self::HARDTRUE:
			case self::CLAY:
			$this->court_type = $courtType;
			return $this->setDirty();
		}
		return false;
    }

    public function getCourtType() {
        return $this->court_type;
    }
	
	public function setClub(Club $club) {
		$this->club_ID = $club->getID();
		$this->club = $club;
        return $this->setDirty();
	}

	public function getClub() {
		if(!isset($this->club)) $this->club = Club::get($this->club_ID);
		return $this->club;
	}

    public function getClubId():int {
        return $this->club_ID;
	}
	
	/**
	 * Check to see if this Court has valid data
	 */
	public function isValid() {
		$mess = '';
		if(!isset($this->club_ID)) $mess = __("Club ID is missing for this Court.");
		if(!$this->isNew() && !isset($this->court_num)) $mess = __( "Court is missing court number!");
		if(!isset($this->court_type)) $this->court_type = self::HARD;

		if(strlen($mess) > 0 ) throw new InvalidClubException($mess);
		return true;
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
						,'court_num'  => $this->getCourtNumber()
						,'court_type' => $this->getCourtType());
		$formats_values = array('%d','%d','%s');
		$wpdb->insert($wpdb->prefix . self::$tablename, $values, $formats_values);
		$result = $wpdb->rows_affected;
		$this->isnew = false;
		$this->isdirty = false;

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
								,'court_num' => $this->getCourtNumber() );
		$formats_where  = array( '%d','%d' );
		$wpdb->update($wpdb->prefix . self::$tablename,$values,$where,$formats_values,$formats_where);
		$result = $wpdb->rows_affected;
		$this->isdirty = false;

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
		$clubId = $this->getClub()->getID();
		$crtnum = $this->getCourtNumber();
		if(isset($clubId) && isset($crtnum)) {
			$table = $wpdb->prefix . self::$tablename;
			
			$wpdb->delete($table,array('club_ID'=>$clubId,'court_num'=>$crtnum),array('%d','%d'));
			$result = $wpdb->rows_affected;
		}
		error_log("Court.delete: deleted $result");
		return $result;
	}
	
	private function init() {
		$this->club_ID = null;
		$this->club    = null;
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