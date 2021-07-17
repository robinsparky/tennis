<?php
/**
* Automatically loads classes referenced in the code.
*/
class Autoloader
{
    public static function register()
    {
        spl_autoload_register( function ( $class ) {
            //error_log(  __CLASS__ . '::' . __FUNCTION__ . " Attempting to register class: ${class}" );
            if( self::nameSpaceClassRegister( $class ) ) {
                return true;
            }
            // elseif( self::cptClassRegister( $class ) ) {
            //     return true;
            // }
            else {
                return false; //self::cmdClassRegister( $class );
            }
        } );
    }
    
    public static function nameSpaceClassRegister( $class ) {
        $class_filename = __DIR__ . "\\includes\\$class" . ".php";
        $file = str_replace('\\', DIRECTORY_SEPARATOR, $class_filename);
        if ( file_exists( $file ) ) {
            //error_log(  __CLASS__ . '::' . __FUNCTION__ . " Register namespace class - loading: $class_filename" );
            require_once $file;
            return true;
        }
        //error_log(  __CLASS__ . '::' . __FUNCTION__ . " Register namespace class failed: $class_filename" );
        return false;
    }
    /*
    public static function cmdClassRegister( $class ) {
        $class_filename = __DIR__ . "\\includes\\commandline\\class-$class" . ".php";
        $file = str_replace('\\', DIRECTORY_SEPARATOR, $class_filename);
        if ( file_exists( $file ) && defined( 'WP_CLI' ) && WP_CLI ) {
            //error_log(  __CLASS__ . '::' . __FUNCTION__ . " Register Command class - loading: $class_filename" );
            require_once $file;
            return true;
        }
        //error_log(  __CLASS__ . '::' . __FUNCTION__ . " Register Command class failed: $class_filename" );
        return false;
    } 

    public static function cptClassRegister( $class ) {
        $class_filename = __DIR__ . "\\includes\\cpt\\$class" . ".php";
        $file = str_replace('\\', DIRECTORY_SEPARATOR, $class_filename);
        if ( file_exists( $file ) ) {
            require_once $file;
            //error_log(  __CLASS__ . '::' . __FUNCTION__ . " Register custom post type class - succeeded: $class_filename" );
            return true;
        }
        //error_log( __CLASS__ . '::' . __FUNCTION__ . " Register custom post type class failed: $class_filename" );
        return false;
    }
    */
}
Autoloader::register();