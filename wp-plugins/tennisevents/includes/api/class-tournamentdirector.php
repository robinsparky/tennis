<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$p2Dir = plugin_dir_path( plugin_dir_path( __DIR__ ) );
require_once($p2Dir . 'tennisevents.php');
require_once('api-exceptions.php');

/** 
 * Responsible for putting together the
 * necessary Events and schedule for a Tournament
 * @class  TournamentDirector
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
*/
class TournamentDirector
{ 

    public const MENSINGLES    = 'Mens Singles';
    public const MENSDOUBLES   = 'Mens Doubles';
    public const WOMENSINGLES  = 'Womens Singles';
    public const WOMENSDOUBLES = 'Womens Doubles';
    public const MIXEDDOUBLES  = 'Mixed Doubles';

    private const BYE = "bye";
    private const CHALLENGER = "challenger";

    private const MIMINUM_ENTRANTS = 8; //minimum for an elimination tournament

    private const DECISION_POINT = 0.50;

    private $numToEliminate;
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
 
        $result += $this->scheduleInitialRounds();
        
        return $result;
    }

    /**
     * Traverse the Brackets to find
     * the first incomplete Round
     */
    public function getCurrentRound() {

    }

    /**
     * The purpose of this function is to eliminate enough players 
     * in the first round so that the next round has 2^n players 
     * and the elimination rounds can then proceed naturally to the end.
     * The initial question is whether to have a "challenger" round (0)
     * or to have a few "byes" from round 1 into round 2.
     * The next big question to work out is determining who gets the byes (if any).
     * And finally the seeded players (who get priority for bye selection) must be distributed
     * evenly amoung the un-seeded players with the first and second seeds being at opposite ends of the draw.
     */
    private function scheduleInitialRounds( $randomizeDraw = false ) {
        //$entrants = $this->event->distributeSeededPlayers( $randomizeDraw );
        $entrants = $this->event->getDraw();
        $unseeded = array_map( function( $e ) { if( $e->getSeed() < 1 ) return $e; }, $entrants );
        $unseeded = usort( $unseeded, $this->sortByPositionAsc );
        if( $randomizeDraw ) $unseeded = shuffle( $unseeded );

        $seeded = array_map( function( $e ) { if( $e->getSeed() > 0 ) return $e; }, $entrants );
        $seeded = usort( $seeded, $this->sortBySeedAsc );


        //Few Byes
        if( $numInvolved >= count( $unseeded )) {
            //For this case it is clear that there are so many players requred to be elminated 
            // (so that tne next round has a power of 2 players) that it has to be treated as round 1
            // and a few players (probably mostly seeds) get a bye into round 2.
            $this->processFewByes( $seeded, $unseeded, $seedByes, $unseedByes );
        }
        //Many Byes
        else if( $numInvolved > self::DECISION_POINT * count( $unseeded ) ) {
            //In this case we have the majority of the unseeded plyaers (but less than 100%) to be eliminated to arrive at power of 2.
            // So again we treat this as the first round but now there may be many more byes into the second round.
            // Many unseeded players will get byes and probably all of the seeds.
            $this->processManyByes(  $seeded, $unseeded, $seedByes, $unseedByes  );
        }
        //Challenger Round
        else {
            //With less than 50% of the unseeded players to be eliminated in order to create a next round with power of 2 player,
            // we create an early or challenger round (0) for them.
            $this->processChallengerRound( $seeded, $unseeded );
        }

        if( (count( $unseeded ) + count( $seeded )) > 0 ) throw new InvalidTournamentException( __( "Did not schedule all players into initial roounds." ) );

        return $this->event->numMatches();
    }

    /**
     * For this case, we have a large number of players involved in bringing the count
     * down to a power of 2 for the subsequent round. So this is considered the first round
     * and only a few byes need to be defined. It is likely that only seeded players will get the byes.
     */
    private function processFewByes( array &$seeded, array &$unseeded ) {
        //Add seeded players as Bye matches using an even distribution
        $slots = count( $seeded ) > 0 ? ceil( $this->event->drawSize() / count( $seeded ) ) : 0;
        
        $numInvolved = 2 * $this->numToEliminate;
        $remainder   = $this->event->drawSize() - $this->numInvolved;
        $highMatchnum    = ceil( $this->event->drawSize() / 2 );
        $lowMatchnum = 1;
        $matchnum = $highMatchnum;
        $usedMatchNums = array();
        $lastSlot = 0;
        
        $initalRound = 1;
        $seedByes    =  min( count( $seeded ) , $numInvolved - count( $unseeded ) );
        $unseedByes  = $remainder - $seedByes;

        for( $i = 0; $i < $seedByes; $i++ ) {
            if( $i === 0 ) {
                $matchnum = $lowMatchnum++;
                array_push( $usedMatchNums, $matchnum );
            }
            else if( $i === 1 ) {
                $matchnum = $highMatchnum--;
            }
            else {
                $lastSlot = ($i - 1) * $slots;
                $matchnum = $lowMatchnum + $lastSlot;
                array_push( $usedMatchNums, $matchnum );
            }
            $home = array_shift( $seeded );
            $match = new Match( $this->event->getID(), $initialRound, $matchnum );
            $match->setIsBye( true );
            $match->setHomeEntrant( $home );
            $this->event->addMatch( $match );
        }

        $matchnum = $lowMatchnum;
        for( $i = 0; $i < $unseedByes; $i++ ) {
            $home = array_shift( $unseeded );
            $match = new Match( $this->event->getID(), $initialRound, $matchnum++ );
            $match->setIsBye( true );
            $match->setHomeEntrant( $home );
            $this->event->addMatch( $match );
        }

        //Set the first lot of matches starting from end of the line
        $matchnum = $highMatchnum;
        while( count( $unseeded ) > 0 ) {
            if( count( $seeded ) > 0 ) {
                $home    = array_pop( $seeded );
                $visitor = array_pop( $unseeded );

                $lastSlot += $slot;
                array_push( $usedMatchNums, $lastSlot );
                
                $match   = new Match( $this->event->getID(), $initialRound, $lastSlot );
                $match->setHomeEntrant( $home );
                $match->setVisitorEntrant( $visitor );
                $this->event->addMatch( $match );                    
            }
            else {
                $home    = array_pop( $unseeded );
                $visitor = array_pop( $unseeded );

                $mn = $matchnum--;
                if( in_array( $mn, $usedMatchNums) ) $mn = $matchnum--;

                $match = new Match( $this->event->getID(), $initialRound, $mn );
                $match->setHomeEntrant( $home );

                if( isset( $visitor ) ) $match->setVisitorEntrant( $visitor );
                else $match->setIsBye( true );

                $this->event->addMatch( $match );
            }
        }
    }
    
    /**
     * There are many byes to create when the majority of unseeded players are involved in bringing the count for the next round to a power of 2. 
     * It is likely that byes will be created for all seeded players and many unseeded players.
     */
    private function processManyByes( array &$seeded, array &$unseeded ) {
        //Add seeded players as Bye matches using an even distribution
        $slots = count( $seeded ) > 0 ? ceil( $this->event->drawSize() / count( $seeded ) ) : 0;
        
        $numInvolved = 2 * $this->numToEliminate;
        $remainder   = $this->event->drawSize() - $this->numInvolved;
        $highMatchnum    = ceil( $this->event->drawSize() / 2 );
        $lowMatchnum = 1;
        $matchnum = $highMatchnum;
        $usedMatchNums = array();
        $lastSlot = 0;

        $initalRound = 1;
        $seedByes    = min( count( $seeded ), $remainder );
        $unseedByes  = $remainder - $seedByes;

        for( $i = 0; $i < $seedByes; $i++ ) {
            if( $i === 0 ) {
                $matchnum = $lowMatchnum++;
                array_push( $usedMatchNums, $matchnum );
            }
            else if( $i === 1 ) {
                $matchnum = $highMatchnum--;
            }
            else {
                $lastSlot = ($i - 1) * $slots;
                $matchnum = $lowMatchnum + $lastSlot;
                array_push( $usedMatchNums, $matchnum );
            }
            $home = array_shift( $seeded );
            $match = new Match( $this->event->getID(), $initialRound, $matchnum );
            $match->setIsBye( true );
            $match->setHomeEntrant( $home );
            $this->event->addMatch( $match );
        }

        $matchnum = $lowMatchnum;
        for( $i = 0; $i < $unseedByes; $i++ ) {
            $home = array_shift( $unseeded );
            $match = new Match( $this->event->getID(), $initialRound, $matchnum++ );
            $match->setIsBye( true );
            $match->setHomeEntrant( $home );
            $this->event->addMatch( $match );
        }

        //Set the first lot of matches starting from end of the line
        $matchnum = $highMatchnum;
        while( count( $unseeded ) > 0 ) {
            if( count( $seeded ) > 0 ) {
                $home    = array_pop( $seeded );
                $visitor = array_pop( $unseeded );

                $lastSlot += $slot;
                array_push( $usedMatchNums, $lastSlot );
                
                $match   = new Match( $this->event->getID(), $initialRound, $lastSlot );
                $match->setHomeEntrant( $home );
                $match->setVisitorEntrant( $visitor );
                $this->event->addMatch( $match );                    
            }
            else {
                $home    = array_pop( $unseeded );
                $visitor = array_pop( $unseeded );

                $mn = $matchnum--;
                if( in_array( $mn, $usedMatchNums) ) $mn = $matchnum--;

                $match = new Match( $this->event->getID(), $initialRound, $mn );
                $match->setHomeEntrant( $home );

                if( isset( $visitor ) ) $match->setVisitorEntrant( $visitor );
                else $match->setIsBye( true );

                $this->event->addMatch( $match );
            }
        }
    }

    /**
     * An advanced or "challenger" round is required when only a very few players are involved in order to bring 
     * the count down to a power of 2. There are no byes but the challenger round happens before round one of the tournament.
     */
    private function processChallengerRound( array &$seeded, array &$unseeded, int $seedByes = 0, int $unseedByes = 0, int $initialRound = 0 ) {
        //Add seeded players as Bye matches using an even distribution
        $slots = count( $seeded ) > 0 ? ceil( $this->event->drawSize() / count( $seeded ) ) : 0;
        
        $numInvolved = 2 * $this->numToEliminate;
        $remainder   = $this->event->drawSize() - $this->numInvolved;
        $highMatchnum    = ceil( $this->event->drawSize() / 2 );
        $lowMatchnum = 1;
        $matchnum = $highMatchnum;
        $usedMatchNums = array();
        $lastSlot = 0;
        
        $initialRound = 0; // early round to bring round 1 to a power of 2
        $seedByes     = 0;
        $unseedByes   = 0;

        //Create the challenger round
        for( $i = $numInvolved; $i > 0; $i -= 2 ) {
            $home    = array_pop( $unseeded );
            $visitor = array_pop( $unseeded );

            $match = new Match( $this->event->getID(), $initialRound, $matchnum-- );
            $match->setHomeEntrant( $home );
            $match->setVisitorEntrant( $visitor );
            $this->event->addMatch( $match );
        }
        
        //Now create the first round using all the remaining players
        ++$initialRound;
        $matchnum = $highMatchnum;
        while( count( $unseeded ) > 0 ) {
            if( count( $seeded ) > 0 ) {
                $home    = array_pop( $seeded );
                $visitor = array_pop( $unseeded );

                $lastSlot += $slot;
                array_push( $usedMatchNums, $lastSlot );
                
                $match   = new Match( $this->event->getID(), $initialRound, $lastSlot );
                $match->setHomeEntrant( $home );
                $match->setVisitorEntrant( $visitor );
                $this->event->addMatch( $match );                    
            }
            else {
                $home    = array_pop( $unseeded );
                $visitor = array_pop( $unseeded );

                $mn = $matchnum--;
                if( in_array( $mn, $usedMatchNums) ) $mn = $matchnum--;

                $match = new Match( $this->event->getID(), $initialRound, $mn );
                $match->setHomeEntrant( $home );

                //If not paired with a visitor then this match is waiting for
                // a winner from the challenger round. The opposite of a BYE.
                if( isset( $visitor ) ) $match->setVisitorEntrant( $visitor );

                $this->event->addMatch( $match );
            }
        }
    }

    private function sortBySeedDesc($a,$b) {
        if($a->getSeed() === $b->getSeed()) return 1; return ($a->getSeed() > $b->getSeed()) ? 1 : -1;
    }
    
    private function sortBySeedAsc($a,$b) {
        if($a->getSeed() === $b->getSeed()) return 1; return ($a->getSeed() < $b->getSeed()) ? 1 : -1;
    }
    
    private function sortByPositionAsc($a,$b) {
        if($a->getPosition() === $b->getPosition()) return 1; return ($a->getPosition() < $b->getPosition()) ? 1 : -1;
    }

    private function calculateEventSize() {
        $this->numToEliminate = 0;
        $this->numRounds = 0;

        if(!$this->event->isLeaf()) {
            $mess = __('Must be a leaf event to generate rounds.',TennisEvents::TEXTDOMAIN);
            throw new InvalidEventException($mess);
        }

        if($this->event->drawSize() < self::MINIMUM_ENTRANTS) {
            $min = self::MINIMUM_ENTRANTS;
            $mess = __("Event must have at least $min entrants for an elimination event.",TennisEvents::TEXTDOMAIN);
            throw new InvalidEventException($mess);
        }

        $this->numRounds = self::calculateExponent($this->event->drawSize());
        $this->$numToEliminate = $this->event->drawSize() - pow(2,$this->numRounds);

        return $this->numToEliminate;
    }

	private function calculateExponent(int $drawSize) {
        $exponent = 0;
        foreach(range(1,8) as $exp) {
            if(pow(2,$exp) > $drawSize) {
                $exponent = $exp - 1;
                break;
            }
        }
        return $exponent;
    }
}