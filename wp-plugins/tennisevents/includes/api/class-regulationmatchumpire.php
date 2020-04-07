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
     * Default the home player/team for this Match
     * @param $match The match being played
     * @param $cmts  Any comments explaining the default.
     * @return true if successful, false otherwise
     */
	public function defaultHome( Match &$match, string $cmts ) {
        return $this->defaultEntrant( $match, Match::HOME, $cmts );
    }	

    /**
     * Default the visitor player/team for this Match
     * @param $match The match being played
     * @param $cmts  Any comments explaining the default.
     */
    public function defaultVisitor( Match &$match, string $cmts ) {
        return $this->defaultEntrant( $match, Match::VISITOR, $cmts );
    }
    
    /**
     * Default the entrant (player/team) for this Match
     * @param $match The match being played
     * @param string @entrantType either visitor or home
     * @param $cmts  Any comments explaining the default.
     */
    public function defaultEntrant( Match &$match, string $entrantType, string $cmts ) {
        $sets = $match->getSets();
        $size = count( $sets );
        $early = $entrantType === Match::HOME ? 1 : 2;
        if( $size > 0 ) {
            $sets[$size - 1]->setEarlyEnd( $early );
            $sets[$size - 1]->setComments( $cmts );
            $match->setDirty();
        }
        else {
            $set = new Set();
            $set->setSetNumber( 1 );
            $match->addSet( $set );
            $set->setEarlyEnd( $early );
            $set->setComments( $cmts );
            $match->setDirty();
        }
        $result = $match->save();
        return $result > 0;
    }
    
    /**
     * Get status of the Match
     * @param $match Match whose status is calculated
     * @return Status of the given match
     */
	public function matchStatus( Match &$match ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;

        $status = '';
        if( $match->isBye() ) $status = ChairUmpire::BYE;
        if( $match->isWaiting() ) $status = ChairUmpire::WAITING;

        if( empty( $status ) ) {
            $status = self::NOTSTARTED;
            extract( $this->getWinnerBasedOnScore( $match ) );

            if( $setInProgress > 0 ) $status = ChairUmpire::INPROGRESS;

            if( !empty( $andTheWinnerIs ) ) {
                $status = ChairUmpire::COMPLETED;
            }
            
            if( $earlyEnd > 0 ) {
                $who = 1 === $earlyEnd ? Match::HOME : Match::VISITOR;
                $status = sprintf("%s %s:%s", ChairUmpire::EARLYEND, $who, $comments );
            }
        }

        $this->log->error_log( sprintf( "%s(%s) is returning status=%s", $loc, $match->toString(), $status ) );

        return $status;
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
            
        extract( $this->getWinnerBasedOnScore( $match ) );

        if($earlyEnd === 0 && ( 0 < $setInProgress && $setInProgress <= $this->MaxSets ) ) {
            $this->log->error_log("$loc($title): set number {$setInProgress} is in progress");
            //TODO: Should I set all scores in sets > in progress to zero?
            //return $andTheWinnerIs; //early return s/b null
        }
        
        return $andTheWinnerIs;
    }

    /**
     * Find the winner based on the score. Also detects early end due to defaults.
     * @param Match $match 
     * @return array Array containing winner, set in progress and early end flag
     */
    private function getWinnerBasedOnScore( $match ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        $title = $match->toString();
        $this->log->error_log("$loc($title)");
        
        //NOTE: It is imperative that sets be in ascending order of set number
        $sets = $match->getSets();
        $numSets = count( $sets );
        $this->log->error_log("$loc($title) has $numSets sets");

        $home = $match->getHomeEntrant();
        $homeSetsWon = 0;
        $visitor = $match->getVisitorEntrant();
        $visitorSetsWon = 0;

        $andTheWinnerIs = null;
        $earlyEnd = 0;
        $setInProgress = 0;
        $cmts = '';

        foreach( $sets as $set ) {
            $this->log->error_log("$loc($title): set number={$set->getSetNumber()}");
            $earlyEnd = $set->earlyEnd();
            if( 1 === $earlyEnd ) {
                //Home defaulted
                $andTheWinnerIs = $visitor;
                $cmts = $set->getComments();
                break;
            }
            elseif( 2 === $earlyEnd ) {
                //Visitor defaulted
                $andTheWinnerIs = $home;
                $cmts = $set->getComments();
                break;
            }
            else {
                $homeW = $set->getHomeWins();
                $homeTB = $set->getHomeTieBreaker();
                $visitorW = $set->getVisitorWins();
                $visitorTB = $set->getVisitorTieBreaker();

                $this->log->error_log( sprintf( "%s(%s): home W=%d, home TB=%d, visitor W=%d, visitor TB=%d"
                                        , $loc, $set->toString(), $homeW, $homeTB, $visitorW, $visitorTB ) );
                
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
                        $this->log->error_log("$loc($title): set number {$set->getSetNumber()} not finished tie breaker yet");
                        break;
                    }
                }
            }
        } //foreach

        if( !$earlyEnd && $setInProgress === 0 ) {
            //Did not end early but there is no set in progress ... so must be done playing
            //Best 3 of 5 or 2 of 3
            if( $homeSetsWon >= ceil( $this->MaxSets/2.0 ) ) {
                    $andTheWinnerIs = $home;
            }
            elseif( $visitorSetsWon >= ceil( $this->MaxSets/2.0 ) ) {
                $andTheWinnerIs = $visitor;
            }
        }
        
        $winnerName = empty( $andTheWinnerIs) ? 'unknown' : $andTheWinnerIs->getName();
        $this->log->error_log("$loc($title): The winner is '{$winnerName}' with sets won: home={$homeSetsWon} and visitor={$visitorSetsWon}");

        return ["andTheWinnerIs"=>$andTheWinnerIs, "setInProgress"=>$setInProgress, "earlyEnd"=>$earlyEnd, "comments"=>$cmts];
    }

    /**
     * Return the score by set of the given Match
     * @param $match
     * @return array of scores
     */
	public function getScores( Match &$match ) {
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
            if( $this->winnerIsVisitor( $match ) ) {
                $scores[$setnum] = array( $set->getVisitorWins(), $set->getHomeWins(), $set->getVisitorTieBreaker(), $set->getHomeTieBreaker() );
            }
            else {
                $scores[$setnum] = array( $set->getHomeWins(), $set->getVisitorWins(), $set->getHomeTieBreaker(), $set->getVisitorTieBreaker() );
            }
        }
        return $scores;
    }

    /**
     * Determines if the visitor entrant was winner
     * @return True if visitor won false otherwise
     */
    public function winnerIsVisitor(  Match &$match ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;

        $visitor = $match->getVisitorEntrant();
        if( is_null( $visitor ) ) return false;

        $vname = $visitor->getName();
        $winner = $this->matchWinner( $match );
        $wname = is_null( $winner ) ? 'no winner yet' : $winner->getName();
        $this->log->error_log("$loc: visitor name=$vname; winner name=$wname");
        return ($vname === $wname);
    }

    /**
     * Determines if the home entrant was winner
     * @return True if home won false otherwise
     */
    public function winnerIsHome(  Match &$match ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;

        $home = $match->getHomeEntrant();
        if( is_null( $home ) ) return false;

        $hname = $home->getName();
        $winner = $this->matchWinner( $match );
        $wname = is_null( $winner ) ? 'no winner yet' : $winner->getName();
        $this->log->error_log("$loc: home name=$hname; winner name=$wname");
        return ($hname === $wname);
    }
    
    /**
     * Return the score by set of the given Match as a string
     * @param $match
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

    public function tableModifyScores( Match &$match ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;

        $mess = sprintf( "%s(%s) called", $loc, $match->toString() );
        $this->log->error_log( $mess );

        $arrScores = $this->getScores( $match );
        $setNums = range( 1, $this->getMaxSets() );

        //Start the table and place the header row
        $tableScores = '<table class="modifymatchscores tennis-modify-scores">';
        $tableScores .= '<thead class="modifymatchscores"><tr>';
        foreach( $setNums as $setNum ) {
            $tableScores .= "<th colspan='2'>$setNum</th>";
        }
        $tableScores .= "</tr><tr>";        
        foreach( $setNums as $setNum ) {
            $tableScores .= "<th>Games</th><th>TB</th>";
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
                                    , 1, $this->GamesPerSet + 1 );
            $homeScores .= sprintf("<td><input class='modifymatchscores' type='number' name='homeTieBreak' value='%d'></td>"
                                      , $scores[2]);
            
            $visitorScores .= sprintf("<td><input type='number' class='modifymatchscores' name='visitorGames' value='%d' min='%d' max='%d'></td>"
                                    , $scores[1] 
                                    , 1, $this->GamesPerSet + 1 );                   
            $visitorScores .= sprintf("<td><input class='modifymatchscores' type='number' name='visitorTieBreak' value='%d'></td>"
                                      , $scores[3]);
        }
        $homeScores .= "</tr>";
        $visitorScores .= "</tr>";
        $tableScores .= $homeScores;
        $tableScores .= $visitorScores;
        $tableScores .= "</tbody></table>";

        return $tableScores;

    }

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
     * A match is locked if it has been completed or if there was a default/early retirement
     * @return true or false
     */
    public function isLocked( Match $match ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;

        $locked = false;
        $status = $this->matchStatus( $match );
        if($status === ChairUmpire::COMPLETED || ( strpos( $status, ChairUmpire::EARLYEND ) !== false ) ) {
            $locked = true;
        }

        return $locked;
    }

} //end of class