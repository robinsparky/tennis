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
 * @class  TournamentDirector
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
*/
abstract class ChairUmpire
{
	private $match;
	private $numChallenges = 3;

	abstract public function recordScores( int $set, int ...$scores );
	abstract public function whatIsScore();
	abstract public function whatIsStatus();
	abstract public function defaultTheMatch();
	abstract public function whoWonTheMatch();

	public function challengesRemaining() {
		return $this->numChallenges;
	}

	public function getHomePlayer() {
		if( isset( $this->match ) ) {
			return $this->match->getHomeEntrant()->getName();
		}
		else return '';
	}
	
	public function getVisitorPlayer() {
		if( isset( $this->match ) ) {
			return $this->match->getVisitorEntrant()->getName();
		}
		else return '';
	}

	public function setMatch( Match $match ) {
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
}