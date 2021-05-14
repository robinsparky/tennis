<?php
namespace commonlib;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 
 * Class providing support functions
 * @class  GW_Support
 * @package Tennis Common Library
 * @version 1.0.0
 * @since   0.1.0
*/
class GW_Support 
{

	//This class's singleton
	private static $_instance;

	private $gw_queued_js = '';

	/**
	 * GW_Support Singleton
	 *
	 * @since 1.0
	 * @static
	 * @return GW_Support $_instance --singleton instance.
	 */
	public static function getInstance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 *  Constructor.
	 */
	public function __construct() {
		// Don't allow more than one instance of the class
		if ( isset( self::$_instance ) ) {
			wp_die( sprintf( esc_html__( '%s is a singleton class and you cannot create a second instance.', 'ten' ),get_class( $this ) ) );
		}
	}

	/**
	 * Queue some JavaScript code to be output in the footer.
	 *
	 * @param string $code
	 */
	public function gw_enqueue_js( $code ) {
		global $gw_queued_js;

		if ( empty( $gw_queued_js ) ) {
			$gw_queued_js = '';
		}

		$gw_queued_js .= "\n" . $code . "\n";
	}

	/**
	 * Quick function to retrieve the name of the browser (user_agent)
	**/
	public function get_browser_name($user_agent)
	{
		if (strpos($user_agent, 'Opera') || strpos($user_agent, 'OPR/')) return 'Opera';
		elseif (strpos($user_agent, 'Edge')) return 'Edge';
		elseif (strpos($user_agent, 'Chrome')) return 'Chrome';
		elseif (strpos($user_agent, 'Safari')) return 'Safari';
		elseif (strpos($user_agent, 'Firefox')) return 'Firefox';
		elseif (strpos($user_agent, 'MSIE') || strpos($user_agent, 'Trident/7')) return 'Internet Explorer';

		return 'Other';
	}


	/**
	 * What type of request is this?
	 *
	 * @param  string $type admin, ajax, cron or frontend.
	 * @return bool
	 * @verison	1.0.0
	 * @since	1.0.0
	 */
	public function is_request( $type ) {
		switch ( $type ) {
			case 'admin' :
				return is_admin();
			case 'ajax' :
				return defined( 'DOING_AJAX' );
			case 'cron' :
				return defined( 'DOING_CRON' );
			case 'frontend' :
				return ( ! is_admin() || defined( 'DOING_AJAX' ) ) && ! defined( 'DOING_CRON' );
		}
	}

	/**
	 * Output any queued javascript code in the footer.
	 */
	public function gw_print_js() {
		global $gw_queued_js;

		if ( !empty( $gw_queued_js ) ) {
			// Sanitize.
			$gw_queued_js = wp_check_invalid_utf8( $gw_queued_js );
			$gw_queued_js = preg_replace( '/&#(x)?0*(?(1)27|39);?/i', "'", $gw_queued_js );
			$gw_queued_js = str_replace( "\r", '', $gw_queued_js );

			$emit = "<!-- GrayWare Support JavaScript -->\n<script type=\"text/javascript\">\njQuery(function($) { $gw_queued_js });\n</script>\n";

			//dump($emit,'EMIT');
			echo $emit;
			unset( $gw_queued_js );
		}
	}

	//add_action('wp_footer','gw_print_js');

	public function gw_GetCallingMethodName() {
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

	public function dump(&$var, $info = FALSE)
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

	public function do_dump(&$var, $var_name = NULL, $indent = NULL, $reference = NULL)
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

	public function display_marker($label) {
		date_default_timezone_set("America/Toronto");
		$datetime = date('l F j, Y \a\t g:i:s a');
		list($usec, $sec) = explode(" ", microtime());
		echo "<div><span><strong>$label</strong> $datetime $usec</span></div>";
	}

	public function time_elapsed( $start ) {

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

	public function micro_time_elapsed( $start ) {
		$ret = 0.0;
		if( isset( $start ) ) {
			$now = microtime(true);
			$ret = $now - $start ;
		}
		return $ret;
	}

	public function gw_print_mem() {
		/* Currently used memory */
		$mem_usage = memory_get_usage();
		
		/* Peak memory usage */
		$mem_peak = memory_get_peak_usage();
	
		/* Get the memory limit in bytes. */
		$mem_limit = $this->gw_get_memory_limit();

		error_log( 'Current usage:' . round($mem_usage / 1024) . 'KB of memory.' );
		error_log( 'Peak usage: ' . round($mem_peak / 1024) . 'KB of memory.' );
		error_log( 'Memory Limit: ' . round($mem_limit / 1048576) . 'MB.');
	}

	public function gw_generateCallTrace() {
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

	public function gw_shortCallTrace() {
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
	public function gw_get_memory_limit() {
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

	/**
	 * Function to render taxonomy/tag links
	 */
	public function tennis_events_get_term_links( $postID, $termname ) {
		$term_list = wp_get_post_terms( $postID, $termname ); 
		$i = 0;
		$len = count( $term_list );
		foreach( $term_list as $term ) {
			if( $i++ >= 0 && $i < $len) {
				$sep = ',';
			}
			else if( $i >= $len ) {
				$sep = '';
			}
			$lnk = get_term_link( $term );
			if( is_wp_error( $lnk ) ) {
				$mess = $lnk->get_error_message();
				echo "<span>$mess</span>$sep";
			}
			else {
				echo "<a href='$lnk'>$term->name</a>$sep";
			}
		}
	}

	/**
	 * Shuffle for associative arrays
	 */
	public function shuffle_assoc(&$array) {
		$keys = array_keys($array);

		shuffle($keys);

		foreach($keys as $key) {
			$new[$key] = $array[$key];
		}

		$array = $new;

		return true;
	}
}
