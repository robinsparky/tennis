<?php
namespace api;

use \TennisEvents;
use commonlib\GW_Support;
use datalayer\Event;
use datalayer\TennisMatch;
use datalayer\Entrant;
use datalayer\ScoreType;
use datalayer\Bracket;
use api\ChairUmpire;
use datalayer\InvalidBracketException;

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
class TTCTeamTennisUmpire extends ChairUmpire
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
	public static function getInstance() : self {
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
     * Overerides function in ChairUmpire and adds TTCTeamTennis properties.
     * Heavy lifting is done in parent class.
     * @param string $score_rules Identifies (i.e. key to) score rules from ScoreTypes.
     */
    public function setScoringRules( string $score_rules) {
        $loc = __CLASS__ . '::' . __FUNCTION__;

        parent::setScoringRules( $score_rules );
        $rules = ScoreType::get_instance()->getScoringRules( $score_rules );
        //$this->log->error_log($rules,"$loc: rules...");
        $numVars = extract( $rules );
        $this->PointsPerWin = $PointsPerWin ?? 2;
        $this->log->error_log($this, "{$loc}: initialized this ...");
    }

    public function getPointsPerWin() {
        return $this->PointsPerWin;
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
        $homePointsWon = 0;
        $visitorPointsWon = 0;
        $cmts = '';
        $tie = false;
        $pointsPerTie = $this->PointsPerWin === 2 ? $this->PointsPerWin / 2 : 0;
        $totalSetsPlayed = 0;
        
        extract($match->findEarlyEnd());
        $this->log->error_log("$loc: {$match->toString()} Early set=$setNumEarly; Early End=$earlyEnd; Comments={$cmts}");

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
                $visitorPointsWon += $this->PointsPerWin * $this->getMaxSets();
                $finalSet = $setNumEarly;
                break;
            }
            elseif( 2 === $earlyEnd ) {
                //Visitor defaulted
                $andTheWinnerIs = $home;
                $homePointsWon += $this->PointsPerWin * $this->getMaxSets();
                $finalSet = $setNumEarly;
                break;
            }
            else {
                $homeW = $set->getHomeWins();
                $homeGamesWon += $homeW;
                //$homeTB = $set->getHomeTieBreaker();
                $visitorW = $set->getVisitorWins();
                $visitorGamesWon += $visitorW;
                //$visitorTB = $set->getVisitorTieBreaker();
                if($homeW === 0 && $visitorW === 0 ) {
                    //Set not started yet
                    $setInProgress = $set->getSetNumber();
                    break;
                }

                ++$totalSetsPlayed;
                $this->log->error_log( sprintf( " %s(%s):Sets Played=%d, home W=%d, visitor W=%d"
                                        , $loc, $set->toString(), $totalSetsPlayed, $homeW, $visitorW ) );
                
                if( ($homeW - $visitorW) >= $this->MustWinBy
                || ($homeW > $visitorW) ) {
                    ++$homeSetsWon;
                    $homePointsWon += $this->PointsPerWin;
                }
                elseif( ($visitorW - $homeW) >= $this->MustWinBy 
                || ($visitorW > $homeW) ) {
                    ++$visitorSetsWon;
                    $visitorPointsWon += $this->PointsPerWin;
                }
                elseif( $homeW === $visitorW ) {
                    $homeSetsWon += 0.5;
                    $visitorSetsWon += 0.5;
                    $homePointsWon += $pointsPerTie;
                    $visitorPointsWon += $pointsPerTie;
                }

                $setInProgress = $set->getSetNumber();
                if($setNum < $this->getMaxSets()) {
                    //Not the final set yet
                    continue;
                }   

                //Final set - determine match winner
                if( $totalSetsPlayed >= $this->getMaxSets() ) {
                    
                    if( $homePointsWon === $visitorPointsWon ) {
                        $andTheWinnerIs = 'tie';
                        $tie = true;
                        $finalSet = $set->getSetNumber();
                        $setInProgress = 0;
                        break;
                    }
                    elseif( $homePointsWon > $visitorPointsWon ) {
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
        } //foreach set
        
        $winnerName = empty( $andTheWinnerIs) ? 'unknown' : $andTheWinnerIs;
        // $this->log->error_log("$loc: The winner is '{$winnerName}' with points won: home={$homePointsWon} and visitor={$visitorPointsWon}");

        $result = [  "matchId"         => $match->toString()
                    ,"andTheWinnerIs"  => $andTheWinnerIs
                    ,"setInProgress"   => $setInProgress
                    ,"finalSet"        => $finalSet
                    ,"homeSetsWon"     => $homeSetsWon
                    ,"homeGamesWon"    => $homeGamesWon
                    ,"visitorSetsWon"  => $visitorSetsWon
                    ,"visitorGamesWon" => $visitorGamesWon
                    ,"earlyEnd"        => $earlyEnd
                    ,"comments"        => $cmts 
                    ,"homePointsWon"   => $homePointsWon
                    ,"visitorPointsWon"=> $visitorPointsWon];

        //$this->log->error_log( sprintf("%s: %0.6f", "{$loc} Elapsed Time", commonlib\micro_time_elapsed( $startTime )));
        // $this->log->error_log($result, "$loc: TennisMatch Summary Result");

        return $result;
    }
       
    /**
     * Retrieve the Champion for this bracket
     * @param Bracket $bracket
     * @return array Entrant and final score of player who won the championship for this bracket
     *               with ['ChampionName'=>null, 'ChampionScore'=>''] if draw not completed
     */
    public function getChampion( &$bracket ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        $bracketName = $bracket->getName();
        $this->log->error_log("$loc($bracketName)");        
        //$this->log->error_log(debug_backtrace()[1]['function'],"Called By");

        $champion = null;
        $champScore = '';

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
                $champScore = $this->strGetScores($finalMatch, true);
            }
        }
        
        return [self::CHAMPIONNAME=>$champion, self::CHAMPSCORE=>$champScore];
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
        $pointsForTie = $pointsForWin / 2;
        $this->log->error_log("$loc: pointsForWin={$pointsForWin}; pointsForTie={$pointsForTie}");
    
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
            $overallPoints = 0;
            $entrantSummary=[];
            $entrantSummary["position"] = $entrant->getPosition();
            $entrantSummary["name"] = $entrant->getName();
            for( $r = 1; $r <= $numRounds; $r++ ) {
                $totalMatchesWon = 0;
                $totalMatchesTied = 0;
                $totalPoints = 0;
                $entrantSummary[$r] = 0;
                foreach( $matches as $match ) {
                    if( $r != $match->getRoundNumber() ) continue;
                    extract( $this->getMatchSummary( $match ) );
                    if( $entrant->getName() === $this->getHomePlayer( $match ) ) {
                        $totalGames += $homeGamesWon;
                        $totalSetsWon += $homeSetsWon;
                        $totalPoints += $homePointsWon;
                        if( $andTheWinnerIs === 'home') {
                            ++$totalMatchesWon;
                        }
                        elseif( $andTheWinnerIs === 'tie') {
                            ++$totalMatchesTied;
                        }
                    }
                    elseif( $entrant->getName() === $this->getVisitorPlayer( $match ) ) {
                        $totalGames += $visitorGamesWon;
                        $totalSetsWon += $visitorSetsWon;
                        $totalPoints += $visitorPointsWon;
                        if( $andTheWinnerIs === 'visitor') {
                            ++$totalMatchesWon;
                        }
                        elseif( $andTheWinnerIs === 'tie') {
                            ++$totalMatchesTied;
                        }
                    }
                    $overallPoints += $totalPoints;
                } //matches
                $entrantSummary[$r] += $totalPoints;
            } //rounds
            $entrantSummary["totalPoints"] = $overallPoints;
            $entrantSummary["totalGames"] = $totalGames;
            $entrantSummary["totalSets"] = $totalSetsWon;
            $entrantSummary["totalTies"] = $totalMatchesTied;
            $summary[] = $entrantSummary;
            $this->log->error_log($entrantSummary, "$loc: Entrant Summary for " . $entrant->getName() );
        } //matchesByEntrant

        unset( $matchesByEntrant );
        unset( $matchInfo );
        unset( $matches );
        $this->log->error_log("$loc>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>");
        return $summary;
    }       
    
    /**
     * This function produces an array of statistics for the given bracket
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
        // if( $total === $completed ) {
        //     if( $allMatchesCompleted ) { //only find champion if all matches have been completed
        //         $entrantSummary = $this->getEntrantSummary( $bracket );
        //         $champion = '';
        //         $maxPoints = 0;
        //         foreach( $entrantSummary as $player ) {
        //             if( $player["totalPoints"] > $maxPoints ) {
        //                 $maxPoints = $player["totalPoints"];
        //                 $champion = $player["name"];
        //             }
        //         }
        //     }  
 
        //     $summary["champion"] = $champion;
        // }
        unset( $matchesByRound );
        unset( $entrantSummary );
        $this->log->error_log("$loc>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>");
        return $summary;
    }

    /**
     * Produce Team Standings table
     * @param array $entrantSummary Summary of entrants returned by getEntrantSummary()
     * @return array Team standings with team name, total points and total games
     */
    public function getTeamStandings( array $entrantSummary) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("{$loc} called...");
        //$this->log->error_log(debug_backtrace()[1]['function'],"Called By");

        //Produce Team Standings table
        $numTeams = count( $entrantSummary ) / 2;
        $standings = array();
        foreach( range( 1, $numTeams ) as $teamNum ) {
            $standings[$teamNum] = array(
                'teamName' => '',
                'points' => 0,
                'games' => 0
            );
        }
        foreach($entrantSummary as $entrantSummary ) {
            foreach( range( 1, $numTeams ) as $teamNum ) {
                if( strpos( $entrantSummary["name"], $teamNum ) !== false ) {
                    $standings[$teamNum]['teamName'] = 'Team ' . $teamNum;
                    $standings[$teamNum]['points'] += $entrantSummary['totalPoints'];
                    $standings[$teamNum]['games'] += $entrantSummary['totalGames'];
                    break;
                }
            }
        }
        usort( $standings, function( $a, $b ) {
            return $b['points'] - $a['points'];
        } );

        return $standings;
    }
} //end of class