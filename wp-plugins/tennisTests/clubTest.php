<?php 
require('./wp-load.php'); 
if ( ! defined( 'ABSPATH' ) ) {
	echo "ABSPATH MISSING!";
	exit;
}
?>
Testing Clubs
<?php 
use PHPUnit\Framework\TestCase;
/**
 * @group club
 * @group all
 */
class ClubTest extends TestCase
{
	
    public static function setUpBeforeClass()
    {        
		global $wpdb;
        $table = "{$wpdb->prefix}tennis_club";
        $sql = "delete from $table where ID between 1 and 999;";
        $wpdb->query($sql);
        //fwrite(STDOUT, __METHOD__ . "\n");
	}
	
	public function test_club()
	{
		$club = new Club;
		$club->setName('Tyandaga Tennis Club');
		$this->assertEquals('Tyandaga Tennis Club',$club->getName());

		$club->save();
		$this->assertGreaterThan(0,$club->getID());
		$res = $club->search('%Tyandaga%');
		$this->assertCount(1,$res);
	}
	
    public static function tearDownAfterClass()
    {
        //fwrite(STDOUT, __METHOD__ . "\n");
    }
}

?> 