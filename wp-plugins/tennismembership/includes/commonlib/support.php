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

	public function display_marker($label) {
		date_default_timezone_set("America/Toronto");
		$datetime = date('l F j, Y \a\t g:i:s a');
		list($usec, $sec) = explode(" ", microtime());
		echo "<div><span><strong>$label</strong> $datetime $usec</span></div>";
	}

	/**
	 * Function to render taxonomy/tag links (i.e. as a tags)
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
	 * Function to return array of term names
	 */
	public function tennis_events_get_term_names( $postID, $termname ) {
		$result = [];
		$term_list = wp_get_post_terms( $postID, $termname ); 
		foreach( $term_list as $term ) {
			$result[] = $term->name;
		}
		return $result;
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

	/* User Capabilities Section*/

	/**
	 * Is the current user a Tournament Director
	 */
	public function userIsTournamentDirector() {
		return $this->userIsAdministrator();
	}
	/**
	 * Is the current user a Chair Umpire
	 */
	public function userIsChairUmpire() {
		return $this->userIsAdministrator();
	}

	/**
	 * Is the current user an Administrator
	 */
	public function userIsAdministrator() {
		return is_user_logged_in() && current_user_can( 'manage_options' );
	}

	/**
	 * Is the current user a Player
	 */
	public function userIsPlayer() {
		return false;
	}
}
