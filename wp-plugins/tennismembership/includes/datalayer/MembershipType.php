<?php
namespace datalayer;

use TennisClubMembership;
use datalayer\appexceptions\InvalidMembershipTypeException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 
 * This class is the interface for managing CRUD operations on the types of membership
 * @class  MembershipType
 * @package Tennis Membership
 * @version 1.0.0
 * @since   0.1.0
*/
class MembershipType  extends AbstractMembershipData 
{

    public static $tablename = '';

	public const COLUMNS = <<<EOD
ID 			
,supertype_ID
,name
,last_update
EOD;

    //DB fields
    private $superTypeId;
    private $name;

    //Private properties
    private $superType;

    /**
     * Find collection of all MembershipTypes
     */
    public static function find(...$fk_criteria) {
        $loc = __CLASS__ . '::' . __FUNCTION__;

        $superTypeFK = 'superTypeFK';

        global $wpdb;
        $table = self::$tablename;
        $columns = self::COLUMNS;
        $sql = '';
        $col = array();
        $fk = 0;
        if(array_key_exists($superTypeFK, $fk_criteria)) {
        //Find all mmemberhip types for a given super type
            $fk = $fk_criteria[$superTypeFK];
            $sql = "select {$columns} from $table where supertype_ID=%d";
        }
        else {
            //Find all memberhip types
            $superTable = MembershipType::$tablename;
            $sql = "select super.ID, super.name, mem.ID, mem.name from $table as mem
                    inner join $superTable as super
                    on mem.supertype_ID = super.ID and mem.supertype_ID > %d;";
        }
        $safe = $wpdb->prepare($sql,$fk);
        $rows = $wpdb->get_results($safe, ARRAY_A);
        
        error_log("{$loc} $wpdb->num_rows rows returned");

        foreach($rows as $row) {
            $obj = new MembershipType;
            self::mapData($obj,$row);
            $obj->isnew = FALSE;
            $col[] = $obj;
        }
        return $col;
    }

    /**
     * Get instance of a MembershipType using it's primary key: ID
     */
    static public function get(int ...$pks) {
        $loc = __CLASS__ . '::' . __FUNCTION__;

        global $wpdb;
        $table = self::$tablename;
        $columns = self::COLUMNS;
        $sql = "select {$columns} from $table where ID=%d";
        $safe = $wpdb->prepare($sql,$pks);
        $rows = $wpdb->get_results($safe, ARRAY_A);

        error_log("{$loc} $wpdb->num_rows rows returned.");

        $obj = NULL;
        if(count($rows) === 1) {
            $obj = new MembershipType;
            self::mapData($obj,$rows[0]);
        }
        return $obj;
    }

    public static function fromData(int $superTypeId, string $name) : self {
        $new = new MembershipType;
        if(!$new->setSuperTypeId($superTypeId)) {
            $mess = __("No such super type with this Id", TennisClubMembership::TEXT_DOMAIN);
            throw new InvalidMembershipTypeException($mess);
        }
        $new->setName($name);
        return $new;
    }

    public function __construct() {
        parent::__construct( true );
        self::$tablename = TennisClubMembership::getInstaller()->getDBTablenames()['membershiptype'];
    }

    /**
     * Set the ID of the super type
     * @param int $superTypeId - the db ID of the super type
     * @return bool - true if successful; false otherwise
     */
    public function setSuperTypeId(int $superTypeId) : bool {
        $this->superTypeId = $superTypeId;
        $this->superType = MembershipSuperType::get($superTypeId);
        if(is_null($this->superType)) {
            $this->superTypeId = 0;
            return false;
        }
        return $this->setDirty();
    }

    public function getSuperTypeId() : int {
        return $this->superTypeId;
    }

    public function setSuperType( MembershipSuperType $superType ) : bool {
        $this->superType = $superType;
        $this->superTypeId = $superType->getID();
        return $this->setDirty();
    }

    public function getSuperType() : MembershipSuperType {
        return $this->superType;
    }

    public function setName(string $name ) : bool {
        $this->name = $name;
        return $this->setDirty();
    }

    public function getName() : string {
        return $this->name;
    }
    
	/**
	 * Create a label/string representing an instance of this MembershipType
	 */
    public function toString() {
		$loc = __CLASS__;
        return sprintf( "{$loc}-%s",$this->getName() );
    }

    public function delete() {
        
		global $wpdb;
		$result = 0;

        //Check Registrations that have this membership type

		//Delete the MembershipType
		$table = self::$tablename;
		$where = array( 'ID'=>$this->getID() );
		$formats_where = array( '%d' );
		$wpdb->delete( $table, $where, $formats_where );
		$result += $wpdb->rows_affected;
    }
    

    /**
     * Check to see if this MembershipType has valid data
     */
    public function isValid() {
        $loc = __CLASS__ ;

        $valid = true;
        $mess = '';
        if(!isset($this->superTypeId)) $mess = __("{$loc} must have a super type assigned. ",TennisClubMembership::TEXT_DOMAIN);
        if(!isset($this->name)) $mess = __( "{$loc} must have a name assigned. ",TennisClubMembership::TEXT_DOMAIN);

        if(strlen($mess) > 0 ) throw new InvalidMembershipTypeException($mess);

        return $valid;
    }
    
	/**
	 * Create a new MembershipType in the database
	 */
	protected function create() {
		$loc = __CLASS__ . "::" . __FUNCTION__;
		
		global $wpdb;

		parent::create();
        
        $values = array( 'supertype_ID'=>$this->getSuperTypeId() 
                        ,'name'=>$this->getName()
        );
        $formats_values = array('%d','%s');
        $res = $wpdb->insert(self::$tablename, $values, $formats_values);

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
	 * Update the MembershipType in the database
	 */
	protected function update() {
		$loc = __CLASS__ . '::' . __FUNCTION__;

		global $wpdb;
		parent::update();
        
        $values = array('supertype_ID'=>$this->getSuperTypeId() 
                       ,'name'=>$this->getName()
        );
		$formats_values = array('%d','%s');
		$where          = array('ID' => $this->ID);
		$formats_where  = array('%d');
		$wpdb->update(self::$tablename,$values,$where,$formats_values,$formats_where);
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

        $obj->superTypeId = $row['supertype_ID'];
        $obj->name = $row['name'];
    }
}
