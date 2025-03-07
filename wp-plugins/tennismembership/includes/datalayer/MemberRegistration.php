<?php
namespace datalayer;

use TennisClubMembership;
use datalayer\ExternalMaping;
use DateTime;
use DateTimeZone;
use datalayer\appexceptions\InvalidRegistrationException;
use datalayer\RegistrationStatus;
use datalayer\RegStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 
 * Data and functions for MemberRegistration
 * @class  MemberRegistration
 * @package Tennis Membership
 * @version 1.0.0
 * @since   0.1.0
*/
class MemberRegistration  extends AbstractMembershipData 
{

	public static $tablename = 'registration';
	private static $membershipTypeTable =  'membershiptype';
	private static $categoryTable = 'membershipcategory';

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

public const JOINCOLUMNS = <<<EOD
reg.ID 
,reg.person_ID
,reg.season_ID
,reg.membership_type_ID
,reg.status
,reg.start_date
,reg.expiry_date
,reg.receive_emails
,reg.include_in_directory
,reg.share_email
,reg.notes 
,reg.last_update
,cat.corporate_ID
EOD;

	//DB fields
	private $personId;
	private $seasonId;
	private $regTypeId;
	private $regType;
	private $regStatus; //RegistrationStatus
	private $startDate;
	private $endDate;
	private $receiveEmails;
	private $incInDir;
	private $shareEmail;
	private $notes;

	//Properties
	private $person;
	private $membershipCategory;
	private $corporateId;
	private $external_refs = array();

	/**
	 * Find out if a user is a member in a given season
	 * @param mixed $season
	 * @param int $personId
	 * @return bool true if person is member; false otherwise
	 */
	static public function IsMember($season,int $personId) : bool {
		$loc = __CLASS__ . '::'. __FUNCTION__;

		global $wpdb;

		$table =  TennisClubMembership::getInstaller()->getDBTablenames()[self::$tablename];
		$sql = "SELECT count(*) FROM $table WHERE person_ID = %d and season_ID = %d;";
		
		$safe = $wpdb->prepare( $sql, $personId, $season );
		$count = $wpdb->get_var( $safe );
		return $count > 0;
	}

