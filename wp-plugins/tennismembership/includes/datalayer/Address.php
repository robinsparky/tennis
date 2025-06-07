<?php
namespace datalayer;

use TennisClubMembership;
use datalayer\appexceptions\InvalidAddressException;
use \DateTime;
use \DateTimeZone;
use tidy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 
 * Data and functions for Address
 * @class  Address
 * @package Tennis Membership
 * @version 1.0.0
 * @since   0.1.0
*/
class Address  extends AbstractMembershipData 
{

    public static $tablename = 'address';

	public const COLUMNS = <<<EOD
ID 			
,owner_ID
,addr1
,addr2
,city
,province
,country
,postal_code
,last_update
EOD;

    //DB fields
    private $ownerId;
    private $addr1;
    private $addr2;
    private $city;
    private $province;
    private $country;
    private $postal_code;

    /**
     * Find collection of Addresses for a given person
     */
    public static function find(...$fk_criteria) {
        $loc = __CLASS__ . '::' . __FUNCTION__;

        global $wpdb;
        if(is_array($fk_criteria[0])) $fk_criteria = $fk_criteria[0];
        $table = TennisClubMembership::getInstaller()->getDBTablenames()[self::$tablename];
        $columns = self::COLUMNS;
        $col = array();
        $sql = "select {$columns} from $table where owner_ID = %d";

        $safe = $wpdb->prepare($sql,$fk_criteria);
        $rows = $wpdb->get_results($safe, ARRAY_A);
        
        error_log("{$loc} $wpdb->num_rows rows returned");

        foreach($rows as $row) {
            $obj = new Address;
            self::mapData($obj,$row);
            $obj->isnew = FALSE;
            $col[] = $obj;
        }
        return $col;
    }

    /**
     * Get instance of a Address using it's primary key: ID
     */
    static public function get(int ...$pks) {
        $loc = __CLASS__ . '::' . __FUNCTION__;

        global $wpdb;
        $table = TennisClubMembership::getInstaller()->getDBTablenames()[self::$tablename];
        $columns = self::COLUMNS;
        $sql = "select {$columns} from $table where ID=%d";
        $safe = $wpdb->prepare($sql,$pks);
        $rows = $wpdb->get_results($safe, ARRAY_A);

        error_log("{$loc} $wpdb->num_rows rows returned.");

        $obj = NULL;
        if(count($rows) === 1) {
            $obj = new Address;
            self::mapData($obj,$rows[0]);
        }
        return $obj;
    }
    
    /**
     * Delete the Address
     * */	
    public static function delete(int $addId) : int {
        
		global $wpdb;
		$result = 0;
	
        $table = TennisClubMembership::getInstaller()->getDBTablenames()[self::$tablename];
		$where = array( 'ID'=>$addId );
		$formats_where = array( '%d' );
		$wpdb->delete( $table, $where, $formats_where );
		$result += $wpdb->rows_affected;
        return $result;
    }

    public static function fromData(int $ownerId, string $addr2, string $city) :self {
        $new = new Address;
        $new->setOwnerId($ownerId);
        $new->setAddr2($addr2);
        $new->setCity($city);
        return $new;
    }

    public function __construct() {
        parent::__construct( true );
    }

    public function getOwnerId() : int {
        return $this->ownerId;
    }

    public function setOwnerId(int $ownerId) : bool {
        if(0 < $ownerId) {
            $this->ownerId = $ownerId;
            return $this->setDirty();
        }
        return false;
    }

    public function setAddr1(string $addr) : bool {
        $this->addr1 = $addr;
        return $this->setDirty();
    }

    public function getAddr1() : string {
        return $this->addr1;
    }
    
    public function setAddr2(string $addr) : bool {
        $this->addr2 = $addr;
        return $this->setDirty();
    }

    public function getAddr2() : string {
        return $this->addr2;
    }
    
    public function setCity(string $city) : bool {
        $this->city = $city;
        return $this->setDirty();
    }

    public function getCity() : string {
        return $this->city;
    }
    
    public function setProvince(string $prov) : bool {
        $this->province = $prov;
        return $this->setDirty();
    }

    public function getProvince() : string {
        return $this->province;
    }

