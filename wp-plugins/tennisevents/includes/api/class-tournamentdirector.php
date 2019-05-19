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
    public const MAXIMUM_ENTRANTS = 256; //maximum for an elimination tournament

    private const CHALLENGERS = "challengers";
    private const BYES = "byes";
    private const AUTO = "auto";

    private $numToEliminate = 0; //The number of players to eliminate to result in a power of 2
    private $numRounds = 0; //Total number of rounds for this tournament; calculated based on signup
    private $hasChallengerRound = false; //Is a challenger round required
    private $matchType; //The type of match such as mens singles, womens doubles etc
    private $name = '';

    private $log;
    
    /**************************************************  Public functions ********************************************************** */

    /**
     * Given the size of the draw (or any integer), calculate the highest 
     * power of 2 which is greater than or equal to that size (or integer)
     * @param $size 
     * @param $upper The upper limit of the search; default is 8
     * @return The exponent if found; zero otherwise
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
        
        $this->matchType = $this->event->getMatchType();
        switch( $this->matchType ) {
            case MatchType::MENS_SINGLES:
                $this->name = self::MENSINGLES;
                break;
            case MatchType::WOMENS_SINGLES:
                $this->name = self::WOMENSINGLES;
                break;
            case MatchType::MENS_DOUBLES:
                $this->name = self::MENSDOUBLES;
                break;
            case MatchType::WOMENS_DOUBLES:
                $this->name = self::WOMENSDOUBLES;
                break;
            case MatchType::MIXED_DOUBLES:
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
    public function getChairUmpire( int $scoretype = 0 ) : ChairUmpire {
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
     * Generates a bracket template for review and approval
     * For double elimination, the following rules apply:
     *   1. A separate Losers bracket is used
     *   2. The Losers bracket must be approved too.
     *   3. Approval should happen after appropriate modifications
     *      have been made; such as moving the prelimary matches around.
     *  ...
     * Once approved, the brackets cannot be modified, only deleted.
     * @param $bracket Bracket within the event
     */
    public function approve( $bracketName ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        $this->log->error_log("$loc($bracketName");

        $bracket = $this->getBracket( $bracketName );
        if( is_null( $bracket ) ) {
            throw new InvalidBracketException( __("No such bracket: $bracketName.", TennisEvents::TEXT_DOMAIN) );
        }

        $bt = $bracket->approve( $this );
        $this->save();

        return $bt->arrGetTemplate();
    }

    /**
     * Advance completed matches to their respective next rounds.
     * @param $bracketName name of the bracket
     * @return Number of entrants advanced
     * 
     */
    public function advance( $bracketName ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        $this->log->error_log("$loc($bracketName)");

        $bracket = $this->getBracket( $bracketName );

        if( is_null( $bracket ) ) {
            throw new InvalidTournamentException( __( "Invalid bracket name $bracketNname.", TennisEvents::TEXT_DOMAIN) );
        }

        if( !$bracket->isApproved() ) {
            throw new InvalidTournamentException( __( "Bracket has not been approved.", TennisEvents::TEXT_DOMAIN) );        
        }

        $matches = $bracket->getMatches( true );
        $umpire = $this->getChairUmpire();

        $numAdvanced = 0;
        foreach( $matches as $match ) {            
            if( $umpire->isLocked( $match ) || $match->isBye() ) {

                $title = $match->title();
                $winner = $umpire->matchWinner( $match );
                if( is_null( $winner ) ) {
                    $mess = "Match $title is locked but cannot determine winner.";
                    $this->log->error_log( $mess );
                    throw new InvalidTournamentException( $mess );
                }

                $this->log->error_log("$loc: attempting to advance match: $title");

                $nextMatch = $bracket->getMatch( $match->getNextRoundNumber(), $match->getNextMatchNumber() );
                if( is_null( $nextMatch ) ) {
                    $mess = "Match $title has invalid next match pointers.";
                    $this->log->error_log( $mess );
                    throw new InvalidTournamentException( $mess );
                } 

                $title = $nextMatch->title();
                $this->log->error_log( "$loc: next match: $title" );
                
                if( $match->getMatchNumber() & 1 ) {
                    $nextMatch->setHomeEntrant( $winner );
                }
                else {
                    $nextMatch->setVisitorEntrant( $winner );
                }
                $nextMatch->setIsBye( false );
                ++$numAdvanced;                    
                $this->log->error_log( sprintf( "%s --> %d. Advanced winner %s of match %s to match %s"
                                        , $loc, $numAdvanced, $winner->getName(), $match->toString(), $nextMatch->toString() ) );
                
            }
        }
        $this->save();
        return $numAdvanced;
    }

    /**
     * Get the name of the underlying Event
     */
    public function getName() {
        return $this->event->getName();
    }

    /**
     * Save the results from addEntrant, etc.
     * Calls save on the underlying Event.
     * NOTE: if significant changes are made to the underlying Event
     *       one should save these earlier.
     */
    public function save() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("$loc -> called ...");

        return $this->event->save();
    }

    /**
     * Traverse the Brackets to find the first incomplete Round
     * @param $bracketName
     * @return Number of the current round
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

    /**
     * Check if the bracket has started or not
     * @param $bracketName
     * @return True if started False otherwise
     */
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
     * The total number of rounds for this tennis event.
     */
    public function totalRounds( string $bracketName = Bracket::WINNERS ) {
        $bracket = $this->event->getBracket( $bracketName );
        $this->numRounds = self::calculateExponent( $bracket->signupSize() );
        return $this->numRounds;
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
     * Get all the Brackets for the underlying Event
     */
    public function getBrackets( $force = false ) {
        return $this->event->getBrackets( $force );
    }

    /**
     * Get a bracket by name
     * @param $bracketName The name of the bracket
     * @return Bracket if exists, null otherwise
     */
    public function getBracket( $bracketName ) {
        return $this->event->getBracket( $bracketName );
    }

    /**
     * Remove all brackets and matches for a tennis event
     * Use with caution as it deletes all of an event's brackets and matches from the database.
     */
    public function removeBrackets( ) {
        $result = 0;
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
        if( is_null( $bracket ) ) {
            throw new InvalidTournamentException( __( "No such bracket '$bracketName' when getting matches." ) );
        }
        else {
            if( is_null( $round ) ) {
                $matches = $bracket->getMatches( $force );
                usort( $matches, array( 'TournamentDirector', 'sortByRoundMatchNumberAsc' ) );
            }
            else {
                $matches = $bracket->getMatchesByRound( $round );
                usort( $matches, array( 'TournamentDirector', 'sortByMatchNumberAsc' ) );
            }
        }
        
        return $matches;
    }

    /**
     * Remove all matches for a bracket
     * @param $bracketName The name of the bracket
     */
    public function removeMatches( string $bracketName = Bracket::WINNERS ) {
        $bracket = $this->event->getBracket( $bracketName );
        if( !is_null( $bracket ) ) {
            $bracket->removeAllMatches();
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
    public function addEntrant( string $name, int $seed = 0 ) {
        $result = 0;

        if( 0 < $this->getMatchCount() ) {
            throw new InvalidTournamentException( __('Cannot add entrant because matches already exist.') );
        }

        if( isset( $this->event ) ) {
            $this->event->addToSignup( $name, $seed );
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
    public function moveMatch( Bracket $bracket, int $fromRoundNum, int $fromMatchNum, int $toMatchNum , string $cmts = null ) {
        $result = 0;
        if( isset( $bracket ) ) {
            $result = Match::move( $this->event->getID(), $bracket->getBracketNumber(), $fromRoundNum, $fromMatchNum, $toMatchNum, $cmts );
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

    /**
     * Load the matches and rounds from database into the appropriate template
     * NOTE: The bracket should have been approved and preliminary rounds already scheduled.
     * @param $bracketName The name of the bracket to be loaded
     * @return Template populated with matches from the database
     */
    public function loadMatches( string $bracketName ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        $this->log->error_log( "$loc($bracketName)" ); 

        $bracket = $this->event->getBracket( $bracketName );

        //Bracket must exist
        if( is_null( $bracket ) ) {
            throw new InvalidBracketException( __("Bracket does not exist for this event.", TennisEvents::TEXT_DOMAIN ) );
        }

        //Bracket must be approved
        if( !$bracket->isApproved() ) {
            throw new InvalidBracketException( __("Bracket signup must be approved before scheduling matches.", TennisEvents::TEXT_DOMAIN ) );
        }

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
        
        $bracketSignupSize = $bracket->signupSize();
        if( 0 ===  $bracketSignupSize ) {
            throw new InvalidBracketException( __('Cannot load matches for bracket because there is no signup.', TennisEvents::TEXT_DOMAIN ) );
        }
        $this->log->error_log( "$loc: signup size=$bracketSignupSize" );

        //$this->calculateEventSize( $bracket );

        $umpire = $this->getChairUmpire();
        $template = $bracket->buildBracketTemplate( $umpire );

        return $template;
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
     * The purpose of this function is to eliminate enough players 
     * in the first round so that the next round has 2^n players 
     * and the elimination rounds can then proceed naturally to the end.
     * The next big question to work out is determining who gets the byes (if any).
     * Finally the seeded players (who get priority for bye selection) must be distributed
     * evenly amoung the un-seeded players with the first and second seeds being at opposite ends of the draw.
     * @param $bracketName
     * @param $method .... always using byes
     * @param $randomizeDraw boolean to indicate if the signup should be randomized
     * @return Number of matches created
     */
    public function schedulePreliminaryRounds( string $bracketName, $randomizeDraw = false ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        $this->log->error_log( ">>>>>>>>>>>>>>>>>>>>>>>>>$loc called with bracket=$bracketName, randomize=$randomizeDraw" ); 

        $bracket = $this->event->getBracket( $bracketName );

        //Bracket must exist
        if( is_null( $bracket ) ) {
            throw new InvalidBracketException( __("Bracket does not exist for this event.", TennisEvents::TEXT_DOMAIN ) );
        }

        //Bracket must be approved
        if( !$bracket->isApproved() ) {
            throw new InvalidBracketException( __("Bracket must be approved before scheduling matches.", TennisEvents::TEXT_DOMAIN ) );
        }

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
        
        $bracketSignupSize = $bracket->signupSize();
        if( 0 ===  $bracketSignupSize ) {
            throw new InvalidBracketException( __('Cannot generate preliminary matches for bracket because there is no signup.', TennisEvents::TEXT_DOMAIN ) );
        }
        $this->log->error_log( "$loc: signup size=$bracketSignupSize" );

        if( 0 < $this->hasStarted( $bracket->getName() ) ) {
            throw new BracketHasStartedException( __('Cannot generate preliminary matches for bracket because play as already started.') );
        }

        //Remove any existing matches ... we know they have not started yet
        $this->removeMatches( $bracket->getName() );
        $this->save();
        $this->calculateEventSize( $bracket );

        $entrants = $bracket->getSignup();
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
        $this->log->error_log( sprintf( "%s: highMatchnum=%d seedByes=%d unseedByes=%d", $loc, $highMatchnum, $seedByes, $unseedByes ) );
        
        $this->processByes( $bracket, $seeded, $unseeded );

        if( (count( $unseeded ) + count( $seeded )) > 0 ) {
            throw new InvalidTournamentException( __( "Did not schedule all players into initial rounds." ) );
        }

        $matchesCreated += $bracket->numMatches();
        $this->save();

        $this->log->error_log("<<<<<<<<<<<<<<<<<<<<<<<$loc<<<<<<<<<<<<<<<<<<<<<<<<<<<");

        return $matchesCreated;
    }
        
    /**************************************************  Private functions ********************************************************** */

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
        error_log('<<<<<');
    }
    
    /**
     * For this case, we could have a large number of players involved in bringing the count
     * down to a power of 2 for the subsequent round. So this is considered the first round
     * and only a few byes need to be defined. It is likely that only seeded players will get the byes.
     */
    private function processByes( Bracket $bracket, array &$seeded, array &$unseeded ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        $this->log->error_log("$loc: called ...");

        $numInvolved = 2 * $this->numToEliminate;
        $remainder   = $numInvolved > 0 ? $bracket->signupSize() - $numInvolved : 0;
        $highMatchnum = ceil( $bracket->signupSize() / 2 );
        $lowMatchnum = 1;
        $usedMatchNums = array();
        $lastSlot = 0;
        $highestMatchnumUsed = 3 * $highMatchnum;
        
        $initialRound = 1;
        $seedByes = 0;
        $unseedByes = 0;
        $seedByes    =  min( count( $seeded ) , $remainder );
        $unseedByes  = $remainder - $seedByes;

        //Add seeded players as Bye matches using an even distribution
        $slot = ($seedByes + $unseedByes) > 1 ? ceil( ceil( $bracket->signupSize() / 2.0 ) / ($seedByes + $unseedByes) ) : 0;
        $slot = max( 2, $slot );

        error_log(' ');
        error_log( sprintf(">>>>>%s -> bracket=%s seeds=%d; unseeded=%d", $loc, $bracket->getName(), count( $seeded ), count( $unseeded ) ) );
        error_log( sprintf("     %s -> bracket=%s slot=%d; numInvolved=%d; remainder=%d; highMatchNum=%d; seedByes=%d; unseedByes=%d", $loc, $bracket->getName(), $slot, $numInvolved, $remainder, $highMatchnum, $seedByes, $unseedByes) );

        for( $i = 0; $i < $seedByes; $i++ ) {
            if( 0 === $i ) {
                $lastSlot = $lowMatchnum++;
                array_push( $usedMatchNums, $lastSlot );
            }
            else if( 1 === $i ) {
                $lastSlot = $highestMatchnumUsed;
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
        error_log('<<<<<');
    }

    /**
     * Convert a bracket's approved template to matches
     * TODO: remove this function
     */
    private function templateToMatch( array &$seeded, array &$unseeded ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;

        $rounds = $this->getMatchTemplate()->getTemplate();

        //First insert entrants into byes and waiting matches
        $ctr = 0;
        foreach( $rounds as $mlist ) {
            $mlist->setIteratorMode( SplDoublyLinkedList::IT_MODE_FIFO );
            for( $mlist->rewind(); $mlist->valid(); $mlist->next() ) {
                ++$ctr;
                if( isset( $mlist->current()->is_bye ) && $mlist->current()->is_bye ) {
                    if( count( $seeded ) > 0 ) {
                        if( $ctr === 1 ) {
                            $player = array_shift( $seeded );
                        }
                        else {
                            $player = array_pop( $seeded );
                        }
                    }
                    else {
                        $player = array_shift( $unseeded );
                    }
                }
                elseif( isset( $mlist->current()->is_waiting ) && $mlist->current()->is_waiting ) {
                    if( count( $seeded ) > 0 ) {
                        if( $ctr & 1 ) {
                            $player = array_shift( $seeded );
                        }
                        else {
                            $player = array_pop( $seeded );
                        }
                    }
                    else {
                        $player = array_shift( $unseeded );
                    }
                }
                else {
                    continue;
                }

                $match = new Match( $this->event->GetID()
                                  , $this->getID()
                                  , $mlist->current()->round
                                  , $mlist->current()->match_num );
                $match->setMatchType( $this->event->getMatchType() );
                $match->setNextRoundNumber( $mlist->current()->next_round_num );
                $match->setNextMatchNumber( $mlist->current()->next_match_num );
                $match->setHomeEntrant( $player );
                $this->addMatch( $match );
                error_log( sprintf( "%s --> Added bye/waiting match %s to bracket %s", $loc, $match->title(), $bracket->getName() ) );
            }
        }


        //Now fill in the remainder of the matches
        foreach( $rounds as $mlist ) {
            $mlist->setIteratorMode( SplDoublyLinkedList::IT_MODE_FIFO );
            for( $mlist->rewind(); $mlist->valid(); $mlist->next() ) {
                //Skip byes and waiting matches
                if( isset($mlist->current()->is_bye) && $mlist->current()->is_bye ) {
                    continue;
                }
                elseif( isset($mlist->current()->is_waiting) && $mlist->current()->is_waiting ) {
                    continue;
                }

                $match = $this->getMatch( $mlist->current()->round, $mlist->current()->match_num );
                if(!is_null( $match ) ) {
                    $this->log->error_log( sprintf( "%s --> Match %s already exists in bracket %s", $loc, $match->title(), $bracket->getName() ) );
                    continue;
                }

                ++$ctr;
                $match = new Match( $this->event->GetID()
                                  , $this->getID()
                                  , $mlist->current()->round
                                  , $mlist->current()->match_num );
                $match->setMatchType( $this->event->getMatchType());
                
                if( $mlist->current()->match_num !== $mlist->top()->match_num 
                &&  $mlist->current()->round     !== $mlist->top()->round ) {
                    $match->setNextRoundNumber( $mlist->current()->next_round_num );
                    $match->setNextMatchNumber( $mlist->current()->next_match_num );
                }

                $home = array_shift( $seeded );
                if( is_null( $home ) ) $home = array_shift( $unseeded );
                $visitor = array_shift( $unseeded );

                if( !is_null( $home ) || !is_null( $visitor ) ) {
                    $match->setHomeEntrant( $home );
                    $match->setVisitorEntrant( $visitor );
                    
                    $this->addMatch( $match );
                    error_log( sprintf( "%s --> Added match %s to bracket %s", $loc, $match->title(), $bracket->getName() ) );
                }
                else {
                    $this->addMatch( $match );
                    error_log( sprintf( "%s --> Added match %s to bracket %s", $loc, $match->title(), $bracket->getName() ) );
                    // $where = !is_null( $home ) ? $home->getName() : '';
                    // $where .= !is_null( $visitor ) ? $visitor->getName: '';
                    // throw new InvalidTournamentException( __( "Ran out of entrants ($ctr) left off at $where", TennisEvents::TEXT_DOMAIN ) );
                }
                    
            }
        }
    }
    
    /**
     * Make matches by recursively reducing the number of players to deal with
     * +++++++++++++++NOT USED++++++++++++++++++++++++++++++
     * TODO: Remove this function
     */
    private function makeMatches( Bracket $bracket, array &$seeded, array &$unseeded ) {
        
        $loc = __CLASS__ . "::" . __FUNCTION__;

        $eventSize = count( $seeded ) + count( $unseeded );
        
        $numRounds = self::calculateExponent( $eventSize );
        $numToEliminate = $eventSize - pow( 2, $numRounds );
        $matchesInvolved   = 2 * $numToEliminate;

        $remainder     = $matchesInvolved > 0 ? $eventSize - $matchesInvolved : 0;
        $highMatchnum  = ceil( $eventSize / 2.0 );
        $lowMatchnum   = 1;
        $usedMatchNums = array();
        $lastSlot      = 0;
        
        $initialRound = 1;
        $seedByes     = 0;
        $unseedByes   = 0;
        $seedByes     =  min( count( $seeded ) , $remainder );
        $unseedByes   = $remainder - $seedByes;

        //Add seeded players as Bye matches using an even distribution
        $slot = ($seedByes + $unseedByes) > 1 ? ceil( $highMatchnum / ($seedByes + $unseedByes) ) : 0;
        $slot = max( 2, $slot );

        error_log(' ');
        error_log( sprintf(">>>>>%s -> bracket=%s seeds=%d; unseeded=%d"
                          , $loc, $bracket->getName(), count( $seeded ), count( $unseeded ) ) );
        error_log( sprintf("     %s -> bracket=%s slot=%d; matchesInvolved=%d; remainder=%d; highMatchNum=%d; seedByes=%d; unseedByes=%d"
                          , $loc, $bracket->getName(), $slot, $matchesInvolved, $remainder, $highMatchnum, $seedByes, $unseedByes) );
        
        
        $template = new SplDoublyLinkedList();
        error_log( "$loc calling makeTemplate" );
        $this->makeTemplate( $initialRound, $eventSize , $template );
        
        error_log( "$loc template ...");
        $template->setIteratorMode(SplDoublyLinkedList::IT_MODE_FIFO);
        $ctr = 0;
        $template->setIteratorMode( SplDoublyLinkedList::IT_MODE_FIFO );
        for( $template->rewind(); $template->valid(); $template->next() ) {
            error_log( sprintf("    round=%d, match_num=%d, is_bye=%d", $template->current()->round, $template->current()->match_num, $template->current()->is_bye ) );
            
            if( $template->current()->is_bye ) {
                $match =  new Match( $this->event->getID()
                                   , $bracket->getBracketNumber()
                                   , $initialRound
                                   , $template->current()->match_num ); 
                $match->setIsBye( true );
                $match->setMatchType( $this->matchType );
                $home = $ctr & 1 ? array_pop( $seeded ) : array_shift( $seeded );
                if( is_null( $home ) ) $home = array_shift( $unseeded );
                $match->setHomeEntrant( $home );
                $bracket->addMatch( $match );
                error_log( sprintf( "    Created Match=%s",$match->title() ) );
                ++$ctr;
                continue;
            }

            $match =  new Match( $this->event->getID()
                               , $bracket->getBracketNumber()
                               , $initialRound
                               , $template->current()->match_num ); 

            $match->setMatchType( $this->matchType );
            $home =  $ctr && 1 ? array_shift( $seeded ) : array_pop( $seeded );
            if( is_null( $home ) ) {
                $home = array_shift( $unseeded );
            }
            $match->setHomeEntrant( $home );
            $visitor = array_shift( $unseeded );
            $match->setVisitorEntrant( $visitor );
            $bracket->addMatch( $match );
            error_log( sprintf( "    Created Match=%s",$match->title() ) );
            ++$ctr;
        }
        error_log( sprintf("%s -> ctr=%d; bracket=%d", $loc, $ctr, $bracket->numMatches())); 
        error_log('<<<<<');    
    }

    /**
     * ++++++++++++++++++NOT USED+++++++++++++++++++++
     * TODO: remove this function
     */
    private function makeTemplate( int $round, int $size, &$template ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        static $numCalls = 0;

        if( $size <= 0 ) return $size;

        ++$numCalls;
        error_log( sprintf("%s call #%d with round=%d, size=%d", $loc, $numCalls, $round, $size) );

        $numRounds = self::calculateExponent( $size );
        $numToEliminate = $size - pow( 2, $numRounds );
        error_log( sprintf("%s ---- numRounds=%d, numToEliminate=%d", $loc, $numRounds, $numToEliminate) );

        if( 0 === $numToEliminate ) {
            while( $size > 0 ) {
                $holder = new stdClass;
                $holder->round = $round;
                $holder->match_num = $template->isEmpty() ? 1 : $template->top()->match_num + 1;
                $holder->is_bye = false;
                $template->push( $holder );
                $size -= 2;
            }
            return 0;
        }
        elseif( 3 < $numToEliminate ) {
            if( $numCalls & 2 ) {
                $bholder = new stdClass;
                $bholder->round = $round;
                $bholder->match_num = $template->isEmpty() ? 1 : $template->top()->match_num + 1;
                $bholder->is_bye = true;
                $template->push( $bholder );
                $size -= 1;

                for( $i = 0; $i < 3; $i++ ) {
                    $holder = new stdClass;
                    $holder->round = $round;
                    $holder->match_num = $template->isEmpty() ? 1 : $template->top()->match_num + 1;
                    $holder->is_bye = false;
                    $template->push( $holder );
                    $size -= 2;
                }
            }
            else {
                for( $i = 0; $i < 3; $i++ ) {
                    $holder = new stdClass;
                    $holder->round = $round;
                    $holder->match_num = $template->isEmpty() ? 1 : $template->top()->match_num + 1;
                    $holder->is_bye = false;
                    $template->push( $holder );
                    $size -= 2;
                }
                
                $bholder = new stdClass;
                $bholder->round = $round;
                $bholder->match_num = $template->isEmpty() ? 1 : $template->top()->match_num + 1;
                $bholder->is_bye = true;
                $template->push( $bholder );
                $size -= 1;
            }
        }
        else {
            $holder = new stdClass;
            $holder->round = $round;
            $holder->match_num = $template->isEmpty() ? 1 : $template->top()->match_num + 1;
            $holder->is_bye = false;
            $template->push( $holder );
            $size -= 2;
            
            $bholder = new stdClass;
            $bholder->round = $round;
            $bholder->match_num = $template->isEmpty() ? 1 : $template->top()->match_num + 1;
            $bholder->is_bye = true;
            $template->push( $bholder );
            $size -= 1;
        }

        return $this->makeTemplate( $round, $size, $template );
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
    private function calculateEventSize( Bracket $bracket ) {
        $this->numToEliminate = 0;
        $this->numRounds = 0;

        if(!$this->event->isLeaf()) {
            $mess = __( 'Must be a leaf event to generate rounds.', TennisEvents::TEXT_DOMAIN );
            throw new InvalidEventException( $mess );
        }

        $found = true;
        foreach( $this->event->getBrackets() as $b ) {
            if( $bracket->getID() === $b->getID() ) {
                $found = true;
                break;
            }
        }
        if( !$found ) {
            throw new InvalidBracketException( __("No such bracket in this event.", TennisEvents::TEXT_DOMAIN) );
        }

        $minplayers = $bracket->getName() === Bracket::WINNERS ? self::MINIMUM_ENTRANTS : self::MINIMUM_ENTRANTS / 2;
        if( $bracket->signupSize() < $minplayers ) {
            $min = self::MINIMUM_ENTRANTS;
            $mess = __( "Event/bracket must have at least $min entrants for an elimination event.", TennisEvents::TEXT_DOMAIN );
            throw new InvalidEventException( $mess );
        }

        $this->numRounds = self::calculateExponent( $bracket->signupSize() );
        $this->numToEliminate = $bracket->signupSize() - pow( 2, $this->numRounds );

        return $this->numToEliminate;
    }
    
    /**
     * Determine if this integer is a power of 2
     * @param $size 
     * @param $upper The upper limit of the search; default is 8
     * @return The exponent if found; zero otherwise
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
     */
    public function byeCount( int $n ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $result = -1;

        if( $n < TournamentDirector::MINIMUM_ENTRANTS || $n > TournamentDirector::MAXIMUM_ENTRANTS ) return $result;

        $lowexp  =  self::calculateExponent( $n );
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
     */
    private function challengerCount( int $n ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $result = -1;

        if( $n < TournamentDirector::MINIMUM_ENTRANTS || $n > TournamentDirector::MAXIMUM_ENTRANTS ) return $result;

        $lowexp   =  self::calculateExponent( $n );
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