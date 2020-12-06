<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pro set
    * Instead of playing multiple sets, players may play one "pro set". 
    * A pro set is first to 8 (or 10) games by a margin of two games, instead of first to 6 games. 
    * A 12-point tie-break is usually played when the score is 8–8 (or 10–10). These are often played with no-ad scoring.
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
 */
class ProSetUmpire extends ChairUmpire
{
    
	//This class's singleton
	private static $_instance;

	/**
	 * ProSetUmpire Singleton
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
		parent::__construct();

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