<?php
namespace datalayer;

use TennisClubMembership;
use DateTime;
use DateTimeZone;
use datalayer\appexceptions\InvalidRegistrationException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

//Primary types
enum RegSuperType {
	case Players;
	case NonPlayers;
}

//Sub types
enum RegType : string {
	//--Players
    case Adult      = "Adult";
    case Couple     = "Couple";
    case Family     = "Family";
    case Student    = "Student";
    case Junior     = "Junior";
	//--Non Players
    case Parent     = "Parent";
    case Public     = "Public";
    case Staff      = "Staff";
    case Instructor = "Instructor";
}

//Registration Status
enum RegStatus : string {
	case Active    = "Active";
	case Inactivve = "Inactive";
	case Suspended = "Suspended";
}

/** 
 * Data and functions for MemberRegistration
 * @class  Registration
 * @package Tennis Membership
 * @version 1.0.0
 * @since   0.1.0
*/
class MemberRegistration  extends AbstractMembershipData 
{

	public static $tablename = '';
	private static $membershipTypeTable =  '';

	public const COLUMNS = <<<EOD
ID 
,person_ID
,season_ID
,membership_type_ID
,status
,start_date
,expiry_date
,receive_emails
,include_in_directory
,share_email
,notes 
,last_update
EOD;

	private $personId;
	private $seasonId;
	private $regTypeId;
	private $regType;
	private $regSuperType;
	private $regStatus;
	private $startDate;
	private $endDate;
	private $receiveEmails;
	private $incInDir;
	private $shareEmail;
	private $notes;

	private $person;

	/**
	 * Find MemberRegistrations
	 */
	static public function find(...$fk_criteria) {
		$loc = __CLASS__ . '::'. __FUNCTION__;

		global $wpdb;

		$table = $wpdb->prefix . self::$tablename;
		$memTypeTable = $wpdb->prefix . self::$membershipTypeTable;
		$columns = self::COLUMNS;
		$col = array();
		$seasonId = 0;
		if( array_key_exists( 'seasonId', $fk_criteria ) ) {
			$seasonId = $fk_criteria['seasonId'];
		}
		if( $seasonId === 0 ) {
			return $col; // Early return
		}

		if( array_key_exists( 'personId', $fk_criteria ) ) {
		//All Registrations for a given Person in a given Season
			$col_value = $fk_criteria["personId"];
			$seasonId = $fk_criteria['seasonId'];
			error_log("{$loc}: seasonID={$seasonId} person_ID={$col_value}");
			$sql = "SELECT {$columns}
					FROM $table 
					WHERE person_ID = %d and season_ID = %d;";
		} elseif( array_key_exists('membershiptype',$fk_criteria) ) {
		//All Registrations of a given MembershipType in a specified season
			$col_value = $fk_criteria["membershiptype"];
			error_log("{$loc}: seasonID={$seasonId} registrationID=$col_value");
			$sql = "SELECT {$columns}
					FROM $table reg
					INNER JOIN $memTypeTable mt on reg.membership_type_ID = reg.ID 
					WHERE reg.season_ID = %d and mt.name = %s;";
		} elseif( array_key_exists('registrationId',$fk_criteria) ) {
		//A given Registration in a given season
			$col_value = $fk_criteria["registrationId"];
			error_log("{$loc}: seasonID={$seasonId} registrationID=$col_value");
			$sql = "SELECT {$columns}
					FROM $table
					ON reg.
					WHERE ID = %d and season_ID = %d;";
		} elseif( !isset( $fk_criteria ) ) {
		//All Registrations for a season
			error_log( "{$loc}: seasonID={$seasonId} all registrations" );
			$col_value = 0;
			$sql = "SELECT {$columns}
					FROM $table
					WHERE ID > %d and season_ID = %d";
		}
		else {
			return $col; //Early return
		}

		$safe = $wpdb->prepare( $sql, $col_value, $seasonId );
		$rows = $wpdb->get_results( $safe, ARRAY_A );

		error_log("{$loc}: {$wpdb->num_rows} rows returned.");

		foreach($rows as $row) {
            $obj = new MemberRegistration;
            self::mapData($obj,$row);
			$col[] = $obj;
		}
		return $col;
	}

