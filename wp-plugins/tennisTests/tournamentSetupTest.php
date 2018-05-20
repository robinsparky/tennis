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
        self::$tournamentEvt->setMatchType( MatchType::MENS_SINGLES );
        self::$tournamentEvt->setFormat(Format::SINGLE_ELIM);
        self::$yearEndEvt->addChild( self::$tournamentEvt );
        self::$yearEndEvt->save();
    }
    
	// public function test_special() {
    //     $loc = __CLASS__ . '::' . __FUNCTION__;
    //     $size = 10;
    //     $title = "+++++++++++++++++++++ $loc for $size entrants+++++++++++++++++++++++++";
    //     error_log( $title );

    //     $td = new TournamentDirector( self::$tournamentEvt, MatchType::MENS_SINGLES );
    //     $this->assertEquals( TournamentDirector::MENSINGLES, $td->tournamentName() );

    //     error_log("First Remove signup...");
    //     $td->removeSignup(); //This removes all brackets and matches too
    //     $this->assertEquals( 0, $td->signupSize() );

    //     $this->createSignup( $size, 2 );
    //     $this->assertEquals( $size, $td->signupSize() );
    //     $bracket = self::$tournamentEvt->getWinnersBracket();
    //     $all = $td->getSignup();
    //     $round = 1;
    //     $matchnum = 0;
    //     for( $i=0; $i < count($all) - 1; $i += 2 ) {
    //         $match = $bracket->addNewMatch( $round, MatchType::MENS_SINGLES, $matchnum, $all[$i], $all[$i+1] );
    //         $this->assertTrue( $match instanceof Match );
    //         $this->assertEquals( MatchType::MENS_SINGLES, $match->getMatchType() );
    //         $this->assertFalse( $match->isBye() );
    //         error_log( sprintf( "%d. Added match %s",$i, $match->toString() ) );
    //     }
    //     $td->save();
    //     error_log("Second Remove signup...");
    //     $td->removeSignup(); //This removes all brackets and matches too
    // }

	public function test_challenger_round()
	{        
        $size = 9;
        $title = "+++++++++++++++++++++ test_challenger_round for $size entrants+++++++++++++++++++++++++";
        error_log( $title );

        $td = new TournamentDirector( self::$tournamentEvt );
        $this->assertEquals( TournamentDirector::MENSINGLES, $td->tournamentName() );

        $td->removeSignup(); //This removes all brackets and matches too
        $this->assertEquals( 0, $td->signupSize() );

        $this->createSignup( $size, 2 );
        $this->assertEquals( $size, $td->signupSize() );

        $watershed = 2;
        $num = $td->schedulePreliminaryRounds( "Winners", "challenger", false );
        $rounds = $td->totalRounds();

        $this->assertEquals( 4, $rounds, 'Number of rounds');
        $this->assertEquals( 5, $num, 'Number of matches' );
    }

	public function test_bye_generation()
	{       
        $size = 15;
        $title = "++++++++++++++++++++test_bye_generation for $size entrants++++++++++++++++++++++++++";
        error_log( $title );
 
        $td = new TournamentDirector( self::$tournamentEvt );
        $this->assertEquals( TournamentDirector::MENSINGLES, $td->tournamentName() );

        $td->removeSignup();
        $this->assertEquals( 0, $td->signupSize() );

        $this->createSignup( $size, 3 );
        $this->assertEquals( $size, self::$tournamentEvt->signupSize() );

        $watershed = 0;
        $num = $td->schedulePreliminaryRounds( "Winners", "bye", false );
        $rnds = $td->totalRounds();

        $this->assertEquals( 3, $rnds,'Number of rounds');
        $this->assertEquals( 8, $num, 'Number of matches' );
    }
    
	public function test_auto_generation()
	{       
        $size = 15;
        $title = "++++++++++++++++++++test_auto_generation for $size entrants++++++++++++++++++++++++++";
        error_log( $title );
 
        $td = new TournamentDirector( self::$tournamentEvt );
        $this->assertEquals( TournamentDirector::MENSINGLES, $td->tournamentName() );

        $td->removeSignup();
        $this->assertEquals( 0, $td->signupSize() );

        $this->createSignup( $size, 3 );
        $this->assertEquals( $size, self::$tournamentEvt->signupSize() );

        $watershed = 0;
        $num = $td->schedulePreliminaryRounds( "Winners", "auto", false );
        $rnds = $td->totalRounds();

        $this->assertEquals( 3, $rnds,'Number of rounds');
        $this->assertEquals( 8, $num, 'Number of matches' );
    }
    
    
	// public function test_shuffle_bye_generation()
	// {        
    //     $size = 31;
    //     $title = "++++++++++++++++++++++test_shuffle_bye_generation for $size entrants++++++++++++++++++++++++";
    //     error_log( $title );

    //     $seeds = 5;
    //     $td = new TournamentDirector( self::$tournamentEvt, MatchType::MENS_SINGLES );
    //     $this->assertEquals( TournamentDirector::MENSINGLES, $td->tournamentName() );

    //     $this->assertGreaterThan( 0, $td->removeSignup() );
    //     $this->assertEquals( 0, $td->signupSize() );

    //     $this->createSignup( $size, $seeds );
    //     $this->assertEquals( $size, $td->signupSize() );

    //     $num = $td->schedulePreliminaryRounds( true ); //with shuffle
    //     $rnds = $td->totalRounds();
        
    //     $this->assertEquals( 4, $rnds,'Number of rounds');
    //     $this->assertEquals(16, $num, 'Number of matches');
    // }
    
	// public function test_big_challenger_generation()
	// {        
    //     $size = 34;
    //     $title = "+++++++++++++++++++++++test_big_challenger_generation for $size entrants+++++++++++++++++++++++";
    //     error_log( $title );

    //     $seeds = 10;
    //     $td = new TournamentDirector( self::$tournamentEvt, MatchType::MENS_SINGLES );
    //     $this->assertEquals( TournamentDirector::MENSINGLES, $td->tournamentName() );

    //     $this->assertGreaterThan( 0, $td->removeSignup() );
    //     $this->assertEquals( 0, $td->signupSize() );
    //     $this->assertEquals( 0, $td->totalRounds() );

    //     $this->createSignup( $size, $seeds );
    //     $this->assertEquals( $size, $td->signupSize() );

    //     $num = $td->schedulePreliminaryRounds( );
    //     $rnds = $td->totalRounds();

    //     $this->assertEquals( 5, $rnds,'Number of rounds');
    //     $this->assertEquals(17, $num, 'Number of matches');
    // }
    
    private function createSignup( int $size, $seeds = 0 ) {
        if($seeds > $size / 2 ) $seeds = 0;

        for( $i = 1; $i <= $size; $i++ ) {
            $s = max( 0, $seeds-- );
            self::$tournamentEvt->addToSignup( "Player $i", $s );
        }
        return self::$tournamentEvt->save();
    }
   
}