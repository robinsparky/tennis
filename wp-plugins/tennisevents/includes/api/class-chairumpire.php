<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$p2Dir = plugin_dir_path( plugin_dir_path( __DIR__ ) );
require_once( $p2Dir . 'tennisevents.php' );
require_once( 'api-exceptions.php' );

/** 
 * ChairUmpire interprets the scores for matches, determines match winners
 * and bracket champions as well as determing if a match is complete or not.
 * This interface also supports defaulting a match.
 * All incarnations of umpire must inherit from this abstract class
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
*/
abstract class ChairUmpire
{
	public const INPROGRESS = "In progress";
	public const NOTSTARTED = "Not started";
	public const COMPLETED  = "Completed";
	public const EARLYEND   = "Retired";
	public const BYE        = "Bye";
	public const WAITING    = "Waiting";
    public const CANCELLED  = "Cancelled";
	
    //General Tennis Scoring Rules
    protected $MaxSets = 3;
    protected $GamesPerSet = 6;
    protected $TieBreakerMinimum = 7;
    protected $TieBreakDecider = false;
    protected $NoTieBreakerFinalSet = false;

    //Mask of scoring rules
    protected $scoreTypeMask = 0;
	
	protected $log;

	abstract public function recordScores(Match &$match, int $set, int ...$scores );
	abstract public function getScores( Match &$match );
    abstract public function matchWinner( Match &$match );
    abstract public function getMatchSummary( Match &$match );
    abstract public function getChampion( Bracket &$bracket );
	// abstract public function matchStatus( Match &$match );
	// abstract public function defaultHome( Match &$match, string $cmts );
	// abstract public function defaultVisitor( Match &$match, string $cmts );
    
    /**
     * Return a ChairUmpire based on the type of event
     * But only leaf events can have a ChairUmpire
     * @param string $scoretype which is a title of a score type such as 'Fast4' or 'Regulation'
     * @return object ChairUmpire ... one of several different possibilities
     */
    public static function getUmpire( string $strScoreType ) : ChairUmpire {

        $chairUmpire = null;

        $scoretype = ScoreType::get_instance()->getScoreTypeMask( $strScoreType );

        if( ($scoretype & ScoreType::NoAd) && ($scoretype & ScoreType::TieBreakAt4All) ) {
            //Fast4
            $chairUmpire = Fast4Umpire::getInstance();
        }
        elseif( $scoretype & ScoreType::TieBreakDecider ) {
            //Match Tie Break (instead of 3rd set)
            $chairUmpire = MatchTieBreakUmpire::getInstance();
        }
        elseif( $scoretype & ScoreType::OneSet ) {
            //Pro Set
            $chairUmpire = ProSetUmpire::getInstance();
        }
        else {
            //Regulation
            $chairUmpire = RegulationMatchUmpire::getInstance();
        }
        $chairUmpire->setScoreTypeMask( $scoretype );

        return $chairUmpire;
    }
    
    /**
     * Given the size of the draw (or any integer), calculate the highest 
     * power of 2 which is greater than or equal to that size (or integer)
     * @param int $size 
     * @param int $upper The upper limit of the search; default is 8
     * @return int The exponent if found; zero otherwise
     * @see TournamentDirector's version of this
     */
	public static function calculateExponent( int $size, $upper = 8 ) {
        $exponent = 0;
        foreach( range( 1, $upper ) as $exp ) {
            if( pow( 2, $exp ) >= $size ) {
                $exponent = $exp;
                break;
            }
        }
        return $exponent;
    }

    /**
     * Ctor
     */
	public function __construct( $logger = false ) {
		$this->log = new BaseLogger( $logger );
    }
    
    /**
     * Set the score type mask and apply it to parse out match parameters
     */
    public function setScoreTypeMask( int $mask ) {
        $this->scoreTypeMask = $mask;
        $this->applyMask();
    }

    /**
     * Get the score type mask
     */
    public function getScoreTypeMask() : int {
        return $this->scoreTypeMask;
    }

    /**
     * Get the name of the home entrant
     */
	public function getHomePlayer( Match &$match ):string {
		if( isset( $match ) ) {
			return $match->getHomeEntrant()->getName();
		}
		else return '';
	}
    
    /**
     * Get the name of the visitor entrant
     */
	public function getVisitorPlayer( Match &$match ):string {
		if( isset( $match ) ) {
			return $match->getVisitorEntrant()->getName();
		}
		else return '';
	}

    /**
     * Get the maximum number of sets in a match
     */
	public function getMaxSets() {
		return $this->MaxSets;
    }
    
    /**
     * Get the number of games in a set
     */
	public function getGamesPerSet() {
		return $this->GamesPerSet;
    }
    
    /**
     * Get the minimum tie breaker score
     */
    public function getTieBreakMinScore() {
        return $this->TieBreakerMinimum;
    }

    /**
     * Is the final set a tie break decider?
     */
    public function getTieBreakDecider() {
        return $this->TieBreakDecider;
    }

