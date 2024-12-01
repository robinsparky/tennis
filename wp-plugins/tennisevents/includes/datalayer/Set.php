<?php
namespace datalayer;
use \TennisEvents;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 
 * Data and functions for Tennis Set(s)
 * TennisMatch class is responsible for deleting Sets.
 * @class  Set
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
*/
class Set extends AbstractData
{ 
    public static $tablename = 'tennis_set';

    const MAXGAMES = 99; //arbitrary
    const MAXSETS  = 99; //arbitrary
    
    //Primary Keys
    private $event_ID    = 0;
    private $bracket_num = 0;
    private $round_num   = 0;
    private $match_num   = 0;
    private $set_num     = 0;

    //Score Results
    private $home_wins      = 0;
    private $visitor_wins   = 0;
    private $home_tb_pts    = 0;
    private $visitor_tb_pts = 0;
    private $home_ties      = 0;
    private $visitor_ties   = 0;

    //Misc
    private $early_end = 0;
    private $comments;
    private $match; 
    
    /**
     * Find all Sets belonging to a specific TennisMatch;
     */
    public static function find( ...$fk_criteria ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$calledBy = debug_backtrace()[1]['function'];
        error_log("{$loc} ... called by {$calledBy}");
        
		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
        $col = array();
 
        $sql = "SELECT event_ID,bracket_num,round_num,match_num,set_num 
                      ,home_wins, visitor_wins 
                      ,home_tb_pts, visitor_tb_pts 
                      ,home_ties, visitor_ties 
                      ,early_end, comments
                 FROM $table 
                 WHERE event_ID = %d 
                 AND   bracket_num = %d 
                 AND   round_num = %d 
                 AND   match_num = %d
                 ORDER BY set_num asc;";

		$safe = $wpdb->prepare( $sql, $fk_criteria );
		$rows = $wpdb->get_results( $safe, ARRAY_A );
		
        error_log( sprintf("Set::find(%d,%d,%d,%d) -> %d rows returned"
                          , $fk_criteria[0]
                          , $fk_criteria[1]
                          , $fk_criteria[2]
                          , $fk_criteria[3]
                          , $wpdb->num_rows ) );

		foreach( $rows as $row ) {
            $obj = new Set( $fk_criteria );
            self::mapData( $obj, $row );
			$col[] = $obj;
        }
		return $col;
    }

	/**
	 * Get instance of a Set from the database.
     * @param $pks Primary key identifying a Set: event_ID,round_num,match_num,set_num
	 */
    static public function get( int ...$pks ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$calledBy = debug_backtrace()[1]['function'];
        error_log("{$loc} ... called by {$calledBy}");

		global $wpdb;
		$table = $wpdb->prefix . self::$tablename;
        $obj = NULL;
        
		$sql = "SELECT event_ID,bracket_num,round_num,match_num,set_num 
                      ,home_wins, visitor_wins 
                      ,home_tb_pts, visitor_tb_pts 
                      ,home_ties, visitor_ties
                      ,early_end, comments 
                FROM $table 
                WHERE event_ID    = %d 
                AND   bracket_num = %d
                AND   round_num   = %d 
                AND   match_num   = %d
                and   set_num     = %d;";
		$safe = $wpdb->prepare( $sql, $pks );
		$rows = $wpdb->get_results( $safe, ARRAY_A );

		error_log( "Set::get(id) $wpdb->num_rows rows returned." );

		if( count( $rows) === 1 ) {
            $obj = new Set;
            self::mapData( $obj, $rows[0] );
		}
		return $obj;
	}

    /*************** Instance Methods ****************/
	public function __construct( ) {
        parent::__construct( true );
    }

    public function __destruct() {
        $loc=__CLASS__ . '->' . __FUNCTION__;
        // $this->log->error_log($loc);
        $this->match = null;
    }
    
    public function setSetNumber( int $set ) {
        $result = false;
        if( $set >= 1 && $set <= self::MAXSETS ) {
            $this->set_num = $set;
            $result = $this->setDirty();
        }
        return $result;
    }

    public function getSetNumber():int {
        return $this->set_num;
    }

    public function setMatch( TennisMatch &$match ) {
        $this->match       = $match;
        $this->event_ID    = $match->getBracket()->getEvent()->getID();
        $this->bracket_num = $match->getBracket()->getBracketNumber();
        $this->match_num   = $match->getMatchNumber();
        $this->round_num   = $match->getRoundNumber();
    }