	/**
	 * Get instance of a MemberRegistration using it's primary key: ID
	 */
    static public function get(int ...$pks) {
		$loc = __CLASS__ . '::'. __FUNCTION__;

		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$columns = self::COLUMNS;
		$id = $pks[0];
		$sql = "select {$columns} from $table where ID=%d";
		$safe = $wpdb->prepare($sql,$id);
		$rows = $wpdb->get_results($safe, ARRAY_A);

		error_log("{$loc}($id) $wpdb->num_rows rows returned.");
		$obj = NULL;
		if( count($rows) === 1 ) {
			$obj = new MemberRegistration;
			self::mapData($obj,$rows[0]);
		}
		return $obj;
	}

	/**
	 * Create a registration with necessary (but insufficient) data
	 * @param int $seasonId - the numeric version of season
	 * @param RegType $regtype - the type of registration
	 * @param int $person - the ID of the person registering
	 * @return MemberRegistration - an incomplete instance of a Registration
	 */
	public static function fromIds(int $seasonId, int $regtypeId, int $personId) : self {
		$new = new static;
        if(!$new->setPersonId($personId)) {
            $mess = __("No such person with this Id", TennisClubMembership::TEXT_DOMAIN);
            throw new InvalidRegistrationException($mess);
        }
		if($seasonId < 2020) {
			$seasonId = esc_attr( get_option(TennisClubMembership::OPTION_TENNIS_SEASON, date('Y') ) ); 
		}
		$new->setSeasonId($seasonId);
		$new->setRegTypeId($regtypeId);
		$new->setStatus();
		return $new;
	}

	/**
	 * Copy ctor for MemberRegistration
	 * Copies all properties from the given Registration except for Person
	 * @param Registration $reg - the Registration to be copied
	 * @param Person $person - the person who is registering
	 * @return Registration or null if the registration to be copied is invalid
	 */
	public static function copyCtor(MemberRegistration $reg, Person $person) : self {
		if($reg->isValid()) {
			$new = new static;
			$new->setPerson($person);
			$new->setSeasonId($reg->getSeasonId());
			//$new->setRegType($reg->getRegType());
			$new->setStatus($reg->getStatus());
			$new->setStartDate_TS($reg->getStartDateTime()->format('U'));
			$new->setEndDate_TS($reg->getEndDateTime()->format('U'));
			$new->setReceiveEmails($reg->getReceiveEmails());
			$new->setShareEmail($reg->getShareEmail());
			$new->setIncludeInDir($reg->getIncludeInDir());
			$new->setNotes($reg->getNotes());
			return $new;
		}
		return null;
	}
	
	/*************** Instance Methods ****************/
    public function __construct() { 
		parent::__construct( true );
		self::$tablename = TennisClubMembership::getInstaller()->getDBTablenames()['membership_registration'];
		self::$membershipTypeTable =  TennisClubMembership::getInstaller()->getDBTablenames()['membership_membershiptype'];
    } 

    /**
     * Set the ID of ther person being registered
     * If this ID is not valid for a person then returns false
     * @param int $personId - the db ID of the person
     * @return bool - true if successful; false otherwise
     */
	public function setPersonId(int $personId) : bool {
		$this->personId = $personId;
		$this->person = Person::get($personId);
		if(is_null($this->person)) {
			$this->personId = 0;
			return false;
		}
		return $this->setDirty();
	}

	/**
	 * Set the Person object for this Registration
	 * Modifies the person ID as well.
	 * @param Person $person
	 * @return bool - true if successful, false otherwise
	 */
	public function setPerson(Person $person) : bool {
		$this->person = $person;
		$this->personId = $person->getID();
		if(is_null($this->personId) || $this->personId < 1) {
			return false;
		}
		return $this->setDirty();
	}

