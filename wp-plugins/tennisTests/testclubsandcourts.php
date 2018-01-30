<?php 
require('./wp-load.php'); 
if ( ! defined( 'ABSPATH' ) ) {
	echo "ABSPATH";
	exit;
}
require_once( ABSPATH . 'wp-content/plugins/tennisevents/autoloader.php');
?>
Testing Clubs and Courts
<?php 
// global $wpdb;
// $wpdb->show_errors(); 

//require_once( ABSPATH . 'wp-admin/includes/upgrade.php'); 
require_once( ABSPATH . 'wp-content/plugins/tennisevents/includes/gw-support.php');

use PHPUnit\Framework\TestCase;
class ClubTest extends TestCase
{
	public function test_club()
	{
		$club = new Club;
		$club->setName('Tyandaga Tennis Club');
		$this->assertEquals('Tyandaga Tennis Club',$club->getName());

		$club->save();
		$this->assertGreaterThan(0
		,$club->getID());
		$res = $club->search('%Tyandaga%');
		$this->assertCount(1,$res);
	}
}

?> 
</pre>