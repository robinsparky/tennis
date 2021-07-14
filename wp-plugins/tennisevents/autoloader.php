<?php
/**
* Simple autoloader, so we don't need Composer just for this.
*/
class Autoloader
{
    public static function register()
    {
        spl_autoload_register( function ( $class ) {
            //error_log(  __CLASS__ . '::' . __FUNCTION__ . " Attempting to register class: ${class}" );
            $class_filename = __DIR__ . "\\includes\\datalayer\\class-$class" . ".php";
            $file = str_replace('\\', DIRECTORY_SEPARATOR, $class_filename);

            if ( file_exists( $file ) ) {
                // error_log(  __CLASS__ . '::' . __FUNCTION__ . " Register Data class - loading: $class_filename" );
                require $file;
                return true;
            }
            elseif( self::apiClassRegister( $class ) ) {
                return true;
            }
            elseif( self::nameSpaceClassRegister( $class ) ) {
                return true;
            }
            elseif( self::cptClassRegister( $class ) ) {
                return true;
            }
            else {
                return false; //self::cmdClassRegister( $class );
            }
        } );
    }

    public static function apiClassRegister( $class ) {
        $class_filename1  = __DIR__ . "\\includes\\api\\class-$class" . ".php";
        $class_filename2 = __DIR__ . "\\includes\\api\\$class" . ".php";
        $file1 = str_replace('\\', DIRECTORY_SEPARATOR, $class_filename1);
        $file2 = str_replace('\\', DIRECTORY_SEPARATOR, $class_filename2);
        if ( file_exists( $file1 ) ) {
            //error_log( __CLASS__ . '::' . __FUNCTION__ . " Register API class - loading: $class_filename1" );
            require $file1;
            return true;
        }
        elseif( file_exists( $file2 ) ) {
            //error_log( __CLASS__ . '::' . __FUNCTION__ . " Register API class - loading: $class_filename2" );
            require $file2;
            return true;
        }
        // error_log( __CLASS__ . '::' . __FUNCTION__ . " Register API class failed: $class_filename1");
        return false;
    }
    
    public static function cmdClassRegister( $class ) {
        $class_filename = __DIR__ . "\\includes\\commandline\\class-$class" . ".php";
        $file = str_replace('\\', DIRECTORY_SEPARATOR, $class_filename);
        if ( file_exists( $file ) && defined( 'WP_CLI' ) && WP_CLI ) {
            // error_log(  __CLASS__ . '::' . __FUNCTION__ . " Register Command class - loading: $class_filename" );
            require_once $file;
            return true;
        }
        // error_log(  __CLASS__ . '::' . __FUNCTION__ . " Register Command class failed: $class_filename" );
        return false;
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

    public static function cptClassRegister( $class ) {
        $class_filename = __DIR__ . "\\includes\\cpt\\$class" . ".php";
        $file = str_replace('\\', DIRECTORY_SEPARATOR, $class_filename);
        if ( file_exists( $file ) ) {
            require_once $file;
            // error_log(  __CLASS__ . '::' . __FUNCTION__ . " Register custom post type class - succeeded: $class_filename" );
            return true;
        }
        // error_log( __CLASS__ . '::' . __FUNCTION__ . " Register custom post type class failed: $class_filename" );
        return false;
    }
}
Autoloader::register();