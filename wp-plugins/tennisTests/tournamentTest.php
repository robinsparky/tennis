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

        $table = "{$wpdb->prefix}tennis_club";
        $sql = "delete from $table where ID between 1 and 999;";
		$wpdb->query($sql);
		
		$sql = "delete from {$wpdb->prefix}tennis_club_event where club_ID between 1 and 999";
        $wpdb->query($sql);
        
        $table = "{$wpdb->prefix}tennis_event";
        $sql = "delete from $table where ID between 1 and 999;";
        $wpdb->query($sql);

		$club = new Club;
        $club->setName('Tyandaga Tennis Club');
        $club->save();

        self::$yearEndEvt = new Event('Year End Tournament');        
        self::$yearEndEvt->setEventType(EventType::TOURNAMENT);
        self::$yearEndEvt->addClub($club);
        self::$tournamentEvt = new Event(TournamentDirector::MENSINGLES);
        self::$tournamentEvt->setFormat(Format::SINGLE_ELIM);
        self::$yearEndEvt->addChild(self::$tournamentEvt);
        self::$yearEndEvt->save();
        
        self::$tournamentEvt->addToDraw("Mike Flintoff");
        self::$tournamentEvt->addToDraw("Steve Knight");
        self::$tournamentEvt->addToDraw("Rafa Chiuzi");
        self::$tournamentEvt->addToDraw("Jonathan Bremer");
        self::$tournamentEvt->addToDraw("Stephen OKeefe");
        self::$tournamentEvt->addToDraw("Roger Federer",1);
        self::$tournamentEvt->addToDraw("Raphael Nadal",2);
        self::$tournamentEvt->addToDraw("Andre Agassi",3);
        self::$tournamentEvt->addToDraw("Rodney Devitt");
        self::$tournamentEvt->addToDraw("Tom West");
        self::$tournamentEvt->addToDraw("Novak Djokavic");
        // self::$tournamentEvt->addToDraw("Andy Murray",4);
        // self::$tournamentEvt->addToDraw("Ben Huh");
	}
	
	public function test_parent_event()
	{
        $td = new TournamentDirector(self::$tournamentEvt);
        $this->assertEquals(self::$tournamentEvt->getName(),TournamentDirector::MENSINGLES);
        //$td->createBrackets();
        $evt = $td->getEvent();
        $entrants = $evt->getDraw();
        $this->assertCount(11,$entrants);
        $this->assertTrue($evt->isDirty());
        $this->assertGreaterThan(7,$evt->save());
        $td->showDraw();

        $num = $td->createBrackets();
        $ms = $td->getEvent()->getMatches();
        usort($ms, array('tournamentTest','sortByMatchNumberAsc'));

        $td->showMatches(0);
        $td->showMatches(1);
        echo PHP_EOL . "Number of matches = $num";

        $td->save();
    }

	private function sortByMatchNumberAsc( $a, $b ) {
		if($a->getMatchNumber() === $b->getMatchNumber()) return 0; return ($a->getMatchNumber() < $b->getMatchNumber()) ? -1 : 1;
	}
   
}