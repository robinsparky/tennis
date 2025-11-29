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
    ID
    ,event_ID
    ,bracket_num
    ,team_num
    ,name
    EOD;

    private $club_ID;
    private $event_ID;
    private $bracket_num;
    private $team_num;
    private $name;

    /**
     * Search for Players that have a name 'like' the provided criteria
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
     * Find all Players belonging to a specific Entry;
     * TODO: fix this to use several possible foreign keys
     */
    public static function find(...$fk_criteria) {
		$loc = __CLASS__ . "::" . __FUNCTION__;

		global $wpdb;
		$table = TennisEvents::getInstaller()->getDBTablenames()[self::$tablename];
		$columns = self::COLUMNS;
        
		if(isset( $fk_criteria[0] ) && is_array( $fk_criteria[0]) ) $fk_criteria = $fk_criteria[0];
        $col = array();
	    if( array_key_exists( 'event', $fk_criteria)) {
			//All teams belonging to specified event
			$col_value = $fk_criteria["event"];
			error_log( "{$loc} using event_ID=$col_value" );
			$sql = "SELECT {$columns} 
					from $table 
					WHERE event_ID = %d;";
		} elseif( !isset( $fk_criteria ) ) {
			//All teams
			error_log( "{$loc} all Teams" );
			$col_value = 0;
			$sql = "SELECT {$columns} 
					FROM $table;";
		}
		else {
			return $col;
		}

		$safe = $wpdb->prepare($sql,$fk_criteria);
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
	 * Get instance of a Team by primary key
     * @param int ...$pks club_ID, event_ID, bracket_num, team_num
     * @return TennisTeam|null Instance of TennisTeam or null if not found
	 */
    static public function get(int ...$pks) : TennisTeam|null {
		$loc = __CLASS__ . "::" . __FUNCTION__;

		global $wpdb;
        
		$table = TennisEvents::getInstaller()->getDBTablenames()[self::$tablename];
		$columns = self::COLUMNS;

		$sql = "select {$columns} from $table where event_ID=%d and bracket_num=%d and team_num=%d;";
		$safe = $wpdb->prepare($sql,$pks);
		$rows = $wpdb->get_results($safe, ARRAY_A);

		error_log("{$loc} { $rows} returned.");

        $obj = NULL;
		if(count($rows) === 1) {
			$obj = new TennisTeam(null);
            self::mapData($obj,$rows[0]);
		}
		return $obj;
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
        $values = [$eventId,$bracketNum,$teamNum];
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
	public function __construct( ?string $name, int $event_ID = 0, int $bracket_num = 0, $team_num = 0) {
        parent::__construct( true );
		$loc = __CLASS__ . "::" . __FUNCTION__;
        $this->log->error_log("{$loc}({$name},{$event_ID},{$bracket_num},{$team_num})");
    
        if( isset( $name )  && (strlen( $name ) > 0) ) {
           $this->name = $name;
        }   
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

    public function isValid()  {
        $isValid = TRUE;
        if( !isset( $this->event_ID ) || !is_int( $this->event_ID ) ) $isValid = FALSE;
        if( !isset( $this->bracket_num ) || !is_int( $this->bracket_num ) ) $isValid = FALSE;
        if( empty( $this->name ) ) $isValid = FALSE;

        return $isValid;
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
     * Delete this Team from the database
     * @return int Number of rows affected
     */
    public function delete() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("{$loc}");

        return self::deleteTeam( $this->getEventID(), $this->getBracketNum(), $this->getTeamNum() );
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
    }

} //end class