<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dir = plugin_dir_path( __DIR__ );
include_once($dir . '/gw-support.php' );

class RegulationMatchUmpire extends ChairUmpire
{
	//This class's singleton
	private static $_instance;

	/**
	 * RegulationMatchUmpire Singleton
	 *
	 * @since 1.0
	 * @static
	 * @return $_instance --Main instance.
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
			wp_die( sprintf( esc_html__( '%s is a singleton class and you cannot create a second instance.', 'ten' ), get_class( $this ) ) );
		}
        parent::__construct( true );

	}
    
    /**
     * Set the maximum number of sets in this tournament
     * @param int $max the maximum
     * @return bool true if successful, false otherwise
     */
	public function setMaxSets( int $max = 3 ) {
		switch( $max ) {
			case 3:
			case 5:
				$this->MaxSets = $max;
				$result = true;
				break;
			default:
			$result = false;
		}
		return $result;
	}

    /**
     * Record game and tie breaker scores for a given set pf the supplied Match.
     * @param object $match The match whose score are recorded
     * @param int $setnum The set number 
     * @param int ...$scores if 2 args then game scores; if 4 then games and tiebreaker scores
     */
	public function recordScores( Match &$match, int $setnum, int ...$scores ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;

        $title = $match->title();
        $this->log->error_log( $scores, "$loc: called with match=$title, set num=$setnum and these scores: ");

        if( $match->isBye() || $match->isWaiting() ) {
            $this->log->error_log( sprintf( "%s -> Cannot record  scores because '%s' has bye or is watiing.", $loc,$match->title() ) );
            throw new ChairUmpireException( sprintf("Cannot record scores because '%s' has bye or is wating.",$match->title() ) );
        }

        if( $this->isLocked( $match ) ) {
            $this->log->error_log( sprintf("%s -> Cannot record scores because match '%s' is locked", $loc, $match->title() ) );
            throw new ChairUmpireException( sprintf("Cannot record scores because '%s' is locked.",$match->title() ) );
        }

        switch( count( $scores ) ) {
            case 2: //just 2 game scores ... no tiebreakers
                $homewins    = $scores[0];
                $visitorwins = $scores[1];
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
                break;
            case 4: //Both game scores and tiebreaker scores are available
                $homewins    = $scores[0];
                $visitorwins = $scores[2];
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
                //$homewins    = min( $scores[0], $this->GamesPerSet );
                $home_tb_pts = $scores[1];
                //$visitorwins = min( $scores[2], $this->GamesPerSet );
                $visitor_tb_pts = $scores[3];
                $match->setScore( $setnum, $homewins, $visitorwins, $home_tb_pts, $visitor_tb_pts );
                $this->log->error_log( sprintf( "%s -> Set home games=%d(%d) and visitor games=%d(%d) for %s."
                                  , $loc, $homewins, $home_tb_pts, $visitorwins, $visitor_tb_pts, $match->title()  ) );
                break;
            default: 
                $this->log->error_log( sprintf( "%s -> Did not find 2 or 4 scores in args for %s.", $loc, $match->title() ) );
                break;
        }

        $match->save();
    }

