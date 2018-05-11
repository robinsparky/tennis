<?php 
require('./wp-load.php'); 
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<?php 
use PHPUnit\Framework\TestCase;

/**
 * @group move
 */
class tournamentMoveTest extends TestCase
{
    public static $tournamentEvt = 0;
    public static $round = 1;

    public static function setUpBeforeClass()
    {
        global $wpdb;
        
        self::$tournamentEvt = $wpdb->get_var( "SELECT MAX(e.ID) 
                                                FROM wp_tennis_event e 
                                                INNER JOIN wp_tennis_match m ON m.event_ID = e.ID
                                                LEFT JOIN wp_tennis_match_entrant me ON me.match_event_ID = m.event_ID AND me.match_round_num = m.round_num AND me.match_num = m.match_num 
                                                LEFT JOIN wp_tennis_entrant ent ON ent.position = me.entrant_position AND ent.event_ID = me.match_event_ID; " );

	}
	
	public function test_move_nonexistant_match()
	{        
        $title = "+++++++++++++++++++++ Move non-existant match 999 to 5 +++++++++++++++++++++";
        error_log( $title );

        $result = Match::move( self::$tournamentEvt, self::$round, 999, 5 );
        $mess = "Move non-existant match: $result matches";
        error_log($mess);
        $this->assertEquals(0,$result);
    }
    
	public function test_move_low_match_forward()
	{        
        global $wpdb;
        $title = "+++++++++++++++++++++ Move low match up one +++++++++++++++++++++";
        error_log( $title );

        $safe = $wpdb->prepare( "SELECT MIN(match_num) FROM wp_tennis_match where round_num = %d; ", array( self::$round ) );
        $low    = (int) $wpdb->get_var( $safe );
        $this->assertGreaterThan( 0, $low, "Low $low greater than 0" );

        $result = Match::move( self::$tournamentEvt, self::$round, $low, $low + 1 );
        $mess =  "Move low $low up one: $result matches";
        error_log( $mess );
        $this->assertGreaterThan( 0, $result, "Low $low up one" );
    }

	public function test_move_median_match_forward()
	{        
        global $wpdb;
        $title = "+++++++++++++++++++++ Move median match up three +++++++++++++++++++++";
        error_log( $title );
        
        //Fancy script to get median match number
        $sql = "SELECT AVG(middle_values) AS 'median' 
                FROM (SELECT t1.match_num AS 'middle_values' FROM 
                        (   SELECT @row:=@row+1 as `row`, x.match_num 
                            FROM wp_tennis_match AS x, (SELECT @row:=0) AS r 
                            WHERE x.round_num = %d 
                            ORDER BY x.match_num 
                        ) AS t1, 
                        (   SELECT COUNT(*) as 'count' 
                            FROM wp_tennis_match x 
                            WHERE x.round_num = %d 
                        ) AS t2 
                        -- the following condition will return 1 record for odd number sets, or 2 records for even number sets.
                        WHERE t1.row >= t2.count/2 and t1.row <= ((t2.count/2) +1) 
                    ) AS t3; ";
        $safe = $wpdb->prepare( $sql, array( self::$round, self::$round ) );
        $median = (int) $wpdb->get_var( $safe );
        $this->assertGreaterThan( 3, $median, "Median $median greater than 3" );

        $result = Match::move( self::$tournamentEvt, self::$round, $median, $median + 3 );
        $mess = "Move median $median up one: $result matches";
        error_log( $mess );
        $this->assertGreaterThan( 0, $result,"Median $median up one" );
    }

	public function test_move_high_match_backward()
	{        
        global $wpdb;
        $title = "+++++++++++++++++++++ Move high match back one +++++++++++++++++++++";
        error_log( $title );

        $safe = $wpdb->prepare( "SELECT MAX(match_num) FROM wp_tennis_match where round_num = %d; ", array( self::$round ) );
        $high   = (int) $wpdb->get_var( $safe );
        $this->assertGreaterThan( 8, $high, "High $high greater than 8" );

        $result = Match::move( self::$tournamentEvt, self::$round, $high, $high - 1 );
        $mess =  "Move high $high back one: $result matches";
        error_log( $mess );
        $this->assertGreaterThan( 0, $result, "High $high back one" );
    }
}