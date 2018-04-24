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
	private $match;

	public const INPROGRESS = "In progress";
	public const NOTSTARTED = "Not started";
	public const COMPLETED  = "Completed";
	public const EARLYEND   = "Early end";
	
    //General Tennis Scoring Rules
	protected $numChallenges = 3;
    protected $MaxSets = 5;
    protected $MinSets = 3;
    protected $GamesPerSet = 6;
    protected $TieBreakerMinimum = 7;

	abstract public function recordScores( int $set, int ...$scores );
	abstract public function matchScore();
	abstract public function matchStatus();
	abstract public function homeDefault();
	abstract public function matchWinner();
	abstract public function visitorDefault();

	public function challengesRemaining() {
		return $this->numChallenges;
	}

	public function getHomePlayer():string {
		if( isset( $this->match ) ) {
			return $this->match->getHomeEntrant()->getName();
		}
		else return '';
	}
	
	public function getVisitorPlayer():string {
		if( isset( $this->match ) ) {
			return $this->match->getVisitorEntrant()->getName();
		}
		else return '';
	}

	public function setMatch( Match &$match ) {
		$result = false;
		if( $match->isValid() ) {
			$this->match = $match;
			$result = true;
		}
		return $result;
	}

	public function getMatch() {
		return $this->match;
	}

	public function getMaxSets() {
		return $this->maxSets;
	}
}