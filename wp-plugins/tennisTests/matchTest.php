<?php 
require('./wp-load.php'); 
if ( ! defined( 'ABSPATH' ) ) {
	echo "ABSPATH MISSING!";
	exit;
}
?>
<?php 
use PHPUnit\Framework\TestCase;
/**
 * @group match
 * @group all
 */
class MatchTest extends TestCase
{
    public static $mens;
	
    public static function setUpBeforeClass()
    {
        global $wpdb;
        $table = "{$wpdb->prefix}tennis_match";
        $sql = "delete from $table where match_num between 1 and 999;";
        $wpdb->query($sql);
        
        //fwrite(STDOUT, __METHOD__ . "\n");
	}

    public function test_create_matches() {
        
        $clubs = Club::search('Tyandaga');
        $this->assertCount(1,$clubs);
        $club = $clubs[0];  

        $events = $club->getEvents();
        $this->assertCount(1,$events);
        $mainevent = $events[0];

        $childEvents = $mainevent->getChildEvents();
        $this->assertCount(3,$childEvents);
        self::$mens = $mainevent->getNamedEvent('Mens Singles');
        $this->assertFalse(self::$mens->isDirty());

        $mike = self::$mens->getNamedEntrant('Mike Flintoff');
        $this->assertEquals('Mike Flintoff',$mike->getName());

        $all = self::$mens->getDraw();
        $this->assertCount(3,$all);

        for( $i=0; $i < count($all) - 1; $i += 2 ) {
            $match = self::$mens->addNewMatch(1,$all[$i],$all[$i+1]);
            $this->assertTrue($match instanceof Match);
            $match->setMatchType(MatchType::MENS_SINGLES);
        }

        $this->assertEquals(1, self::$mens->numMatches());
        $matches = self::$mens->getMatches();
        $this->assertCount(1,$matches);
        //var_dump($mens);
        $this->assertTrue(self::$mens->isDirty());
        $this->assertGreaterThan(0,self::$mens->save());
        //var_dump($mens);
    }

    public function test_match_scoring() {

        $this->assertFalse(self::$mens->isDirty());
        $this->assertEquals(1, self::$mens->numMatches());

        $matches = self::$mens->getMatches();
        $this->assertCount(1,$matches);

        foreach($matches as $match) {
            $match->setMatchDate(2018,4,16);
            $this->assertEquals('2018-04-16',$match->getMatchDate_Str(),'Test match date');
            $match->setMatchTime(2,30);
            $this->assertEquals('02:30',$match->getMatchTime_Str(),'Test match time');
            $this->assertTrue($match->setScore(1,6,3));
            $this->assertTrue($match->isDirty(),'Match is dirty 1');
            $this->assertTrue($match->setComments('Hello World'));
            $this->assertTrue($match->getEvent()->isDirty(),'Matches parent is dirty 2');
            $this->assertEquals(self::$mens,$match->getEvent(),'Mens equals parent');
        }
        
        $this->assertTrue(self::$mens->isDirty(),'Mens singles must be dirty!');
        $affected = self::$mens->save();
        $this->assertGreaterThan(0,$affected);
    }
}