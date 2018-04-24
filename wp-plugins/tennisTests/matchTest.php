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
        $table = "{$wpdb->prefix}tennis_match";
        $sql = "delete from $table where match_num between 1 and 999;";
        $wpdb->query($sql);
        
        //fwrite(STDOUT, __METHOD__ . "\n");
	}


    public function test_create_matches() {
        
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

        $all = self::$mens->getDraw();
        $this->assertCount( 13, $all );

        for( $i=0; $i < count($all) - 1; $i += 2 ) {
            $match = self::$mens->addNewMatch( 1, $all[$i], MatchType::MENS_SINGLES, $all[$i+1] );
            $this->assertTrue( $match instanceof Match );
            $this->assertEquals( MatchType::MENS_SINGLES, $match->getMatchType() );
        }

        $this->assertEquals( 6, self::$mens->numMatches() );
        $matches = self::$mens->getMatches();
        $this->assertCount( 6, $matches );
        //var_dump($mens);
        $this->assertTrue( self::$mens->isDirty() );
        $this->assertGreaterThan( 10, self::$mens->save() );
        //var_dump($mens);
    }

    public function test_match_scoring() {

        $this->assertFalse(self::$mens->isDirty());
        $this->assertEquals(6, self::$mens->numMatches());

        $matches = self::$mens->getMatches();
        $this->assertCount( 6, $matches );

        foreach($matches as $match) {
            $this->assertGreaterThan( 0, $match->getMatchNumber(),'Match number > 0' );
            $match->setMatchDate( 2018, 4, 16 );
            $this->assertEquals( '2018-04-16', $match->getMatchDate_Str(), 'Test match date');
            $match->setMatchTime( 2, 30 );
            $this->assertEquals( '02:30', $match->getMatchTime_Str(), 'Test match time');
            $hscore = rand( 1, 6 );
            $vscore = rand( 1, 6 );
            $this->assertTrue( $match->setScore( 1, $hscore, $vscore) );
            $set = $match->getSetByNumber( 1 );
            $this->assertTrue( isset( $set ) );
            $this->assertEquals( 1, $set->getSetNumber() );
            $this->assertEquals( 0, $set->earlyEnd() );
            $this->assertEquals( $hscore, $set->getHomeWins(), 'Home wins' );
            $this->assertEquals( $vscore, $set->getVisitorWins(), 'Visitor wins' );
            $this->assertTrue( $match->isDirty(), 'Match is dirty 1' );
            $this->assertTrue( $match->setComments( 'Results for match.' ) );
            $this->assertTrue( $match->getEvent()->isDirty(), 'Matches parent is dirty 2' );
            $this->assertEquals( self::$mens, $match->getEvent(), 'Mens equals parent' );
        }
        
        $this->assertTrue( self::$mens->isDirty(), 'Mens singles must be dirty!' );
        $affected = self::$mens->save();
        $this->assertGreaterThan( 0, $affected );
    }
}