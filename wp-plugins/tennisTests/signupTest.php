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
class SignupTest extends TestCase
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
    public function test_add_entrants_to_signup() {
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

        $this->assertCount(0,$menSingles->getSignup());
        $this->assertTrue($menSingles->addToSignup("Mike Flintoff"),'Test add to draw 1');
        $this->assertTrue($menSingles->addToSignup("Steve Knight"),'Test add to draw 2');
        $this->assertTrue($menSingles->addToSignup("Rafa Chiuzi",2),'Test add to draw 3');
        $this->assertTrue($menSingles->addToSignup("Jonathan Bremer",1),'Test add to draw 4');
        $this->assertTrue($menSingles->addToSignup("Stephen OKeefe"),'Test add to draw 5');
        $this->assertTrue($menSingles->addToSignup("Roger Federer"),'Test add to draw 6');
        $this->assertTrue($menSingles->addToSignup("Raphael Nadal"),'Test add to draw 7');
        $this->assertTrue($menSingles->addToSignup("Andre Agassi"),'Test add to draw 8');
        $this->assertTrue($menSingles->addToSignup("Rodney Devitt"),'Test add to draw 9');
        $this->assertTrue($menSingles->addToSignup("Tom West"),'Test add to draw 10');
        $this->assertTrue($menSingles->addToSignup("Novak Djokavic"),'Test add to draw 11');
        $this->assertTrue($menSingles->addToSignup("Andy Murray"),'Test add to draw 12');
        $this->assertTrue($menSingles->addToSignup("Ben Huh"),'Test add to draw 13');
        $this->assertTrue($menSingles->addToSignup("Remove Me"),'Test add to draw 14');

        self::$dsize = 14;
        $this->assertCount( self::$dsize, $menSingles->getSignup(), 'Test draw size is 14' );
        $this->assertEquals( self::$dsize, $menSingles->signupSize() );

        $this->assertGreaterThan(0,$menSingles->save());

    }

    /**
     * @depends test_add_entrants_to_signup
     */
    public function test_remove_entrants_from_signup() {
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
        $this->assertCount( self::$dsize, $menSingles->getSignup() );

        $this->assertTrue($menSingles->removeFromSignup("Remove Me"));
        $this->assertCount( self::$dsize - 1, $menSingles->getSignup() );
        $this->assertGreaterThan( 0, $menSingles->save() );        

    }

    public static function tearDownAfterClass()
    {
        //fwrite(STDOUT, __METHOD__ . "\n");
    }
}