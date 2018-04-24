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
class tournamentSetupTest extends TestCase
{
    public static $yearEndEvt;
    public static $tournamentEvt;

    public static function setUpBeforeClass()
    {
        global $wpdb;

        $table = "{$wpdb->prefix}tennis_club";
        $sql = "delete from $table where ID between 1 and 999999;";
		$wpdb->query($sql);
		
		$sql = "delete from {$wpdb->prefix}tennis_club_event where club_ID between 1 and 999999";
        $wpdb->query($sql);
        
        $table = "{$wpdb->prefix}tennis_event";
        $sql = "delete from $table where ID between 1 and 999999;";
        $wpdb->query($sql);

		$club = new Club;
        $club->setName('Tyandaga Tennis Club');
        $club->save();

        self::$yearEndEvt = new Event( 'Year End Tournament' );        
        self::$yearEndEvt->setEventType( EventType::TOURNAMENT );
        self::$yearEndEvt->addClub( $club );
        self::$tournamentEvt = new Event( TournamentDirector::MENSINGLES );
        self::$tournamentEvt->setFormat(Format::SINGLE_ELIM);
        self::$yearEndEvt->addChild( self::$tournamentEvt );
        self::$yearEndEvt->save();
	}
	
	public function test_challenger_round()
	{        
        $size = 9;
        $title = "+++++++++++++++++++++ test_challenger_round for $size entrants+++++++++++++++++++++++++";
        echo PHP_EOL . $title;
        error_log( $title );

        $td = new TournamentDirector( self::$tournamentEvt, MatchType::MENS_SINGLES );
        $this->assertEquals( TournamentDirector::MENSINGLES, $td->tournamentName() );

        $this->assertEquals( 0, $td->removeDraw() );

        $this->createDraw( $size, 2 );
        $this->assertEquals( $size, $td->drawSize() );
        $td->showDraw();

        $num = $td->createBrackets();

        $rnds = $td->totalRounds();
        echo PHP_EOL . PHP_EOL . "Tournament has $rnds rounds";
        $td->showRounds();

        echo PHP_EOL . PHP_EOL . "Tournament has $num matches";

        $this->assertEquals( 5, $num, 'Number of matches' );
    }

	public function test_bye_generation()
	{       
        $size = 15;
        $title = "++++++++++++++++++++test_bye_generation for $size entrants++++++++++++++++++++++++++";
        echo PHP_EOL . $title;
        error_log( $title );
 
        $td = new TournamentDirector( self::$tournamentEvt, MatchType::MENS_SINGLES );
        $this->assertEquals( TournamentDirector::MENSINGLES, $td->tournamentName() );

        $this->assertGreaterThan( 0, $td->removeDraw() );
        $this->assertEquals( 0, $td->drawSize() );

        $this->createDraw( $size, 3 );
        $this->assertEquals( $size, $td->drawSize() );
        
        $td->showDraw();

        $num = $td->createBrackets();
        $rnds = $td->totalRounds();
        echo PHP_EOL . PHP_EOL . "Tournament has $rnds rounds";
        $td->showRounds();

        echo PHP_EOL . PHP_EOL . "Generated $num matches";

        $this->assertEquals( 8, $num, 'Number of matches' );;
    }
    
	public function test_shuffle_bye_generation()
	{        
        $size = 31;
        $title = "++++++++++++++++++++++test_shuffle_bye_generation for $size entrants++++++++++++++++++++++++";
        echo PHP_EOL . $title;
        error_log( $title );

        $seeds = 5;
        $td = new TournamentDirector( self::$tournamentEvt, MatchType::MENS_SINGLES );
        $this->assertEquals( TournamentDirector::MENSINGLES, $td->tournamentName() );

       $this->assertGreaterThan( 0, $td->removeDraw() );
        $this->assertEquals( 0, $td->drawSize() );

        $this->createDraw( $size, $seeds );
        $this->assertEquals( $size, $td->drawSize() );
        $td->showDraw();

        $num = $td->createBrackets( true ); //with shuffle
        $rnds = $td->totalRounds();
        echo PHP_EOL . PHP_EOL . "Tournament has $rnds rounds";
        $td->showRounds();

        echo PHP_EOL . PHP_EOL . "Generated $num matches";
        
        $this->assertEquals(16, $num, 'Number of matches');
    }
    
	public function test_big_challenger_generation()
	{        
        $size = 34;
        $title = "+++++++++++++++++++++++test_big_challenger_generation for $size entrants+++++++++++++++++++++++";
        echo PHP_EOL . $title;
        error_log( $title );

        $seeds = 10;
        $td = new TournamentDirector( self::$tournamentEvt, MatchType::MENS_SINGLES );
        $this->assertEquals( TournamentDirector::MENSINGLES, $td->tournamentName() );

        $this->assertGreaterThan( 0, $td->removeDraw() );
        $this->assertEquals( 0, $td->drawSize() );
        $this->assertEquals( 0, $td->totalRounds() );

        $this->createDraw( $size, $seeds );
        $this->assertEquals( $size, $td->drawSize() );
        $td->showDraw();

        $num = $td->createBrackets( );
        $rnds = $td->totalRounds();
        echo PHP_EOL . PHP_EOL . "Tournament has $rnds rounds";
        $td->showRounds();

        echo PHP_EOL . PHP_EOL . "Generated $num matches";
        $this->assertEquals(17, $num, 'Number of matches');
    }
    
    private function createDraw( int $size, $seeds = 0 ) {
        if($seeds > $size / 2 ) $seeds = 0;

        for( $i = 1; $i <= $size; $i++ ) {
            $s = max( 0, $seeds-- );
            self::$tournamentEvt->addToDraw( "Player $i", $s );
        }
        return self::$tournamentEvt->save();
    }
   
}