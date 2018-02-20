<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

//require_once('class-abstractdata.php');

/** 
 * Data and functions for Tennis Set(s)
 * @class  Match
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
*/
class Set extends AbstractData
{ 
    private static $tablename = 'tennis_set';

    const MAXSETS = 5;
    const MINSETS = 1;
    
    //Primary Keys
    private $event_ID;
    private $round_num;
    private $match_num;
    private $set_num;

    //Various scores
    private $home_wins      = 0;
    private $visitor_wins   = 0;
    private $home_tb_pts    = 0;
    private $visitor_tb_pts = 0;
    private $home_ties      = 0;
    private $visitor_ties   = 0;
    
    /**
     * Search for Matches that have a name 'like' the provided criteria
     */
    public static function search($criteria) {
		global $wpdb;
		return array();
    }
    
    /**
     * Find all Sets belonging to a specific Match;
     */
    public static function find(...$fk_criteria) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
        $col = array();
 
        $sql = "SELECT event_ID,round_num,match_num,set_num
                    ,home_wins, visitor_wins 
                    ,home_tb_pts, visitor_tb_pts 
                    ,home_ties, visitor_ties
                 FROM $table 
                 WHERE event_ID = %d 
                 AND   round_num = %d 
                 AND   match_num = %d;";

		$safe = $wpdb->prepare($sql,$fk_criteria);
		$rows = $wpdb->get_results($safe, ARRAY_A);
		
		error_log("Set::find $wpdb->num_rows rows returned");

