<?php 
require('./wp-load.php'); 
if ( ! defined( 'ABSPATH' ) ) {
	echo "ABSPATH MISSING!";
	exit;
}
?>
Testing Courts
<?php 
use PHPUnit\Framework\TestCase;
/**
 * @group court
 */
class CourtTest extends TestCase
{
	public function test_court()
	{
		$club = Club::get(1);
		$this->assertEquals('Tyandaga Tennis Club',$club->getName());

        //Court 1
        $court = new Court;
        $this->assertEquals(0,$court->getCourtNum());
        $court->setClubId($club->getID());
        $this->assertEquals($club->getID(),$court->getClubId());
        $court->save();
        $this->assertEquals(1,$court->getCourtNum());
        $scourt = Court::get($court->getClubID(),$court->getCourtNum());
        $this->assertEqual(1,$scourt->getCourtNum());
        $this->assertEquals($club->getID(),$scourt->getClubId());

        //Court 2
        $court2 = new Court;
        $court->setClubId($club->getID());
	}
}

?> 