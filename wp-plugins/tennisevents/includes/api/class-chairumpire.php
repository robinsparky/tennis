<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$p2Dir = plugin_dir_path( plugin_dir_path( __DIR__ ) );
require_once( $p2Dir . 'tennisevents.php' );
require_once( 'api-exceptions.php' );

/** 
 * ChairUmpire interprets the scores for matches
 * as well as determing if a match is complete or not.
 * This interface also supports defaulting a match.
 * @class  ChairUmpire
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
	
	protected $log;

	abstract public function recordScores(Match &$match, int $set, int ...$scores );
	abstract public function getScores( Match &$match );
    abstract public function matchWinner( Match &$match );
	// abstract public function matchStatus( Match &$match );
	// abstract public function defaultHome( Match &$match, string $cmts );
	// abstract public function defaultVisitor( Match &$match, string $cmts );
	abstract public function setMaxSets( int $max = 3 );
    abstract public function getMatchSummary( Match &$match );
	
	public function __construct( $logger = false ) {
		$this->log = new BaseLogger( $logger );
	}

	public function getHomePlayer( Match &$match ):string {
		if( isset( $match ) ) {
			return $match->getHomeEntrant()->getName();
		}
		else return '';
	}
	
	public function getVisitorPlayer( Match &$match ):string {
		if( isset( $match ) ) {
			return $match->getVisitorEntrant()->getName();
		}
		else return '';
	}

	public function getMaxSets() {
		return $this->MaxSets;
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
     * @param string @entrantType either visitor or home
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
     * A match is locked if it has been completed or if there was a default/early retirement
     * In other words, it must have a winner
     * @return bool true if locked, false otherwise
     */
    public function isLocked( Match $match ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;

        $locked = false;
        $status = $this->matchStatusEx( $match );
        if($status->getMajorStatus() === MatchStatus::Completed 
        || $status->getMajorStatus() === MatchStatus::Retired ) {
            $locked = true;
        }

        return $locked;
    }
    
}