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
        $title = "Test Parent Event";
        error_log("++++++++++++++++++++++++++++$title+++++++++++++++++++++++++++++++++++++++++++");

        $clubs = Club::search('Tyandaga');
        $this->assertCount(1,$clubs);
		$this->assertEquals('Tyandaga Tennis Club',$clubs[0]->getName());

        $event = new Event('Year End Tournament');
        $this->assertEquals('Year End Tournament',$event->getName());
        $this->assertFalse($event->isParent(),'First time testing for parent');
    }

    
    /**
     * 
     */
    public function test_persisting_events() {
        $title = "Test Persisting Events";
        error_log("++++++++++++++++++++++++++++$title+++++++++++++++++++++++++++++++++++++++++++");

        $clubs = Club::search('Tyandaga');
        $this->assertCount(1,$clubs);
        $club = $clubs[0];
        $this->assertEquals('Tyandaga Tennis Club',$club->getName());

        
        $parent = new Event('Year End Tournament');
        $this->assertTrue($parent->isRoot(),'Test is root');
        $this->assertEquals('Year End Tournament',$parent->getName(),'Test get name');
        $this->assertFalse($parent->isParent(),'Test parent event is not parent yet');

        $this->assertTrue($club->isValid(),'Test that Club is valid before adding it.');
        $this->assertTrue($parent->addClub($club),'Test adding club to parent event');
        $this->assertTrue($parent->setEventType(EventType::TOURNAMENT));
        $this->assertEquals(EventType::TOURNAMENT,$parent->getEventType());
        
        //First child
        $child = new Event('Mens Singles');
        $this->assertEquals('Mens Singles',$child->getName());
        $this->assertTrue($child->setFormat(Format::SINGLE_ELIM),'Setting format for child 1');
        $this->assertEquals(Format::SINGLE_ELIM,$child->getFormat());
        $this->assertFalse($child->isParent(),'Test for child 1 not parent');

        $this->assertTrue($parent->addChild($child),'Adding child');
        $this->assertFalse($child->isRoot(),'Test that child 1 is not root');
        $this->assertTrue($child->isValid(),'Test that child 1 is valid');
        $this->assertTrue($parent->isValid(),'Test parent event is now valid because it has a child');
        $this->assertEquals($parent,$child->getParent());
        $this->assertEquals($child->getParent()->getID(),$parent->getID());
        $this->assertTrue($parent->isParent(),'Test parent event is now a parent');

        //Second child
        $child2 = new Event();
        $child2->setName('Mens Doubles');
        $this->assertEquals('Mens Doubles',$child2->getName());
        $this->assertTrue($child2->setFormat(Format::DOUBLE_ELIM),'Setting format for child 2');
        $this->assertEquals(Format::DOUBLE_ELIM,$child2->getFormat());
        $this->assertFalse($child2->isParent(),'Test for child 2 not parent');

        $this->assertTrue($parent->addChild($child2),'Adding child 2');
        $this->assertTrue($child2->isValid(),'Test for child 2 is valid');
        $this->assertEquals($parent,$child2->getParent());
        $this->assertEquals($child2->getParent()->getID(),$parent->getID());

        $this->assertGreaterThan(0,$parent->save());
    }
    
    /**
     * Test signup, start and end dates for a match
     */
    public function test_event_dates() {
        $title = "Test Creating Events with Dates";
        error_log("++++++++++++++++++++++++++++$title+++++++++++++++++++++++++++++++++++++++++++");

        $clubs = Club::search('Tyandaga');
        $this->assertCount(1,$clubs);
        $club = $clubs[0];
        $this->assertEquals('Tyandaga Tennis Club',$club->getName());

        
        $parent = new Event('Year End Tournament');
        $this->assertTrue($parent->isRoot(),'Test is root');
        $this->assertEquals('Year End Tournament',$parent->getName(),'Test get name');
        $this->assertFalse($parent->isParent(),'Test parent event is not parent yet');
        $this->assertTrue($club->isValid(),'Test that Club is valid before adding it.');
        $this->assertTrue($parent->addClub($club),'Test adding club to parent event');
        $this->assertTrue($parent->setEventType(EventType::TOURNAMENT));
        $this->assertEquals(EventType::TOURNAMENT,$parent->getEventType());

        //Child event
        $child = new Event('Mens Singles');
        $this->assertEquals('Mens Singles',$child->getName());
        $this->assertTrue($child->setFormat(Format::SINGLE_ELIM),'Setting format for child 1');
        $this->assertEquals( $child->getMatchType(), MatchType::MENS_SINGLES );
        $this->assertEquals(Format::SINGLE_ELIM,$child->getFormat());
        $this->assertFalse($child->isParent(),'Test for child 1 not parent');
        $this->assertTrue($parent->addChild($child),'Adding child');
        $this->assertTrue($child->isValid(),'Test that child 1 is valid');

        $this->assertTrue($child->setSignupBy('2018/2/14'),'Test setting signup date');
        $test = $child->getSignupBy_Str();
        // $mess = isset($test) ? " ***** signup by = $test" : " **** signup by is null";
        // fwrite(STDOUT,PHP_EOL .  __METHOD__ .$mess . PHP_EOL);
        $this->assertEquals('2018-02-14 00:00:00',$test);
        
        $this->assertTrue($child->setStartDate('2018/02/16'),'Test setting start date');
        $test = $child->getStartDate_Str();
        // $mess = isset($test) ? " ***** start = $test" : " **** start is null";
        // fwrite(STDOUT,PHP_EOL .  __METHOD__ .$mess . PHP_EOL);
        $this->assertEquals('2018-02-16 00:00:00',$test);

        $this->assertTrue($child->setEndDate('2018/02/20'),'Test setting end date');
        $test = $child->getEndDate_Str();
        // $mess = isset($test) ? " ***** end = $test" : " **** end is null";
        // fwrite(STDOUT,PHP_EOL .  __METHOD__ .$mess . PHP_EOL);
        $this->assertEquals('2018-02-20 00:00:00',$test);

        $test = $child->getEndDate_ISO();
        $this->assertEquals('2018-02-20T00:00:00+0000',$test);
        // $mess = isset($test) ? " ***** end ISO = $test" : " **** end ISO is null";
        // fwrite(STDOUT,PHP_EOL .  __METHOD__ .$mess . PHP_EOL);
    }
    
    /**
     * @depends test_persisting_events
     */
    public function test_removing_children() {
        $title = "Test Removing Child Events";
        error_log("++++++++++++++++++++++++++++$title+++++++++++++++++++++++++++++++++++++++++++");

        $events = Event::search('Year End');
        $this->assertCount(1,$events);
        $mainevent = $events[0];
        $this->assertTrue($mainevent->isRoot(),'Is root');

        $this->assertCount(2,$mainevent->getChildEvents(),'Test 2 children');

        $anotherChild = new Event('Womens Singles');
        $anotherChild->setFormat(Format::GAMES);
        $this->assertTrue($mainevent->addChild($anotherChild),'Test adding child event');
        $this->assertFalse($mainevent->addChild($anotherChild),'Test adding duplicate child');
        
        $this->assertTrue($anotherChild->setSignupBy('2018/02/14'),'Test saving signup date');
        $test = $anotherChild->getSignupBy_Str();
        $this->assertEquals('2018-02-14 00:00:00',$test);

        $this->assertTrue($anotherChild->setStartDate('2018/02/16'),'Test saving start date');
        $test = $anotherChild->getStartDate_Str();
        $this->assertEquals('2018-02-16 00:00:00',$test);

        $this->assertTrue($anotherChild->setEndDate('2018/02/20'),'Test saving end date');
        $test = $anotherChild->getEndDate_Str();
        $this->assertEquals('2018-02-20 00:00:00',$test);

        $this->assertCount(3,$mainevent->getChildEvents(),'Test 3 children');

        $this->assertGreaterThan(0,$mainevent->save(),'Test saving with new 3rd child');
        $this->assertEquals(0,$mainevent->save(),'Test saving with no changes');
        $this->assertFalse($mainevent->isDirty());
        $this->assertFalse($anotherChild->isDirty());
 
        $deleteChild = new Event('Delete this event');
        $deleteChild->setFormat(Format::DOUBLE_ELIM);
        $this->assertTrue($mainevent->addChild($deleteChild),'Test adding child event to be deleted');

        $this->assertCount(4,$mainevent->getChildEvents(),'Test 4 children');
        $check = $mainevent->save();
        $this->assertTrue($mainevent->removeChild($deleteChild),'Test removing delete child');
        $check = $mainevent->save();
        $this->assertCount(3,$mainevent->getChildEvents(),'Test 3 children');

        //Fetch the event and compare the start date again
        $evt = Event::get($anotherChild->getID());
        //var_dump($evt);
        $test = $evt->getStartDate_Str();
        $this->assertEquals('2018-02-16 00:00:00',$test,'Test fetched events start date');

    }
	
    public static function tearDownAfterClass()
    {
        //fwrite(STDOUT, __METHOD__ . "\n");
    }
}

?> 