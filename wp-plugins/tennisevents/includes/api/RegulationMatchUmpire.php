<?php
namespace api;

use \TennisEvents;
use commonlib\GW_Debug;
use datalayer\Event;
use datalayer\TennisMatch;
use datalayer\EventType;
use datalayer\MatchType;
use datalayer\Entrant;
use datalayer\InvalidBracketException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The chair umpire for an tennis tournament in which the best 2 of 3, or 3 of 5, etc is 
 * used to determine match winner. Includes the use of tie breaker to determine winner of a set.
 * What about no tie breakers???
 * 
 * @package TennisAdmin
 * @version 1.0.0
 * @since   0.1.0
 */
class RegulationMatchUmpire extends ChairUmpire
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
     * Determine the winner of the given TennisMatch
     * @param TennisMatch Reference to a $match object
     * @return Entrant who won or null if not completed yet
     */
    public function matchWinner( TennisMatch &$match ) {
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
     * @param object TennisMatch $match
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
    public function getMatchSummary( TennisMatch &$match, $force = false ) {
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
        
        extract($match->findEarlyEnd());
        $this->log->error_log("$loc: {$match->toString()} Early set=$setNumEarly; Early End=$earlyEnd; Comments={$cmts}");

        foreach( $sets as $set ) {
            $setNum = $set->getSetNumber();
            $this->log->error_log("{$loc}: set number={$setNum}");
            if( 1 === $this->getMaxSets() && 1 !== $setNum ) {
                $this->log->error_log("{$loc}: skipping set number={$setNum}");
                break;
            }

            $earlyEnd = $set->earlyEnd();
            if( 1 === $earlyEnd ) {
                //Home defaulted
                $andTheWinnerIs = $visitor;
                $finalSet = $setNumEarly;
                break;
            }
            elseif( 2 === $earlyEnd ) {
                //Visitor defaulted
                $andTheWinnerIs = $home;
                $finalSet = $setNumEarly;
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
                        $this->log->error_log("$loc: This is a tie break decider set: $setNum");
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
                        if( ($homeTB - $visitorTB >= $this->MustWinBy ) 
                            && $homeTB >= $this->getTieBreakMinScore() ) {
                            ++$homeSetsWon;
                        }
                        elseif( ($visitorTB - $homeTB >= $this->MustWinBy )  
                            && $visitorTB >= $this->getTieBreakMinScore() ) {
                            ++$visitorSetsWon;
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

        error_log( sprintf("%s: %0.6f", "{$loc} Elapsed Time", GW_Debug::micro_time_elapsed( $startTime )));
        $this->log->error_log($result, "$loc: TennisMatch Summary Result");

        return $result;
    }
       
    /**
     * Retrieve the Champion for this bracket if exists 'tba' otherwise
     * @param Bracket $bracket
     * @return array Entrant and final score of player who won the championship for this bracket
     *               with ['ChampionName'=>null, 'ChampionScore'=>''] if draw not completed
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
        $champScore = '';

        if( $bracket->isApproved() ) {
            $lastRound = $bracket->getNumberOfRounds();
            $finalMatches = $bracket->getMatchesByRound( $lastRound );
            $this->log->error_log("$loc: lastRound={$lastRound}");

            if( count( $finalMatches ) !== 1 ) {
                $c = count( $finalMatches );
                $errmess = "Final round in bracket '{$bracketName}' with {$lastRound} rounds does not have exactly one match({$c}).";
                $this->log->error_log( $errmess );
                foreach ($finalMatches as $match) {
                    $this->log->error_log("Last Round TennisMatch: {$match->title()}");
                }
                throw new InvalidBracketException( $errmess );
            } else {
                $finalMatch = array_pop($finalMatches);
                $this->isLocked( $finalMatch, $champion );
                $this->log->error_log($champion, "$loc: champion: ");
                $champScore = $this->strGetScores($finalMatch,true);
            }
        }
        
        return [self::CHAMPIONNAME=>$champion, self::CHAMPSCORE=>$champScore];
    }

} //end of class