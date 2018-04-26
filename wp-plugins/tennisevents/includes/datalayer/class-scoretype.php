<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
* Match scoring:
* No ad
    * 'No advantage'. Scoring method created by Jimmy Van Alen. 
    * The first player or doubles team to win four points wins the game, regardless of whether the player or team is ahead by two points. 
    * When the game score reaches three points each, the receiver chooses which side of the court (advantage court or deuce court) 
    * the service is to be delivered on the seventh and game-deciding point in the 6 point tie-breaker. 
    * Utilized by World Team Tennis professional competition, ATP tours, WTA tours, ITF Pro Doubles and ITF Junior Doubles.
* Pro set
    * Instead of playing multiple sets, players may play one "pro set".
    * A pro set is first to 8 (or 10) games by a margin of two games, instead of first to 6 games. 
    * A 12-point tie-break is usually played when the score is 8–8 (or 10–10). 
    * These are often played with no-ad scoring.
* Match tie-break
    * This is sometimes played instead of a third set. 
    * A match tie-break (also called super tie-break) is played like a regular tie-break, 
    * but the winner must win ten points instead of seven. 
    * Match tie-breaks are used in the Hopman Cup, Grand Slams (excluding Wimbledon) and the Olympic Games for mixed doubles; 
    * on the ATP (since 2006), WTA (since 2007) and ITF (excluding four Grand Slam tournaments and the Davis Cup) tours for doubles and as a player's choice in USTA league play.
* Fast4
    * Fast4 is a shortened format that offers a "fast" alternative, with four points, four games and four rules: 
    * there are no advantage scores, lets are played, tie-breakers apply at three games all and the first to four games wins the set.
 */
class ScoreType {

    public const NoAd             = 1; //Can win game by one point
    public const LetsNotPlayed    = 2; //Keep playing if a let occurs
    public const TieBreak6Pt      = 4; //6 point tie breaker
    public const TieBreak8Pt      = 8; //8 point tie breaker
    public const TieBreak10Pt     = 16; //10 point tie breaker
    public const TieBreak12Pt     = 32; //12 point tie breaker
    public const TieBreakAt3      = 64; //tie break at 3 all
    public const TieBreakAt6      = 128; //tie break at 6 all
    public const TieBreakAt8      = 256; //tie break at 8 all
    public const TieBreakAt10     = 512; //tie break at 10 all
    public const TieBreakDecider  = 1024; //Match is decided by tie breaker instead of final set

    public const REGULATION      = 'Regulation';
    public const NO_AD           = "No Ad";
    public const PRO_SET8        = "Pro Set 8 Games";
    public const PRO_SET10       = "Pro Set 10 Games";
    public const MATCH_TIE_BREAK = "Match Tie Break";
    public const FAST4           = "Fast4";

    public $ScoreTypes = array( REGULATION      => TieBreakAt6 & TieBreak6Pt,
                                NO_AD           => TieBreakAt3 & TieBreak6Pt,
                                PRO_SET8        => TieBreakAt8 & TieBreak12Pt,
                                PRO_SET10       => TieBreakAt10 & TieBreak12Pt,
                                MATCH_TIE_BREAK => TieBreakDecider & TieBreak10Pt );
                               

	//This class's singleton
	private static $_instance;

	/**
	 * ScoreType Singleton
	 *
	 * @since 1.0
	 * @static
	 * @return $_instance --Main instance.
	 */
	public static function get_instance() {
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
			wp_die( sprintf( esc_html__( '%s is a singleton class and you cannot create a second instance.', 'ten' ),get_class( $this ) ) );
		}

	}
}