	public function getPerson() : Person {
		return $this->person;
	}
	
	public function getPersonId() {
		return $this->personId;
	}

	public function setSeasonId(int $seasonid) : bool {
		$this->seasonId = $seasonid;
		return $this->setDirty();
	}

	public function getSeasonId() {
		return $this->seasonId;
	}

	public function setRegTypeId(int $regTypId) : bool {
		$this->regTypeId = $regTypId;
		$this->regType = MembershipType::get($regTypId);
		if(is_null($this->regType )) {
			$this->regTypeId = 0;
			return false;
		}
		return $this->setDirty();
	}

	public function getRegTypeId() : int {
		return $this->regTypeId;
	}

	public function setRegType(MembershipType $regtype) : bool {
		$this->regType = $regtype;
		$this->regTypeId = $this->regType->getID();
		return $this->setDirty();
	}

	public function getRegType() : MembershipType {
		return $this->regType;
	}

	public function getRegSuperType() : MembershipSuperType {
		if(!isset($this->regTypeId)) return null;
		if(!isset($this->regType)) $this->setRegType(MembershipType::get($this->regTypeId));
		if(!isset($this->regSuperType)) {
			$superId = $this->getRegType()->getSuperTypeId();
			$this->regSuperType = MembershipSuperType::get($superId);
		}
		return $this->regSuperType;
	}

	public function setStatus( RegStatus $regStatus = RegStatus::Active ) {
		$this->regStatus = $regStatus;
	}

	public function getStatus() : RegStatus {
		return $this->regStatus ?? RegStatus::Active;
	}

	public function setReceiveEmails( bool $recEmails) : bool {
		$this->receiveEmails = $recEmails;
		return $this->setDirty();
	}

	public function getReceiveEmails() : bool {
		return $this->receiveEmails && false;
	}
	
	public function setIncludeInDir( bool $inc) : bool {
		$this->incInDir = $inc;
		return $this->setDirty();
	}

	public function getIncludeInDir() : bool {
		return $this->incInDir ?? false;
	}
	
	public function setShareEmail( bool $share) : bool {
		$this->shareEmail = $share;
		return $this->setDirty();
	}

	public function getShareEmail() : bool {
		return $this->shareEmail ?? false;
	}
	
	/**
	 * Set notes for this Registration
	 */
	public function setNotes(string $notes = '') {
        $loc = __CLASS__ . ":" . __FUNCTION__;

		$this->notes = htmlspecialchars($notes);
		return $this->setDirty();
	}

	/**
	 * Get the notes for this Registration
	 */
	public function getNotes() : string {
		return $this->notes ?? '';
	}
	
    /**
     * Set the start date. 
     * This date will be stored in the db as a UTC date.
     * @param $date is local date in string in Y-m-d format
     */
    public function setStartDate_Str( string $date = '' ) {
        $loc = __CLASS__ . ":" . __FUNCTION__;
        $result = false;
        $tz = TennisClubMembership::getTimeZone();
        $this->log->error_log("$loc:('{$date}')");

        if( empty( $date ) ) {
            $this->startDate = null;
			$result = $this->setDirty();
        }
        else {
            try {
                $dt_local = new DateTime( $date, $tz );
                $this->startDate = $dt_local;
                $this->startDate->setTimezone(new \DateTimeZone('UTC'));
                $result = $this->setDirty();
                return $result; //early return
            }
            catch( \Exception $ex ) {
                $this->log->error_log("$loc: failed to construct using '{$date}'");
            }

            $test = DateTime::createFromFormat("Y-m-d G:i", $date );
            if(false === $test) $test = \DateTime::createFromFormat("Y-m-d H:i", $date, $tz );
            if(false === $test) $test = \DateTime::createFromFormat("Y-m-d g:i a", $date, $tz );
            if(false === $test) $test = \DateTime::createFromFormat("Y-m-d h:i a", $date, $tz );
            if(false === $test) $test = \DateTime::createFromFormat("Y/m/d G:i", $date, $tz );
            if(false === $test) $test = \DateTime::createFromFormat("Y/m/d H:i", $date, $tz );
            if(false === $test) $test = \DateTime::createFromFormat("Y/m/d g:i a", $date, $tz );
            if(false === $test) $test = \DateTime::createFromFormat("Y/m/d h:i a", $date, $tz );
            if(false === $test) $test = \DateTime::createFromFormat("d/m/Y G:i", $date, $tz );
            if(false === $test) $test = \DateTime::createFromFormat("d/m/Y H:i", $date, $tz );
            if(false === $test) $test = \DateTime::createFromFormat("d/m/Y g:i a", $date, $tz );
            if(false === $test) $test = \DateTime::createFromFormat("d/m/Y h:i a", $date, $tz );
            
            $last = DateTIme::getLastErrors();
            if( $last['error_count'] > 0 ) {
                $arr = $last['errors'];
                $mess = '';
                foreach( $arr as $err ) {
                    $mess .= $err.':';
                }
                throw new InvalidRegistrationException( $mess );
            }
            elseif( $test instanceof \DateTime ) {
                $this->startDate = $test;
                $this->startDate->setTimezone( new \DateTimeZone('UTC'));
                $result = $this->setDirty();
            }
        }

        return $result;
    }

