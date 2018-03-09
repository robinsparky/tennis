
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
            if (file_exists($file)) {
                error_log("Register Data class - loading: $class_filename");
                require $file;
                return true;
            }
            error_log("Register Data class failed: $class_filename. Calling api class search!");
            return self::apiClassRegister( $class );
        } );
    }

    public static function apiClassRegister( $class ) {
        $class_filename = __DIR__ . "\\includes\\api\\class-$class" . ".php";
        $file = str_replace('\\', DIRECTORY_SEPARATOR, $class_filename);
        if (file_exists($file)) {
            error_log("Register API class - loading: $class_filename");
            require $file;
            return true;
        }
        error_log("Register API class failed: $class_filename");
        return false;
    }
}
Autoloader::register();