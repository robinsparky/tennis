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
 */
class ClubEventTest extends TestCase
{
	
    public static function setUpBeforeClass()
    {
        global $wpdb;
        $table = "{$wpdb->prefix}tennis_club_event";
        $sql = "truncate $table;";
        $wpdb->query($sql);
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
        $this->assertCount(2,$event->getChildEvents());

    }

    
    public static function tearDownAfterClass()
    {
        //fwrite(STDOUT, __METHOD__ . "\n");
    }
}