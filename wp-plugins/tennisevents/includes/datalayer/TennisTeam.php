<?php
namespace datalayer;
use \TennisEvents;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 
 * Data and functions for Tennis Team(s)
 * @class  TennisTeam
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
*/
class TennisTeam extends AbstractData
{ 
    public static $tablename = 'team';

    const MAXSKILL = 7.0;
    const MINSKILL = 2.5;
    
	public const COLUMNS = <<<EOD
    event_ID
    ,bracket_num
    ,team_num
    ,name
    EOD;
    
	public const tCOLUMNS = <<<EOD
    t.event_ID
    ,t.bracket_num
    ,t.team_num
    ,t.name
    EOD;

    private $event_ID;
    private $bracket_num;
    private $team_num;
    private $name;
    private $division;

    private $members = [];

    /**
     * Search for Teams that have a name 'like' the provided criteria
     */
    public static function search(string $name):array {
		$loc = __CLASS__ . "::" . __FUNCTION__;
        
		global $wpdb;

		$col = array();
		if(empty($lname)) {
			return $col;
		}

		$table = TennisEvents::getInstaller()->getDBTablenames()[self::$tablename];
		$columns = self::COLUMNS;
		
		$name .= strpos($name,'%') ? '' : '%';
		$sql = "select {$columns} from $table where name like %s";
		
		$safe = $wpdb->prepare($sql,$name);
		$rows = $wpdb->get_results($safe, ARRAY_A);
		
		error_log("{$loc}: {$wpdb->num_rows} rows returned for name search: '$name'");

		$col = array();
		foreach($rows as $row) {
            $obj = new TennisTeam(null);
            self::mapData($obj,$row);
			$obj->isnew = FALSE;
			$col[] = $obj;
		}
		return $col;
    }
    
    /**
     * Find all Teams belonging to a specific event and bracket;
     */
    public static function find(...$fk_criteria) {
		$loc = __CLASS__ . "::" . __FUNCTION__;
        $ids = print_r($fk_criteria,true);
        error_log("{$loc}:");
        error_log($ids);

		global $wpdb;

		$table = TennisEvents::getInstaller()->getDBTablenames()[self::$tablename];
		$joinTable = TennisEvents::getInstaller()->getDBTablenames()['squad'];
		$columns = self::tCOLUMNS;
        
		if(isset( $fk_criteria[0] ) && is_array( $fk_criteria[0]) ) $fk_criteria = $fk_criteria[0];

        $col = array();
        if( array_key_exists( 'event_ID', $fk_criteria) && array_key_exists( 'bracket_num', $fk_criteria)) {
			//All teams belonging to specified event and bracket
			$sql = "SELECT {$columns}, s.division
					from $table as t
                    left join {$joinTable} as s on t.event_ID = s.event_ID and t.bracket_num = s.bracket_num and t.team_num = s.team_num 
					WHERE t.event_ID = %d AND t.bracket_num = %d;";
            error_log("Find all teams where event_ID={$fk_criteria["event_ID"]} bracket_num={$fk_criteria["bracket_num"]}");
		    $safe = $wpdb->prepare($sql,$fk_criteria["event_ID"], $fk_criteria["bracket_num"]);
        }
        else if( array_key_exists( 'event_ID', $fk_criteria) ) {
            //All teams belonging to specified event    
            $sql = "SELECT {$columns}, s.division
                    from $table as t
                    left join {$joinTable} as s on t.event_ID = s.event_ID and t.bracket_num = s.bracket_num and t.team_num = s.team_num 
                    WHERE t.event_ID = %d;";
            error_log("Find all teams where event_ID={$fk_criteria["event_ID"]}");
            $safe = $wpdb->prepare($sql,$fk_criteria["event_ID"]);
        } else {
			return $col;
		}

        // error_log("{$loc}:");
        // error_log(print_r($safe,true));
		$rows = $wpdb->get_results($safe, ARRAY_A);
		
		error_log("{$loc} {$wpdb->num_rows} rows returned");

		foreach($rows as $row) {
            $obj = new TennisTeam(null);
            self::mapData($obj,$row);
			$col[] = $obj;
		}
		return $col;
    }

