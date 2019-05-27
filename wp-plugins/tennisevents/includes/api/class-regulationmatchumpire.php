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
        parent::__construct();

	}
    
    /**
     * Set the maximum nuber of sets in this tournament
     * @param $max the maximum
     * @return true if successful, false otherwise
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
     * @param $match The match whose score are recorded
     * @param $setnum The set number 
     * @param ...$scores if 2 args then game scores; if 4 then games and tiebreaker scores
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
            case 2:
                $homewins    = $scores[0];
                $visitorwins = $scores[1];
                $maxGames = $this->GamesPerSet + 1;
                if($homewins >= $maxGames ) {
                    $homewins = $maxGames;
                    $visitorwins = $this->GamesPerSet - 1;
                }
                elseif( $visitorwins >= $maxGames ) {
                    $visitorwins = $maxGames;
                    $homewins = $this->GamesPerSet - 1;
                }
                $match->setScore( $setnum, $homewins, $visitorwins );
                $this->log->error_log( sprintf( "%s -> Set home games=%d and visitor games=%d for %s."
                                  , $loc, $homewins, $visitorwins, $match->title()  ) );
                break;
            case 4:
                $homewins    = min( $scores[0], $this->GamesPerSet );
                $home_tb_pts = $scores[1];
                $visitorwins = min( $scores[2], $this->GamesPerSet );
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
        $sets = $match->getSets();
        $size = count( $sets );
        if( $size > 0 ) {
            $sets[$size - 1]->setEarlyEnd( 1 );
            $sets[$size - 1]->setComments( $cmts );
            $match->setDirty();
        }
        $result = $match->save();
        return $result > 0;
    }	

    /**
     * Default the visitor player/team for this Match
     * @param $match The match being played
     * @param $cmts  Any comments explaining the default.
     */
    public function defaultVisitor( Match &$match, string $cmts ) {
        $sets = $match->getSets();
        $size = count( $sets );
        if( $size > 0 ) {
            $sets[$size - 1]->setEarlyEnd( 2 );
            $sets[$size - 1]->setComments( $cmts );
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

        if( strlen( $status ) === 0 ) {
            $sets = $match->getSets();
            $status = self::NOTSTARTED;
            foreach( $sets as $set ) {
                $setnum = $set->getSetNumber();
                $status = self::INPROGRESS;
                if( $set->earlyEnd() > 0 ) {
                    $cmts = $set->getComments();
                    $who = 1 === $set->earlyEnd() ? "home" : "visitor";
                    $status = sprintf("%s %s:%s", self::EARLYEND, $who, $cmts );
                    break;
                }                
            }
            if( $status === self::INPROGRESS && !is_null( $this->matchWinner( $match ) ) ) {
                $status = ChairUmpire::COMPLETED;
            }
        }

        $this->log->error_log( sprintf( "%s(%s) is returning status=%s", $loc, $match->toString(), $status ) );

        return $status;
    }

    /**
     * Determine the winner of the given Match
     * @param $match
     * @return Entrant who won or null if not completed yet
     */
    public function matchWinner( Match &$match ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;

        $andTheWinnerIs = null;
        $home = $match->getHomeEntrant();
        $homeSetsWon = 0;
        $visitor = $match->getVisitorEntrant();
        $visitorSetsWon = 0;
        $finished = false;

        if( $match->isBye() ) {
            $andTheWinnerIs = $home;
        }
        elseif( !$match->isWaiting() ) {
            $sets = $match->getSets();
            foreach( $sets as $set ) {
                if( 1 === $set->earlyEnd() ) {
                    //Home defaulted
                    $andTheWinnerIs = $home->getName();
                    $finished = true;
                    break;
                }
                elseif( 2 === $set->earlyEnd() ) {
                    //Visitor defaulted
                    $andTheWinnerIs = $visitor->getName();
                    $finished = true;
                    break;
                }
                else {
                    $homeW = $set->getHomeWins();
                    $homeTB = $set->getHomeTieBreaker();
                    $visitorW = $set->getVisitorWins();
                    $visitorTB = $set->getVisitorTieBreaker();

                    $this->log->error_log( sprintf( "%s ->In %s: home W=%d, home TB=%d, visitor W=%d, visitor TB=%d"
                                         , $loc, $set->toString(), $homeW, $homeTB, $visitorW, $visitorTB ) );
                    
                    if( !in_array($homeW, array($this->GamesPerSet, $this->GamesPerSet + 1))
                    &&  !in_array($visitorW, array($this->GamesPerSet, $this->GamesPerSet + 1) )) {
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
                            break;
                        }
                    }
                }
            } //foreach
        } //Not is waiting

        //Best 3 of 5 or 2 of 3
        if( $homeSetsWon >= ceil( $this->MaxSets/2.0 ) ) {
                $andTheWinnerIs = $home;
        }
        elseif( $visitorSetsWon >= ceil( $this->MaxSets/2.0 ) ) {
            $andTheWinnerIs = $visitor;
        }
        
        return $andTheWinnerIs;
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
            // $mess = sprintf("%s(%s) -> Home=%d Visitor=%d HomeTB=%d VisitorTB=%d"
            //                , $loc, $set->toString()
            //                , $set->getHomeWins(), $set->getVisitorWins(), $set->getHomeTieBreaker(), $set->getVisitorTieBreaker() );
            // $this->log->error_log( $mess );
            $scores[$setnum] = array( $set->getHomeWins(), $set->getVisitorWins(), $set->getHomeTieBreaker(), $set->getVisitorTieBreaker() );
        }
        return $scores;
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
        if( count( $arrScores) === 0 ) return '...';

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

    public function tableGetScores( Match &$match ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;

        $mess = sprintf( "%s(%s) called", $loc, $match->toString() );
        $this->log->error_log( $mess );

        $scoreClass = "tennistablescores";
        $arrScores = $this->getScores( $match );
        if( count( $arrScores) === 0 ) return '<table class="' . $scoreClass . '"></table>';

        $tableScores = '<table class="' . $scoreClass . '"><tbody>';
        $homeScores  = "<tr>";
        $visitorScores = "<tr>";
        $setNums = range( 1, $this->getMaxSets() );
        foreach( $setNums as $setNum ) {
            if( $setNum === $this->MaxSets ) $sep = '';
            if( array_key_exists( $setNum, $arrScores ) ) {
                $scores = $arrScores[ $setNum ];
                // $mess = sprintf("%s(%s) -> Set=%d Home=%d Visitor=%d HomeTB=%d VisitorTB=%d"
                //                 , $loc, $match->toString(), $setNum
                //                 , $scores[0], $scores[1], $scores[2], $scores[3] );
                if( $scores[0] === $scores[1] && $scores[0] === $this->GamesPerSet ) {
                    $homeScores .= sprintf("<td>%d<sub>%d</sup></td>", $scores[0], $scores[2]);
                    $visitorScores .= sprintf("<td>%d<sub>%d</sup></td>", $scores[1], $scores[3]);
                } 
                else {
                    $homeScores .= sprintf("<td>%d</td>", $scores[0]);
                    $visitorScores .= sprintf("<td>%d</td>", $scores[1]);
                }
            }
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
        if($status === ChairUmpire::COMPLETED || ( strpos( ChairUmpire::EARLYEND, $status ) !== false ) ) {
            $locked = true;
        }

        return $locked;
    }

} //end of class