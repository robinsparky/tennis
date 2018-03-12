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

    public const MINIMUM_ENTRANTS = 8; //minimum for an elimination tournament

    private const DECISION_POINT = 0.50;
    private $decision_table = array(.25,.5,.75);

    private $numToEliminate;
    private $numRounds;
    private $matchType;

    public function __construct(Event $evt, string $matchType = MatchType::MENS_SINGLES ) {
        $this->event = $evt;
        
        switch( $matchType ) {
            case MatchType::MENS_SINGLES:
            case MatchType::WOMENS_SINGLES:
            case MatchType::MENS_DOUBLES:
            case MatchType::WOMENS_DOUBLES:
            case MatchType::MIXED_DOUBLES:
                $this->matchType = $matchType;
                break;
            default:
                $this->matchType = MatchType::MENS_SINGLES;
        }
    }

    public function __destruct() {
        $this->event = null;
    }

    public function createBrackets() {
        $this->calculateEventSize();
        return $this->scheduleInitialRounds();
    }

    public function getEvent() {
        return $this->event;
    }

    public function save() {
        return $this->event->save();
    }

    /**
     * Traverse the Brackets to find
     * the first incomplete Round
     */
    public function getCurrentRound() {

    }

    public function showDraw() {
        if( !isset( $this->event) || $this->event->drawSize() < 1 ) {
            echo PHP_EOL . "Draw is empty";
        }
        else {
            $entrants = $this->event->getDraw();
            usort( $entrants, array( 'TournamentDirector','sortByPositionAsc' ) );
            foreach( $entrants as $ent ) {
                $seed = $ent->getSeed() > 0 ? '(' . $ent->getSeed() . ')' : ''; 
                $e = sprintf("%d. %s %s", $ent->getPosition(), $ent->getName(),$seed);
                echo PHP_EOL . $e;
            }
        }
    }

    public function showMatches( int $round = 1) {
        $matches = $this->event->getMatches();
        if( count( $matches ) < 1 ) {
            echo PHP_EOL . "No matches defined.";
        }
        else {
            usort($matches, array( 'TournamentDirector', 'sortByMatchNumberAsc' ) );
            echo PHP_EOL . "Round $round";
            foreach( $matches as $match ) {
                if( $round === $match->getRoundNumber() ) {
                    $mn = $match->getMatchNumber();
                    $home = $match->getHomeEntrant();
                    if(!isset($home)) throw new Exception("Really!");
                    $hid = $home->getPosition();
                    $hname = $home->getName();
                    $visitor = $match->getVisitorEntrant();
                    $vname = isset($visitor) ? $visitor->getName() : 'tba';
                    $vid = isset($visitor) ? $visitor->getPosition() : '0';
                    if($match->isBye() ) {
                        echo PHP_EOL . "Match($mn): Home($hid)='$hname' has Bye ";
                    }
                    else {
                        echo PHP_EOL . "Match($mn): Visitor($vid)='$vname' vs Home($hid)='$hname'  ";
                    }
                }
            }
        }
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
        $unseeded = array_filter(array_map( function( $e ) { if( $e->getSeed() < 1 ) return $e; }, $entrants ));
        
        if( $randomizeDraw ) shuffle( $unseeded );
        else usort( $unseeded, array( 'TournamentDirector', 'sortByPositionAsc' ) );

        $seeded = array_filter(array_map( function( $e ) { if( $e->getSeed() > 0 ) return $e; }, $entrants ));
        usort( $seeded, array( 'TournamentDirector', 'sortBySeedAsc') );

        $numInvolved = 2 * $this->numToEliminate;
        $remainder   = $numInvolved > 0 ? $this->event->drawSize() - $numInvolved : 0;

        //Few Byes
        if( $numInvolved <= $this->decision_table[0] * count( $entrants ) ) {
            //For this case it is clear that there are so many players requred to be elminated 
            // (so that tne next round has a power of 2 players) that it has to be treated as round 1
            // and a few players (probably mostly seeds) get a bye into round 2.
            $this->processChallengerRound( $seeded, $unseeded );
        }
        //Many Byes
        else if( $numInvolved > $this->decision_table[0] * count( $entrants ) 
                && $numInvolved <= $this->decision_table[1] * count( $entrants )) {
            //In this case we have the majority of the unseeded plyaers (but less than 100%) to be eliminated to arrive at power of 2.
            // So again we treat this as the first round but now there may be many more byes into the second round.
            // Many unseeded players will get byes and probably all of the seeds.
            $this->processChallengerRound( $seeded, $unseeded );
        }
        //Many Byes
        else if( $numInvolved > $this->decision_table[1] * count( $entrants ) 
                && $numInvolved <= $this->decision_table[2] * count( $entrants )) {
            //In this case we have the majority of the unseeded plyaers (but less than 100%) to be eliminated to arrive at power of 2.
            // So again we treat this as the first round but now there may be many more byes into the second round.
            // Many unseeded players will get byes and probably all of the seeds.
            $this->processByes( $seeded, $unseeded );
        }
        else {
            //With less than 50% of the unseeded players to be eliminated in order to create a next round with power of 2 player,
            // we create an early or challenger round (0) for them.
            $this->processByes( $seeded, $unseeded );
        }

        if( (count( $unseeded ) + count( $seeded )) > 0 ) throw new InvalidTournamentException( __( "Did not schedule all players into initial roounds." ) );

        return $this->event->numMatches();
    }

    /**
     * For this case, we have a large number of players involved in bringing the count
     * down to a power of 2 for the subsequent round. So this is considered the first round
     * and only a few byes need to be defined. It is likely that only seeded players will get the byes.
     */
    private function processByes( array &$seeded, array &$unseeded ) {
        //Add seeded players as Bye matches using an even distribution
        //$slot = count( $seeded ) > 0 ? floor( $this->event->drawSize() / count( $seeded ) ) : 0;
        //$slot = count ( $seeded ) > 0 ? floor( ceil( $this->event->drawSize() / 2.0 ) / count( $seeded ) ) : 0;
        $numInvolved = 2 * $this->numToEliminate;
        $remainder   = $numInvolved > 0 ? $this->event->drawSize() - $numInvolved : 0;
        $highMatchnum = ceil( $this->event->drawSize() / 2 );
        $lowMatchnum = 1;
        $matchnum = $lowMatchnum;
        $usedMatchNums = array();
        $lastSlot = 0;
        
        $initialRound = 1;
        $seedByes = 0;
        $unseedByes = 0;
        $seedByes    =  min( count( $seeded ) , $remainder );
        $unseedByes  = $remainder - $seedByes;

        // if( 0 === $remainder ) {
        //     $seedByes = $unseedByes = 0;
        // }
        $slot = ($seedByes + $unseedByes) > 0 ? ceil( ceil( $this->event->drawSize() / 2.0 ) / ($seedByes + $unseedByes) ) : 0;
        $slot = max( 1, $slot );
        error_log("TournamentDirector.processFewByes: seeds=" . count($seeded) . '; unseeded='.count($unseeded));
        error_log("TournamentDirector.processFewByes...slot=$slot; numInvolved=$numInvolved; remainder=$remainder; highMatchnum=$highMatchnum; seedByes=$seedByes; unseedByes=$unseedByes");

        for( $i = 0; $i < $seedByes; $i++ ) {
            if( 0 === $i ) {
                $matchnum = $lowMatchnum++;
                array_push( $usedMatchNums, $matchnum );
            }
            else if( 1 === $i ) {
                $matchnum = $highMatchnum--;
                array_push( $usedMatchNums, $matchnum );
            }
            else {
                $lastSlot += $slot;
                $matchnum = $lowMatchnum + $lastSlot;
                array_push( $usedMatchNums, $matchnum );
            }
            $home = array_shift( $seeded );
            $match = new Match( $this->event->getID(), $initialRound, $matchnum );
            $match->setIsBye( true );
            $match->setHomeEntrant( $home );
            $match->setMatchType( $this->matchType );
            $this->event->addMatch( $match );
        }

        for( $i = 0; $i < $unseedByes; $i++ ) {
            $home = array_shift( $unseeded );
            $lastSlot += $slot;
            $matchnum = $lastSlot;
            array_push( $usedMatchNums, $matchnum );
            $match = new Match( $this->event->getID(), $initialRound, $matchnum++ );
            $match->setIsBye( true );
            $match->setHomeEntrant( $home );
            $match->setMatchType( $this->matchType );
            $this->event->addMatch( $match );
        }

        //Set the first lot of matches starting from end of the line
        $matchnum = $lowMatchnum;
        while( count( $unseeded ) > 0 ) {
            if( count( $seeded ) > 0 ) {
                $home    = array_shift( $seeded );
                $visitor = array_shift( $unseeded );

                $lastSlot += $slot;
                array_push( $usedMatchNums, $lastSlot );
                
                $match   = new Match( $this->event->getID(), $initialRound, $lastSlot );
                $match->setHomeEntrant( $home );
                $match->setVisitorEntrant( $visitor );
                $match->setMatchType( $this->matchType );
                $this->event->addMatch( $match );                    
            }
            else {
                $home    = array_shift( $unseeded );
                $visitor = array_shift( $unseeded );

                $mn = $matchnum++;
                $mn = $this->getNextAvailable( $usedMatchNums, $mn );
                array_push( $usedMatchNums, $mn );

                $match = new Match( $this->event->getID(), $initialRound, $mn );
                $match->setHomeEntrant( $home );

                if( isset( $visitor ) ) $match->setVisitorEntrant( $visitor );
                else $match->setIsBye( true );

                $match->setMatchType( $this->matchType );
                $this->event->addMatch( $match );
            }
        }
    }

    /**
     * An advanced or "challenger" round is required when only a very few players are involved in order to bring 
     * the count down to a power of 2. There are no byes but the challenger round happens before round one of the tournament.
     */
    private function processChallengerRound( array &$seeded, array &$unseeded ) {
        //Add seeded players as Bye matches using an even distribution
        $slot = count( $seeded ) > 0 ? floor( $this->event->drawSize() / count( $seeded ) ) : 0;
        
        $numInvolved = 2 * $this->numToEliminate;
        $remainder   = $this->event->drawSize() - $numInvolved;
        $highMatchnum    = ceil( $this->event->drawSize() / 2 );
        $lowMatchnum = 1;
        $matchnum = $highMatchnum;
        $usedMatchNums = array();
        $lastSlot = 0;
        
        $initialRound = 0; // early round to bring round 1 to a power of 2
        $seedByes     = 0;
        $unseedByes   = 0;

        error_log("TournamentDirector.processChallengerRound: seeds=" . count($seeded) . '; unseeded='.count($unseeded));
        error_log("TournamentDirector.processChallengerRound...slot=$slot; numInvolved=$numInvolved; remainder=$remainder; highMatchnum=$highMatchnum; seedByes=$seedByes; unseedByes=$unseedByes");

        //Create the challenger round using unseeded players from end of list
        for( $i = $numInvolved; $i > 0; $i -= 2 ) {
            $home    = array_pop( $unseeded );
            $visitor = array_pop( $unseeded );

            $match = new Match( $this->event->getID(), $initialRound, $matchnum-- );
            $match->setHomeEntrant( $home );
            $match->setVisitorEntrant( $visitor );
            $match->setMatchType( $this->matchType );
            $this->event->addMatch( $match );
        }
        
        //Now create the first round using all the remaining players
        ++$initialRound;
        $matchnum = $highMatchnum;
        while( count( $unseeded ) > 0 || count( $seeded ) > 0 ) {
            if( count( $seeded ) > 0 ) {
                $home    = array_pop( $seeded );
                $visitor = array_pop( $unseeded );
                if( !isset( $visitor ) ) $visitor = array_pop( $seeded );

                $lastSlot += $slot;
                array_push( $usedMatchNums, $lastSlot );
                
                $match = new Match( $this->event->getID(), $initialRound, $lastSlot );
                $match->setHomeEntrant( $home );
                //If not paired with a visitor then this match is waiting for
                // a winner from the challenger round. The opposite of a BYE.
                if( isset( $visitor ) ) $match->setVisitorEntrant( $visitor );
                $match->setMatchType( $this->matchType );
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

                $match->setMatchType( $this->matchType );
                $this->event->addMatch( $match );
            }
        }
    }
    /**
     * Sort Draw by seeding in descending order
     */
    private function sortBySeedDesc($a,$b) {
        if($a->getSeed() === $b->getSeed()) return 0; return ($a->getSeed() < $b->getSeed()) ? 1 : -1;
    }
    
    /**
     * Sort Draw bye seeding in ascending order
     */
    private function sortBySeedAsc($a,$b) {
        if($a->getSeed() === $b->getSeed()) return 0; return ($a->getSeed() < $b->getSeed()) ? -1 : 1;
    }
    
    /**
     * Sort Draw by position in asending order
     */
    private function sortByPositionAsc($a,$b) {
        if($a->getPosition() === $b->getPosition()) return 0; return ($a->getPosition() < $b->getPosition()) ? -1 : 1;
    }
    /**
     * Sort matches by match number in ascending order
     */
	private function sortByMatchNumberAsc( $a, $b ) {
		if($a->getMatchNumber() === $b->getMatchNumber()) return 0; return ($a->getMatchNumber() < $b->getMatchNumber()) ? -1 : 1;
	}

    /**
     * Retunrs the next available integer that is not in the given array of integers
     * @param $haystack the array to search
     * @param $needle the integer starting point which will be returned if not in the array
     */
    private function getNextAvailable( array &$haystack, int $needle ):int {
        if( in_array( $needle, $haystack ) ) {
            return $this->getNextAvailable( $haystack, ++$needle );
        }
        else {
            return $needle;
        }
    }
    
    

    private function calculateEventSize() {
        $this->numToEliminate = 0;
        $this->numRounds = 0;

        if(!$this->event->isLeaf()) {
            $mess = __( 'Must be a leaf event to generate rounds.', TennisEvents::TEXT_DOMAIN );
            throw new InvalidEventException($mess);
        }

        if($this->event->drawSize() < self::MINIMUM_ENTRANTS) {
            $min = self::MINIMUM_ENTRANTS;
            $mess = __( "Event must have at least $min entrants for an elimination event.", TennisEvents::TEXT_DOMAIN );
            throw new InvalidEventException($mess);
        }

        $this->numRounds = self::calculateExponent($this->event->drawSize());
        $this->numToEliminate = $this->event->drawSize() - pow(2,$this->numRounds);

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