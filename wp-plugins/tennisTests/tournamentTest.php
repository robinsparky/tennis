<?php 
require('./wp-load.php'); 
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<?php 
use PHPUnit\Framework\TestCase;
/**
 * @group tournament
 */
class tournamentTest extends TestCase
{
    public static $yearEndEvt;
    public static $tournamentEvt;

    public static function setUpBeforeClass()
    {
        global $wpdb;
        $table = "{$wpdb->prefix}tennis_event";
        $sql = "delete from $table where ID between 1 and 999;";
        $wpdb->query($sql);

        self::$yearEndEvt = new Event('Year End Tournament');        
        self::$yearEndEvt->setEventType(EventType::TOURNAMENT);
        self::$tournamentEvt = new Event(TournamentDirector::MENSINGLES);
        self::$tournamentEvt->setFormat(Format::SINGLE_ELIM);
        self::$yearEndEvt->addChild(self::$tournamentEvt);
        //fwrite(STDOUT, __METHOD__ . "\n");
	}
	
	public function test_parent_event()
	{
        $td = new TournamentDirector(self::$tournamentEvt);
        $this->assertEquals(self::$tournamentEvt->getName(),TournamentDirector::MENSINGLES);
    }
   
}