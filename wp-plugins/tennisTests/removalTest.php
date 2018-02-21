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
 * @group remove
 * @group all
 */
class RemovalTest extends TestCase
{   
    public static $mens;

    public function test_remove_sets() {
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

        $matches = self::$mens->getMatches(true);

        $this->assertEquals(count($matches),count(self::$mens->getMatchesByRound(1)));

        foreach($matches as $match) {
            $sets = $match->getSets();
            $this->assertCount(1,$sets);
            $this->assertTrue($match->removeSet($sets[0]));
            $this->assertEquals(1,$match->save());
        }
        
    }
    public function test_remove_matches() {

    }

    public function test_remove_draw() {

    }

    public function test_remove_events() {

    }
}