	/**
	 * Get instance of a Team or an array of 2 team instances: one for each division/squad
     * @param int ...$pks club_ID, event_ID, bracket_num, team_num and possibly squad
     * @return TennisTeam|array Instance of TennisTeam or array
	 */
    static public function get(...$pks) : TennisTeam | array {
		$loc = __CLASS__ . "::" . __FUNCTION__;
        $ids = print_r($pks,true);
        error_log("{$loc}:");
        error_log($ids);


		global $wpdb;
		$table = TennisEvents::getInstaller()->getDBTablenames()[self::$tablename];
		$joinTable = TennisEvents::getInstaller()->getDBTablenames()['squad'];

		$columns = self::tCOLUMNS;

        $event_ID = 0;
        $bracket_num = 0;
        $team_num = 0;
        $squad = '';
        
        switch(count($pks)) {
            case 3:
                $event_ID = $pks[0];
                $bracket_num = $pks[1];
                $team_num = $pks[2];
                $sql = "select {$columns}, s.divsion from $table as t "
                    . " left join {$joinTable} as s on t.event_ID = s.event_ID and t.bracket_num = s.bracket_num and t.team_num = s.team_num "
                    . " where t.event_ID=%d and t.bracket_num=%d and t.team_num=%d;";
                $safe = $wpdb->prepare($sql,$event_ID,$bracket_num,$team_num);
            break;
            case 4:
                $event_ID = $pks[0];
                $bracket_num = $pks[1];
                $team_num = $pks[2];
                $squad = $pks[3];
                $sql = "select {$columns}, s.division from $table as t "
                    . " left join {$joinTable} as s on t.event_ID = s.event_ID and t.bracket_num = s.bracket_num and t.team_num = s.team_num "
                    . " where t.event_ID=%d and t.bracket_num=%d and t.team_num=%d and s.division='%s';";
                $safe = $wpdb->prepare($sql,$event_ID,$bracket_num,$team_num,$squad);
            break;
            default:
                return [];
        }

        error_log("{$loc}:");
        error_log(print_r($safe,true));
		$rows = $wpdb->get_results($safe, ARRAY_A);

		error_log("{$loc} {$wpdb->rows_affected} returned.");

        $col=[];      
        $obj = NULL;
		if(count($rows) === 1) {
			$obj = new TennisTeam(null);
            self::mapData($obj,$rows[0]);
		    return $obj;
		}
        else { 
            foreach($rows as $row) {
                $obj = new TennisTeam(null);
                self::mapData($obj,$row);
                $col[] = $obj;
            }
            return $col;
        }
	}
    
	/**
	 * Delete the team and related player_team, squad
     * @param int $eventId The ID of the event
     * @param int $bracketNum The bracket number 
     * @param int $teamNum The team number
     * @return int Number of rows affected
	 */
	public static function deleteTeam( int $eventId = 0, int $bracketNum = 0, int $teamNum = 0 ) : int {
        $loc = __CLASS__ . '::' . __FUNCTION__;

        global $wpdb;
		$result = 0;
        if( 0 === $eventId || 0 === $bracketNum || 0 === $teamNum ) return $result;
        
        $values = ['event_ID' => $eventId, 'bracket_num' => $bracketNum, 'team_num' => $teamNum];    
        $formats_values = ['%d','%d','%d'];

        $table = TennisEvents::getInstaller()->getDBTablenames()['player_team'];
        $wpdb->delete( $table, $values, $formats_values );
        $result += $wpdb->rows_affected;

        $table = TennisEvents::getInstaller()->getDBTablenames()['squad'];
        $wpdb->delete( $table, $values, $formats_values );
        $result += $wpdb->rows_affected;
        
        $table = TennisEvents::getInstaller()->getDBTablenames()[self::$tablename];
        $result += $wpdb->rows_affected;
        
        error_log("{$loc} {$result} rows affected.");

		return $result;
    }

