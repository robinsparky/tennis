<?php
namespace datalayer;
use DateTime;
use DateTimeZone;

use TennisClubMembership;
use datalayer\Genders;
use cpt\ClubMembershipCpt;
use cpt\TennisMemberCpt;
use commonlib\GW_Support;
use datalayer\appexceptions\InvalidPersonException;


// use commonlib\GW_Support;
// use utilities\CleanJsonSerializer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Data and functions for Person(s)
 * @class  Person
 * @package Tennis Membership
 * @version 1.0.0
 * @since   0.1.0
*/
class Person extends AbstractMembershipData
{ 
	//table name
	public static $regtablename = 'registration';
	public static $tablename = 'person';
	public const COLUMNS = <<<EOD
	ID
	,corporate_ID
	,sponsor_ID
	,first_name
	,last_name
	,gender
	,birthdate
	,skill_level
	,emailHome
	,emailBusiness
	,phoneHome
	,phoneMobile
	,phoneBusiness
	,notes
	,last_update
	EOD;

	//DB fields
	private $corporateId;
	private $canSponsor;
	private $sponsorId;
	private $first_name;// varchar(45) 
	private $last_name;// varchar(45) 
	private $gender;// varchar(10) 
	private $birthdate;// date 
	private $skill_level;// decimal(4,1) 
	private $emailHome;// varchar(100) 
	private $emailBusiness;// varchar(100) 
	private $phoneHome;// varchar(45) 
	private $phoneMobile;// varchar(45) 
	private $phoneBusiness;// varchar(45) 
	private $notes;// varchar(255)

	//Properties
	private $sponsor;
	private $address;

	/**
	 * Collection of persons sponsored by this Person
	 */
	private $sponsored = array();
	private $sponsoredToBeDeleted=array();
	private $external_refs = array();
	
	/*************** Static methods ******************/
	/**
	 * Search for Persons using names
	 * @param string $lname - The last name or first part of the last name to search for
	 * @param string $lname - The first name or first part of the first name to search for defaults to all
	 * @return array Collection of Persons whose last name is 'like' the criteria and first name is 'like' the criteria
	 */
	static public function search(string $lname, string $fname = '%') {
		$loc = __CLASS__ . "::" . __FUNCTION__;

		$col = array();
		if(empty($lname)) {
			return $col;
		}

		global $wpdb;
		$table = TennisClubMembership::getInstaller()->getDBTablenames()[self::$tablename];
		$columns = self::COLUMNS;
		
		$lname .= strpos($lname,'%') ? '' : '%';
		$fname .= strpos($fname,'%') ? '' : '%';
		$sql = "select {$columns} from $table where last_name like %s and first_name like %s";
		
		$safe = $wpdb->prepare($sql,$lname,$fname);
		$rows = $wpdb->get_results($safe, ARRAY_A);
		
		error_log("{$loc}: {$wpdb->num_rows} rows returned for name search: '$lname' and '$fname'");

		foreach($rows as $row) {
			$obj = new Person;
			self::mapData($obj,$row);
			$col[] = $obj;
		}
		return $col;
	}
	
