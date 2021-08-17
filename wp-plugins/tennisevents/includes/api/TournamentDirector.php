<?php
namespace api;

use commonlib\Math_Combinatorics;
use commonlib\BaseLogger;
use commonlib\GW_Support;
use \TennisEvents;
use datalayer\Event;
use datalayer\Bracket;
use datalayer\Match;
use datalayer\EventType;
use datalayer\MatchType;
use datalayer\Entrant;
use datalayer\Format;
use datalayer\InvalidBracketException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$p2Dir = plugin_dir_path( plugin_dir_path( __DIR__ ) );
//require_once( $p2Dir . 'tennisevents.php' );
require_once( 'api-exceptions.php' );

/** 
 * Responsible for putting together the necessary Events and schedule for a Tournament
 * Calculates the inital rounds of tournament; encapsulates the event, its brackets and scoring of matches
 * Responsible for determining the ultimate champion in any contest.
 * Composes several data level functions for Events, Brackets, Matches as it caches all of these from the db.
 * Other components or functions should expect to get any data re an event's brackets, matches or sets from the
 * Tournament Director.
 * @class  TournamentDirector
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
*/
class TournamentDirector
{ 

    public const MINIMUM_ENTRANTS = 8; //minimum for an elimination tournament
    public const MAXIMUM_ENTRANTS = 256; //maximum for an elimination tournament
    
    public const MINIMUM_RR_ENTRANTS = 3; //minimum for a round robin tournament

    private $numToEliminate = 0; //The number of players to eliminate to result in a power of 2
    private $numRounds = 0; //Total number of rounds for this tournament; calculated based on signup
    //private $hasChallengerRound = false; //Is a challenger round required
    private $matchType; //The type of match such as singles or doubles
    private $name = '';
    private $event = null;

    private $log;
    
    /**************************************************  Public functions ********************************************************** */

    /**
     * Given the size of the draw (or any integer), calculate the highest 
     * power of 2 which is greater than or equal to that size (or integer)
     * @param int $size 
     * @param int $upper The upper limit of the search; default is 8
     * @return int The exponent if found; zero otherwise
     */
	public static function calculateExponent( int $size, $upper = 8 ) {
        $exponent = 0;
        foreach( range( 1, $upper ) as $exp ) {
            if( pow( 2, $exp ) >= $size ) {
                $exponent = $exp;
                break;
            }
        }
        return $exponent;
    }

    public function __construct( Event $evt ) {
        $this->log = new BaseLogger( true );
        $this->event = $evt;
        $this->getAllDescendants();
        
        $this->matchType = $this->event->getMatchType();
        $this->name = $this->event->getName() . '-' . MatchType::AllTypes()[$this->matchType];
    }

    public function __destruct() {
        unset( $this->event );
    }

    /**
     * Retrieve all descendants from the db.
     * For the encapsulated event this includes all brackets, their matches and their sets.
     * This is to ensure that the TournamentDirector is caching all of the relevant data of a given event.
     * This should be ths source of all of this data without need to go to the db.
     * @param Event $event; The event for which all brackets, matches and sets are to be retrieved
     */
    private function getAllDescendants( Event $event = null ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        $numBrackets = 0;
        $numMatches = 0;
        $numSets = 0;
        if( empty( $event ) ) $event = $this->event;
        foreach( $event->getBrackets( true ) as $bracket ) {
            ++$numBrackets;
            foreach( $bracket->getMatches( true ) as $match ) {
                ++$numMatches;
                foreach( $match->getSets( true ) as $set ) {
                    ++$numSets;
                } //sets
                $match->getHomeEntrant();
                $match->getVisitorEntrant();
            } //matches
        } //brackets
        $this->log->error_log("{$loc} loaded {$numBrackets} brackets; {$numMatches} matches; {$numSets} sets");
    }

    /**
     * Get the name of the tournament
     * @return string Name of the tournament (i.e. name of the underlying Event)
     */
    public function tournamentName() {
        return $this->name;
    }

    /**
     * Get the event for this tournament
     * @return object The underlying Event object
     */
    public function getEvent() {
        return $this->event;
    }

    /**
     * Get the underlying event's ID.
     */
    public function getEventId() :int {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        $id = 0;
        if( !is_null( $this->event) ) {
            $id = $this->event->getID();
        }
        return $id;
    }

    /**
     * Get the underlying event's parent event's name.
     */
    public function getParentEventName() {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        $pname = '';
        if( !is_null( $this->event) ) {
            if( $this->event->isLeaf() ) {
                $pname = $this->event->getParent()->getName();
            }
        }
        return $pname;
    }

    /**
     * Get the URL for the event's underlying post object 
     */
    public function getPermaLink() {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        
        $link = "#";
        if( !is_null( $this->event) ) {
            $refs = $this->event->getExternalRefs();
            if( count($refs) > 0 ) {
                $postId = $refs[0];
                $link = get_permalink($postId, false);
            }
        }
        return $link;
    }

    /**
     * Get the name of the Tennis Club for this tournament
     * @return string Name of the club; defaults to home club
     */
    public function getClubName() {
        $clubName = '';
        $homeClubId = esc_attr( get_option('gw_tennis_home_club', 0) );
        $clubs = $this->getEvent()->getClubs();
        foreach( $clubs as $club ) {
            if( $homeClubId === $club->getID()) {
                $clubName = $club->getName();
            }
        }
        return $clubName;
    }

    /**
     * Get the the ID of Tennis Club for this tournament
     * @return int Home club Id; defaults to home club
     */
    public function getClubId() {
        $homeClubId = esc_attr( get_option('gw_tennis_home_club', 0) );
        return $homeClubId;
        // $clubs = $this->getEvent()->getClubs();
        // $found = false;
        // foreach( $clubs as $club ) {
        //     if( $homeClubId === $club->getID()) {
        //         $found = true;
        //     }
        // }
        // return $found ? $homeClubId : 0;
    }

    /**
     * Get the Match Type for this tournament
     * @return float Match type
     */
    public function matchType():float {
        return $this->matchType;
    }

