<?php
namespace commonlib;

class GW_Debug {

    private function __construct() {
    }

    public static function debug( $obj, $label='' ) {
        global $wp_version;
        global $wp_db_version;
        global $wp;
        global $_REQUEST, $_SERVER, $_GET, $_POST;
        self::display_my_datetime('GW Debug Info');
        $func = get_query_var('gw_vars');
        //echo '<p>WP Version=' . $wp_version . '</p>';
        //echo '<p>DB Version=' . $wp_db_version . '</p>';
        //echo '<p>func=' . $func . '</p>';
        $src = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'Unknown REQUEST_URI';
        echo "<p>REQUEST_URI='$src'</p>";
        $src = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : 'Unknown PATH_INFO';
        echo "<p>PATH_INFO='$src'</p>";
        $src = isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : 'Unknown PHP_SELF';
        echo "<p>PHP_SELF='$src'</p>";

        echo '<div><p>************************************' . $label . '*************************************************</p>';
        echo '<pre>';
        print_r(is_null($obj) ? 'NULL' : $obj);
        echo  '</pre>';
        echo '</div>';
    }

    public static function display_my_datetime($label) {

        date_default_timezone_set("America/Toronto");
        $datetime = date('l F j, Y \a\t g:i:s a');

        list($usec, $sec) = explode(" ", microtime());

        echo "<div><span><strong>$label</strong> $datetime $usec</span></div>";
    }

    public static function get_debug_trace( $frames = 2) {
 
        $trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, $frames + 1 );
        array_shift($trace);

        return $trace;
    }
}