    public function setStartDate_TS( int $timestamp ) {
        $loc = __CLASS__ . ":" . __FUNCTION__;
        $this->log->error_log("$loc:{$this->toString()}($timestamp)");

        if( !isset( $this->startDate ) ) $this->startDate = new DateTime('now', new \DateTimeZone('UTC'));
        $this->startDate->setTimeStamp( $timestamp );
		$this->setDirty();
    }
    
    /**
     * Get the localized DateTime object representing the start date
     * @return object DateTime or null
     */
    public function getStartDateTime() : DateTime {
        if( empty( $this->startDate ) ) return null;
        else {
            $temp = clone $this->startDate;
            return $temp->setTimezone(TennisClubMembership::getTimeZone());
        }
    } 

	/**
	 * Get the start date in string format
	 */
	public function getStartDate_Str() : string {
        $loc = __CLASS__ . ":" . __FUNCTION__;
        $this->log->error_log( $this->startDate, $loc);

		if( !isset( $this->startDate ) || is_null( $this->startDate ) ) {
            return '';
        }
		else {
            $temp = clone $this->startDate;
            return $temp->setTimezone(TennisClubMembership::getTimeZone())->format( TennisClubMembership::$outdateformat );
        }
	}

    /**
	 * Get the UTC start date in string format
	 */
	public function getStartDateUTC_Str() : string {
        $loc = __CLASS__ . ":" . __FUNCTION__;
        $this->log->error_log( $this->startDate, $loc);

		if( !isset( $this->startDate ) || is_null( $this->startDate ) ) {
            return '';
        }
		else return $this->startDate->format( TennisClubMembership::$outdateformat );
	}
    
	/**
	 * Get the start date AND time in string format
	 */
	public function getStartDateTime_Str( int $formatNum=1) : string {
        $loc = __CLASS__ . ":" . __FUNCTION__;
        $this->log->error_log( $this->startDate, $loc);

        $result = '';
        $format = TennisClubMembership::$outdatetimeformat1;
        switch($formatNum) {
            case 1:
                $format = TennisClubMembership::$outdatetimeformat1;
                break;
            case 2:
                $format = TennisClubMembership::$outdatetimeformat2;
                break;
            default:
                $format = TennisClubMembership::$outdatetimeformat1;
        }
        
		if( isset( $this->startDate ) ) {
            $temp = clone $this->startDate;
            $result = $temp->setTimezone(TennisClubMembership::getTimeZone())->format($format);
        }
		
        $this->log->error_log("$loc: returning {$result}");
        return $result;
	}
		
