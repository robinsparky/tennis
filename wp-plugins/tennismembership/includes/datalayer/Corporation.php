<?php
namespace datalayer;

use TennisClubMembership;
use datalayer\Address;
use datalayer\appexceptions\InvalidCorporationException;
use \DateTime;
use \DateTimeZone;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 
 * Data and functions for Corporation
 * @class  Corporation
 * @package Tennis Membership
 * @version 1.0.0
 * @since   0.1.0
*/
class Corporation  extends AbstractMembershipData 
{

    public static $tablename = 'corporation';

	public const COLUMNS = <<<EOD
ID 
name
,yearend_date
,status
,gst_number
,gst_rate1
,gst_rate2			
,last_update
EOD;

    //DB fields
    private $name;
    private $yearendDate;

    //Private properties
    private $address;

    /**
     * Find collection of all Corporation(s)
     */
    public static function find(...$fk_criteria) {
        $loc = __CLASS__ . '::' . __FUNCTION__;

        global $wpdb;
        $table = TennisClubMembership::getInstaller()->getDBTablenames()[self::$tablename];
        $columns = self::COLUMNS;
        $col = array();
        $sql = "select {$columns} from $table";

        $safe = $wpdb->prepare($sql,$fk_criteria);
        $rows = $wpdb->get_results($safe, ARRAY_A);
        
        error_log("{$loc} $wpdb->num_rows rows returned");

        foreach($rows as $row) {
            $obj = new Corporation;
            self::mapData($obj,$row);
            $obj->isnew = FALSE;
            $col[] = $obj;
        }
        return $col;
    }

    /**
     * Get instance of a Corporation using it's primary key: ID
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
            $obj = new Corporation;
            self::mapData($obj,$rows[0]);
        }
        return $obj;
    }

    public static function fromData(string $name ) : self {
        $new = new Corporation;
        if(!$new->setName($name)) {
            $mess = __("Failed", TennisClubMembership::TEXT_DOMAIN);
            throw new InvalidCorporationException($mess);
        }
        return $new;
    }

    public function __construct() {
        parent::__construct( true );
    }

    /**
     * Set the name of the corporation
     * @param string $name - the Corporation
     * @return bool - true if successful; false otherwise
     */
    public function setName(string $name) : bool {
        $this->name = $name;
        return $this->setDirty();
    }

    public function getName() : string {
        return $this->name;
    }

    public function setYearEnd(DateTime $ye) : bool {
        $this->yearendDate = $ye;
        return $this->setDirty();
    }

    /**
     * Get the year end date as mm-dd
     */
    public function getYearEnd() : string {
        if(!isset($this->yearendDate)) {
            $this->yearendDate = new DateTime(date('Y').'-12-31');
        }
        return $this->yearendDate->format('m-d');
    }

    /**
     * Get the year end date as full DateTime
     */
    public function getYearEndAsDate() : DateTime {
        if(!isset($this->yearendDate)) {
            $this->yearendDate = new DateTime(date('Y') . '-12-31');
        }
        return $this->yearendDate;
    }

	/**
	 * Set the Address for this Person
	 * NOTE: if no arg passed  the Address will be deleted
	 *       otherwise any existing Address will be overwritten by the new one supplied.
	 * @param Address is the new Address for this person.
	 */
	public function setAddress(Address $addr = null) : bool {

		if(is_null($addr) || !is_null($this->address)) {
			$this->address->delete();
		}
		$this->address = $addr;
		return $this->setDirty();
	}

	public function getAddress() : Address {
		return $this->address;
	}
	
    public function delete() {
        
		// global $wpdb;
		// $result = 0;

		// //Delete the Address		
        // $table = TennisClubMembership::getInstaller()->getDBTablenames()[self::$tablename];
		// $where = array( 'ID'=>$this->getID() );
		// $formats_where = array( '%d' );
		// $wpdb->delete( $table, $where, $formats_where );
		// $result += $wpdb->rows_affected;
    }
    

    /**
     * Check to see if this Corporation has valid data
     */
    public function isValid() {
        $loc = __CLASS__ ;

        $valid = true;
        $mess = '';
        if(!isset($this->name)) $mess = __("{$loc} must have a name assigned. ",TennisClubMembership::TEXT_DOMAIN);

        if(strlen($mess) > 0 ) throw new InvalidCorporationException($mess);

        return $valid;
    }
    
	/**
	 * Create a new Corporation in the database
	 */
	protected function create() {
		$loc = __CLASS__ . "::" . __FUNCTION__;
		
		global $wpdb;

		parent::create();
        
        $values = array( 'name'=>$this->getName() 
                       , 'yearend_date' => date('Y') . '-' . $this->getYearEnd()
                       );
        $formats_values = array('%s','%s');
        $table = TennisClubMembership::getInstaller()->getDBTablenames()[self::$tablename];
        $res = $wpdb->insert($table, $values, $formats_values);

        if( $res === false || $res === 0 ) {
            $mess = "$loc: wpdb->insert returned false or inserted 0 rows.";
            $err = empty($wpdb->last_error) ? '' : $wpdb->last_error;
            $mess .= " : Err='$err'";
            throw new InvalidCorporationException($mess);
        }

        $this->ID = $wpdb->insert_id;

        $result = $wpdb->rows_affected;
        $this->isnew = FALSE;
        $this->isdirty = FALSE;

        $this->log->error_log("{$loc}: $result rows affected.");

        return $result;
    }
    
	/**
	 * Update the Corporation in the database
	 */
	protected function update() {
		$loc = __CLASS__ . '::' . __FUNCTION__;

		global $wpdb;
		parent::update();
        
        $values = array( 'person_ID' => $this->getName() 
                       , 'yearend_date' => date('Y') . '-' . $this->getYearEnd()
                       );
		$formats_values = array('%s','%s');
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
     * Map incoming data to an instance of Corporation
     */
    protected static function mapData($obj,$row) {
		$loc = __CLASS__ . '::' . __FUNCTION__;

        parent::mapData($obj,$row);

        $obj->name = $row['name']; 
        
        if( !empty($row["yearend_date"]) && $row["yearend_date"] !== '0000-00-00 00:00:00') {
            $st = new DateTime( $row["yearend_date"], new DateTimeZone('UTC') );
            $mess = print_r($st,true);
            error_log("$loc: DateTime for yearend_date ...");
            error_log($mess);
            $obj->setYearEnd($st);
        }
        else {
            $obj->yearendDate = new DateTime(date('Y') . '-12-31');
        }    
    }
}
