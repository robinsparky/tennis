<?php
/*
	Plugin Name: Tennis Club Membership
	Plugin URI: grayware.ca/tennisclubmembership
	Description: Tennis Club Membership Management
	Version: 1.0
	Author: Robin Smith
	Author URI: grayware.ca
*/

use api\view\RenderRegistrations;
use api\ajax\ManageRegistrations;
use api\ajax\ManagePeople;
use api\view\RenderPeople;
use commonlib\GW_Support;
use commonlib\BaseLogger;
use cpt\ClubMembershipCpt;
use cpt\TennisMemberCpt;
use datalayer\Corporation;
use datalayer\MembershipType;
use datalayer\MembershipCategory;

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
	public const OPTION_TENNIS_SEASON = 'gw_tennis_event_season';
	public const OPTION_NAME_SEEDED  = 'clubmembership_data_seeded';
	public const OPTION_HOME_CORPORATION = 'clubmembership_home_corp';

	public const QUERY_PARM_CORPORATEID = 'corpid';
	public const PIVOT = "Player";
	public static $initialSuperTypes = [self::PIVOT,"NonPlayer"];
	public static $initialPlayerTypes = ["Adult","Couples","Family","Student","Junior"];
	public static $initialNonPlayerTypes = ["Public","Parent","Staff","Instructor"];

	public const INITIALMEMBERSHIPTYPES = ["Player"=>["Adult","Couples","Family","Student","Junior"]
	                                      ,"NonPlayer"=> ["Public","Parent","Staff","Instructor"]
                                          ];

	const OPTION_NAME_VERSION = 'tennisclubmember_version';

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
	
	/**
	 * Init Tennis Club Membership 
	 */
	public function init() {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log( ">>>>>>>>>>>$loc start>>>>>>>>>" );

		//Register custom post types 
		ClubMembershipCpt::register();
		TennisMemberCpt::register();

		//Register Membership Registrations
		RenderRegistrations::register();
		ManageRegistrations::register();

		//Register People as Users
		RenderPeople::register();
		ManagePeople::register();

		flush_rewrite_rules(); //necessary to make permlinks work for clubmembership templates
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
			$corp = Corporation::fromData("Tyandaga Ltd.");
			$corp->save();
			update_option(TennisClubMembership::OPTION_HOME_CORPORATION, $corp->getID());
			foreach(array_keys(self::INITIALMEMBERSHIPTYPES) as $cat) {
				$catType = MembershipCategory::fromData($cat,$corp->getID());
				$result = $catType->save();
				$catId = $catType->getID();
				foreach(self::INITIALMEMBERSHIPTYPES[$cat] as $mbrship) {
					$memType = MembershipType::fromData($catId,$mbrship);
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
	 * Retrieve the season
	 * If not in the URL then retrieved from options
	 * @return season
	 */
	 public function getSeason() {
		 $loc = __CLASS__. "::" .__FUNCTION__;
		 $this->log->error_log("$loc");
		 
		 $seasonDefault = esc_attr( get_option(TennisClubMembership::OPTION_TENNIS_SEASON, date('Y') ) ); 
		 $season = isset($_GET['season']) ? $_GET['season'] : '';
		 if(empty($season)) {
			 $season = $seasonDefault;
			 $this->log->error_log("$loc: Using default season='{$seasonDefault}'");
		 }
		 else {
			$this->log->error_log("$loc: Using posted season='{$season}'");    
		 }
		 return $season;
	 }
 
	 
	/**
	 * Retrieve the Corporation ID
	 * If not in the URL then retrieved from options
	 * @return int Corporate Id
	 */
	public function getCorporationId() {
		$loc = __CLASS__. "::" .__FUNCTION__;
		$this->log->error_log("$loc");
		
		$corpDefault = esc_attr( get_option(TennisClubMembership::OPTION_HOME_CORPORATION), 1 ); 
		$corp = isset($_GET[self::QUERY_PARM_CORPORATEID]) ? $_GET[self::QUERY_PARM_CORPORATEID] : '';
		if(empty($corp)) {
			$corp = $corpDefault;
			$this->log->error_log("$loc: Using default corporation='{$corpDefault}'");
		}
		else {
		   $this->log->error_log("$loc: Using posted corporation='{$corp}'");    
		}
		return $corp;
	}


	/**
	 * Customize the Query for Tennis Membership Archives
	 * @param object $query 
	 *
	 */
	public function archive_tennismembership_query( $query ) {
		$loc = __FILE__ . '::' . __FUNCTION__;
		$this->log->error_log("$loc");
		
		if( $query->is_main_query() && !$query->is_feed() && !is_admin() ) {
			if($query->is_post_type_archive( ClubMembershipCpt::CUSTOM_POST_TYPE ) ) {
				//$this->log->error_log($query, "Registration Query Object Before");
				$season = get_query_var('season','');
				if(!empty($season)) {
					$meta_query = array( 
										array(
											'key' => ClubMembershipCpt::MEMBERSHIP_SEASON
											,'value' => $season
											,'compare' => '='
										)
								);

					$query->set( 'meta_query', $meta_query );
				}
				$query->set("meta_key",ClubMembershipCpt::REGISTRATION_ID);  
				$query->set("orderby",'meta_value_num');
				$query->set("order","ASC");
				$query->set("posts_per_page",15);
				//$this->log->error_log($query, "Registration Query Object After");
			}
			elseif($query->is_post_type_archive( TennisMemberCpt::CUSTOM_POST_TYPE ) ) {
				$this->log->error_log($query, "Member Query Object Before");

				$query->set("meta_key",ManagePeople::USER_PERSON_ID);  
				$query->set("orderby",'meta_value_num');
				$query->set("order","ASC");
				$query->set("posts_per_page",15);
				//$this->log->error_log($query, "Member Query Object After");
			}
		}
	}
		
	private function includes() {
		//include_once( 'includes/class-controller-manager.php' );
		//include_once( 'includes/class-tennis-install.php' );
		// include_once( 'includes/functions-admin-menu.php' );
		include_once( 'includes/clubmembership-template-loader.php' );

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
