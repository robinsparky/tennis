
<?php
/**
* Simple autoloader, so we don't need Composer just for this.
*/
class Autoloader
{
    public static function register()
    {
        spl_autoload_register( function ( $class ) {
            $class_filename = __DIR__ . "\\includes\\datalayer\\class-$class" . ".php";
            $file = str_replace('\\', DIRECTORY_SEPARATOR, $class_filename);
            if ( file_exists( $file ) ) {
                //error_log( "Register Data class - loading: $class_filename" );
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
        $class_filename = __DIR__ . "\\includes\\api\\class-$class" . ".php";
        $file = str_replace('\\', DIRECTORY_SEPARATOR, $class_filename);
        if ( file_exists( $file ) ) {
            //error_log( "Register API class - loading: $class_filename" );
            require $file;
            return true;
        }
        //error_log("Register API class failed: $class_filename");
        return false;
    }
    
    public static function cmdClassRegister( $class ) {
        $class_filename = __DIR__ . "\\includes\\commandline\\class-$class" . ".php";
        $file = str_replace('\\', DIRECTORY_SEPARATOR, $class_filename);
        if ( file_exists( $file ) && defined( 'WP_CLI' ) && WP_CLI ) {
            //error_log( "Register Command class - loading: $class_filename" );
            require_once $file;
            return true;
        }
        //error_log( "Register Command class failed: $class_filename" );
        return false;
    } 

    public static function nameSpaceClassRegister( $class ) {
        $class_filename = __DIR__ . "\\includes\\$class" . ".php";
        $file = str_replace('\\', DIRECTORY_SEPARATOR, $class_filename);
        if ( file_exists( $file ) ) {
            //error_log( "Register namespace class - loading: $class_filename" );
            require_once $file;
            return true;
        }
        //error_log( "Register namespace class failed: $class_filename" );
        return false;
    }

    public static function cptClassRegister( $class ) {
        $class_filename = __DIR__ . "\\includes\\cpt\\$class" . ".php";
        $file = str_replace('\\', DIRECTORY_SEPARATOR, $class_filename);
        if ( file_exists( $file ) ) {
           //error_log( "Register custom post type class - loading: $class_filename" );
            require_once $file;
            return true;
        }
        //error_log( "Register custom post type class failed: $class_filename" );
        return false;
    }
}
Autoloader::register();