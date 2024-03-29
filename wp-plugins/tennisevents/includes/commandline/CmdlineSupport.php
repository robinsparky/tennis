<?php
namespace commandline;

use \WP_CLI;
use \WP_CLI_Command;
use \TennisEvents;

use datalayer\Club;
use datalayer\Bracket;
use datalayer\Event;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( !class_exists( 'commandline\CmdlineSupport' ) ) :

/**
 * Supporting functions for Command Line interface
 *
 * @class CmdlineSupport
 * @version	1.0.0
*/
class CmdlineSupport {

    const ENVNAME = 'tennisEnvironment';

	//This class's singleton
	private static $_instance;

	/**
	 * CmdlineSupport Singleton
	 *
	 * @since 1.0
	 * @static
	 * @see WP_CLI
	 * @return $_instance --Main instance.
	 */
	public static function get_instance() {
		return self::instance();
    }

	/**
	 * CmdlineSupport Singleton
	 *
	 * @since 1.0
	 * @static
	 * @see WP_CLI
	 * @return $_instance --Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
    }
    
    public static function preCondtion() {
        $tcs = self::instance();
        $tcs->checkUserError();
        return $tcs;
    }
	
	/**
	 *  Constructor.
	 */
	public function __construct() {
		// Don't allow more than one instance of the class
		if ( isset( self::$_instance ) ) {
			WP_CLI::error( sprintf( '%s is a singleton class and you cannot create a second instance.', get_class( $this ) ) );
		}
    }

    public function checkUser() {
        $result = false;
        $user = wp_get_current_user();
        if( $user->ID > 0 ) {
            $result = true;
        }
        return $result;
    }
    
    public function checkUserError() {
        $user = wp_get_current_user();
        if( $user->ID < 1 ) {
            WP_CLI::error("Failed: user not set.");
        }
    }

    public function getEnv() {
        $arr = array();
        $env = get_transient( self::ENVNAME );
        if( is_array( $env ) &&  count( $env ) === 2 ) {
            list( $clubId, $eventId ) = $env;
            array_push( $arr, $clubId, $eventId );
        }
        else {
            array_push( $arr, 0, 0 );
        }
        return $arr;
    }
    
    public function getEnvError() {
        $env = get_transient( self::ENVNAME );
        if( is_array( $env ) &&  count( $env ) === 3 ) {
            return $env;
        }
        WP_CLI::error( "Please set the club Id, Event Id and Bracket Names in the tennis environment" );
    }

    public function getEventRecursively( Event $evt, int $descendantId ) {
        static $attempts = 0;
        if( $descendantId === $evt->getID() ) return $evt;

        if( count( $evt->getChildEvents() ) > 0 ) {
            if( ++$attempts > 10 ) return null;
            foreach( $evt->getChildEvents() as $child ) {
                if( $descendantId === $child->getID() ) {
                    return $child;
                }
                else { 
                    return $this->getEventRecursively( $child, $descendantId );
                }
            }
        }
        else {
            return null;
        }
    }
    
}
endif;