	/**
	 * Find Persons by Sponsorship
	 * @param array $fk_criteria (foreign keys). Defaults to finding all Persons in the DB
	 *              ['sponsoredBy'=> ID]
	 *              ['mySponsor'=> ID]
	 *              ['email'=> <email address>]
	 *              ['external'=> <external reference>]
	 */
	static public function find(...$fk_criteria) : array {
		$loc = __CLASS__ . '::'. __FUNCTION__;
		error_log("{$loc}...");

		global $wpdb;

		if(is_array($fk_criteria[0])) $fk_criteria = $fk_criteria[0];

		$table = TennisClubMembership::getInstaller()->getDBTablenames()[self::$tablename];
		$columns = self::COLUMNS;
		$col = array();

		if( array_key_exists( 'sponsoredBy', $fk_criteria ) ) {
			//All Persons who are sponsored by the given Person's ID
			$col_value = $fk_criteria["sponsoredBy"];
			error_log("{$loc}: sponsoredBy ID=$col_value");
			$sql = "SELECT {$columns}
					FROM $table 
					WHERE sponsor_ID = %d;";
		} elseif( array_key_exists('mySponsor',$fk_criteria) ) {
			//Get the Person who sponsors the given Person's ID
			$col_value = $fk_criteria["mySponsor"];
			error_log("{$loc}: mySponsor ID=$col_value");
			$sql = "SELECT {$columns}
					FROM $table 
					WHERE ID = %d;";
		} elseif( array_key_exists('email',$fk_criteria) ) {
			//Get the Person with given email address
			$col_value = $fk_criteria["email"];
			error_log("{$loc}: home email=$col_value");
			$sql = "SELECT {$columns}
					FROM $table 
					WHERE emailHome = '%s';";
		} elseif( array_key_exists("external",$fk_criteria)) {
			//Get the person with the given external reference
			$col_value = $fk_criteria["external"];
			$mapTable = TennisClubMembership::getInstaller()->getDBTablenames()['externalmap'];
			$subject = self::$tablename;
			error_log("{$loc}: external={$col_value}");
			$sql = "SELECT {$columns}
					FROM {$table} 
					INNER JOIN {$mapTable}
					ON ID = internal_ID
					WHERE subject = '{$subject}'
					AND external_ID ='%s'
					ORDER BY ID;";
		} elseif( !isset( $fk_criteria ) ) {
			//All persons
			error_log( "{$loc}: all persons" );
			$col_value = 0;
			$sql = "SELECT {$columns}
					FROM $table
					ORDER BY ID;";
		}
		else {
			return $col;
		}

		$safe = $wpdb->prepare( $sql, $col_value );
		$rows = $wpdb->get_results( $safe, ARRAY_A );

		error_log("{$loc}: {$wpdb->num_rows} rows returned.");

		foreach($rows as $row) {
            $obj = new Person;
            self::mapData($obj,$row);
			$col[] = $obj;
		}
		return $col;
	}

	/**
	 * Get instance of a Person using primary key: ID
	 */
    static public function get(int ...$pks) {
		$loc = __CLASS__ . '::'. __FUNCTION__;

		global $wpdb;
		$table = TennisClubMembership::getInstaller()->getDBTablenames()[self::$tablename];
		$columns = self::COLUMNS;
		$id = $pks[0];
		$sql = "select {$columns} from $table where ID=%d";
		$safe = $wpdb->prepare($sql,$id);
		$rows = $wpdb->get_results($safe, ARRAY_A);

		error_log("{$loc}($id) $wpdb->num_rows rows returned.");
		$obj = NULL;
		if( count($rows) === 1 ) {
			$obj = new Person;
			self::mapData($obj,$rows[0]);
		}
		return $obj;
	}
		
	/**
	 * Fetches one or more Registrations from the db with the given external reference
	 * @param $extReference Alphanumeric up to 100 chars
	 * @return MemberRegistration(s) linked to the external reference.
	 *         Or an array of registrations matching reference
	 *         Or Null if not found
	 */
	static public function getRegistrationByExtRef( $extReference ) {
		$loc = __CLASS__ . "::" . __FUNCTION__;

		$iids = ExternalMapping::fetchInternalIds(self::$tablename,$extReference);
		
		$result = null;
		if( count( $iids ) > 1) {
			$result = array();
			foreach( $iids as $id ) {
				$result[] = MemberRegistration::get( $id );
			}
		}
		elseif( count( $iids ) === 1 ) {
			$result = MemberRegistration::get( $iids[0] );
		}
		return $result;
	}
	
	/**
	 * Fetches one or more Registration ids from the db with the given external reference
	 * @param $extReference Alphanumeric up to 100 chars
	 * @return int Registration ID(s) matching external reference.
	 *         Or an array of event ids matching reference
	 *         Or 0 if not found
	 */
	static public function getRegistrationIdByExtRef( $extReference ) {
	
		$refs = ExternalMapping::fetchInternalIds(self::$tablename,$extReference);

		$result = 0;
		if( count( $refs ) > 1) {
			$result = array();
			foreach( $refs as $ref ) {
				$result[] = $ref;
			}
		}
		elseif( count( $refs ) === 1 ) {
			$result = $refs[0];
		}
		return $result;
	}

