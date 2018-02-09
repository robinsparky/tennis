<?php 
require('./wp-load.php'); 
if ( ! defined( 'ABSPATH' ) ) {
	echo "ABSPATH MISSING!";
	exit;
}
?>
Testing Clubs and Events
<?php 
use PHPUnit\Framework\TestCase;
/**
 * @group draw
 * @group all
 */
class DrawTest extends TestCase
{
	
    public static function setUpBeforeClass()
    {
        global $wpdb;
        $table = "{$wpdb->prefix}tennis_entrant";
        $sql = "delete from $table where event_ID between 1 and 999;";
        $wpdb->query($sql);
        //fwrite(STDOUT, __METHOD__ . "\n");
    }
    
    /**
     * 
     */
    public function test_add_entrants_to_draw() {
        $clubs = Club::search('Tyandaga');
        $this->assertEquals(1,count($clubs));
        $this->assertEquals('Tyandaga Tennis Club',$clubs[0]->getName());

        $club = $clubs[0];
        $events = $club->getEvents();
        $this->assertCount(1,$club->getEvents(),'Test club has only one root event');
        $event = $events[0];
        $this->assertTrue($event->isRoot());
        $this->assertCount(3,$event->getChildEvents());

        $menSingles = $event->getNamedEvent('Mens Singles');

        $this->assertInstanceOf(Event::class,$menSingles);

        $this->assertCount(0,$menSingles->getDraw());
        $this->assertTrue($menSingles->addToDraw("Mike Flintoff"),'Test add to draw 1');
        $this->assertTrue($menSingles->addToDraw("Steve Knight"),'Test add to draw 2');
        $this->assertTrue($menSingles->addToDraw("Rafa Chiuzi",2),'Test add to draw 3');
        $this->assertTrue($menSingles->addToDraw("Jonathan Bremer",1),'Test add to draw 4');
        $this->assertCount(4,$menSingles->getDraw(),'Test draw size is 4');
        $this->assertEquals(4,$menSingles->getDrawSize());

        $this->assertGreaterThan(0,$menSingles->save());

    }

    /**
     * @depends test_add_entrants_to_draw
     */
    public function test_remove_entrants_from_draw() {
        $clubs = Club::search('Tyandaga');
        $this->assertEquals(1,count($clubs));
        $this->assertEquals('Tyandaga Tennis Club',$clubs[0]->getName());

        $club = $clubs[0];
        $events = $club->getEvents();
        $event = $events[0];
        $this->assertTrue($event->isRoot());
        $this->assertCount(3,$event->getChildEvents());

        $menSingles = $event->getNamedEvent('Mens Singles');
        $this->assertInstanceOf(Event::class,$menSingles);
        $this->assertEquals('Mens Singles',$menSingles->getName());
        $this->assertEquals($event->getName(),$menSingles->getRoot()->getName());
        $this->assertCount(4,$menSingles->getDraw());

        $this->assertTrue($menSingles->removeFromDraw("Steve Knight"));
        $this->assertCount(3,$menSingles->getDraw());
        $this->assertGreaterThan(0,$menSingles->save());        

    }

    public static function tearDownAfterClass()
    {
        //fwrite(STDOUT, __METHOD__ . "\n");
    }
}