    /**
     * Set the end date. 
     * This date will be stored in the db as a UTC date.
     * @param $date is local date in string in Y-m-d format
     */
    public function setEndDate_Str( string $date = '' ) {
        $loc = __CLASS__ . ":" . __FUNCTION__;
        $result = false;
        $tz = TennisClubMembership::getTimeZone();
        $this->log->error_log("$loc:('{$date}')");

        if( empty( $date ) ) {
            $this->endDate = null;
			$result = $this->setDirty();
        }
        else {
            try {
                $dt_local = new DateTime( $date, $tz );
                $this->endDate = $dt_local;
                $this->endDate->setTimezone(new \DateTimeZone('UTC'));
                $result = $this->setDirty();
                return $result; //early return
            }
            catch( \Exception $ex ) {
                $this->log->error_log("$loc: failed to construct using '{$date}'");
            }

            $test = DateTime::createFromFormat("Y-m-d G:i", $date );
            if(false === $test) $test = \DateTime::createFromFormat("Y-m-d H:i", $date, $tz );
            if(false === $test) $test = \DateTime::createFromFormat("Y-m-d g:i a", $date, $tz );
            if(false === $test) $test = \DateTime::createFromFormat("Y-m-d h:i a", $date, $tz );
            if(false === $test) $test = \DateTime::createFromFormat("Y/m/d G:i", $date, $tz );
            if(false === $test) $test = \DateTime::createFromFormat("Y/m/d H:i", $date, $tz );
            if(false === $test) $test = \DateTime::createFromFormat("Y/m/d g:i a", $date, $tz );
            if(false === $test) $test = \DateTime::createFromFormat("Y/m/d h:i a", $date, $tz );
            if(false === $test) $test = \DateTime::createFromFormat("d/m/Y G:i", $date, $tz );
            if(false === $test) $test = \DateTime::createFromFormat("d/m/Y H:i", $date, $tz );
            if(false === $test) $test = \DateTime::createFromFormat("d/m/Y g:i a", $date, $tz );
            if(false === $test) $test = \DateTime::createFromFormat("d/m/Y h:i a", $date, $tz );
            
            $last = DateTIme::getLastErrors();
            if( $last['error_count'] > 0 ) {
                $arr = $last['errors'];
                $mess = '';
                foreach( $arr as $err ) {
                    $mess .= $err.':';
                }
                throw new InvalidRegistrationException( $mess );
            }
            elseif( $test instanceof DateTime ) {
                $this->endDate = $test;
                $this->endDate->setTimezone( new \DateTimeZone('UTC'));
                $result = $this->setDirty();
            }
        }

        return $result;
    }

    public function setEndDate_TS( int $timestamp ) {
        $loc = __CLASS__ . ":" . __FUNCTION__;
        $this->log->error_log("$loc:{$this->toString()}($timestamp)");

        if( !isset( $this->endDate ) ) $this->endDate = new \DateTime('now', new \DateTimeZone('UTC'));
        $this->startDate->setTimeStamp( $timestamp );
		$this->setDirty();
    }
    
    /**
     * Get the localized DateTime object representing the end date
     * @return object DateTime or null
     */
    public function getEndDateTime() : DateTime {
        if( empty( $this->endDate ) ) return null;
        else {
            $temp = clone $this->endDate;
            return $temp->setTimezone(TennisClubMembership::getTimeZone());
        }
    } 

	/**
	 * Get the start date in string format
	 */
	public function getEndDate_Str() : string {
        $loc = __CLASS__ . ":" . __FUNCTION__;
        $this->log->error_log( $this->endDate, $loc);

		if( !isset( $this->endDate ) || is_null( $this->endDate ) ) {
            return '';
        }
		else {
            $temp = clone $this->endDate;
            return $temp->setTimezone(TennisClubMembership::getTimeZone())->format( TennisClubMembership::$outdateformat );
        }
	}

    /**
	 * Get the UTC end date in string format
	 */
	public function getEndDateUTC_Str() : string {
        $loc = __CLASS__ . ":" . __FUNCTION__;
        $this->log->error_log( $this->endDate, $loc);

		if( !isset( $this->endDate ) || is_null( $this->endDate ) ) {
            return '';
        }
		else return $this->endDate->format( TennisClubMembership::$outdateformat );
	}
    
