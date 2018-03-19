<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
* Match scoring:
* No ad
    * 'No advantage'. Scoring method created by Jimmy Van Alen. 
    * The first player or doubles team to win four points wins the game, regardless of whether the player or team is ahead by two points. 
    * When the game score reaches three points each, the receiver chooses which side of the court (advantage court or deuce court) the service is to be delivered
    *  on the seventh and game-deciding point. 
    * Utilized by World Team Tennis professional competition, ATP tours, WTA tours, ITF Pro Doubles and ITF Junior Doubles.
* Pro set
    * Instead of playing multiple sets, players may play one "pro set". A pro set is first to 8 (or 10) games by a margin of two games, instead of first to 6 games. 
    * A 12-point tie-break is usually played when the score is 8–8 (or 10–10). These are often played with no-ad scoring.
* Match tie-break
    * This is sometimes played instead of a third set. A match tie-break (also called super tie-break) is played like a regular tie-break, 
    * but the winner must win ten points instead of seven. 
    * Match tie-breaks are used in the Hopman Cup, Grand Slams (excluding Wimbledon) and the Olympic Games for mixed doubles; 
    * on the ATP (since 2006), WTA (since 2007) and ITF (excluding four Grand Slam tournaments and the Davis Cup) tours for doubles and as a player's choice in USTA league play.
* Fast4
    * Fast4 is a shortened format that offers a "fast" alternative, with four points, four games and four rules: 
    * there are no advantage scores, lets are played, tie-breakers apply at three games all and the first to four games wins the set.
 */
class ScoreType {
    public const NO_AD           = "no ad";
    public const PRO_SET         = "pro set";
    public const MATCH_TIE_BREAK = "match tie break";
    public const FAST4           = "fast4";
}
