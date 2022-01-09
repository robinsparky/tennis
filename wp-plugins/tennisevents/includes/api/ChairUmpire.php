<?php
namespace api;

use \TennisEvents;
use commonlib\GW_Debug;
use commonlib\GW_Support;
use commonlib\BaseLogger;
use datalayer\ScoreType;
use datalayer\Format;
use datalayer\Event;
use datalayer\Match;
use datalayer\Bracket;
use datalayer\MatchStatus;
use datalayer\Entrant;
use datalayer\Set;
use datalayer\InvalidEventException;
use datalayer\InvalidBracketException;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// $p2Dir = plugin_dir_path( plugin_dir_path( __DIR__ ) );
// require_once( $p2Dir . 'tennisevents.php' );
// require_once( 'api-exceptions.php' );

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
    protected $Scoring_Rules = '';
    protected $MaxSets = 3;
    protected $GamesPerSet = 6;
    protected $TieBreakAt  = 6; //Can be less than GamesPerSet e.g. Fast4
    protected $TieBreakerMinimum = 7;
    protected $TieBreakDecider = false;
    protected $NoTieBreakerFinalSet = false;
    protected $MustWinBy = 2;
    protected $PointsPerWin = 1;
	
	protected $log;

    abstract public function matchWinner( Match &$match );
    abstract public function getMatchSummary( Match &$match, $force = false );
    abstract public function getChampion( Bracket &$bracket );
    
    /**
     * Return a ChairUmpire based on the type of event
     * But only leaf events can have a ChairUmpire
     * @param string $scoretype which is a title of a set of scoring rules such as 'Fast4' or 'Regulation'
     * @return ChairUmpire ... one of several different possibilities
     */
    public static function getUmpire( string $strScoreType ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        // $trace = GW_Debug::get_debug_trace( 2 );
        // error_log("{$loc}('{$strScoreType}')");
        // error_log(print_r($trace, true ));
        //error_log(debug_backtrace()[1]['function']);

        $chairUmpire = null;

        switch( $strScoreType ) {
            case ScoreType::BEST2OF3:
            case ScoreType::BEST3OF5:
            case ScoreType::PROSET8:
            case ScoreType::PROSET10:
                //Regulation such as winning majority of sets with tie breaker for set deciders
                $chairUmpire = RegulationMatchUmpire::getInstance();
                break;
            case ScoreType::BEST2OF3TB:
                //Pro Set & match tie breakers
                $chairUmpire = MatchTieBreakerUmpire::getInstance();
                break;
            case ScoreType::FAST4: 
                //Fast4 or NoAd scoring
                $chairUmpire = NoAdUmpire::getInstance();                
                break;
            case ScoreType::POINTS1:
            case ScoreType::POINTS2:
            case ScoreType::POINTS3:
                //Points such as Round Robins where games are played up to a time limit
                $chairUmpire = PointsMatchUmpire::getInstance();
                break;
            default:
                $mess = __( 'Invalid Score Type: ', TennisEvents::TEXT_DOMAIN ) . $strScoreType;
                throw new InvalidEventException( $mess );
        }

        //Initialize the umpire with the scoring rules
        $chairUmpire->setScoringRules( $strScoreType );

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
        return $this->Scoring_Rules;
    }

    /**
     * Extract the scoring rules into object properties
     * @param string $score_rules Identifies (i.e. key to) score rules from ScoreTypes.
     */
    public function setScoringRules( string $score_rules) {
        if( !empty( $this->Scoring_Rules ) ) return; //can only be set once!
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("{$loc}('{$score_rules}')");


        $this->Scoring_Rules = $score_rules;
        $rules = ScoreType::get_instance()->getScoringRules( $score_rules );
        //$this->log->error_log($rules,"$loc: rules...");

        $numVars = extract( $rules );
        $this->MustWinBy = $MustWinBy ?? 2;
        $this->MaxSets   = $MaxSets ?? 3;
        $this->GamesPerSet = $GamesPerSet ?? 6;
        $this->TieBreakAt = $TieBreakAt ?? 0; //no tie breakers by default
        $this->TieBreakerMinimum = $TieBreakerMinimum ?? 7;
        $this->TieBreakDecider = $TieBreakDecider ?? false;
        $this->NoTieBreakerFinalSet = $NoTieBreakerFinalSet ?? false;
        if( $this->TieBreakDecider ) $this->NoTieBreakerFinalSet = false;

        if( $this->TieBreakAt > $this->GamesPerSet ) $this->TieBreakAt = $this->GamesPerSet;
        if( !in_array($this->MustWinBy, array(1,2) ) ) $this->MustWinBy = 2;
        //if( $this->MustWinBy === 1 ) $this->NoTieBreakerFinalSet = true;
        
        $this->log->error_log($this, "{$loc}: initialized this ...");
    }

    /**
     * Return the score by set of the given Match
     * @param object Match $match
     * @param bool $winnerFirst return winner's scores first is set to true
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
     * Manupliate (massage) game, tie breaker scores beforing saving them.
     * @param Match $match The match whose score are recorded
     * @param array $score dictionary of game scores and tb scores for home and visitor; also contains the associated set number
     */
    public function recordScores( Match &$match, array $score ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;

        $calledBy = isset(debug_backtrace()[1]['class']) ? debug_backtrace()[1]['class'] . '::'. debug_backtrace()[1]['function'] : debug_backtrace()[1]['function'];
        
        $title = $match->title();
        $this->log->error_log( $score, "$loc: called with match=$title, this set number and scores: ");
        $this->log->error_log( "Called by {$calledBy}");
        
        $setnum = (int)$score['setNum'];
        if( $setnum < 1 || $setnum > $this->getMaxSets() ) return;

        if( $match->isBye() || $match->isWaiting() ) {
            $this->log->error_log( sprintf( "%s -> Cannot save  scores because '%s' has bye or is watiing.", $loc,$match->title() ) );
            return; //throw new ChairUmpireException( sprintf("Cannot save scores because '%s' has bye or is wating.",$match->title() ) );
        }

        if( $this->isLocked( $match ) ) {
            $this->log->error_log( sprintf("%s -> Cannot save scores because match '%s' is locked", $loc, $match->title() ) );
            return; //throw new ChairUmpireException( sprintf("Cannot save scores because '%s' is locked.",$match->title() ) );
        }

        $homewins       = $score["homeGames"];
        $home_tb_pts    = $score["homeTieBreaker"];
        $visitorwins    = $score["visitorGames"];
        $visitor_tb_pts = $score["visitorTieBreaker"];

        if( !is_numeric( $homewins) ) $homewins = 0;
        if( !is_numeric( $home_tb_pts) ) $home_tb_pts = 0;
        if( !is_numeric( $visitorwins) ) $visitorwins = 0;
        if( !is_numeric( $visitor_tb_pts) ) $visitor_tb_pts = 0;

        $this->log->error_log("{$loc}: before allowable game score call: home={$homewins}, visitor={$visitorwins}");
        $this->getAllowableGameScore( $homewins, $visitorwins );
        $this->log->error_log("{$loc}: after allowable game score call: home={$homewins}, visitor={$visitorwins}");
        
        $this->log->error_log("{$loc}: before allowable tie break score call: home tb={$home_tb_pts}, visitor tb={$visitor_tb_pts}");
        $this->getAllowableTieBreakScore( $home_tb_pts, $visitor_tb_pts );
        $this->log->error_log("{$loc}: after allowable tie break score call: home tb={$home_tb_pts}, visitor tb={$visitor_tb_pts}");
        
        if( $setnum === $this->getMaxSets() && $this->getTieBreakDecider() ) {
            $this->log->error_log("$loc: '{$title}' Set {$setnum} is a tie break decider set.");
            $homewins = 0;
            $visitorwins = 0;
        }

        $this->saveScores( $match, $setnum, $homewins, $visitorwins, $home_tb_pts, $visitor_tb_pts );
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
     * At what tie score are tie breakers played
     * NOTE: value could be zero which means no tie breakers
     * @return int Tie score at which tie breaker is palyes
     * @see noTieBreakers function
     */
    public function getTieBreakAt(): int {
        return $this->TieBreakAt;
    }

    /**
     * Are tie breakers used?
     * @return bool true if no tie breakers, false otherwise
     */
    public function noTieBreakers(): bool {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("{$loc}: TieBreakAt={$this->TieBreakAt}");
        $result = false;
        if( $this->TieBreakAt < 1 ) $result = true;
 
        return $result;
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

            $this->log->error_log("$loc: setInProgress={$setInProgress}");
            if( (int)$setInProgress > 0 ) $status->setMajor(MatchStatus::InProgress);
            $this->log->error_log("$loc: Status={$status->toString()}");
            
            $rightNow = new \DateTime('now', TennisEvents::getTimeZone() );
            $startDate = $match->getMatchDateTime();
            if( !empty($startDate) ) {
                if( $startDate <= $rightNow ) {
                    $this->log->error_log("$loc: {$match->toString()} Date Comparison now: {$rightNow->format('Y-m-d H:i:sP')}");
                    $this->log->error_log("$loc: {$match->toString()} Date Comparison start date: {$startDate->format('Y-m-d H:i:sP')}");
                    $status->setMajor(MatchStatus::InProgress);
                    $this->log->error_log("$loc: Status={$status->toString()}");
                }
            }

            if( !empty( $andTheWinnerIs ) ) {
                $status->setMajor(MatchStatus::Completed);
            }
            
            if( $earlyEnd > 0 ) {
                $status->setMajor(MatchStatus::Retired);
                $status->setExplanation($comments);
            }
        }

        //$this->log->error_log(sprintf("%s(%s) is returning status=%s", $loc, $match->toString(), $status->toString()));

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
        if( is_a( $winner, 'datalayer\Entrant' ) ) {
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
        if( is_a( $winner, 'datalayer\Entrant' ) ) {
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
        $strScores = '';
        if( $match->isBye() ) return $strScores; //early return

        $arrScores = $this->getScores( $match );
        if( count( $arrScores) === 0 ) return 'vs';

        $sep = ',';
        $setNums = range( 1, $this->getMaxSets() );
        foreach( $setNums as $setNum ) {
            if( $setNum === $this->MaxSets ) $sep = '';
            if( array_key_exists( $setNum, $arrScores ) ) {
                $scores = $arrScores[ $setNum ];
                // $mess = sprintf("%s(%s) -> Set=%d Home=%d Visitor=%d HomeTB=%d VisitorTB=%d"
                //                 , $loc, $match->toString(), $setNum
                //                 , $scores[0], $scores[1], $scores[2], $scores[3] );
                if( ($scores[0] === $scores[1]) 
                && (($scores[0] === $this->GamesPerSet )
                ||  ($scores[0] === $this->getTieBreakAt()))) {
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

        $playerHdr = __("Players", TennisEvents::TEXT_DOMAIN);
        $gameHdr = __("Games",TennisEvents::TEXT_DOMAIN);
        $tbHdr   = __("T.B.", TennisEvents::TEXT_DOMAIN);
        //Start the table and place the header row
        $tableScores = '<table class="modifymatchscores tennis-modify-scores ui-sortable-handle">';
        $tableScores .= "<caption>{$match->toString()}</caption>";
        $tableScores .= '<thead class="modifymatchscores"><tr>';
        $tableScores .= "<th rowspan='2'>{$playerHdr}</th>";
        foreach( $setNums as $setNum ) {
            if( $this->includeTieBreakerScores( $setNum ) ) {
                $tableScores .= "<th colspan='2'>Set {$setNum}</th>";
            }
            else {
                $tableScores .= "<th>Set {$setNum}</th>";
            }
        }
        $tableScores .= "</tr><tr>";        
        foreach( $setNums as $setNum ) {
            $tableScores .= "<th>{$gameHdr}</th>";
            if( $this->includeTieBreakerScores( $setNum ) ) {
                $tableScores .= "<th class='tiebreakscorehdr'>{$tbHdr}</th>";
            }
            else{
                $tableScores .= "";
            }
        }
        $tableScores .= "</tr></thead><tbody>";

        //Now put the actual scores into the table
        $homePlayer    = empty($match->getHomeEntrant()) ? "" : $match->getHomeEntrant()->getName();
        $visitorPlayer = empty($match->getVisitorEntrant()) ? "" :$match->getVisitorEntrant()->getName();
        $homeScores  = "<tr><td>{$homePlayer}</td>";
        $visitorScores = "<tr><td>{$visitorPlayer}</td>";
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

            $maxGameScore = $this->getGamesPerSet() + 24;
            $maxTBScore   = $this->getTieBreakMinScore() + 21;
            $homeScores .= sprintf("<td><input type='number' class='modifymatchscores gamescore' name='homeGames' value='%d' min='0' max='%d'></td>"
                                    ,$scores[0] 
                                    ,$maxGameScore );
                                    
            if( $this->includeTieBreakerScores( $setNum ) ) {                
                $homeScores .= sprintf("<td><input class='modifymatchscores tiebreakscore' type='number' name='homeTieBreak' value='%d' min='0' max='%d'></td>"
                                        ,$scores[2]
                                        ,$maxTBScore );
            }
            else {
                $homeScores .= ""; //null op
            }
            
            $visitorScores .= sprintf("<td><input type='number' class='modifymatchscores gamescore' name='visitorGames' value='%d' min='0' max='%d'></td>"
                                    ,$scores[1] 
                                    ,$maxGameScore );
                                    
            if( $this->includeTieBreakerScores( $setNum ) ) {
                $visitorScores .= sprintf("<td><input class='modifymatchscores tiebreakscore' type='number' name='visitorTieBreak' value='%d' min='0' max='%d'></td>"
                                        ,$scores[3]
                                        ,$maxTBScore );
            }
            else {
                $visitorScores .= "";
            }
        } //end foreach

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
            if( ($scores[0] === $scores[1]) && (($scores[0] >= $this->GamesPerSet) || ($scores[0] >= $this->getTieBreakAt() ))
            ||  ($this->getMaxSets() === $setNum && $this->getTieBreakDecider()) ) {
                if( $this->includeTieBreakerScores( $setNum ) ) {
                    $homeTBScores = sprintf("<sup>%d</sup>", $scores[2]);
                    $visitorTBScores = sprintf("<sup>%d</sup>", $scores[3]);
                }
                else {
                    $homeTBScores = "";
                    $visitorTBScores = '';
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
       
    public function getPointsPerWin() {
        return $this->PointsPerWin ?? 0;
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
        $pointsForWin = $this->getPointsPerWin() ?? 1;
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
            for( $setNum = $cutoff + 1; $setNum <= $this->getMaxSets(); $setNum++ ) {
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
     * Rule for determining if tie break scores should be included
     * @param int $setNum the set number
     * @return bool true if include tie break scores or false otherwise
     */
    protected function includeTieBreakerScores( $setNum ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        
        $trace = GW_Debug::get_debug_trace_Str();
        $this->log->error_log("$loc: {$trace}");

        $result = True;

        if( ( $this->getMaxSets() === $setNum && $this->getNoTieBreakerFinalSet() ) 
            || $this->noTieBreakers() ) {
            $result = False;
            $this->log->error_log("{$loc}({$setNum}): yields false");
        }
        else {
            $this->log->error_log("{$loc}({$setNum}): yields true");
        }

        return $result;        
    }
    
    /**
     * Edits the game scores before saving them
     * @param int $homeScore The home entrant's game score
     * @param int $visitorScore the visitor entrant's game score
     */
    protected function getAllowableGameScore( int &$homeScore, int &$visitorScore ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;

        $diff = $homeScore - $visitorScore;
        $gamesPerSet = $this->getGamesPerSet();
        if( 0 === $diff && $homeScore >= $this->getTieBreakAt() ) $gamesPerSet = $this->getTieBreakAt();
 
        if($homeScore >= $gamesPerSet && $diff > 0 ) {
            if( $diff >= $this->getMustWinBy() && $this->noTieBreakers() ) {
                $homeScore = max( $visitorScore + $this->getMustWinBy(), $gamesPerSet );    
            }
            elseif( $diff >= $this->getMustWinBy() ) {
                if( $visitorScore === $gamesPerSet - 1 ) {
                    $homeScore = $gamesPerSet  + ($this->getMustWinBy() - 1);
                }
                else {
                    $homeScore = $gamesPerSet;
                    $visitorScore = min( $visitorScore, $homeScore - $this->getMustWinBy() );
                }
            }
        }
        elseif( $visitorScore >= $gamesPerSet && $diff < 0 ) {
            $diff =  abs($diff);
            if( $diff >= $this->getMustWinBy()  && $this->noTieBreakers() ) {
                $visitorScore = max( $homeScore + $this->getMustWinBy(), $gamesPerSet );  
            }
            elseif( $diff >= $this->getMustWinBy() ) {
                if( $homeScore === $gamesPerSet - 1 ) {
                    $visitorScore = $gamesPerSet  + ($this->getMustWinBy() - 1);
                }
                else {
                    $visitorScore = $gamesPerSet;
                    $homeScore = min( $homeScore, $visitorScore - $this->getMustWinBy() );
                }
            }
        }
        elseif( 0 === $diff ) {
            if( !$this->noTieBreakers() ) {
                $homeScore = min( $homeScore, $gamesPerSet );
                $visitorScore = min( $visitorScore, $gamesPerSet );
            }
        }
        
    }

    /**
     * Edits the tie breaker scores before saving them
     * @param int $homeScore The home entrant's tie break score
     * @param int $visitorScore the visitor entrant's tie break score
     */
    protected function getAllowableTieBreakScore( int &$homeScore, int &$visitorScore ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        
        if( $this->noTieBreakers() ) return;

        $diff = $homeScore - $visitorScore;
        if($homeScore >= $this->getTieBreakMinScore() && $diff > 0 ) {
            if( $diff >= $this->getMustWinBy() ) {
                if( $visitorScore === $this->getTieBreakMinScore() - 1 ) {
                    $homeScore = $this->getTieBreakMinScore() + ($this->getMustWinBy() - 1);
                }
                else {
                    $homeScore = $this->getTieBreakMinScore();
                    $visitorScore = min( $visitorScore, $homeScore - $this->getMustWinBy() );
                }
            }
        }
        elseif( $visitorScore >= $this->getTieBreakMinScore() && $diff < 0 ) {
            $diff =  abs($diff);
            if( $diff >= $this->getMustWinBy() ) {
                if( $homeScore === $this->getTieBreakMinScore() - 1 ) {
                    $visitorScore = $this->getTieBreakMinScore() + ($this->getMustWinBy() - 1);
                }
                else {
                    $visitorScore = $this->getTieBreakMinScore();
                    $homeScore = min( $homeScore, $visitorScore - $this->getMustWinBy() );
                }
            }
        }
    }
    
    
    /**
     * Save the game, tie breaker and tie scores for a given set of the supplied Match.
     * @param Match $match The match whose score are recorded
     * @param int $setnum The set number 
     * @param int ...$scores if 2 args then game scores; if 4 then games and tiebreaker scores
     */
	private function saveScores( Match &$match, int $setnum,  int $home_wins, int $visitor_wins, int $home_tb = 0, int $visitor_tb = 0, $home_ties = 0, $visitor_ties = 0 ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;

        $title = $match->title();
        $mess = sprintf("%s: called with match=%s, set num=%d with scores:hw=%d,vw=%d,htb=%d,vtb=%d,hometies=%d,visitorties=%d"
                        ,$loc, $title, $setnum
                        ,$home_wins
                        ,$visitor_wins
                        ,$home_tb
                        ,$visitor_tb 
                        ,$home_ties
                        ,$visitor_ties);
        $calledBy = isset(debug_backtrace()[1]['class']) ? debug_backtrace()[1]['class'] . '::'. debug_backtrace()[1]['function'] : debug_backtrace()[1]['function'];
        
        $this->log->error_log("{$mess} called by {$calledBy}");



        $match->setScore( $setnum, $home_wins, $visitor_wins, $home_tb, $visitor_tb, $home_ties, $visitor_ties );
        $this->log->error_log( sprintf( "%s ->For %s Set home games=%d(%d) and visitor games=%d(%d)."
                            , $loc, $match->title(), $home_wins, $home_tb, $visitor_wins, $visitor_tb ) );
            
        $match->save();
    }

       
}