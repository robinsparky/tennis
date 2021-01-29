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
    
    public function getPointsPerWin() {
        return $this->PointsPerWin;
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
                //$homeTB = $set->getHomeTieBreaker();
                $visitorW = $set->getVisitorWins();
                $visitorGamesWon += $visitorW;
                //$visitorTB = $set->getVisitorTieBreaker();

                $this->log->error_log( sprintf( "%s(%s): home W=%d, visitor W=%d"
                                        , $loc, $set->toString(), $homeW, $visitorW ) );
                
                if( $homeW < $this->getGamesPerSet() &&  $visitorW < $this->getGamesPerSet() ) {
                    $setInProgress = $set->getSetNumber();
                    break; //not done yet so don't even consider other sets
                }
                
                if( ($homeW - $visitorW >= $this->MustWinBy ) ) {
                    ++$homeSetsWon;
                }
                elseif( ($visitorW - $homeW >= $this->MustWinBy) ) {
                    ++$visitorSetsWon;
                }
                else {
                    if( false === $this->includeTieBreakerScores( $setNum )  ) {
                        //tie score and we know there is only one set
                        if( $homeW === $visitorW ) {
                            $homeSetsWon += 0.5;
                            $visitorSetsWon += 0.5;
                        }
                    }
                    else { //Tie breaker
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
                //Best of 1 happened yet?
                if( ceil($homeSetsWon) >= ceil( $this->getMaxSets()/2.0 ) ) {
                    $andTheWinnerIs = 'home';
                    if( $homeSetsWon === $visitorSetsWon ) {
                        $andTheWinnerIs = 'tie';
                    }
                    $finalSet = $set->getSetNumber();
                    $setInProgress = 0;
                    break;
                }
                elseif( ceil($visitorSetsWon) >= ceil( $this->getMaxSets()/2.0 ) ) {
                    $andTheWinnerIs = 'visitor';
                    if( $homeSetsWon === $visitorSetsWon ) {
                        $andTheWinnerIs = 'tie';
                    }
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
     
       /**
     * This function produces an array of statistics for the given bracket
     * TODO: Should be moved the ChairUmpire
     * @param Bracket The bracket for which summary is required
     * @return array matches completed and total matches played by round
     *               total matches completed and played for the bracket
     *               Bracket champion if all matches have been played
     */
    public function getBracketSummary( Bracket $bracket ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        $calledBy = isset(debug_backtrace()[1]['class']) ? debug_backtrace()[1]['class'] . '::'. debug_backtrace()[1]['function'] : debug_backtrace()[1]['function'];
        $this->log->error_log("{$loc} Called By: {$calledBy}");
        //$this->log->error_log(debug_backtrace()[1]['function'],"Called By");
        
        if( !$bracket->hasEvent() ) {
            throw new InvalidBracketException("Bracket's event is missing");
        }
        
        $matchesByRound = $bracket->getMatchHierarchy();
        
        $numRounds = 0;
        $numMatches = 0;
        foreach( $matchesByRound as $r => $matches ) {
            if( $r > $numRounds ) $numRounds = $r;
            foreach( $matches as $match ) {
                ++$numMatches;
            }
        }

        //$numRounds = $bracket->getNumberOfRounds();
        $summary=[];
        $completed = 0;
        $total = 0;
        $summary["byRound"] = array();
        $lastMatchNum = 0;
        $allMatchesCompleted = true;
        for($r = 1; $r <= $numRounds; $r++ ) {
            $completedByRound = $totalByRound = 0;
            foreach( $matchesByRound[$r] as $match ) {
                ++$totalByRound;
                $lastMatchNum = $match->getMatchNumber();
                if( !empty( $this->matchWinner( $match ) ) ) {
                    ++$completedByRound;
                }
                if( $allMatchesCompleted && !$this->isLocked( $match ) ) $allMatchesCompleted = false;
            }
            $summary["byRound"][$r] = $completedByRound . '/' . $totalByRound;
            $total += $totalByRound;
            $completed += $completedByRound;
        }

        $summary["completedMatches"] = $completed;
        $summary["totalMatches"] = $total;
        $summary["champion"] = '';
        //Determine Champion
        if( $total === $completed ) {
            switch( $bracket->getEvent()->getFormat() ) {
                case Format::ELIMINATION:
                    $champion = $this->matchWinner( $matchesByRound[$numRounds][$lastMatchNum] );
                    $champion = is_null( $champion ) ? 'Could not determine the champion!' : $champion->getName();
                    $summary["champion"] = $champion;
                break;
                case Format::ROUNDROBIN:
                    if( $allMatchesCompleted ) { //only find champion if all matches have been completed
                        $entrantSummary = $this->getEntrantSummary( $bracket );
                        $champion = '';
                        $maxPoints = 0;
                        foreach( $entrantSummary as $player ) {
                            if( $player["totalPoints"] > $maxPoints ) {
                                $maxPoints = $player["totalPoints"];
                                $champion = $player["name"];
                            }
                        }
                    }
                break;
                default:
                break;
            }
            $summary["champion"] = $champion;
        }
        unset( $matchesByRound );
        unset( $entrantSummary );
        $this->log->error_log("$loc>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>");
        return $summary;
    }
    
    /**
     * Get summary of entrant match wins by round as well as total points and total games
     * @param object Bracket $bracket
     * @return array entrant summary: name, position, points, games and sets
     *                                and matches won per round
     */
    public function getEntrantSummary( Bracket $bracket ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;        
        $calledBy = isset(debug_backtrace()[1]['class']) ? debug_backtrace()[1]['class'] . '::'. debug_backtrace()[1]['function'] : debug_backtrace()[1]['function'];
        $this->log->error_log("{$loc} Called by: {$calledBy}");
        //$this->log->error_log(debug_backtrace()[1]['function'],"Called By");

        if( !$bracket->hasEvent() ) {
            throw new InvalidBracketException("Bracket's event is missing");
        }

        $summary = [];
        $pointsForWin = $this->getPointsPerWin();
        $matchesByEntrant = $bracket->matchesByEntrant();
        $numRounds = $bracket->getNumberOfRounds();
        foreach( $matchesByEntrant as $matchInfo) {
            $entrant = $matchInfo[0];
            $matches = $matchInfo[1];
            $totalGames = 0;
            $totalPoints = 0;
            $totalSetsWon = 0;
            $totalMatchesWon = 0;
            $totalMatchesTied = 0;
            $entrantSummary=[];
            $entrantSummary["position"] = $entrant->getPosition();
            $entrantSummary["name"] = $entrant->getName();
            for( $r = 1; $r <= $numRounds; $r++ ) {
                $totalMatchesWon = 0;
                $totalMatchesTied = 0;
                $entrantSummary[$r] = 0;
                foreach( $matches as $match ) {
                    if( $r != $match->getRoundNumber() ) continue;
                    extract( $this->getMatchSummary( $match ) );
                    if( $entrant->getName() === $this->getHomePlayer( $match ) ) {
                        $totalGames += $homeGamesWon;
                        $totalSetsWon += $homeSetsWon;
                        if( $andTheWinnerIs === 'home') {
                            ++$totalMatchesWon;
                            $totalPoints += $totalMatchesWon * $pointsForWin;
                        }
                        elseif( $andTheWinnerIs === 'tie') {
                            ++$totalMatchesTied;
                            $totalPoints += $totalMatchesTied * $pointsForWin/2;
                        }
                    }
                    elseif( $entrant->getName() === $this->getVisitorPlayer( $match ) ) {
                        $totalGames += $visitorGamesWon;
                        $totalSetsWon += $visitorSetsWon;
                        if( $andTheWinnerIs === 'visitor') {
                            ++$totalMatchesWon;
                            $totalPoints += $totalMatchesWon * $pointsForWin;
                        }
                        elseif( $andTheWinnerIs === 'tie') {
                            ++$totalMatchesTied;
                            $totalPoints += $totalMatchesTied * $pointsForWin/2;
                        }
                    }
                } //matches
                $entrantSummary[$r] += $totalMatchesWon + $totalMatchesTied/2;
            } //rounds
            $entrantSummary["totalPoints"] = $totalPoints;
            $entrantSummary["totalGames"] = $totalGames;
            $entrantSummary["totalSets"] = $totalSetsWon;
            $entrantSummary["totalTies"] = $totalMatchesTied;
            $summary[] = $entrantSummary;
        } //matchesByEntrant

        unset( $matchesByEntrant );
        unset( $matchInfo );
        unset( $matches );
        $this->log->error_log("$loc>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>");
        return $summary;
    }

} //end of class