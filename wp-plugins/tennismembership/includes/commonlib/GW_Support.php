<?php
namespace commonlib;

use WP_Error;
use DateTime;
use DateInterval;
use WP_User;
use WP_User_Query;
use datalayer\Person;
use cpt\ClubMembershipCpt;
use cpt\TennisMemberCpt;
use datalayer\MemberRegistration;

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
     * Given the size of the draw (or any integer), calculate the highest 
     * power of 2 which is greater than or equal to that size (or integer)
     * @param int $size 
     * @param int $upper The upper limit of the search; default is 8
     * @return int The exponent if found; zero otherwise
     */
	public static function calculateExponent( int $size, $upper = 8 ) {
        $exponent = 0;
        foreach( range( 1, $upper ) as $exp ) {
            if( pow( 2, $exp ) >= $size ) {
                $exponent = $exp;
                break;
            }
        }
        return $exponent;
    }
	
	/**
     * Determine if this integer is a power of 2
     * @param $size 
     * @param $upper The upper limit of the search; default is 8
     * @return The exponent if found; zero otherwise
     */
	public static function isPowerOf2( int $size, $upper = 8 ) {
        $exponent = 0;
        foreach( range( 1, $upper ) as $exp ) {
            if( pow( 2, $exp ) === $size ) {
                $exponent = $exp;
                break;
            }
        }
        return $exponent;
    }

	public static function getUserByEmail(string $email = '') : ?WP_User {

		$ret_user = null;
		$args = ["user_email"=>$email];
		$user_query = new WP_User_Query($args);
		if( ! empty($user_query->get_results())) {
			foreach( $user_query->get_results() as $user) {
				if($email === $user->user_email) {
					$ret_user = $user;
					break;
				}
			}
		}
		return $ret_user;
	}
	
	public static function getRegLink(Person $person) : string {
		$user = GW_Support::getUserByEmail($person->getHomeEmail());
		$reglink = get_bloginfo('url') . '/' . ClubMembershipCpt::CLUBMEMBERSHIP_SLUG . '/?user_id=' . $user->ID;
		return $reglink;
	}

	public static function getHomeLink(Person $person) : string {
		$user = GW_Support::getUserByEmail($person->getHomeEmail());
		$homelink = get_bloginfo('url') . '/' . TennisMemberCpt::CUSTOM_POST_TYPE_SLUG . '/'  . $user->user_login;
		return $homelink;
	}

	public static function getPostId(Person | MemberRegistration $thing) : int {
		$postId = $thing->getExtRefSingle();
		return (int)$postId;
	}

	public static function log(mixed $something) {
		error_log(print_r($something,true));
	}

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
	public function get_browser_name($user_agent) {
		if (strpos($user_agent, 'Opera') || strpos($user_agent, 'OPR/')) return 'Opera';
		elseif (strpos($user_agent, 'Edge')) return 'Edge';
		elseif (strpos($user_agent, 'Chrome')) return 'Chrome';
		elseif (strpos($user_agent, 'Safari')) return 'Safari';
		elseif (strpos($user_agent, 'Firefox')) return 'Firefox';
		elseif (strpos($user_agent, 'MSIE') || strpos($user_agent, 'Trident/7')) return 'Internet Explorer';

		return 'Other';
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

	/**
	 * Get Unix-style permissions for a file or directory
	 */
	public function filePerms(string $filePath) : string | WP_Error {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		if(!file_exists($filePath)) {
			$err = new WP_Error("$loc: file '{$filePath}' does not exist.");
			return $err;
		}

		$perms = fileperms($filePath);
		$octal=substr(sprintf('%o', $perms), -4);
		error_log("---------> octal $octal");
		
		switch ($perms & 0xF000) {
			case 0xC000: // socket
				$info = 's';
				break;
			case 0xA000: // symbolic link
				$info = 'l';
				break;
			case 0x8000: // regular
				$info = 'r';
				break;
			case 0x6000: // block special
				$info = 'b';
				break;
			case 0x4000: // directory
				$info = 'd';
				break;
			case 0x2000: // character special
				$info = 'c';
				break;
			case 0x1000: // FIFO pipe
				$info = 'p';
				break;
			default: // unknown
				$info = 'u';
		}
		// Owner
		$info .= (($perms & 0x0100) ? 'r' : '-');
		$info .= (($perms & 0x0080) ? 'w' : '-');
		$info .= (($perms & 0x0040) ?
					(($perms & 0x0800) ? 's' : 'x' ) :
					(($perms & 0x0800) ? 'S' : '-'));

		// Group
		$info .= (($perms & 0x0020) ? 'r' : '-');
		$info .= (($perms & 0x0010) ? 'w' : '-');
		$info .= (($perms & 0x0008) ?
					(($perms & 0x0400) ? 's' : 'x' ) :
					(($perms & 0x0400) ? 'S' : '-'));

		// World
		$info .= (($perms & 0x0004) ? 'r' : '-');
		$info .= (($perms & 0x0002) ? 'w' : '-');
		$info .= (($perms & 0x0001) ?
					(($perms & 0x0200) ? 't' : 'x' ) :
					(($perms & 0x0200) ? 'T' : '-'));

		return $info;
	}

	
    /**
    * Determines the interval in days to the end of the month in the given date
    * @param DateTime $initDate
    * @return DateInterval
    */
    public function getInterval( DateTime $initDate ) : DateInterval {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        
        $month = +$initDate->format("n");
        $numDays = 31;
        switch($month) {
            case 2:
                $year = +$initDate->format('Y');
                $isLeap = ($year % 4 === 0) ? true : false;
                $numDays = $isLeap ? 29 : 28;
                break;
            case 4:
            case 6:
            case 9:
            case 11;
                $numDays = 30;
                break;
            case 1:
            case 3:
            case 5:
            case 7:
            case 8:
            case 10:
            case 12:
            default:
                $numDays = 31;
        }
        $interval = new DateInterval("P{$numDays}D");
        return $interval;
    }

    /**
     * Get the last day of the month found in the given date
     * @param DateTime $initDate
     * @return int The last day of the month
     */
    public function lastDayOfMonth( DateTime $initDate ) : int {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        
        $month = +$initDate->format("n");
        $lastDay = 31;
        switch($month) {
            case 2:
                $year = +$initDate->format('Y');
                $isLeap = ($year % 4 === 0) ? true : false;
                $lastDay = $isLeap ? 29 : 28;
                break;
            case 4:
            case 6:
            case 9:
            case 11;
                $lastDay = 30;
                break;
            case 1:
            case 3:
            case 5:
            case 7:
            case 8:
            case 10:
            case 12:
            default:
                $lastDay = 31;
        }
        return $lastDay;
    }
}
