<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require('abstract-class-data.php');

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
    public static function find($fk_id,$context) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$sql = "select * from $table where entry_ID = %d";
		$safe = $wpdb->prepare($sql,$fk_id);
		$rows = $wpdb->get_results($safe, ARRAY_A);
		
		error_log("Player::find $wpdb->num_rows rows returned using entry_ID=$fk_id");

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
    static public function get($id) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
		$sql = "select * from $table where ID=%d";
		$safe = $wpdb->prepare($sql,$id);
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
    

    public function setLastName($last) {
        if(!is_string($last) || strlen($last) < 2) return;
        $this->last_name = $last;
        $this->isdirty = TRUE;
    }

    public function getLastName() {
        return $this->last_name;
    }

    public function setFirstName($first) {
        if(!is_string($first) || strlen($first) < 2) return;
        $this->first_name = $first;
        $this->isdirty = TRUE;
    }

    public function getFirstName() {
        return $this->first_name;
    }
    
    public function setSkillLevel($skill) {
        if(is_nan($skill)) return;
        if($skill < self::MINSKILL || $skill > self::MAXSKILL) return;
        $this->skill_level = $skill;
        $this->isdirty = TRUE;
    }

    public function getSkillLevel() {
        return $this->skill;
    }

    public function setEntrantID($id) {
        if(!is_numeric($id) || $id < 0) return;
        $this->tennis_entrant_ID = $id;
    }

    public function getEntrantID() {
        return $this->tennis_entrant_ID;
    }
    
    public function setDrawID($id) {
        if(!is_numeric($id) || $id < 0) return;
        $this->tennis_entrant_draw_ID = $id;
    }

    public function getDrawID() {
        return $this->tennis_entrant_draw_ID;
    }
    
    public function setSquadID($id) {
        if(!is_numeric($id) || $id < 0) return;
        $this->tennis_squad_ID = $id;
    }

    public function getSquadID() {
        return $this->tennis_squad_ID;
    }

    public function setHomePhone($phone) {
        if(!is_string($phone)) return;
        $this->homePhone = $phone;
        $this->isdirty = TRUE;
    }

    public function getHomePhone() {
        return $this->homePhone;
    }
    
    public function setMobilePhone($phone) {
        if(!is_string($phone)) return;
        $this->mobilePhone = $phone;
        $this->isdirty = TRUE;
    }

    public function getMobilePhone() {
        return $this->mobilePhone;
    }

    public function setBusinessPhone($phone) {
        if(!is_string($phone)) return;
        $this->businessPhone = $phone;
        $this->isdirty = TRUE;
    }

    public function getBusinessPhone() {
        return $this->businessPhone;
    }
    
    public function setHomeEmail($email) {
        if(!is_string($email)) return;
        $this->homeEmail = $email;
        $this->isdirty = TRUE;
    }

    public function getHomeEmail() {
        return $this->homeEmail;
    }
    
    public function setBusinessEmail($email) {
        if(!is_string($email)) return;
        $this->businessEmail = $email;
        $this->isdirty = TRUE;
    }

    public function getBusinessEmail() {
        return $this->businessEmail;
    }


	/**
	 * Get all my children!
	 */
    public function getChildren() {

    }

	private function create() {
        global $wpdb;
        
        if(!$this->isValid()) return;

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

		error_log("Player::create $wpdb->rows_affected rows affected.");

		return $wpdb->rows_affected;
	}

	private function update() {
		global $wpdb;

        if(!$this->isValid()) return;

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