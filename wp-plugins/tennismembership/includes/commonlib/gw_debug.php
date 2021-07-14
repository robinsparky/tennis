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
    
	public static function gw_GetCallingMethodName() {
		$e = new Exception();
		$trace = $e->getTrace(); // or use debug_trace
		//position 0 would be the line that called this function so we ignore it
		$last_call = $trace[1];
		return $last_call;
	}

	////////////////////////////////////////////////////////
	// Function:         dump
	// Inspired from:     PHP.net Contributions
	// Description: Helps with php debugging

	public static function dump(&$var, $info = FALSE)
	{
		$scope = false;
		$prefix = 'unique';
		$suffix = 'value';

		if($scope) $vals = $scope;
		else $vals = $GLOBALS;

		$old = $var;
		$var = $new = $prefix.rand().$suffix; $vname = FALSE;
		foreach($vals as $key => $val) if($val === $new) $vname = $key;
		$var = $old;

		echo "<pre style='margin: 0px 0px 10px 0px; display: block; background: white; color: black; font-family: Verdana; border: 1px solid #cccccc; padding: 5px; font-size: 10px; line-height: 13px;'>";
		if($info != FALSE) echo "<b style='color: red;'>$info:</b><br>";
		do_dump($var, '$'.$vname);
		echo "</pre>";
	}

	////////////////////////////////////////////////////////
	// Function:         do_dump
	// Inspired from:     PHP.net Contributions
	// Description: Better GI than print_r or var_dump

	public static function do_dump(&$var, $var_name = NULL, $indent = NULL, $reference = NULL)
	{
		$do_dump_indent = "<span style='color:#eeeeee;'>|</span> &nbsp;&nbsp; ";
		$reference = $reference.$var_name;
		$keyvar = 'the_do_dump_recursion_protection_scheme'; $keyname = 'referenced_object_name';

		if (is_array($var) && isset($var[$keyvar]))
		{
			$real_var = &$var[$keyvar];
			$real_name = &$var[$keyname];
			$type = ucfirst(gettype($real_var));
			echo "$indent$var_name <span style='color:#a2a2a2'>$type</span> = <span style='color:#e87800;'>&amp;$real_name</span><br>";
		}
		else
		{
			$var = array($keyvar => $var, $keyname => $reference);
			$avar = &$var[$keyvar];

			$type = ucfirst(gettype($avar));
			if($type == "String") $type_color = "<span style='color:green'>";
			elseif($type == "Integer") $type_color = "<span style='color:red'>";
			elseif($type == "Double"){ $type_color = "<span style='color:#0099c5'>"; $type = "Float"; }
			elseif($type == "Boolean") $type_color = "<span style='color:#92008d'>";
			elseif($type == "NULL") $type_color = "<span style='color:black'>";

			if(is_array($avar))
			{
				$count = count($avar);
				echo "$indent" . ($var_name ? "$var_name => ":"") . "<span style='color:#a2a2a2'>$type ($count)</span><br>$indent(<br>";
				$keys = array_keys($avar);
				foreach($keys as $name)
				{
					$value = &$avar[$name];
					do_dump($value, "['$name']", $indent.$do_dump_indent, $reference);
				}
				echo "$indent)<br>";
			}
			elseif(is_object($avar))
			{
				echo "$indent$var_name <span style='color:#a2a2a2'>$type</span><br>$indent(<br>";
				foreach($avar as $name=>$value) do_dump($value, "$name", $indent.$do_dump_indent, $reference);
				echo "$indent)<br>";
			}
			elseif(is_int($avar)) echo "$indent$var_name = <span style='color:#a2a2a2'>$type(".strlen($avar).")</span> $type_color$avar</span><br>";
			elseif(is_string($avar)) echo "$indent$var_name = <span style='color:#a2a2a2'>$type(".strlen($avar).")</span> $type_color\"$avar\"</span><br>";
			elseif(is_float($avar)) echo "$indent$var_name = <span style='color:#a2a2a2'>$type(".strlen($avar).")</span> $type_color$avar</span><br>";
			elseif(is_bool($avar)) echo "$indent$var_name = <span style='color:#a2a2a2'>$type(".strlen($avar).")</span> $type_color".($avar == 1 ? "TRUE":"FALSE")."</span><br>";
			elseif(is_null($avar)) echo "$indent$var_name = <span style='color:#a2a2a2'>$type(".strlen($avar).")</span> {$type_color}NULL</span><br>";
			else echo "$indent$var_name = <span style='color:#a2a2a2'>$type(".strlen($avar).")</span> $avar<br>";

			$var = $var[$keyvar];
		}
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
    
	public static function time_elapsed( $start ) {

		$string = '';
		$t = array( //suffixes
			'd' => 86400,
			'h' => 3600,
			'm' => 60,
		);
		$end = time();
		$s = abs($end - $start);
		foreach($t as $key => &$val) {
			$$key = floor($s/$val);
			$s -= ($$key*$val);
			$string .= ($$key==0) ? '' : $$key . "$key ";
		}
		return $string . $s. 's';
	}

	public static function micro_time_elapsed( $start ) {
		$ret = 0.0;
		if( isset( $start ) ) {
			$now = microtime(true);
			$ret = $now - $start ;
		}
		return $ret;
	}

	public static function gw_print_mem() {
		/* Currently used memory */
		$mem_usage = memory_get_usage();
		
		/* Peak memory usage */
		$mem_peak = memory_get_peak_usage();
	
		/* Get the memory limit in bytes. */
		$mem_limit = self::gw_get_memory_limit();

		error_log( 'Current usage:' . round($mem_usage / 1024) . 'KB of memory.' );
		error_log( 'Peak usage: ' . round($mem_peak / 1024) . 'KB of memory.' );
		error_log( 'Memory Limit: ' . round($mem_limit / 1048576) . 'MB.');
	}

	public static function gw_generateCallTrace() {
		$e = new Exception();
		$trace = explode("\n", $e->getTraceAsString());
		// reverse array to make steps line up chronologically
		$trace = array_reverse($trace);
		array_shift($trace); // remove {main}
		array_pop($trace); // remove call to this method
		$length = count($trace);
		$result = array();
	
		for ($i = 0; $i < $length; $i++)
		{
			$result[] = ($i + 1)  . ')' . substr($trace[$i], strpos($trace[$i], ' ')); // replace '#someNum' with '$i)', set the right ordering
		}
	
		return "\t" . implode("\n\t", $result);
	}

	public static function gw_shortCallTrace() {
		$trace = debug_backtrace();
		array_shift($trace); // remove {main}
		array_pop($trace); // remove call to this method
		$length = count($trace);
		$result = array();
	
		for ($i = 0; $i < $length; $i++)
		{
			$funName = array_key_exists("function", $trace[$i]) ? $trace[$i]['function'] : '?function?';
			$fileName = array_key_exists("file", $trace[$i]) ? $trace[$i]['file'] : 'unknown';
			$lineNum = array_key_exists( "line", $trace[$i]) ? $trace[$i]['line'] : '?';
			$className = array_key_exists("class", $trace[$i]) ? $trace[$i]['class'] : '';
			$obj = array_key_exists("object", $trace[$i]) ? $trace[$i]['object'] : '';
			$callType = array_key_exists("type", $trace[$i]) ? $trace[$i]['type'] : '.';
			$name = '';
			if( empty( $className ) && empty( $objName ) ) {
				$name = $fileName;
			}
			elseif( empty( $objName ) ) {
				$name = $className;
			}
			else {
				$name = ($obj);
			}
			$result[] = '\t' . ($i + 1)  . ')' . $name . $callType . $funName . '(' . $lineNum . ')';
		}
	
		return implode("\n\t", $result);
	}

	/* Parse the memory_limit variable from the php.ini file. */
	public static function gw_get_memory_limit() {
		$limit_string = ini_get('memory_limit');
		$unit = strtolower(mb_substr($limit_string, -1 ));
		$bytes = intval(mb_substr($limit_string, 0, -1), 10);
		
		switch ($unit)
		{
			case 'k':
				$bytes *= 1024;
				break 1;
			
			case 'm':
				$bytes *= 1048576;
				break 1;
			
			case 'g':
				$bytes *= 1073741824;
				break 1;
			
			default:
				break 1;
		}
		
		return $bytes;
	}

}
