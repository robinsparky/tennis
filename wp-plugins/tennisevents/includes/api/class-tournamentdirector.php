<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$p2Dir = plugin_dir_path( plugin_dir_path( __DIR__ ) );
require_once( $p2Dir . 'tennisevents.php' );
require_once( 'api-exceptions.php' );

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

    public function __construct( Event $evt, string $matchType = MatchType::MENS_SINGLES ) {
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

    public function createBrackets( $randomizeDraw = false ) {
        $this->calculateEventSize();
        return $this->scheduleInitialRounds( $randomizeDraw );
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
                    $hid = isset( $home ) ? $home->getPosition() : '0';
                    $hname = isset( $home ) ? $home->getName() : 'tba';
                    $visitor = $match->getVisitorEntrant();
                    $vid = isset($visitor) ? $visitor->getPosition() : '0';
                    $vname = isset($visitor) ? $visitor->getName() : 'tba';
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
     * or to have "byes" from round 1 into round 2.
     * The next big question to work out is determining who gets the byes (if any).
     * Finally the seeded players (who get priority for bye selection) must be distributed
     * evenly amoung the un-seeded players with the first and second seeds being at opposite ends of the draw.
     */
    private function scheduleInitialRounds( $randomizeDraw = false ) {
        //$entrants = $this->event->distributeSeededPlayers( $randomizeDraw );
        $entrants = $this->event->getDraw();
        $unseeded = array_filter( array_map( function( $e ) { if( $e->getSeed() < 1 ) return $e; }, $entrants ) );
        
        if( $randomizeDraw ) shuffle( $unseeded );
        else usort( $unseeded, array( 'TournamentDirector', 'sortByPositionAsc' ) );

        $seeded = array_filter( array_map( function( $e ) { if( $e->getSeed() > 0 ) return $e; }, $entrants ) );
        usort( $seeded, array( 'TournamentDirector', 'sortBySeedAsc') );

        $numInvolved = 2 * $this->numToEliminate;
        $remainder   = $numInvolved > 0 ? $this->event->drawSize() - $numInvolved : 0;

        if($numInvolved > $remainder ) {
            $seedByes    =  min( count( $seeded ) , $remainder );
            $unseedByes  = $remainder - $seedByes;
        }
        else {
            $seedByes = min( count( $seeded ), $numInvolved );
            $unseedByes = $numInvolved - $seedByes;
        }
        $totalByes = $seedByes + $unseedByes;
        $highMatchnum = ceil( $this->event->drawSize() / 2 );
        error_log("TournamentDirector.scheduleInitialRounds:highMatchnum=$highMatchnum seedByes=$seedByes unseedByes=$unseedByes");
        if( $this->numToEliminate < 3 ) {
            $this->processChallengerRound( $seeded, $unseeded );
        }
        else {
            $this->processByes( $seeded, $unseeded );
        }

        if( (count( $unseeded ) + count( $seeded )) > 0 ) throw new InvalidTournamentException( __( "Did not schedule all players into initial roounds." ) );

        return $this->event->numMatches();
    }

    /**
     * For this case, we could have a large number of players involved in bringing the count
     * down to a power of 2 for the subsequent round. So this is considered the first round
     * and only a few byes need to be defined. It is likely that only seeded players will get the byes.
     */
    private function processByes( array &$seeded, array &$unseeded ) {
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

        //Add seeded players as Bye matches using an even distribution
        $slot = ($seedByes + $unseedByes) > 1 ? ceil( ceil( $this->event->drawSize() / 2.0 ) / ($seedByes + $unseedByes) ) : 0;
        $slot = max( 1, $slot );
        error_log("TournamentDirector.processByes: seeds=" . count($seeded) . '; unseeded='.count($unseeded));
        error_log("TournamentDirector.processByes...slot=$slot; numInvolved=$numInvolved; remainder=$remainder; highMatchnum=$highMatchnum; seedByes=$seedByes; unseedByes=$unseedByes");

        for( $i = 0; $i < $seedByes; $i++ ) {
            if( 0 === $i ) {
                $matchnum = $lowMatchnum++;
                array_push( $usedMatchNums, $matchnum );
            }
            else if( 1 === $i ) {
                $matchnum = 2*$highMatchnum--;
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
            $lastSlot = $this->getNextAvailable( $usedMatchNums, $lastSlot );
            array_push( $usedMatchNums, $lastSlot );
            $match = new Match( $this->event->getID(), $initialRound, $lastSlot );
            $match->setIsBye( true );
            $match->setHomeEntrant( $home );
            $match->setMatchType( $this->matchType );
            $this->event->addMatch( $match );
        }

        //Set the first lot of matches starting from end of the line
        $matchnum = $lowMatchnum;
        while( count( $unseeded ) > 0 || count( $seeded ) > 0 ) {
            if( count( $seeded ) > 0 ) {
                $home    = array_shift( $seeded );
                $visitor = array_shift( $unseeded );

                $lastSlot += $slot;
                $lastSlot = $this->getNextAvailable( $usedMatchNums, $lastSlot );
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
        
        $numInvolved = 2 * $this->numToEliminate;
        $remainder   = $this->event->drawSize() - $numInvolved;
        $highMatchnum    = ceil( $this->event->drawSize() / 2 );
        $lowMatchnum = 1;
        $matchnum = $lowMatchnum;
        $usedMatchNums = array();
        $lastSlot = 0;
        
        $initialRound = 0; // early round to bring round 1 to a power of 2
        $seedByes     = 0;
        $unseedByes   = 0;

        //Add seeded players as Bye matches using an even distribution
        $slot = ($seedByes + $unseedByes) > 1 ? ceil( ceil( $this->event->drawSize() / 2.0 ) / ($seedByes + $unseedByes) ) : 0;
        $slot = max( 1, $slot );

        error_log("TournamentDirector.processChallengerRound: seeds=" . count($seeded) . '; unseeded='.count($unseeded));
        error_log("TournamentDirector.processChallengerRound...slot=$slot; numInvolved=$numInvolved; remainder=$remainder; highMatchnum=$highMatchnum; seedByes=$seedByes; unseedByes=$unseedByes");

        //Create the challenger round using unseeded players from end of list
        //Note that $numInvolved is always an even number
        for( $i = $numInvolved; $i > 0; $i -= 2 ) {
            $home    = array_pop( $unseeded );
            $visitor = array_pop( $unseeded );

            $match = new Match( $this->event->getID(), $initialRound, $matchnum++ );
            $match->setHomeEntrant( $home );
            $match->setVisitorEntrant( $visitor );
            $match->setMatchType( $this->matchType );
            $this->event->addMatch( $match );
        }
        
        ++$initialRound;
        $matchnum = 1;
        //Schedule the odd player to wait for the winner of a challenger round
        if( (1 & $remainder) ) {
            $home = array_shift( $unseeded );
            if( !isset( $home ) ) $home = array_shift( $seeded );
            if( isset( $home ) ) {
                $mn = $matchnum++;
                $mn = $this->getNextAvailable( $usedMatchNums, $mn );
                array_push( $usedMatchNums, $mn );
                $match = new Match( $this->event->getID(), $initialRound, $mn );
                $match->setHomeEntrant( $home );
                $match->setMatchType( $this->matchType );
                $this->event->addMatch( $match );
            }
        }

        //Now create the first round using all the remaining players
        // and there must be an even number of them
        while( count( $unseeded ) > 0 || count( $seeded ) > 0 ) {
            if( count( $seeded ) > 0 ) {
                $home    = array_shift( $seeded );
                $visitor = array_shift( $unseeded );
                if( !isset( $visitor ) ) $visitor = array_shift( $seeded );

                $lastSlot += $slot;
                $lastSlot = $this->getNextAvailable( $usedMatchNums, $lastSlot );
                array_push( $usedMatchNums, $lastSlot );
                
                $match = new Match( $this->event->getID(), $initialRound, $lastSlot );
                $match->setHomeEntrant( $home );
                
                if( isset( $visitor ) ) $match->setVisitorEntrant( $visitor );
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
    
    /**
     * This function calculates how many players must be "eliminated"
     * in order to bring either the first or second round 
     * down to a size which is a power of 2
     */
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

    /**
     * Given the size of the draw calculate the highest 
     * power of 2 which is less than that size
     */
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