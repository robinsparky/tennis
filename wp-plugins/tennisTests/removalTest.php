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
            $this->assertTrue($match->removeSet(1));
            $this->assertEquals(1,$match->save());
        }
    }

    public function test_remove_matches() {
        
        $this->assertTrue(self::$mens->removeAllMatches(),'Remove all matches');
        $this->assertEquals(0,self::$mens->numMatches(),'Number of matches should be zero');
        $this->assertGreaterThan(0,self::$mens->save());
    }

    public function test_remove_draw() {

        $draw = self::$mens->getDraw();
        $this->assertEquals(13,count($draw));
        foreach($draw as $ent) {
            $this->assertTrue(self::$mens->removeFromDraw($ent->getName()));
        }
        
        //var_dump(self::$mens->getDraw());
        $this->assertCount(0,self::$mens->getDraw());

        $this->assertEquals(13,self::$mens->save());

    }

    public function test_remove_events() {

        $children = self::$mens->getChildEvents();
        $root = self::$mens->getRoot();
        $this->assertCount(3,$root->getChildEvents());
        foreach($root->getChildEvents() as $child) {
           $this->assertTrue($root->removeChild($child));
        }

        $this->assertEquals(3,$root->save());
        $this->assertEquals(1,$root->delete());
    }

    public function test_remove_clubs() {
        $clubs = Club::find();
        $this->assertCount(2,$clubs);
        foreach($clubs as $club) {
            $club->delete();
        }
    
    }
}