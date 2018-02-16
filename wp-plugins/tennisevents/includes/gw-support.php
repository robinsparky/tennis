<?php

$gw_queued_js = '';
/**
 * Queue some JavaScript code to be output in the footer.
 *
 * @param string $code
 */
function gw_enqueue_js( $code ) {
	global $gw_queued_js;

	if ( empty( $gw_queued_js ) ) {
		$gw_queued_js = '';
	}

	$gw_queued_js .= "\n" . $code . "\n";
}

/**
 * Quick function to retrieve the name of the browser (user_agent)
**/
function get_browser_name($user_agent)
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
function is_request( $type ) {
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
function gw_print_js() {
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

add_action('wp_footer','gw_print_js');

function gw_GetCallingMethodName() {
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

function dump(&$var, $info = FALSE)
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

function do_dump(&$var, $var_name = NULL, $indent = NULL, $reference = NULL)
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

function display_marker($label) {
	date_default_timezone_set("America/Toronto");
	$datetime = date('l F j, Y \a\t g:i:s a');
	list($usec, $sec) = explode(" ", microtime());
	echo "<div><span><strong>$label</strong> $datetime $usec</span></div>";
}

class GW_Debug {

	private function __construct() {

	}
	
	public static function debug($obj,$label='') {
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
}