    /**
     * Get the ChairUmpire for match controlled by this Touornament Director
     * @param int $scoretype which is a bitmask of scoring types
     * @see ScoreType class
     * @return object ChairUmpire subclass
     */
    public function getChairUmpire( ) : ChairUmpire {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        
        $chairUmpire = null;
        if( empty( $this->getEvent() ) ) return $chairUmpire;
        
        $format = $this->getEvent()->getFormat();
        $scoretype = $this->getEvent()->getScoreType();
        $chairUmpire = ChairUmpire::getUmpire( $scoretype );
        
        return $chairUmpire;
    }

    /**
     * Generates a bracket template for review and approval
     * For double elimination, the following rules apply:
     *   1. A separate Consolation/Losers bracket is used
     *   2. The Losers bracket must be approved too.
     *   3. Approval should happen after appropriate modifications
     *      have been made; such as moving the prelimary matches around.
     *  ...
     * Once approved, the brackets cannot be modified, only deleted.
     * @param string $bracketName of the Bracket within the event
     * @return array Match hierarchy for the bracket
     */
    public function approve( $bracketName ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        $this->log->error_log("$loc($bracketName");

        $bracket = $this->getBracket( $bracketName );
        if( is_null( $bracket ) ) {
            throw new InvalidBracketException( __("No such bracket: $bracketName.", TennisEvents::TEXT_DOMAIN) );
        }

        $matchHierarchy = $bracket->approve();
        $this->save();

        return $matchHierarchy;
    }

    /**
     * Advance completed matches to their respective next rounds.
     * @param string $bracketName name of the bracket
     * @return int Number of entrants advanced
     */
    public function advance( $bracketName ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;        
        $calledBy = isset(debug_backtrace()[1]['class']) ? debug_backtrace()[1]['class'] . '::'. debug_backtrace()[1]['function'] : debug_backtrace()[1]['function'];

        $this->log->error_log("$loc($bracketName) called by {$calledBy}");

        $format = $this->getEvent()->getFormat( $bracketName );
        switch( $format ) {
            case Format::ELIMINATION:
                return $this->advanceSingleElimination( $bracketName );
            break;
            case Format::ROUNDROBIN:
                return $this->advanceRoundRobin( $bracketName );
            break;
        }
    }

    /**
     * Advance completed matches. 
     * What does this mean for a Round Robin format as all rounds and matches are set.
     * @param string $bracketName name of the bracket
     * @return int 0
     */
    private function advanceRoundRobin( $bracketName ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        $this->log->error_log("$loc($bracketName)");

        $bracket = $this->getBracket( $bracketName );

        if( is_null( $bracket ) ) {
            throw new InvalidTournamentException( __( "Invalid bracket name $bracketNname.", TennisEvents::TEXT_DOMAIN) );
        }

        if( !$bracket->isApproved() ) {
            throw new InvalidTournamentException( __( "Bracket has not been approved.", TennisEvents::TEXT_DOMAIN) );        
        }

        return 0;
    }
    
    /**
     * Get the highest round number for this bracket
     * NOTE: This round should have only one match in it
     */
    public function getLastRoundNumber( $bracketName = Bracket::WINNERS ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        $this->log->error_log("$loc($bracketName");

        return GW_Support::calculateExponent( $this->signupSize( $bracketName ) );
    }

    /**
     * Retrieve the Champion for this bracket
     * @param String $bracketName
     * @return Entrant who won the bracket or null if not completed
     * @see class ChairUmpire
     */
    public function getChampion( $bracketName = Bracket::WINNERS ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        $this->log->error_log("$loc($bracketName)");

        $bracket = $this->getBracket( $bracketName ); 
        
        return $this->getChairUmpire()->getChampion( $bracket ); 
    }

    /**
     * Advance completed matches to their respective next rounds
     * in a single elimination event. And save the results.
     * @param string $bracketName name of the bracket
     * @return mixed Number of entrants advanced or name of the Champion
     */
    private function advanceSingleElimination( $bracketName ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;        

        $this->log->error_log("$loc($bracketName)");

        $bracket = $this->getBracket( $bracketName );

        if( is_null( $bracket ) ) {
            throw new InvalidTournamentException( __( "Invalid bracket name {$bracketNname}.", TennisEvents::TEXT_DOMAIN) );
        }

        if( !$bracket->isApproved() ) {
            throw new InvalidTournamentException( __( "Bracket has not been approved.", TennisEvents::TEXT_DOMAIN) );        
        }

        $matches = $bracket->getMatches();
        $umpire = $this->getChairUmpire();
        $lastRound = $this->getLastRoundNumber( $bracketName );

        $numAdvanced = 0;
        foreach( $matches as $match ) {  
            $title = $match->title();
            
            $isLastRound = $lastRound === $match->getRoundNumber();
            //We don't advance the last match of the bracket
            // if( $isLastRound ) {
            //     $this->log->error_log( "$loc: '$title' in last round=$lastRound");
            //     break;
            // }

            $nextMatch = $bracket->getMatch( $match->getNextRoundNumber(), $match->getNextMatchNumber() );
            if( is_null( $nextMatch ) && !$isLastRound ) {
                //When the bracket is approved all matches from preliminary to the end of the 
                // tournament are generated. So we should not have the case where a next match
                // is null until the very last match
                $mess = "Match '{$title}' has invalid next match pointers.";
                $this->log->error_log( $mess );
                throw new InvalidTournamentException( $mess );
            }

            $winner = null;
            if( $umpire->isLocked( $match, $winner ) ) {

                if( is_null( $winner ) ) {
                    $mess = "Match $title is locked but cannot determine winner.";
                    $this->log->error_log( $mess );
                    throw new InvalidTournamentException( $mess );
                }

                //If we have a winner from the match in last round
                // then this is the champion of the tournament
                if( $isLastRound ) {
                    $numAdvanced = $winner->getName(); //i.e. champion
                    break;
                } 

                $this->log->error_log("$loc: attempting to advance match: $title");

                $nextTitle = $nextMatch->title();
                $this->log->error_log( "$loc: next match: $nextTitle" );
                
                if( $nextMatch->isWaiting() ) {
                    if( $match->getMatchNumber() & 1 ) {
                        $nextMatch->setHomeEntrant( $winner );
                    }
                    else {
                        $nextMatch->setVisitorEntrant( $winner );
                    }
                    $nextMatch->save();
                    $bracket->setDirty();
                    $nextMatch->setIsBye( false );
                    $numAdvanced += 0.5;                    
                    $this->log->error_log( sprintf( "%s --> %d. Advanced winner %s of match %s to next match %s"
                                                  , $loc, $numAdvanced, $winner->getName(), $match->toString(), $nextMatch->toString() ) );
                }
                else {
                    $this->log->error_log( sprintf( "%s. Did NOT advance winner %s of match %s to next match %s because it is NOT waiting."
                                                  , $loc, $winner->getName(), $match->toString(), $nextMatch->toString() ) );
                }                
            }
        } //foreach

        $this->save();
        return $numAdvanced;
    }

