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
 * @group club
 * @group all
 */
class ClubTest extends TestCase
{
	public static $tyandaga = 0;
	public static $bfrc = 0;

    public static function setUpBeforeClass()
    {        
		global $wpdb;
        $table = "{$wpdb->prefix}tennis_club";
        $sql = "delete from $table where ID between 1 and 999;";
		$wpdb->query($sql);
		
		$sql = "delete from {$wpdb->prefix}tennis_club_event where club_ID between 1 and 999";
        //fwrite(STDOUT, __METHOD__ . "\n");
	}
	
	public function test_club()
	{
		$club = new Club;
		$club->setName('Tyandaga Tennis Club');
		$this->assertEquals('Tyandaga Tennis Club',$club->getName());

		$this->assertEquals(1,$club->save());
		$this->assertGreaterThan(0,$club->getID());
		self::$tyandaga = $club->getID();

		$res = $club->search('%Tyandaga%');
		$this->assertCount(1,$res);

		$club2 = new Club("BFRC");
		$this->assertEquals("BFRC",$club2->getName());
		$club2->setName('Burlington Fitness & Racket Club');
		$this->assertEquals("Burlington Fitness & Racket Club",$club2->getName());
		$this->assertEquals(1,$club2->save());
		self::$bfrc = $club2->getID();
	}

	public function test_interclub() {
        $tclub = Club::get(self::$tyandaga);
		$this->assertEquals('Tyandaga Tennis Club',$tclub->getName());

		$bclub = Club::get(self::$bfrc);
		$this->assertEquals("Burlington Fitness & Racket Club",$bclub->getName());


        $event = new Event('Interclub League');
        $this->assertEquals('Interclub League',$event->getName());
		$this->assertTrue($event->isRoot(),'First time testing for root');
		$this->assertTrue($event->setEventType(EventType::LEAGUE));
		$this->assertEquals(EventType::LEAGUE,$event->getEventType());
		
		$this->assertTrue($event->addClub($tclub),'Add Tyandaga to league');
		$this->assertTrue($event->addClub($bclub),'Add BFRC to league');

		$this->assertCount(2,$event->getClubs());
		$this->assertCount(0,$bclub->getEvents());
		$this->assertCount(0,$tclub->getEvents());
		$this->assertEquals(EventType::LEAGUE,$event->getEventType());

		$this->assertEquals(3,$event->save());
		
		$this->assertCount(1,$bclub->getEvents(true));
		$this->assertCount(1,$tclub->getEvents(true));


	}
	
    public static function tearDownAfterClass()
    {
        //fwrite(STDOUT, __METHOD__ . "\n");
    }
}

?> 