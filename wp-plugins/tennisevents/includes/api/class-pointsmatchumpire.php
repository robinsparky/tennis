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

    protected $PointsPerWin = 1;

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
     * Overerides function in ChairUmpire and adds PointsPerWin property.
     * @param string $score_rules Identifies (i.e. key to) score rules from ScoreTypes.
     * @return array Rules for this identifier of score type
     */
    public function setScoringRules( string $score_rules) {
        $loc = __CLASS__ . '::' . __FUNCTION__;

        parent::setScoringRules( $score_rules );
        $rules = ScoreType::get_instance()->getScoringRules( $score_rules );
        //$this->log->error_log($rules,"$loc: rules...");

        $numVars = extract( $rules );
        $this->PointsPerWin = $PointsPerWin ?? 1;
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
                case 'tie':
                    $andTheWinnerIs = 'tie';
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
        $this->log->error_log( "$loc($title) called by {$calledBy}" );
        //$this->log->error_log(debug_backtrace()[1]['function'],"Called By");
        
        //NOTE: It is imperative that sets be in ascending order of set number
        $sets = $match->getSets( );
        $numSets = count( $sets );

        // if( $numSets > 1 ) {
        //     $mess = "{$loc}({$title}): The number of sets must be 1 or 0 not '{$numSets}'";
        //     $this->log->error_log($mess);
        //     //throw new InvalidSetException( $mess );
        // }
    
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
        $tie = false;

        //TODO: Make use of "ties" in the database!!!!!!!!!!
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
                $homeTB = $set->getHomeTieBreaker();
                $visitorW = $set->getVisitorWins();
                $visitorGamesWon += $visitorW;
                $visitorTB = $set->getVisitorTieBreaker();

                $this->log->error_log( sprintf( "%s(%s): home W=%d, visitor W=%d"
                                        , $loc, $set->toString(), $homeW, $visitorW ) );
                
                if( $homeW < $this->getGamesPerSet() &&  $visitorW < $this->getGamesPerSet() ) {
                    $setInProgress = $set->getSetNumber();
                    break; //not done yet so don't even consider other sets
                }
                
                if( ($homeW - $visitorW) >= $this->MustWinBy
                || ($homeW > $visitorW) ) {
                    ++$homeSetsWon;
                }
                elseif( ($visitorW - $homeW) >= $this->MustWinBy 
                || ($visitorW > $homeW) ) {
                    ++$visitorSetsWon;
                }
                else {
                    //No check for tie breakers because ties are allowed in POINTS score types
                    //tie score?
                    $homeSetsWon += 0.5;
                    $visitorSetsWon += 0.5;
                }

                $setInProgress = $set->getSetNumber();

                if( $setInProgress === $this->getMaxSets() ) {
                    
                    if( $homeSetsWon === $visitorSetsWon ) {
                        $andTheWinnerIs = 'tie';
                        $finalSet = $set->getSetNumber();
                        $setInProgress = 0;
                        break;
                    }
                    elseif( $homeSetsWon > $visitorSetsWon ) {
                        $andTheWinnerIs = 'home';
                        $finalSet = $set->getSetNumber();
                        $setInProgress = 0;
                        break;
                    }
                    else {
                        $andTheWinnerIs = 'visitor';
                        $finalSet = $set->getSetNumber();
                        $setInProgress = 0;
                        break;
                    }
                } //in final set
            } //early end?
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

        $this->log->error_log( sprintf("%s: %0.6f", "${loc} Elapsed Time", commonlib\micro_time_elapsed( $startTime )));
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
        $this->log->error_log(debug_backtrace()[1]['function'],"Called By");

        $champion = null;

        if( $bracket->isApproved() ) {
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

} //end of class