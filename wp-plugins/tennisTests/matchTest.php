<?php 
require('./wp-load.php');
if ( ! defined( 'ABSPATH' ) ) {
	echo "ABSPATH MISSING!";
	exit;
}
?>

<?php 
use PHPUnit\Framework\TestCase;
/**
 * @group match
 * @group all
 */
class MatchTest extends TestCase
{
    public static $mens;
	
    public static function setUpBeforeClass()
    {
        global $wpdb;
        $table = "{$wpdb->prefix}tennis_bracket";
        $sql = "delete from $table where bracket_num between 1 and 999;";
        $wpdb->query($sql);
        
        //fwrite(STDOUT, __METHOD__ . "\n");
	}


    public function test_create_matches() {
        $title = "Test Create Matches";
        error_log("++++++++++++++++++++++++++++$title+++++++++++++++++++++++++++++++++++++++++++");
        
        $clubs = Club::search( 'Tyandaga' );
        $this->assertCount( 1, $clubs );
        $club = $clubs[0];  

        $events = $club->getEvents();
        $this->assertCount( 1, $events );
        $mainevent = $events[0];

        $childEvents = $mainevent->getChildEvents();
        $this->assertCount( 3, $childEvents );
        self::$mens = $mainevent->getNamedEvent( 'Mens Singles' );
        $this->assertFalse( self::$mens->isDirty() );

        $mike = self::$mens->getNamedEntrant( 'Mike Flintoff' );
        $this->assertEquals( 'Mike Flintoff', $mike->getName() );

        $all = self::$mens->getSignup();
        $this->assertCount( 13, $all );

        $bracket = self::$mens->getWinnersBracket();
        $this->assertEquals( Bracket::WINNERS, $bracket->getName(), 'Winners bracket');
        $this->assertTrue( self::$mens->getID() === $bracket->getEvent()->getID(), 'Mens equals parent' );
        $this->assertTrue( self::$mens->isDirty() );
        $this->assertEquals( 0, self::$mens->save()); //zero because GetWinnersBracket does a save

        $round = 1;
        $matchnum = 0;
        for( $i=0; $i < count($all) - 1; $i += 2 ) {
            $match = $bracket->addNewMatch( $round, MatchType::MENS_SINGLES, $matchnum, $all[$i], $all[$i+1] );
            $this->assertTrue( $match instanceof TennisMatch );
            $this->assertEquals( MatchType::MENS_SINGLES, $match->getMatchType() );
            $this->assertFalse( $match->isBye() );
        }

        $this->assertEquals( 6, $bracket->numMatches() );
        $matches = $bracket->getMatches();
        $this->assertCount( 6, $matches );

        $this->assertTrue( self::$mens->isDirty() );
        $this->assertGreaterThan( 10, self::$mens->save() );
    }

    public function test_match_scoring() {
        $title = "Test TennisMatch Scoring";
        error_log("++++++++++++++++++++++++++++$title+++++++++++++++++++++++++++++++++++++++++++");

        $bracket = self::$mens->getBracket();
        $this->assertEquals( Bracket::WINNERS, $bracket->getName(),'Winners bracket');
        $this->assertTrue( self::$mens->getID() === $bracket->getEvent()->getID(), 'Mens equals parent' );

        $this->assertFalse(self::$mens->isDirty());
        $this->assertFalse( $bracket->isDirty() );
        $this->assertEquals(6, $bracket->numMatches(), 'numMatches is 6');

        $matches = $bracket->getMatches();
        $this->assertCount( 6, $matches, 'count matches is 6' );

        foreach($matches as $match) {
            $this->assertGreaterThan( 0, $match->getMatchNumber(), 'TennisMatch number > 0' );
            $match->setMatchDate( 2018, 4, 16 );
            $this->assertEquals( '2018-04-16', $match->getMatchDate_Str(), 'Test match date');
            //$match->setMatchTime( 2, 30 );
            $this->assertEquals( '02:30', $match->getMatchTime_Str(), 'Test match time');
            $hscore = rand( 1, 6 );
            $vscore = rand( 1, 6 );
            $this->assertTrue( $match->setScore( 1, $hscore, $vscore) );
            $set = $match->getSet( 1 );
            $this->assertTrue( isset( $set ) );
            $this->assertEquals( 1, $set->getSetNumber() );
            $this->assertEquals( 0, $set->earlyEnd() );
            $this->assertEquals( $hscore, $set->getHomeWins(), 'Home wins' );
            $this->assertEquals( $vscore, $set->getVisitorWins(), 'Visitor wins' );
            $this->assertTrue( $match->isDirty(), 'TennisMatch is dirty 1' );
            $this->assertTrue( $match->setComments( 'Results for match.' ) );
            $this->assertTrue( $match->getBracket()->isDirty(), 'Matches parent bracket is dirty 2' );
            $this->assertTrue( $match->getBracket()->getEvent()->isDirty(), 'Matchws grandarent event is dirty 2' );
            $this->assertEquals( $match->getBracket(), $bracket );
        }
        
        $this->assertTrue( self::$mens->isDirty(), 'Mens singles must be dirty!' );
        $affected = self::$mens->save();
        $this->assertGreaterThan( 0, $affected );
    }
}