	/**
	 * Get the end date AND time in string format
	 */
	public function getEndDateTime_Str( int $formatNum=1) : string {
        $loc = __CLASS__ . ":" . __FUNCTION__;
        $this->log->error_log( $this->startDate, $loc);

        $result = '';
        $format = TennisClubMembership::$outdatetimeformat1;
        switch($formatNum) {
            case 1:
                $format = TennisClubMembership::$outdatetimeformat1;
                break;
            case 2:
                $format = TennisClubMembership::$outdatetimeformat2;
                break;
            default:
                $format = TennisClubMembership::$outdatetimeformat1;
        }
        
		if( isset( $this->endDate ) ) {
            $temp = clone $this->endDate;
            $result = $temp->setTimezone(TennisClubMembership::getTimeZone())->format($format);
        }
		
        $this->log->error_log("$loc: returning {$result}");
        return $result;
	}

	/**
	 * Remove Registration for a given Person
	 */
	public function remove(int $personId, int $registrationId, int $seasonId = 0 ) : int {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		error_log("{$loc}($personId, $registrationId)");

		$result = 0;
		if($seasonId < 1) {
			$seasonId = esc_attr( get_option(TennisClubMembership::OPTION_TENNIS_SEASON, date('Y') ) ); 
		}

		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		if( $registrationId > 0 && $personId > 0 ) {
				$result = $wpdb->delete($table,array( "person_ID" => $personId, 'ID' => $registrationId, 'season_ID'=>$seasonId ),array('%d','%d','%d'));
		}
		$result = $wpdb->rows_affected;
		if( $wpdb->last_error ) {
			error_log("$loc: Last error='$wpdb->last_error'");
		}
		
		error_log("$loc: deleted $result rows");
		return $result;
	}
	
	/**
	 * Test the validity of this Registration
	 * NOTE: throws an exception on failure
	 * @return bool true if object is valid
	 */
	public function isValid() {
		$loc = __CLASS__ ;
		$isvalid = true;
		$mess = '';
		if( !isset($this->personId) ) {
			$mess .= "{$loc} must have a person assigned. ";
		}
		
		if( !isset($this->regtype) ) {
			$mess .= "{$loc} must have a registration type assigned. ";
		}
		
		if( !isset($this->seasonId) ) {
			$mess .= "{$loc} must have a season assigned. ";
		}

		if( !isset( $this->startDate ) ) {
			$mess .= "{$loc} must have a start date. ";
		}
		
		if( !isset( $this->endDate ) ) {
			$mess .= "{$loc} must have an end date. ";
		}

		if( strlen( $mess ) > 0 ) {
			throw new InvalidRegistrationException( $mess );
		}

		return $isvalid;
	}

	/**
	 * Create a label/string representing an instance of this Registration
	 */
    public function toString() {
		$loc = __CLASS__;
		if(is_null($this->getRegType())) {
			return sprintf( "%s:(%d) unknown reg type)",$loc, $this->getID() );{
		}
		}
		return sprintf( "%s:(%d)-%s/%s)",$loc, $this->getID(), $this->getRegSuperType()->toString(), $this->getRegType()->toString() );
    }

		
	/**
	 * Create a new Registration in the database
	 */
	protected function create() {
		$loc = __CLASS__ . "::" . __FUNCTION__;
		
		global $wpdb;

		parent::create();

		$values = array('person_ID'=>$this->getPersonId() 
					   ,'season_ID'=>$this->seasonId
					   ,'membership_type_ID'=>$this->getRegTypeId()
					   ,'status'=>$this->getStatus()->value
					   ,'start_date'=>$this->getStartDate_Str() 
					   ,'expiry_date'=>$this->getEndDate_Str() 
					   ,'receive_emails'=>$this->getReceiveEmails() ? 1 : 0 
					   ,'include_in_directory'=>$this->getIncludeInDir() ? 1 : 0
					   ,'share_email'=>$this->getShareEmail() ? 1 : 0 
					   ,'notes'=>$this->getNotes()
		);
		$formats_values = array('%d,%d,%s,%s,%s,%s,%d,%d,%d,%s');
		$res = $wpdb->insert($wpdb->prefix . self::$tablename, $values, $formats_values);
		
		if( $res === false || $res === 0 ) {
			$mess = "$loc: wpdb->insert returned false or inserted 0 rows.";
			$err = empty($wpdb->last_error) ? '' : $wpdb->last_error;
			$mess .= " : Err='$err'";
			throw new InvalidRegistrationException($mess);
		}
		
		$this->ID = $wpdb->insert_id;

		$result = $wpdb->rows_affected;
		$this->isnew = FALSE;
		$this->isdirty = FALSE;

		$result += $this->manageRelatedData();
		
		$this->log->error_log("{$loc}: $result rows affected.");

		return $result;
	}

