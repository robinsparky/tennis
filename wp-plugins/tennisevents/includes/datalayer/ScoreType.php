<?php
namespace datalayer;

use TennisEvents;

//use \TennisEvents;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
* TennisMatch scoring:
* No ad
    * A popular alternative to advantage scoring is "no-advantage" (or "no-ad") scoring, created by James Van Alen in order to shorten match playing time.
    * No-advantage scoring is a scoring method in which the first player to reach four points wins the game. 
    * No-ad scoring eliminates the requirement that a player must win by two points. 
    * Therefore, if the game is tied at deuce, the next player to win a point wins the game. 
    * This method of scoring is used in most World TeamTennis matches.
    * When this style of play is implemented, at deuce, the receiver then chooses from which side of the court he or she desires to return the serve. 
    * However, in no-ad mixed doubles play, each gender always serves to the same gender at game point and during the final point of tiebreaks.
* Pro set
    * Instead of playing multiple sets, players may play one "pro set".
    * A pro set is first to 8 (or 10) games by a margin of two games, instead of first to 6 games. 
    * A 12-point tie-break is usually played when the score is 8–8 (or 10–10). 
    * These are often played with no-ad scoring.
* TennisMatch tie-break
    * This is sometimes played instead of a third set. 
    * A match tie-break (also called super tie-break) is played like a regular tie-break, 
    * but the winner must win ten points instead of seven. 
    * TennisMatch tie-breaks are used in the Hopman Cup, Grand Slams (excluding Wimbledon) and the Olympic Games for mixed doubles; 
    * on the ATP (since 2006), WTA (since 2007) and ITF (excluding four Grand Slam tournaments and the Davis Cup) tours for doubles and as a player's choice in USTA league play.
* Fast4
    * Fast4 is a shortened format that offers a "fast" alternative, with four points, four games and four rules: 
    * there are no advantage scores, lets are played, tie-breakers apply at three games all and the first to four games wins the set.
 */
class ScoreType {

    //Keys for rule masks
    public const BEST2OF3        = '2of3'; //Best 2 of 3 sets with 7pt tie breaker at 6 all
    public const BEST3OF5        = '3of5'; //Best 3 of 5 sets with 7pt tie breaker at 6 all
    public const PROSET8         = "proset8"; //Best of 8 games with 7 pt tie break at 8 all
    public const PROSET10        = "proset10"; //Best of 10 games with 7pt tie break at 10 all
    public const BEST2OF3TB      = "2of3matchtiebreaker"; //Best 2 of 3 sets, but 3rd set is 10pt tie breaker. e.g. Laver Cup
    public const FAST4           = "fast4"; //No ad scoring, lets ignored, single point decider at 3 all
    public const POINTS1         = "1set1point"; //Based on points per win and total games won
    public const POINTS2         = "1set2points"; //Based on points per win and total games won    
    public const POINTS3         = "2sets2points"; //Based on points per win and total games won
    public const TEAMTENNIS      = "ttc-teamtennis"; //Team Tennis Scoring at Tyandaga Tennis Club

    /*
    * Score Type Descriptions
    */
    private $ScoreRuleDescriptions = [
            self::BEST2OF3   => 'Best 2 of 3',
            self::BEST3OF5   => 'Best 3 of 5',
            self::PROSET8    => "Pro Set 8 Games",
            self::PROSET10   => "Pro Set 10 Games",
            self::BEST2OF3TB => "Best 2 of 3 with 3rd set tie breaker",
            self::FAST4      => "Fast4",
            self::POINTS1    => "One Set One Point Per Win",
            self::POINTS2    => "One Set Two Points Per Win",    
            self::POINTS3    => "Two Sets Two Points Per Win",
            self::TEAMTENNIS => "Total games Two Points Per Win One Point Per Tie",
    ];
    
    /**
     * Scoring rules
     * Each scoring rule is defined by a dictionary of scoring attributes
     */
    public $ScoreRules = 
             array( self::BEST2OF3   => array("MaxSets"=>3,"GamesPerSet"=>6, "TieBreakAt"=>6, "TieBreakerMinimum"=>7),
                    self::BEST3OF5   => array("MaxSets"=>5,"GamesPerSet"=>6, "TieBreakAt"=>6, "TieBreakerMinimum"=>7),
                    self::FAST4      => array("MaxSets"=>3,"GamesPerSet"=>4, "TieBreakAt"=>0, "MustWinBy"=>1, "TieBreakerMinimum"=>1),
                    self::PROSET8    => array("MaxSets"=>1,"GamesPerSet"=>8,"MustWinBy"=>2, "TieBreakAt"=>0, "TieBreakerMinimum"=>0),
                    self::PROSET10   => array("MaxSets"=>1,"GamesPerSet"=>10, "TieBreakAt"=>10, "TieBreakerMinimum"=>7),
                    self::BEST2OF3TB => array("MaxSets"=>3,"GamesPerSet"=>6,"TieBreakAt"=>6, "TieBreakerMinimum"=>7, "TieBreakDecider"=>10), 
                    self::POINTS1    => array("MaxSets"=>1,"GamesPerSet"=>6,"MustWinBy"=>2,"PointsPerWin"=>1),
                    self::POINTS2    => array("MaxSets"=>1,"GamesPerSet"=>6,"MustWinBy"=>2,"PointsPerWin"=>2),
                    self::POINTS3    => array("MaxSets"=>2,"GamesPerSet"=>4,"MustWinBy"=>2,"PointsPerWin"=>2),
                    self::TEAMTENNIS => array("MaxSets"=>4,"GamesPerSet"=>99,"MustWinBy"=>1,"PointsPerWin"=>2),
                );
                

    //Used to separate Elimination rules from Round Robin rules
    private $RoundRobinOnly = [self::POINTS1 =>''
                              ,self::POINTS2 =>''
                              ,self::POINTS3 =>''
                              ,self::TEAMTENNIS =>''
                            ];
   
	//This class's singleton
	private static $_instance;

	/**
	 * ScoreType Singleton
	 *
	 * @since 1.0
	 * @static
	 * @return ScoreType $_instance --Main instance.
	 */
	public static function get_instance() : self {
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
			wp_die( sprintf( esc_html__( '%s is a singleton class and you cannot create a second instance.', TennisEvents::TEXT_DOMAIN ),get_class( $this ) ) );
		}
    }

    /*
    * Get the descriptions of the scoring rules
    */
    public function getRuleDescriptions() {
        return $this->ScoreRuleDescriptions;
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
            // error_log("$loc: returning ...{$key}=>");            
            // error_log(print_r($this->ScoreRules[$key], true ) );
            return $this->ScoreRules[$key];
        }
        // error_log("$loc: returning empty array");
        return [];
    }

    /**
     * Get scoring rules valid for given Format
     * @param string $format
     * @return array of scoring rules
     */
    public function validFormatScoringRules( string $format ) : array {
        $loc = __CLASS__ . '::' . __FUNCTION__;

        $result = array();

        if( $this->isValid( $format ) ) {
            $elimOnly = array_diff_key($this->ScoreRules, $this->RoundRobinOnly);
            $rrOnly = array_diff_key($this->ScoreRules, $elimOnly );

            error_log("{$loc}: Elimination rules:");
            error_log(print_r($elimOnly,true));
            error_log("{$loc}: Round Robin rules:");
            error_log(print_r($rrOnly,true));

            switch($format) {
                case Format::ELIMINATION:
                    $result = $elimOnly;
                    break;
                case Format::ROUNDROBIN:
                    $result = $rrOnly;
                    break;
            }
        }
        return $result;
    }
}