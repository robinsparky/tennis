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
    }
    
	public function test_start()
	{    
        $this->assertGreaterThan( 0, self::$tournamentEvtId );
        $this->assertTrue( self::$tournamentEvt instanceof Event );
    }

    public function test_set_scores_full_match() {
        
        $title = "+++++++++++++++++++++ test_set_scores_full_match +++++++++++++++++++++++++";
        error_log( $title );
        $td = new TournamentDirector( self::$tournamentEvt, MatchType::MENS_SINGLES );
        $this->assertEquals( TournamentDirector::MENSINGLES, $td->tournamentName() );
        $this->assertEquals( $td->matchType(), MatchType::MENS_SINGLES );
    }
    
}