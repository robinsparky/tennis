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
 * @group all
 */
class EventTest extends TestCase
{
	
    public static function setUpBeforeClass()
    {
        global $wpdb;
        $table = "{$wpdb->prefix}tennis_event";
        $sql = "delete from $table where ID between 1 and 999;";
        $wpdb->query($sql);
        //fwrite(STDOUT, __METHOD__ . "\n");
	}
	
	public function test_parent_event()
	{
        $clubs = Club::search('Tyandaga%');
        $this->assertGreaterThan(0,count($clubs));
		$this->assertEquals('Tyandaga Tennis Club',$clubs[0]->getName());

        $event = new Event(true);
        $event->setName('Year End Tournament');
        $this->assertEquals('Year End Tournament',$event->getName());
        $this->assertTrue($event->isParent(),'First time testing for parent');
    }
    
    /**
     * 
     */
    public function test_persisting_events() {
        $clubs = Club::search('Tyandaga%');
        $this->assertEquals(1,count($clubs));
        $this->assertEquals('Tyandaga Tennis Club',$clubs[0]->getName());
        $club = $clubs[0];

        
        $parent = new Event(true);
        $this->assertTrue($parent->isRoot(),'Test is root');
        $parent->setName('Year End Tournament');
        $this->assertEquals('Year End Tournament',$parent->getName(),'Test get name');
        $this->assertTrue($parent->isParent(),'Test for parent event');
        $this->assertTrue($parent->addClub($club));
        $this->assertTrue($parent->setEventType(Event::TOURNAMENT));
        $this->assertEquals(Event::TOURNAMENT,$parent->getEventType());
        $this->assertTrue($parent->isValid(),'Test parent event is valid');
        //$this->assertGreaterThan(0,$parent->save(),'Test saving parent');
        
        //First child
        $child = new Event();
        $child->setName('Mens Singles');
        $this->assertEquals('Mens Singles',$child->getName());
        $this->assertTrue($child->setFormat(Event::SINGLE_ELIM),'Setting format for child 1');
        $this->assertEquals(Event::SINGLE_ELIM,$child->getFormat());
        $this->assertFalse($child->isParent(),'Test for child 1 not parent');
        $this->assertTrue($child->isValid(),'Test for child 1 is valid');

        $this->assertTrue($parent->addChild($child),'Adding child');
        $this->assertEquals($parent,$child->getParent());
        $this->assertEquals($child->getParent()->getID(),$parent->getID());

        //Second child
        $child2 = new Event();
        $child2->setName('Mens Doubles');
        $this->assertEquals('Mens Doubles',$child2->getName());
        $this->assertTrue($child2->setFormat(Event::DOUBLE_ELIM),'Setting format for child 2');
        $this->assertEquals(Event::DOUBLE_ELIM,$child2->getFormat());
        $this->assertFalse($child2->isParent(),'Test for child 2 not parent');
        $this->assertTrue($child2->isValid(),'Test for child 2 is valid');

        $this->assertTrue($parent->addChild($child2),'Adding child 2');
        $this->assertEquals($parent,$child2->getParent());
        $this->assertEquals($child2->getParent()->getID(),$parent->getID());

        $this->assertGreaterThan(0,$parent->save());
    }

    /**
     * @depends test_persisting_events
     */
    public function test_removing_children() {
        $events = Event::search('Year End Tournament%');
        $this->assertCount(1,$events);
        $mainevent = $events[0];
        $mainevent->getChildren();

        $this->assertCount(2,$mainevent->getChildEvents(),'Test 2 children');

        $anotherChild = new Event;
        $anotherChild->setName('Womens Singles');
        $anotherChild->setFormat(Event::ROUND_ROBIN);
        $this->assertTrue($mainevent->addChild($anotherChild),'Test adding child event');
        $this->assertFalse($mainevent->addChild($anotherChild),'Test adding duplicate child');

        $this->assertCount(3,$mainevent->getChildEvents(),'Test 3 children');

        $this->assertTrue($mainevent->removeChild($anotherChild),'Test removing 1 child');
        $this->assertCount(2,$mainevent->getChildEvents(),'Test 2 children');
    }
	
    public static function tearDownAfterClass()
    {
        //fwrite(STDOUT, __METHOD__ . "\n");
    }
}

?> 