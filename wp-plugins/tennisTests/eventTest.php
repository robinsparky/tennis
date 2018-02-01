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
    
    public function test_child_event() {
        $clubs = Club::search('Tyandaga%');
        $this->assertGreaterThan(0,count($clubs));
        $this->assertEquals('Tyandaga Tennis Club',$clubs[0]->getName());
        
        $parent = new Event();
        $parent->setName('Year End Tournament');
        $this->assertEquals('Year End Tournament',$parent->getName());
        $this->assertTrue($parent->isParent(),'Test for parent event');
        $parent->setEventType(Event::TOURNAMENT);
        $this->assertTrue($parent->isValid());
        
        $child = new Event();
        $child->setName('Mens Singles');
        $this->assertEquals('Mens Singles',$child->getName());
        $child->setParent($parent);
        $this->assertEquals($child->getParent()->getID(),$parent->getID());
        $this->assertFalse($child->isParent(),'Test for child event');
        $child->setFormat(EVENT::SINGLE_ELIM);
        $this->assertTrue($child->isValid());
        
        $parent->save();
    }
	
    public static function tearDownAfterClass()
    {
        //fwrite(STDOUT, __METHOD__ . "\n");
    }
}

?> 