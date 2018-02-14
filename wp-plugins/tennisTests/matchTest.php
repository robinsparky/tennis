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
	
    public static function setUpBeforeClass()
    {
        global $wpdb;
        $table = "{$wpdb->prefix}tennis_match";
        $sql = "delete from $table where match_num between 1 and 999;";
        $wpdb->query($sql);
        //fwrite(STDOUT, __METHOD__ . "\n");
	}
	
	public function test_retrieve_entrants()
	{
        $clubs = Club::search('Tyandaga');
        $this->assertCount(1,$clubs);
        $this->assertEquals('Tyandaga Tennis Club',$clubs[0]->getName());  
        $club = $clubs[0];  

        $events = $club->getEvents();
        $this->assertCount(1,$events);
        $mainevent = $events[0];

        $childEvents = $mainevent->getChildEvents();
        $this->assertCount(3,$childEvents);
        $mens = $mainevent->getNamedEvent('Mens Singles');
        $this->assertEquals('Mens Singles',$mens->getName());

        $mike = $mens->getNamedEntrant('Mike Flintoff');
        $this->assertEquals('Mike Flintoff',$mike->getName());

        $all = $mens->getDraw();
        $this->assertCount(3,$all);
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
        $mens = $mainevent->getNamedEvent('Mens Singles');

        $all = $mens->getDraw();
        $this->assertCount(3,$all);

        for($i=0; $i < count($all) - 1; $i+=2) {
            $match = $mens->addNewMatch(1,$all[$i],$all[$i+1]);
            $this->assertTrue($match instanceof Match);
            $match->setMatchType(1.1);
        }
        $this->assertEquals(1, $mens->numMatches());
        var_dump($mens);
        $this->assertGreaterThan(0,$mens->save());
        var_dump($mens);
    }
}