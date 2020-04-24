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
    public const TieBreak7Pt      = 2; //7 point tie breaker
    public const TieBreak10Pt     = 4; //10 point tie breaker
    public const TieBreak12Pt     = 8; //12 point tie breaker
    public const Best2Of3         = 16; //Specifies that match is best 2 of 3 sets
    public const TieBreakAt3All   = 32; //tie break at 3 all because first to win 4 games wins the set
    public const TieBreakAt6All   = 64; //tie break at 6 all
    public const TieBreakAt8All   = 128; //tie break at 8 all
    public const TieBreakAt10All  = 256; //tie break at 10 all
    public const TieBreakDecider  = 512; //Match is decided by tie breaker instead of final set
    public const NoTieBreakFinalSet = 1024; //Final set must be won by 2 games; ie no tie breaker
    public const Best3Of5         = 2048; //Best 3 of 5 sets
    public const OneSet           = 4096; //Only 1 set played

    public const REGULATION      = 'Regulation'; //Best 2 of 3 sets with 7pt tie breaker at 6 all
    public const ATPMAJOR        = 'Major'; //Best 3 of 5 sets with 7pt tie breaker at 6 all
    public const PRO_SET8        = "Pro Set 8 Games"; //Best of 8 games with 7 pt tie break at 8 all
    public const PRO_SET10       = "Pro Set 10 Games"; //Best of 10 games with 7pt tie break at 10 all
    public const MATCH_TIE_BREAK = "Match Tie Break"; //Best 2 of 3 sets, but 3rd set is 10pt tie breaker. e.g. Laver Cup
    public const FAST4           = "Fast4"; //No ad scoring, lets ignored, 7pt tie breaker at 3 all

    public $ScoreTypes = array( self::REGULATION      => self::Best2Of3 & self::TieBreakAt6All & self::TieBreak7Pt,
                                self::ATPMAJOR        => self::Best3Of5 & self::TieBreakAt6All & self::TieBreak7Pt,
                                self::FAST4           => self::Best2Of3 & self::NoAd & self::TieBreakAt3All & self::TieBreak7Pt,
                                self::PRO_SET8        => self::OneSet & self::TieBreakAt8All & self::TieBreak7Pt,
                                self::PRO_SET10       => self::OneSet & self::TieBreakAt10All & self::TieBreak7Pt,
                                self::MATCH_TIE_BREAK => self::Best2Of3 & self::TieBreakDecider & self::TieBreakAt6All & self::TieBreak10Pt, 
                            );
                               

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
    
    public function allowedTypes() {
        return array_keys($this->ScoreTypes);
    }

    public function isValid( $possible ) {
        return in_array( $possible, $this->allowedTypes() );
    }

    public function getScoreTypeMask( string $key ): int {
        if( $this->isValid($key) ) {
            return $this->ScoreTypes[$key];
        }
        return 0;
    }
}