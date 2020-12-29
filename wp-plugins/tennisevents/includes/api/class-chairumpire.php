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
    //Match Statuses
	public const INPROGRESS = "In progress";
	public const NOTSTARTED = "Not started";
	public const COMPLETED  = "Completed";
	public const EARLYEND   = "Retired";
	public const BYE        = "Bye";
	public const WAITING    = "Waiting";
    public const CANCELLED  = "Cancelled";
	
    //General Tennis Scoring parameters
    protected $Scoring_Rule = '';
    protected $MaxSets = 3;
    protected $GamesPerSet = 6;
    protected $TieBreakAt  = 6; //Can be less than GamesPerSet e.g. Fast4
    protected $TieBreakerMinimum = 7;
    protected $TieBreakDecider = false;
    protected $NoTieBreakerFinalSet = false;
    protected $MustWinBy = 2;
	
	protected $log;

	//abstract public function recordScores(Match &$match, int $set, int ...$scores );
	//abstract public function getScores( Match &$match );
    abstract public function matchWinner( Match &$match );
    abstract public function getMatchSummary( Match &$match );
    abstract public function getChampion( Bracket &$bracket );

	// abstract public function matchStatus( Match &$match );
	// abstract public function defaultHome( Match &$match, string $cmts );
	// abstract public function defaultVisitor( Match &$match, string $cmts );
    
    /**
     * Return a ChairUmpire based on the type of event
     * But only leaf events can have a ChairUmpire
     * @param string $scoretype which is a title of a set of scoring rules such as 'Fast4' or 'Regulation'
     * @return object ChairUmpire ... one of several different possibilities
     */
    public static function getUmpire( string $strScoreType ) : ChairUmpire {
        $loc = __CLASS__ . ':: static ' . __FUNCTION__;
        error_log("{$loc}('{$strScoreType}')");

        $chairUmpire = null;

        switch( $strScoreType ) {
            case ScoreType::PRO_SET8:
            case ScoreType::PRO_SET10:
                //Pro Set
                $chairUmpire = RegulationMatchUmpire::getInstance();
                break;
            case ScoreType::FAST4: 
                //Fast4
                $chairUmpire = Fast4Umpire::getInstance();
                break;
            case ScoreType::POINTS1:
            case ScoreType::POINTS2:
                //Points
                $chairUmpire = PointsMatchUmpire::getInstance();
                break;
            case ScoreType::REGULATION:
            case ScoreType::ATPMAJOR:
            case ScoreType::MATCH_TIE_BREAK:
            default:
            //Regulation
            $chairUmpire = RegulationMatchUmpire::getInstance();
        }

        //Initialize the umpire with the scoring rules
        $chairUmpire->setScoringRules( $strScoreType );

        error_log("$loc finished!");
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
     * Get the string identifier of the scoring rules for this umpire
     */
    public function getScoringRules() {
        return $this->Scoring_Rule;
    }

    /**
     * Extract the scoring rules into object properties
     * @param string $score_rules Identifyng (i.e. key to) score rules from ScoreTypes.
     */
    public function setScoringRules( string $score_rules) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("{$loc}('{$score_rules}')");

        $this->Scoring_Rule = $score_rules;
        $rules = ScoreType::get_instance()->getScoringRules( $score_rules );
        $this->log->error_log($rules,"$loc: rules...");

        $numVars = extract( $rules );
        $this->MustWinBy = $MustWinBy ?? 2;
        $this->MaxSets   = $MaxSets ?? 3;
        $this->GamesPerSet = $GamesPerSet ?? 6;
        $this->TieBreakAt = $TieBreakAt ?? 6;
        $this->TieBreakerMinimum = $TieBreakerMinimum ?? 7;
        $this->TieBreakDecider = $TieBreakerDecider ?? false;
        $this->NoTieBreakerFinalSet = $NoTieBreakerFinalSet ?? false;

        if( $this->TieBreakAt > $this->GamesPerSet ) $this->TieBreakAt = $this->GamesPerSet;
        if( !in_array($this->MustWinBy, array(1,2) ) ) $this->MustWinBy = 2;
        if( $this->MustWinBy === 1 ) $this->NoTieBreakerFinalSet = true;

        // if( $this->NoTieBreakerFinalSet ) $this->TieBreakDecider = false;
        // if( $this->TieBreakDecider ) $this->NoTieBreakerFinalSet = false;

        $this->PointsPerWin = $PointsPerWin ?? 1;
        
        $this->log->error_log($this, "{$loc}: initialized this ...");
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
     * Record game and tie breaker scores for a given set pf the supplied Match.
     * @param Match $match The match whose score are recorded
     * @param int $setnum The set number 
     * @param int ...$scores if 2 args then game scores; if 4 then games and tiebreaker scores
     */
	public function recordScores( Match &$match, int $setnum, int ...$scores ) {
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
	public function getMaxSets() :int {
		return $this->MaxSets;
    }
    
    /**
     * Get the number of games in a set
     */
	public function getGamesPerSet() :int {
		return $this->GamesPerSet;
    }
    
    /**
     * Get the minimum tie breaker score
     */
    public function getTieBreakMinScore() :int {
        return $this->TieBreakerMinimum;
    }

    /**
     * Is the final set a tie break decider?
     */
    public function getTieBreakDecider() :bool {
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
     * For NoAd matches this value should be 1
     * otherwise it defaults to 2
     */
    public function getMustWinBy() {
        return $this->MustWinBy;
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

        $wname = 'no winner yet';
        if( is_a( $winner, 'Entrant' ) ) {
            $wname = $winner->getName();
        }
        elseif( is_string( $winner ) ) {
            $wname = $winner;
        }
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
        
        $wname = 'no winner yet';
        if( is_a( $winner, 'Entrant' ) ) {
            $wname = $winner->getName();
        }
        elseif( is_string( $winner ) ) {
            $wname = $winner;
        }
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
            $tableScores .= "<th>{$gameHdr}</th>";
            if( $this->getMaxSets() === $setNum && $this->getNoTieBreakerFinalSet() ) {
                $tableScores .= "";
            }
            else{
                $tableScores .= "<th>{$tbHdr}</th>";
            }
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
                                    
            if( $this->getMaxSets() === $setNum && $this->getNoTieBreakerFinalSet() ) {
                $homeScores .= "";
            }
            else {                
                $homeScores .= sprintf("<td><input class='modifymatchscores' type='number' name='homeTieBreak' value='%d' min='0' max='7'></td>"
                , $scores[2]);
            }
            
            $visitorScores .= sprintf("<td><input type='number' class='modifymatchscores' name='visitorGames' value='%d' min='%d' max='%d'></td>"
                                    , $scores[1] 
                                    , 0, $this->GamesPerSet + 1 );
                                    
            if( $this->getMaxSets() === $setNum && $this->getNoTieBreakerFinalSet() ) {
                $visitorScores .= "";
            }
            else {
                $visitorScores .= sprintf("<td><input class='modifymatchscores' type='number' name='visitorTieBreak' value='%d' min='0' max='7'></td>"
                , $scores[3]);
            }
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
        $calledBy = debug_backtrace()[1]['function'];
        $mess = sprintf( "%s(%s) called by: ", $loc, $match->toString(), $calledBy );
        $this->log->error_log( $mess );

        $scoreClass = "tennis-display-scores";
        $arrScores = $this->getScores( $match );
        $this->log->error_log("{$loc}: max sets={$this->getMaxSets()}");
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
                if( $setNum === $this->getMaxSets() ) {
                    $homeTBScores = "";
                    $visitorTBScores = '';
                }
                else {
                    $homeTBScores = sprintf("<sup>%d</sup>", $scores[2]);
                    $visitorTBScores = sprintf("<sup>%d</sup>", $scores[3]);
                }
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
       
}