	/**
	 * Fetches one or more Event external refs from the db with the given an subject id
	 * @param int $id 
	 * @return string external reference or array of external refs or '' if not found
	 */
	static public function getExtRefByRegistrationId( int $id ) {

		$refs = ExternalMapping::fetchExternalRefs(self::$tablename,$id);

		$result = '';
		if( count( $refs ) > 1) {
			$result = array();
			foreach( $refs as $ref ) {
				$result[] = $ref;
			}
		}
		elseif( count( $refs ) === 1 ) {
			$result = $refs[0];
		}
		return $result;
	}
	
	//Alternate ctor's
	public static function fromName(int $corporateId, string $fname, string $lname) : Person {
		$new = new Person;
		$new->corporateId = $corporateId;
		$new->setFirstName($fname);
		$new->setLastName($lname);	
		
		return $new;
	}
	
	public static function fromEmail(int $corporateId,string $email, string $fname, string $lname) : Person {
		$new = new Person;
		$new->corporateId = $corporateId;
		$new->setFirstName($fname);
		$new->setLastName($lname);	
		$new->setHomeEmail($email);
		
		return $new;
	}

	/*************** Instance Methods ****************/
	private function __construct() {
		parent::__construct( true );
	}

	public function __destruct() {
		
	}

	public function getCorpId() : int {
		return $this->corporateId;
	}

	public function setCorpId(int $corpId) : bool {
		$this->corporateId = $corpId > 0 ? $corpId : 0;
		return $this->setDirty();
	}

	public function setFirstName($name) : bool {
		if(strlen($name) < 2) return false;
		$this->first_name = $name;
		return $this->setDirty();
	}

	public function getFirstName() {
		return $this->first_name ?? '';
	}

	
	public function setLastName(string $name) : bool {
		if(strlen($name) < 2) return false;
		$this->last_name = $name;
		return $this->setDirty();
	}
	
	public function getLastName() {
		return $this->last_name ?? '';
	}
	
    /**
     * Get the name of this Person
     */
    public function getName() {
        return $this->getFirstName() . ' ' . $this->getLastName();
	}

	/**
	 * Set the Address for this Person
	 * @param Address is the new Address for this person.
	 */
	public function setAddress(Address $addr) : bool {
		if($addr->isValid()) {
			$this->address = $addr;
			return $this->setDirty();
		}
		return false;
	}

	public function getAddress() : ?Address {
		return $this->address;
	}
	
    /**
     * Set the birthdate. 
     * This date will be stored in the db as a UTC date.
     * @param $date is local date in string in Y-m-d format
     */
    public function setBirthDate_Str( string $date = '' ) : bool {
        $loc = __CLASS__ . ":" . __FUNCTION__;
        $result = false;
        $this->log->error_log("$loc:('{$date}')");
		if(!empty($date)) {
			$this->birthdate = new DateTime($date);
			$result = $this->setDirty();
		}
		return $result;
	}

    public function setBirthDate_TS( int $timestamp ) : bool {
        $loc = __CLASS__ . ":" . __FUNCTION__;
        $this->log->error_log("$loc:{$this->toString()}($timestamp)");

        if( empty( $this->birthdate ) ) $this->birthdate = new DateTime('now', new DateTimeZone('UTC'));
        $this->birthdate->setTimeStamp( $timestamp );
		return $this->setDirty();
    }
    
    /**
     * Get the localized DateTime object representing the birthdate
     * @return object DateTime or null
     */
    public function getBirthDateTime() : ?DateTime {
        if( empty( $this->birthdate ) || !($this->birthdate instanceof DateTime)) return null;
        else {
            $temp = clone $this->birthdate;
            return $temp;
        }
    } 

	/**
	 * Get the birthdate as string
	 */
	public function getBirthDate_Str() : string {
        $loc = __CLASS__ . ":" . __FUNCTION__;
        $this->log->error_log( $this->birthdate, "$loc: birthdate...");

		if( empty($this->birthdate) || !($this->birthdate instanceof DateTime)) {
            return '';
        }
		// $temp = clone $this->birthdate;
		// return $temp->setTimezone(TennisClubMembership::getTimeZone())->format( TennisClubMembership::$outdateformat );
		return $this->birthdate->format( TennisClubMembership::$outdateformat );
	}

