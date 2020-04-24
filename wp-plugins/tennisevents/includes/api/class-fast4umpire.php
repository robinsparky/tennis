<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
* Fast4
	* Fast4 is a shortened format that offers a "fast" alternative, 
	* with four points, four games and four rules: 
    * 1. there are no advantage scores 
    * 2. lets are not played
    * 3. tie-breakers apply at three games all 
    * 4. the first to four games wins the set
 */
class Fast4Umpire extends ChairUmpire
{
	//This class's singleton
	private static $_instance;

	/**
	 * Fast4Umpire Singleton
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

}