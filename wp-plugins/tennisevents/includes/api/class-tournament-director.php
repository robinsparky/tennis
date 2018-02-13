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

    private const BYE = "bye";
    private const CHALLENGER = "challenger";

    private const MIMINUM_ENTRANTS = 8; //minimum for an elimination tournament

    private $numSpecial;
    private $numRounds;
    private $splitBasis;

    public function __construct(Event $evt) {
        $this->event = $evt;
    }

    public function __destruct() {
        $this->event = null;
    }

    public function createBrackets() {
        $this->calculateEventSize();
        $this->splitBasis = $this->decideSplit();
        switch($this->splitBasis) {
            case self::BYE:
                $result += $this->scheduleByes();
                break;
            case self::CHALLENGER:
                $result += $this->scheduleChallengers();
                break;
        }
        $result += $this->scheduleFirstRound();
        return $result;
    }

    private function decideSplit(float $decideFactor = 1.8) {
        //Decide on Byes or Challngers
        $size = $this->event->drawSize();
        $result = self::CHALLENGER;
        $expLow = $this->calculateExponent($size);
        if($size/pow(2,$expLow) > $decideFactor) {
            $result = self::BYE;
        } 
        return $result;
    }

    /**
     * Traverse the Brackets to find
     * the first incomplete Round
     */
    public function getCurrentRound() {

    }

    private function scheduleByes() {
        $unseeded = array_map(function($e) {if($e->getSeed() < 1) return $e;},$this->events->getEntrants());

        $seeded = array_map(function($e) {if($e->getSeed() > 0) return $e;},$this->event->getEntrants());
        $seeded = usort($seeded,$this->sortBySeedAsc);
        
        //Schedule the byes for seeded players
        $limit = min(count($seeded),$this->numSpecial);
        for( $i = 0; $i < $this->limit; $i++) {
            $home = $seeded[$i];
            $this->event->addNewMatch(1,$home);
        }

        //Schedule the byes for unseeded players if any
        $extra = $this->numSpecial - $limit;
        for( $i = 0;$i < $extra; $i++) {
            $home = $unseeded[$i];
            $this->event->addNewMatch(1,$home);
        }

    }

    private function scheduleChallengers() {
        $unseeded = array_map(function($e) {if($e->getSeed() < 1) return $e;},$this->events->getEntrants());
        $unseeded = array_reverse($unseeded); //want in descending position order

        $seeded = array_map(function($e) {if($e->getSeed() > 0) return $e;},$this->event->getEntrants());
        $seeded = usort($seeded,$this->sortBySeedDesc);
        
        //pick the challengers
        for( $i = 0; $i < $this->numSpecial; $i+=2) {
            $home = $unseeded[$i];
            $visitor = $unseeded[$i+1];
            $this->event->addNewMatch(0,$home,$visitor);
        }

        //THE FOLLOWING SCENARIO SHOULD NEVER HAPPEN
        if(2 * $this->event->numMatches() < $this->numSpecial) {
            //pick the challengers from seeded players
            error_log("Need to choose remaining challengers from seeded players.");
            $limit = $this->numSpecial - $evt->numMatches() * 2;
            for( $i = 0; $i < $limit; $i+=2) {
                $home = $seeded[$i];
                $visitor = $seeded[$i+1];
                $evt->addNewMatch(0,$home,$visitor);
            }
        }
    }

    /**
     * This function takes all players not scheduled in the challenger round
     * and schedules them along with seeded players in the first round.
     */
    private function scheduleFirstRound($evt) {
        $numPlayers = 2 *  $evt->numMatches(); //The challenger matches
        if($numPlayers !== $this->numSpecial || $numPlayers !== ($this->numSpecial + 1)) {
            $mess = __('Number of players scheduled to play is not compatable with the number of challengers.');
            throw new Exception($mess);
        }
        
        $unseeded = array_map( function($e) {if($e->getSeed() < 1) return $e;}, $evt->getEntrants() );
        //$unseeded = array_reverse($unseeded); //want in descending position order

        $seeded = array_map( function($e) {if($e->getSeed() > 0) return $e;}, $evt->getEntrants() );
        $seeded = usort( $seeded, $this->sortBySeedAsc );

        //Remove players already playing in challenger round
        for($i = 0; $i < $numPlayers; $i++) {
            array_pop( $unseeded ); //take players off the end because these are asc order by position
        }

        $totalToBeScheduled = count($seeded) + count($unseeded);
        $toBeScheduled = array($totalToBeScheduled);

        //Add seeded players to array using an even distribution
        $toBeScheduled[0] = count($seeded) > 0 ? array_unshift($seeded) : array_unshift($unseeded);
        $toBeScheduled[count($toBeScheduled) - 1]  = count($seeded) > 0 ? array_pop($seeded) : array_pop($unseeded);
        $k=2;
        $slots = count($seeded) > 0 ? floor(count($toBeScheduled)/count($seeded)) : 0;
        while( count($unseeded) > 0 ) {
            if($slots > 0 && ($slots % $k === 0) && count($seeded) > 0) {
                $toBeScheduled[$k] = ($k % 2 === 0) ? array_unshift($seeded) : array_pop($seeded);               
            }
            else {
                $toBeScheduled[$k] = array_unshift($unseeded);
            }
            ++$k;
        }

        $exp = $this->calculateExponent(count($toBeScheduled));
        $expectedPlayers = pow(2,$exp);
        if(count($toBeScheduled) !== $expectedPlayers) {
            $mess = __("Number of players scheduled for first round is not '$expectedPlayers' ");
            throw new Exception($mess);
        }

        for( $i = 0; $i < count($toBeScheduled); $i += 2 ) {
            $home = $toBeScheduled[$i];
            $visitor = $toBeScheduled[$i+1];
            $evt->addNewMatch(1,$home,$visitor);
        }

    }

    private function sortBySeedDesc($a,$b) {
        if($a->getSeed() === $b->getSeed()) return 1; return ($a->getSeed() > $b->getSeed()) ? 1 : -1;
    }
    
    private function sortBySeedAsc($a,$b) {
        if($a->getSeed() === $b->getSeed()) return 1; return ($a->getSeed() < $b->getSeed()) ? 1 : -1;
    }

    private function calculateEventSize() {
        $this->numSpecial = 0;
        $this->numRounds = 0;

        if(!$this->event->isLeaf()) {
            $mess = __('Event must be leaf to generate rounds.',TennisEvents::TEXTDOMAIN);
            throw new InvalidEventException($mess);
        }

        if($this->event->drawSize() < self::MINIMUM_ENTRANTS) {
            $min = self::MINIMUM_ENTRANTS;
            $mess = __("Event must have at leest $min entrants to be an elimination event.",TennisEvents::TEXTDOMAIN);
            throw new InvalidEventException($mess);
        }

        $this->numRounds = self::calculateExponent($this->event->drawSize());
        $this->$numSpecial = $this->event->drawSize() - pow(2,$this->numRounds);

        return $this->numSpecial;
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