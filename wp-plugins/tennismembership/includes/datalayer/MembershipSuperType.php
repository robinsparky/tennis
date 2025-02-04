<?php
namespace datalayer;

use TennisClubMembership;
use datalayer\appexceptions\InvalidMembershipTypeException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 
 * Data and functions for MembershipSuperType
 * @class  MembershipSuperType
 * @package Tennis Membership
 * @version 1.0.0
 * @since   0.1.0
*/
class MembershipSuperType  extends AbstractMembershipData 
{

    public static $tablename = '';

	public const COLUMNS = <<<EOD
ID 			
,name
,last_update
EOD;

    //DB fields
    private $name;

    //Private properties
    private $superType;

    /**
     * Find collection of all MembershipSuperTypes
     */
    public static function find(...$fk_criteria) {
        $loc = __CLASS__ . '::' . __FUNCTION__;

        global $wpdb;
        $table = self::$tablename;
        $columns = self::COLUMNS;
        $col = array();
        $sql = "select {$columns} from $table";
        $safe = $wpdb->prepare($sql);
        $rows = $wpdb->get_results($safe, ARRAY_A);
        
        error_log("{$loc} $wpdb->num_rows rows returned");

        foreach($rows as $row) {
            $obj = new MembershipSuperType;
            self::mapData($obj,$row);
            $obj->isnew = FALSE;
            $col[] = $obj;
        }
        return $col;
    }

    /**
     * Get instance of a MembershipSuperType using it's primary key: ID
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
            $obj = new MembershipSuperType;
            self::mapData($obj,$rows[0]);
        }
        return $obj;
    }

    public static function fromData(string $name) : self {
        $new = new MembershipSuperType;
        $new->setName($name);
        return $new;
    }

    public function __construct() {
        parent::__construct( true );
        self::$tablename = TennisClubMembership::getInstaller()->getDBTablenames()['membershipsupertype'];
    }

    public function setName(string $name ) : bool {
        $this->name = $name;
        return $this->setDirty();
    }

    public function getName() : string {
        return $this->name;
    }
    
	/**
	 * Create a label/string representing an instance of this MembershipSuperType
	 */
    public function toString() {
		$loc = __CLASS__;
        return sprintf( "{$loc}-%s",$this->getName() );
    }

    public function delete() {
        
		global $wpdb;
		$result = 0;

        //Check MembershipTypes that have this super type

		//Delete the MembershipSuperType
		$table = $wpdb->prefix . self::$tablename;
		$where = array( 'ID'=>$this->getID() );
		$formats_where = array( '%d' );
		$wpdb->delete( $table, $where, $formats_where );
		$result += $wpdb->rows_affected;
    }
    

    /**
     * Check to see if this MembershipSuperType has valid data
     */
    public function isValid() {
        $loc = __CLASS__ ;

        $valid = true;
        $mess = '';
        if(!isset($this->name)) $mess = __( "{$loc} must have a name assigned. ",TennisClubMembership::TEXT_DOMAIN);

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
        
        $values = array('name'=>$this->getName());
        $formats_values = array('%s');
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
	 * Update the MembershipSuperType in the database
	 */
	protected function update() {
		$loc = __CLASS__ . '::' . __FUNCTION__;

		global $wpdb;
		parent::update();
        
        $values = array('name'=>$this->getName());
		$formats_values = array('%s');
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
        $obj->name = $row['name'];
    }
}
