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
        
        // self::$tournamentEvt->addToDraw("Mike Flintoff");
        // self::$tournamentEvt->addToDraw("Steve Knight");
        // self::$tournamentEvt->addToDraw("Rafa Chiuzi");
        // self::$tournamentEvt->addToDraw("Jonathan Bremer");
        // self::$tournamentEvt->addToDraw("Stephen OKeefe");
        // self::$tournamentEvt->addToDraw("Roger Federer",1);
        // self::$tournamentEvt->addToDraw("Raphael Nadal",2);
        // self::$tournamentEvt->addToDraw("Andre Agassi",3);
        // self::$tournamentEvt->addToDraw("Rodney Devitt");
        // self::$tournamentEvt->addToDraw("Tom West");
        // self::$tournamentEvt->addToDraw("Novak Djokavic");
        // self::$tournamentEvt->addToDraw("Andy Murray",4);
        // self::$tournamentEvt->addToDraw("Ben Huh");
        // self::$tournamentEvt->addToDraw("Stephen Cheesman");
        // self::$tournamentEvt->addToDraw("Jonathan Prinsell");
        // self::$tournamentEvt->addToDraw("Larry Chud");
        // self::$tournamentEvt->addToDraw("Player 17");
        // self::$tournamentEvt->addToDraw("Player 18");
        // self::$tournamentEvt->addToDraw("Player 19");
        // self::$tournamentEvt->addToDraw("Player 20");
        // self::$tournamentEvt->addToDraw("Player 21");
        // self::$tournamentEvt->addToDraw("Player 22");
        // self::$tournamentEvt->addToDraw("Player 23");
        // self::$tournamentEvt->addToDraw("Player 24");
        // self::$tournamentEvt->addToDraw("Player 25");
        // self::$tournamentEvt->addToDraw("Player 26");
        // self::$tournamentEvt->addToDraw("Player 27");
        // self::$tournamentEvt->addToDraw("Player 28");
	}
	
	public function test_challenger_round()
	{
        $size = 9;
        $td = new TournamentDirector(self::$tournamentEvt);
        $this->assertEquals( self::$tournamentEvt->getName(),TournamentDirector::MENSINGLES );

        $evt = $td->getEvent();
        $evt->removeDraw();
        $this->createDraw( $size );
        $entrants = $evt->getDraw();
        $this->assertCount( $size, $entrants );
        $this->assertTrue( $evt->isDirty() );
        $this->assertGreaterThan( 7,$evt->save() );
        $td->showDraw();

        $num = $td->createBrackets();
        $ms = $td->getEvent()->getMatches();

        $td->showMatches( 0 );
        $td->showMatches( 1 );
        echo PHP_EOL . PHP_EOL . "Number of matches = $num";

        $this->assertEquals(5,$num, 'Number of matches');
        $this->assertGreaterThan($num, $td->save(),'td save');
    }

	public function test_bye_generation()
	{
        $size = 15;
        $td = new TournamentDirector(self::$tournamentEvt);
        $this->assertEquals( self::$tournamentEvt->getName(),TournamentDirector::MENSINGLES );

        $evt = $td->getEvent();
        $this->assertTrue( $evt->removeDraw() );
        $this->assertEquals( 0, $evt->drawSize() );

        $this->createDraw( $size );
        $entrants = $evt->getDraw();
        $this->assertCount( $size, $entrants );
        $this->assertTrue( $evt->isDirty() );
        $this->assertGreaterThan( 7,$evt->save() );
        $td->showDraw();

        $num = $td->createBrackets();

        $ms = $td->getEvent()->getMatches();

        $td->showMatches( 0 );
        $td->showMatches( 1 );
        echo PHP_EOL . PHP_EOL . "Number of matches = $num";

        $this->assertEquals(8,$num, 'Number of matches');
        $this->assertGreaterThan($num, $td->save(),'td save');
    }
    
	public function test_shuffle_bye_generation()
	{
        $size = 31;
        $seeds = 6;
        $td = new TournamentDirector( self::$tournamentEvt );
        $this->assertEquals( self::$tournamentEvt->getName(),TournamentDirector::MENSINGLES );

        $evt = $td->getEvent();
        $this->assertTrue( $evt->removeDraw() );
        $this->assertEquals( 0, $evt->drawSize() );

        $this->createDraw( $size, $seeds );
        $entrants = $evt->getDraw();
        $this->assertCount( $size, $entrants );
        $this->assertTrue( $evt->isDirty() );
        $this->assertGreaterThan( 7,$evt->save() );
        $td->showDraw();

        $num = $td->createBrackets( true );

        $ms = $td->getEvent()->getMatches();

        $td->showMatches( 0 );
        $td->showMatches( 1 );
        echo PHP_EOL . PHP_EOL . "Number of matches = $num";
        
        $this->assertEquals(16, $num, 'Number of matches');
        $this->assertGreaterThan($num, $td->save(),'td save');
    }
    
    private function createDraw( int $size, $seeds = 0 ) {
        if($seeds > $size / 2 ) $seeds = 0;

        for( $i = 1; $i <= $size; $i++ ) {
            $s = max( 0, $seeds-- );
            self::$tournamentEvt->addToDraw( "Player $i", $s );
        }
    }
   
}