    /**
     * Determine the winner of the given Match
     * @param object Match Reference to a $match object
     * @return object Entrant who won or null if not completed yet
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
            
        extract( $this->getMatchSummary( $match ) );
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
     * This function removes sets that were kept after the final set or set in progress
     * @param object Match
     * @return int The number of sets removed from the match
     */
    public function trimSets( &$match ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        $title = $match->toString();
        $this->log->error_log("$loc($title)");

        extract( $this->getMatchSummary( $match ) );
        
        $this->log->error_log("$loc($title): set number {$setInProgress} is in progress");
        $this->log->error_log("$loc($title): final set number {$finalSet}");

        $cutoff = max( $setInProgress, $finalSet );
        if( $cutoff > 0 ) {
            //Remove all extraneous sets
            $numRemoved = 0;
            for( $setNum = $cutoff + 1; $setNum <= $this->MaxSets; $setNum++ ) {
                $match->removeSet( $setNum );
                ++$numRemoved;
            }
            if( $numRemoved > 0 ) {
                $this->log->error_log("$loc($title): removed {$numRemoved} extraneous sets");
            }
        }
        return $numRemoved;
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

    /**
     * Return the score by set as a string
     * @param object Match $match
     * @return string representation of the scores
     */
	public function strGetScores( Match &$match ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;

        $mess = sprintf( "%s(%s) called", $loc,$match->toString() );
        $this->log->error_log( $mess );

        $arrScores = $this->getScores( $match );
        if( count( $arrScores) === 0 ) return '';

        $strScores = '';
        $sep = ',';
        $setNums = range( 1, $this->getMaxSets() );
        foreach( $setNums as $setNum ) {
            if( $setNum === $this->MaxSets ) $sep = '';
            if( array_key_exists( $setNum, $arrScores ) ) {
                $scores = $arrScores[ $setNum ];
                // $mess = sprintf("%s(%s) -> Set=%d Home=%d Visitor=%d HomeTB=%d VisitorTB=%d"
                //                 , $loc, $match->toString(), $setNum
                //                 , $scores[0], $scores[1], $scores[2], $scores[3] );
                if( $scores[0] === $scores[1] && $scores[0] === $this->GamesPerSet ) {
                    $strScores .= sprintf("%d(%d)-%d(%d)%s ", $scores[0], $scores[2], $scores[1], $scores[3], $sep);
                } 
                else {
                    $strScores .= sprintf("%d-%d%s ", $scores[0], $scores[1], $sep);
                }
            }
        }
        return $strScores;
    }

    /**
     * Provides HTML for modifying scores.
     * Includes games and tie break points by set.
     * @param object Match $match
     * @return string HTML markup for table with save and cancel buttons
     */
    public function tableModifyScores( Match &$match ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;

        $mess = sprintf( "%s(%s) called", $loc, $match->toString() );
        $this->log->error_log( $mess );

        $arrScores = $this->getScores( $match );
        $setNums = range( 1, $this->getMaxSets() );
        
        $saveCancel =<<<EOT
<div class='modifymatchscores save-cancel-buttons'>
<button class='savematchscores modifymatchscores'>Save</button>
<button class='cancelmatchscores modifymatchscores'>Cancel</button></div>
EOT;

        $gameHdr = __("Games",TennisEvents::TEXT_DOMAIN);
        $tbHdr   = __("T.B.", TennisEvents::TEXT_DOMAIN);
        //Start the table and place the header row
        $tableScores = '<table class="modifymatchscores tennis-modify-scores ui-sortable-handle">';
        $tableScores .= '<caption>' . $match->toString() . '</caption>';
        $tableScores .= '<thead class="modifymatchscores"><tr>';
        foreach( $setNums as $setNum ) {
            $tableScores .= "<th colspan='2'>$setNum</th>";
        }
        $tableScores .= "</tr><tr>";        
        foreach( $setNums as $setNum ) {
            $tableScores .= "<th>{$gameHdr}</th><th>{$tbHdr}</th>";
        }
        $tableScores .= "</tr></thead><tbody>";

        //Now put the actual scores into the table
        $homeScores  = "<tr>";
        $visitorScores = "<tr>";
        foreach( $setNums as $setNum ) {
            //If set does not exist yet then fake it
            if( !array_key_exists( $setNum, $arrScores ) ) {
                $arrScores[$setNum] = [0,0,0,0];
            }
            $scores = $arrScores[ $setNum ];
            $mess = sprintf("%s(%s) -> Set=%d Home=%d Visitor=%d HomeTB=%d VisitorTB=%d"
                            , $loc, $match->toString(), $setNum
                            , $scores[0], $scores[1], $scores[2], $scores[3] );
            $this->log->error_log($mess);

            $homeTBScores = $visitorTBScores = '';
            if( $scores[0] === $scores[1] && $scores[0] === $this->GamesPerSet ) {
                $homeTBScores = sprintf("<sup>%d</sup>", $scores[2]);
                $visitorTBScores = sprintf("<sup>%d</sup>", $scores[3]);
            } 
            $homeScores .= sprintf("<td><input type='number' class='modifymatchscores' name='homeGames' value='%d' min='%d' max='%d'></td>"
                                    , $scores[0] 
                                    , 0, $this->GamesPerSet + 1 );
            $homeScores .= sprintf("<td><input class='modifymatchscores' type='number' name='homeTieBreak' value='%d' min='0' max='7'></td>"
                                      , $scores[2]);
            
            $visitorScores .= sprintf("<td><input type='number' class='modifymatchscores' name='visitorGames' value='%d' min='%d' max='%d'></td>"
                                    , $scores[1] 
                                    , 0, $this->GamesPerSet + 1 );                   
            $visitorScores .= sprintf("<td><input class='modifymatchscores' type='number' name='visitorTieBreak' value='%d' min='0' max='7'></td>"
                                      , $scores[3]);
        }
        $homeScores .= "</tr>";
        $visitorScores .= "</tr>";
        $tableScores .= $homeScores;
        $tableScores .= $visitorScores;
        $tableScores .= "</tbody></table>";
        $tableScores .= $saveCancel;


        return $tableScores;

    }

    /**
     * Provides HTML for displaying scores as a table.
     * @param object Match $match
     * @return string HTML markup for table
     */
    public function tableDisplayScores( Match &$match ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;

        $mess = sprintf( "%s(%s) called", $loc, $match->toString() );
        $this->log->error_log( $mess );

        $scoreClass = "tennis-display-scores";
        $arrScores = $this->getScores( $match );
        $setNums = range( 1, $this->getMaxSets() );

        //Start the table and place the header row
        $tableScores = '<table class="' . $scoreClass . '">';
        $tableScores .= "<tbody>";

        //Now put the actual scores into the table
        $homeScores  = "<tr>";
        $visitorScores = "<tr>";
        foreach( $setNums as $setNum ) {
            //If set does not exist yet then fake it
            if( !array_key_exists( $setNum, $arrScores ) ) {
                $arrScores[$setNum] = [0,0,0,0];
                //continue;
            }
            $scores = $arrScores[ $setNum ];
            $mess = sprintf("%s(%s) -> Set=%d Home=%d Visitor=%d HomeTB=%d VisitorTB=%d"
                            , $loc, $match->toString(), $setNum
                            , $scores[0], $scores[1], $scores[2], $scores[3] );
            $this->log->error_log($mess);

            $homeTBScores = $visitorTBScores = '';
            if( $scores[0] === $scores[1] && $scores[0] === $this->GamesPerSet ) {
                $homeTBScores = sprintf("<sup>%d</sup>", $scores[2]);
                $visitorTBScores = sprintf("<sup>%d</sup>", $scores[3]);
            } 
            $homeScores .= sprintf("<td><span class='showmatchscores'>%d %s</span></td>"
                                    , $scores[0]
                                    , $homeTBScores );
            
            $visitorScores .= sprintf("<td><span class='showmatchscores'>%d %s</span></td>"
                                    , $scores[1]
                                    , $visitorTBScores);                   
        }
        $homeScores .= "</tr>";
        $visitorScores .= "</tr>";
        $tableScores .= $homeScores;
        $tableScores .= $visitorScores;
        $tableScores .= "</tbody></table>";
        return $tableScores;

    }
    
    /**
     * Provides the HTML markup for displaying scores in list format
     * @param object Match $match
     * @return string HTML markup showing scores in a list <ul>...</ul>
     */
    public function listGetScores( Match &$match ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;

        $mess = sprintf( "%s(%s) called", $loc, $match->toString() );
        $this->log->error_log( $mess );

        $scoreClass = "tennislistscores";
        $arrScores = $this->getScores( $match );
        if( count( $arrScores) === 0 ) return '<ul class="' . $scoreClass . '"></ul>';

        $listScores = '<ul class="' . $scoreClass . '">';
        $homeScores  = "<li>";
        $visitorScores = "<li>";
        $setNums = range( 1, $this->getMaxSets() );
        foreach( $setNums as $setNum ) {
            if( $setNum === $this->MaxSets ) $sep = '';
            if( array_key_exists( $setNum, $arrScores ) ) {
                $scores = $arrScores[ $setNum ];
                // $mess = sprintf("%s(%s) -> Set=%d Home=%d Visitor=%d HomeTB=%d VisitorTB=%d"
                //                 , $loc, $match->toString(), $setNum
                //                 , $scores[0], $scores[1], $scores[2], $scores[3] );
                if( $scores[0] === $scores[1] && $scores[0] === $this->GamesPerSet ) {
                    $homeScores .= sprintf("<span>%d<sub>%d</sup></span>", $scores[0]);
                    $visitorScores .= sprintf("<span>%d<sub>%d</sup></span>", $scores[1], $scores[3]);
                } 
                else {
                    $homeScores .= sprintf("<span>%d</span>", $scores[0]);
                    $visitorScores .= sprintf("<span>%d</span>", $scores[1]);
                }
            }
        }
        $homeScores .= "</li>";
        $visitorScores .= "</li>";
        $listScores .= $homeScores;
        $listScores .= $visitorScores;
        $listScores .= "</ul>";

        return $listScores;

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
    public function getMatchSummary(Match &$match ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        //$this->log->error_log("$loc");
        
        //NOTE: It is imperative that sets be in ascending order of set number
        $sets = $match->getSets( true );
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

        foreach( $sets as $set ) {
            //$this->log->error_log("$loc: set number={$set->getSetNumber()}");
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

                // $this->log->error_log( sprintf( "%s(%s): home W=%d, home TB=%d, visitor W=%d, visitor TB=%d"
                //                         , $loc, $set->toString(), $homeW, $homeTB, $visitorW, $visitorTB ) );
                
                if( !in_array($homeW, array($this->GamesPerSet, $this->GamesPerSet + 1))
                &&  !in_array($visitorW, array($this->GamesPerSet, $this->GamesPerSet + 1) )) {
                    $setInProgress = $set->getSetNumber();
                    break; //not done yet
                }
                if( ($homeW - $visitorW >= 2) ) {
                    ++$homeSetsWon;
                }
                elseif( ($visitorW - $homeW >= 2) ) {
                    ++$visitorSetsWon;
                }
                else { //Tie breaker
                    if( ($homeTB - $visitorTB >= 2) && $homeTB >= $this->TieBreakerMinimum ) {
                        ++$homeSetsWon;
                    }
                    elseif( ($visitorTB - $homeTB >= 2)  && $visitorTB >= $this->TieBreakerMinimum ) {
                        ++$visitorSetsWon;
                    }
                    else { //match not finished yet
                        $setInProgress = $set->getSetNumber();
                        //$this->log->error_log("$loc($title): set number {$set->getSetNumber()} not finished tie breaker yet");
                        break;
                    }
                }
                //Best 3 of 5 or 2 of 3 happened yet?
                if( $homeSetsWon >= ceil( $this->MaxSets/2.0 ) ) {
                    $andTheWinnerIs = 'home';
                    $finalSet = $set->getSetNumber();
                    break;
                }
                elseif( $visitorSetsWon >= ceil( $this->MaxSets/2.0 ) ) {
                    $andTheWinnerIs = 'visitor';
                    $finalSet = $set->getSetNumber();
                    break;
                }
            }
        } //foreach
        
        // $winnerName = empty( $andTheWinnerIs) ? 'unknown' : $andTheWinnerIs;
        // $this->log->error_log("$loc: The winner is '{$winnerName}' with sets won: home={$homeSetsWon} and visitor={$visitorSetsWon}");

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

        $this->log->error_log($result, "$loc: Match Summary");

        return $result;
    }

} //end of class