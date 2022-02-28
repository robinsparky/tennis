<?php
namespace api;

use \TennisEvents;
use commonlib\gw_debug;
use datalayer\Event;
use datalayer\Match;
use datalayer\EventType;
use datalayer\MatchType;
use datalayer\Entrant;
use datalayer\ScoreType;
use datalayer\InvalidBracketException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The chair umpire for NoAd rules:
 * 4 points earned, wins the game
 * 7 points is the maximum points played in a game
 * 1 point earned after the score is 40-40 (deuce) wins the game (i.e. each side has 3 points)
 * The receiver or the receiving doubles team decides which service court to receive serve when 4 (or 6) points have been played and the game is tied
 * For mixed doubles, the 7th point served is served from the server to the receiver of the same gender
 * 
 * @package TennisAdmin
 * @version 1.0.0
 * @since   0.1.0
 */
class NoAdUmpire extends ChairUmpire
{
	//This class's singleton
	private static $_instance;

	/**
	 * Fast4Umpire Singleton
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
        $calledBy = isset(debug_backtrace()[1]['class']) ? debug_backtrace()[1]['class'] . '::'. debug_backtrace()[1]['function'] : debug_backtrace()[1]['function'];

        $title = $match->toString();
        $this->log->error_log("$loc($title) called by {$calledBy}" );
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
                
                // the game score can go as high as it wants if there is no tie breaker
                if( $homeW < $this->getGamesPerSet() 
                &&  $visitorW < $this->getGamesPerSet() ) {
                    $setInProgress = $set->getSetNumber();
                    break; //not done yet and don't even consider other sets
                }

                if( ($homeW - $visitorW) >= $this->getMustWinBy() ) {
                    ++$homeSetsWon;
                }
                elseif( ($visitorW - $homeW) >= $this->getMustWinBy() ) {
                    ++$visitorSetsWon;
                }
                else {
                    if( false === $this->includeTieBreakerScores( $setNum ) ) {
                        //do nothing because there are no tie breakers
                    }
                    else { //Tie breakers ... will never get here for NoAd
                        if( ($homeTB - $visitorTB >= $this->MustWinBy ) 
                            && $homeTB >= $this->getTieBreakMinScore() ) {
                            ++$homeSetsWon;
                        }
                        elseif( ($visitorTB - $homeTB >= $this->MustWinBy )  
                            && $visitorTB >= $this->getTieBreakMinScore() ) {
                            ++$visitorSetsWon;
                        }
                        else { //match not finished yet
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
        } //foreach
        
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
     * For NoAd matches this value should be 1
     * otherwise it defaults to 2
     */
    public function getMustWinBy() {
        return 1;
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
            // $this->log->error_log("$loc: lastRound={$lastRound}");

            if( count( $finalMatches ) !== 1 ) {
                $c = count( $finalMatches );
                $errmess = "Final round in bracket '{$bracketName}' with {$lastRound} rounds does not have exactly one match({$c}).";
                //$this->log->error_log( $errmess );
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
    * Edits the game scores before saving them
    * NOTE: mustWinBy must always be 1; and noTieBreakers always true
    * @param int $homeScore The home entrant's game score
    * @param int $visitorScore the visitor entrant's game score
    */
   protected function getAllowableGameScore( int &$homeScore, int &$visitorScore ) {
       $loc = __CLASS__ . '::' . __FUNCTION__;
       $this->log->error_log("$loc");

       $diff = $homeScore - $visitorScore;
       $gamesPerSet = $this->getGamesPerSet();
       //if( 0 === $diff && $homeScore >= $this->getTieBreakAt() ) $gamesPerSet = $this->getTieBreakAt();

       if($homeScore >= $gamesPerSet && $diff > 0 ) {
           if( $diff >= $this->getMustWinBy() && $this->noTieBreakers() ) {
               //$homeScore = max( $visitorScore + $this->getMustWinBy(), $gamesPerSet );
               $homeScore = $gamesPerSet;
               $visitorScore = min($visitorScore, $homeScore - 1);
           }
           //Should never get here
           elseif( $diff >= $this->getMustWinBy() ) {
               if( $visitorScore === $gamesPerSet - 1 ) {
                   $homeScore = $gamesPerSet;
               }
               else {
                   $homeScore = $gamesPerSet;
                   $visitorScore = min( $visitorScore, $homeScore - $this->getMustWinBy() );
               }
           }
       }
       elseif( $visitorScore >= $gamesPerSet && $diff < 0 ) {
           $diff =  abs($diff);
           if( $diff >= $this->getMustWinBy() && $this->noTieBreakers() ) {
               //$visitorScore = max( $homeScore + $this->getMustWinBy(), $gamesPerSet ); 
               $visitorScore = $gamesPerSet;
               $homeScore = min($homeScore, $visitorScore - 1); 
           }
           //Should never get here
           elseif( $diff >= $this->getMustWinBy() ) {
               if( $homeScore === $gamesPerSet - 1 ) {
                   $visitorScore = $gamesPerSet;
               }
               else {
                   $visitorScore = $gamesPerSet;
                   $homeScore = min( $homeScore, $visitorScore - $this->getMustWinBy() );
               }
           }
       }
       elseif( 0 === $diff ) {
            $homeScore = min( $homeScore, $gamesPerSet - 1 );
            $visitorScore = min( $visitorScore, $gamesPerSet - 1 );
       }
   }

} //end of class