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

    public static $tablename = "membershiptype";

	public const COLUMNS = <<<EOD
ID 			
,category_ID
,name
,description
,last_update
EOD;

public const JOINCOLUMNS = <<<EOD
cat.ID as category_ID
,cat.name as category
,cat.corporate_ID as corporate_ID
,mem.ID
,mem.name
,mem.description
,mem.last_update
EOD;

    //DB fields
    private $categoryId;
    private $name;
    private $description;

    //Private properties
    private $category; //name of the category
    private $corporateId;

    /**
     * Find collection of all MembershipTypes
     * or MembershipTypes belonging to a given super type
     * @param array $fk_criteria (foreign key)
     *              ['superTypeFK' => ID]
     */
    public static function find(...$fk_criteria) {
        $loc = __CLASS__ . '::' . __FUNCTION__;

        $categoryFK = 'categoryId';

        $table      = TennisClubMembership::getInstaller()->getDBTablenames()[self::$tablename];
        $catTable = TennisClubMembership::getInstaller()->getDBTablenames()[MembershipCategory::$tablename];
        $columns = self::COLUMNS;
        $sql = '';
        $col = array();
        $fk = 0;
        if(array_key_exists($categoryFK, $fk_criteria)) {
        //Find all mmemberhip types for a given category
            $fk = $fk_criteria[$categoryFK];
            error_log("$loc: categoryId = $fk");
            $sql = "select {$columns} from {$table} where category_ID=%d";
        }
        else {
            //Find all memberhip types
            $columns = self::JOINCOLUMNS;
            $sql = "select {$columns} from {$table} as mem
                    inner join {$catTable} as cat
                    on mem.category_ID = cat.ID and mem.category_ID > %d;";
        }

        global $wpdb;
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
        $table = TennisClubMembership::getInstaller()->getDBTablenames()[self::$tablename];
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

    public static function fromData(int $catId, string $name, string $desc = '') : self {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        if(0 === $catId) {
            error_log("$loc($catId, $name, $desc) - categorId cannot be 0");
            return null;
        }
        $new = new MembershipType;
        if(!$new->setCategoryId($catId)) {
            $mess = __("No such category with this Id", TennisClubMembership::TEXT_DOMAIN);
            throw new InvalidMembershipTypeException($mess);
        }
        $new->setName($name);
        return $new;
    }

    public static function iterateTypes() : iterable {
        foreach(self::find() as $tp) {
            yield $tp;
        }
    }
    
    public static function allTypes() : array {
        $res = array();
        foreach(self::iterateTypes() as $tp) {
            $res[] = $tp;
        }
        return $res;
    }

    public static function isValidTypeId(int $typeId) {
        foreach(self::allTypes() as $tp) {
            if($typeId === $tp->getID()) {
                return true;
            }
        }
        return false;
    }

    public function __construct() {
        parent::__construct( true );
    }

    /**
     * Set the ID of the category
     * @param int $categoryId - the db ID of the category
     * @return bool - true if successful; false otherwise
     */
    public function setCategoryId(int $catId) : bool {
        $this->categoryId = $catId;
        $category = MembershipCategory::get($this->categoryId );
        if(is_null($category)) {
            $this->categoryId = 0;
            return false;
        }
        $this->category = $category->getName();
        return $this->setDirty();
    }

    public function getCategory() : string {
        return $this->category;
    }

    public function setName(string $name ) : bool {
        $this->name = $name;
        return $this->setDirty();
    }

    public function getName() : string {
        return $this->name;
    }
    
    public function setDescription(string $desc ) : bool {
        $this->description = $desc;
        return $this->setDirty();
    }

    public function getDescription() : string {
        return isset($this->description) ? $this->description : '';
    }
    
	/**
	 * Create a label/string representing an instance of this MembershipType
	 */
    public function toString() {
		$loc = __CLASS__;
        return sprintf( "{$loc}(%d)-%s", $this->getID(), $this->getName() );
    }

    public function delete() : int {
        
		global $wpdb;
		$result = 0;

        //Check Registrations that have this membership type

		//Delete the MembershipType
        $table = TennisClubMembership::getInstaller()->getDBTablenames()[self::$tablename];
		$where = array( 'ID'=>$this->getID() );
		$formats_where = array( '%d' );
		$wpdb->delete( $table, $where, $formats_where );
		$result += $wpdb->rows_affected;

        return $result;
    }
    

    /**
     * Check to see if this MembershipType has valid data
     */
    public function isValid() {
        $loc = __CLASS__ ;

        $valid = true;
        $mess = '';
        if(!isset($this->categoryId)) $mess = __("{$loc} must have a category assigned. ",TennisClubMembership::TEXT_DOMAIN);
        if(!isset($this->name)) $mess += __( "{$loc} must have a name assigned. ",TennisClubMembership::TEXT_DOMAIN);

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
        
        $values = array( 'category_ID'=>$this->categoryId 
                        ,'name'=>$this->name
                        ,'description'=>$this->getDescription()
        );
        $formats_values = array('%d','%s','%s');
        $table = TennisClubMembership::getInstaller()->getDBTablenames()[self::$tablename];

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
	 * Update the MembershipType in the database
	 */
	protected function update() {
		$loc = __CLASS__ . '::' . __FUNCTION__;

		global $wpdb;
		parent::update();
        
        $values = array('category_ID'=>$this->categoryId 
                       ,'name'=>$this->getName()
                       ,'description'=>$this->getDescription()
        );
		$formats_values = array('%d','%s','%s');
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
     * Map incoming data to an instance of MembershipType
     */
    protected static function mapData($obj,$row) {
		$loc = __CLASS__ . '::' . __FUNCTION__;

        $obj->categoryId = $row['category_ID'];
        $obj->name = $row['name']; 
        $obj->description = $row['description'];
        $obj->corporateId = isset($row['corporate_ID']) ? $row['corporate_ID'] : -1;
    }
}
