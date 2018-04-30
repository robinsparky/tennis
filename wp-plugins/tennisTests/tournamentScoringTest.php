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
class tournamentScoringTest extends TestCase
{
    public static $club;
    public static $yearEndEvt;
    public static $tournamentEvt;
    public static $tournamentDirector;

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

		self::$club = new Club;
        self::$club->setName('Tyandaga Tennis Club');
        self::$club->save();

        self::$yearEndEvt = new Event( 'Year End Tournament' );        
        self::$yearEndEvt->setEventType( EventType::TOURNAMENT );
        self::$yearEndEvt->addClub( self::$club );
        self::$tournamentEvt = new Event( TournamentDirector::MENSINGLES );
        self::$tournamentEvt->setFormat(Format::SINGLE_ELIM);
        self::$yearEndEvt->addChild( self::$tournamentEvt );
        self::$yearEndEvt->save();
        
        self::$tournamentDirector = new TournamentDirector( self::$tournamentEvt, MatchType::MENS_SINGLES );
        
    }

    public function test_set_scores_create_chairumpire() {
        
        $size = 29; //rand( 12, 20 );
        $seeds = rand( 1, 4 );
        $title = "+++++++++++++++++++++ test_set_scores_full_match: signup size=$size and seeds=$seeds +++++++++++++++++++++++++";
        error_log( $title );
        $this->assertEquals( TournamentDirector::MENSINGLES, self::$tournamentDirector->tournamentName() );
        $this->assertEquals( self::$tournamentDirector->matchType(), MatchType::MENS_SINGLES );
        $this->assertEquals( 0, self::$tournamentDirector->removeDraw() );
        $this->assertEquals( 0, self::$tournamentDirector->drawSize() );
      
        $this->createSignup( $size, $seeds );
        $this->assertEquals( $size, self::$tournamentDirector->drawSize() );

        $num = self::$tournamentDirector->schedulePreliminaryRounds();
        $rnds = self::$tournamentDirector->totalRounds();
        $this->assertGreaterThan( 5, $num, 'Number of matches' );
        $this->assertEquals( $num, count( self::$tournamentDirector->getMatches() ), ' Count of matches' );

        $umpire = self::$tournamentDirector->getChairUmpire();
        $this->assertTrue( $umpire instanceof ChairUmpire, 'Instance of ChairUmpire' );
    }

    public function test_set_scores_each_match() {
        $title = "+++++++++++++++++++++ test_set_scores_each_match +++++++++++++++++++++++++";
        error_log( $title );

        $umpire = self::$tournamentDirector->getChairUmpire();
        $this->assertTrue( $umpire instanceof ChairUmpire, 'Instance of ChairUmpire' );

        $currentRound = self::$tournamentDirector->currentRound();
        $this->assertTrue( (0 === $currentRound) || (1 === $currentRound) );
        $setNum = 1;
        foreach( self::$tournamentDirector->getMatches( $currentRound ) as $match ) {
            if($match->isBye() || $match->isWaiting() ) continue;

            $this->assertEquals( ChairUmpire::NOTSTARTED
                                , $umpire->matchStatus( $match )
                                , sprintf("Status = Not Started for %s", $match->toString()) );
            $hw = rand( 1, 6 );
            $vw = rand( 0, 6 );
            if( $umpire->recordScores( $match, $setNum, $hw, $vw ) ) {
                $this->assertEquals( ChairUmpire::INPROGRESS, $umpire->matchStatus( $match ),"Status = In Progress with hw=$hw vw=$vw" );
            }
        }
    }

    public function test_set_default_players_at_random() {
        $title = "+++++++++++++++++++++ test_set_default_players_at_random +++++++++++++++++++++++++";
        error_log( $title );

        $umpire = self::$tournamentDirector->getChairUmpire();
        $this->assertTrue( $umpire instanceof ChairUmpire, 'Instance of ChairUmpire' );

        $currentRound = self::$tournamentDirector->currentRound();
        foreach( self::$tournamentDirector->getMatches( $currentRound ) as $match ) {
            $coinFlip = rand( 0, 1 );
            if( 1 === $coinFlip && !$match->isBye() && !$match->isWaiting() ) {
                $umpire->defaultVisitor( $match, 'Sore knee');
                $this->assertEquals( ChairUmpire::EARLYEND.':Sore knee' 
                                   , $umpire->matchStatus( $match )
                                   , sprintf("Sore knee for %s",$match->toString()) );
                break;
            }
        }
    }
    
    private function createSignup( int $size, $seeds = 0 ) {
        if($seeds > $size / 2 ) $seeds = 0;

        for( $i = 1; $i <= $size; $i++ ) {
            $s = max( 0, $seeds-- );
            self::$tournamentEvt->addToDraw( "Player $i", $s );
        }
        return self::$tournamentEvt->save();
    }
    
}