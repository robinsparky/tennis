<?php
namespace datalayer;

use DateTime;
use \TennisEvents;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 
 * Data and functions for Tennis Player(s)
 * @class  Player
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
*/
class Player extends AbstractData
{ 
    private static $tablename = 'player';

    const MAXSKILL = 7.0;
    const MINSKILL = 2.5;
    
    public const COLUMNS = <<<EOD
ID
,event_ID
,bracket_num
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
EOD;

    public const pCOLUMNS = <<<EOD
p.ID
,p.event_ID
,p.bracket_num
,p.first_name
,p.last_name
,p.gender
,p.birthdate
,p.skill_level
,p.emailHome
,p.emailBusiness
,p.phoneHome
,p.phoneMobile
,p.phoneBusiness
EOD;

    /*************** Instance Variables ****************/
    private $event_ID;
    private $bracket_num;
    private $last_name; //NOT NULL
    private $first_name;
    private $gender;
    private $birth_date;
    private $skill_level; //NOT NULL
    private $homePhone;
    private $mobilePhone;
    private $businessPhone;
    private $homeEmail;
    private $businessEmail;

    /**
     * Search for Players that have a name 'like' the provided criteria
     */
    public static function search(string $lname, string $fname='%'):array {
		$loc = __CLASS__ . "::" . __FUNCTION__;
        
		global $wpdb;

		$col = array();
		if(empty($lname)) {
			return $col;
		}

		$table = TennisEvents::getInstaller()->getDBTablenames()[self::$tablename];
		$columns = self::COLUMNS;
		
		$lname .= strpos($lname,'%') ? '' : '%';
		$fname .= strpos($fname,'%') ? '' : '%';
		$sql = "select {$columns} from $table where last_name like %s and first_name like %s";
		
		$safe = $wpdb->prepare($sql,$lname,$fname);
		$rows = $wpdb->get_results($safe, ARRAY_A);
		
		error_log("{$loc}: {$wpdb->num_rows} rows returned for name search: '$lname' and '$fname'");

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
     * Find all Players belonging to a specific event and/or bracket
     * @param array $fk_criteria Associative array of foreign key criteria:
     *                           'event_ID' => int,
     *                           'bracket_num' => int
     *                           'team_num' => int when getting members of a team
     *                           'unassigned' => any value
     *                           'division' => ''A or 'B'
     * @return array Array of Player objects
     */
    public static function find(...$fk_criteria) {
		$loc = __CLASS__ . "::" . __FUNCTION__;        
        $ids = print_r($fk_criteria,true);
        error_log("{$loc}:");
        error_log($ids);

		global $wpdb;
		$table = TennisEvents::getInstaller()->getDBTablenames()[self::$tablename];
        $intersection = TennisEvents::getInstaller()->getDBTablenames()['player_team'];

		$columns = self::pCOLUMNS;

        if( isset( $fk_criteria[0] ) && is_array( $fk_criteria[0]) ) $fk_criteria = $fk_criteria[0];

        if( array_key_exists( 'event_ID', $fk_criteria ) && array_key_exists( 'bracket_num', $fk_criteria ) && array_key_exists('assigned',$fk_criteria)) {            
            //find all the players assigned to any team
            $event_ID = $fk_criteria['event_ID'];
            $bracket_num = $fk_criteria['bracket_num'];
            $sql = "SELECT  {$columns}
                    FROM {$table} as p inner join {$intersection} j on p.event_ID=j.event_ID and p.bracket_num=j.bracket_num and p.ID = j.player_ID
                    where p.event_ID=%d and p.bracket_num=%d";
            error_log("Find all players assigned to ANY team: event_ID={$event_ID} bracket_num={$bracket_num}");
            $safe = $wpdb->prepare($sql, $event_ID, $bracket_num);
        }
        elseif( array_key_exists( 'event_ID', $fk_criteria ) && array_key_exists( 'bracket_num', $fk_criteria ) && array_key_exists('team_num',$fk_criteria) && array_key_exists('division',$fk_criteria)) {
            //find all the players in a team and squad (called division)
            $event_ID = $fk_criteria['event_ID'];
            $bracket_num = $fk_criteria['bracket_num'];
            $team_num = $fk_criteria['team_num'];
            $division = $fk_criteria['division'];
            $sql = "SELECT  {$columns}
                    FROM {$table} as p inner join {$intersection} j on p.event_ID=j.event_ID and p.bracket_num=j.bracket_num and p.ID = j.player_ID
                    where p.event_ID=%d and p.bracket_num=%d and j.team_num=%d and j.division='%s'";
            error_log("Find all players in a team and squad. event_ID={$event_ID} bracket_num={$bracket_num} team_num={$team_num} squad={$division}");
            $safe = $wpdb->prepare($sql, $event_ID,$bracket_num,$team_num,$division);
        }
        elseif( array_key_exists( 'event_ID', $fk_criteria ) && array_key_exists( 'bracket_num', $fk_criteria ) && array_key_exists('team_num',$fk_criteria)) {
            //find all the players in a team
            $event_ID = $fk_criteria['event_ID'];
            $bracket_num = $fk_criteria['bracket_num'];
            $team_num = $fk_criteria['team_num'];
            $sql = "SELECT  {$columns}
                    FROM {$table} as p inner join {$intersection} j on p.event_ID=j.event_ID and p.bracket_num=j.bracket_num and p.ID = j.player_ID
                    where p.event_ID=%d and p.bracket_num=%d and j.team_num=%d";
            error_log("Find all players in a team. event_ID={$event_ID} bracket_num={$bracket_num} team_num={$team_num}");
            $safe = $wpdb->prepare($sql, $event_ID, $bracket_num, $team_num);
        }
        elseif( array_key_exists( 'event_ID', $fk_criteria ) && array_key_exists( 'bracket_num', $fk_criteria ) ) {
            //All players in event/bracket
            $event_ID = $fk_criteria['event_ID'];
            $bracket_num = $fk_criteria['bracket_num'];
            $sql = "SELECT  {$columns}
                    FROM {$table} as p left join {$intersection} j on p.event_ID=j.event_ID and p.bracket_num=j.bracket_num and p.ID = j.player_ID
                    where p.event_ID=%d and p.bracket_num=%d";
            error_log("Find all players in an event and bracket. event_ID={$event_ID} bracket_num={$bracket_num}");
            $safe = $wpdb->prepare($sql,$event_ID,$bracket_num);
        }
        else {
            $mess = "{$loc}: Invalid foreign key criteria provided.";
            throw new \InvalidArgumentException($mess);
        }  

        // error_log("{$loc}:");
        // error_log(print_r($safe,true));
		$rows = $wpdb->get_results($safe, ARRAY_A);
		
		error_log("{$loc}: {$wpdb->num_rows} rows returned");

		$col = array();
		foreach($rows as $row) {
            $obj = new Player;
            self::mapData($obj,$row);
			$col[] = $obj;
		}
		return $col;
    }
    
    /**
     * Delete a Player from the database
     * @return int Number of rows affected
     */
    public static function deletePlayer(int $player_ID) : int {
		$loc = __CLASS__ . "::" . __FUNCTION__;
       
        if($player_ID <= 0) {
            error_log("{$loc} Invalid Player ID: {$player_ID}");
            return 0;
        }   
		global $wpdb;
        $result = 0;

        //Delete related data first
        //...player-team-squad
        $table = TennisEvents::getInstaller()->getDBTablenames()['player_team'];
        $values = ['player_ID'=>$player_ID];    
        $formats_values = ['%d'];
        $wpdb->delete( $table, $values, $formats_values );
        $result += $wpdb->rows_affected;

		//Delete the Person
		$table = TennisEvents::getInstaller()->getDBTablenames()[self::$tablename];
		$where = array( 'ID'=>$player_ID );
		$formats_where = array( '%d' );
		$wpdb->delete( $table, $where, $formats_where );
		$result += $wpdb->rows_affected;

        error_log("{$loc} $wpdb->rows_affected rows affected.");

		return $result;
    }

	/**
	 * Get instance of a Player using his or her ID
	 */
    static public function get(int ...$pks) {
		$loc = __CLASS__ . "::" . __FUNCTION__;

		global $wpdb;
        
		$table = TennisEvents::getInstaller()->getDBTablenames()[self::$tablename];
		$columns = self::COLUMNS;

		$sql = "select {$columns} from $table where ID=%d";
		$safe = $wpdb->prepare($sql,$pks);
		$rows = $wpdb->get_results($safe, ARRAY_A);

		error_log("{$loc}(id) $wpdb->num_rows rows returned.");

        $obj = NULL;
		if(count($rows) === 1) {
			$obj = new Player;
            self::mapData($obj,$rows[0]);
		}
		return $obj;
	}

	/*************** Instance Methods ****************/
	public function __construct(int $event_ID = 0, int $bracket_num = 0) {
        parent::__construct(true);
		$loc = __CLASS__ . "::" . __FUNCTION__;
        $this->isnew = TRUE;
        $this->event_ID = $event_ID <= 0 ? 0 : $event_ID;
        $this->bracket_num = $bracket_num <= 0 ? 0 : $bracket_num;
        $this->init();
    }
    
    public function __destruct() {
        $loc = __CLASS__ . "::" . __FUNCTION__;
    }

    public function getEventID():int {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        return $this->event_ID ?? 0;
    }

    public function setEventID(int $event_ID): bool {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        if($event_ID <= 0) return false;
        $this->event_ID = $event_ID;
        return $this->setDirty();
    }   

    public function getBracketNum():int {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        return $this->bracket_num ?? 0;
    }   

    public function setBracketNum(int $bracket_num): bool {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        if($bracket_num <= 0) return false;
        $this->bracket_num = $bracket_num;
        return $this->setDirty();
    }

    public function setLastName(string $last = '') : bool {
		$loc = __CLASS__ . "::" . __FUNCTION__;
        $result = false;
        if(strlen($last) < 2) return $result;

        $this->last_name = $last;
        $result = $this->setDirty();

        return $result;
    }

    public function getLastName():string {
		$loc = __CLASS__ . "::" . __FUNCTION__;
        return $this->last_name ?? '';
    }

    public function setFirstName(string $first = '') : bool {
		$loc = __CLASS__ . "::" . __FUNCTION__;

        $result = false;
        if(strlen($first) < 2) return $result;

        $this->first_name = $first;
        $result = $this->setDirty();

        return $result;
    }

    public function getFirstName():string {
		$loc = __CLASS__ . "::" . __FUNCTION__;
        return $this->first_name ?? '';
    }

    public function getName():string {
        $fname = $this->getFirstName();
        $lname = $this->getLastName();
        return trim("{$fname} {$lname}");
    } 
    
    public function setSkillLevel(float $skill = self::MINSKILL) : bool {
		$loc = __CLASS__ . "::" . __FUNCTION__;

        $result = false;
        if($skill < self::MINSKILL || $skill > self::MAXSKILL) return $result;
        
        $this->skill_level = $skill;
        $result = $this->setDirty();

        return $result;
    }

    public function getSkillLevel(): float {
        return $this->skill_level ?? self::MINSKILL;
    }

    /**
     * Set Gender of Player
     * TODO: use ENUM in database
     * @param string $gender 'M', 'F', or 'O' (other)
     */
    public function setGender(string $gender = 'O') : bool {
		$loc = __CLASS__ . "::" . __FUNCTION__;

        $result = false;
        if( !in_array( $gender, array('M','F','O') ) ) return $result;
        $this->gender = $gender;
        $result = $this->setDirty();

        return $result;
    }

    public function getGender():string {
        return $this->gender ?? 'O';
    }

    public function setBirthDateStr(string $birthDate) : bool {
		$loc = __CLASS__ . "::" . __FUNCTION__;

        $result = false;
        $temp = new DateTime($birthDate);
        if($temp === false) return $result;
        $this->birth_date = $temp;

        $result = $this->setDirty();
        return $result;
    }
    
    public function setBirthDate(DateTime $birthDate) : bool {
		$loc = __CLASS__ . "::" . __FUNCTION__;

        $result = false;
        $this->birth_date = $birthDate;

        $result = $this->setDirty();
        return $result;
    }

	/**
	 * Get the birth date for this player as a string
	 */
	public function getBirthDateStr() : string {
		if( !isset( $this->birth_date ) ) return '';
		else return $this->birth_date->format( "Y-m-d");
	}

    public function getBirthDate(): \DateTime|null {
        $result = new \DateTime($this->birth_date);
        return $result ?? $this->birth_date ?? null;
    }

    public function setHomePhone(string $phone) : bool {
        $this->homePhone = $phone;
        return $this->isdirty();
    }

    public function getHomePhone():string {
        return $this->homePhone ?? '';
    }
    
    public function setMobilePhone(string $phone) : bool {
        $this->mobilePhone = $phone;
        return $this->isdirty();
    }

    public function getMobilePhone():string {
        return $this->mobilePhone ?? '';
    }

    public function setBusinessPhone(string $phone) : bool {
        $this->businessPhone = $phone;
        return $this->isdirty();
    }

    public function getBusinessPhone():string {
        return $this->businessPhone ?? '';
    }
    
    public function setHomeEmail(string $email) {
		$loc = __CLASS__ . "::" . __FUNCTION__;
        
		$result = false;
		if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
			$this->homeEmail = $email;
			$result = $this->setDirty();
		}
        else {
            $this->log->error_log("{$loc} Home Email address '{$email}' is NOT valid.");
        }
		return $result;
    }

