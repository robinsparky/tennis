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
    
    public static function get_debug_trace_CLI( $frames = 2 ) {
        $trace = self::get_debug_trace( $frames + 1 );
        array_shift($trace); //remove the frame re this function call
        $ctr = 0;
        foreach( $trace as $frame) {
            ++$ctr;
            foreach( $frame as $key => $value) {
                \WP_CLI::Line("Frame#{$ctr}: {$key} = {$value}");
            }
            \WP_CLI::Line('-');
        }
    }
    
    public static function get_debug_trace_Str( $frames = 2 ) {
        $trace = self::get_debug_trace( $frames + 1 );
        array_shift($trace); //remove the frame re this function call
        $res = '***Debug Trace***' . PHP_EOL;
        $ctr = 0;
        foreach( $trace as $frame) {
            ++$ctr;
            foreach( $frame as $key => $value) {
                if( \is_array($value) ) {
                    $value = implode(";", $value);
                }
                $res .= \sprintf("Frame#%d %s = %s%s", $ctr, $key, $value, PHP_EOL);
            }
            $res .= '----' . PHP_EOL;
        }
        return $res;
    }
    
    public static function get_debug_trace_Htm( $frames = 2 ) {
        $trace = self::get_debug_trace( $frames + 1 );
        array_shift($trace); //remove the frame re this function call
        $res = '<h2>***Debug Trace***</h2>' . PHP_EOL;
        $ctr = 0;
        foreach( $trace as $frame) {
            ++$ctr;
            $res .= "<ul class='debug_trace'>Frame {$ctr}" . PHP_EOL;
            foreach( $frame as $key => $value) {
                $res .= \sprintf("<li>%s = %s</li>%s", $key, $value, PHP_EOL);
            }
            $res .= '</ul>' . PHP_EOL;
        }
        return $res;
    }
}
