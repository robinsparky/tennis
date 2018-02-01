<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once('abstract-class-data.php');

/** 
 * Data and functions for Tennis Player(s)
 * @class  Player
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
*/
class Player extends AbstractData
{ 
    private static $tablename = 'tennis_player';

    const MAXSKILL = 7.0;
    const MINSKILL = 2.5;
    
    private $entrant_ID;
    private $squad_ID;
    private $entrant_draw_ID;

    private $last_name; //NOT NULL
    private $first_name;
    private $skill_level; //NOT NULL
    private $homePhone;
    private $mobilePhone;
    private $businessPhone;
    private $homeEmail;
    private $businessEmail;

    /**
     * Search for Players that have a name 'like' the provided criteria
     */
    public static function search($criteria) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$sql = "select * from $table where last_name like '%%s%'";
		$safe = $wpdb->prepare($sql,$criteria);
		$rows = $wpdb->get_results($safe, ARRAY_A);
		
		error_log("Player::search $wpdb->num_rows rows returned using criteria: $criteria");

		$col = array();
		foreach($rows as $row) {
            $obj = new Player;
            self::mapData($obj,$row);
			$obj->isnew = FALSE;
			$col[] = $obj;
		}
		return $col;
    }
    
    /**
     * Find all Players belonging to a specific Entry;
     */
    public static function find(...$fk_criteria) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$sql = "select * from $table where entry_ID = %d";
		$safe = $wpdb->prepare($sql,$fk_criteria);
		$rows = $wpdb->get_results($safe, ARRAY_A);
		
		error_log("Player::find $wpdb->num_rows rows returned using entry_ID=$fk_criteria");

		$col = array();
		foreach($rows as $row) {
            $obj = new Player;
            self::mapData($obj,$row);
			$col[] = $obj;
		}
		return $col;
    }

	/**
	 * Get instance of a Match using it's ID
	 */
    static public function get(int ...$pks) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$sql = "select * from $table where ID=%d";
		$safe = $wpdb->prepare($sql,$pks);
		$rows = $wpdb->get_results($safe, ARRAY_A);

		error_log("Player::get(id) $wpdb->num_rows rows returned.");

        $obj = NULL;
		if($rows.length === 1) {
			$obj = new Player;
            self::mapData($obj,$rows[0]);
		}
		return $obj;
	}

	/*************** Instance Methods ****************/
	public function __construct() {
        $this->isnew = TRUE;
        $this->init();
    }
    

    public function setLastName(string $last) {
        if(strlen($last) < 2) return;
        $this->last_name = $last;
        $this->isdirty = TRUE;
    }

    public function getLastName():string {
        return $this->last_name;
    }

    public function setFirstName(string $first) {
        if(!is_string($first) || strlen($first) < 2) return;
        $this->first_name = $first;
        $this->isdirty = TRUE;
    }

    public function getFirstName():string {
        return $this->first_name;
    }
    
    public function setSkillLevel(float $skill) {
        if($skill < 1.0) return;
        if($skill < self::MINSKILL || $skill > self::MAXSKILL) return;
        $this->skill_level = $skill;
        $this->isdirty = TRUE;
    }

    public function getSkillLevel():float {
        return $this->skill;
    }

    public function setEntrantID(int $id) {
        if(id < 0) return;
        $this->tennis_entrant_ID = $id;
    }

    public function getEntrantID():int {
        return $this->tennis_entrant_ID;
    }
    
    public function setDrawID(int $id) {
        if($id < 0) return;
        $this->tennis_entrant_draw_ID = $id;
    }

    public function getDrawID():int {
        return $this->tennis_entrant_draw_ID;
    }
    
    public function setSquadID(int $id) {
        if($id < 0) return;
        $this->tennis_squad_ID = $id;
    }

    public function getSquadID():int {
        return $this->tennis_squad_ID;
    }

    public function setHomePhone(string $phone) {
        $this->homePhone = $phone;
        $this->isdirty = TRUE;
    }

    public function getHomePhone():string {
        return $this->homePhone;
    }
    
    public function setMobilePhone(string $phone) {
        $this->mobilePhone = $phone;
        $this->isdirty = TRUE;
    }

    public function getMobilePhone():string {
        return $this->mobilePhone;
    }

    public function setBusinessPhone(string $phone) {
        $this->businessPhone = $phone;
        $this->isdirty = TRUE;
    }

    public function getBusinessPhone():string {
        return $this->businessPhone;
    }
    
    public function setHomeEmail(string $email) {
        $this->homeEmail = $email;
        $this->isdirty = TRUE;
    }

    public function getHomeEmail():string {
        return $this->homeEmail;
    }
    
    public function setBusinessEmail(string $email) {
        $this->businessEmail = $email;
        $this->isdirty = TRUE;
    }

    public function getBusinessEmail():string {
        return $this->businessEmail;
    }

	/**
	 * Get all my children!
	 */
    public function getChildren($force=false) {

    }

	protected function create() {
        global $wpdb;
        
        parent::create();

        $values         = array('last_name' => $this->last_name
                               ,'first_name' => $this->last_name
                               ,'skill_level' => $this->skill_level
                               ,'homePhone' => $this->homePhone
                               ,'businessPhone' => $this->businessPhone
                               ,'mobilePhone' => $this->mobilePhone                               
                               ,'homeEmail' => $this->homeEmail                              
                               ,'businessEmail' => $this->businessEmail
                               ,'entrant_ID' => $this->entrant_ID
                               ,'entrant_draw_ID' => $this->entry_draw_ID
                               ,'squad_ID' => $this->$squad_ID);
		$formats_values = array('%s','%s','%d','%s','%s','%s','%s','%s','%d','%d','%d');
		$wpdb->insert($wpdb->prefix . self::$tablename, $values, $formats_values);
		$this->ID = $wpdb->insert_id;
		$this->isnew = FALSE;
		$this->isdirty = FALSE;

		error_log("Player::create $wpdb->rows_affected rows affected.");

		return $wpdb->rows_affected;
	}

	protected function update() {
		global $wpdb;

        parent::update();

        $values         = array('last_name' => $this->last_name
                               ,'first_name' => $this->last_name
                               ,'skill_level' => $this->skill_level
                               ,'homePhone' => $this->homePhone
                               ,'businessPhone' => $this->businessPhone
                               ,'mobilePhone' => $this->mobilePhone                               
                               ,'homeEmail' => $this->homeEmail                              
                               ,'businessEmail' => $this->businessEmail
                               ,'entrant_ID' => $this->entrant_ID
                               ,'entrant_draw_ID' => $this->entrant_draw_ID
                               ,'squad_ID' => $this->$tennis_squad_ID);
        $formats_values = array('%s','%s','%d','%s','%s','%s','%s','%s','%d','%d','%d');
		$where          = array('ID' => $this->ID);
		$formats_where  = array('%d');
		$wpdb->update($wpdb->prefix . self::$tablename, $values, $where, $formats_values, $formats_where);
		$this->isdirty = FALSE;

		error_log("Round::update $wpdb->rows_affected rows affected.");

		return $wpdb->rows_affected;
	}

    //TODO: Add delete logic
    public function delete() {

    }

    public function isValid() {
        $isvalid = TRUE;
        if(!isset($this->last_name) || !is_string($this->last_name)) $isvalid = FALSE;
        if(isset($this->skill) && is_nan($skill)) $isvalid = FALSE;
        
        return $isvalid;
    }
    
    /**
     * Map incoming data to an instance of Round
     */
    protected static function mapData($obj,$row) {
        parent::mapData($obj,$row);
        $obj->entrant_ID       = $row["entrant_ID "];
        $obj->entrant_draw_ID  = $row["entrant_draw_ID"];
        $obj->squad_ID         = $row["squad_ID "];
        $obj->last_name        = $row["last_name"];
        $obj->first_name       = $row["first_name"];
        $obj->skill_level      = $row["skill_level"];
        $obj->homeEmail        = $row["homeEmail"];
        $obj->businessEmail    = $row["businessEmail"];
        $obj->homePhone        = $row["homePhone"];
        $obj->mobilePhone      = $row["mobilePhone"];
        $obj->businessPhone    = $row["businessPhone"];
    }

    /**
     * Initialize this instance;
     */
    private function init() {
        $this->entrant_ID = NULL;
        $this->entrant_draw_ID = NULL;
        $this->squad_ID = NULL;
        $this->last_name = NULL;
        $this->first_name = NULL;
        $this->skill_level = NULL;
        $this->homePhone = NULL;
        $this->mobilePhone = NULL;
        $this->businessPhone = NULL;
        $this->homeEmail = NULL;
        $this->businessEmail = NULL;
    }

} //end class