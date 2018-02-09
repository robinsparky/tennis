<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once('../../tennisevents.php');
require_once('api-exceptions.php');

/** 
 * Responsible for putting together the
 * necessary Events and schedule for a Tournament
 * @class  Event
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
*/
class TournamenDirector
{ 

    private const MENSINGLES = 'Mens Singles';
    private const MENSDOUBLES = 'Mens Doubles';
    private const WOMENSINGLES = 'Womens Singles';
    private const WOMENSDOUBLES = 'Womens Doubles';
    private const MIXEDDOUBLES = 'Mixed Doubles';

    private const MIMINUM_ENTRANTS = 8; //minimum for an elimination tournament

    private $tournament;
    private $challengers;

	//This class's singleton
	private static $_instance;

	/**
	 * TournamentDirector Singleton
	 *
	 * @since 1.0
	 * @static
	 * @return $_instance --Main instance.
	 */
	public static function get_instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

    public function __construct() {
		// Don't allow more than one instance of the class
		if ( isset( self::$_instance ) ) {
			wp_die( sprintf( esc_html__( '%s is a singleton class and you cannot create a second instance.', 'ten' ),get_class( $this ) ) );
		}
    }

    public function __destruct() {
        if(isset($this->tournament)) {
            foreach($this->tournament->getChildEvents() as $evt) {
                $evt = null;
            }
        }
    }

    public function createTournament(string $desc) {

        if(isset($this->tournament)) $this->tournament = null;

        $this->tournament = new Event($desc);

        $this->tournament->addChildEvent(new Event(self::MENSINGLES));
        $this->tournament->addChildEvent(new Event(self::WOMENSINGLES));
        $this->tournament->addChildEvent(new Event(self::MENSDOUBLES));
        $this->tournament->addChildEvent(new Event(self::WOMENSDOUBLES));
        $this->tournament->addChildEvent(new Event(self::MIXEDDOUBLES));

        return $this->tournament;

    }

    public function getTournament() {
        return $this->tournament;
    }

    public function createBrackets(Event $evt) {
        $result = false;
        $challengers = $this->generateRounds($evt);
        if($challengers) {
            $this->scheduleChallengers($evt);
        }
        $result = $this->scheduleNextRound($evt);
        return $result;
    }


    /**
     * Traverse the Brackets to find
     * the first incomplete Round
     */
    public function getCurrentRound($evt) {

    }

    private function scheduleChallengers($evt) {

    }

    private function scheduleNextRound($evt) {

    }
    
    /**
     * Traverse the Brackets and create
     * Matches to put into the Rounds
     * Initial draw --> challenger round + Round 1
     * Winners of Round 1 --> Round 2
     *      etc......
     */
    private function populateBrackets($evt) {

    }

    private function clearRounds($evt) {
        $evt->removeRounds();
    }

    private function generateRounds(Event $evt) {
        $this->challengers = 0;

        if(!$evt->isLeaf()) {
            $mess = __('Event must be leaf to generate rounds.',TennisEvents::TEXTDOMAIN);
            throw new InvalidEventException($mess);
        }

        if($evt->getDrawSize() >= self::MINIMUM_ENTRANTS) {
            $numRounds = self::calculateExponent($evt->getDrawSize());
            $this->$challengers = $evt->getDrawSize() - pow(2,$numRounds);

            $rounds = array();
            if($this->challengers > 0) {
                $rounds[] = new Round($evt->getID(),1);
                $rounds[0]->setComments('Challenger');
            }

            for($i=0;$i<$numRounds;$i++) {
                $r = $i + 1;
                $rnd = new Round($evt->getID(),$r);
                $rnd->setComments("Round $r");
                $rounds[] = $rnd;
            }
            $evt->setRounds($result->rounds);
        }
        return $challengers;
    }

	private function calculateExponent(int $drawSize) {
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