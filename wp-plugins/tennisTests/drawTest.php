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
 * @group draw
 * @group all
 */
class DrawTest extends TestCase
{
    public static $dsize;
	
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
        $this->assertTrue($menSingles->addToDraw("Stephen OKeefe"),'Test add to draw 5');
        $this->assertTrue($menSingles->addToDraw("Roger Federer"),'Test add to draw 6');
        $this->assertTrue($menSingles->addToDraw("Raphael Nadal"),'Test add to draw 7');
        $this->assertTrue($menSingles->addToDraw("Andre Agassi"),'Test add to draw 8');
        $this->assertTrue($menSingles->addToDraw("Rodney Devitt"),'Test add to draw 9');
        $this->assertTrue($menSingles->addToDraw("Tom West"),'Test add to draw 10');
        $this->assertTrue($menSingles->addToDraw("Novak Djokavic"),'Test add to draw 11');
        $this->assertTrue($menSingles->addToDraw("Andy Murray"),'Test add to draw 12');
        $this->assertTrue($menSingles->addToDraw("Ben Huh"),'Test add to draw 13');
        $this->assertTrue($menSingles->addToDraw("Remove Me"),'Test add to draw 14');

        self::$dsize = 14;
        $this->assertCount( self::$dsize, $menSingles->getDraw(), 'Test draw size is 14' );
        $this->assertEquals( self::$dsize, $menSingles->drawSize() );

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
        $this->assertTrue( $event->isRoot() );
        $this->assertCount( 3, $event->getChildEvents() );

        $menSingles = $event->getNamedEvent('Mens Singles');
        $this->assertInstanceOf( Event::class, $menSingles );
        $this->assertEquals('Mens Singles',$menSingles->getName());
        $this->assertEquals($event->getName(),$menSingles->getRoot()->getName());
        $this->assertCount( self::$dsize, $menSingles->getDraw() );

        $this->assertTrue($menSingles->removeFromDraw("Remove Me"));
        $this->assertCount( self::$dsize - 1, $menSingles->getDraw() );
        $this->assertGreaterThan( 0, $menSingles->save() );        

    }

    public static function tearDownAfterClass()
    {
        //fwrite(STDOUT, __METHOD__ . "\n");
    }
}