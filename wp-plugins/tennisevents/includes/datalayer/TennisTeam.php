<?php
namespace datalayer;

use commonlib\BaseLogger;
use \TennisEvents;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 
 * Data and functions for Tennis Team(s)
 * @class  TennisTeam
 * @package Tennis Events
 * @version 1.1.0
 * @since   1.1.0
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
        $logger = new BaseLogger();
        $ids = print_r($fk_criteria,true);
        $logger->error_log("{$loc}:");
        $logger->error_log($ids);

		global $wpdb;

		$table = TennisEvents::getInstaller()->getDBTablenames()[self::$tablename];
		// $joinTable = TennisEvents::getInstaller()->getDBTablenames()['squad'];
        $columns = self::COLUMNS;
        
		if(isset( $fk_criteria[0] ) && is_array( $fk_criteria[0]) ) $fk_criteria = $fk_criteria[0];

        $col = array();
		//All teams belonging to specified event and bracket
        if( array_key_exists( 'event_ID', $fk_criteria) && array_key_exists( 'bracket_num', $fk_criteria)) {
			$sql = "SELECT {$columns} from $table WHERE event_ID = %d AND bracket_num = %d;";
            $logger->error_log("Find all teams where event_ID={$fk_criteria["event_ID"]} bracket_num={$fk_criteria["bracket_num"]}");
		    $safe = $wpdb->prepare($sql,$fk_criteria["event_ID"], $fk_criteria["bracket_num"]);
        }
        //All teams belonging to specified event    
        else if( array_key_exists( 'event_ID', $fk_criteria) ) {
            $sql = "SELECT {$columns}
                    from $table WHERE event_ID = %d;";
            $logger->error_log("Find all teams where event_ID={$fk_criteria["event_ID"]}");
            $safe = $wpdb->prepare($sql,$fk_criteria["event_ID"]);
        } else {
			return $col;
		}

        $logger->error_log("{$loc}:");
        $logger->error_log(print_r($safe,true));
		$rows = $wpdb->get_results($safe, ARRAY_A);
		
		$logger->error_log("{$loc} {$wpdb->num_rows} rows returned");

		foreach($rows as $row) {
            $obj = new TennisTeam(null);
            self::mapData($obj,$row);
			$col[] = $obj;
		}
		return $col;
    }

	/**
	 * Get instance of a Team or an array of 2 team instances: one for each division/squad
     * @param int ...$pks event_ID, bracket_num, team_num and possibly squad
     * @return TennisTeam|array Instance of TennisTeam or array
	 */
    static public function get(...$pks) : TennisTeam | null {
		$loc = __CLASS__ . "::" . __FUNCTION__;
        $ids = print_r($pks,true);
        error_log("{$loc}:");
        error_log($ids);


		global $wpdb;
		$table = TennisEvents::getInstaller()->getDBTablenames()[self::$tablename];

        $event_ID = 0;
        $bracket_num = 0;
        $team_num = 0;
   
        $columns = self::COLUMNS;
        $event_ID = $pks[0];
        $bracket_num = $pks[1];
        $team_num = $pks[2];
        $sql = "select {$columns} from $table where event_ID=%d and bracket_num=%d and team_num=%d; ";
        $safe = $wpdb->prepare($sql,$event_ID,$bracket_num,$team_num);


        error_log("{$loc}:");
        error_log(print_r($safe,true));
		$rows = $wpdb->get_results($safe, ARRAY_A);

        if(is_null($rows) || count($rows) < 1 ) {
            error_log("{$loc}: No team rows returned.");
            return null;
        }
        else if( count($rows) > 1 ) {
            error_log("{$loc}: More than one team row returned.");
            return null;
        }   

		error_log("{$loc} {$wpdb->rows_affected} returned.");

        $obj = new TennisTeam(null);
        self::mapData($obj,$rows[0]);
        return $obj;
	}

    /**
     * Delete all Teams and players, squads, squad_player mappings for the specified event and bracket
     * @param int $eventId The ID of the event
     * @param int $bracketNum The bracket number 
     * @return int Number of rows affected
     */
    public static function deleteAllTeams( int $eventId = 0, int $bracketNum = 0 ) : int    {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $logger = new BaseLogger();
        $logger->error_log("{$loc}: Deleting all teams for event_ID={$eventId} bracket_num={$bracketNum}");
        
        $result = 0;
        if( 0 === $eventId || 0 === $bracketNum ) return $result;

        global $wpdb;
        $values = ['event_ID' => $eventId, 'bracket_num' => $bracketNum];
        $formats_values = ['%d','%d'];

        $table = TennisEvents::getInstaller()->getDBTablenames()['squad_player'];
        $ptable = TennisEvents::getInstaller()->getDBTablenames()['player'];
        $sql = <<<EOD
delete s 
FROM $ptable as p inner join $table as s on p.ID = s.player_ID 
where p.event_ID = %d and p.bracket_num = %d;
EOD;
        $safe = $wpdb->prepare($sql,$eventId,$bracketNum);
        $wpdb->query($safe);
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
        
        $logger->error_log("{$loc} {$result} rows affected.");

        return $result;
    }

	/*************** Instance Methods ****************/
	public function __construct( ?string $name, int $event_ID = 0, int $bracket_num = 0, $team_num = 0, $division='') {
        parent::__construct( true );
		// $loc = __CLASS__ . "::" . __FUNCTION__;
        // $this->log->error_log("{$loc}({$name},{$event_ID},{$bracket_num},{$team_num})");
    
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
     * Get members of this Team that are assigned to the specified match
     * @param int $roundNum The round number
     * @param int $matchNum The match number
     * @return array Array of Player instances assigned to the match
     */
    public function getMembersAssignedToMatch(int $roundNum, int $matchNum) : array {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("{$loc}");

        $assignedPlayers = [];
        foreach($this->getSquads() as $squad ) {
            $this->log->error_log("{$loc}: Team has squad '{$squad->getName()}'");
            foreach( $squad->getMembersAssignedToMatch( $roundNum, $matchNum ) as $player ) {
                $assignedPlayers[] = $player;
            }
        }
        return $assignedPlayers;
    }


    /**
     * Get the Squad for this team. Maybe be empty.
     */
    public function getSquad(string $squadName) : TennisSquad | null {
		$loc = __CLASS__ . "::" . __FUNCTION__;
        $this->log->error_log("{$loc}: Getting squad '{$squadName}' for team '{$this->getName()}'");
        
        $squad = TennisSquad::get( $this->getEventID(), $this->getBracketNum(), $this->getTeamNum(), $squadName );
        return $squad;
    }

    /**
     * Add a Squad (i.e. division) to this team
     * @param string $squadName The name of the squad/division (e.g. 'A','B')
     * @return int The number of db records affected
     */
    public function addSquad(string $squadName) : int {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        $this->log->error_log("{$loc}: Adding squad '{$squadName}' to team '{$this->getName()}'");

        $result = 0;
        if(empty($squadName)) return $result;

        $squad = TennisSquad::get( $this->getEventID(), $this->getBracketNum(), $this->getTeamNum(), $squadName );
        if( is_null($squad) ) {
            // Create a new squad
            $squad = new TennisSquad($squadName, $this->getEventID(), $this->getBracketNum(), $this->getTeamNum());
            $result = $squad->save();
        }

        return $result;
    }

    /**
     * Get all the squads for this team
     * @return array Array of squads belonging to this team
     */
    public function getSquads( ) : array {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        $result = [];
        $result = TennisSquad::find(['event_ID'=>$this->getEventID()
                                    ,'bracket_num'=>$this->getBracketNum()
                                    ,'team_num'=>$this->getTeamNum()
                                    ]);
        return $result;
    }

    public function getMembers(string $squad = ''): array {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("{$loc}");
        $result = [];

        foreach($this->getSquads() as $sq) {
            $this->log->error_log("{$loc}: Team has squad '{$sq->getName()}'");
            if($sq->getName() !== $squad && !empty($squad)) {
                $this->log->error_log("{$loc}:   Skipping squad '{$sq->getName()}'");
                continue;
            }
            foreach( $sq->getMembers() as $p ) {
                $this->log->error_log("{$loc}: Member '{$p->getFirstName()} {$p->getLastName()}'");
                $result[] = $p;
            }   
        }
        return $result;
    }

    public function addMember(Player $player, string $squad) : bool {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("{$loc}");
        
		$result = false;
        foreach($this->getSquads() as $sq) {
            if($sq->getName() == $squad && !empty($squad)) {
                $this->log->error_log("{$loc}: found squad '{$sq->getName()}'");
                $sq->addMember( $player );
                $result = $this->setDirty();
                $sq->save();
                break;
            }
        }
		
        return $result;
    }

    /**
     * Remove player from this team
     * @param Player $player An instance of Player
     * @return int The number of db records affected
     */
    public function removeMember(Player $player, string $squad) : int {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("{$loc}");
                
        foreach($this->getSquads() as $sq) {
            if($sq->getName() == $squad && !empty($squad)) {
                $this->log->error_log("{$loc}: found squad '{$sq->getName()}'");
                $sq->removeMember( $player );
                $result = $this->setDirty();
                $sq->save();
                break;
            }
        }
        return $result;
    }

    /**
     * Remove all members of this Team
     * @return int The number of db records deleted
     */
    public function removeAllTeamMembers() : int {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("{$loc}");
        
        $result = 0;
        foreach($this->getSquads() as $squad) {
            $result += $squad->removeAllMembers();
        }

        return $result;
    }

    /**
     * Delete this Team from the database
     * @return int Number of rows affected
     */
    public function delete() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("{$loc}");

        global $wpdb;
        $result = 0;
        foreach($this->getSquads() as $squad) {
            $result += TennisSquad::deleteSquad( $squad->getID() );
        }
        
        $table = TennisEvents::getInstaller()->getDBTablenames()[self::$tablename];
        $values = ['event_ID'=>$this->getEventID(),'bracket_num'=>$this->getBracketNum(),'team_num'=>$this->getTeamNum()];    
        $formats_values = ['%d','%d','%d'];

        $wpdb->delete( $table, $values, $formats_values );
        return $result;
    }


    /** 
     * Validate this Team instance
     * @return bool TRUE if valid, FALSE otherwise
     */
    public function isValid()  {
        $isValid = TRUE;
        if( 0 == $this->event_ID ?? 0 ) $isValid = FALSE;
        if( 0 == $this->bracket_num ?? 0 ) $isValid = FALSE;
        if( 0 == $this->team_num ?? 0) $isValid = FALSE;
        if( empty( $this->name ) ) $isValid = FALSE;

        return $isValid;
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
        $wpdb->query( "UNLOCK TABLES;" );
        
		if( $result === false || $result === 0 ) {
			$mess = "{$loc}: wpdb->insert returned false or inserted 0 rows.";
			$err = empty($wpdb->last_error) ? '' : $wpdb->last_error;
			$mess .= " : Err='$err'";
			throw new InvalidEntrantException($mess);
		}
            
		$this->isnew = false;
		$this->isdirty = false;

        $result += $this->createRelatedData();

		$this->log->error_log("{$loc} {$result} rows affected.");

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

		$this->log->error_log("{$loc} {$result} rows affected.");

		return $result;
	}

    /**
     * Create related data for this Team
     * This assumes an A and B squad for each team
     * @return int Number of rows affected
     */
    private function createRelatedData() : int {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("{$loc}");
        $result = 0;

        $result += $this->addSquad( 'A' );
        $result += $this->addSquad( 'B' );

        return $result;
    }   
    
    /**
     * Map incoming data to an instance of Team
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