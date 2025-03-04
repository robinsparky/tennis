<?php
/**
* Simple autoloader, so we don't need Composer just for this.
*/
class AutoloaderM
{
    public static function register()
    {
        //$tmpFileName = tempnam(sys_get_temp_dir(), 'tennis');
        //error_log(  __CLASS__ . '::' . __FUNCTION__ ,3,$tmpFileName);

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
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $class_filename = __DIR__ . "\\includes\\$class" . ".php";
        $file = wp_normalize_path($class_filename); //str_replace('\\', DIRECTORY_SEPARATOR, $class_filename);
        if ( file_exists( $file ) ) {
            //error_log( "$loc: loading: '$class' in '$file'}" );
            require $file;
            return true;
        }
        // error_log(  "$loc: class failed: '$class' in '$file'" );
        return false;
    }
}
AutoloaderM::register();