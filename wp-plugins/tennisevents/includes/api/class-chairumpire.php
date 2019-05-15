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
	protected $numChallenges = 3;
    protected $MaxSets = 3;
    protected $GamesPerSet = 6;
	protected $TieBreakerMinimum = 7;
	
	protected $log;

	abstract public function recordScores(Match &$match, int $set, int ...$scores );
	abstract public function getScores( Match &$match );
	abstract public function matchWinner( Match &$match );
	abstract public function matchStatus( Match &$match );
	abstract public function defaultHome( Match &$match, string $cmts );
	abstract public function defaultVisitor( Match &$match, string $cmts );
	abstract public function setMaxSets( int $max = 3 );
	
	public function __construct() {
		$this->log = new BaseLogger( true );
	}
	
	public function challengesRemaining() {
		return $this->numChallenges;
	}

	public function getHomePlayer( Match &$match ):string {
		if( isset( $match ) ) {
			return $match->getHomeEntrant()->getName();
		}
		else return '';
	}
	
	public function getVisitorPlayer( Match &$match ):string {
		if( isset( $match ) ) {
			return $this->match->getVisitorEntrant()->getName();
		}
		else return '';
	}

	public function getMaxSets() {
		return $this->MaxSets;
	}
}