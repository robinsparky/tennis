<?php 
require('./wp-load.php'); 
if ( ! defined( 'ABSPATH' ) ) {
	echo "ABSPATH MISSING!";
	exit;
}
?>
Testing Events
<?php 
use PHPUnit\Framework\TestCase;
/**
 * @group event
 */
class EventTest extends TestCase
{
	
    public static function setUpBeforeClass()
    {
        global $wpdb;
        $table = "wp_tennis_event";
        $sql = "delete from $table where ID between 1 and 999;";
        $wpdb->query($sql);
        //fwrite(STDOUT, __METHOD__ . "\n");
	}
	
	public function test_parent_event()
	{
        $clubs = Club::search('Tyandaga%');
        $this->assertGreaterThan(0,count($clubs));
		$this->assertEquals('Tyandaga Tennis Club',$clubs[0]->getName());

        $event = new Event();
        $event->setName('Year End Tournament');
        $this->assertEquals('Year End Tournament',$event->getName());
        $this->assertTrue($event->isParent(),'First time testing for parent');
    }
    
    /**
     * 
     */
    public function test_child_event() {
        $clubs = Club::search('Tyandaga%');
        $this->assertGreaterThan(0,count($clubs));
        $this->assertEquals('Tyandaga Tennis Club',$clubs[0]->getName());
        
        $parent = new Event();
        $parent->setName('Year End Tournament');
        $this->assertEquals('Year End Tournament',$parent->getName());
        $this->assertTrue($parent->isParent(),'Test for parent event');
        $this->assertTrue($parent->setEventType(Event::TOURNAMENT));
        $this->assertEquals(Event::TOURNAMENT,$parent->getEventType());
        $this->assertTrue($parent->isValid(),'Test for valid parent event');
        $this->assertGreaterThan(0,$parent->save());
        
        $child = new Event();
        $child->setName('Mens Singles');
        $this->assertEquals('Mens Singles',$child->getName());
        $this->assertTrue($child->setFormat(Event::SINGLE_ELIM),'Setting format');
        $this->assertEquals(Event::SINGLE_ELIM,$child->getFormat());

        $this->assertTrue($parent->addChild($child),'Adding child');
        $this->assertEquals($parent,$child->getParent());
        $this->assertEquals($child->getParent()->getID(),$parent->getID());
        $this->assertFalse($child->isParent(),'Test for child event');
        $this->assertTrue($child->isValid());
        $num = $parent->save();
        fwrite(STDOUT,"\n".__METHOD__ .": Event rows saved: $num\n");
        $this->assertGreaterThan(0,$num);
    }

    /**
     * @depends test_child_event
     */
    public function test_removing_children() {
        $events = Event::search('Year End Tournament%');
        $this->assertCount(1,$events);
        $mainevent = $events[0];
        $mainevent->getChildren(true);

        var_dump($mainevent);
        $this->assertCount(1,$mainevent->getChildEvents());

    }
	
    public static function tearDownAfterClass()
    {
        //fwrite(STDOUT, __METHOD__ . "\n");
    }
}

?> 