    /**
     * Delete all Teams and related player_team, squads for the specified event and bracket
     * @param int $eventId The ID of the event
     * @param int $bracketNum The bracket number 
     * @return int Number of rows affected
     */
    public static function deleteAllTeams( int $eventId = 0, int $bracketNum = 0 ) : int    {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        
        $result = 0;
        if( 0 === $eventId || 0 === $bracketNum ) return $result;

        global $wpdb;
        $values = ['event_ID' => $eventId, 'bracket_num' => $bracketNum];
        $formats_values = ['%d','%d'];

        $table = TennisEvents::getInstaller()->getDBTablenames()['player_team'];
        $wpdb->delete( $table, $values, $formats_values );
        $result += $wpdb->rows_affected;

        $table = TennisEvents::getInstaller()->getDBTablenames()['squad'];
        $wpdb->delete( $table, $values, $formats_values );
        $result += $wpdb->rows_affected;

        //Delete all players in this event/bracket
        $table = TennisEvents::getInstaller()->getDBTablenames()['player'];
        $wpdb->delete( $table, $values, $formats_values );
        $result += $wpdb->rows_affected;
        
        $table = TennisEvents::getInstaller()->getDBTablenames()[self::$tablename];
        $wpdb->delete( $table, $values, $formats_values );
        $result += $wpdb->rows_affected;
        
        error_log("{$loc} {$result} rows affected.");

        return $result;
    }

	/*************** Instance Methods ****************/
	public function __construct( ?string $name, int $event_ID = 0, int $bracket_num = 0, $team_num = 0, $division='') {
        parent::__construct( true );
		$loc = __CLASS__ . "::" . __FUNCTION__;
        $this->log->error_log("{$loc}({$name},{$event_ID},{$bracket_num},{$team_num})");
    
        if( isset( $name )  && (strlen( $name ) > 0) ) {
           $this->name = $name;
        }   
        if( !empty($division) && in_array($division,['A','B'])) $this->division = $division;
        $this->event_ID = $event_ID;
        $this->bracket_num = $bracket_num;
        $this->team_num = $team_num;
        
        $this->isnew = true;
    }

    public function getEventID(): int {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        return $this->event_ID ?? 0;
    }
    
    public function setEventID(int $eventID) : bool {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        $result = false;
        if($eventID < 1) return $result;

        $this->event_ID = $eventID;
        $result = $this->setDirty();

        return $result;
    }

    public function getBracketNum(): int {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        return $this->bracket_num ?? 0;
    }

    public function setBracketNum(int $bracketNum) : bool {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        $result = false;
        if($bracketNum < 1) return $result;

        $this->bracket_num = $bracketNum;
        $result = $this->setDirty();

        return $result;
    }

    public function getTeamNum(): int {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        return $this->team_num ?? 0;
    }


    public function setName(string $name) : bool {
		$loc = __CLASS__ . "::" . __FUNCTION__;
        $result = false;
        if(strlen($name) < 1) return $result;

        $this->name = $name;
        $result = $this->setDirty();

        return $result;
    }

    public function getName(): string {
		$loc = __CLASS__ . "::" . __FUNCTION__;
        return $this->name ?? '';
    }

    /**
     * Get the Squad (i.e. division) for this team. Maybe be empty.
     * For TTC Team Tennis, this is extracted from the end of the team name.
     * TODO: Formalize division as a property of TennisTeam.
     */
    public function getSquad() : string {
		$loc = __CLASS__ . "::" . __FUNCTION__;
        
        if(!empty($this->division)) return $this->division;

        $division = '';
        $pat = "/\d+[AB]$/i";
        if(preg_match($pat, $this->getName(), $matches)) {
            $division = substr($matches[0],1);
        } 
        return $division;
    }

    public function getMembers(string $squad = '', $force=false): array {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("{$loc}");

        if(empty($this->members)) {
            $this->fetchMembers($squad);
        }
        elseif($force) {
            $this->fetchMembers($squad);
        }
        return $this->members;
    }

