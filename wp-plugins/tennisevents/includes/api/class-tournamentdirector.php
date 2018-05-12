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
    private     $adjacencyMatrix = array();

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
     * Approve the preliminary matches.
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
    public function approve( int $bracketNum = 1 ) {

        $loc = __CLASS__ . "::" . __FUNCTION__;

        error_log( sprintf(" >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>%s>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>", $loc) );
        
        //1. Set the "next pointers" of the preliminary matches
        $bracket;
        foreach( $this->event->getBrackets() as $b ) {
            if( $bracketNum == $bracket->getBracketNumber() ) {
                $bracket = $b;
                break;
            }
        }

        if( !isset( $bracket ) ) {
            throw new InvalidTournamentException( sprintf("Invalid bracket number %d.", $bracketNum) );
        }

        $round0Matches = $bracket->getMatches( 0 );
        $round1Matches = $bracket->getMatches( 1 );

        // Note: There should be no match in round 1 with the same match number as a round 0 match
        // This makes it easy to point round 0 matches to round 1 without affecting existing round 1 matches
    
        //If we have odd number of challengers then link the 
        // last one to the first round 1 match but only if it is waiting
        $linkMatch0 = null;
        $linkMatch1 = null;
        if( count( $round0Matches ) & 1 ) {
            if( $round1Matches[0]->isWaiting() ) {
                $linkMatch1 = $round1Matches[0];
                $nextMatchNum = $round1Matches[0]->getMatchNumber();
                $linkMatch0 = array_pop( $round0Matches );
                $linkMatch0->setNextMatchNumber( $nextMatchNum );
                $linkMatch0->setNextRoundNumber( 1 );
                error_log(sprintf( "%s -> linked the last match %s from challengers to round 1 match %d"
                                 , $loc, $linkMatch0->title(), $nextMatchNum ) );
            }
            else {
                $linkMatch0 = array_pop( $round0Matches );
                $mess = sprintf( "Match %s was last of odd numbered challengers but does not have waiting match in round 1 %s"
                               , $linkMatch0->title(), $round1Matches[0]->title() );
                throw new InvalidTournamentException( $mess );
            }
        }

        //At this point there must be an even number of challenger matches
        $ctr = 1;
        while( count( $round0Matches ) > 0 ) {
            $nextMatchNum = $ctr++;
            $m1 = array_shift( $round0Matches );
            $m1->setNextMatchNumber( $nextMatchNum );
            $m1->setNextRoundNumber( 1 );
            error_log(sprintf("%s -> linked %s to round 1 match %d", $loc, $m1->title(), $nextMatchNum ) );

            $m2 = array_shift( $round0Matches );

            if( is_null( $m2 ) ) {
                throw new InvalidTournamentException( sprintf( "%s Unexpectedly encountered odd number of challengers", $loc ) );
            }

            $m2->setNextMatchNumber( $nextMatchNum );
            $m2->setNextRoundNumber( 1 );
            error_log( sprintf( "%s -> linked %s to round 1 match %d", $loc, $m2->title(), $nextMatchNum ) );
        }

        //Round 1 could have odd number of matches
        while( count( $round1Matches ) > 0 ) {
            $nextMatchNum = $ctr++;

            $m1 = array_shift( $round1Matches );
            $m1->setNextMatchNumber( $nextMatchNum );
            $m1->setNextRoundNumber( 2 );

            $bye = $m1->isBye() ? 'bye' : '';
            $wait = $m1->isWaiting() ? 'waiting' : '';
            error_log(sprintf("%s -> linked %s (%s%s) to round 2 match %d", $loc, $m1->title(), $bye, $wait, $nextMatchNum ) );
            
            //Skip the waiting match in round 1 because
            // it will pair up last match in odd-numbered round 0 matches
            if( $m1->isWaiting() ) {
                continue;
            }

            $m2 = array_shift( $round1Matches );

            if( is_null( $m2 ) ) {
                throw new InvalidTournamentException(sprintf("%s -> Odd number of round 1 matches. Could not find partner match for %s", $loc, $m1->title() ) );
            }

            $m2->setNextRoundNumber( 2 );
            $m2->setNextMatchNumber( $nextMatchNum );

            $bye = $m2->isBye() ? 'bye' : '';
            error_log(sprintf("%s -> linked %s (%s) to round 2 match %d", $loc, $m2->title(), $bye, $nextMatchNum ) );
        }

        $this->save(); //save all matchtes to db before generating linked lists

        //2. Generate the linked lists (and put them into the adjacencyMatrix)
        $this->generateLinkedLists( $bracket, $linkMatch0, $linkMatch1 );

        $this->linkedListsToMatches();

        error_log( sprintf( "<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<%s<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<", $loc ) );

    }

    /**
     * Advance completed matches to their respective next rounds.
     * 
     */
    public function advance() {
        $loc = __CLASS__ . "::" . __FUNCTION__;

        $matches = $this->getMatches( null, true ); //force reloading all matches from db

        foreach( $matches as $match ) {
            $nextMatch = $this->advanceMatch( $match );
            if( !is_null( $nextMatch ) ) {
                $this->event->addMatch( $nextMatch );
            }
        }

        $this->save();
    }

    /**
     * Advance the given match to its next next round
     * @param $match to be advanced
     */
    private function advanceMatch( Match &$match ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;

        $matchNext = null;

        if( !$match->isLocked() && !$match->isBye() ) return $matchNext;

        $umpire = $this->getChairUmpire();
        $winner = $umpire->matchWinner( $match );
        $matchNext = $this->event->getMatch( $match->getNextRoundNumber(), $match->getNextMatchNumber() );
        if( is_null( $matchNext ) ) {
            $matchNext = new Match($this->event->getID(), $match->getNextRoundNumber(), $match->getNextMatchNumber() );
            $matchNext->setMatchType( $match->getMatchType() );
            $matchNext->setHomeEntrant( $winner );            
        }
        else {
            $matchNext->setVisitorEntrant( $winner );
        }

        return $matchNext;
    }

    /**
     * Generate a linked list for each preliminary match.
     * These lists form the basis for the entire bracket
     * @param $link0 The match in round 0 that needs to link to a waiting match in round 1
     * @param $link1 The match in ournd 1 that is waiting to be linked with a match in round 0
     */
    private function generateLinkedLists( Bracket $bracket, Match $link0 = null, Match $link1 = null ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;

        $matches = $bracket->getMatches( true ); //force reloading all matches from db

        foreach( $matches as $match ) {
            $dlist = new SplDoublyLinkedList();
            $this->adjacencyMatrix[] = $dlist;

            if( !is_null( $link1 ) &&  $match->getRoundNumber() === $link1->getRoundNumber() &&  $match->getMatchNumber() === $link1->getMatchNumber() ) {
                if( !$match->isWaiting() ) {
                    $mess = sprintf("%s -> %s should be waiting but it is not!", $loc, $match->title() );
                    throw new InvalidTournamentException( $mess );
                }
            }

            //Create dummy starter in round 0 for this round 1 match
            if( 1 === $match->getRoundNumber() && $dlist->count() === 0 ) {
                $dummy = new stdClass;
                $dummy->round_num = 0;
                $dummy->match_num = 0;
                $dummy->next_round_num = $match->getRoundNumber();
                $dummy->next_match_num = $match->getMatchNumber();
                $dlist->push( $dummy );
                error_log( sprintf("%s -> Pushed Dummy(%d,%d->%d,%d) onto list from %s"
                         , $loc, $dummy->round_num, $dummy->match_num,$dummy->next_round_num,$dummy->next_match_num, $match->title() ) );
            }
            $cur = new stdClass;
            $cur->round_num = $match->getRoundNumber();
            $cur->match_num = $match->getMatchNumber();
            $cur->next_round_num = $match->getNextRoundNumber();
            $cur->next_match_num = $match->getNextMatchNumber();
            $dlist->push( $cur );
            error_log( sprintf("%s -> Pushed Cur(%d,%d->%d,%d) onto list from %s"
                     , $loc, $cur->round_num, $cur->match_num,$cur->next_round_num,$cur->next_match_num, $match->title() ) );
            
            //Link final round 0 match with the first round 1 match
            if( !is_null( $link0 ) &&  $match->getRoundNumber() === $link0->getRoundNumber()  &&  $match->getMatchNumber() === $link0->getMatchNumber() ) {
                $obj = new stdClass;
                $obj->round_num = $link0->getNextRoundNumber();
                $obj->match_num = $link0->getNextMatchNumber();
                $obj->next_round_num = $link1->getNextRoundNumber();
                $obj->next_match_num = $link1->getNextMatchNumber();
                $dlist->push( $obj );
                error_log( sprintf("%s -> Also pushed Obj(%d,%d->%d,%d) onto same list from %s"
                         , $loc, $obj->round_num, $obj->match_num,$obj->next_round_num,$obj->next_match_num, $match->title() ) );
            }
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
                error_log( sprintf("%s -> Pushed Cur(%d,%d->%d,%d) onto list", $loc, $cur->round_num, $cur->match_num,$cur->next_round_num, $cur->next_match_num ) );
            }
        }
    }

    /**
     * Get array of strings representing
     * the adjacency matrix
     */
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

    /**
     * Get the array containing all of the linked lists
     */
    public function getAdjacencyMatrix() {
        return $this->adjacencyMatrix;
    }

    
    private function linkedListsToMatches() {
        $loc = __CLASS__ . "::" . __FUNCTION__;

        foreach( $this->adjacencyMatrix as $dlist ) {
            $dlist->setIteratorMode( SplDoublyLinkedList::IT_MODE_FIFO );
            for( $dlist->rewind(); $dlist->valid(); $dlist->next() ) {
                $match = $this->getMatch( $dlist->current()->round_num, $dlist->current()->match_num );
                if(!is_null( $match ) ) continue;
                $match = new Match( $this->event->GetID(), $dlist->current()->round_num, $dlist->current()->match_num );
                $match->setMatchType( $this->matchtype );
                $this->event->addMatch( $match );
            }
        }
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
                if( ( $dlist->current()->round_num === $r ) &&  ( $dlist->current()->match_num === $m ) ) {
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
    public function currentRound( string $bracketName = Bracket::WINNERS ):int {

        $loc = __CLASS__ . "::" . __FUNCTION__;
        
        $currentRound = -1;
        $umpire = $this->getChairUmpire();
        $bracket = $this->event->getBracket( $bracketName );
        $totalRounds = $this->totalRounds( $bracketName );
        for($i = 0; !is_null( $bracket ) && $i < $totalRounds; $i++ ) {
            foreach( $bracket->getMatchesByRound( $i ) as $match ) {
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
        error_log( sprintf( "%s -> returning bracket %s's current round=%d", $loc,$bracketName, $currentRound ) );
        return $currentRound;
    }

    public function hasStarted( string $bracketName ) { 
        $loc = __CLASS__ . "::" . __FUNCTION__;
        
        $started = false;
        $umpire = $this->getChairUmpire();
        $bracket = $this->event->getBracket( $bracketName );
        if( !is_null( $bracket ) ) {
            foreach( $bracket->getMatches() as $match ) {
                $status = $umpire->matchStatus( $match );
                error_log( sprintf( "%s(%s) -> %s's bracket has status='%s'", $loc, $match->toString(), $bracketName, $status ) );
                if( $status != ChairUmpire::NOTSTARTED && $status != ChairUmpire::BYE && $status != ChairUmpire::WAITING ) {
                    $started = true;
                    break;
                }
            }
        }
        else {
            $started = true;
        }
        error_log( sprintf( "%s->returning started=%d for bracket '%s'", $loc, $started, $bracketName ) );
        return $started;
    }

    /**
     * The minimum exponent giving a number less than the signup size
     * TODO: this should be used in the calcs rather than totalRounds which currently is wrong
     */
    public function minExponent( string $bracketName = Bracket::WINNERS ) {
        $bracket = $this->event->getBracket( $bracketName );
        $this->numRounds = $this->calculateExponent( $bracket->signupSize() );
        return $this->numRounds;
    }
    
    /**
     * The total number of rounds for this tennis event.
     * Including a challenger round if needed.
     */
    public function totalRounds( string $bracketName = Bracket::WINNERS, $incChallenger = true ) {
        $bracket = $this->event->getBracket( $bracketName );
        $size = isset( $bracket) ? $bracket->signupSize( ) : 0;
        $size = $this->calculateExponent( $size );
        $this->numRounds = $incChallenger && $this->hasChallengerRound() && $size > 0  ? ++$size : $size;
        return $this->numRounds;
    }

    public function hasChallengerRound() {
        return $this->hasChallengerRound;
    }

    /**
     * Get ths signup for this tournament sorted by position in the draw
     */
    public function getSignup() {
        $entrants = isset( $this->event ) ? $this->event->getSignup() : array();
        usort( $entrants, array( 'TournamentDirector', 'sortByPositionAsc' ) );

        return $entrants;
    }
    
    /**
     * Return the size of the signup for the tennis event
     */
    public function signupSize( ) {
        return $this->event->signupSize( );
    }

    /**
     * Remove the signup and all brackets (and matches) for a tennis event
     */
    public function removeSignup( ) {
        $result = 0;
        $bracket = $this->event->getWinnersBracket();
        if( $this->hasStarted( $bracket->getName() ) ) {
            throw new InvalidTournamentException( "Cannot remove signup because tournament has started." );
        }
        $this->event->removeSignup();
        $result = $this->event->save();
        return $result;
    }

    /**
     * Get the Brackets
     */
    public function getBrackets( $force = false ) {
        return $this->event->getBrackets( $force );
    }

    /**
     * Remove all brackets and matches for a tennis event specific bracket
     */
    public function removeBrackets( $force = false ) {
        $result = 0;
        $bracket = $this->event->getWinnersBracket();
        if( !$force && $this->hasStarted( $bracket->getName() ) ) {
            throw new InvalidTournamentException( "Cannot remove brackets because tournament has started." );
        }

        $this->event->removeBrackets();
        $result = $this->save();
        
        return $result;
    }


    /**
     * Return all matches for an event or just for a given round
     * @param $bracketName The bracket name
     * @param $round The round whose matches are to be retrieved
     * @param $force Force reloading from database
     */
    public function getMatches( string $bracketName = Bracket::WINNERS, $round = null, $force = false ) {
        $matches = array();
        $bracket = $this->event->getBracket( $bracketName );
        if( !is_null( $bracket ) ) {
            if( is_null( $round ) ) {
                $matches = $bracket->getMatches( $force );
                usort( $matches, array( 'TournamentDirector', 'sortByRoundMatchNumberAsc' ) );
            }
            else {
                $matches = $bracket->getMatchesByRound( $round );
                usort( $matches, array( 'TournamentDirector', 'sortByMatchNumberAsc' ) );
            }
        }
        else {
            throw new InvalidTournamentException( __( "Wrong bracket number." ) );
        }
        
        return $matches;
    }

    public function removeMatches( string $bracketName = Bracket::WINNERS, $force = false ) {
        $matches = array();
        $bracket = $this->event->getBracket( $bracketName );
        if( !is_null( $bracket ) ) {
            $bracket->removeAllMatches();
        }
        else {
            throw new InvalidTournamentException( __( "Bracket name $bracketName does not exist when trying to remove its matches." ) );
        }
        
        return $matches;
    }
    
    /**
     * Return the count of all matches or just for a given round
     * @param $round The round whose matches are to be counted
     */
    public function getMatchCount( string $bracketName = Bracket::WINNERS, $round = null ) {
        $matches = array();
        $bracket = $this->event->getBracket( $bracketName );
        if( isset( $bracket ) ) {
            if( is_null( $round ) ) {
                $matches = $bracket->getMatches();
            }
            else {
                $matches = $bracket->getMatchesByRound( $round );
            }
        }
        return count( $matches );
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
            $this->event->removeFromSignup( $name );
            $result = $this->event->save();
        }
        return $result > 0;
    }
    
    /**
     * Move a match from its current spot to the target match number.
     * @param $bracketName The name of the bracket
     * @param $round The round number of this match
     * @param $fromMatchNum The match's current number (i.e. place in the lineup)
     * @param $toMatchNum The intended place for this match
     * @return true if succeeded; false otherwise
     */
    public function moveMatch( string $bracketName, int $fromRoundNum, int $fromMatchNum, int $toMatchNum ) {
        $result = 0;
        $bracket = $this->event->getBracket( $bracketName );
        if( isset( $bracket ) ) {
            $result = Match::move( $this->event->getID(), $bracket->getBracketNumber(), $fromRoundNum, $fromMatchNum, $toMatchNum );
        }
        
        return 1 === $result;
    }

    /**
     * Resequence the match numbers for these matches
     * @param $bracketName The name of the bracket. Defaults to WINNERS
     * @param $start The starting match number
     * @param $incr The increment to apply to generate the match numbers
     */
    public function resequenceMatches( string $bracketName = Bracket::WINNERS, int $start = 1, int $incr = 1 ) {
        $result = 0;
        $bracket = $this->event->getBracket( $bracketName );
        if( isset( $bracket ) ) {
            $result = Match::resequence( $this->event->getID(), $bracket->getBracketNumber(), $start, $incr );
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
        error_log(' ');
        error_log(">>>>>>>>>>>>>>>>>>>>>$loc>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>");
        error_log( sprintf( "%s called with randomize=%d and watershed=%d", $loc, $randomizeDraw, $watershed ) ); 

        $matchesCreated = 0;
        $winnerbracket = null;
        $loserbracket = null;
        foreach( $this->event->getBrackets() as $b ) {
            if( $b->getName() === Bracket::WINNERS ) {
                $winnerbracket = $b;
            }
            elseif( $b->getName() === Bracket::LOSERS || $b->getName() === Bracket::CONSOLATION ) {
                $loserbracket = $b;
            }
        }
        
        if( !isset( $winnerbracket ) ) {
            $winnerbracket = $this->event->getWinnersBracket();
        }

        if( isset( $loserbracket ) && $this->event->getFormat() === Format::SINGLE_ELIM ) {
            throw new InvalidTournamentException( __("Loser bracket defined for single elimination tournament.", TennisEvents::TEXT_DOMAIN ) );
        }
        if( !isset( $loserbracket ) && $this->event->getFormat() === Format::DOUBLE_ELIM) {
            $loserbracket = $this->event->createBracket( Bracket::LOSERS );
        }
        elseif( !isset( $loserbracket ) && $this->event->getFormat() === Format::CONSOLATION) {
            $loserbracket = $this->event->createBracket( Bracket::CONSOLATION );
        }

        error_log( sprintf("%s getting signup size...", $loc ) );
        $winnerSignupSize = $this->event->signupSize();
        if( 0 ===  $winnerSignupSize ) {
            throw new InvalidTournamentException( __('Cannot generate preliminary matches for bracket because there is no signup.', TennisEvents::TEXT_DOMAIN ) );
        }
        error_log( sprintf("%s: signup size=%d", $loc, $winnerSignupSize ) );

        error_log( sprintf("%s tournament has started?...", $loc ) );
        if( 0 < $this->hasStarted( $winnerbracket->getName() ) ) {
            throw new InvalidTournamentException( __('Cannot generate preliminary matches for bracket because play as already started.') );
        }

        foreach( array( $winnerbracket, $loserbracket ) as $bracket ) {
            if( is_null( $bracket) ) break;
            
            error_log( sprintf("%s processing bracket %s...", $loc, $bracket->getName() ) );

            //Remove any existing matches ... we know they have not started yet
            error_log( sprintf("%s remove existing matches...", $loc ) );
            $this->removeMatches( $bracket->getName() );
            $this->calculateEventSize( $bracket->getName() );

            error_log( sprintf("%s getting signup...", $loc ) );
            $entrants = $this->getSignup();
            $unseeded = array_filter( array_map( function( $e ) { if( $e->getSeed() < 1 ) return $e; }, $entrants ) );
            
            if( $randomizeDraw ) shuffle( $unseeded );
            else usort( $unseeded, array( 'TournamentDirector', 'sortByPositionAsc' ) );

            $seeded = array_filter( array_map( function( $e ) { if( $e->getSeed() > 0 ) return $e; }, $entrants ) );
            usort( $seeded, array( 'TournamentDirector', 'sortBySeedAsc') );

            $numInvolved = 2 * $this->numToEliminate;
            $remainder   = $numInvolved > 0 ? $bracket->signupSize() - $numInvolved : 0;

            if($numInvolved > $remainder ) {
                $seedByes    =  min( count( $seeded ) , $remainder );
                $unseedByes  = $remainder - $seedByes;
            }
            else {
                $seedByes = min( count( $seeded ), $numInvolved );
                $unseedByes = $numInvolved - $seedByes;
            }
            $totalByes = $seedByes + $unseedByes;
            $highMatchnum = ceil( $bracket->signupSize() / 2 );
            error_log( sprintf("%s: highMatchnum=%d seedByes=%d unseedByes=%d",$loc, $highMatchnum, $seedByes, $unseedByes ) );
            
            if( $this->numToEliminate <= $watershed ) {
                $this->processChallengerRound( $bracket, $seeded, $unseeded );
            }
            else {
                $this->processByes( $bracket, $seeded, $unseeded );
            }

            if( (count( $unseeded ) + count( $seeded )) > 0 ) throw new InvalidTournamentException( __( "Did not schedule all players into initial rounds." ) );

            $matchesCreated += $bracket->numMatches();
            error_log( sprintf("%s saving...", $loc ) );
            $this->save();
        }
        error_log("<<<<<<<<<<<<<<<<<<<<<<<$loc<<<<<<<<<<<<<<<<<<<<<<<<<<<");

        return $matchesCreated;
    }

    /**
     * For this case, we could have a large number of players involved in bringing the count
     * down to a power of 2 for the subsequent round. So this is considered the first round
     * and only a few byes need to be defined. It is likely that only seeded players will get the byes.
     */
    private function processByes( Bracket $bracket, array &$seeded, array &$unseeded ) {
        
        $loc = __CLASS__ . "::" . __FUNCTION__;

        error_log(' ');
        $numInvolved = 2 * $this->numToEliminate;
        $remainder   = $numInvolved > 0 ? $bracket->signupSize() - $numInvolved : 0;
        $highMatchnum = ceil( $bracket->signupSize() / 2 );
        $lowMatchnum = 1;
        $usedMatchNums = array();
        $lastSlot = 0;
        
        $initialRound = 1;
        $seedByes = 0;
        $unseedByes = 0;
        $seedByes    =  min( count( $seeded ) , $remainder );
        $unseedByes  = $remainder - $seedByes;

        //Add seeded players as Bye matches using an even distribution
        $slot = ($seedByes + $unseedByes) > 1 ? ceil( ceil( $bracket->signupSize() / 2.0 ) / ($seedByes + $unseedByes) ) : 0;
        $slot = max( 2, $slot );

        error_log( sprintf(">>>>>%s -> bracket=%s seeds=%d; unseeded=%d", $loc, $bracket->getName(), count( $seeded ), count( $unseeded ) ) );
        error_log( sprintf("     %s -> bracket=%s slot=%d; numInvolved=%d; remainder=%d; highMatchNum=%d; seedByes=%d; unseedByes=%d", $loc, $bracket->getName(), $slot, $numInvolved, $remainder, $highMatchnum, $seedByes, $unseedByes) );

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
            $match = new Match( $this->event->getID(), $bracket->getBracketNumber(), $initialRound, $lastSlot );
            $match->setIsBye( true );
            $match->setHomeEntrant( $home );
            $match->setMatchType( $this->matchType );
            $bracket->addMatch( $match );
            error_log( sprintf( "%s -> added bye for seeded player %s to round %d using match number %d", $loc, $home->getName(), $initialRound, $lastSlot ) );
        }

        $matchnum = $lowMatchnum;
        for( $i = 0; $i < $unseedByes; $i++ ) {
            $home = array_shift( $unseeded );
            $mn = $matchnum++;
            $mn = $this->getNextAvailable( $usedMatchNums, $mn );
            array_push( $usedMatchNums, $mn );
            $match = new Match( $this->event->getID(), $bracket->getBracketNumber(), $initialRound, $mn );
            $match->setIsBye( true );
            $match->setHomeEntrant( $home );
            $match->setMatchType( $this->matchType );
            $bracket->addMatch( $match );            
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
                
                $match   = new Match( $this->event->getID(), $bracket->getBracketNumber(), $initialRound, $lastSlot );
                $match->setHomeEntrant( $home );
                $match->setVisitorEntrant( $visitor );
                $match->setMatchType( $this->matchType );
                $bracket->addMatch( $match );  
                error_log( sprintf( "%s -> added match for seeded player '%s' vs unseeded '%s' in round %d using match number %d", $loc, $home->getName(), $visitor->getName(), $initialRound, $lastSlot ) );                  
            }
            else {
                $home    = array_shift( $unseeded );
                $visitor = array_shift( $unseeded );

                $mn = $matchnum++;
                $mn = $this->getNextAvailable( $usedMatchNums, $mn );
                array_push( $usedMatchNums, $mn );

                $match = new Match( $this->event->getID(), $bracket->getBracketNumber(), $initialRound, $mn );
                $match->setHomeEntrant( $home );

                if( !is_null( $visitor ) ) $match->setVisitorEntrant( $visitor );
                else $match->setIsBye( true );

                $match->setMatchType( $this->matchType );
                $bracket->addMatch( $match );
                error_log( sprintf( "%s -> added match for unseeded players '%s' vs '%s' in round %d using match number %d", $loc, $home->getName(), $visitor->getName(), $initialRound, $mn ) );
            }
            ++$ctr;
        }
        error_log('>>>>>');
    }

    /**
     * An advanced or "challenger" round is required when only a very few players are involved in order to bring 
     * the count down to a power of 2. There are no byes but the challenger round happens before round one of the tournament.
     */
    private function processChallengerRound( Bracket $bracket, array &$seeded, array &$unseeded ) {

        $loc = __CLASS__ . "::" . __FUNCTION__;

        error_log(' ');
        $this->hasChallengerRound = true;
        $numInvolved = 2 * $this->numToEliminate;
        $remainder   = $bracket->signupSize() - $numInvolved;
        $highMatchnum    = ceil( $bracket->signupSize() / 2 );
        $lowMatchnum = 1;
        $matchnum = $lowMatchnum;
        $usedMatchNums = array();
        $lastSlot = 0;
        
        $initialRound = 0; // early round to bring round 1 to a power of 2
        $seedByes     = 0;
        $unseedByes   = 0;

        //Add seeded players as Bye matches using an even distribution
        $slot = ($seedByes + $unseedByes) > 1 ? ceil( ceil( $bracket->signupSize()  / 2.0 ) / ($seedByes + $unseedByes) ) : 0;
        $slot = max( 2, $slot );
        
        error_log( sprintf(">>>>>%s -> bracket=%s seeds=%d; unseeded=%d", $loc, $bracket->getName(), count( $seeded ), count( $unseeded ) ) );
        error_log( sprintf("     %s -> bracket=%s slot=%d; numInvolved=%d; remainder=%d; highMatchNum=%d; seedByes=%d; unseedByes=%d", $loc, $bracket->getName(), $slot, $numInvolved, $remainder, $highMatchnum, $seedByes, $unseedByes) );


        //Create the challenger round using unseeded players from end of list
        //Note that $numInvolved is always an even number
        for( $i = $numInvolved; $i > 0; $i -= 2 ) {
            $home    = array_pop( $unseeded );
            $visitor = array_pop( $unseeded );
            
            $mn = $matchnum++;
            array_push( $usedMatchNums, $mn );

            $match = new Match( $this->event->getID(), $bracket->getBracketNumber(), $initialRound, $mn );
            $match->setHomeEntrant( $home );
            $match->setVisitorEntrant( $visitor );
            $match->setMatchType( $this->matchType );
            $bracket->addMatch( $match );
            error_log( sprintf( "%s -> added  unseeded players '%s' vs '%s' to challenger round %d with match num=%d"
                              , $loc, $home->getName(),$visitor->getName(), $initialRound, $mn ) );
        }
        
        ++$initialRound;
        //Schedule the odd player to wait for the winner of the last challenger round
        if( (1 & $remainder) ) {
            $home = array_shift( $unseeded );
            if( !isset( $home ) ) $home = array_shift( $seeded );
            if( isset( $home ) ) {
                $mn = $matchnum++;
                $match = new Match( $this->event->getID(), $bracket->getBracketNumber(), $initialRound, $mn );
                array_push( $usedMatchNums, $mn );
                $match->setHomeEntrant( $home );
                $match->setMatchType( $this->matchType );
                $bracket->addMatch( $match );
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
                
                $match = new Match( $this->event->getID(), $bracket->getBracketNumber(), $initialRound, $lastSlot );
                $match->setHomeEntrant( $home );
                
                if( !is_null( $visitor ) ) $match->setVisitorEntrant( $visitor );
                $match->setMatchType( $this->matchType );
                $bracket->addMatch( $match ); 
                error_log( sprintf( "%s -> added match for seeded player '%s' vs unseeded '%s'  in round %d using match number %d"
                                  , $loc, $home->getName(), $visitor->getName(),$initialRound, $lastSlot ) );                   
            }
            else {
                $home    = array_shift( $unseeded );
                $visitor = array_shift( $unseeded );

                $mn = $matchnum++;
                $mn = $this->getNextAvailable( $usedMatchNums, $mn );
                array_push( $usedMatchNums, $mn );

                $match = new Match( $this->event->getID(), $bracket->getBracketNumber(), $initialRound, $mn );
                $match->setHomeEntrant( $home );

                //If not paired with a visitor then this match is waiting for
                // a winner from the challenger round. The opposite of a BYE.
                if( !is_null( $visitor ) ) $match->setVisitorEntrant( $visitor );

                $match->setMatchType( $this->matchType );
                $bracket->addMatch( $match );               
                 error_log( sprintf( "%s -> added match for unseeded players '%s' vs '%s' in round %d using match number %d"
                                   , $loc, $home->getName(), $visitor->getName(), $initialRound, $mn ) );
            }
            ++$ctr;
        }
        error_log('>>>>>');
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
    private function calculateEventSize( string $bracketName = Bracket::WINNERS ) {
        $this->numToEliminate = 0;
        $this->numRounds = 0;

        if(!$this->event->isLeaf()) {
            $mess = __( 'Must be a leaf event to generate rounds.', TennisEvents::TEXT_DOMAIN );
            throw new InvalidEventException( $mess );
        }

        $bracket = $this->event->getBracket( $bracketName );
        if( !isset( $bracket ) ) {
            throw new InvalidBracketException( __("$bracketName bracket is not defined.", TennisEvents::TEXT_DOMAIN) );
        }

        $minplayers = $bracketName === Bracket::WINNERS ? self::MINIMUM_ENTRANTS : self::MINIMUM_ENTRANTS / 2;
        if( $bracket->signupSize() < $minplayers ) {
            $min = self::MINIMUM_ENTRANTS;
            $mess = __( "Event/bracket must have at least $min entrants for an elimination event.", TennisEvents::TEXT_DOMAIN );
            throw new InvalidEventException( $mess );
        }

        $this->numRounds = $this->calculateExponent( $bracket->signupSize() );
        $this->numToEliminate = $bracket->signupSize() - pow( 2, $this->numRounds );

        return $this->numToEliminate;
    }

    /**
     * Given the size of the draw (or any integer), calculate the highest 
     * power of 2 which is less than that size (or integer)
     */
	private function calculateExponent( int $size ) {
        $exponent = 0;
        foreach( range(1,8) as $exp ) {
            if( pow( 2, $exp ) > $size ) {
                $exponent = $exp - 1;
                break;
            }
        }
        return $exponent;
    }
}