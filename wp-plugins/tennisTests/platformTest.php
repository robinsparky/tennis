<?php 
require('./wp-load.php'); 
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// $path = plugin_dir_path( __FILE__ ) . '../wp-content/plugins/tennisevents/includes/datalayer/Event.php';
// //$path = str_replace( '\\', DIRECTORY_SEPARATOR, $path );
// echo $path;
// require $path;
?>
Testing Research
<?php 
use datalayer\Event;
use PHPUnit\Framework\TestCase;
/**
 * @group research
 */
class PlatformTest extends TestCase
{
    
	public function test_Platform()
	{
        $t1 = 1;
        $t2 = 1;
        $this->assertTrue($t1 === $t2);
        /*
        $o1 = new stdclass;
        $o1->format = "Y/m/d";
        $o1->date = DateTime::createFromFormat($o1->format,"2018/1/1");
        var_dump($o1);

        $o2 = new stdclass;
        $o2->format = "!Y/m/d";
        $o2->date = DateTime::createFromFormat($o2->format,"2018/1/1");
        var_dump($o2);
        
        $o3 = new stdclass;
        $o3->format = "|Y/m/d";
        $o3->date = DateTime::createFromFormat($o3->format,"2018/1/1");
        var_dump($o3);
        
        $o4 = new stdclass;
        $o4->format = "|Y/n/j";
        $o4->date = DateTime::createFromFormat($o4->format,"2018/1/1");
        var_dump($o4);

        $o5 = new stdclass;
        $o5->format = "|j-n-Y";
        $o5->date = DateTime::createFromFormat($o5->format,"1-1-2018");
        var_dump($o5);

        $o6 = new stdclass;
        $o6->format="H:i:s";
        $o6->date = new DateTime("5:10:30");
        $o6->date->setDate(0,1,1);
        var_dump($o6);
        
        $o7 = new stdclass;
        $o7->format="H:i:s";
        $o7->date = new DateTime();
        $o7->date->setTime(5,30);
        $o7->date->setDate(0,1,1);
        var_dump($o7);


        $o7 = new stdclass;
        $o7->format="Y-m-d";
        $o7->date = new DateTime();
        var_dump($o7);

        global $wpdb;
        $id = 293;
        $table = "{$wpdb->prefix}tennis_event";
        $sql = "select * from $table where ID = $id";
        $rows = $wpdb->get_results($sql);
        var_dump($rows);

        $td = new DateTime($rows[0]->start_date);
        var_dump($td);

        $test = $td->format("Y-m-d H:i:s");
        $mess = isset($test) ? "--->Start = $test" : " --->Start is null";
        fwrite(STDOUT,PHP_EOL .  __METHOD__ .$mess . PHP_EOL);
        */

        $root = Event::get(171);
        echo $root->toString();
        $this->assertFalse($root->isDirty());
        // $mens = $root->getNamedEvent('Mens Singles');
        // $this->assertFalse($mens->isDirty());
        // $refMens = $root->getNamedEvent('Mens Singles');
        // $this->assertTrue($mens === $refMens);
        
        // $mens->setFormat(Format::DOUBLE_ELIM);
        // $this->assertTrue($mens->isDirty());
        // $this->assertTrue($refMens->isDirty());
        // $this->assertTrue($root->isDirty());
    }

}