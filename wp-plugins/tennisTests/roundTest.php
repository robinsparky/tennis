<?php 
require('./wp-load.php'); 
if ( ! defined( 'ABSPATH' ) ) {
	echo "ABSPATH MISSING!";
	exit;
}
?>
Testing Clubs and Events
<?php 
use PHPUnit\Framework\TestCase;
/**
 * @group round
 * @group all
 */
class RoundTest extends TestCase
{
    public static function setUpBeforeClass()
    {
        global $wpdb;
        $table = "{$wpdb->prefix}tennis_entrant";
        $sql = "delete from $table where event_ID between 1 and 999;";
        $wpdb->query($sql);

        $table = "{$wpdb->prefix}tennis_round";
        $sql = "delete from $table where event_ID between 1 and 999;";
        $wpdb->query($sql);
        //fwrite(STDOUT, __METHOD__ . "\n");
    }
    
    /**
     * 
     */
    public function test_calculate_challengers() {
        $events = Event::search('Year End');
        $this->assertCount(1,$events);

        $rootEvent = $events[0];
        $this->assertEquals('Year End Tournament', $rootEvent->getName());
        $this->assertEquals(Event::TOURNAMENT,$rootEvent->getEventType());

        $mens = $rootEvent->getNamedEvent('Mens Singles');
        $this->assertEquals('Mens Singles',$mens->getName());
        $this->assertInstanceOf(Event::class,$mens);

        $this->assertCount(0,$mens->getDraw());
        $this->assertTrue($mens->addToDraw("Mike Flintoff"),'Test add to draw 1');
        $this->assertTrue($mens->addToDraw("Steve Knight"),'Test add to draw 2');
        $this->assertTrue($mens->addToDraw("Rafa Chiuzi",2),'Test add to draw 3');
        $this->assertTrue($mens->addToDraw("Jonathan Bremer",1),'Test add to draw 4');
        $this->assertTrue($mens->addToDraw("Stephen Okeefe"),'Test add to draw 5');
        $this->assertTrue($mens->addToDraw("Rodney Devitt"),'Test add to draw 6');
        $this->assertTrue($mens->addToDraw("Richard Daniels"),'Test add to draw 7');
        $this->assertTrue($mens->addToDraw("Stephen Cheeseman"),'Test add to draw 8');
        $this->assertTrue($mens->addToDraw("Jim Walker"),'Test add to draw 9');
        $this->assertEquals(9,$mens->getDrawSize());

        $exponent = self::calculateExponent($mens->getDrawSize());
        $this->assertEquals(3,$exponent); //2^3 = 8 < 9

        $challengers = $mens->getDrawSize() - pow(2,$exponent);
        $this->assertEquals(1,$challengers);

        $this->assertGreaterThan(0,$mens->save());
    }

    /**
     * @depend test_calculate_challengers
     */
    public function test_generate_rounds() {
        $events = Event::search('Year End');
        $this->assertCount(1,$events);

        $rootEvent = $events[0];
        $this->assertEquals('Year End Tournament', $rootEvent->getName());
        $this->assertEquals(Event::TOURNAMENT,$rootEvent->getEventType());

        $mens = $rootEvent->getNamedEvent('Mens Singles');
        $this->assertEquals('Mens Singles',$mens->getName());
        $this->assertInstanceOf(Event::class,$mens);
        $this->assertEquals(9,$mens->getDrawSize());
        $rounds = self::generateRounds($mens);

        $this->assertEquals(3,$rounds->numRounds,'Test number of rounds');
        $this->assertEquals(1,$rounds->numChallengers,'Test number of challangers');
        $this->assertCount(4,$rounds->rounds);
    }

    public static function generateRounds(Event $evt) {
        $result = new stdClass;
        $numRounds = self::calculateExponent($evt->getDrawSize());
        $challengers = $evt->getDrawSize() - pow(2,$numRounds);

        $result->rounds = array();
        if($challengers > 0) {
            $result->rounds[] = new Round($evt->getID(),1);
            $result->rounds[0]->setComments('Challenger');
        }

        for($i=0;$i<$numRounds;$i++) {
            $r = $i + 1;
            $rnd = new Round($evt->getID(),$r);
            $rnd->setComments("Round $r");
            $result->rounds[] = $rnd;
        }

        $result->numChallengers = $challengers;
        $result->numRounds = $numRounds;

        return $result;
    }

	public static function calculateExponent(int $drawSize) {
        $exponent = 0;
        foreach(range(1,10) as $exp) {
            if(pow(2,$exp) > $drawSize) {
                $exponent = $exp - 1;
                break;
            }
        }
        return $exponent;
    }
}