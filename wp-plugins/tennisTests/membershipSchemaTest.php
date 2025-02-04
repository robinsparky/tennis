<?php
require('./wp-load.php');
require_once( ABSPATH . 'wp-admin/includes/upgrade.php'); 
require_once( ABSPATH . 'wp-content/plugins/tennismembership/includes/TM_install.php');
if ( ! defined( 'ABSPATH' ) ) {
	echo "ABSPATH MISSING!";
	exit;
}
?>

<?php
use PHPUnit\Framework\TestCase;

/**
 * @group schema
 * @group all
 */
class membershipSchemaTest extends TestCase
{


    // public static function setUpBeforeClass()
    // {
    //     global $wpdb;
    //     $wpdb->show_errors(); 
	// }

    public function test_add_schema() {
        $installer = TM_Install::get_instance();
        $result = $installer->createSchema( true );
        $this->assertTrue( strlen($result) === 0 );
    }



    // public function test_seed_data() {
    //     $installer = TM_Install::get_instance();
    //     $this->assertTrue( isset( $installer) );

    //     $affected = $installer->seedData();
    //     $this->assertTrue( 0  < $affected, $affected );
    // }
    
    public function test_drop_schema() {
        $installer = TM_Install::get_instance();
        $this->assertTrue( isset( $installer) );
        $result = $installer->dropSchema( true );
        $this->assertEquals(1, $result);
    }
}
 