    /**
     * Get the name of the underlying Event
     * @return string Name of the underlying event
     */
    public function getName() {
        return $this->event->getName();
    }

    /**
     * Save the results from addEntrant, etc.
     * Calls save on the underlying Event.
     * NOTE: if significant changes are made to the underlying Event
     *       one should save these earlier.
     * @return int The number of rows affected
     */
    public function save() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		$calledBy = debug_backtrace()[1]['function'];
        error_log("{$loc} ... called by {$calledBy}");

        //TODO: This should spiral down thru all brackets, matches and sets.
        //      Needs to be tested and fixed.
        return $this->event->save();
    }

    /**
     * Traverse the Bracket to find the first incomplete Round
     * @param string $bracketName
     * @return int Number of the current round
     */
    public function currentRound( string $bracketName = Bracket::WINNERS ):int {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        
        $currentRound = -1;
        $umpire = $this->getChairUmpire();
        $bracket = $this->getBracket( $bracketName );
        $totalRounds = $this->totalRounds( $bracketName );
        for($i = 0; !is_null( $bracket ) && $i < $totalRounds; $i++ ) {
            foreach( $bracket->getMatchesByRound( $i ) as $match ) {
                $status = $umpire->matchStatus( $match );
                // $mess = sprintf( "%s(%s) -> i=%d status='%s'"
                //                , $loc, $match->toString()
                //                , $i, $status );
                //$this->log->error_log( $mess );
                if( $status === ChairUmpire::NOTSTARTED || $status === ChairUmpire::INPROGRESS ) {
                    $currentRound = $i;
                    break;
                }
            }
            if( $currentRound !== -1 ) break;
        }
        $this->log->error_log( sprintf( "%s -> returning bracket %s's current round=%d", $loc,$bracketName, $currentRound ) );
        return $currentRound;
    }

    /**
     * Check if the bracket has started or not
     * @param string $bracketName
     * @return bool True if started False otherwise
     */
    public function hasStarted( string $bracketName ) { 
        $loc = __CLASS__ . "::" . __FUNCTION__;
        
        $started = false;
        $umpire = $this->getChairUmpire();
        $bracket = $this->getBracket( $bracketName );
        if( !is_null( $bracket ) ) {
            foreach( $bracket->getMatches() as $match ) {
                $status = $umpire->matchStatus( $match );
                $this->log->error_log( sprintf( "%s(%s) -> %s's bracket has status='%s'", $loc, $match->toString(), $bracketName, $status ) );
                if( $status != ChairUmpire::NOTSTARTED && $status != ChairUmpire::BYE && $status != ChairUmpire::WAITING ) {
                    $started = true;
                    break;
                }
            }
        }
        else {
            $started = true;
        }
        $this->log->error_log( sprintf( "%s->returning started=%d for bracket '%s'", $loc, $started, $bracketName ) );
        return $started;
    }
    
    /**
     * The total number of rounds for this tennis event.
     * @param string $bracketName
     * @return int Total number of rounds in the bracket
     */
    public function totalRounds( string $bracketName = Bracket::WINNERS ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;

        $bracket = $this->getBracket( $bracketName );
        switch($this->getEvent()->getFormat()) {
            case Format::ELIMINATION:
                $this->numRounds = GW_Support::calculateExponent( $bracket->signupSize() );
                //$this->numRounds = $bracket->getNumberOfRounds();
            break;
            case Format::ROUNDROBIN:
                $this->numRounds = $bracket->getNumberOfRounds();
                break;
            default:
                $this->numRounds = 0;
        }

        return $this->numRounds;
    }



    /**
     * Get ths signup for this tournament sorted by position in the draw
     * @param string $bracketName
     * @return array Entrants signed up for the bracket
     */
    public function getSignup( $bracketName = Bracket::WINNERS ) { 
        $loc = __CLASS__ . "::" . __FUNCTION__;
        $this->log->error_log("$loc: bracketName={$bracketName}");

        $entrants = array();
        $bracket = $this->getBracket( $bracketName );
        if( !is_null( $bracket ) ) {
            $entrants = $bracket->getSignup();
            usort( $entrants, array( __CLASS__, 'sortByPositionAsc' ) );
        }

        return $entrants;
    }
    
    /**
     * Return the size of the signup for the tennis event
     * @param string $bracketName
     * @return int Size of the signup for the bracket
     */
    public function signupSize( $bracketName = Bracket::WINNERS ) {
        return count($this->getSignup( $bracketName ) );
    }

    /**
     * Remove the signup and all brackets (and matches) for a tennis event/bracket
     * @param string $bracketName
     * @return int Number of rows affected
     */
    public function removeSignup( $bracketName = Bracket::WINNERS ) {
        $result = 0;
        $bracket = $this->getBracket( $bracketName );
        if( is_null( $bracket ) ) return $result;

        if( $this->hasStarted( $bracket->getName() ) ) {
            throw new InvalidTournamentException( "Cannot remove signup because tournament has started." );
        }

        $bracket->removeSignup();
        $result = $bracket->save();
        return $result;
    }

    /**
     * Resequence the positions in the signup
     */
    public function resequenceSignup( $bracketName = Bracket::WINNERS ) {
        $bracket = $this->getBracket( $bracketName );
        if( is_null( $bracket ) ) return;
        return $bracket->resequenceSignup();
    }

    /**
     * Get all the Brackets for the underlying Event
     * If the event has no brackets, then Winners and Consolation are created
     * @param bool $force True will force a db fetch
     * @return array Array of brackets for the underlying Event
     */
    public function getBrackets( $force = false ) {
        $brackets = $this->event->getBrackets( $force );
        // if( empty( $brackets ) ) {
        //     $this->event->getWinnersBracket();
        //     $this->event->getConsolationBracket();
        //     $brackets = $this->event->getBrackets();
        // }
        return $brackets;
    }

    /**
     * Get a bracket by name or number
     * @param  $bracketId The name or number of the bracket
     * @return Bracket if exists, null otherwise
     */
    public function getBracket( $bracketId ) {
        return $this->event->getBracket( $bracketId );
    }

    /**
     * Remove all brackets, signups and matches for a tennis event
     * Use with caution as it deletes all of an event's brackets, signups and matches from the database.
     */
    public function removeBrackets( ) {
        $result = 0;
        $this->event->removeBrackets();
        $result = $this->save();
        
        return $result;
    }
    /**
     * Add a new bracket to the underlying event
     * @param string $bracketName
     * @return object Bracket
     * 
     */
    public function addBracket( $bracketName ) {
        $result = 0;
        $bracket = $this->event->createBracket( $bracketName );
        $result = $this->save();
        return $bracket;
    }

    /**
     * Remove a bracket by name
     * @param string $bracketName
     */
    public function removeBracket( $bracketName ) {
        $result = false;
        $result = $this->event->removeBracket( $bracketName );
        return $result;
    }

    /**
     * Return all matches for an event or just for a given round
     * @param string $bracketName The bracket name
     * @param mixed $round The round whose matches are to be retrieved
     * @param bool $force Force reloading from database
     */
    public function getMatches( string $bracketName = Bracket::WINNERS, $round = null, $force = false ) {
        $matches = array();
        $bracket = $this->event->getBracket( $bracketName );
        if( is_null( $bracket ) ) {
            throw new InvalidTournamentException( __( "No such bracket '$bracketName' when getting matches." ) );
        }
        else {
            if( is_null( $round ) ) {
                $matches = $bracket->getMatches( $force );
                usort( $matches, array( __CLASS__, 'sortByRoundMatchNumberAsc' ) );
            }
            else {
                $matches = $bracket->getMatchesByRound( $round );
                usort( $matches, array( __CLASS__, 'sortByMatchNumberAsc' ) );
            }
        }
        
        return $matches;
    }

    /**
     * Remove all matches for a bracket
     * @param string $bracketName The name of the bracket
     */
    public function removeMatches( string $bracketName = Bracket::WINNERS ) {
        $bracket = $this->event->getBracket( $bracketName );
        if( !is_null( $bracket ) ) {
            if( $bracket->removeAllMatches() ) {
                $this->save();
                return true;
            }
            return false;
        }
        else {
            throw new InvalidTournamentException( __( "Bracket name $bracketName does not exist when trying to remove its matches." ) );
        }
    }
    
    /**
     * Return the count of all matches or just for a given round
     * @param $round The round whose matches are to be counted
     * @return Number of matches in bracket or in a round in a bracket
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
    public function addEntrant( string $name, int $seed = 0, string $bracketName = Bracket::WINNERS  ) {
        $result = 0;

        if( 0 < $this->getMatchCount( $bracketName ) ) {
            throw new InvalidTournamentException( __('Cannot add entrant because matches already exist.') );
        }

        $bracket = $this->getBracket( $bracketName );
        if( !is_null( $bracket ) ) {
            $bracket->addToSignup( $name, $seed );
            $result = $this->save();
        }
        return $result > 0;
    }

    /**
     * Remove an Entrant from the signup
     * @param $name The name of the player or doubles team
     */
    public function removeEntrant( string $name, string $bracketName = Bracket::WINNERS ) {
        $result = 0;

        if( 0 < $this->getMatchCount( $bracketName ) ) {
            throw new InvalidTournamentException( __('Cannot remove entrant because matches already exist.') );
        }
        
        $bracket = $this->getBracket( $bracketName );

        if( !is_null( $bracket ) ) {
            $bracket->removeFromSignup( $name );
            $result = $this->save();
        }
        return $result > 0;
    }
    
    /**
     * Move a match from its current spot to the target match number.
     * @param $fromPos The entrant's current position (i.e. place in the lineup)
     * @param $toPos The target position for the entrant
     * @return true if succeeded; false otherwise
     */
    public function moveEntrant( int $fromPos, int $toPos, string $bracketName = Bracket::WINNERS ) {
        $result = 0;
        $bracket = $this->getBracket( $bracketName );
        if( !is_null( $bracket ) ) {
            $result = $bracket->moveEntrant( $fromPos, $toPos );
        }
        return $result;
    }
    
    /**
     * Move a match from its current spot to the target match number.
     * @param $bracketName The name of the bracket
     * @param $round The round number of this match
     * @param $fromMatchNum The match's current number (i.e. place in the lineup)
     * @param $toMatchNum The intended place for this match
     * @return true if succeeded; false otherwise
     */
    public function moveMatch( Bracket $bracket, int $fromRoundNum, int $fromMatchNum, int $toMatchNum , string $cmts = null ) {
        $result = 0;
        if( isset( $bracket ) ) {
            try {
                $result = $bracket->moveMatch( $this->event->getID(), $bracket->getBracketNumber(), $fromRoundNum, $fromMatchNum, $toMatchNum, $cmts );
            }
            catch( Exception | InvalidMatchException $ex ) {
                $result = 0;
            }
        }
        return $result >= 1;
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

    /**
     * Set or remove comments on a match
     * @param $bracketName The name of the bracket
     * @param $round The round numberj
     * @param $match_num The match number
     * @param $comments The comments for the match
     * @return true if comments set; false otherwise
     */
    public function comment( $bracketName, $round, $match_num, $comment = '' ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        $this->log->error_log( "$loc($bracketName,$round,$match_num,'$comment')" ); 

        $result = false;
        $bracket = $this->event->getBracket( $bracketName );

        //Bracket must exist
        if( is_null( $bracket ) ) {
            throw new InvalidBracketException( __("Bracket does not exist for this event.", TennisEvents::TEXT_DOMAIN ) );
        }

        //Match must exist
        $match = $bracket->getMatch( $round, $match_num );
        if(is_null( $match ) ) {
            $this->log->error_log( sprintf( "%s --> Match %s does not exist in bracket %s", $loc, $match->title(), $bracket->getName() ) );
            return $result;
        }

        $result = $match->setComments( $comment );
        if( $result === true ) $this->save();
        return $result;
    }
   /**
     * Organize the initial matches depending on the Event Type of the underlying event 
     * @param string $bracketName
     * @param bool $randomizeDraw true if the signup should be randomized
     * @return int Number of matches created
     */
    public function schedulePreliminaryRounds( string $bracketName, $randomizeDraw = false ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        $this->log->error_log("$loc");

        switch( $this->getEvent()->getFormat() ) {
            case Format::ELIMINATION:
                return $this->initializeEliminationRounds( $bracketName, $randomizeDraw );
            case Format::ROUNDROBIN:
                return $this->initializeRoundRobin( $bracketName, true );
        }
        return 0;
    }

    /**
     * The purpose of this function is to eliminate enough players 
     * in the first round so that the next round has 2^n players 
     * and the elimination rounds can then proceed naturally to the end.
     * The next big question to work out is determining who gets the byes (if any).
     * Finally the seeded players (who get priority for bye selection) must be distributed
     * evenly amoung the un-seeded players with the first and second seeds being at opposite ends of the draw.
     * @param string $bracketName
     * @param bool $randomizeDraw boolean to indicate if the signup should be randomized
     * @return int Number of matches created
     */
    private function initializeEliminationRounds( string $bracketName, $randomizeDraw = false ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        $this->log->error_log( ">>>>>>>>>>>>>>>>>>>>>>>>>$loc called with bracket=$bracketName, randomize=$randomizeDraw" ); 

        //Get or create the requested bracket
        $mainbracket = null;
        $loserbracket = null;
        $chairUmpire = null;
        $minplayers = self::MINIMUM_ENTRANTS;
        $bracket = $this->getBracket( $bracketName );
        switch( $bracketName ) {
            case Bracket::WINNERS:
                //$bracket = $this->getEvent()->getWinnersBracket();
                $loserbracket = $this->getEvent()->getConsolationBracket();
                $mainbracket = $bracket;
                break;
            case Bracket::CONSOLATION:
                //$bracket = $this->getEvent()->getConsolationBracket();
                $mainbracket = $this->getEvent()->getWinnersBracket();
                $loserbracket = $bracket;
                $minplayers =  ceil(self::MINIMUM_ENTRANTS / 2);
                break;
            case Bracket::LOSERS:
            default:        
        }
        
        //Bracket must not be approved already
        if( $bracketName === Bracket::WINNERS && $bracket->isApproved() ) {
            throw new InvalidBracketException( __("Bracket already approved. Please reset first.", TennisEvents::TEXT_DOMAIN ) );
        }
        elseif( $bracketName === Bracket::CONSOLATION && !$mainbracket->isApproved() ) {
            throw new InvalidBracketException( __("$bracketName cannot be scheduled because main draw is not approved yet.", TennisEvents::TEXT_DOMAIN));
        }

        //Cannot schedule preliminary rounds if matches have already started
        if( 0 < $this->hasStarted( $bracket->getName() ) ) {
            throw new BracketHasStartedException( __('Cannot schedule preliminary matches for bracket because play as already started.') );
        }
        
        $matchesCreated = 0;
        $entrants = $bracket->getSignup( );
        $bracketSignupSize = count( $entrants );
        //Check minimum entrants constraint
        if( $bracketSignupSize < $minplayers ) {
            $mess = __( "Bracket must have at least {$minplayers} entrants for an elimination event. {$bracketSignupSize} entrants found.", TennisEvents::TEXT_DOMAIN );
            throw new InvalidBracketException( $mess );
        }
        $this->log->error_log( "$loc: signup size={$bracketSignupSize}" );


        //Remove any existing matches ... we know they have not started yet
        $this->removeMatches( $bracketName );
        $this->save();

        $this->numRounds = GW_Support::calculateExponent( $bracketSignupSize );
        $this->numToEliminate = $bracketSignupSize - pow( 2, $this->numRounds ) / 2;

        $unseeded = array_filter( array_map( function( $e ) { if( $e->getSeed() < 1 ) return $e; }, $entrants ) );
        
        if( $randomizeDraw ) shuffle( $unseeded );
        else usort( $unseeded, array( __CLASS__, 'sortByPositionAsc' ) );

        $seeded = array_filter( array_map( function( $e ) { if( $e->getSeed() > 0 ) return $e; }, $entrants ) );
        usort( $seeded, array( __CLASS__, 'sortBySeedAsc') );

        // $numInvolved = 2 * $this->numToEliminate;
        // $remainder   = $numInvolved > 0 ? $bracketSignupSize - $numInvolved : 0;

        // if($numInvolved > $remainder ) {
        //     $seedByes    =  min( count( $seeded ) , $remainder );
        //     $unseedByes  = $remainder - $seedByes;
        // }
        // else {
        //     $seedByes = min( count( $seeded ), $numInvolved );
        //     $unseedByes = $numInvolved - $seedByes;
        // }
        // $totalByes = $seedByes + $unseedByes;
        // $highMatchnum = ceil( $bracketSignupSize / 2 );
        // $this->log->error_log( "$loc: highMatchnum=$highMatchnum seedByes=$seedByes unseedByes=$unseedByes" );
        
        //Heavy lifting done here!
        $this->processByes( $bracket, $seeded, $unseeded );

        if( (count( $unseeded ) + count( $seeded )) > 0 ) {
            throw new InvalidTournamentException( __( "Did not schedule all players into initial rounds." ) );
        }

        $matchesCreated += $bracket->getNumberOfMatches();
        $this->save();

        $this->log->error_log("<<<<<<<<<<<<<<<<<<<<<<<$loc<<<<<<<<<<<<<<<<<<<<<<<<<<<");

        return $matchesCreated;
    }
        
    /**************************************************  Private functions ********************************************************** */
    
    /**
     * For this case, we could have a large number of players involved in bringing the count
     * down to a power of 2 for the subsequent round. So this is considered the first round
     * and only a few byes need to be defined. It is likely that only seeded players will get the byes.
     */
    private function processByes( Bracket $bracket, array &$seeded, array &$unseeded ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        
        $bracketSignupSize = count( $seeded ) + count( $unseeded );
        $this->numRounds = GW_Support::calculateExponent( $bracketSignupSize );
        $this->numToEliminate = $bracketSignupSize - pow( 2, $this->numRounds ) / 2;

        $this->log->error_log("++++$loc: numRounds={$this->numRounds} and numToEliminate={$this->numToEliminate}");

        $numInvolved = 2 * $this->numToEliminate;
        $remainder   = $numInvolved > 0 ? $bracketSignupSize - $numInvolved : 0;
        $highMatchnum = ceil( $bracketSignupSize / 2 );
        $lowMatchnum = 1;
        $usedMatchNums = array();
        $lastSlot = 0;
        $highestMatchnumUsed = 3 * $highMatchnum;
        
        $initialRound = 1;
        $seedByes = $unseedByes = 0;
        $seedByes = min( count( $seeded ) , $remainder );
        $unseedByes = $remainder - $seedByes;

        //Add seeded players as Bye matches using an even distribution
        //$slot = ($seedByes + $unseedByes) > 1 ? ceil( ceil( $bracketSignupSize / 2.0 ) / ($seedByes + $unseedByes) ) : 0;
        $slot = ($seedByes + $unseedByes) > 1 ? ceil( ceil( $bracketSignupSize / 2.0 ) / count( $seeded ) ) : 0;
        $slot = max( 2, $slot );

        $this->log->error_log( sprintf(">>>>>%s -> bracket=%s seeds=%d; unseeded=%d", $loc, $bracket->getName(), count( $seeded ), count( $unseeded ) ) );
        $this->log->error_log( sprintf("     %s -> bracket=%s slot=%d; numInvolved=%d; remainder=%d; highMatchNum=%d; seedByes=%d; unseedByes=%d"
                                        ,$loc, $bracket->getName(), $slot, $numInvolved, $remainder, $highMatchnum, $seedByes, $unseedByes) );

        for( $i = 0; $i < $seedByes; $i++ ) {
            if( 0 === $i ) {//first seeded player
                $lastSlot = $lowMatchnum++;
                array_push( $usedMatchNums, $lastSlot );
            }
            else if( 1 === $i ) {//second seeded player
                $lastSlot = $highestMatchnumUsed;
                array_push( $usedMatchNums, $lastSlot );
            }
            else {//remaining seeded players
                $lastSlot = ($i * $slot);// + $lowMatchnum;
                array_push( $usedMatchNums, $lastSlot );
            }
            $home = array_shift( $seeded );
            $match = new Match( $this->event->getID(), $bracket->getBracketNumber(), $initialRound, $lastSlot );
            $match->setIsBye( true );
            $match->setHomeEntrant( $home );
            $match->setMatchType( $this->matchType );
            $bracket->addMatch( $match );
            $this->log->error_log( sprintf( "%s -> added bye for seeded player %s to round %d using match number %d", $loc, $home->getName(), $initialRound, $lastSlot ) );
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
            $this->log->error_log( sprintf( "%s -> added bye for unseeded player %s in round %d using match number %d", $loc, $home->getName(), $initialRound, $mn ) );
        }

        //Set the first lot of matches starting from end of the line
        $ctr = 1;
        while( count( $unseeded ) > 0 || count( $seeded ) > 0 ) {
            $numSeeded = count($seeded);
            $numUnseeded = count($unseeded);
            $this->log->error_log("$loc:$ctr. numSeeded=${numSeeded} numUnSeeded=${numUnseeded}");

            if( count( $seeded ) > 0 ) {
                $home    = array_shift( $seeded );
                $visitor = array_shift( $unseeded );

                if( $ctr & 2 ) {
                    $lastSlot = (int)($highMatchnum - $ctr * $slot);
                    if(0 === $lastSlot) $lastSlot = 1;
                }
                else {          
                    $lastSlot = (int)($lowMatchnum  + $ctr * $slot);
                }
                $this->log->error_log("$loc:$ctr. lastSlot=$lastSlot ");

                if( 0 === $lastSlot ) {
                    $this->log->error_log("$loc: lastSlot is zero! with ctr=$ctr");
                    throw new InvalidBracketException( __("$loc: lastSlot is zero! with ctr=$ctr", TennisEvents::TEXT_DOMAIN ) );
                }

                $lastSlot = $this->getNextAvailable( $usedMatchNums, $lastSlot );
                array_push( $usedMatchNums, $lastSlot );
                
                $match   = new Match( $this->event->getID(), $bracket->getBracketNumber(), $initialRound, $lastSlot );
                $match->setHomeEntrant( $home );
                $match->setVisitorEntrant( $visitor );
                $match->setMatchType( $this->matchType );
                $bracket->addMatch( $match );  
                $this->log->error_log( sprintf( "%s -> added match for seeded player '%s' vs unseeded '%s' in round %d using match number %d", $loc, $home->getName(), $visitor->getName(), $initialRound, $lastSlot ) );                  
            }
            else {
                $home    = array_shift( $unseeded );
                $visitor = array_shift( $unseeded );

                $mn = $matchnum++;
                $mn = $this->getNextAvailable( $usedMatchNums, $mn );
                $this->log->error_log("$loc:$ctr. lastSlot=$mn");

                array_push( $usedMatchNums, $mn );

                $match = new Match( $this->event->getID(), $bracket->getBracketNumber(), $initialRound, $mn );
                $match->setHomeEntrant( $home );

                $visitorName = '';
                if( !is_null( $visitor ) ) {
                    $match->setVisitorEntrant( $visitor );
                    $visitorName = $visitor->getName();
                }
                else {
                    $match->setIsBye( true );
                }

                $match->setMatchType( $this->matchType );
                $bracket->addMatch( $match );
                $this->log->error_log( sprintf( "%s -> added match for unseeded players '%s' vs '%s' in round %d using match number %d", $loc, $home->getName(), $visitorName, $initialRound, $mn ) );
            }
            ++$ctr;
        }

        //Now fix the last match (usually the second seed) so that 
        // its match number is sequential
        $prevMatchNum = 0;
        $this->log->error_log("$loc: Fixing match number $highestMatchnumUsed");
        foreach( $bracket->getMatchesByRound($initialRound) as $match ) {
            $m = $match->getMatchNumber();
            $this->log->error_log("$loc: checking M(1,$m)");
            if($m == $highestMatchnumUsed) {
                $newMatchNum = $prevMatchNum + 1;
                $match->setMatchNumber($newMatchNum);
                $this->log->error_log("$loc: changing M(1,$m) to M(1,$newMatchNum)");
            }
            $prevMatchNum = $m;
        }
        $this->log->error_log("----$loc");
    }
    
    /**
     * Initalize matches using Round Robin format
     * @param string $bracketName
     * @param bool $randomizeDraw boolean to indicate if the signup should be randomized
     * @return int Number of matches created
     */
    private function initializeRoundRobin( string $bracketName, $randomizeDraw = false ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        $this->log->error_log( ">>>>>>>>>>>>>>>>>>>>>>>>>$loc called with bracket=$bracketName, randomize=$randomizeDraw" ); 

        //Get or create the requested bracket
        $mainbracket = null;
        $loserbracket = null;
        $minplayers =  self::MINIMUM_RR_ENTRANTS;
        $bracket = $this->getBracket( $bracketName );

        
        //Bracket must not be approved already
        if( $bracket->isApproved() ) {
            throw new InvalidBracketException( __("Bracket already approved. Please reset first.", TennisEvents::TEXT_DOMAIN ) );
        }

        //Cannot schedule preliminary rounds if matches have already started
        if( 0 < $this->hasStarted( $bracket->getName() ) ) {
            throw new BracketHasStartedException( __('Cannot schedule preliminary matches for bracket because play as already started.') );
        }
        
        $matchesCreated = 0;
        $entrants = $bracket->getSignup();
        $bracketSignupSize = count( $entrants );
        //Check minimum entrants constraint
        if( $bracketSignupSize < $minplayers ) {
            $mess = __( "Bracket must have at least $minplayers entrants for an elimination event. $bracketSignupSize entrants found.", TennisEvents::TEXT_DOMAIN );
            throw new InvalidBracketException( $mess );
        }
        $this->log->error_log( "$loc: signup size=$bracketSignupSize" );

        //Remove any existing matches ... we know they have not started yet
        $this->removeMatches( $bracketName );
        $this->save();

        //$this->numRounds = $bracketSignupSize * ( $bracketSignupSize - 1 ) / 2;
        $this->numToEliminate = 0;

        
        //Heavy lifting done here!
        //1. Get contestants
        $contestants = array_map( function( $e ) { return $e->getName(); }, $entrants);
        
        //2. Shuffle the matches
        if( $randomizeDraw ) shuffle( $contestants );

        //3. Get combinations in groups of 2
        // NOTE: (n choose k) = n!/k!(n-k)! where in this case k=2
        $numMatches = $bracketSignupSize * ( $bracketSignupSize - 1 ) / 2;
        $this->log->error_log( "$loc: Calculated number of matches={$numMatches}" );
        $matches = $this->getCombinations( $contestants );
        $matchesCreated = count( $matches );

        shuffle( $matches ); //randomize again to prevent unfair assignment to rounds

        if( $matchesCreated !== $numMatches ) {
            $this->log->error_log($matches, "$loc: Calculated number of matches={$numMatches} differs from faux matches created={$matchesCreated}.");
            throw new InvalidTournamentException(__("Calculated number of matches={$numMatches} differs from faux matches created={$matchesCreated}.",TennisEvents::TEXT_DOMAIN ));
        }

        //$this->log->error_log( $matches, "$loc: Combinatorics Matches");

        //4. Fill out the matches by round array 
        //   ensuring that players do not play more than once in a round
        $matchesByRound = array();
        $r=$m=1;
        $matchesByRound[$r] = array();
        $ctr = 0;
        while( 0 < count( $matches ) ) {
            $ct = count( $matches );
            ++$ctr;
            $this->log->error_log("$loc: {$ctr}. while count of matches={$ct} for round={$r}");

            $match = $this->nextRRMatch( $matchesByRound[$r], $matches );

            if( !empty( $match ) ) {
                $matchesByRound[$r][$m++] = $match;
            }
            elseif( !empty( $matches ) ) {
                ++$r;
                $m=1;
                $matchesByRound[$r] = array();
            }
            $this->log->error_log($matchesByRound[$r], "$loc - Matches scheduled for round {$r}");
        }

        $genRounds = $r;
        $this->numRounds = $r;        
        $this->log->error_log( "$loc: Generated number of rounds={$genRounds}");
        $this->log->error_log( $matchesByRound, "$loc: Matches By Round" );

        for( $r = 1; $r <= $genRounds; $r++ ) {
            $matches = $matchesByRound[$r];
            $m = 1;
            foreach( $matches as $mtch ) {
                $players = array_values( $mtch );
                $this->log->error_log($players,"$loc:Players for round={$r}, match={$m}");
                $home = $bracket->getNamedEntrant( $players[0] );
                $visitor = $bracket->getNamedEntrant( $players[1] );
                $match = new Match( $this->getEvent()->getID(), $bracket->getBracketNumber(), $r, $m++ );
                $match->setHomeEntrant( $home );
                $match->setVisitorEntrant( $visitor );
                $match->setMatchType(  $this->matchType );
                $bracket->addMatch( $match );
            }
        }

        // $bsm = array_map( function( $m ) { return $m->toString(); }, $bracket->getMatches());
        // $this->log->error_log( $bsm,"$loc: Matches before save...");

        $matchesCreated = $bracket->numMatches();
        if( $matchesCreated !== $numMatches ) {
            $this->log->error_log( "Actual number of real matches {$matchesCreated} differs from original created {$numMatches}.");
            throw new InvalidTournamentException( __("Actual number of real matches {$matchesCreated} differs from original created {$numMatches}.",TennisEvents::TEXT_DOMAIN ));
        }
        $this->save();

        $this->log->error_log("<<<<<<<<<<<<<<<<<<<<<<<$loc<<<<<<<<<<<<<<<<<<<<<<<<<<<");

        return $matchesCreated;
    }

    private function nextRRMatch( array $scheduled, array &$remainingMatches ) : array {
        $loc = __CLASS__ . "::" . __FUNCTION__;

        $result = array();
        
        // $this->log->error_log($scheduled, "$loc - Scheduled");
        // $this->log->error_log($remainingMatches,"$loc - Remaining at Start");

        $offset = 0;
        foreach($remainingMatches as $remain ) {
            $found = 0;
            foreach( $scheduled as $m ) {
                $schedPlayers = array_values( $m );
                if( in_array($schedPlayers[0], $remain)  || in_array($schedPlayers[1], $remain)) {
                    $found += 1;
                }
            }
            if( $found === 0 ) {
                $result = $remain;
                break;
            }                
            ++$offset;
        }

        if( !empty( $result ) ) {
            $extracted = array_splice( $remainingMatches, $offset, 1 );
            //$this->log->error_log($extracted, "$loc - extracted from remaining at offset={$offset}");
        }

        //$this->log->error_log($scheduled, "$loc - Scheduled at End");
        // $this->log->error_log($result, "$loc - Result Selected");
        // $this->log->error_log($remainingMatches, "$loc - Remaining at End");

        return $result;
    }

    /**
     * Get all the combinations of the identified players
     * @param array $set
     * @param int $num The size of any combination i.e. (M choose N)
     *              where M is the size of $set and N is $num
     * @return array array of all arrays
     */
    private function getCombinations( $set, int $num=2 ) {

        $combinatorics = new Math_Combinatorics;
        if( is_null( $set ) ) return array();
        if( count($set) <= $num ) return array();

        $combs = $combinatorics->combinations($set, $num);
        return $combs;
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
     * @param array $haystack the array to search
     * @param int $needle the integer starting point which will be returned if not in the array
     * @return int Next available integer
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
     * Determine if this integer is a power of 2
     * @param int $size 
     * @param int $upper The upper limit of the search; default is 8
     * @return int The exponent if found; zero otherwise
     */
	private function isPowerOf2( int $size, $upper = 8 ) {
        $exponent = 0;
        foreach( range( 1, $upper ) as $exp ) {
            if( pow( 2, $exp ) === $size ) {
                $exponent = $exp;
                break;
            }
        }
        return $exponent;
    }
    
    /**
     * Calculates the number of byes in round 1
     * to cause the number of players in round 2 be a power of 2
     * The number of players and the number of byes must be 
     * of the same parity (i.e.both even or both odd)
     * @param int $n Size of the round (i.e. number of entrants)
     * @return int Number of byes (-1 if size is out of range)
     */
    public function byeCount( int $n ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $result = -1;

        if( $n < TournamentDirector::MINIMUM_ENTRANTS || $n > TournamentDirector::MAXIMUM_ENTRANTS ) return $result;

        $lowexp  =  GW_Support::calculateExponent( $n );
        $highexp = $lowexp + 1;
        $target  = pow( 2, $lowexp ); //or 2 * $lowexp
        $result  = 2 * $target - $n; // target = (n + b) / 2
        // echo "$loc: n=$n; lowexp=$lowexp; highexp=$highexp; target=$target; byes=$result; " . PHP_EOL;
        if( !($n & 1) && ($result & 1) ) $result = -1;
        elseif( ($n & 1) && !($result & 1) ) $result = -1;
        elseif( $this->isPowerOf2( $n ) ) $result = 0;
        
        return $result;
    }

    /**
     * Calculate the number of challengers (if using early round 0)
     * to bring round 1 to a power of 2
     * The number of players and the number of challengers must be of opposite parity
     * (i.e. if one is odd the other must be even and visa versa )
     * @param int $n Size of the round (i.e. number of entrants)
     * @return int The number of challengers for a challenger round
     */
    private function challengerCount( int $n ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $result = -1;

        if( $n < TournamentDirector::MINIMUM_ENTRANTS || $n > TournamentDirector::MAXIMUM_ENTRANTS ) return $result;

        $lowexp   =  GW_Support::calculateExponent( $n );
        $highexp  = $lowexp + 1;
        $target   = pow(2, $lowexp );
        $result   = $n - $target;
        $round1   = $n - $result; // players in r1 = target = (n - 2p + p)
        // echo "$loc: n=$n; lowexp=$lowexp; highexp=$highexp; round1=$round1; target=$target; challengers=$result; " . PHP_EOL;
        if( ($round1 & 1) ) $result = -1;
        elseif( $this->isPowerOf2( $n ) ) $result = 0;

        return $result;
    }
    
}