<?php
namespace datalayer;

use commonlib\BaseLogger;
use \TennisEvents;
use \datalayer\InvalidTennisOperationException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 
 * Data and functions for Tennis Squad(s)
 * @class  TennisSquad
 * @package Tennis Events
 * @version 1.2.0
 * @since   1.2.0
*/
class TennisSquad extends AbstractData
{ 
    public static $tablename = 'squad';
    const MAXSKILL = 7.0;
    const MINSKILL = 2.5;
    
	public const COLUMNS = <<<EOD
    ID
    ,event_ID 
    ,bracket_num
    ,team_num
    ,name
    EOD;

    private $event_ID;
    private $bracket_num;
    private $team_num;
    private $name;

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
            $obj = new TennisSquad();
            self::mapData($obj,$row);
			$obj->isnew = FALSE;
			$col[] = $obj;
		}
		return $col;
    }
    
    /**
     * Find all Squads belonging to a specific event and bracket and team.
     */
    public static function find(...$fk_criteria) {
		$loc = __CLASS__ . "::" . __FUNCTION__;
        $logger = new BaseLogger();
        $ids = print_r($fk_criteria,true);
        $logger->error_log("{$loc}:");
        $logger->error_log($ids);

		global $wpdb;

		$table = TennisEvents::getInstaller()->getDBTablenames()[self::$tablename];
        $columns = self::COLUMNS;
        
		if(isset( $fk_criteria[0] ) && is_array( $fk_criteria[0]) ) $fk_criteria = $fk_criteria[0];

        $col = array();
        if( array_key_exists( 'event_ID', $fk_criteria) && array_key_exists( 'bracket_num', $fk_criteria) && array_key_exists( 'team_num', $fk_criteria)) {
			//All squads belonging to specified team
			$sql = "SELECT {$columns} from $table WHERE event_ID = %d AND bracket_num = %d AND team_num = %d order by name;";
            $logger->error_log("Find all squads where event_ID={$fk_criteria["event_ID"]} bracket_num={$fk_criteria["bracket_num"]} team_num={$fk_criteria["team_num"]}");
		    $safe = $wpdb->prepare($sql,$fk_criteria["event_ID"], $fk_criteria["bracket_num"], $fk_criteria["team_num"]);
        } else {
			return $col;
		}

        $logger->error_log("{$loc}:" . print_r($safe,true));
		$rows = $wpdb->get_results($safe, ARRAY_A);
		
		$logger->error_log("{$loc} {$wpdb->num_rows} rows returned");
		foreach($rows as $row) {
            $obj = new TennisSquad();
            self::mapData($obj,$row);
			$col[] = $obj;
		}
		return $col;
    }

	/**
	 * Get instance of a Squad
     * @param int ...$pks event_ID, bracket_num, team_num
     * @return TennisSquad|array Instance of TennisSquad
	 */
    static public function get(...$pks) : TennisSquad | null {
		$loc = __CLASS__ . "::" . __FUNCTION__;
        $logger = new BaseLogger();
        $ids = print_r($pks,true);
        $logger->error_log("{$loc}({$ids})");

		global $wpdb;
		$table = TennisEvents::getInstaller()->getDBTablenames()[self::$tablename];

		$columns = self::COLUMNS;
        if(count($pks) == 4 ) {
            $event_ID = $pks[0] ?? 0;
            $bracket_num = $pks[1] ?? 0;
            $team_num = $pks[2] ?? 0;
            $name = $pks[3] ?? '';
            $sql = "select {$columns} from $table where event_ID=%d and bracket_num=%d and team_num=%d and name='%s';";
            $safe = $wpdb->prepare($sql,$event_ID,$bracket_num,$team_num,$name);
        }
        elseif(count($pks) == 1 ) {
            $id = $pks[0] ?? 0;
            $sql = "select {$columns} from $table where ID=%d;";
            $safe = $wpdb->prepare($sql,$id);
        }
        else {
            return null;
        }

        $logger->error_log("{$loc}:" . print_r($safe,true));
        $rows = $wpdb->get_results($safe, ARRAY_A);
        if(is_null($rows) || count($rows) < 1 ) {
            $logger->error_log("{$loc}: No squad rows returned.");
            return null;
        }
        else if( count($rows) > 1 ) {
            $logger->error_log("{$loc}: More than one squad row returned.");
            throw new InvalidTennisOperationException("More than one squad row returned.");
        }   
        $logger->error_log("{$loc}: {$wpdb->rows_affected} returned.");


        $obj = new TennisSquad();
        self::mapData($obj,$rows[0]);
        return $obj;
	}
    
	/**
	 * Delete the squad and related squad_player
     * @param int $squadId The ID of the squad to delete
     * @return int Number of rows affected
	 */
	public static function deleteSquad( int $squadId ) : int {
        $loc = __CLASS__ . '::' . __FUNCTION__;

        global $wpdb;
		$result = 0;
        if( 0 === $squadId ) return $result;
        
        $values = ['squad_ID' => $squadId];    
        $formats_values = ['%d'];

        $table = TennisEvents::getInstaller()->getDBTablenames()['squad_player'];
        $wpdb->delete( $table, $values, $formats_values );
        $result = $wpdb->rows_affected;
        
        $table = TennisEvents::getInstaller()->getDBTablenames()[self::$tablename];
        $wpdb->delete( $table, $values, $formats_values );
        $result += $wpdb->rows_affected;
        
        error_log("{$loc} {$result} rows affected.");

		return $result;
    }


	/*************** Instance Methods ****************/
	public function __construct( string $name = '', int $event_ID = 0, int $bracket_num = 0, $team_num = 0) {
        parent::__construct( true );
		$loc = __CLASS__ . "::" . __FUNCTION__;
        $this->log->error_log("{$loc}({$name},{$event_ID},{$bracket_num},{$team_num})");
    
        if( isset( $name )  && (strlen( $name ) > 0) ) {
           $this->name = $name;
        }   
        $this->event_ID = $event_ID;
        $this->bracket_num = $bracket_num;
        $this->team_num = $team_num;
        $this->name = $name;
        
        $this->isnew = true;
    }

    public function getEventID(): int {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        return $this->event_ID ?? 0;
    }
    
    public function setEventID(int $eventId) : bool {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        $result = false;
        if($eventId < 1) return $result;

        $this->event_ID = $eventId;
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

    public function getMembers($force = false): array {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("{$loc}");
        if( $force || empty( $this->members ) ) {
            $this->fetchMembers();
        }
        return $this->members;
    }

    /**
     * Add player to this Squad
     * @param Player $player An instance of Player
     * @return bool TRUE if added, FALSE otherwise
     */
    public function addMember(Player $player) : bool {
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

        $table = TennisEvents::getInstaller()->getDBTablenames()['squad_player'];
		$num = 0;
		$result = 0;
        foreach( $this->getMembers() as &$m ) {
            if( $m->getFirstName() === $player->getFirstName() && $m->getLastName() === $player->getLastName() ) {
                $result = true;
                unset( $this->members[$num] );
                
                //Delete player intersection data
                //...player-team-squad
                $values = ['squad_ID'=>$this->getID(),'player_ID'=>$player->getID()];    
                $formats_values = ['%d','%d'];
                $wpdb->delete( $table, $values, $formats_values );
                $result = $wpdb->rows_affected;
            }
            ++$num;
        }
        $this->log->error_log("{$loc}: rows_affected={$result}");
        return $result;
    }

    /**
     * Remove all members of this Squad for this Team
     * @return int The number of db records deleted
     */
    public function removeAllMembers() : int {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("{$loc}");
        global $wpdb;

        $table = TennisEvents::getInstaller()->getDBTablenames()['squad_player'];
        //Delete player intersection data
        //...player-team-squad
        $values = ['squad_ID'=>$this->getID()];    
        $formats_values = ['%d'];
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

        return self::deleteSquad( $this->getID() );
    }

    /**
     * Get members of this squad that are assigned to the specified match
     * @param int $roundNum The round number
     * @param int $matchNum The match number
     * @return array Array of Player instances assigned to the match
     */
    public function getMembersAssignedToMatch(int $roundNum, int $matchNum) : array {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("{$loc}");
        global $wpdb;

        $assignedPlayers = [];
        $table = TennisEvents::getInstaller()->getDBTablenames()['match_team_player'];
        $sql = <<<EOD
select player_ID from $table 
where squad_ID=%d and round_num=%d and match_num=%d;
EOD;
        $safe = $wpdb->prepare( $sql, $this->getID(), $roundNum, $matchNum );
        $rows = $wpdb->get_results( $safe, ARRAY_A );

        foreach( $this->getMembers() as $player ) {
            if( in_array( $player->getID(), $rows ) ) {
                $assignedPlayers[] = $player;                    
                $mess = sprintf( __("Team '%d' Squad '%s' Player '%s' is assigned to match.", TennisEvents::TEXT_DOMAIN ), 
                                    $this->getTeamNum(), $this->getName(), $player->getName() );
                    $this->log->error_log( $mess );
            }
        }
        return $assignedPlayers;
    }

    /** 
     * Validate this Team instance
     * @return bool TRUE if valid, FALSE otherwise
     */
    public function isValid()  {
        $isValid = TRUE;
        if(0 == $this->event_ID ?? 0 ) $isValid = FALSE;
        if(0 == $this->bracket_num ?? 0 ) $isValid = FALSE;
        if(0 == $this->team_num ?? 0 ) $isValid = FALSE;
        if( empty( $this->name ) ) $isValid = FALSE;

        return $isValid;
    }
    
	/**
	 * Fetch the members for this Squad from the database
	 */
	private function fetchMembers() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("{$loc}()");

        $args=array('squad'=>$this->getID());
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
     * Create this Squad in the database
     * @return int Number of rows affected
     */
	protected function create() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("{$loc}");

        global $wpdb;
        
        parent::create();

        $result = 0;
		$table = TennisEvents::getInstaller()->getDBTablenames()[self::$tablename];

        $values = array( 'event_ID' => $this->event_ID
                        ,'bracket_num' => $this->bracket_num
                        ,'team_num' => $this->team_num
                        ,'name' => $this->name
                        );
		$formats_values = array('%d','%d','%d','%s');
		$result = $wpdb->insert($table, $values, $formats_values);
        $this->ID = $wpdb->insert_id;
        
		if( $result === false || $result === 0 ) {
			$mess = "{$loc}: wpdb->insert returned false or inserted 0 rows.";
			$err = empty($wpdb->last_error) ? '' : $wpdb->last_error;
			$mess .= " : Err='$err'";
			throw new InvalidEntrantException($mess);
		}
            
		$this->isnew = false;
		$this->isdirty = false;

        //Now save related data
        $result += $this->manageRelatedData();

        $this->log->error_log("{$loc}: {$result} rows affected.");

		return $result;
	}
      
    /**
     * Update this Squad in the database
     * @return int Number of rows affected
     */
	protected function update() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("{$loc}");

		global $wpdb;
        parent::update();

        $result = 0;
		$table = TennisEvents::getInstaller()->getDBTablenames()[self::$tablename];

        $values         = array('name' => $this->getName());
        $formats_values = array('%s');
		$where          = array('ID' => $this->getID());
		$formats_where  = array('%d');
		$result = $wpdb->update($table, $values, $where, $formats_values, $formats_where);
		$this->isdirty = FALSE;

        //Now save related data
        $result += $this->manageRelatedData();

		$this->log->error_log("{$loc} {$result} rows affected.");

		return $result;
	}

    private function manageRelatedData() : int {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("{$loc}");
        
        $result = 0;
		global $wpdb;
        $table = TennisEvents::getInstaller()->getDBTablenames()[self::$tablename];
        $playerSquadTable = TennisEvents::getInstaller()->getDBTablenames()['squad_player'];
        $playerTable = TennisEvents::getInstaller()->getDBTablenames()['player'];

        $sql = <<< EOS
LOCK TABLES {$table} LOW_PRIORITY WRITE
select count(*) from {$table} as s
inner join {$playerSquadTable} as j on s.ID = j.squad_ID
inner join {$playerTable} as p on j.player_ID = p.ID
where s.ID=%d and p.ID=%d;
EOS;

		//Save squad members
        foreach( $this->members as $player ) {
            if( $player->isValid() ) {
                $result += $player->save();
                $this->log->error_log("{$loc}: processing player: '{$player->getID()}'");
                $safe = $wpdb->prepare($sql,$this->getID(), $player->getID() );
                $exists = (int) $wpdb->get_var($safe,0,0);
                $this->log->error_log("{$loc}: exists={$exists}");
                $this->log->error_log("{$safe}");
                if($exists < 1) {
                    //Now insert an intersection record to link this player to this squad
                    $values = array( 'player_ID' => $player->getID()
                                    ,'squad_ID' => $this->getID()
                                    );
                    $formats_values = array('%d','%d');
                    $result += $wpdb->insert($playerSquadTable, $values, $formats_values);
                }
            }
        } 
        $wpdb->query("UNLOCK TABLES;");
        return $result;
    }
    
    /**
     * Map incoming data to an instance of Squad
     * @param TennisSquad $obj An instance of TennisSquad
     */
    protected static function mapData($obj,$row) {
        $loc = __CLASS__ . '::' . __FUNCTION__;

        parent::mapData($obj,$row);
        $obj->event_ID    = $row["event_ID"];
        $obj->bracket_num = $row["bracket_num"];
        $obj->team_num    = $row["team_num"];
        $obj->name        = $row["name"];
    }

} //end class