	/**
	 * Update the Registration in the database
	 */
	protected function update() {
		$loc = __CLASS__ . '::' . __FUNCTION__;

		global $wpdb;

		parent::update();

		$values = array('person_ID'=>$this->getPersonId() 
					   ,'season_ID'=>$this->seasonId
					   ,'membership_type_ID'=>$this->getRegTypeId()
					   ,'status'=>$this->getStatus()->value
					   ,'start_date'=>$this->getStartDate_Str() 
					   ,'expiry_date'=>$this->getEndDate_Str() 
					   ,'receive_emails'=>$this->getReceiveEmails() ? 1 : 0 
					   ,'include_in_directory'=>$this->getIncludeInDir() ? 1 : 0
					   ,'share_email'=>$this->getShareEmail() ? 1 : 0 
					   ,'notes'=>$this->getNotes()
		);
		$formats_values = array('%d,%d,%s,%s,%s,%s,%d,%d,%d,%s');
		$where          = array('ID' => $this->ID);
		$formats_where  = array('%d');
		$wpdb->update($wpdb->prefix . self::$tablename,$values,$where,$formats_values,$formats_where);
		$this->isdirty = FALSE;
		$result = $wpdb->rows_affected;

		$result += $this->manageRelatedData();

		$this->log->error_log("{$loc}: $result rows affected.");
		return $result;
	}

	/**
	 * TODO: complete the related data processing for Registration
	 */
	private function manageRelatedData() {
		return $result = 0;
	}

    /**
     * Map incoming data to an instance of Registration
     */
    protected static function mapData($obj,$row) {
		$loc = __CLASS__ . '::' . __FUNCTION__;

        parent::mapData($obj,$row);

		$obj->personId = $row['person_ID'];
		$obj->seasonId = $row["season_ID"]; 
		$obj->regTypeId = $row['membership_type_ID'];
		$obj->regStatus = RegStatus::tryFrom($row['status']) ?? RegStatus::Active;
		$obj->receiveEmails = $row['receive_emails'] > 0 ? true : false;
		$obj->shareEmails =  $row['share_email'] > 0 ? true : false;
		$obj->incInDir = $row['inlcude_in_directory'] > 0 ? true : false;
		$obj->notes = $row["notes"];
        if( !empty($row["start_date"]) && $row["start_date"] !== '0000-00-00 00:00:00') {
            $st = new DateTime( $row["start_date"], new DateTimeZone('UTC') );
            $mess = print_r($st,true);
            error_log("$loc: DateTime for start_date ...");
            error_log($mess);
            $obj->startDate = $st;
        }
        else {
            $obj->startDate = null;
        }      
        if( !empty($row["end_date"]) && $row["end_date"] !== '0000-00-00 00:00:00') {
            $st = new DateTime( $row["end_date"], new DateTimeZone('UTC') );
            $mess = print_r($st,true);
            error_log("$loc: DateTime for start_date ...");
            error_log($mess);
            $obj->endDate = $st;
        }
        else {
            $obj->endDate = null;
        }
	}
	
	
} //end of class