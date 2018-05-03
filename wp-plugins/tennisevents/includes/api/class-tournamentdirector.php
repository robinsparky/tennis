<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$p2Dir = plugin_dir_path( plugin_dir_path( __DIR__ ) );
require_once( $p2Dir . 'tennisevents.php' );
require_once( 'api-exceptions.php' );

/** 
 * Responsible for putting together the necessary Events and schedule for a Tournament
 * Calculates the inital rounds of tournament using either byes or challenger round
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

    public const MINIMUM_ENTRANTS = 8; //minimum for an elimination tournament

    private $numToEliminate = 0; //The number of players to eliminate to result in a power of 2
    private $numRounds = 0; //Total number of rounds for this tournament; calculated based on signup
    private $hasChallengerRound = false; //Is a challenger round required
    private $matchType; //The type of match such as mens singles, womens doubles etc
    private $name = '';
    
    //An array of doubly linked lists
    // where each list is the full path for each Match in the tournament
    private $adjacencyMatrix = array();

    //The ChairUmpire for this tournament
    private $chairUmpire;

    
    /**************************************************  Public functions ********************************************************** */

    public function __construct( Event $evt, string $matchType = MatchType::MENS_SINGLES ) {
        $this->event = $evt;
        
        switch( $matchType ) {
            case MatchType::MENS_SINGLES:
                $this->matchType = $matchType;
                $this->name = self::MENSINGLES;
                break;
            case MatchType::WOMENS_SINGLES:
                $this->matchType = $matchType;
                $this->name = self::WOMENSINGLES;
                break;
            case MatchType::MENS_DOUBLES:
                $this->matchType = $matchType;
                $this->name = self::MENSDOUBLES;
                break;
            case MatchType::WOMENS_DOUBLES:
                $this->matchType = $matchType;
                $this->name = self::WOMENSDOUBLES;
                break;
            case MatchType::MIXED_DOUBLES:
                $this->matchType = $matchType;
                $this->name = self::MIXEDDOUBLES;
                break;
            default:
                $this->matchType = MatchType::MENS_SINGLES;
                $this->name = self::MENSINGLES;
                break;
        }
    }

    public function __destruct() {
        $this->event = null;
    }

    /**
     * Get the name of the tournament
     */
    public function tournamentName() {
        return $this->name;
    }

    /**
     * Get the event for this tournament
     */
    public function getEvent() {
        return $this->event;
    }

    /**
     * Get the Match Type for this tournament
     */
    public function matchType():float {
        return $this->matchType;
    }

    /**
     * Get the ChairUmpire for match controlled by this Touornament Director
     * @param $scoretype A bitmask of scoring features
     * @see ScoreType class
     * @return ChairUmpire subclass
     */
    public function getChairUmpire( int $scoretype = 0 ):ChairUmpire {
        if( ($scoretype & ScoreType::NoAd) && ($scoretype & ScoreType::TieBreakAt3) ) {
            $chairUmpire = Fast4Umpire::getInstance();
        }
        elseif( $scoretype & ScoreType::TieBreakDecider ) {
            $chairUmpire = MatchTieBreakUmpire::getInstance();
        }
        elseif( !($scoretype & ScoreType::TieBreakDecider) && ($scoretype & ScoreType::TieBreak12Pt) ) {
            $chairUmpire = ProSetUmpire::getInstance();
        }
        else {
            $chairUmpire = RegulationMatchUmpire::getInstance();
            if($scoretype & ScoreType::TieBreakAt3 ) {
                $chairUmpire->setMaxSets( 3 );
            }
        }
        return $chairUmpire;
    }

    /**
     * Get ths signup for this tournament sorted by position in the draw
     */
    public function getDraw() {
        $entrants = isset( $this->event ) ? $this->event->getDraw() : array();
        usort( $entrants, array( 'TournamentDirector', 'sortByPositionAsc' ) );

        return $entrants;
    }

    /**
     * Approve the generated and possibly modified brackets.
     * This causes the brackets to be fleshed to the final round
     * with placeholder matches.
     * For double elimination, the following rules apply:
     *   1. A separate Losers bracket is used
     *   2. The Losers bracket must be approved too.
     *   3. Approval should happen after appropriate modifications
     *      have been made; such as moving the prelimary matches around.
     *  ...
     * Once approved, the brackets cannot be modified, only deleted.
     */
    public function approve() {

        $loc = __CLASS__ . "::" . __FUNCTION__;

        error_log( sprintf(" >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>%s>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>", $loc) );
        /* 
         * First we set the "next pointers" of the preliminary matches to their next matches
        */
        $earlyMatches = $this->getMatches( 0 );
        $round1Matches = $this->getMatches( 1 );
        //$maxRound1MatchNum = $this->event->maxMatchNumber( 1 );

        
        // Note: There should be no match in round 1 with the same match number as a round 0 match
        // This makes it easy to point round 0 matches to round 1 without affecting existing round 1 matches
    
        //If we have odd number of challengers then link the 
        // last one to the first round 1 match but only if it is waiting
        if( count( $earlyMatches ) & 1 ) {
            if( !$round1Matches[0]->isWaiting() ) {
                $nextMatchNum = $round1Matches[0]->getMatchNumber();
                $m = array_pop( $earlyMatches );
                $m->setNextMatchNumber( $nextMatchNum );
                $m->setNextRoundNumber( 1 );
                error_log(sprintf("%s -> linked the last match %s from challengers to round 1 match %d", $loc, $m->toString(), $nextMatchNum ) );
            }
            else {
                $mess = sprintf( "Match was last of odd numbered challengers but does not have waiting match in round 1:%s - %s"
                               , $round1Matches[0]->toString(), $round1Matches[0]->getHomeEntrant()->getName() );
                throw new InvalidTournamentException( $mess );
            }
        }

        //At this point there must be an even number of challenger matches
        $ctr = 1;
        while( count( $earlyMatches ) > 0 ) {
            $nextMatchNum = $ctr++;
            $m1 = array_shift( $earlyMatches );
            $m1->setNextMatchNumber( $nextMatchNum );
            $m1->setNextRoundNumber( 1 );
            error_log(sprintf("%s -> linked %s to round 1 match %d", $loc, $m1->toString(), $nextMatchNum ) );

            $m2 = array_shift( $earlyMatches );

            if( is_null( $m2 ) ) {
                throw new InvalidTournamentException( sprintf( "%s Unexpectedly encountered odd number of challengers", $loc ) );
            }

            $m2->setNextMatchNumber( $nextMatchNum );
            $m2->setNextRoundNumber( 1 );
            error_log(sprintf("%s -> linked %s to round 1 match %d", $loc, $m2->toString(), $nextMatchNum ) );
        }

        //Round 1 could have odd number of matches
        while( count( $round1Matches ) > 0 ) {
            $nextMatchNum = $ctr++;

            $m1 = array_shift( $round1Matches );
            $m1->setNextMatchNumber( $nextMatchNum );
            $m1->setNextRoundNumber( 2 );

            $bye = $m1->isBye() ? 'bye' : '';
            error_log(sprintf("%s -> linked %s (%s) to round 2 match %d", $loc, $m1->toString(), $bye, $nextMatchNum ) );
            
            if( count( $earlyMatches ) > 0 && $m1->isWaiting() ) {
                continue;
            }

            $m2 = array_shift( $round1Matches );

            if( is_null( $m2 ) ) break;

            $m2->setNextRoundNumber( 2 );
            $m2->setNextMatchNumber( $nextMatchNum );

            $bye = $m2->isBye() ? 'bye' : '';
            error_log(sprintf("%s -> linked %s (%s) to round 2 match %d", $loc, $m2->toString(), $bye, $nextMatchNum ) );
        }

        $this->fillLinkedList();

        $this->save();
        
        error_log( sprintf( "<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<%s<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<", $loc) );

    }

    /**
     * Advance completed matches to their respective next rounds.
     * Implies that players/teams are filled in the placeholder 
     * matches created by the approve function.
     */
    public function advance() {
        $loc = __CLASS__ . "::" . __FUNCTION__;

    }

    private function fillLinkedList() {
        $loc = __CLASS__ . "::" . __FUNCTION__;

        $matches = $this->getMatches();        
        usort( $matches, array( __CLASS__, 'sortByRoundMatchNumberAsc' ) );

        foreach( $matches as $match ) {
            $dlist = $this->findListFor( $match->getRoundNumber(), $match->getMatchNumber() );
            if( is_null( $dlist ) ) {
                $dlist = new SplDoublyLinkedList();
                $this->adjacencyMatrix[] = $dlist;
            }

            $cur = new stdClass;
            $cur->round_num = $match->getRoundNumber();
            $cur->match_num = $match->getMatchNumber();
            $cur->next_round_num = $match->getNextRoundNumber();
            $cur->next_match_num = $match->getNextMatchNumber();
            if( 1 === $match->getRoundNumber() && $dlist->count() === 0 ) {
                $dummy = new stdClass;
                $dummy->round_num = 0;
                $dummy->match_num = 0;
                $dummy->next_round_num = $match->getRoundNumber();
                $dummy->next_match_num = $match->getMatchNumber();
                $dlist->push( $dummy );
            }
            $dlist->push( $cur );
            error_log(sprintf("%s -> Pushed Cur(%d,%d->%d,%d) onto list from %s", $loc, $cur->round_num, $cur->match_num,$cur->next_round_num,$cur->next_match_num, $match->toString() ) );
        }

        foreach( $this->adjacencyMatrix as $dlist ) {
            $nm = (int)$dlist->top()->next_match_num;
            for( $r = (int)$dlist->top()->next_round_num; $r <= $this->totalRounds() + 1; $r++ ) {
                $cur = new stdClass;
                $cur->round_num = $r;
                $cur->match_num = $nm;
                if( $r === ($this->totalRounds() + 1 ) ) {
                    $cur->next_round_num = 0;
                    $cur->next_match_num = 0;
                }
                else {
                    $cur->next_round_num = $cur->round_num + 1;
                    $cur->next_match_num = $nm - ceil( ( $nm - 1 ) / 2 );
                }
                $nm = $cur->next_match_num;
                $dlist->push( $cur );
                error_log(sprintf("%s -> Pushed Cur(%d,%d->%d,%d) onto list", $loc, $cur->round_num, $cur->match_num,$cur->next_round_num, $cur->next_match_num ) );
            }
        }
    }

    public function strAdjacencyMatrix() {
        $result = array();
        foreach( $this->adjacencyMatrix as $dlist ) {
            $dlist->setIteratorMode( SplDoublyLinkedList::IT_MODE_FIFO );
            $str = '';
            for( $dlist->rewind(); $dlist->valid(); $dlist->next() ) {
                $str .= sprintf( "%d:M(%d,%d)->M(%d,%d) | "
                              , $dlist->key(), $dlist->current()->round_num, $dlist->current()->match_num, $dlist->current()->next_round_num, $dlist->current()->next_match_num );
            }
            $result[] = $str;
        }
        return $result;
    }

    public function getAdjacencyMatrix() {
        return $this->adjacencyMatrix;
    }

    /**
     * Find the linked list for the given round and match numbers
     * If current or next matches the list is returned
     */
    private function findListFor( int $r, int $m ) {
        $result = null;
        foreach( $this->adjacencyMatrix as $dlist ) {
            $dlist->setIteratorMode( SplDoublyLinkedList::IT_MODE_FIFO );
            for( $dlist->rewind(); $dlist->valid(); $dlist->next() ) {
                if( ( $dlist->current()->round_num === $r ) &&  ( $dlist->current()->match_num === $m ) 
                ||  ( $dlist->current()->next_round_num === $r ) &&  ( $dlist->current()->next_match_num === $m)) {
                    $dlist->rewind();
                    $result = $dlist;
                    break;
                }
            }
        }
        return $result;
    }

    /**
     * Save the results from addEntrant, etc.
     * Calls save on the underlying Event.
     * NOTE: if significant changes are made to the underlying Event
     *       one should save these earlier.
     */
    public function save() {
        return $this->event->save();
    }

    /**
     * Traverse the Brackets to find
     * the first incomplete Round
     */
    public function currentRound():int {

        $loc = __CLASS__ . "::" . __FUNCTION__;
        
        $currentRound = -1;
        $umpire = $this->getChairUmpire();
        for($i = 0; $i < $this->totalRounds(); $i++ ) {
            foreach( $this->getMatches( $i ) as $match ) {
                $status = $umpire->matchStatus( $match );
                // $mess = sprintf( "%s(%s) -> i=%d status='%s'"
                //                , $loc, $match->toString()
                //                , $i, $status );
                // error_log( $mess );
                if( $status === ChairUmpire::NOTSTARTED || $status === ChairUmpire::INPROGRESS ) {
                    $currentRound = $i;
                    break;
                }
            }
            if( $currentRound !== -1 ) break;
        }
        $mess = sprintf( "%s->returning current round=%d", $loc, $currentRound );
        error_log( $mess );
        return $currentRound;
    }

    public function hasStarted() {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        
        $started = false;
        $umpire = $this->getChairUmpire();
        foreach( $this->getMatches() as $match ) {
            $status = $umpire->matchStatus( $match );
            // $mess = sprintf( "%s(%s) -> status='%s'"
            //                 , $loc, $match->toString()
            //                 , $status );
            // error_log( $mess );
            if( $status != ChairUmpire::NOTSTARTED && $status != ChairUmpire::BYE && $status != ChairUmpire::WAITING ) {
                $started = true;
                break;
            }
        }

        $mess = sprintf( "%s->returning started=%d", $loc, $started );
        error_log( $mess );
        return $started;
    }

    /**
     * The total number of rounds for this tennis event.
     * Including a challenger round if needed.
     */
    public function totalRounds( $incChallenger = true ) {
        if( isset( $this->event ) ) {
            $this->numRounds = $this->calculateExponent( $this->event->drawSize() );
            if( $incChallenger && $this->hasChallengerRound() ) ++$this->numRounds;
        }
        return $this->numRounds;
    }

    public function hasChallengerRound() {
        return $this->hasChallengerRound;
    }

    /**
     * Return the size of the signup for the tennis event
     */
    public function drawSize() {
        return isset( $this->event ) ? $this->event->drawSize() : 0;
    }

    /**
     * Remove the signup and all defined matches for a tennis event
     */
    public function removeDraw() {
        $result = 0;
        if( $this->hasStarted() ) {
            throw new InvalidTournamentException( "Cannot remove draw because tournament has started." );
        }
        if( isset( $this->event ) ) {
            $this->event->removeDraw();
            $result = $this->event->save();
        }
        return $result;
    }

    /**
     * Remove all defined matches for a tennis event
     */
    public function removeBrackets( $force = false ) {
        $result = 0;
        if( !$force && $this->hasStarted() ) {
            throw new InvalidTournamentException( "Cannot remove brackets because tournament has started." );
        }
        if( isset( $this->event ) ) {
            $this->event->removeAllMatches();
            $result = $this->event->save();
        }
        return $result;
    }


    /**
     * Return all matches for an event or just for a given round
     * @param $round The round whose matches are to be retrieved
     */
    public function getMatches( $round = null ) {
        $matches = array();
        if( isset( $this->event ) ) {
            if( is_null( $round ) ) {
                $matches = $this->event->getMatches();
                usort( $matches, array( 'TournamentDirector', 'sortByRoundMatchNumberAsc' ) );
            }
            else {
                $matches = $this->event->getMatchesByRound( $round );
                usort( $matches, array( 'TournamentDirector', 'sortByMatchNumberAsc' ) );
            }
        }
        return $matches;
    }
    
    /**
     * Return the count of all matches or just for a given round
     * @param $round The round whose matches are to be counted
     */
    public function getMatchCount( $round = null ) {
        $matches = array();
        if( isset( $this->event ) ) {
            if( is_null( $round ) ) {
                $matches = $this->event->getMatches();
            }
            else {
                $matches = $this->event->getMatchesByRound( $round );
            }
        }
        return count( $matches );
    }

    public function showDraw() {
        if( !isset( $this->event) || $this->event->drawSize() < 1 ) {
            echo PHP_EOL . "Draw is empty";
        }
        else {
            $entrants = $this->event->getDraw();
            usort( $entrants, array( 'TournamentDirector', 'sortByPositionAsc' ) );
            foreach( $entrants as $ent ) {
                $seed = $ent->getSeed() > 0 ? '(' . $ent->getSeed() . ')' : ''; 
                $e = sprintf( "%d. %s %s", $ent->getPosition(), $ent->getName(),$seed );
                echo PHP_EOL . $e;
            }
        }
    }

    public function arrShowDraw() {
        $result = array();
        if( !isset( $this->event ) ) {
            $result[] = "Event is missing";
        }
        elseif( count( $this->event->getDraw() ) < 1  ) {
            $result[] = "Draw is empty";
        }
        else {
            $entrants = $this->event->getDraw();
            usort( $entrants, array( 'TournamentDirector', 'sortByPositionAsc' ) );
            foreach( $entrants as $ent ) {
                $seed = $ent->getSeed() > 0 ? '(' . $ent->getSeed() . ')' : ''; 
                $e = sprintf( "%d. %s %s", $ent->getPosition(), $ent->getName(),$seed );
                error_log( "TournamentDirector::arrShowDraw: $e" );
                $result[] = $e;
            }
        }
        return $result;
    }

    public function showRounds() {
        if( $this->hasChallengerRound ) {
            $this->showMatches( 0 );
        }

        for($i = 1; $i <= $this->totalRounds(); $i++ ) {
            $this->showMatches( $i );
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
                    $hseed = isset( $home ) && $home->getSeed() > 0 ? '(' . $home->getSeed() . ')' : '';
                    $visitor = $match->getVisitorEntrant();
                    $vid = isset( $visitor ) ? $visitor->getPosition() : '0';
                    $vname = isset( $visitor ) ? $visitor->getName() : 'tba';
                    $vseed = isset( $homvisitore ) && $visitor->getSeed() > 0 ? '(' . $visitor->getSeed() .')' : '';
                    if($match->isBye() ) {
                        echo PHP_EOL . "Match($mn): Home($hid)='$hname$hseed' has Bye ";
                    }
                    else {
                        echo PHP_EOL . "Match($mn): Visitor($vid)='$vname$vseed' vs Home($hid)='$hname$hseed'  ";
                    }
                }
            }
        }
    }

    public function arrShowMatches( int $round = 1 ){
        $report = array();
        $matches = $this->event->getMatches();
        if( count( $matches ) < 1 ) {
            $report[] = "No matches defined.";
        }
        else {
            usort($matches, array( 'TournamentDirector', 'sortByMatchNumberAsc' ) );
            $report [] = "Round $round";
            foreach( $matches as $match ) {
                if( $round === $match->getRoundNumber() ) {
                    $mn = $match->getMatchNumber();
                    $home = $match->getHomeEntrant();
                    $hid = isset( $home ) ? $home->getPosition() : '0';
                    $hname = isset( $home ) ? $home->getName() : 'tba';
                    $hseed = isset( $home ) && $home->getSeed() > 0 ? '(' . $home->getSeed() . ')' : '';
                    $visitor = $match->getVisitorEntrant();
                    $vid = isset( $visitor ) ? $visitor->getPosition() : '0';
                    $vname = isset( $visitor ) ? $visitor->getName() : 'tba';
                    $vseed = isset( $homvisitore ) && $visitor->getSeed() > 0 ? '(' . $visitor->getSeed() .')' : '';
                    if($match->isBye() ) {
                        $report[] = "Match($mn): Home($hid)='$hname$hseed' has Bye ";
                    }
                    else {
                        $report[] = "Match($mn): Visitor($vid)='$vname$vseed' vs Home($hid)='$hname$hseed'  ";
                    }
                }
            }
        }
        return $report;
    }

    /**
     * Add an Entrant to the signup
     * @param $name The name of the player or doubles team
     * @param $seed The seeding of this player or doubles team
     */
    public function addEntrant( string $name, int $seed = 0 ) {
        $result = 0;

        if( 0 < $this->getMatchCount() ) {
            throw new InvalidTournamentException( __('Cannot add entrant because matches already exist.') );
        }

        if( isset( $this->event ) ) {
            $this->event->addToDraw( $name, $seed );
            $result = $this->event->save();
        }
        return $result > 0;
    }

    /**
     * Remove an Entrant from the signup
     * @param $name The name of the player or doubles team
     */
    public function removeEntrant( string $name ) {
        $result = 0;

        if( 0 < $this->getMatchCount() ) {
            throw new InvalidTournamentException( __('Cannot remove entrant because matches already exist.') );
        }

        if( isset( $this->event ) ) {
            $this->event->removeFromDraw( $name );
            $result = $this->event->save();
        }
        return $result > 0;
    }
    
    /**
     * Move a match from its current spot to the target match number.
     * @param $round The round number of this match
     * @param $fromMatchNum The match's current number (i.e. place in the lineup)
     * @param $toMatchNum The intended place for this match
     * @return true if succeeded; false otherwise
     */
    public function moveMatch(int $fromRoundNum, int $fromMatchNum, int $toMatchNum ) {
        $result = 0;
        if( isset( $this->event ) ) {
            $result = Match::move($this->event->getID(), $fromRoundNum, $fromMatchNum, $toMatchNum );
        }
        return 1 === $result;
    }

    /**
     * Resequence the match numbers for these matches
     * @param $start The starting match number
     * @param $incr The increment to apply to generate the match numbers
     */
    public function resequenceMatches( int $start = 1, int $incr = 1 ) {
        $result = 0;
        if( isset( $this->event ) ) {
            $result = Match::resequence( $this->event->getID(), $start, $incr );
        }
        return $result > 1;

    }

    /**************************************************  Private functions ********************************************************** */

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
    public function schedulePreliminaryRounds( $randomizeDraw = false, $watershed = 5 ) {

        $loc = __CLASS__ . "::" . __FUNCTION__;
        error_log('>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>');
        $mess = sprintf( "%s called with randomize=%d and watershed=%d", $loc, $randomizeDraw, $watershed );
        error_log( $mess ); 

        if( 0 === $this->drawSize() ) {
            throw new InvalidTournamentException( __('Cannot generate preliminary matches because there is no signup.') );
        }

        if( 0 < $this->hasStarted() ) {
            throw new InvalidTournamentException( __('Cannot generate preliminary matches because play as already started.') );
        }

        //Remove any existing matches ... we know they have not started yet
        $this->removeBrackets();

        $this->calculateEventSize();
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
        error_log( "$loc: highMatchnum=$highMatchnum seedByes=$seedByes unseedByes=$unseedByes" );
        
        if( $this->numToEliminate <= $watershed ) {
            $this->processChallengerRound( $seeded, $unseeded );
        }
        else {
            $this->processByes( $seeded, $unseeded );
        }

        if( (count( $unseeded ) + count( $seeded )) > 0 ) throw new InvalidTournamentException( __( "Did not schedule all players into initial rounds." ) );

        $this->save();
        error_log('<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<');

        return $this->event->numMatches();
    }

    /**
     * For this case, we could have a large number of players involved in bringing the count
     * down to a power of 2 for the subsequent round. So this is considered the first round
     * and only a few byes need to be defined. It is likely that only seeded players will get the byes.
     */
    private function processByes( array &$seeded, array &$unseeded ) {
        
        $loc = __CLASS__ . "::" . __FUNCTION__;

        $numInvolved = 2 * $this->numToEliminate;
        $remainder   = $numInvolved > 0 ? $this->event->drawSize() - $numInvolved : 0;
        $highMatchnum = ceil( $this->event->drawSize() / 2 );
        $lowMatchnum = 1;
        $usedMatchNums = array();
        $lastSlot = 0;
        
        $initialRound = 1;
        $seedByes = 0;
        $unseedByes = 0;
        $seedByes    =  min( count( $seeded ) , $remainder );
        $unseedByes  = $remainder - $seedByes;

        //Add seeded players as Bye matches using an even distribution
        $slot = ($seedByes + $unseedByes) > 1 ? ceil( ceil( $this->event->drawSize() / 2.0 ) / ($seedByes + $unseedByes) ) : 0;
        $slot = max( 2, $slot );
        error_log("$loc: seeds=" . count($seeded) . '; unseeded='.count($unseeded));
        error_log("$loc ...slot=$slot; numInvolved=$numInvolved; remainder=$remainder; highMatchnum=$highMatchnum; seedByes=$seedByes; unseedByes=$unseedByes");

        for( $i = 0; $i < $seedByes; $i++ ) {
            if( 0 === $i ) {
                $lastSlot = $lowMatchnum++;
                array_push( $usedMatchNums, $lastSlot );
            }
            else if( 1 === $i ) {
                $lastSlot = 3 * $highMatchnum;
                array_push( $usedMatchNums, $lastSlot );
            }
            else {
                //$lastSlot += $slot;
                $lastSlot = $lowMatchnum + $i * $slot;
                array_push( $usedMatchNums, $lastSlot );
            }
            $home = array_shift( $seeded );
            $match = new Match( $this->event->getID(), $initialRound, $lastSlot );
            $match->setIsBye( true );
            $match->setHomeEntrant( $home );
            $match->setMatchType( $this->matchType );
            $this->event->addMatch( $match );
            error_log( sprintf( "%s -> added bye for seeded player %s to round %d using match number %d", $loc, $home->getName(), $initialRound, $lastSlot ) );
        }

        $matchnum = $lowMatchnum;
        for( $i = 0; $i < $unseedByes; $i++ ) {
            $home = array_shift( $unseeded );
            $mn = $matchnum++;
            $mn = $this->getNextAvailable( $usedMatchNums, $mn );
            array_push( $usedMatchNums, $mn );
            $match = new Match( $this->event->getID(), $initialRound, $mn );
            $match->setIsBye( true );
            $match->setHomeEntrant( $home );
            $match->setMatchType( $this->matchType );
            $this->event->addMatch( $match );            
            error_log( sprintf( "%s -> added bye for unseeded player %s in round %d using match number %d", $loc, $home->getName(), $initialRound, $mn ) );
        }

        //Set the first lot of matches starting from end of the line
        $ctr = 0;
        while( count( $unseeded ) > 0 || count( $seeded ) > 0 ) {
            if( count( $seeded ) > 0 ) {
                $home    = array_pop( $seeded );
                $visitor = array_shift( $unseeded );

                // $lastSlot = rand( $lowMatchnum, $highMatchnum );
                // $lastSlot += $slot;
                if( $ctr & 2 ) $lastSlot = $highMatchnum - $ctr * $slot;
                else           $lastSlot = $lowMatchnum  + $ctr * $slot;
                $lastSlot = $this->getNextAvailable( $usedMatchNums, $lastSlot );
                array_push( $usedMatchNums, $lastSlot );
                
                $match   = new Match( $this->event->getID(), $initialRound, $lastSlot );
                $match->setHomeEntrant( $home );
                $match->setVisitorEntrant( $visitor );
                $match->setMatchType( $this->matchType );
                $this->event->addMatch( $match );  
                error_log( sprintf( "%s -> added match for seeded player '%s' vs unseeded '%s' in round %d using match number %d", $loc, $home->getName(), $visitor->getName(), $initialRound, $lastSlot ) );                  
            }
            else {
                $home    = array_shift( $unseeded );
                $visitor = array_shift( $unseeded );

                $mn = $matchnum++;
                $mn = $this->getNextAvailable( $usedMatchNums, $mn );
                array_push( $usedMatchNums, $mn );

                $match = new Match( $this->event->getID(), $initialRound, $mn );
                $match->setHomeEntrant( $home );

                if( !is_null( $visitor ) ) $match->setVisitorEntrant( $visitor );
                else $match->setIsBye( true );

                $match->setMatchType( $this->matchType );
                $this->event->addMatch( $match );
                error_log( sprintf( "%s -> added match for unseeded players '%s' vs '%s' in round %d using match number %d", $loc, $home->getName(), $visitor->getName(), $initialRound, $mn ) );
            }
            ++$ctr;
        }
    }

    /**
     * An advanced or "challenger" round is required when only a very few players are involved in order to bring 
     * the count down to a power of 2. There are no byes but the challenger round happens before round one of the tournament.
     */
    private function processChallengerRound( array &$seeded, array &$unseeded ) {

        $loc = __CLASS__ . "::" . __FUNCTION__;

        $this->hasChallengerRound = true;
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
        $slot = max( 2, $slot );

        error_log("$loc: seeds=" . count($seeded) . '; unseeded='.count($unseeded));
        error_log("$loc ...slot=$slot; numInvolved=$numInvolved; remainder=$remainder; highMatchnum=$highMatchnum; seedByes=$seedByes; unseedByes=$unseedByes");

        //Create the challenger round using unseeded players from end of list
        //Note that $numInvolved is always an even number
        for( $i = $numInvolved; $i > 0; $i -= 2 ) {
            $home    = array_pop( $unseeded );
            $visitor = array_pop( $unseeded );
            
            $mn = $matchnum++;
            $mn = $this->getNextAvailable( $usedMatchNums, $mn );
            array_push( $usedMatchNums, $mn );

            $match = new Match( $this->event->getID(), $initialRound, $mn );
            $match->setHomeEntrant( $home );
            $match->setVisitorEntrant( $visitor );
            $match->setMatchType( $this->matchType );
            $this->event->addMatch( $match );
            error_log( sprintf( "%s -> added  unseeded players '%s' vs '%s' to challenger round %d with match num=%d", $loc, $home->getName(),$visitor->getName(), $initialRound, $mn ) );
        }
        
        ++$initialRound;
        //Schedule the odd player to wait for the winner of a challenger round
        if( (1 & $remainder) ) {
            $home = array_shift( $unseeded );
            if( !isset( $home ) ) $home = array_shift( $seeded );
            if( isset( $home ) ) {
                $mn = $matchnum++; //use the last match number from round 0
                $mn = $this->getNextAvailable( $usedMatchNums, $mn );
                array_push( $usedMatchNums, $mn );

                $match = new Match( $this->event->getID(), $initialRound, $mn );
                $match->setHomeEntrant( $home );
                $match->setMatchType( $this->matchType );
                $this->event->addMatch( $match );
                error_log( sprintf( "%s -> added  unseeded player '%s' to round %d with match num=%d to wait for winner from early round"
                                  , $loc, $home->getName(), $initialRound, $mn ) );
            }
        }

        //Now create the first round using all the remaining players
        // and there must be an even number of them
        $ctr = 0;
        while( count( $unseeded ) > 0 || count( $seeded ) > 0 ) {
            if( count( $seeded ) > 0 ) {
                //If there are still seeded players available
                // then we "slot" them in using even distribution
                $home    = array_shift( $seeded );
                $visitor = array_shift( $unseeded );
                //if we have run out of unseeded, we must use seeded vs seeded
                if( is_null( $visitor ) ) $visitor = array_shift( $seeded );

                //# 1 seed is scheduled first
                // followed by other seeds with alternating match numbers (lower then higher)
                // but #2 seed (ctr === 1) gets put at end of list
                if( $ctr & 2 ) $lastSlot = $highMatchnum - $ctr * $slot;
                else           $lastSlot = $lowMatchnum  + $ctr * $slot;
                if( 1 === $ctr ) $lastSlot = 3 * $highMatchnum;
                $lastSlot = $this->getNextAvailable( $usedMatchNums, $lastSlot );
                array_push( $usedMatchNums, $lastSlot );
                
                $match = new Match( $this->event->getID(), $initialRound, $lastSlot );
                $match->setHomeEntrant( $home );
                
                if( !is_null( $visitor ) ) $match->setVisitorEntrant( $visitor );
                $match->setMatchType( $this->matchType );
                $this->event->addMatch( $match ); 
                error_log( sprintf( "%s -> added match for seeded player '%s' vs unseeded '%s'  in round %d using match number %d", $loc, $home->getName(), $visitor->getName(),$initialRound, $lastSlot ) );                   
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
                if( !is_null( $visitor ) ) $match->setVisitorEntrant( $visitor );

                $match->setMatchType( $this->matchType );
                $this->event->addMatch( $match );               
                 error_log( sprintf( "%s -> added match for unseeded players '%s' vs '%s' in round %d using match number %d", $loc, $home->getName(), $visitor->getName(), $initialRound, $mn ) );
            }
            ++$ctr;
        }
    }

    /**
     * Sort Draw by seeding in descending order
     */
    private function sortBySeedDesc( $a, $b ) {
        if($a->getSeed() === $b->getSeed()) return 0; return ( $a->getSeed() < $b->getSeed() ) ? 1 : -1;
    }
    
    /**
     * Sort Draw bye seeding in ascending order
     */
    private function sortBySeedAsc( $a, $b ) {
        if($a->getSeed() === $b->getSeed()) return 0; return ( $a->getSeed() < $b->getSeed() ) ? -1 : 1;
    }
    
    /**
     * Sort Draw by position in asending order
     */
    private function sortByPositionAsc( $a, $b ) {
        if($a->getPosition() === $b->getPosition()) return 0; return ( $a->getPosition() < $b->getPosition() ) ? -1 : 1;
    }

    /**
     * Sort matches by match number in ascending order
     */
	private function sortByMatchNumberAsc( $a, $b ) {
		if($a->getMatchNumber() === $b->getMatchNumber()) return 0; return ( $a->getMatchNumber() < $b->getMatchNumber() ) ? -1 : 1;
    }
    
    /**
     * Sort matches by round number then match number in ascending order
     * Assumes that across all matches, the match number is always less than $max
     */
	private function sortByRoundMatchNumberAsc( $a, $b, $max = 1000 ) {
        if($a->getRoundNumber() === $b->getRoundNumber() && $a->getMatchNumber() === $b->getMatchNumber()) return 0; 
        $compa = $a->getRoundNumber() * $max + $a->getMatchNumber();
        $compb = $b->getRoundNumber() * $max + $b->getMatchNumber();
        return ( $compa < $compb  ? -1 : 1 );
	}

    /**
     * Returns the next available integer that is not in the given array of integers
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
            throw new InvalidEventException( $mess );
        }

        if( $this->event->drawSize() < self::MINIMUM_ENTRANTS ) {
            $min = self::MINIMUM_ENTRANTS;
            $mess = __( "Event must have at least $min entrants for an elimination event.", TennisEvents::TEXT_DOMAIN );
            throw new InvalidEventException( $mess );
        }

        $this->numRounds = $this->calculateExponent( $this->event->drawSize() );
        $this->numToEliminate = $this->event->drawSize() - pow( 2, $this->numRounds );

        return $this->numToEliminate;
    }

    /**
     * Given the size of the draw (or any integer), calculate the highest 
     * power of 2 which is less than that size (or integer)
     */
	private function calculateExponent( int $drawSize ) {
        $exponent = 0;
        foreach( range(1,8) as $exp ) {
            if( pow( 2, $exp ) > $drawSize ) {
                $exponent = $exp - 1;
                break;
            }
        }
        return $exponent;
    }
}