    public function addMember(Player $player) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("{$loc}");
        
		$result = false;
		$found = false;
        foreach( $this->getMembers() as $p ) {
            if($p->getFirstName() ===  $player->getFirstName() && $p->getLastName() === $player->getLastName() ) {
                $found = true;
                break;
            }
        }
        if( !$found ) {
            $this->members[] = $player;
            $result = $this->setDirty();
        }
		
        return $result;
    }

    /**
     * Remove player from this team
     * @param Player $player An instance of Player
     * @return int The number of db records affected
     */
    public function removeMember(Player $player) : int {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("{$loc}");
        
        global $wpdb;

        $table = TennisEvents::getInstaller()->getDBTablenames()['player_team'];
		$num = 0;
		$result = 0;
        foreach( $this->getMembers() as &$m ) {
            if( $m->getFirstName() === $player->getFirstName() && $m->getLastName() === $player->getLastName() ) {
                $result = true;
                unset( $this->members[$num] );
                
                //Delete player intersection data
                //...player-team-squad
                $values = ['event_ID'=>$this->getEventID(),'bracket_num'=>$this->getBracketNum(),'team_num'=>$this->getTeamNum(),'player_ID'=>$player->getID()];    
                $formats_values = ['%d','%d','%d','%d'];
                $wpdb->delete( $table, $values, $formats_values );
                $result = $wpdb->rows_affected;
            }
            ++$num;
        }
        $this->log->error_log("{$loc}: rows_affected={$result}");
        return $result;
    }

    /**
     * Remove all members of this Team
     * @return int The number of db records deleted
     */
    public function removeAllTeamMembers() : int {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("{$loc}");
        global $wpdb;


        $table = TennisEvents::getInstaller()->getDBTablenames()['player_team'];
        //Delete player intersection data
        //...player-team-squad
        $values = ['event_ID'=>$this->getEventID(),'bracket_num'=>$this->getBracketNum(),'team_num'=>$this->getTeamNum()];    
        $formats_values = ['%d','%d','%d'];
        $wpdb->delete( $table, $values, $formats_values );
        $result = $wpdb->rows_affected;

        return $result;
    }

    /**
     * Remove all members of the given Squad for this Team
     * @param string $squad The squad/division identifier
     * @return int The number of db records deleted
     */
    public function removeAllSquadMembers( string $squad = '') : int {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("{$loc}");
        global $wpdb;


        $table = TennisEvents::getInstaller()->getDBTablenames()['player_team'];
        //Delete player intersection data
        //...player-team-squad
        $values = ['event_ID'=>$this->getEventID(),'bracket_num'=>$this->getBracketNum(),'team_num'=>$this->getTeamNum(),'division'=>$squad];    
        $formats_values = ['%d','%d','%d','%s'];
        $wpdb->delete( $table, $values, $formats_values );
        $result = $wpdb->rows_affected;

        return $result;
    }

    /**
     * Delete this Team from the database
     * @return int Number of rows affected
     */
    public function delete() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("{$loc}");

        return self::deleteTeam( $this->getEventID(), $this->getBracketNum(), $this->getTeamNum() );
    }

    /** 
     * Validate this Team instance
     * @return bool TRUE if valid, FALSE otherwise
     */
    public function isValid()  {
        $isValid = TRUE;
        if( !isset( $this->event_ID ) || !is_int( $this->event_ID ) ) $isValid = FALSE;
        if( !isset( $this->bracket_num ) || !is_int( $this->bracket_num ) ) $isValid = FALSE;
        if( empty( $this->name ) ) $isValid = FALSE;

        return $isValid;
    }
    
	/**
	 * Fetch the members for this team from the database
	 */
	private function fetchMembers( string $squad = '') {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        error_log("{$loc}({$squad})");

        if(empty($squad)) {
            $args = array("event_ID" => $this->getEventID()
                        ,"bracket_num" => $this->getBracketNum()
                        ,"team_num" => $this->getTeamNum()
                        );
        } else {
            $args =  array("event_ID" => $this->getEventID()
                        ,"bracket_num" => $this->getBracketNum()
                        ,"team_num" => $this->getTeamNum()
                        ,"division" => $this->getSquad()
                        );
        }
        $test = Player::find($args);
        if(isset($test) && is_array($test)) {
            $mess="Array of players returned."; //print_r($test,true);
            $this->members = $test;
        }
        elseif(isset($test)) {
            $mess= "Returned value is NOT array " . print_r($test,true);
            $this->members = [];
        }
        else {
            $mess = "Return value is null";
            $this->members = [];
        }
        $this->log->error_log("{$loc}: $mess");
	}
    
    /** 
     * Create this Team in the database
     * @return int Number of rows affected
     */
	protected function create() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("{$loc}");

        global $wpdb;
        
        parent::create();

        $result = 0;
		$table = TennisEvents::getInstaller()->getDBTablenames()[self::$tablename];

        $wpdb->query("LOCK TABLES {$table} LOW_PRIORITY WRITE;");

        if( isset( $this->team_num ) && $this->team_num > 0 ) {
            //If team_num has a value then use it
            $sql = "SELECT COUNT(*) FROM $table WHERE event_ID=%d AND bracket_num=%d AND team_num=%d;";
            $exists = (int) $wpdb->get_var( $wpdb->prepare( $sql,$this->event_ID, $this->bracket_num, $this->team_num ), 0, 0 );
            
            //If this match arleady exists throw exception
            if( $exists > 0 ) {
                $wpdb->query( "UNLOCK TABLES;" );                
                $code = 570;
                throw new InvalidMatchException( sprintf("Cannot create '%s' because it already exists (%d)", $this->name, $exists ), $code );
            }
        }
        else {
            //If team_num is not provided, then use the next largest value from the db
            $sql = "SELECT IFNULL(MAX(team_num),0) FROM $table WHERE event_ID=%d AND bracket_num=%d;";
            $safe = $wpdb->prepare( $sql, $this->event_ID, $this->bracket_num );
            $this->team_num = $wpdb->get_var( $safe ) + 1;
            $this->log->error_log( sprintf( "{$loc} -> creating team '%s'", $this->getName() ) );
        }
        $values = array( 'event_ID' => $this->event_ID
                        ,'bracket_num' => $this->bracket_num
                        ,'team_num' => $this->team_num
                        ,'name' => $this->name
                        );
		$formats_values = array('%d','%d','%d','%s');
		$result = $wpdb->insert($table, $values, $formats_values);
        
		if( $result === false || $result === 0 ) {
			$mess = "{$loc}: wpdb->insert returned false or inserted 0 rows.";
			$err = empty($wpdb->last_error) ? '' : $wpdb->last_error;
			$mess .= " : Err='$err'";
			throw new InvalidEntrantException($mess);
		}
            
		$this->isnew = false;
		$this->isdirty = false;

        $result += $this->createRelatedData();

		error_log("{$loc} {$result} rows affected.");

		return $result;
	}
    
    /**
     * Update this Team in the database
     * @return int Number of rows affected
     */
	protected function update() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("{$loc}");

		global $wpdb;
        parent::update();

		$table = TennisEvents::getInstaller()->getDBTablenames()[self::$tablename];

        $values         = array('name' => $this->name
                               );
        $formats_values = array('%s');
		$where          = array('event_ID' => $this->getEventID()
                                ,'bracket_num' => $this->getBracketNum()
                                ,'team_num' => $this->getTeamNum()
                             );
		$formats_where  = array('%d','%d','%d');
		$result = $wpdb->update($table, $values, $where, $formats_values, $formats_where);
		$this->isdirty = FALSE;
        
		$result += $this->manageRelatedData();

		$this->log->error_log("{$loc} {$result} rows affected.");

		return $result;
	}

    /**
     * Create related data for this Team
     * TODO: This is temporary - needs api for Squad management
     * @return int Number of rows affected
     */
    private function createRelatedData() : int {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("{$loc}");

		global $wpdb;
        $table = TennisEvents::getInstaller()->getDBTablenames()['squad'];
        
        $wpdb->query("LOCK TABLES {$table} LOW_PRIORITY WRITE;");
        $sql = "SELECT COUNT(*) FROM $table WHERE event_ID=%d AND bracket_num=%d AND team_num=%d AND (division='A' OR division='B');";
        $exists = (int) $wpdb->get_var( $wpdb->prepare( $sql,$this->event_ID, $this->bracket_num, $this->team_num ), 0, 0 );
        
        //If this match arleady exists throw exception
        if( $exists > 0 ) {
            $wpdb->query( "UNLOCK TABLES;" );
            return 0;                
        }
        $Avalues = ['event_ID' => $this->event_ID
                  ,'bracket_num' => $this->bracket_num
                  ,'team_num' => $this->team_num
                  ,'division' => 'A'
                 ];
        $formats_values = ['%d','%d','%d','%s'];
        $wpdb->insert($table, $Avalues, $formats_values);
        $result = $wpdb->rows_affected;

        $Bvalues = ['event_ID' => $this->event_ID
                  ,'bracket_num' => $this->bracket_num
                  ,'team_num' => $this->team_num
                  ,'division' => 'B'
                 ];
        $wpdb->insert($table, $Bvalues, $formats_values);
        $result += $wpdb->rows_affected;
        $wpdb->query("UNLOCK TABLES;");

        return $result;
    }   

    private function manageRelatedData() : int {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("{$loc}");
        
        $result = 0;
		global $wpdb;
        $table = TennisEvents::getInstaller()->getDBTablenames()[self::$tablename];
        $squadJoinTable = TennisEvents::getInstaller()->getDBTablenames()['squad'];
        $playerJoinTable = TennisEvents::getInstaller()->getDBTablenames()['player_team'];
        $sql = <<< EOS
select count(*) from {$table} as t 
inner join {$squadJoinTable} as s 
on t.event_ID = s.event_ID and t.bracket_num = s.bracket_num and t.team_num = s.team_num 
inner join {$playerJoinTable} as p 
on s.event_ID = p.event_ID and s.bracket_num = p.bracket_num and s.team_num = p.team_num and s.division = p.division 
where t.team_num = %d and p.player_ID=%d 
EOS;

        $division = $this->getSquad();
        $division = empty($division) ? 'A' : $division;
		//Save team members
		if( count( $this->members ) > 0 ) {
			foreach( $this->members as $player ) {
				if( $player->isValid() ) {
					$result += $player->save();
                    $this->log->error_log("{$loc}: processing player: '{$player->getID()}'");
                    $safe = $wpdb->prepare($sql,$this->getTeamNum(),$player->getID());
                    $exists = (int) $wpdb->get_var($safe,0,0);
                    $this->log->error_log("{$loc}: exists={$exists}");
                    $this->log->error_log("{$safe}");
                    if($exists < 1) {
                        //Now insert an intersection record to link this player to this team/squad
                        $values = array( 'event_ID' => $this->getEventID()
                                        ,'bracket_num' => $this->getBracketNum()
                                        ,'team_num' => $this->getTeamNum()
                                        ,'player_ID' => $player->getID()
                                        ,'division' => $division
                                        );
                        $formats_values = array('%d','%d','%d','%d','%s');
                        $result += $wpdb->insert($playerJoinTable, $values, $formats_values);
                    }
				}
			}
		}
        return $result;
    }
    
    /**
     * Map incoming data to an instance of Team
     */
    protected static function mapData($obj,$row) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        error_log("{$loc}");

        parent::mapData($obj,$row);
        $obj->event_ID    = $row["event_ID"];
        $obj->bracket_num = $row["bracket_num"];
        $obj->team_num    = $row["team_num"];
        $obj->name        = $row["name"];
        $obj->division    = isset($row["division"]) ? $row["division"] : '';
    }

} //end class