<?php
namespace datalayer;

use TennisClubMembership;
use datalayer\appexceptions\InvalidMembershipTypeException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 
 * Data and functions for MembershipCategory
 * @class  MembershipCategory
 * @package Tennis Membership
 * @version 1.0.0
 * @since   0.1.0
*/
class MembershipCategory  extends AbstractMembershipData 
{

    public static $tablename = 'membershipcategory';

	public const COLUMNS = <<<EOD
ID
,corporate_ID
,name
,last_update
EOD;

    //DB fields
    private $name;
    private $corporateId;

    /**
     * Find collection of all MembershipCategory(ies)
     * NOTE: The fk_criteria must contain a key/value for 'corporateId'/corporate_ID
     * @param array $fk_criteria - foreign keys for the query
     */
    public static function find(...$fk_criteria) {
        $loc = __CLASS__ . '::' . __FUNCTION__;

        global $wpdb;
        $table = TennisClubMembership::getInstaller()->getDBTablenames()[self::$tablename];
        $columns = self::COLUMNS;
        $col = array();
        $corpId = 0;
        if(array_key_exists("corporateId",$fk_criteria)) {
            $corpId = $fk_criteria("corporateId");
        }
        if($corpId == 0) return $col;

        $sql = "select {$columns} from $table where corporate_ID={$corpId}";
        $safe = $wpdb->prepare($sql);
        $rows = $wpdb->get_results($safe, ARRAY_A);
        
        error_log("{$loc} $wpdb->num_rows rows returned");

        foreach($rows as $row) {
            $obj = new MembershipCategory;
            self::mapData($obj,$row);
            $obj->isnew = FALSE;
            $col[] = $obj;
        }
        return $col;
    }

    /**
     * Get instance of a MembershipCategory using it's primary key: ID
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
            $obj = new MembershipCategory;
            self::mapData($obj,$rows[0]);
        }
        return $obj;
    }

    public static function fromData(string $name, int $corporateId ) : self {
        $new = new MembershipCategory;
        $new->setName($name);
        $new->setCorporateId($corporateId);
        return $new;
    }

    public function __construct() {
        parent::__construct( true );
    }

    public function setName(string $name ) : bool {
        $this->name = $name;
        return $this->setDirty();
    }

    public function getName() : string {
        return $this->name;
    }

    public function setCorporateId(int $corpId) : bool {
        if(0 >= $corpId) {
            return false;
        }
        $this->corporateId = $corpId;
        return $this->setDirty();
    }

    public function getCorporateId() : int {
        return $this->corporateId;
    }
    
	/**
	 * Create a label/string representing an instance of this MembershipSuperType
	 */
    public function toString() : string {
		$loc = __CLASS__;
        return sprintf( "{$loc}(%d)-%s", $this->getID(), $this->getName() );
    }

    public function delete() : int {
        
		global $wpdb;
		$result = 0;

        //Check MembershipTypes that have this super type
        foreach(MembershipType::find(['superTypeFK' => $this->getID()]) as $mt) {
            $result += $mt->delete();
        }

		//Delete the MembershipSuperType
        $table = TennisClubMembership::getInstaller()->getDBTablenames()[self::$tablename];
		$where = array( 'ID'=>$this->getID() );
		$formats_where = array( '%d' );
		$wpdb->delete( $table, $where, $formats_where );
		$result += $wpdb->rows_affected;

        return $result;
    }
    

    /**
     * Check to see if this MembershipSuperType has valid data
     */
    public function isValid() : bool {
        $loc = __CLASS__ ;

        $valid = true;
        $mess = '';
        if(!isset($this->name)) $mess = __( "{$loc} must have a name assigned. ",TennisClubMembership::TEXT_DOMAIN);
        if(!isset($this->corporateId)) $mess = __( "{$loc} must have a corporate id. ",TennisClubMembership::TEXT_DOMAIN);

        if(strlen($mess) > 0 ) throw new InvalidMembershipTypeException($mess);

        return $valid;
    }
    
	/**
	 * Create a new MembershipSuperType in the database
	 */
	protected function create() {
		$loc = __CLASS__ . "::" . __FUNCTION__;
		
		global $wpdb;

		parent::create();
        
        $table = TennisClubMembership::getInstaller()->getDBTablenames()[self::$tablename];
        $values = array('name'=>$this->getName()
                       ,'corporate_ID'=>$this->corporateId
                       );
        $formats_values = array('%s');
        $res = $wpdb->insert($table, $values, $formats_values);

        if( $res === false || $res === 0 ) {
            $mess = "$loc: wpdb->insert returned false or inserted 0 rows.";
            $err = empty($wpdb->last_error) ? '' : $wpdb->last_error;
            $mess .= " : Err='$err'";
            throw new InvalidMembershipTypeException($mess);
        }

        $this->ID = $wpdb->insert_id;

        $result = $wpdb->rows_affected;
        $this->isnew = FALSE;
        $this->isdirty = FALSE;

        $this->log->error_log("{$loc}: $result rows affected.");

        return $result;
    }
    
	/**
	 * Update the MembershipSuperType in the database
	 */
	protected function update() {
		$loc = __CLASS__ . '::' . __FUNCTION__;

		global $wpdb;
		parent::update();
        
        $table  = TennisClubMembership::getInstaller()->getDBTablenames()[self::$tablename];
        $values = array('name'=>$this->getName()
                        ,'corporate_ID' => $this->getCorporateId()
                       );
		$formats_values = array('%s','%d');
		$where          = array('ID' => $this->ID);
		$formats_where  = array('%d');
		$wpdb->update($table,$values,$where,$formats_values,$formats_where);
		$this->isdirty = FALSE;
		$result = $wpdb->rows_affected;

		$this->log->error_log("{$loc}: $result rows affected.");
		return $result;
    }
    
    /**
     * Map incoming data to an instance of MembershipType
     */
    protected static function mapData($obj,$row) {
		$loc = __CLASS__ . '::' . __FUNCTION__;

        parent::mapData($obj,$row);
        $obj->name = $row['name'];
        $obj->corporateId = $row['corporate_ID'];
    }
}
