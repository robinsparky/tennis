<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Represents the chair umpire for an tennis tournament 
 * using sets to record scores, but determines match winners and bracket champion
 * using total points 
 * 
 * @package TennisAdmin
 * @version 1.0.0
 * @since   0.1.0
 */
class PointsMatchUmpire extends ChairUmpire
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
        //Scoring Rules
        $this->MaxSets = 1;
        $this->GamesPerSet = 6;
        $this->TieBreakerMinimum = 0;
        $this->TieBreakDecider = false;
        $this->NoTieBreakerFinalSet = false;
	}

    /**
     * Record game and tie breaker scores for a given set pf the supplied Match.
     * @param Match $match The match whose score are recorded
     * @param int $setnum The set number 
     * @param int ...$scores if 2 args then game scores; if 4 then games and tiebreaker scores
     */
	public function recordScores( Match &$match, int $setnum, int $homeWins, int $visitorWins ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;

        $title = $match->title();
        $this->log->error_log( $scores, "$loc: called with match=$title, set num=$setnum and these scores: ");
        
        if( 0 === array_sum( $scores ) )  return; //E A R L Y return

        if( $match->isBye() || $match->isWaiting() ) {
            $this->log->error_log( sprintf( "%s -> Cannot record  scores because '%s' has bye or is watiing.", $loc,$match->title() ) );
            throw new ChairUmpireException( sprintf("Cannot record scores because '%s' has bye or is wating.",$match->title() ) );
        }

        if( $this->isLocked( $match ) ) {
            $this->log->error_log( sprintf("%s -> Cannot record scores because match '%s' is locked", $loc, $match->title() ) );
            throw new ChairUmpireException( sprintf("Cannot record scores because '%s' is locked.",$match->title() ) );
        }

        if( $setnum !== 1 ) {
            $this->log->error_log( sprintf("%s -> Set number must be one '%d' for a points match '%s'", $loc, $setnum, $match->title() ) );
            $setnum = 1;
        }

        $maxGames = $this->GamesPerSet + 1;
        if($homewins >= $maxGames ) {
            $diff = $homewins - $visitorwins;
            switch($diff) {
                case 0:
                case 1:
                case 2:
                    break;
                default: //assume 7 to 5
                    $homewins = $maxGames;
                    $visitorwins = $this->GamesPerSet - 1;
                    break;
            }
        }
        elseif( $visitorwins >= $maxGames ) {
            $diff =  $visitorwins - $homewins;
            switch($diff) {
                case 0:
                case 1:
                case 2:
                    break;
                default: //assume 7 to 5
                    $visitorwins = $maxGames;
                    $homewins = $this->GamesPerSet - 1;
                    break;
            }
        }
        $match->setScore( $setnum, $homewins, $visitorwins );
        $this->log->error_log( sprintf( "%s -> Set home games=%d and visitor games=%d for %s."
                            , $loc, $homewins, $visitorwins, $match->title()  ) );
 
        $match->save();
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
    public function getMatchSummary( Match &$match ) {
        $startTime = \microtime( true );
        $loc = __CLASS__ . "::" . __FUNCTION__;

        $title = $match->toString();
        $this->log->error_log("$loc($title)");
        $this->log->error_log(debug_backtrace()[1]['function'],"Called By");
        
        //NOTE: It is imperative that sets be in ascending order of set number
        $sets = $match->getSets( );
        $numSets = count( $sets );

        if( $numSets > 1 ) {
            $mess = "{$loc}({$title}): The number of sets must be 1 or 0 not '{$numSets}'";
            $this->log->error_log($mess);
            //throw new InvalidSetException( $mess );
        }
    
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
                //$homeTB = $set->getHomeTieBreaker();
                $visitorW = $set->getVisitorWins();
                $visitorGamesWon += $visitorW;
                //$visitorTB = $set->getVisitorTieBreaker();

                $this->log->error_log( sprintf( "%s(%s): home W=%d, visitor W=%d"
                                        , $loc, $set->toString(), $homeW, $visitorW ) );
                
                if( !in_array($homeW, array($this->GamesPerSet, $this->GamesPerSet + 1))
                &&  !in_array($visitorW, array($this->GamesPerSet, $this->GamesPerSet + 1) )) {
                    $setInProgress = $set->getSetNumber();
                    break; //not done yet
                }
                if( ($homeW - $visitorW >= $this->mustWinBy ) ) {
                    ++$homeSetsWon;
                }
                elseif( ($visitorW - $homeW >= $this->mustWinBy) ) {
                    ++$visitorSetsWon;
                }

                $setInProgress = $set->getSetNumber();
                //Best of 1 happened yet?
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

        $result = [  "matchId"         => $match->toString()
                    ,"andTheWinnerIs"  => $andTheWinnerIs
                    ,"setInProgress"   => $setInProgress
                    ,"finalSet"        => $finalSet
                    ,"homeSetsWon"     => $homeSetsWon
                    ,"homeGamesWon"    => $homeGamesWon
                    ,"visitorSetsWon"  => $visitorSetsWon
                    ,"visitorGamesWon" => $visitorGamesWon
                    ,"earlyEnd"        => $earlyEnd
                    ,"comments"        => $cmts ];

        error_log( sprintf("%s: %0.6f", "${loc} Elapsed Time", commonlib\micro_time_elapsed( $startTime )));
        $this->log->error_log($result, "$loc: Match Summary Result");

        return $result;
    }
       
    /**
     * Retrieve the Champion for this bracket
     * @param Bracket $bracket
     * @return Entrant who won the bracket or null if not completed
     */
    public function getChampion( &$bracket ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        $bracketName = $bracket->getName();
        $this->log->error_log("$loc($bracketName)");
        $champion = null;

        if( !$bracket->isApproved() ) {
            $lastRound = $bracket->getNumberOfRounds();
            $finalMatches = $bracket->getMatchesByRound( $lastRound );

            if( count( $finalMatches ) !== 1 ) {
                $c = count( $finalMatches );
                $errmess = "Final round in bracket '{$bracketName}' with {$lastRound} rounds does not have exactly one match({$c}).";
                $this->log->error_log( $errmess );
                //throw new InvalidBracketException( $errmess );
            } else {
                $finalMatch = array_pop($finalMatches);
                $this->isLocked( $finalMatch, $champion );
            }

        }
        
        return $champion;
    }
    
    /**
     * Return the score by set of the given Match
     * @param object Match $match
     * @return array of scores
     */
	public function getScores( Match &$match, bool $winnerFirst = false ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;

        $mess = sprintf( "%s(%s) starting", $loc,$match->toString() );
        $this->log->error_log( $mess );

        $sets = $match->getSets();
        $scores = array();

        foreach($sets as $set ) {
            $setnum = (int)$set->getSetNumber();
            $mess = sprintf("%s(%s) -> Home=%d Visitor=%d HomeTB=%d VisitorTB=%d"
                           , $loc, $set->toString()
                           , $set->getHomeWins(), $set->getVisitorWins(), $set->getHomeTieBreaker(), $set->getVisitorTieBreaker() );
            $this->log->error_log( $mess );
            if( $this->winnerIsVisitor( $match ) && $winnerFirst ) {
                $scores[$setnum] = array( $set->getVisitorWins(), $set->getHomeWins(), $set->getVisitorTieBreaker(), $set->getHomeTieBreaker() );
            }
            else {
                $scores[$setnum] = array( $set->getHomeWins(), $set->getVisitorWins(), $set->getHomeTieBreaker(), $set->getVisitorTieBreaker() );
            }
        }
        return $scores;
    }

} //end of class