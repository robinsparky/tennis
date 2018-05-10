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
 * @group remove
 * @group all
 */
class RemovalTest extends TestCase
{   
    public static $mens;

    public function test_remove_sets() {
        $clubs = Club::search('Tyandaga');
        $this->assertCount(1,$clubs);
        $club = $clubs[0];  

        $events = $club->getEvents();
        $this->assertCount(1,$events);
        $mainevent = $events[0];

        $childEvents = $mainevent->getChildEvents();
        $this->assertCount(3,$childEvents);
        self::$mens = $mainevent->getNamedEvent('Mens Singles');
        $this->assertFalse(self::$mens->isDirty());

        $bracket = self::$mens->getBracket();
        $this->assertEquals( Bracket::WINNERS, $bracket->getName(),'Winners bracket');
        $this->assertEquals( self::$mens, $bracket->getEvent(), 'Bracket event equals mens' );
        $matches = $bracket->getMatches(true);

        $this->assertEquals(count($matches),count($bracket->getMatchesByRound(1)));

        foreach($matches as $match) {
            $sets = $match->getSets();
            $this->assertCount(1,$sets);
            $this->assertTrue($match->removeSet(1));
            $this->assertEquals(1,$match->save());
        }
    }

    public function test_remove_matches() {
        
        $bracket = self::$mens->getBracket();
        $this->assertEquals( Bracket::WINNERS, $bracket->getName(),'Winners bracket');
        $this->assertEquals( self::$mens, $bracket->getEvent(), 'Bracket event equals mens' );
        
        $this->assertTrue($bracket->removeAllMatches(),'Remove all matches');
        $this->assertEquals(0,$bracket->numMatches(),'Number of matches should be zero');
        $this->assertTrue( self::$mens->isDirty());
        $this->assertGreaterThan(0,self::$mens->save());
    }

    public function test_remove_signup() {

        $bracket = self::$mens->getBracket();
        $this->assertEquals( Bracket::WINNERS, $bracket->getName(),'Winners bracket');
        $this->assertEquals( self::$mens, $bracket->getEvent(), 'Bracket event equals mens' );
        
        $signup = self::$mens->getSignup();
        $this->assertEquals(13,count($signup));
        $this->assertEquals( self::$mens->signupSize(), $bracket->signupSize(), '1. Event and bracket equal signup size');

        $i = 0;
        foreach($signup as $ent) {
            if( $i++ > 6 ) break;
            $this->assertTrue( self::$mens->removeFromSignup( $ent->getName() ) );
        }
        
        $this->assertCount( 6, self::$mens->getSignup() );
        $this->assertEquals( self::$mens->signupSize(), $bracket->signupSize(), '2. Event and bracket equal signup size');

        $this->assertTrue( self::$mens->removeSignup() );
        $this->assertEquals( 0, self::$mens->signupSize() );
        $this->assertEquals( self::$mens->signupSize(), $bracket->signupSize(), '3. Event and bracket equal signup size');

        $this->assertEquals( 13, self::$mens->save() );

    }

    public function test_remove_events() {

        $children = self::$mens->getChildEvents();
        $root = self::$mens->getRoot();
        $this->assertCount(3,$root->getChildEvents());
        foreach($root->getChildEvents() as $child) {
           $this->assertTrue($root->removeChild($child));
        }

        $this->assertEquals(3,$root->save());
        $this->assertEquals(1,$root->delete());
    }

    public function test_remove_clubs() {
        $clubs = Club::find();
        $this->assertCount(2,$clubs);
        foreach($clubs as $club) {
            $club->delete();
        }
    
    }
}