<?php
/*
	Plugin Name: Tennis Club Membership
	Plugin URI: grayware.ca/tennisclubmembership
	Description: Tennis Club Membership Management
	Version: 1.0
	Author: Robin Smith
	Author URI: grayware.ca
*/
use commonlib\GW_Support;
use commonlib\BaseLogger;
use datalayer\MembershipType;
use datalayer\MembershipSuperType;
use cpt\TennisClubRegistrationCpt;

// use \WP_CLI;
// use \WP_CLI_Command;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$wpdb->hide_errors(); 

if (isset($tennisClubMembership) && is_object($tennisClubMembership) && is_a($tennisClubMembership, '\TennisClubMembership') ) return;

/**
 * Main Plugin class.
 *
 * @class TennisClubMembership
 * @version	1.0.0
*/
class TennisClubMembership {

	/**
	 * Plugin version
	 * @since   1.0.0
	 * @var     string
	 */
	public const VERSION = '1.0.0';
	const OPTION_NAME_VERSION = 'tennisclubmember_version';
	public const OPTION_TENNIS_SEASON = 'gw_tennis_event_season';
	public const OPTION_NAME_SEEDED  = 'clubmembership_data_seeded';

	public const PIVOT = "Player";
	public static $initialSuperTypes = [self::PIVOT,"NonPlayer"];
	public static $initialPlayerTypes = ["Adult","Couples","Family","Student","Junior"];
	public static $initialNonPlayerTypes = ["Public","Parent","Staff","Instructor"];

	public const INITIALMEMBERSHIPTYPES = ["Player"=>["Adult","Couples","Family","Student","Junior"]
	                                      ,"NonPlayer"=> ["Public","Parent","Staff","Instructor"]
                                          ];

	/**
	 * Unique identifier for the plugin.
	 * The variable name is used as the text domain when internationalizing strings
	 * of text.
	 *
	 * @since    1.0.0
	 * @var      string
	 */
	public const PLUGIN_SLUG = 'tennisclubmembership';
	public const TEXT_DOMAIN = 'tennisclubmembership_text';

    //Date and time output formats
    public static $outdatetimeformat1 = "Y-m-d G:i"; 
    public static $outdatetimeformat2 = "Y-m-d g:i a"; 

    //Date only output formats
    public static $outdateformat = "Y-m-d";

    //Time only output formats
    public static $outtimeformat1 = "G:i"; 
    public static $outtimeformat2 = "g:i a";

	//This class's singleton
	private static $_instance;

	private $log;

	/**
	 * TennisClubMembership Singleton
	 *
	 * @since 1.0
	 * @static
	 * @see TM()
	 * @return TennisClubMembership $_instance --singleton instance.
	 */
	public static function get_instance() {
		if ( is_null( TennisClubMembership::$_instance ) ) {
			self::$_instance = new self();
		}
		return TennisClubMembership::$_instance;
	}

	public static function getInstaller() {
		return TM_Install::get_instance();
	}
	
	/**
	 * Get the DateTimeZone object
	 * as set in the WordPress settings
	 */
	static public function getTimeZone() {
		$tz = wp_timezone();
		return $tz;
	}

	// static public function getControllerManager() {
	// 	include_once( 'includes/class-controller-manager.php' );
	// 	return TennisControllerManager::get_instance();
	// }
	
	/**
	 *  Constructor.
	 */
	public function __construct() {
		// Don't allow more than one instance of the class
		if ( isset( TennisClubMembership::$_instance ) ) {
			wp_die( sprintf( esc_html__( '%s is a singleton class and you cannot create a second instance.', 'ten' ),get_class( $this ) ) );
		}
	}

	public function plugin_setup() {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		$this->includes();
		$this->log = new BaseLogger( true );
		$this->log->error_log("$loc: created logger!");
		$support = new GW_Support();
		$this->log->error_log("$loc: created GW Support!");
		$this->check_version();
		$this->setup();
	}

	public function enqueue_admin( $hook ) {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		// Only add to the admin.php admin page.
		// See WP docs.
		if ('toplevel_page_gwtennissettings' !== $hook) {
			return;
		}
		$jsUrl = plugin_dir_url(__FILE__) . 'js/tennisadmin.js';
		$this->log->error_log("$loc: $jsUrl");
		wp_enqueue_script('gw_tennis_admin_script', $jsUrl, array('jquery'));
	}

	static public function on_activate() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		error_log(">>>>>>>>>>>>>>>>>>>>>>>>>>$loc Start>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>");
		
		$incpath = __DIR__ . "/includes";
		self::filePerms($incpath);
		$datalayerpath = __DIR__ . "/includes/datalayer";
		self::filePerms($datalayerpath);
		$exceptpath = __DIR__ . "/includes/datalayer/appexceptions";
		self::filePerms($exceptpath);
		$phpfile = __DIR__ . "/includes/datalayer/appexceptions/InvalidAddressException.php";
		self::filePerms($phpfile);