    /**
	 * Get the birthdate in string format converted to UTC time zone
	 */
	protected function getBirthDateUTC_Str() : string {
        $loc = __CLASS__ . ":" . __FUNCTION__;
        $this->log->error_log( $this->birthdate, $loc);

		if( empty($this->birthdate) || !($this->birthdate instanceof DateTime)) {
            return '';
        }
		else return $this->birthdate->setTimezone(new DateTimeZone('UTC'))->format( TennisClubMembership::$outdateformat );
	}
    
	/**
	 * Get the birth date AND time in string format
	 */
	public function getBirthDateTime_Str( int $formatNum=1) : string {
        $loc = __CLASS__ . ":" . __FUNCTION__;
        $this->log->error_log( $this->birthdate, $loc);

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
        
		if( !empty($this->birthdate) && ($this->birthdate instanceof DateTime) ) {
            // $temp = clone $this->birthdate;
            // $result = $temp->setTimezone(TennisClubMembership::getTimeZone())->format($format);
			return $this->birthdate->format($format);
        }
		
        $this->log->error_log("$loc: returning '{$result}'");
        return $result;
	}

	/**
	 * Get the Gender
	 */
	public function getGender() : string {
        $loc = __CLASS__ . ":" . __FUNCTION__;
		return $this->gender ?? '';
	}

	/**
	 * Set the gender
	 * @param string $gen
	 */
	public function setGender(string $gen ) : bool {
        $loc = __CLASS__ . ":" . __FUNCTION__;
		$foundIt = false;
		foreach(Genders::$genders as $g) {
			//$this->log->error_log("$loc: comparing '$gen' to '$g'");
			if($gen == $g) {
				$foundIt = true;
				break;
			}
		}
		if($foundIt) {
			//$this->log->error_log("$loc: setting gender to '$gen'");
			$this->gender = $gen;
			//$this->log->error_log("$loc: set gender to '{$this->getGender()}'");
			return $this->setDirty();
		}
		return false;
	}

	/**
	 * Get the tennis ATP rating
	 */
	public function getSkillLevel() : float {
        $loc = __CLASS__ . ":" . __FUNCTION__;
		return $this->skill_level ?? 1.0;
	}

	/**
	 * Set the tennis ATP ranking
	 */
	public function setSkillLevel( float $skill ) : bool {
        $loc = __CLASS__ . ":" . __FUNCTION__;
		$allowed = [1.0,1.5,2.0,2.5,3.0,3.5,4.0,4.5,5.0,5.5,6.0,6.5,7.0];

		$result = false;
		if( in_array($skill,$allowed) ) {
			$this->skill_level = $skill;
			$result = $this->setDirty();
		}
		return $result;	
	}