    public function getHomeEmail():string {
        return $this->homeEmail ?? '';
    }
    
    public function setBusinessEmail(string $email) {
		$loc = __CLASS__ . "::" . __FUNCTION__;

		$result = false;
		if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
			$this->businessEmail = $email;
			$result = $this->setDirty();
		}
        else {
            $this->log->error_log("{$loc} Business Email address '{$email}' is NOT valid.");
        }
		return $result;
    }

    public function getBusinessEmail():string {
        return $this->businessEmail ?? '';
    }

    public function isValid() {
        $isvalid = TRUE;
        if($this->getEventID() <= 0) $isvalid = FALSE;
        if($this->getBracketNum() <= 0) $isvalid = FALSE;
        if(empty($this->getFirstName())) $isvalid = FALSE;
        if(empty($this->getLastName())) $isvalid = FALSE;
        if(empty($this->getHomeEmail())) $isvalid = FALSE;
        if(empty($this->getBirthDateStr())) $isvalid = FALSE;
        
        return $isvalid;
    }

    /**
     * Delete this Player from the database
     * @return int Number of rows affected
     */
    public function delete() : int {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("{$loc}");

        return self::deletePlayer($this->getID());
    }

    private function checkExists(string $fname, string $lname) : bool {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("{$loc}");

        $exists = false;

        $players = self::search($lname,$fname);
        if(count($players) > 0) {
            $exists = true;
        }

        return $exists;
    }
	protected function create() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("{$loc}");

        if( $this->checkExists( $this->getFirstName(), $this->getLastName() ) ) {
            $mess = "{$loc}: Player '{$this->getFirstName()} {$this->getLastName()}' already exists.";
            $this->log->error_log($mess);
            return 0;
        }

        global $wpdb;
        
        parent::create();

        $result = 0;
		$table = TennisEvents::getInstaller()->getDBTablenames()[self::$tablename];

        $values = array('last_name' => $this->getLastName()
                        ,'first_name' => $this->getFirstName()
                        ,'event_ID' => $this->getEventID()
                        ,'bracket_num' => $this->getBracketNum()
                        ,'skill_level' => $this->getSkillLevel()
                        ,'birthdate' => $this->getBirthDateStr()
                        ,'phoneHome' => $this->getHomePhone()
                        ,'phoneBusiness' => $this->getBusinessPhone()
                        ,'phoneMobile' => $this->getMobilePhone()                               
                        ,'emailHome' => $this->getHomeEmail()                              
                        ,'emailBusiness' => $this->getBusinessEmail()
                        );
		$formats_values = array('%s','%s','%d','%d','%f','%s','%s','%s','%s','%s','%s');
		$result = $wpdb->insert($table, $values, $formats_values);
        
		if( $result === false || $result === 0 ) {
			$mess = "{$loc}: wpdb->insert returned false or inserted 0 rows.";
			$err = empty($wpdb->last_error) ? '' : $wpdb->last_error;
			$mess .= " : Err='$err'";
			throw new InvalidEntrantException($mess);
		}
		
		$this->ID = $wpdb->insert_id;
		$this->isnew = FALSE;
		$this->isdirty = FALSE;

		error_log("{$loc} {$result} rows affected.");

		return $result;
	}

	protected function update() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("{$loc}");

		global $wpdb;
        parent::update();

		$table = TennisEvents::getInstaller()->getDBTablenames()[self::$tablename];

        $values = array('last_name' => $this->getLastName()
                        ,'first_name' => $this->getFirstName()
                        ,'event_ID' => $this->getEventID()
                        ,'bracket_num' => $this->getBracketNum()
                        ,'skill_level' => $this->getSkillLevel()
                        ,'birthdate' => $this->getBirthDateStr()
                        ,'phoneHome' => $this->getHomePhone()
                        ,'phoneBusiness' => $this->getBusinessPhone()
                        ,'phoneMobile' => $this->getMobilePhone()                               
                        ,'emailHome' => $this->getHomeEmail()                              
                        ,'emailBusiness' => $this->getBusinessEmail()
                        );
        $formats_values = array('%s','%s','%d','%d','%f','%s','%s','%s','%s','%s','%s');
		$where          = array('ID' => $this->getID());
		$formats_where  = array('%d');
		$result = $wpdb->update($table, $values, $where, $formats_values, $formats_where);
		$this->isdirty = FALSE;

		$result += $this->manageRelatedData();

		$this->log->error_log("{$loc} {$result} rows affected.");

		return $result;
	}

    private function manageRelatedData() : int {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("{$loc}");

        $result = 0;

        //Manage related data here
        //...team memberships, squad memberships, entrant records, etc.

        return $result;
    }

    /**
     * Map incoming data to an instance of Player
     */
    protected static function mapData($obj,$row) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        // error_log("{$loc}");

        parent::mapData($obj,$row);
        $obj->last_name        = $row["last_name"];
        $obj->first_name       = $row["first_name"];
        $obj->event_ID         = $row["event_ID"];
        $obj->bracket_num      = $row["bracket_num"];
        $obj->skill_level      = $row["skill_level"];
		$obj->birth_date       = isset( $row['birthdate'] ) ? new \DateTime( $row['birthdate'] ) : null;
        $obj->homeEmail        = $row["emailHome"];
        $obj->businessEmail    = $row["emailBusiness"];
        $obj->homePhone        = $row["phoneHome"];
        $obj->mobilePhone      = $row["phoneMobile"];
        $obj->businessPhone    = $row["phoneBusiness"];
    }

    /**
     * Initialize this instance;
     */
    private function init() {

    }

} //end class