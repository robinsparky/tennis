<?php
/**
* Automatically loads classes referenced in the code.
*/
class Autoloader
{
    public static function register()
    {
        spl_autoload_register( function ( $class ) {
            if( self::nameSpaceClassRegister( $class ) ) {
                return true;
            }
            else {
                return false;
            }
        } );
    }
    
    public static function nameSpaceClassRegister( $class ) {
        $class_filename = __DIR__ . "\\includes\\$class" . ".php";
        $file = str_replace('\\', DIRECTORY_SEPARATOR, $class_filename);
        if ( file_exists( $file ) ) {
            //error_log( __CLASS__ . '::' . __FUNCTION__ . " Register namespace class - loading: $class_filename" );
            require_once $file;
            return true;
        }
        //error_log(  __CLASS__ . '::' . __FUNCTION__ . " Register namespace class failed: $class_filename" );
        return false;
    }
}
Autoloader::register();