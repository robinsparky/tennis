<?php 
require('./wp-load.php'); 
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<?php 
use PHPUnit\Framework\TestCase;

/**
 * @group tournament
 */
class tournamentTweakTest extends TestCase
{
    public static $tournamentEvt = 535;
    public static $round = 1;
    public static $median = 0;
    public static $low = 0;
    public static $high = 0;

    public static function setUpBeforeClass()
    {
        global $wpdb;
    
        $sql = "SELECT AVG(middle_values) AS 'median' FROM (SELECT t1.match_num AS 'middle_values' FROM 
                    (
                        SELECT @row:=@row+1 as `row`, x.match_num 
                        FROM wp_tennis_match AS x, (SELECT @row:=0) AS r 
                        WHERE x.round_num = %d 
                        ORDER BY x.match_num 
                    ) AS t1, 
                    (
                        SELECT COUNT(*) as 'count' 
                        FROM wp_tennis_match x 
                        WHERE x.round_num = %d 
                    ) AS t2 
                    -- the following condition will return 1 record for odd number sets, or 2 records for even number sets.
                    WHERE t1.row >= t2.count/2 and t1.row <= ((t2.count/2) +1)) AS t3; ";
        $safe = $wpdb->prepare( $sql, array( self::$round, self::$round ) );
        self::$median = (int) $wpdb->get_var( $safe );

        $safe = $wpdb->prepare( "SELECT MIN(match_num) FROM wp_tennis_match where round_num = %d; ", array( self::$round ) );
        self::$low    = (int) $wpdb->get_var( $safe );

        $safe = $wpdb->prepare( "SELECT MAX(match_num) FROM wp_tennis_match where round_num = %d; ", array( self::$round ) );
        self::$high   = (int) $wpdb->get_var( $safe );

	}
	
	public function test_move_nonexistant_match()
	{        
        $result = Match::move( self::$tournamentEvt, self::$round, 999, 5 );
        echo PHP_EOL . "Move non-existant match: $result matches";
        $this->assertEquals(0,$result);
    }

	public function test_move_one_match_forward()
	{        
        $m = self::$median;
        $this->assertGreaterThan(3,$m,'Median greater than 3');
        $result = Match::move( self::$tournamentEvt, self::$round,$m, $m + 1 );
        echo PHP_EOL . "Move median $m up one: $result matches" . PHP_EOL;
        $this->assertGreaterThan(0,$result,'Median up one');
    }

	public function test_move_one_match_backward()
	{        
        $h = self::$high;
        $this->assertGreaterThan(8,$h,'High greater than 8');
        $result = Match::move( self::$tournamentEvt, self::$round,$h,$h - 1 );
        echo PHP_EOL . "Move high $h back one: $result matches" . PHP_EOL;
        $this->assertGreaterThan(0,$result,'High back one');
    }
}