    /**
     * Is a tie break used to settle the final set? 
     * i.e. Does the winner have to win the final set by 2 games.
     */
    public function getNoTieBreakerFinalSet() {
        return $this->NoTieBreakerFinalSet;
    }
	
    /**
     * Default the home player/team for this Match
     * @param object Match $match The match being played
     * @param string $cmts  Any comments explaining the default.
     * @return true if successful, false otherwise
     */
	public function defaultHome( Match &$match, string $cmts ) {
        return $this->defaultEntrant( $match, Match::HOME, $cmts );
    }	

    /**
     * Default the visitor player/team for this Match
     * @param object Match $match The match being played
     * @param string $cmts  Any comments explaining the default.
     */
    public function defaultVisitor( Match &$match, string $cmts ) {
        return $this->defaultEntrant( $match, Match::VISITOR, $cmts );
    }
    
    /**
     * Default the entrant (player/team) for this Match
     * @param object Match $match The match being played
     * @param string @entrantType either 'visitor' or 'home'
     * @param string $cmts  Any comments explaining the default.
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
     * @param object Match $match Match whose status is calculated
     * @return string Status of the given match
     */
	public function matchStatus( Match &$match ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;

        $status = '';
        if( $match->isBye() ) $status = ChairUmpire::BYE;
        if( $match->isWaiting() ) $status = ChairUmpire::WAITING;

        if( empty( $status ) ) {
            $status = ChairUmpire::NOTSTARTED;
            extract( $this->getMatchSummary( $match ) );

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
     * Get status of the Match
     * @param object Match $match Match whose status is calculated
     * @return object MatchStatus of the given match
     */
	public function matchStatusEx( Match &$match ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;

        $status = new MatchStatus();
        if( $match->isBye() ) $status->setMajor(MatchStatus::Bye);
        if( $match->isWaiting() ) $status->setMajor(MatchStatus::Waiting);

        if( !$status->isSet() ) {
            $status->setMajor(MatchStatus::NotStarted);
            extract( $this->getMatchSummary( $match ) );

            if( $setInProgress > 0 ) $status->setMajor(MatchStatus::InProgress);

            if( !empty( $andTheWinnerIs ) ) {
                $status->setMajor(MatchStatus::Completed);
            }
            
            if( $earlyEnd > 0 ) {
                $status->setMajor(MatchStatus::Retired);
                $status->setExplanation($comments);
            }
        }

        $this->log->error_log(sprintf("%s(%s) is returning status=%s", $loc, $match->toString(), $status->toString()));

        return $status;
    }
    
    /**
     * Determines if the visitor entrant was winner
     * @return bool True if visitor won false otherwise
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
     * @return bool True if home won false otherwise
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
     * A match is locked if it has a winner
     * @return bool true if locked, false otherwise
     */
    public function isLocked( Match $match, Entrant &$entrant = null ) {
        $entrant = $this->matchWinner( $match );
        return is_null( $entrant ) ? false : true; 
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
     * Apply the rules mask to set up match parameters.
     */
    protected function applyMask() {
        //Number of sets
        if( $this->scoreTypeMask & ScoreType::OneSet ) {
            $this->MaxSets = 1;
        }
        elseif($this->scoreTypeMask & ScoreType::Best2Of3 ) {
            $this->MaxSets = 3;
        }
        elseif($this->scoreTypeMask & ScoreType::Best3Of5 ) {
            $this->MaxSets = 5;
        }

        //Number of games per set
        if( $this->scoreTypeMask & ScoreType::TieBreakAt3All ) {
            $this->GamesPerSet = 4; //Fast 4
        }
        elseif( $this->scoreTypeMask & ScoreType::TieBreakAt6All ) {
            $this->GamesPerSet = 6;
        }
        elseif( $this->scoreTypeMask & ScoreType::TieBreakAt8All ) {
            $this->GamesPerSet = 8;
        }
        elseif( $this->scoreTypeMask & ScoreType::TieBreakAt10All ) {
            $this->GamesPerSet = 10;
        }

        //Tie breaker score
        if( $this->scoreTypeMask & ScoreType::TieBreak7Pt ) {
            $this->TieBreakerMinimum = 7;
        }
        elseif( $this->scoreTypeMask & ScoreType::TieBreak10Pt ) {
            $this->TieBreakerMinimum = 10;
        }
        elseif( $this->scoreTypeMask & ScoreType::TieBreak12Pt ) {
            $this->TieBreakerMinimum = 12;
        }

        //No tie breaker in the final set .. must win by at least 2 games
        if( $this->scoreTypeMask & ScoreType::NoTieBreakFinalSet ) {
            $this->NoTieBreakerFinalSet = true;
        }

        //Final set is a tie breaker
        if( $this->scoreTypeMask & ScoreType::TieBreakDecider ) {
            $this->TieBreakDecider = true;
        }
    }
    
}