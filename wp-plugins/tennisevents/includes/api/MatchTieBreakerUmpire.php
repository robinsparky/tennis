<?php
namespace api;

use \TennisEvents;
use commonlib\GW_Debug;
use datalayer\Event;
use datalayer\Match;
use datalayer\EventType;
use datalayer\MatchType;
use datalayer\Entrant;
use datalayer\InvalidBracketException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MatchTieBreaker uses a tie breaker instead of a deciding set.
 * Could be the best 2 of 3, or 3 of 5, but in the final deciding set
 * a tie breaker is played instead of a whole set. 
 * 
 * @package TennisAdmin
 * @version 1.0.0
 * @since   0.1.0
 */
class MatchTieBreakerUmpire extends ChairUmpire
{
	//This class's singleton
	private static $_instance;

	/**
	 * RegulationMatchUmpire Singleton
	 *
	 * @since 1.0
	 * @static
	 * @return $_instance The Singleton instance.
	 */
	public static function getInstance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}
	
	/**
	 *  Constructor.
	 */
	public function __construct() {
		// Don't allow more than one instance of the class
		if ( isset( self::$_instance ) ) {
			wp_die( sprintf( esc_html__( '%s is a singleton class and you cannot create a second instance.', TennisEvents::TEXT_DOMAIN ), get_class( $this ) ) );
		}
        parent::__construct( true );

	}

    /**
     * Determine the winner of the given Match
     * @param Match Reference to a $match object
     * @return Entrant who won or null if not completed yet
     */
    public function matchWinner( Match &$match ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        $title = $match->toString();
        $this->log->error_log("$loc($title)");

        if( $match->isBye() ) {
            //Should always be the home entrant
            return $match->getHomeEntrant(); //Early return
        }
        elseif( $match->isWaiting() ) {
            return null; //Early return; s/b null because match is waiting for entrant
        }
            
        extract( $this->getMatchSummary( $match ) ); //Magic!
        
        if( !empty( $andTheWinnerIs ) ) {
            switch( $andTheWinnerIs ) {
                case 'home':
                    $andTheWinnerIs = $match->getHomeEntrant();
                    break;
                case 'visitor':
                    $andTheWinnerIs = $match->getVisitorEntrant();
                    break;
                default:
                    $andTheWinnerIs = null;
            }
        }
        else {
            $andTheWinnerIs = null;
        }

        return $andTheWinnerIs;
    }

    /**
     * Find the winner based on the score. Also detects early end due to defaults.
     * NOTE: This function forces a read of all sets for match from the db
     * @param object Match $match
     * @return array Array containing:
     *               indicator of winner (either 'home', 'visitor' or '')
     *               set number in progress (set still in progress if match started but not finished)
     *               final set number (set in which the match finished)
     *               early end flag
     *               set level comments
     *               home sets won
     *               home games won
     *               visitor sets won
     *               visitor games won
     */
    public function getMatchSummary( Match &$match, $force = false ) {
        $startTime = \microtime( true );
        $loc = __CLASS__ . "::" . __FUNCTION__;
        $title = $match->toString();
        $tr = GW_Debug::get_debug_trace_Str();
        $this->log->error_log("$loc($title) trace: $tr" );
        //$this->log->error_log(debug_backtrace()[1]['function'],"Called By");
        
        //NOTE: It is imperative that sets be in ascending order of set number
        $sets = $match->getSets( );
        $numSets = count( $sets );
    
        $home = 'home';
        $visitor = 'visitor';
        $andTheWinnerIs = '';
        $earlyEnd = 0;
        $setInProgress = 0;
        $finalSet = 0;
        $homeSetsWon = 0;
        $visitorSetsWon = 0;
        $homeGamesWon = 0;
        $visitorGamesWon = 0;
        $cmts = '';
        $inTieBreakDecider = false;

        foreach( $sets as $set ) {
            $setNum = $set->getSetNumber();
            $inTieBreakDecider = false;
            $this->log->error_log("{$loc}: set number={$setNum}");
            if( 1 === $this->getMaxSets() && 1 !== $setNum ) {
                $this->log->error_log("{$loc}: skipping set number={$setNum}");
                break;
            }

            $earlyEnd = $set->earlyEnd();
            if( 1 === $earlyEnd ) {
                //Home defaulted
                $andTheWinnerIs = $visitor;
                $cmts = $set->getComments();
                $finalSet = $set->getSetNumber();
                break;
            }
            elseif( 2 === $earlyEnd ) {
                //Visitor defaulted
                $andTheWinnerIs = $home;
                $cmts = $set->getComments();
                $finalSet = $set->getSetNumber();
                break;
            }
            else {
                $homeW = $set->getHomeWins();
                $homeGamesWon += $homeW;
                $homeTB = $set->getHomeTieBreaker();
                $visitorW = $set->getVisitorWins();
                $visitorGamesWon += $visitorW;
                $visitorTB = $set->getVisitorTieBreaker();

                $this->log->error_log( sprintf( "%s(%s): home W=%d, home TB=%d, visitor W=%d, visitor TB=%d"
                                     , $loc, $set->toString(), $homeW, $homeTB, $visitorW, $visitorTB ) );
                
                // Check game scores
                // the game score can be arbitrarily high if there is no tie breaker
                if( $homeW    < min($this->getGamesPerSet(),$this->getTieBreakAt()) 
                &&  $visitorW < min($this->getGamesPerSet(),$this->getTieBreakAt()) ) {
                    //Final set and tie breaker?
                    $this->log->error_log("$loc: max sets = {$this->getMaxSets()} Tie Break Decider={$this->getTieBreakDecider()}");
                    if( $setNum === $this->getMaxSets() && $this->getTieBreakDecider() ) {
                        //Process this set as below
                        $this->log->error_log("$loc: '{$title}' Set {$setNum} is a tie break decider set.");
                        $inTieBreakDecider = true;
                        $homeW = 0;
                        $visitorW = 0;
                    }
                    else {
                        //not done yet so don't even consider other sets
                        $setInProgress = $set->getSetNumber();
                        break; 
                    }
                }

                //Process this set
                //Game scores
                if( ($homeW - $visitorW) >= $this->getMustWinBy() ) {
                    ++$homeSetsWon;
                }
                elseif( ($visitorW - $homeW) >= $this->getMustWinBy() ) {
                    ++$visitorSetsWon;
                }
                //Tie breaker scores
                else {
                    if( $this->includeTieBreakerScores( $setNum ) ) {
                        $this->log->error_log("$loc: include tiebreak scores!set=$setNum: homeTB=$homeTB, visitorTB=$visitorTB");
                        $this->log->error_log("$loc: Must win by={$this->MustWinBy}; Tie Break Min Score={$this->getTieBreakMinScore()}");
                        if( ($homeTB - $visitorTB >= $this->MustWinBy ) 
                            && $homeTB >= $this->getTieBreakMinScore() ) {
                            ++$homeSetsWon;
                            if( $inTieBreakDecider ) {
                                $andTheWinnerIs = 'home';
                                $finalSet = $set->getSetNumber();
                                $setInProgress = 0;
                                break;
                            }
                        }
                        elseif( ($visitorTB - $homeTB >= $this->MustWinBy )  
                            && $visitorTB >= $this->getTieBreakMinScore() ) {
                            ++$visitorSetsWon;
                            if( $inTieBreakDecider ) {
                                $andTheWinnerIs = 'visitor';
                                $finalSet = $set->getSetNumber();
                                $setInProgress = 0;
                                break;
                            }
                        }
                        elseif( $inTieBreakDecider ) {

                        }
                        else { //match not finished yet so break out of foreach loop
                            $setInProgress = $set->getSetNumber();
                            $this->log->error_log("$loc($title): set number {$set->getSetNumber()} not finished tie breaker yet");
                            break;
                        } 
                    } 
                }

                $setInProgress = $set->getSetNumber();
                //Best 3 of 5 or 2 of 3 happened yet?
                if( $homeSetsWon >= ceil( $this->MaxSets/2.0 ) ) {
                    $andTheWinnerIs = 'home';
                    $finalSet = $set->getSetNumber();
                    $setInProgress = 0;
                    break;
                }
                elseif( $visitorSetsWon >= ceil( $this->MaxSets/2.0 ) ) {
                    $andTheWinnerIs = 'visitor';
                    $finalSet = $set->getSetNumber();
                    $setInProgress = 0;
                    break;
                }
            }
        } //foreach set
        
        $winnerName = empty( $andTheWinnerIs) ? 'unknown' : $andTheWinnerIs;
        $this->log->error_log("$loc: The winner is '{$winnerName}' with sets won: home={$homeSetsWon} and visitor={$visitorSetsWon}");

        $result = [  "matchId" => $match->toString()
                    , "andTheWinnerIs" => $andTheWinnerIs
                    , "setInProgress"  => $setInProgress
                    , "finalSet"       => $finalSet
                    , "homeSetsWon"    => $homeSetsWon
                    , "homeGamesWon"   => $homeGamesWon
                    , "visitorSetsWon" => $visitorSetsWon
                    , "visitorGamesWon" => $visitorGamesWon
                    , "earlyEnd"       => $earlyEnd
                    , "comments"       => $cmts ];

        error_log( sprintf("%s: %0.6f", "${loc} Elapsed Time", GW_Debug::micro_time_elapsed( $startTime )));
        $this->log->error_log($result, "$loc: Match Summary Result");

        return $result;
    }
       
    /**
     * Retrieve the Champion for this bracket if exists 'tba' otherwise
     * @param Bracket $bracket
     * @return Entrant who won the bracket or null if not completed
     * @throws InvalidBracketException
     */
    public function getChampion( &$bracket ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        $bracketName = $bracket->getName();
        $this->log->error_log("$loc($bracketName)");
        // $trace = GW_Debug::get_debug_trace( 2 );
        // $this->log->error_log($trace, "$loc called by ...");
        //$this->log->error_log(debug_backtrace()[1]['function'],"Called By");
        //print_r(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2));
        
        $champion = null;

        if( $bracket->isApproved() ) {
            $lastRound = $bracket->getNumberOfRounds();
            $finalMatches = $bracket->getMatchesByRound( $lastRound );
            $this->log->error_log("$loc: lastRound={$lastRound}");

            if( count( $finalMatches ) !== 1 ) {
                $c = count( $finalMatches );
                $errmess = "Final round in bracket '{$bracketName}' with {$lastRound} rounds does not have exactly one match({$c}).";
                $this->log->error_log( $errmess );
                foreach ($finalMatches as $match) {
                    $this->log->error_log("Last Round Match: {$match->title()}");
                }
                throw new InvalidBracketException( $errmess );
            } else {
                $finalMatch = array_pop($finalMatches);
                $this->isLocked( $finalMatch, $champion );
                $this->log->error_log($champion, "$loc: champion: ");
            }
        }
        
        return $champion;
    }

    /**
     * Vet the scores for MatchTieBreaker scoring before calling parent version.
     */
    public function recordScores( Match &$match, array $score ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        $setnum = (int)$score['setNum'];
        $title = $match->toString();

        if( $setnum === $this->getMaxSets() && $this->getTieBreakDecider() ) {
            $this->log->error_log("$loc: '{$title}' Set {$setnum} is a tie break decider set.");
            $score["homeGames"] = 0;
            $score["visitorGames"] = 0;
        }
        parent::recordScores($match, $score );

    }
} //end of class