		foreach($rows as $row) {
            $obj = new Set($fk_criteria[0],$fk_criteria[1],$fk_criteria[2]);
            self::mapData($obj,$row);
			$col[] = $obj;
		}
		return $col;
    }

	/**
	 * Get instance of a Match using it's primary key: event_ID,round_num,match_num,set_num,Set_num
	 */
    static public function get(int ...$pks) {
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
        $obj = NULL;
        
		$sql = "SELECT event_ID,round_num,match_num,set_num
                      ,home_wins, visitor_wins 
                      ,home_tb_pts, visitor_tb_pts 
                      ,home_ties, visitor_ties
                FROM $table 
                WHERE event_ID  = %d 
                AND   round_num = %d 
                AND   match_num = %d
                and   set_num   = %d;";
		$safe = $wpdb->prepare($sql,$pks);
		$rows = $wpdb->get_results($safe, ARRAY_A);

		error_log("Set::get(id) $wpdb->num_rows rows returned.");

		if(count($rows) === 1) {
            $obj = new Set($pks[0],$pks[1],$pks[2],$pks[3]);
            self::mapData($obj,$rows[0]);
		}
		return $obj;
	}

	/*************** Instance Methods ****************/
	public function __construct(int $eventID, int $round, int $match,int $set=NULL) {
        $this->isnew = TRUE;
        $this->event_ID   = $eventID;
        $this->round_num = $round;
        $this->match_num = $match;
        if( $set >= self::MINSETS && $set <= self::MAXSETS ) {
            $this->set_num = $set;
        }
        $this->init();
    }

    public function __destruct() {

    }
    
    public function setSetNumber(int $set) {
        $result = false;
        if( $set >= self::MINSETS && $set <= self::MAXSETS ) {
            $this->set_num = $set;
            $result = $this->isdirty = TRUE;
        }
        return $result;
    }

    public function getSetNumber():int {
        return $this->set_num;
    }

    public function setHomeScore( int $wins,int $tb_pts=0, int $ties=0 ) {
        $result = false;
        if($wins > -1 && $tb_pts > -1 && $ties > -1) {
            $this->home_wins = $wins;
            $this->home_ties = $ties;
            $this->home_tb_pts = $tb_pts;
            $result = $this->isdirty = TRUE;
        }
        return $result;
    }

    public function getHomeWins():int {
        return $this->home_wins;
    }

    public function getHomeTies():int {
        return $this->home_ties;
    }

    public function getHomeTieBreaker():int {
        return $this->home_tb_pts;
    }

    public function setVisitorScore( int $wins, int $tb_pts=0, int $ties=0 ) {
        $result = false;
        if($wins > -1 && $tb_pts > -1 && $ties > -1) {
            $this->visitor_wins = $wins;
            $this->visitor_ties = $ties;
            $this->visitor_tb_pts = $tb_pts;
            $result = $this->isdirty = TRUE;
        }
        return $result;
    }

    public function getVisitorWins():int {
        return $this->visitor_wins;
    }

    public function getVisitorTies():int {
        return $this->visitor_ties;
    }
    
    public function getVisitorTieBreaker():int {
        return $this->visitor_tb_pts;
    }
    
    public function isValid() {
        $mess = '';
        if( !isset( $this->event_ID ) )  $mess = __( "Missing event ID." );
        if( !isset( $this->round_num ) ) $mess = __( "Missing round number." );
        if( !isset( $this->match_num ) ) $mess = __( "Misisng match number." );
        if( !$this->isNew() && !isset( $this->set_num ) ) $mess =  __( "Missing set number." );
        if( !isset( $this->home_wins ) && !isset( $this->visitor_wins ))  $mess =  __( "No scores are set." );
        if( 0 === $this->home_wins && 0 === $this->visitor_wins) $mess =  __( "Both home and visitor scores cannot be zero" );

        if(strlen( $mess ) > 0) throw new InvalidSetException( $mess );
        return true;
    }
    
    /**
     * Delete this Set
     */
    public function delete() {
        $table = $wpdb->prefix . self::$tablename;
        $where = array( 'event_ID' => $this->event_ID
                        ,'round_num' => $this->round_num
                        ,'match_num' => $this->match_num
                        ,'set_num'   => $this->set_num );
        $formats_where = array( '%d', '%d', '%d', '%d' );

        $wpdb->delete( $table, $where, $formats_where );
        $result += $wpdb->rows_affected;

        error_log( "Set.delete: deleted $result rows" );

        return $result;
    }

	protected function create() {
        global $wpdb;
        
        parent::create();
        
		$table = $wpdb->prefix . self::$tablename;
        $wpdb->query( "LOCK TABLES $table LOW_PRIORITY WRITE;" );
        
		$sql = "SELECT IFNULL(MAX(set_num),0) FROM $table WHERE event_ID=%d AND round_num=%d AND match_num=%d;";
        $safe = $wpdb->prepare( $sql, $this->event_ID, $this->round_num, $this->match_num );
        $this->set_num = $wpdb->get_var( $safe,0,0 ) + 1;
        error_log("Set::create: set number assigned is '$this->set_num'");
        
        if($this->set_num > self::MAXSETS) {
            $max = self::MAXSETS;
            $wpdb->query("UNLOCK TABLES;");
            $this->isnew = FALSE;
            $mess = __( "Set number exceed limit of '$max'" );
            throw new InvalidSetException($mess);
        }
        
        $this->Set_num =  $wpdb->get_var( $safe,0,1 ) + 1;

        $values = array( 'event_ID'       => $this->event_ID
                        ,'round_num'      => $this->round_num
                        ,'match_num'      => $this->match_num
                        ,'set_num'        => $this->set_num
                        ,'home_wins'      => $this->home_wins
                        ,'visitor_wins'   => $this->visitor_wins
                        ,'home_tb_pts'    => $this->home_tb_pts
                        ,'visitor_tb_pts' => $this->visitor_tb_pts
                        ,'home_ties'      => $this->home_ties
                        ,'visitor_ties'   => $this->visitor_ties);
		$formats_values = array( '%d','%d','%d','%d','%d','%d','%d','%d','%d','%d' );
		$wpdb->insert( $wpdb->prefix . self::$tablename, $values, $formats_values );
        $result =  $wpdb->rows_affected;
        $wpdb->query( "UNLOCK TABLES;" );

		$this->isnew = FALSE;
		$this->isdirty = FALSE;

		error_log("Set::create $result rows affected.");

		return $result;
	}

	protected function update() {
		global $wpdb;

        parent::update();

        $values = array( 'home_wins'      => $this->homes_wins
                        ,'visitor_wins'   => $this->visitor_wins
                        ,'home_tb_pts'    => $this->home_tb_pts
                        ,'visitor_tb_pts' => $this->visitor_tb_pts
                        ,'home_ties'      => $this->home_ties
                        ,'visitor_ties'   => $this->visitor_ties );
		$formats_values = array( '%d', '%d', '%d', '%d', '%d', '%d' );
        $where = array('event_ID'       => $this->event_ID
                      ,'round_num'      => $this->round_num
                      ,'match_num'      => $this->match_num
                      ,'set_num'        => $this->set_num);
		$formats_where  = array( '%d', '%d', '%d', '%d' );
		$wpdb->update( $wpdb->prefix . self::$tablename, $values, $where, $formats_values, $formats_where );
		$this->isdirty = FALSE;
        $result =  $wpdb->rows_affected;

		error_log("Set::update $result rows affected.");

		return $result;
    }
    
    /**
     * Map incoming data to an instance of Set
     */
    protected static function mapData( $obj, $row ) {
        parent::mapData($obj,$row);
        $obj->event_ID        = $row["event_ID"];
        $obj->round_num       = $row["round_num"];
        $obj->match_num       = $row["match_num"];
        $obj->set_num         = $row["set_num"];
        $obj->homes_wins      = $row["home_wins"];
        $obj->visitor_wins    = $row["visitor_wins"];
        $obj->home_tb_pts     = $row["home_tb_pts"];
        $obj->visitor_tb_pts  = $row["visitor_tb_pts"];
        $obj->home_ties       = $row["home_ties"];
        $obj->visitor_ties    = $row["visitor_ties"];
    }
 
    private function getIndex( $obj ) {
        return $obj->getPosition();
    }

    private function init() {
        // $this->event_ID     = NULL;
        // $this->round_num    = NULL;
        // $this->match_num    = NULL;
        // $this->set_num      = NULL;
        // $this->home_wins      = 0;
        // $this->visitor_wins   = 0;
        // $this->home_tb_pts    = 0;
        // $this->visitor_tb_pts = 0;
        // $this->home_ties      = 0;
        // $this->visitor_ties   = 0;
    }
} //end class