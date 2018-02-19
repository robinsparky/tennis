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
class CourtTest extends TestCase
{
    public static function setUpBeforeClass()
    {        
		global $wpdb;
        $table = "{$wpdb->prefix}tennis_court";
        $sql = "delete from $table where court_num between 1 and 999;";
		$wpdb->query($sql);
    }
    
	public function test_court()
	{
        $clubs = Club::find();
        $this->assertCount(2,$clubs);
        $club = $clubs[0];
		$this->assertEquals('Tyandaga Tennis Club',$club->getName());

        //Court 1
        $court = new Court;
        $this->assertEquals(0,$court->getCourtNumber());
        $court->setClub($club);
        $this->assertTrue($club->addCourt($court));
        //$this->assertEquals($club->getID(),$court->getClubId());
        //$court->save();
        $this->assertEquals(1,$club->save());
        $this->assertEquals(1,$court->getCourtNumber());
        $scourt = Court::get($court->getClub()->getID(),$court->getCourtNumber());
        $this->assertEquals(1,$scourt->getCourtNumber());
        $this->assertEquals($club->getID(),$scourt->getClubId());

        //Courts 2 thu 9
        for($i=2; $i < 10; $i++) {
            $crt = new Court;
            $this->assertTrue($crt->setCourtType(Court::HARD));
            $this->assertTrue($club->addCourt($crt));
        }
        $this->assertCount(9,$club->getCourts());
        $this->assertTrue($club->isDirty());
        $this->assertEquals(8,$club->save());
    }
    
    public function test_court_remove() {

        $clubs = Club::find();
        $club = $clubs[0];
        $this->assertEquals('Tyandaga Tennis Club',$club->getName());

        $courts = $club->getCourts();
        $this->assertCount(9,$courts);
        $court = $club->getCourtByNumber(8);
        $this->assertEquals(8,$court->getCourtNumber());
        $this->assertTrue($club->removeCourt($court));
        $this->assertGreaterThan(0,$club->save()); 
        $this->assertCount(8,$club->getCourts());
    }
}

?> 