	/**
	 * Find MemberRegistrations
	 * @param array $fk_criteria (foreign keys)
	 *        ['seasonId' => nnnn]
	 *        ['seasonId' => nnnn, 'personId' => ID]
	 *        ['seasonId' => nnnn, 'membershiptype' => 'name']
	 *        ['seasonId' => nnnn, 'registrationId' => ID]
	 */
	static public function find(...$fk_criteria) {
		$loc = __CLASS__ . '::'. __FUNCTION__;

		global $wpdb;

		if(is_array($fk_criteria[0])) $fk_criteria = $fk_criteria[0];

		$table =  TennisClubMembership::getInstaller()->getDBTablenames()[self::$tablename];
		$memTypeTable = TennisClubMembership::getInstaller()->getDBTablenames()[self::$membershipTypeTable];
		$catTable = TennisClubMembership::getInstaller()->getDBTablenames()[self::$categoryTable];
		$columns = self::COLUMNS;
		$col = array();
		$seasonId = TM()->getSeason();
		if( array_key_exists( 'seasonId', $fk_criteria ) ) {
			$seasonId = $fk_criteria['seasonId'];
		}
		if( $seasonId === 0 ) {
			return $col; // Early return
		}

		if( array_key_exists( 'personId', $fk_criteria ) ) {
		//All Registrations for a given Person in a given Season
			$col_value = $fk_criteria["personId"];
			error_log("{$loc}: seasonId={$seasonId} person_Id={$col_value}");
			$sql = "SELECT {$columns}
					FROM $table 
					WHERE person_ID = %d and season_ID = %d;";
					
		} 
		elseif( array_key_exists('membershiptype',$fk_criteria) ) {
		//All Registrations of a given MembershipType by name in a specified season
			$col_value = $fk_criteria["membershiptype"];
			error_log("{$loc}: seasonID={$seasonId} registration type=$col_value");
			$columns = self::JOINCOLUMNS;
			$sql = "SELECT {$columns}
					FROM $table reg
					INNER JOIN $memTypeTable mt on reg.membership_type_ID = mt.ID 
					INNER JOIN $catTable cat on mt.category_ID = cat.ID
					WHERE reg.season_ID = %d and mt.name = '%s';";
		}
		elseif( array_key_exists('categorytype',$fk_criteria) ) {
		//All Registrations of a given Category by name in a specified season
			$col_value = $fk_criteria["categorytype"];
			error_log("{$loc}: seasonID={$seasonId} category type id=$col_value");
			$columns = self::JOINCOLUMNS;
			$sql = "SELECT {$columns}
					FROM $table reg
					INNER JOIN $memTypeTable mt on reg.membership_type_ID = mt.ID 
					INNER JOIN $catTable cat on mt.category_ID = cat.ID
					WHERE reg.season_ID = %d and mt.name = '%s';";
		} elseif( array_key_exists('registrationId',$fk_criteria) ) {
		//A given Registration in a given season
			$col_value = $fk_criteria["registrationId"];
			error_log("{$loc}: seasonId={$seasonId} registrationId=$col_value");
			$sql = "SELECT {$columns}
					FROM $table
					ON reg.
					WHERE ID = %d and season_ID = %d;";
		} elseif( !isset( $fk_criteria ) ) {
		//All Registrations for a given season
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
		$table =  TennisClubMembership::getInstaller()->getDBTablenames()[self::$tablename];
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
	 * @param int $membershipTypeId - the ID of the membership type
	 * @param int $personId - the ID of the person registering
	 * @return MemberRegistration - an incomplete instance of a Registration
	 */
	public static function fromIds(int $seasonId, int $membershipTypeId, int $personId) : self {
		$new = new static;
        if(!$new->setPersonId($personId)) {
            $mess = __("No such person with this Id", TennisClubMembership::TEXT_DOMAIN);
            throw new InvalidRegistrationException($mess);
        }
		$new->setSeasonId($seasonId);
		$new->setMembershipTypeId($membershipTypeId);
		return $new;
	}

	/**
	 * Copy ctor for MemberRegistration
	 * Copies all properties from the given Registration except for Person
	 * @param Registration $reg - the Registration to be copied
	 * @param Person $person - the person who is registering
	 * @return Registration or null if the registration to be copied is invalid
	 */
	public static function copyCtor(MemberRegistration $reg, Person $person) : ?self {
		if($reg->isValid()) {
			$new = new static;
			$new->setPerson($person);
			$new->setSeasonId($reg->getSeasonId());
			$new->setMembershipType($reg->getMembershipType());
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
    } 

    /**
     * Set the ID of ther person being registered
     * @param int $personId - the db ID of the person
     * @return bool - true if successful; false otherwise
     */
	public function setPersonId(int $personId) : bool {
		$this->personId = $personId;
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
		return $this->setDirty();
	}

	public function getPerson() : ?Person {
		if(!isset($this->person)) $this->person = Person::get($this->personId);
		return $this->person;
	}
	
	public function getPersonId() : ?int {
		return $this->personId;
	}

	public function setSeasonId(int $seasonid) : bool {
		$this->seasonId = $seasonid;
		return $this->setDirty();
	}

	public function getSeasonId() {
		return $this->seasonId;
	}

	public function setMembershipTypeId(int $regTypId) : bool {
		$this->regTypeId = $regTypId;
		$this->regType = MembershipType::get($regTypId);
		if(is_null($this->regType )) {
			$this->regTypeId = 0;
			return false;
		}
		return $this->setDirty();
	}

	public function getMembershipTypeId() : int {
		return $this->regTypeId;
	}

	public function setMembershipType(MembershipType $regtype) : bool {
		$this->regType = $regtype;
		$this->regTypeId = $this->regType->getID();
		return $this->setDirty();
	}

	public function getMembershipType() : ?MembershipType {
		if(!isset($this->regType)) $this->regType = MembershipType::get($this->regTypeId);
		return $this->regType;
	}

	public function getMembershipCategory() : string {
		if(!isset($this->regTypeId)) return '';
		if(!isset($this->regType)) $this->setMembershipType(MembershipType::get($this->regTypeId));
		return $this->getMembershipType()->getCategory();
	}

	public function getCorporateId() {
		return $this->corporateId;
	}

	public function setStatus( RegistrationStatus $newStatus, bool $force=false ) : bool {
		if($force || empty($this->regStatus)) {
			$this->regStatus = $newStatus;
		}
        elseif(!$this->regStatus->isValidTransition($newStatus)) {
            throw new InvalidRegistrationException("Unable to transition from {$this->regStatus->value} to {$newStatus->value}");
        }
        $this->regStatus = $newStatus;
		return $this->setDirty();
	}

	public function getStatus() : ?RegistrationStatus {
		return $this->regStatus;
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

		$this->notes = $notes;
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
        $this->log->error_log("$loc:('{$date}')");

        if( !empty( $date ) ) {
            $this->startDate = new DateTime($date);
			$result = $this->setDirty();
        }

        return $result;
    }
		
	/**
	 * Set the start date
     * This date will be stored in the db as a UTC date.
	 * @param DateTime $date the expiry date
	 * @return bool true if successful; false otherwise
	 */
	public function setStartDate(?DateTime $date) : bool {
        $loc = __CLASS__ . ":" . __FUNCTION__;

		$this->startDate = $date;

		return $this->setDirty();
	}
	
    public function setStartDate_TS( int $timestamp ) {
        $loc = __CLASS__ . ":" . __FUNCTION__;
        $this->log->error_log("$loc:{$this->toString()}($timestamp)");

        if( !isset( $this->startDate ) ) $this->startDate = new DateTime('now');
        $this->startDate->setTimeStamp( $timestamp );
		$this->setDirty();
    }
    
    /**
     * Get the localized DateTime object representing the start date
     * @return object DateTime or null
     */
    public function getStartDateTime() : ?DateTime {
        if( empty( $this->startDate ) || !($this->startDate instanceof DateTime)) return null;
        else {
            $temp = clone $this->startDate;
            return $temp;
        }
    } 

	/**
	 * Get the start date in string format
	 */
	public function getStartDate_Str() : string {
        $loc = __CLASS__ . ":" . __FUNCTION__;
        $this->log->error_log( $this->startDate, $loc);

		if( empty($this->startDate) || !($this->startDate instanceof DateTime)) {
            return '';
        }
		else {
            return $this->startDate->format( TennisClubMembership::$outdateformat );
        }
	}

    /**
	 * Get the UTC start date in string format
	 */
	public function getStartDateUTC_Str() : string {
        $loc = __CLASS__ . ":" . __FUNCTION__;
        $this->log->error_log( $this->startDate, $loc);

		if( empty($this->startDate) || !($this->startDate instanceof DateTime)) {
            return '';
        }
		else {
            $temp = clone $this->startDate;
			return $temp->setTimezone(new DateTimeZone('UTC'))->format( TennisClubMembership::$outdateformat );
		}
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
        
		if( $this->startDate instanceof DateTime) {
			$result = $this->startDate->format($format);
        }
		
        $this->log->error_log("$loc: returning '{$result}'");
        return $result;
	}
		
	/**
	 * Set the expiry (or end) date
     * This date will be stored in the db as a UTC date.
	 * @param DateTime $date the expiry date
	 * @return bool true if successful; false otherwise
	 */
	public function setEndDate(DateTime $date) : bool {
        $loc = __CLASS__ . ":" . __FUNCTION__;

		$this->endDate = $date;

		return $this->setDirty();
	}

    /**
     * Set the end/expiry date. 
     * This date will be stored in the db as a UTC date.
     * @param $date is local date in string in Y-m-d format
     */
    public function setEndDate_Str( string $date = '' ) {
        $loc = __CLASS__ . ":" . __FUNCTION__;
        $result = false;
        $this->log->error_log("$loc:('{$date}')");

		if(!empty($date)) {
			$this->endDate = new DateTime($date);
			$result = $this->setDirty();
		}

        return $result;
    }
		
    /**
     * Set the end/expiry date. 
     * This date will be stored in the db as a UTC date.
     * @param $date is local date represented as a timestamp
     */
    public function setEndDate_TS( int $timestamp ) {
        $loc = __CLASS__ . ":" . __FUNCTION__;
        $this->log->error_log("$loc:{$this->toString()}($timestamp)");

        if( !isset( $this->endDate ) ) $this->endDate = new \DateTime('now');
        $this->startDate->setTimeStamp( $timestamp );
		$this->setDirty();
    }
    
    /**
     * Get the localized DateTime object representing the end date
     * @return object DateTime or null
     */
    public function getEndDateTime() : ?DateTime {
        if( !isset( $this->endDate ) ) return null;
        else {
            $temp = clone $this->endDate;
            return $temp;
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
			return $this->endDate->format( TennisClubMembership::$outdateformat );
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
		else {
			$temp = clone $this->endDate;
            return $temp->setTimezone(new DateTimeZone('UTC'))->format( TennisClubMembership::$outdateformat );
		}
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
			$result = $this->endDate->format($format);
        }
		
        $this->log->error_log("$loc: returning '{$result}'");
        return $result;
	}

	/**
	 * Delete a Registration for a given Person in a given season
	 * @param int personId - the DB id of the affected person
	 * @param int $seasonId - the id of the season; defaults to current season
	 */
	public function deleteRegistration(int $personId, int $seasonId = 0 ) : int {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		error_log("{$loc}($personId, $seasonId)");

		$result = 0;
		if($seasonId < 1) {
			$seasonId = TM()->getSeason();
		}

		global $wpdb;
		$table = TennisClubMembership::getInstaller()->getDBTablenames(self::$tablename);
		if( $personId > 0 ) {
			$result = $wpdb->delete($table,array( "person_ID" => $personId, 'season_ID'=>$seasonId ),array('%d','%d'));
		}
		$result = $wpdb->rows_affected;
		if(false === $result ||  $wpdb->last_error ) {
			error_log("$loc: Last error='$wpdb->last_error'");
			$result = 0;
		}
		else {
			error_log("$loc: deleted $result rows");
		}
		return $result;
	}
		
	/**
	 * Delete this Registration
	 */
	public function delete() : int {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		error_log("{$loc}()");

		$result = 0;
		global $wpdb;
		$table = TennisClubMembership::getInstaller()->getDBTablenames(self::$tablename);
		$result = $wpdb->delete($table,array( 'ID' => $this->getID() ),array('%d'));
		if(false == $result || $wpdb->last_error ) {
			error_log("$loc: Last error='$wpdb->last_error'");
			$result = 0;
		}
		else {
			error_log("$loc: deleted $result rows");
		}
		return $result;
	}
	
	/**
	 * Delete all Registrations for a given Person in a given season
	 * @param int personId - the DB id of the affected person
	 * @param int $seasonId - the id of the season; 
	 */
	public function deleteAllForPerson(int $personId, int $seasonId ) : int {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		error_log("{$loc}($personId, $seasonId)");

		$result = 0;
		if($seasonId < 1) {
			 return 0; //$seasonId = esc_attr( get_option(TennisClubMembership::OPTION_TENNIS_SEASON, date('Y') ) ); 
		}

		global $wpdb;
		$table = TennisClubMembership::getInstaller()->getDBTablenames(self::$tablename);
		if( $personId > 0 ) {
				$result = $wpdb->delete($table,array( "person_ID" => $personId, 'season_ID'=>$seasonId ),array('%d','%d'));
		}
		$result = $wpdb->rows_affected;
		if( $wpdb->last_error ) {
			error_log("$loc: Last error='$wpdb->last_error'");
		}
		
		error_log("$loc: deleted $result rows");
		return $result;
	}
		
	/**
	 * A Registration can have zero or more external references associated with it.
	 * How these are usesd is up to the developer. 
	 * For example, a custom post type in WordPress
	 * @param string $extRef the external reference to be added to this event
	 * @return bool True if successful; false otherwise
	 */
	public function addExternalRef( string $extRef ) {
		$loc = __CLASS__ . "::" . __FUNCTION__;
		$this->log->error_log( $extRef, "$loc: External Reference Value");

		$result = false;
		if( !empty( $extRef ) ) {
			$found = false;
			foreach( $this->getExternalRefs( true ) as $er ) {
				if( $extRef === $er ) {
					$found = true;
					break;
				}
			}
			if(!$found) {
				$this->external_refs[] = $extRef;
				$result = $this->setDirty();
			}
		}
		return $result;
	}
	
	/**
	 * Remove the external reference
	 * @param string $extRef The external reference to be removed	 
	 * @return True if successful; false otherwise
	 */
	public function removeExternalRef( string $extRef ) {
		$loc = __CLASS__ . "::" . __FUNCTION__;
		$this->log->error_log("$loc: extRef='{$extRef}'");

		$result = false;
		if( !empty( $extRef ) ) {
			$i=0;
			foreach( $this->getExternalRefs() as $er ) {
				if( $extRef === $er ) {
					unset( $this->external_refs[$i] );
					$result = $this->setDirty();
					ExternalMapping::remove(self::$tablename, $this->getID(), $er );
				}
				$i++;
			}
		}
		return $result;
	}
	
	/**
	 * Get all external references associated with this Registration
	 * @param bool $force When set to true will force loading of related external references
	 *               This will cause unsaved external refernces to be lost.
	 * @param bool $single - returns single value if true; array otherwise
	 * @return array of external references or a single string reference or null
	 */
	public function getExternalRefs( $force = false, bool $single=false ) : mixed {
		$loc = __CLASS__ . "::" . __FUNCTION__;
		$this->log->error_log($loc);
		
		if( !isset( $this->external_refs ) 
		   || (0 === count( $this->external_refs))  || $force ) {
			$this->external_refs = ExternalMapping::fetchExternalRefs(self::$tablename, $this->getID());
		}
		if($single) {
			return count($this->external_refs) > 0 ? null : $this->external_refs[0];
		}
		return $this->external_refs;
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
			$isvalid = false;
		}
		
		if( !isset($this->regTypeId) ) {
			$mess .= "{$loc} must have a registration type id assigned. ";
			$isvalid = false;
		}
		
		if( !isset($this->seasonId) ) {
			$mess .= "{$loc} must have a season assigned. ";
			$isvalid = false;
		}

		if( !isset( $this->startDate ) ) {
			$mess .= "{$loc} must have a start date. ";
			$isvalid = false;
		}
		
		if( !isset( $this->endDate ) ) {
			$mess .= "{$loc} must have an end date. ";
			$isvalid = false;
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
		if(is_null($this->getMembershipType())) {
			return sprintf( "%s:(%d) unknown membership type)",$loc, $this->getID() );{
		}
		}
		return sprintf("%s:(%d)-%s)",$loc, $this->getID(), $this->getMembershipType()->toString());
    }

		
	/**
	 * Create a new Registration in the database
	 */
	protected function create() {
		$loc = __CLASS__ . "::" . __FUNCTION__;
		
		global $wpdb;

		parent::create();
		$wpdb->show_errors();

		$table = TennisClubMembership::getInstaller()->getDBTablenames()[self::$tablename];
		$this->log->error_log("$loc: table name is '$table'");

		$values = array('person_ID'=>$this->getPersonId() 
					   ,'season_ID'=>$this->seasonId
					   ,'membership_type_ID'=>$this->getMembershipTypeId()
					   ,'status'=>$this->getStatus()->value
					   ,'start_date'=>$this->getStartDateUTC_Str() 
					   ,'expiry_date'=>$this->getEndDateUTC_Str() 
					   ,'receive_emails'=>$this->getReceiveEmails() ? 1 : 0 
					   ,'include_in_directory'=>$this->getIncludeInDir() ? 1 : 0
					   ,'share_email'=>$this->getShareEmail() ? 1 : 0 
					   ,'notes'=>$this->getNotes()
		);
		
		// foreach($values as $field=>$value) {
		// 	$colres = $wpdb->get_col_length($table,$field);
		// 	if(is_wp_error($colres)) {
		// 		$mess = "$loc: $table($field) with value has improper length '{$value}'.";
		// 		$this->log->error_log($mess);
		// 		$mess .= " : Err='$mess'";
		// 		throw new InvalidRegistrationException($mess);
		// 	}
		// 	// elseif(false == $colres) {
		// 	// 	$this->log->error_log("$loc: $table($field) has has no size (it is numeric) '{$value}'.");

		// 	// }
		// 	// else {
		// 	// 	$this->log->error_log($colres,"$loc: $table($field) with value '{$value}' has size ...");
		// 	// }
		// 	$charst = $wpdb->get_col_charset($table,$field);
		// 	if(is_wp_error($charst)) {				
		// 		$mess = "$loc: $table($field) with value '{$value}' has improper char set.";
		// 		$this->log->error_log($mess);
		// 		$mess .= " : Err='$mess'";
		// 		throw new InvalidRegistrationException($mess);
		// 	}
		// }

		$formats_values = array('%d','%d','%d','%s','%s','%s','%d','%d','%d','%s');
		$res = $wpdb->insert($table, $values, $formats_values);
		
		$this->log->error_log($values,"$loc: data to be inserted...");
		if( $res === false ) {
			$mess = "$loc: wpdb->insert returned false.";
			$err = empty($wpdb->last_error) ? '' : $wpdb->last_error;
			$mess .= " : Err='$err'";
			throw new InvalidRegistrationException($mess);
		}
		if( $res === 0 ) {
			$mess = "$loc: wpdb->insert inserted 0 rows.";
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

		$table = TennisClubMembership::getInstaller()->getDBTablenames(self::$tablename);
		$values = array('person_ID'=>$this->getPersonId() 
					   ,'season_ID'=>$this->seasonId
					   ,'membership_type_ID'=>$this->getMembershipTypeId()
					   ,'status'=>$this->getStatus()->value
					   ,'start_date'=>$this->getStartDateUTC_Str() 
					   ,'expiry_date'=>$this->getEndDateUTC_Str() 
					   ,'receive_emails'=>$this->getReceiveEmails() ? 1 : 0 
					   ,'include_in_directory'=>$this->getIncludeInDir() ? 1 : 0
					   ,'share_email'=>$this->getShareEmail() ? 1 : 0 
					   ,'notes'=>$this->getNotes()
		);
		$formats_values = array('%d','%d','%d','%s','%s','%s','%d','%d','%d','%s');
		$where          = array('ID' => $this->ID);
		$formats_where  = array('%d');
		$wpdb->update($table,$values,$where,$formats_values,$formats_where);
		$this->isdirty = FALSE;
		$result = $wpdb->rows_affected;

		$result += $this->manageRelatedData();

		$this->log->error_log("{$loc}: $result rows affected.");
		return $result;
	}

	/**
	 * Manage data related to this Registration
	 */
	private function manageRelatedData() {
		return $result = 0;

		//Save the External references related to this Registration
		if( isset( $this->external_refs ) ) {
			foreach($this->external_refs as $er) {
				//Create relation between this Registration and its external references
				$result += ExternalMapping::add(self::$tablename, $this->getID(), $er );
			}
		}

		return $result;
	}

    /**
     * Map incoming data to an instance of Registration
     */
    protected static function mapData($obj,$row) {
		$loc = __CLASS__ . '::' . __FUNCTION__;

        parent::mapData($obj,$row);

		$obj->personId = $row['person_ID'];
		$obj->seasonId = $row["season_ID"]; //TODO: this must be compatible with expiryDate
		$obj->regTypeId = $row['membership_type_ID'];
		$obj->regStatus = RegistrationStatus::tryFrom($row['status']);
		$obj->receiveEmails = $row['receive_emails'] > 0 ? true : false;
		$obj->shareEmail =  $row['share_email'] > 0 ? true : false;
		$obj->incInDir = $row['include_in_directory'] > 0 ? true : false;
		$obj->notes = $row["notes"];
		$obj->corporateId = array_key_exists("corporate_ID",$row) ? $row['corporate_ID'] : 1;

		$tz = TennisClubMembership::getTimeZone();
		$obj->startDate = null;
        if( !empty($row["start_date"]) && !str_starts_with($row["start_date"],'0000')) {
            $st = new DateTime($row['start_date'], $tz);
            error_log("$loc: DateTime for start_date ...");
			error_log(print_r($st,true));
			$obj->startDate = $st;

        }
		$obj->endDate = null;
        if( !empty($row["expiry_date"]) && !str_starts_with($row["expiry_date"],'0000')) {
            $et = new DateTime($row['expiry_date'], $tz);
            error_log("$loc: DateTime for expiry_date ...");
			error_log(print_r($et,true));
			$obj->endDate = $et;
        }  
	}
} //end of class