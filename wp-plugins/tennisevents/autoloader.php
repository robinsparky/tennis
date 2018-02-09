
<?php
/**
* Simple autoloader, so we don't need Composer just for this.
*/
class Autoloader
{
    public static function register()
    {
        spl_autoload_register(function ($class) {
            $class_filename = __DIR__ . "\\includes\\datalayer\\class-$class" . ".php";
            $file = str_replace('\\', DIRECTORY_SEPARATOR, $class_filename);
            if (file_exists($file)) {
                error_log("Register loading: $class_filename");
                require $file;
                return true;
            }
            //error_log("Register failed: $class_filename");
            return false;
        });
        
        spl_autoload_register(function ($class) {
            $class_filename = __DIR__ . "\\includes\\api\\class-$class" . ".php";
            $file = str_replace('\\', DIRECTORY_SEPARATOR, $class_filename);
            if (file_exists($file)) {
                error_log("Register loading: $class_filename");
                require $file;
                return true;
            }
            //error_log("Register failed: $class_filename");
            return false;
        });
    }
}
Autoloader::register();