<?php
namespace datalayer;
use DateTime;
use TennisMembership;
use datalayer\appexceptions\InvalidPersonException;

// use commonlib\GW_Support;
// use utilities\CleanJsonSerializer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

enum Genders : string {
	case Male   = "Male";
	case Female = "Female";
	case Other  = "Other";
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
	public static $tablename = '';
	public const COLUMNS = <<<EOD
	ID
	,sponsor_ID
	,can_sponsor
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
	private $canSponsor;
	private $sponsorId;
	private $first_name;// varchar(45) 
	private $last_name;// varchar(45) 
	private $gender;// varchar(1) 
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
	
	/*************** Static methods ******************/
	/**
	 * Search for Persons using last name
	 * @param string $lname - The last name or first part of the last name to search for
	 * @param string $lname - The first name or first part of the first name to search for
	 * @return array Collection of Persons whose last name is 'like' the criteria
	 */
	static public function search(string $lname, string $fname = '%') {
		$loc = __CLASS__ . "::" . __FUNCTION__;

		$col = array();
		if(empty($lname)) {
			return $col;
		}

		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
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
	 */
	static public function find(...$fk_criteria) {
		$loc = __CLASS__ . '::'. __FUNCTION__;

		global $wpdb;

		$table = $wpdb->prefix . self::$tablename;
		$columns = self::COLUMNS;
		$col = array();

		if( array_key_exists( 'sponsoredBy', $fk_criteria ) ) {
			//All Persons who are sponsored by the given Person
			$col_value = $fk_criteria["sponsoredBy"];
			error_log("{$loc}: sponsor_ID=$col_value");
			$sql = "SELECT {$columns}
					FROM $table 
					WHERE sponsor_ID = %d;";
		} elseif( array_key_exists('mySponsor',$fk_criteria) ) {
			//Get the Person who sponsors the given Person
			$col_value = $fk_criteria["mySponsor"];
			error_log("{$loc}: ID=$col_value");
			$sql = "SELECT {$columns}
					FROM $table 
					WHERE ID = %d;";
		} elseif( !isset( $fk_criteria ) ) {
			//All persons
			error_log( "{$loc}: all persons" );
			$col_value = 0;
			$sql = "SELECT {$columns}
					FROM $table;";
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
		$table = $wpdb->prefix . self::$tablename;
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
	
	//Alternate ctor's
	public static function fromName(string $fname, $lname) : Person {
		$new = new Person;
		$new->setFirstName($fname);
		$new->setLastName($lname);	
		
		return $new;
	}

	/*************** Instance Methods ****************/
	private function __construct() {
		parent::__construct( true );
		self::$tablename = TennisMembership::getInstaller()->getDBTablenames()['membership_person'];

	}

	public function __destruct() {
		
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
     * Get the name of this object
     */
    public function getName() {
        return $this->getFirstName() . ' ' . $this->getLastName();
	}

	public function setAddress(Address $addr) : bool {
		$this->address = $addr;
		return $this->setDirty();
	}

	public function getAddress() : Address {
		return $this->address;
	}
	
    /**
     * Set the birthdate. 
     * This date will be stored in the db as a UTC date.
     * @param $date is local date in string in Y-m-d format
     */
    public function setBirthDate_Str( string $date = '' ) {
        $loc = __CLASS__ . ":" . __FUNCTION__;
        $result = false;
        $tz = TennisMembership::getTimeZone();
        $this->log->error_log("$loc:('{$date}')");

        if( empty( $date ) ) {
            $this->birthdate = null;
			$result = $this->setDirty();
        }
        else {
            try {
                $dt_local = new \DateTime( $date, $tz );
                $this->birthdate = $dt_local;
                $this->birthdate->setTimezone(new \DateTimeZone('UTC'));
                $result = $this->setDirty();
                return $result; //early return
            }
            catch( \Exception $ex ) {
                $this->log->error_log("$loc: failed to construct using '{$date}'");
            }

            $test = \DateTime::createFromFormat("Y-m-d G:i", $date );
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
            
            $last = \DateTIme::getLastErrors();
            if( $last['error_count'] > 0 ) {
                $arr = $last['errors'];
                $mess = '';
                foreach( $arr as $err ) {
                    $mess .= $err.':';
                }
                throw new InvalidMatchException( $mess );
            }
            elseif( $test instanceof \DateTime ) {
                $this->birthdate = $test;
                $this->birthdate->setTimezone( new \DateTimeZone('UTC'));
                $result = $this->setDirty();
            }
        }

        return $result;
    }

    public function setBirthDate_TS( int $timestamp ) {
        $loc = __CLASS__ . ":" . __FUNCTION__;
        $this->log->error_log("$loc:{$this->toString()}($timestamp)");

        if( !isset( $this->birthdate ) ) $this->birthdate = new \DateTime('now', new \DateTimeZone('UTC'));
        $this->birthdate->setTimeStamp( $timestamp );
		$this->setDirty();
    }
    
    /**
     * Get the localized DateTime object representing the birthdate
     * @return object DateTime or null
     */
    public function getBirthDateTime() : DateTime {
        if( empty( $this->birthdate ) ) return null;
        else {
            $temp = clone $this->birthdate;
            return $temp->setTimezone(TennisMembership::getTimeZone());
        }
    } 

	/**
	 * Get the birthdate in string format
	 */
	public function getBirthDate_Str() : string {
        $loc = __CLASS__ . ":" . __FUNCTION__;
        $this->log->error_log( $this->birthdate, $loc);

		if( !isset( $this->birthdate ) || is_null( $this->birthdate ) ) {
            return '';
        }
		else {
            $temp = clone $this->birthdate;
            return $temp->setTimezone(TennisMembership::getTimeZone())->format( TennisMembership::$outdateformat );
        }
	}

    /**
	 * Get the UTC birthdate in string format
	 */
	public function getBirthDateUTC_Str() : string {
        $loc = __CLASS__ . ":" . __FUNCTION__;
        $this->log->error_log( $this->birthdate, $loc);

		if( !isset( $this->birthdate ) || is_null( $this->birthdate ) ) {
            return '';
        }
		else return $this->birthdate->format( TennisMembership::$outdateformat );
	}
    
	/**
	 * Get the birth date AND time in string format
	 */
	public function getBirthDateTime_Str( int $formatNum=1) : string {
        $loc = __CLASS__ . ":" . __FUNCTION__;
        $this->log->error_log( $this->birthdate, $loc);

        $result = '';
        $format = TennisMembership::$outdatetimeformat1;
        switch($formatNum) {
            case 1:
                $format = TennisMembership::$outdatetimeformat1;
                break;
            case 2:
                $format = TennisMembership::$outdatetimeformat2;
                break;
            default:
                $format = TennisMembership::$outdatetimeformat1;
        }
        
		if( isset( $this->birthdate ) ) {
            $temp = clone $this->birthdate;
            $result = $temp->setTimezone(TennisMembership::getTimeZone())->format($format);
        }
		
        $this->log->error_log("$loc: returning {$result}");
        return $result;
	}

	/**
	 * Set the Gender
	 */
	public function getGender() : Genders {
        $loc = __CLASS__ . ":" . __FUNCTION__;
		return $this->gender;
	}

	/**
	 * Get the gender
	 */
	public function setGender(Genders $gender ) : bool {
        $loc = __CLASS__ . ":" . __FUNCTION__;
		$this->gender = $gender;
		return $this->setDirty();
	}

	/**
	 * Get the tennis USTA rating
	 */
	public function getSkillLevel() : float {
        $loc = __CLASS__ . ":" . __FUNCTION__;
		return $this->skill_level ?? 1.0;
	}

	/**
	 * Set the tennis USTA ranking
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
		if($this->validatePhoneNumberWithFilter($phone)) {
			$this->phoneHome = $phone;
			$result = $this->setDirty();
		}
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
		if($this->validatePhoneNumberWithFilter($phone)) {
			$this->phoneBusiness = $phone;
			$result = $this->setDirty();
		}
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
		if($this->validatePhoneNumberWithFilter($phone)) {
			$this->phoneMobile = $phone;
			$result = $this->setDirty();
		}
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
		$this->sponsor = Person::get($sponsorId);
		if(is_null($this->sponsor)) {
			return false;
		}
		$this->sponsorId = $sponsorId;
		return $this->setDirty();
	}

	public function getSponsorId() : int {
		return $this->sponsorId;
	}

	public function setSponsor(Person $sponsor) {
		$this->sponsor = $sponsor;
		$this->sponsorId = $sponsor->getID();
		return $this->setDirty();
	}

	public function getSponsor() : Person {
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
		elseif(isset($this->fetchMySponsor())) {
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
		return $this->canSponsor;
	}

	/**
	 * Is this Person sponsored
	 */
	public function isSponsored() : bool {
        $loc = __CLASS__ . ":" . __FUNCTION__;
		$mysponsor = self::find(array("mySponsor"=>$this->sponsorId));
		return isset($this->fetchMySponsor()) ? true : false;
	}

	/**
	 * Is this Person sponsoring others
	 */
	public function isSponsoring() : bool {
        $loc = __CLASS__ . ":" . __FUNCTION__;
		return count($this->getSponsored()) > 0;
	}

    public function toString() {
        return sprintf( "Person(%d:%s)", $this->getID(), $this->getName() );
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
			foreach($this->getSponsored() as $sp) {
				if($person->getID() == $sp->getID()) {
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
	public function removeSponsorship(Person $person) : bool {
		if( !isset( $person ) ) return false;
		
		$i=0;
		foreach( $this->getSponsored() as $sp ) {
			if($person->getID() == $sp->getID()) {
				$this->sponsoredToBeDeleted[] = $person->getID();
				unset( $this->sponsored[$i] );
				return $this->setDirty();
			}
			$i++;
		}
		return false;
	}

	public function isValid() {
		$loc = __CLASS__ ;
		$isvalid = true;
		$mess = '';
		if( !isset( $this->fname ) ) {
			$mess .= __("{$loc} must have a first name. ", TennisMembership::TEXT_DOMAIN);
		}
		
		if( !isset( $this->lname ) ) {
			$mess .= __("{$loc} must have a last name. ", TennisMembership::TEXT_DOMAIN);
		}
		
		if( !isset( $this->gender ) ) {
			$mess .= __("{$loc} must have a gender. ", TennisMembership::TEXT_DOMAIN);
		}

		if( !isset( $this->birthdate ) ) {
			$mess .= __("{$loc} must have a date of birth. ", TennisMembership::TEXT_DOMAIN);
		}
		
		if( !isset( $this->phoneHome ) ) {
			$mess .= __("{$loc} must have a home phone. ", TennisMembership::TEXT_DOMAIN);
		}
		
		if( !isset( $this->emailHome ) ) {
			$mess .= __("{$loc} must have a home email. ", TennisMembership::TEXT_DOMAIN);
		}

		if( strlen( $mess ) > 0 ) {
			throw new InvalidPersonException( $mess );
		}

		return $isvalid;
	}

	/**
	 * Delete this Person and all related data such as sponsorships, registrations, transactions, entrants, etc.
	 */
	public function delete() {
		//TODO: must delete all registrations, addresses, transactions and ?entrants?
		global $wpdb;
		$result = 0;

		//Address
		$this->getAddress()->delete();

		//Sponsorships
		foreach($this->getSponsored() as $sp) {
			$sp->delete();
		}

		//Program/Tournament entrants
		//TODO: entrants

		//Financial Transactions
		//TODO: financial transactions

		//Delete the Person
		$table = $wpdb->prefix . self::$tablename;
		$where = array( 'ID'=>$this->getID() );
		$formats_where = array( '%d' );
		$wpdb->delete( $table, $where, $formats_where );
		unset($this);
		$result += $wpdb->rows_affected;
	}

	/**
	 * Get all Persons sponsored by this Person.
	 * @return array of Person(s) who are sponsored by this Person
	 */
	private function fetchSponsored() {
		$this->sponsored = 	self::find(array("sponsoredBy"=>$this->getID()));
	}
	
	/**
	 * Get the Person who sponsors this Person
	 */
	private function fetchMySponsor() {
		$this->sponsor = self::find(array("mySponsor"=>$this->sponsorId))[0];
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
					   ,'can_sponsor'=>$this->canSponsor() ? 1 : 0
					   ,'sponsor_id'=> $this->getSponsorId()
					   ,'gender'=>$this->getGender()->value
					   ,'birthdate'=>$this->getBirthDate_Str()
					   ,'skill_level'=>$this->getSkillLevel()
					   ,'emailHome'=>$this->getHomeEmail()
					   ,'emailBusiness'=>$this->getBusinessEmail()
					   ,'phoneHome'=>$this->getHomePhone()
					   ,'phoneBusiness'=>$this->getBusinessPhone()
					   ,'phoneMobile'=>$this->getMobilePhone()
					   ,'notes'=>$this->getNotes()
		);
		$formats_values = array('%s,%s,%d,%s,%s,%s,%d,%s,%s,%s,%s,%s,%s');
		$res = $wpdb->insert($wpdb->prefix . self::$tablename, $values, $formats_values);
		
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
					   ,'can_sponsor'=>$this->canSponsor() ? 1 : 0
					   ,'sponsor_id'=> $this->getSponsorId()
					   ,'gender'=>$this->getGender()->value
					   ,'birthdate'=>$this->getBirthDate_Str()
					   ,'skill_level'=>$this->getSkillLevel()
					   ,'emailHome'=>$this->getHomeEmail()
					   ,'emailBusiness'=>$this->getBusinessEmail()
					   ,'phoneHome'=>$this->getHomePhone()
					   ,'phoneBusiness'=>$this->getBusinessPhone()
					   ,'phoneMobile'=>$this->getMobilePhone()
					   ,'notes'=>$this->getNotes()
		);
		$formats_values = array('%s,%s,%d,%s,%s,%s,%d,%s,%s,%s,%s,%s,%s');
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
     * Map incoming data to an instance of Person
     */
    protected static function mapData($obj,$row) {
        parent::mapData($obj,$row);
		$obj->canSponsor = $row['can_sponsor'] > 0 ? true: false;
		$obj->sponsorId = $row["sponsor_id"];
		$obj->first_name = $row["first_name"];
		$obj->last_name = $row["last_name"];
		$obj->gender = Genders::tryFrom($row['gender']) ?? Genders::Other;
		$obj->skill_level = $row['skill_level'];
		$obj->emailHome = $row['emailHome'];
		$obj->emailBusiness = $row['emailBusiness'];
		$obj->phoneHome = $row['phoneHome'];
		$obj->phoneBusiness = $row['phoneBusiness'];
		$obj->phoneMobile = $row['phoneMobile'];
		$obj->notes = $row["notes"];
	}
	
	private function init() {
	}

	private function manageRelatedData():int {
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

		return $result;
	}

} //end class
 