	/**
	 * Set the home email address
	 */
	public function setHomeEmail( string $email ) : bool {
        $loc = __CLASS__ . ":" . __FUNCTION__;
		
		$result = false;
		if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
			$this->log->error_log("Email address '$email' is valid.");
			$this->emailHome = $email;
			$result = $this->setDirty();
		}
		return $result;
	}

	/**
	 * Get the home email address
	 */
	public function getHomeEmail() : string {
		return $this->emailHome ?? '';
	}
	
	/**
	 * Set the business email address
	 */
	public function setBusinessEmail( string $email ) : bool {
        $loc = __CLASS__ . ":" . __FUNCTION__;
		
		$result = false;
		if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
			$this->log->error_log("Email address '$email' is valid.");
			$this->emailBusiness = $email;
			$result = $this->setDirty();
		}
		return $result;
	}

	/**
	 * Get the business email address
	 */
	public function getBusinessEmail() : string {
		return $this->emailBusiness ?? '';
	}
	
	/**
	 * Set the home phone number
	 */
	public function setHomePhone( $phone ) : bool {
        $loc = __CLASS__ . ":" . __FUNCTION__;

		$result = false;
		$this->phoneHome = $phone;
		$result = $this->setDirty();
		// if($this->validatePhoneNumberWithFilter($phone)) {
		// 	$this->phoneHome = $phone;
		// 	$result = $this->setDirty();
		// }
		return $result;
	}

	/**
	 * Get the home phone number
	 */
	public function getHomePhone() : string {
        $loc = __CLASS__ . ":" . __FUNCTION__;

		return $this->phoneHome ?? '';
	}

	/**
	 * Set the business phone number
	 */
	public function setBusinessPhone( $phone ) : bool {
        $loc = __CLASS__ . ":" . __FUNCTION__;

		$result = false;
		$this->phoneBusiness = $phone;
		$result = $this->setDirty();
		return $result;
	}

	/**
	 * Get the business phone number
	 */
	public function getBusinessPhone() : string {
        $loc = __CLASS__ . ":" . __FUNCTION__;
		
		return $this->phoneBusiness ?? '';
	}
	
	/**
	 * Set the mobile phone number
	 */
	public function setMobilePhone( $phone ) : bool {
        $loc = __CLASS__ . ":" . __FUNCTION__;

		$result = false;
		$this->phoneMobile = $phone;
		$result = $this->setDirty();
		// if($this->validatePhoneNumberWithFilter($phone)) {
		// 	$this->phoneMobile = $phone;
		// 	$result = $this->setDirty();
		// }
		return $result;
	}

	/**
	 * Get the mobile phone number
	 */
	public function getMobilePhone() : string {
		return $this->phoneMobile ?? '';
	}

	/**
	 * Set notes for this Person
	 */
	public function setNotes(string $notes = '') {
        $loc = __CLASS__ . ":" . __FUNCTION__;

		$this->notes = htmlspecialchars($notes);
		return $this->setDirty();
	}

	/**
	 * Get the notes for this Person
	 */
	public function getNotes() : string {
		return $this->notes ?? '';
	}

	public function setSponsorId(int $sponsorId) : bool {
		$test= Person::get($sponsorId);
		if(null !== $test) {
			$this->sponsor = $test;
			$this->sponsorId = $this->sponsor->getID();
		}
		if(is_null($test)) {
			$this->sponsor = null;
			$this->sponsorId = 0;
		}
		return $this->setDirty();
	}

	public function getSponsorId() : int {
		return $this->sponsorId ?? 0;
	}

	public function setSponsor(Person $sponsor) {
		$this->sponsor = $sponsor;
		$this->sponsorId = null !== $sponsor ? $sponsor->getID() : 0;
		return $this->setDirty();
	}

	public function getSponsor() : Person | null {
		if(isset($this->sponsor)) {
			return $this->sponsor;
		}
		elseif(isset($this->sponsorId)) {
			$this->sponsor = Person::get($this->sponsorId);
		}
		else {
			$this->sponsor = null;
		}
		return $this->sponsor;
	}

	/**
	 * Set whether nor not this Person can sponsor another Person
	 * If has sponsors then is set to true
	 * If has a sponsor the is set to false
	 * Otherwise value is set to the argument $can
	 * @param bool $can
	 * @return bool the final value of this property
	 */
	public function setCanSponsor(bool $can) : bool {
        $loc = __CLASS__ . ":" . __FUNCTION__;
		if(count($this->getSponsored()) > 0) {
			$this->canSponsor = true;
		}
		elseif(null === $this->fetchMySponsor()) {
			$this->canSponsor = false;
		}
		else {
			$this->canSponsor = $can;
		}
		$this->setDirty();
		return $this->canSponsor;
	}

	/**
	 * Can this Person sponsor another?
	 */
	public function canSponsor() : bool {
		return $this->canSponsor ?? __return_false();
	}

	/**
	 * Is this Person sponsored
	 */
	public function isSponsored() : bool {
        $loc = __CLASS__ . ":" . __FUNCTION__;
		return null === $this->fetchMySponsor() ? true : false;
	}

	/**
	 * Is this Person sponsoring others
	 */
	public function isSponsoring() : bool {
        $loc = __CLASS__ . ":" . __FUNCTION__;
		return count($this->getSponsored()) > 0;
	}

    public function toString() {
        return sprintf( "Person(%d) - %s)", $this->getID(), $this->getName() );
    }
	
	/**
	 * Get array of people sponsored by this Person
	 */
	public function getSponsored($force=false) {
		if(empty($this->sponsored) || $force) $this->fetchSponsored();
		return $this->sponsored;
	}
	
	/**
	 * Sponsor a Person
	 */
	public function sponsor( Person $person ) {
		$result = false;
		if($this->canSponsor()) {
			$found = false;
			foreach($this->getSponsored() as $p) {
				if($person->getID() == $p->getID()) {
					$found = true;
					break;
				}
			}
			if(!$found) {
				$this->sponsored[] = $person;
				$result = $this->setDirty();
			}
		}
		return $result;
	}

	/**
	 * Remove sponsorship from this Person
	 */
	public function removeSponsorship(int $idOfSponsored) : bool {
		if( !isset( $idOfSponsored ) ) return false;

		$i=0;
		foreach( $this->getSponsored() as $p ) {
			if($idOfSponsored == $p->getID()) {
				$this->sponsoredToBeDeleted[] = $p->getID();
				unset( $this->sponsored[$i] );
				return $this->setDirty();
			}
			$i++;
		}
		return false;
	}
		
	/**
	 * Delete all Registrations for a given Person in a given season
	 * @param int personId - the DB id of the affected person
	 * @param int $seasonId - the id of the season; 
	 */
	static public function deleteAllRegistrationsForPerson(int $personId, int $seasonId ) : int {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		error_log("{$loc}($personId, $seasonId)");

		$result = 0;
		if($seasonId < 1) {
			 return 0; //$seasonId = esc_attr( get_option(TennisClubMembership::OPTION_TENNIS_SEASON, date('Y') ) ); 
		}

		global $wpdb;
		$table = TennisClubMembership::getInstaller()->getDBTablenames(self::$regtablename);
		if( $personId > 0 ) {
			$result = $wpdb->delete($table,array( "person_ID" => $personId, 'season_ID'=>$seasonId ),array('%d','%d'));
		}
		$result = $wpdb->rows_affected;
		if( $wpdb->last_error ) {
			error_log("$loc: Last error='$wpdb->last_error'");
		}

		error_log("$loc: deleted $result rows from $table for person ID=$personId and season ID=$seasonId");
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
	 * Fetches one or more Event external refs from the db with the given an subject id
	 * @return string external reference or array of external refs or '' if not found
	 */
	public function getExtRefSingle() {

		$this->getExternalRefs();

		$result = '';
		if( count( $this->external_refs ) > 1) {
			$result = array();
			foreach( $this->external_refs as $ref ) {
				$result[] = $ref;
			}
		}
		elseif( count( $this->external_refs ) === 1 ) {
			$result = $this->external_refs[0];
		}
		return $result;
	}
	
	/**
	 * Get all external references associated with this Registration
	 * @param $force When set to true will force loading of related external references
	 *               This will cause unsaved external refernces to be lost.
	 */
	public function getExternalRefs( $force = false ) {
		$loc = __CLASS__ . "::" . __FUNCTION__;
		$this->log->error_log($loc);
		
		if( !isset( $this->external_refs ) 
		   || (0 === count( $this->external_refs))  || $force ) {
			$this->external_refs = ExternalMapping::fetchExternalRefs(self::$tablename, $this->getID());
		}
		return $this->external_refs;
	}
	

	public function isValid() : bool {
		$loc = __CLASS__ ;
		$isvalid = true;
		$mess = '';
		if( !isset( $this->first_name ) ) {
			$mess .= __("{$loc} must have a first name. ", TennisClubMembership::TEXT_DOMAIN);
			$isvalid = false;
		}
		
		if( !isset( $this->last_name ) ) {
			$mess .= __("{$loc} must have a last name. ", TennisClubMembership::TEXT_DOMAIN);
			$isvalid = false;
		}
		
		if( !isset( $this->emailHome ) ) {
			$mess .= __("{$loc} must have a home email. ", TennisClubMembership::TEXT_DOMAIN);
			$isvalid = false;
		}

		if(!isset( $this->gender )) {
			$mess .= __("{$loc} must have a gender. ", TennisClubMembership::TEXT_DOMAIN);
			$isvalid = false;
		}

		if( strlen( $mess ) > 0 ) {
			throw new InvalidPersonException( $mess );
		}

		return $isvalid;
	}

	/**
	 * Delete this Person and all related data such as sponsorships, registrations, transactions, entrants, etc.
	 * @return int the total number of rows deleted in the DB
	 */
	public function delete() : int {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log("$loc: Deleting Person ID={$this->getID()} - {$this->getName()}");
		//TODO: must delete all registrations, addresses, transactions and ?entrants?
		//TODO: What about sponsors??
		$result = 0;

		//Registrations
		$seasonId =  TM()->getSeason();
		$regs = MemberRegistration::find(array('seasonId'=>$seasonId, 'personId'=> $this->getID()));
		foreach($regs as $reg) {
			//$result += $reg->deleteAllForPerson($this->getID(), (int)$seasonId);
			$result += Person::deleteAllRegistrationsForPerson($this->getID(), (int)$seasonId);
		}

		//Delete Address
		$address = $this->getAddress();
		if($address instanceof Address) Address::delete($address->getID());

		//Delete Sponsorships
		foreach($this->getSponsored() as $sp) {
			$result += $sp->delete();
		}

		//Remove exteral references
		ExternalMapping::remove(self::$tablename, $this->getID());

		//Delete Program/Tournament entrants
		//TODO: entrants

		//Delete Financial Transactions
		//TODO: financial transactions

		global $wpdb;
		//Delete the Person
		$table = TennisClubMembership::getInstaller()->getDBTablenames()[self::$tablename];
		$where = array( 'ID'=>$this->getID() );
		$formats_where = array( '%d' );
		$wpdb->delete( $table, $where, $formats_where );
		$result += $wpdb->rows_affected;
		return $result;
	}
	

	/**
	 * Get all Persons sponsored by this Person.
	 * @return array of Person(s) who are sponsored by this Person
	 */
	private function fetchSponsored() {
		$this->sponsored = 	self::find(array("sponsoredBy" => $this->getID()));
	}
	
	/**
	 * Get the Person who sponsors this Person
	 */
	private function fetchMySponsor() {
		$this->sponsor = self::find(array("mySponsor" => $this->sponsorId))[0];
		return empty($this->sponsor) ? NULL : $this->sponsor;
	}
	
	/**
	 * Validate phone number
	 */
	private function validatePhoneNumberWithFilter($phoneNumber) {
		$pattern = '/^\+?[1-9]\d{7,14}$/';
		return filter_var($phoneNumber, FILTER_VALIDATE_REGEXP, array("options" => array("regexp" => $pattern)));
	}
	
	/**
	 * Create a new Person in the database
	 */
	protected function create() {
		$loc = __CLASS__ . "::" . __FUNCTION__;
		
		global $wpdb;

		parent::create();

		$values = array('first_name'=>$this->getFirstName()
		               ,'last_name'=>$this->getLastName()
					   ,'corporate_ID'=>$this->getCorpId()
					   ,'sponsor_id'=> $this->getSponsorId()
					   ,'gender'=>$this->getGender()
					   ,'birthdate'=>$this->getBirthDateUTC_Str()
					   ,'skill_level'=>$this->getSkillLevel()
					   ,'emailHome'=>$this->getHomeEmail()
					   ,'emailBusiness'=>$this->getBusinessEmail()
					   ,'phoneHome'=>$this->getHomePhone()
					   ,'phoneBusiness'=>$this->getBusinessPhone()
					   ,'phoneMobile'=>$this->getMobilePhone()
					   ,'notes'=>$this->getNotes()
		);
		$formats_values = array('%s','%s','%d','%d','%s','%s','%d','%s','%s','%s','%s','%s','%s');
		$table = TennisClubMembership::getInstaller()->getDBTablenames()[self::$tablename];
		$res = $wpdb->insert($table, $values, $formats_values);
		
		if( $res === false || $res === 0 ) {
			$mess = "$loc: wpdb->insert returned false or inserted 0 rows.";
			$err = empty($wpdb->last_error) ? '' : $wpdb->last_error;
			$mess .= " : Err='$err'";
			throw new InvalidPersonException($mess);
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
	 * Update the Person in the database
	 */
	protected function update() {
		$loc = __CLASS__ . '::' . __FUNCTION__;

		global $wpdb;

		parent::update();

		$values = array('first_name'=>$this->getFirstName()
		               ,'last_name'=>$this->getLastName()
					   ,'corporate_ID' => $this->getCorpId()
					   ,'sponsor_id'=> $this->getSponsorId()
					   ,'gender'=>$this->getGender()
					   ,'birthdate'=>$this->getBirthDateUTC_Str()
					   ,'skill_level'=>$this->getSkillLevel()
					   ,'emailHome'=>$this->getHomeEmail()
					   ,'emailBusiness'=>$this->getBusinessEmail()
					   ,'phoneHome'=>$this->getHomePhone()
					   ,'phoneBusiness'=>$this->getBusinessPhone()
					   ,'phoneMobile'=>$this->getMobilePhone()
					   ,'notes'=>$this->getNotes()
		);
		$formats_values = array('%s','%s','%d','%d','%s','%s','%d','%s','%s','%s','%s','%s','%s');
		$where          = array('ID' => $this->ID);
		$formats_where  = array('%d');
		$table = TennisClubMembership::getInstaller()->getDBTablenames()[self::$tablename];
		$wpdb->update($table,$values,$where,$formats_values,$formats_where);
		$this->isdirty = FALSE;
		$result = $wpdb->rows_affected;

		$result += $this->manageRelatedData();

		$this->log->error_log("{$loc}: $result rows affected.");
		return $result;
	}

	/**
	 * Maintain the data related to this person such as persons sponsored by this person
	 */
	private function manageRelatedData() : int {
		$result = 0;
		
		//Remove this person's sponsorship of those identified to be deleted
		$mapper = function( Person $p) { return $p->getID();};
		$spIds = array_map($mapper,$this->sponsored );
		for($i=0; $i < count($this->sponsoredToBeDeleted); $i++) {
			$id=$this->sponsoredToBeDeleted[$i];
			if( in_array($id, $spIds) ) {
				$person = self::get($id);
				$person->setSponsorId(0);
				$person->save();
				++$result;
			}
		}
		$this->sponsoredToBeDeleted = array();
		
		//Save the External references related to this Registration
		if( isset( $this->external_refs ) ) {
			foreach($this->external_refs as $er) {
				//Create relation between this Person and its external references
				$result += ExternalMapping::add(self::$tablename, $this->getID(), $er );
			}
		}

		return $result;
	}
	
    /**
     * Map incoming data to an instance of Person
     */
    protected static function mapData($obj,$row) {
		$loc = __CLASS__ . '::' . __FUNCTION__;

        parent::mapData($obj,$row);

		$obj->corporateId = $row['corporate_ID'];
		$obj->sponsorId = $row["sponsor_ID"];
		$obj->first_name = $row["first_name"];
		$obj->last_name = $row["last_name"];
		$obj->gender = $row['gender'];
		$obj->skill_level = $row['skill_level'];
		$obj->emailHome = $row['emailHome'];
		$obj->emailBusiness = $row['emailBusiness'];
		$obj->phoneHome = $row['phoneHome'];
		$obj->phoneBusiness = $row['phoneBusiness'];
		$obj->phoneMobile = $row['phoneMobile'];
		$obj->notes = $row["notes"];
		
		$tz = TennisClubMembership::getTimeZone();
		$obj->birthdate = null;
        if( !empty($row["birthdate"]) && !str_starts_with($row["birthdate"],'0000')) {
			$st = new DateTime($row['birthdate'],$tz);
			error_log("$loc: DateTime for birthdate ...");
			error_log(print_r($st,true));
			$obj->birthdate = $st;
		}
	}
	
} //end class
 