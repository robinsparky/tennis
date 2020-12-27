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

    //Keys for rule masks
    public const REGULATION      = 'Regulation'; //Best 2 of 3 sets with 7pt tie breaker at 6 all
    public const ATPMAJOR        = 'Major'; //Best 3 of 5 sets with 7pt tie breaker at 6 all
    public const PRO_SET8        = "Pro Set 8 Games"; //Best of 8 games with 7 pt tie break at 8 all
    public const PRO_SET10       = "Pro Set 10 Games"; //Best of 10 games with 7pt tie break at 10 all
    public const MATCH_TIE_BREAK = "Match Tie Break"; //Best 2 of 3 sets, but 3rd set is 10pt tie breaker. e.g. Laver Cup
    public const FAST4           = "Fast4"; //No ad scoring, lets ignored, 7pt tie breaker at 3 all
    public const POINTS1         = "Points1"; //Based on points per win and total games won
    public const POINTS2         = "Points2"; //Based on points per win and total games won
    
    /**
     * Scoring rules
     * Each scoring rule is defined by a dictionary of scoring attributes
     */
    public $ScoreRules = 
             array( self::REGULATION => array("MaxSets"=>3,"GamesPerSet"=>6, "TieBreakAt"=>6, "TieBreakerMinimum"=>7),
                    self::ATPMAJOR   => array("MaxSets"=>5,"GamesPerSet"=>6, "TieBreakAt"=>6, "TieBreakerMinimum"=>7),
                    self::FAST4      => array("MaxSets"=>3,"GamesPerSet"=>4, "TieBreakAt"=>3, "MustWinBy"=>1, "TieBreakerMinimum"=>7),
                    self::PRO_SET8   => array("MaxSets"=>1,"GamesPerSet"=>8, "TieBreakAt"=>8, "TieBreakerMinimum"=>12),
                    self::PRO_SET10  => array("MaxSets"=>1,"GamesPerSet"=>10, "TieBreakAt"=>10, "TieBreakerMinimum"=>12),
                    self::MATCH_TIE_BREAK => array("MaxSets"=>3,"GamesPerSet"=>6,"TieBreakAt"=>6, "TieBreakerMinimum"=>10, "TieBreakDecider"=>true), 
                    self::POINTS1    => array("MaxSets"=>1,"GamesPerSet"=>6,"MustWinBy"=>1,"PointsForWin"=>1),
                    self::POINTS2    => array("MaxSets"=>1,"GamesPerSet"=>6,"MustWinBy"=>1,"PointsForWin"=>2),
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
    
    /**
     * Get keys of scoring rules
     * Each key identifies a set of rules for scoring
     */
    public function allowedRules() {
        return array_keys( $this->ScoreRules );
    }

    /**
     * Verify is a score key is valid
     */
    public function isValid( $possible ) {
        return array_key_exists( $possible, $this->ScoreRules );
    }

    /**
     * Get a the set of scoring rules for a given score type
     * @param string $key
     * @return array Set of scoring rules
     */
    public function getScoringRules( string $key ): array {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        error_log("{$loc}('{$key}')");

        if( array_key_exists( $key, $this->ScoreRules ) ) {
            error_log("$loc: returning ...{$key}=>");            
            error_log(print_r($this->ScoreRules[$key], true ) );
            return $this->ScoreRules[$key];
        }
        error_log("$loc: returning empty array");
        return [];
    }
}