		TennisClubMembership::getInstaller()->activate();
		error_log(">>>>>>>>>>>>>>>>>>>>>>>>>>$loc End>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>");
	}

	static public function on_deactivate() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		error_log(">>>>>>>>>>>>>>>>>>>>>>>>>>$loc Start>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>");
		TennisClubMembership::getInstaller()->deactivate();
		error_log(">>>>>>>>>>>>>>>>>>>>>>>>>>$loc End>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>");
	}

	static public function on_uninstall() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		error_log(">>>>>>>>>>>>>>>>>>>>>>>>>>$loc Start>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>");
		TennisClubMembership::getInstaller()->uninstall();
		error_log(">>>>>>>>>>>>>>>>>>>>>>>>>>$loc End>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>");
	}

	static public function filePerms(string $filePath) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		if(!file_exists($filePath)) return;

		error_log("$loc: Permissions for {$filePath}");

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

		error_log("---------> perms $info");
	}
	
	/**
	 * Init Club Membership 
	 * 1. Instantiate the installer
	 * 2. Instantiate the Endpoints/routes Controller
	 */
	public function init() {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log( ">>>>>>>>>>>$loc start>>>>>>>>>" );
		//Register various 
		TennisClubRegistrationCpt::register();
		// ManageSignup::register();
		// ManageDraw::register();
		// ManageRoundRobin::register();
		flush_rewrite_rules(); //necessary to make permlinks work for tennis templates
		$this->seedData();
		$this->log->error_log( "<<<<<<<<<<<$loc end<<<<<<<<<<<" );
	}

	
	/**
	 * Seed the newly created schema with a initial membership types
	 */ 
	public function seedData() {
		$loc = __CLASS__ . '::' . __FUNCTION__;

		$result = 0;
		if( false === get_option(TennisClubMembership::OPTION_NAME_SEEDED) ) {
			foreach(array_keys(self::INITIALMEMBERSHIPTYPES) as $super) {
				$superType = MembershipSuperType::fromData($super);
				$result = $superType->save();
				$supId = $superType->getID();
				foreach(self::INITIALMEMBERSHIPTYPES[$super] as $mbrship) {
					$memType = MembershipType::fromData($supId,$mbrship);
					$result += $memType->save();
				}
			}
			update_option(TennisClubMembership::OPTION_NAME_SEEDED, "yes");
		}
		return $result;
	}
	
	public function getPluginPath() {
		return plugin_dir_path( __FILE__ );
	}

	public function getPluginUrl() {
		return trailingslashit(plugins_url()) . trailingslashit(self::PLUGIN_SLUG);
	}

	/**
	 * Customize the Query for Tennis Membership Archives
	 * @param object $query 
	 *
	 */
	public function archive_tennismembership_query( $query ) {
		$loc = __FILE__ . '::' . __FUNCTION__;
		$this->log->error_log("$loc");
		
		// if( $query->is_main_query() && !$query->is_feed() && !is_admin() 
		// && $query->is_post_type_archive( TennisEventCpt::CUSTOM_POST_TYPE ) ) {
		// 	//$this->log->error_log($query, "Query Object Before");
		// 	$meta_query = array( 
		// 						array(
		// 							'key' => TennisEventCpt::PARENT_EVENT_META_KEY
		// 							,'compare' => 'NOT EXISTS'
		// 						)
		// 				);

		// 	$query->set( 'meta_query', $meta_query );
		// 	//$this->log->error_log($query, "Query Object After");
		// }
	}
		
	private function includes() {
		//include_once( 'includes/class-controller-manager.php' );
		//include_once( 'includes/class-tennis-install.php' );
		// include_once( 'includes/functions-admin-menu.php' );
		// include_once( 'includes/tennis-template-loader.php' );

		// if ( defined( 'WP_CLI' ) && WP_CLI ) {
		// 	include_once( 'includes/commandline/class-clubcommands.php' );
		// 	include_once( 'includes/commandline/class-eventcommands.php' );
		// 	include_once( 'includes/commandline/class-cmdlinesupport.php' );
		// 	include_once( 'includes/commandline/class-environmentcommands.php' );
		// 	include_once( 'includes/commandline/class-showcommands.php' );
		// 	include_once( 'includes/commandline/class-tournamentcommands.php' );
		// 	include_once( 'includes/commandline/class-signupcommands.php' );
		// }
	}
			
	/**
	 * Check version and run the updater if required.
	 * This check is done on all requests and runs if the versions do not match.
	 */
	private function check_version() {
		if ( get_option( self::OPTION_NAME_VERSION ) !== TennisClubMembership::VERSION ) {
			//TODO: inlcude a file to perform the upgrade to this plugin
			update_option( self::OPTION_NAME_VERSION , TennisClubMembership::VERSION );
		}
	}

	/**
	 * Setup this plugin
	 * @since  1.0
	 */
	private function setup() {

        // Add actions
		add_action( 'init', array( $this, 'init') );
		// add_action( 'rest_api_init', array( self::getControllerManager(), 'register_tennis_rest_routes' ) );
		add_action( 'admin_enqueue_scripts', array( $this,'enqueue_admin') );
		add_action( 'pre_get_posts', array( $this, 'archive_tennismembership_query' ) );
		
	}   
	
	// private function __wakeup() {}
	// private function __clone() {}	

}

include_once( 'autoloaderm.php' );

$tennisClubMembership = TennisClubMembership::get_instance();
$GLOBALS['tennisClubMembership'] = $tennisClubMembership;
function TM() {
	global $tennisClubMembership;
	return $tennisClubMembership;
}

// Register activation/deactivation hooks
register_activation_hook( __FILE__, array( 'TennisClubMembership', 'on_activate' ) );
register_deactivation_hook ( __FILE__, array( 'TennisClubMembership', 'on_deactivate' ) );
register_uninstall_hook ( __FILE__, array( 'TennisClubMembership', 'on_uninstall' ) );
add_action(	'plugins_loaded', array ( $tennisClubMembership, 'plugin_setup' ) );
