<?php
require('./wp-load.php');
require_once( ABSPATH . 'wp-admin/includes/upgrade.php'); 
require_once( ABSPATH . 'wp-content/plugins/tennisevents/includes/class-tennis-install.php');
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
class SchemaTest extends TestCase
{


    public static function setUpBeforeClass()
    {
        global $wpdb;
        $wpdb->show_errors(); 
	}

    public function test_drop_schema() {
        $installer = TE_Install::get_instance();
        $this->assertTrue( isset( $installer) );
        $result = $installer->dropSchema();
        $this->assertEquals(1, $result);
    }

    public function test_add_schema() {
        $installer = TE_Install::get_instance();
        $result = $installer->createSchema();
        
        $this->assertTrue( strlen($result) === 0 );
    }
}
 

