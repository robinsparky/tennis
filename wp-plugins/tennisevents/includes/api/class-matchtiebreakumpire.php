<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// $dir = plugin_dir_path( __DIR__ );
// include_once($dir . '/gw-support.php' );


/**
 * Match tie-break
    * This is sometimes played instead of a third set. A match tie-break (also called super tie-break) is played like a regular tie-break, 
    * but the winner must win ten points instead of seven. 
    * Match tie-breaks are used in the Hopman Cup, Grand Slams (excluding Wimbledon) and the Olympic Games for mixed doubles; 
    * on the ATP (since 2006), WTA (since 2007) and ITF (excluding four Grand Slam tournaments and the Davis Cup) tours for doubles and as a player's choice in USTA league play.
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
 */
class MatchTieBreakUmpire extends ChairUmpire
{
    
	//This class's singleton
	private static $_instance;

	/**
	 * MatchTieBreakUmpire Singleton
	 *
	 * @since 1.0
	 * @static
	 * @return $_instance --Main instance.
	 */
	public static function getInstance() {
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
     * Retrieve the Champion for this bracket
     * @param Bracket $bracket
     * @return Entrant who won the bracket or null if not completed
     */
    public function getChampion( &$bracket ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        $bracketName = $bracket->getName();
        $this->log->error_log("$loc($bracketName)");

        if( !$bracket->isApproved() ) return null;

        $lastRound = $bracket->getNumberOfRounds();
        $finalMatches = $bracket->getMatchesByRound( $lastRound );

        if( count( $finalMatches ) !== 1 ) {
            $c = count( $finalMatches );
            $errmess = "Final round in bracket '{$bracketName}' with {$lastRound} rounds does not have exactly one match({$c}).";
            $this->log->error_log( $errmess );
            throw new InvalidBracketException( $errmess );
        }

        $finalMatch = array_pop($finalMatches);
        $champion = null;
        $this->isLocked( $finalMatch, $champion );
        
        return $champion;
    }
    
}