    public function setCountry(string $country) : bool {
        $this->country = $country;
        return $this->setDirty();
    }

    public function getCountry() : string {
        return $this->country;
    }
    
    public function setPostalCode(string $postal) : bool {
        $this->postal_code = $postal;
        return $this->setDirty();
    }

    public function getPostalCode() : string {
        return $this->postal_code;
    }

    /**
     * Delete this Address
     * */	
    public function deleteMe() : int {
        
		global $wpdb;
		$result = 0;
	
        $result = self::delete($this->getID());
        if(0 < $result) $this->ID = 0;
        return $result;
    }
    
    /**
     * Check to see if this Address has valid data
     */
    public function isValid() {
        $loc = __CLASS__ ;

        $valid = true;
        $mess = '';
        if(!isset($this->ownerId)) $mess = __("{$loc} must have a person assigned. ",TennisClubMembership::TEXT_DOMAIN);
        if(!isset($this->addr2)) $mess = __( "{$loc} must have a street assigned. ",TennisClubMembership::TEXT_DOMAIN);
        if(!isset($this->city)) $mess = __( "{$loc} must have a city assigned. ",TennisClubMembership::TEXT_DOMAIN);

        if(strlen($mess) > 0 ) throw new InvalidAddressException($mess);

        return $valid;
    }
    
	/**
	 * Create a new Address in the database
	 */
	protected function create() {
		$loc = __CLASS__ . "::" . __FUNCTION__;
		
		global $wpdb;

		parent::create();
        
        $values = array( 'person_ID'=>$this->getOwnerId() 
                        ,'addr1'=>$this->getAddr1()
                        ,'addr2'=>$this->getAddr2()
                        ,'city'=>$this->getCity()
                        ,'province'=>$this->getProvince()
                        ,'country'=>$this->getCountry()
                        ,'postal_code'=>$this->getPostalCode()
        );
        $formats_values = array('%d,%s,%s,%s,%s,%s,%s');
        $table = TennisClubMembership::getInstaller()->getDBTablenames()[self::$tablename];
        $res = $wpdb->insert($table, $values, $formats_values);

        if( $res === false || $res === 0 ) {
            $mess = "$loc: wpdb->insert returned false or inserted 0 rows.";
            $err = empty($wpdb->last_error) ? '' : $wpdb->last_error;
            $mess .= " : Err='$err'";
            throw new InvalidAddressException($mess);
        }

        $this->ID = $wpdb->insert_id;

        $result = $wpdb->rows_affected;
        $this->isnew = FALSE;
        $this->isdirty = FALSE;

        $this->log->error_log("{$loc}: $result rows affected.");

        return $result;
    }
    
	/**
	 * Update the Address in the database
	 */
	protected function update() {
		$loc = __CLASS__ . '::' . __FUNCTION__;

		global $wpdb;
		parent::update();
        
        $values = array( 'person_ID'=>$this->getOwnerId() 
                        ,'addr1'=>$this->getAddr1()
                        ,'addr2'=>$this->getAddr2()
                        ,'city'=>$this->getCity()
                        ,'province'=>$this->getProvince()
                        ,'country'=>$this->getCountry()
                        ,'postal_code'=>$this->getPostalCode()
        );
		$formats_values = array('%d,%s,%s,%s,%s,%s,%s');
		$where          = array('ID' => $this->ID);
		$formats_where  = array('%d');
        $table = TennisClubMembership::getInstaller()->getDBTablenames()[self::$tablename];
		$wpdb->update($table,$values,$where,$formats_values,$formats_where);
		$this->isdirty = FALSE;
		$result = $wpdb->rows_affected;

		$this->log->error_log("{$loc}: $result rows affected.");
		return $result;
    }
    
    /**
     * Map incoming data to an instance of Address
     */
    protected static function mapData($obj,$row) {
		$loc = __CLASS__ . '::' . __FUNCTION__;

        parent::mapData($obj,$row);

        $obj->ownerId = $row['person_ID'];
        $obj->addr1 = $row['addr1'];
        $obj->addr2 = $row['addr2'];
        $obj->city = $row['city'];
        $obj->province = $row['province'];
        $obj->country = $row['country'];
        $obj->postal_code = $row['postal_code']; 
    }
}