    public function getMatch( $force = false ) {
        if( !isset( $this->match ) || $force ) {
            $this->match = $this->fetchMatch();
        }
        return $this->match;
    }

    /**
     * Set how the match ended possibly early via retired, no show, etc.
     * This function just records who defauted: home or visitor
     * @param int $early 0 - not early; 1 - home defaulted; 2 - visitor defaulted
     */
    public function setEarlyEnd( int $early ) {
        switch( $early ) {
            case 0:
            case 1:
            case 2:
                $this->early_end = $early;
                $result = $this->setDirty();
                break;
            default:
                $result = false;
        }
        return $result;
    }

    /**
     * Did match end early due to withdrawal no no show
     * @return int 0 means not early end; 1 means home defaulted; 2 means visitor defaulted
     */
    public function earlyEnd():int {
        return isset( $this->early_end ) ? $this->early_end : 0;
    }

    public function setComments( string $cmts ) {
        $this->comments = $cmts;
        $this->setDirty();
    }

    public function getComments():string {
        return isset( $this->comments ) ? $this->comments : '';
    }

    public function setHomeScore( int $wins, int $tb_pts=0, int $ties=0 ) {
        $result = false;
        if( $wins > -1 && $tb_pts > -1 && $ties > -1 ) {
            $this->home_wins = $wins;
            $this->home_ties = $ties;
            $this->home_tb_pts = $tb_pts;
            $result = $this->setDirty();
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
        if( $wins > -1 && $tb_pts > -1 && $ties > -1 ) {
            $this->visitor_wins = $wins;
            $this->visitor_ties = $ties;
            $this->visitor_tb_pts = $tb_pts;
            $result = $this->setDirty();
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

    public function toArray() {

        $arr = ["eventId" => $this->event_ID
               ,"bracketNumber" => $this->bracket_num
               ,"roundNumber" => $this->round_num
               ,"matchNumber" => $this->match_num
               ,"setNumber" => $this->set_num
        
               //Score Results
               ,"homeWins" => $this->home_wins
               ,"visitorWins" => $this->visitor_wins
               ,"homeTieBreakPoints" => $this->home_tb_pts
               ,"visitorTieBreakPoints" => $this->visitor_tb_pts
               ,"homeTies" => $this->home_ties
               ,"visitorTies" => $this->visitor_ties
        
               //Misc
               ,"earlyEnd" => $this->early_end
               ,"comments" => $this->comments
            ];

        return $arr;
    }
    
    public function isValid() {
        $mess = '';

        if( $this->event_ID < 1) {  
            $mess = __( "Invalid event ID." );
        }
        elseif( $this->bracket_num < 1 ) {
            $mess = __( "Invalid bracket number.");
        }
        elseif( $this->round_num < 0 ) {
            $mess = __( "Invalid round number." );
        }
        elseif( $this->match_num < 1 ) {
            $mess = __( "Invalid match number." );
        }
        elseif( $this->set_num < 1 || $this->set_num > self::MAXSETS ) {
            $mess = __( "Invalid set number." );
        }
        // elseif( 0 === $this->home_wins && 0 === $this->visitor_wins && !$this->earlyEnd() ) {
        //     $mess =  __( "Both home and visitor scores cannot be zero." );
        // }

        if( strlen( $mess ) > 0 ) throw new InvalidSetException( $mess );

        return true;
    }
    
    public function toString() {
        return sprintf("S(%d,%d,%d,%d,%d)", $this->event_ID, $this->bracket_num, $this->round_num, $this->match_num, $this->set_num );
    }

    private function fetchMatch() {
        $this->match = TennisMatch::get( $this->event_ID, $this->bracket_num, $this->round_num, $this->match_num, $this->set_num );
    }

	protected function create() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        global $wpdb;
        
        parent::create();
        
        $table = $wpdb->prefix . self::$tablename;
        $wpdb->query("LOCK TABLES $table LOW_PRIORITY WRITE;");
        
        if( $this->set_num > 0 ) {
            $sql = "SELECT COUNT(*) FROM $table WHERE event_ID=%d AND bracket_num=%d AND round_num=%d AND match_num=%d AND set_num=%d;";
            $exists = (int) $wpdb->get_var($wpdb->prepare( $sql, $this->event_ID, $this->bracket_num, $this->round_num, $this->match_num, $this->set_num ), 0 ,0 );
            
            if($exists > 0) {
                $this->isnew = false;
                $mess = __( sprintf("%s -> %s already exists.",$loc, $this->toString() ) );
                $code = 600;
                throw new InvalidSetException( $mess, $code );
            }
        }
        else {
            //If set_num is null or zero, then use the next largest value from the db
            $sql = "SELECT IFNULL(MAX(set_num),0) FROM $table WHERE event_ID=%d AND bracket_num=%d AND round_num=%d AND match_num=%d;";
            $safe = $wpdb->prepare( $sql, $this->event_ID, $this->bracket_num, $this->round_num, $this->match_num );
            $this->set_num = $wpdb->get_var( $safe ) + 1;
            error_log( sprintf("%s %s set number assigned.", $loc, $this->toString() ) );
        }
        
 
        $values = array( 'event_ID'       => $this->event_ID
                        ,'bracket_num'    => $this->bracket_num
                        ,'round_num'      => $this->round_num
                        ,'match_num'      => $this->match_num
                        ,'set_num'        => $this->set_num
                        ,'home_wins'      => $this->home_wins
                        ,'visitor_wins'   => $this->visitor_wins
                        ,'home_tb_pts'    => $this->home_tb_pts
                        ,'visitor_tb_pts' => $this->visitor_tb_pts
                        ,'home_ties'      => $this->home_ties
                        ,'visitor_ties'   => $this->visitor_ties
                        ,'early_end'      => $this->early_end
                        ,'comments'       => $this->comments );
		$formats_values = array( '%d', '%d','%d','%d','%d','%d','%d','%d','%d','%d','%d', '%d', '%s' );
		$wpdb->insert( $wpdb->prefix . self::$tablename, $values, $formats_values );
        $result =  $wpdb->rows_affected;
        $wpdb->query( "UNLOCK TABLES;" );
		$this->isnew = false;
        $this->isdirty = false;
        
        error_log( sprintf("%s(%s) -> %d rows affected.", $loc, $this->toString(), $result) );

		return $result;
	}

	protected function update() {
		global $wpdb;
        $loc = __CLASS__ . '::' . __FUNCTION__;

        parent::update();

        $values = array( 'home_wins'      => $this->home_wins
                        ,'visitor_wins'   => $this->visitor_wins
                        ,'home_tb_pts'    => $this->home_tb_pts
                        ,'visitor_tb_pts' => $this->visitor_tb_pts
                        ,'home_ties'      => $this->home_ties
                        ,'visitor_ties'   => $this->visitor_ties 
                        ,'early_end'      => $this->early_end
                        ,'comments'       => $this->comments );
		$formats_values = array( '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s'  );
        $where = array('event_ID'       => $this->event_ID
                      ,'bracket_num'    => $this->bracket_num
                      ,'round_num'      => $this->round_num
                      ,'match_num'      => $this->match_num
                      ,'set_num'        => $this->set_num );
		$formats_where  = array( '%d', '%d', '%d', '%d', '%d' );
		$wpdb->update( $wpdb->prefix . self::$tablename, $values, $where, $formats_values, $formats_where );
		$this->isdirty = false;
        $result =  $wpdb->rows_affected;

        error_log( sprintf("%s(%s) -> %d rows affected.", $loc, $this->toString(), $result) );

		return $result;
    }
    
    /**
     * Map incoming data to an instance of Set
     */
    protected static function mapData( $obj, $row ) {
        parent::mapData( $obj, $row );
        $obj->event_ID        = (int) $row["event_ID"];
        $obj->bracket_num     = (int) $row["bracket_num"];
        $obj->round_num       = (int) $row["round_num"];
        $obj->match_num       = (int) $row["match_num"];
        $obj->set_num         = (int) $row["set_num"];
        $obj->home_wins       = (int) $row["home_wins"];
        $obj->visitor_wins    = (int) $row["visitor_wins"];
        $obj->home_tb_pts     = (int) $row["home_tb_pts"];
        $obj->visitor_tb_pts  = (int) $row["visitor_tb_pts"];
        $obj->home_ties       = (int) $row["home_ties"];
        $obj->visitor_ties    = (int) $row["visitor_ties"];
        $obj->early_end       = (int) $row["early_end"];
        $obj->comments        = $row["comments"];
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