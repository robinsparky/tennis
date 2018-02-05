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
 * @group clubevent
 * @group all
 */
class ClubEventTest extends TestCase
{
	
    public static function setUpBeforeClass()
    {
        // global $wpdb;
        // $table = "{$wpdb->prefix}tennis_club_event";
        // $sql = "truncate $table;";
        // $wpdb->query($sql);
        //fwrite(STDOUT, __METHOD__ . "\n");
    }
    
    /**
     * 
     */
    public function test_add_events_to_club() {
        $clubs = Club::search('Tyandaga%');
        $this->assertEquals(1,count($clubs));
        $this->assertEquals('Tyandaga Tennis Club',$clubs[0]->getName());

        $club = $clubs[0];

        $events = Event::search('Year End%');
        $this->assertCount(1,$events);
        $event = $events[0];
        $event->getChildren();
        $this->assertCount(3,$event->getChildEvents());

        $club2 = new Club;
        $club2->setName("BFRC");
        $this->assertEquals("BFRC",$club2->getName());
        $this->assertTrue($club2->isValid());
        $this->assertTrue($event->isRoot(),'Is root');

        $this->assertTrue($event->addClub($club2));
        $this->assertGreaterThan(0,$event->save());

    }

    
    public static function tearDownAfterClass()
    {
        //fwrite(STDOUT, __METHOD__ . "\n");
    }
}