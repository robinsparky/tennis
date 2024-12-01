<?php
/*
	Plugin Name: Tennis Membership
	Plugin URI: grayware.ca/tennismembership
	Description: Tennis Membership Management
	Version: 1.0
	Author: Robin Smith
	Author URI: grayware.ca
*/
use commonlib\GW_Support;
use commonlib\BaseLogger;

// use \WP_CLI;
// use \WP_CLI_Command;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$wpdb->hide_errors(); 

if (isset($tennisMembership) && is_object($tennisMembership) && is_a($tennisMembership, '\TennisMembership') ) return;

/**
 * Main Plugin class.
 *
 * @class TennisMembership
 * @version	1.0.0
*/
class TennisMembership {

	/**
	 * Plugin version
	 * @since   1.0.0
	 * @var     string
	 */
	public const VERSION = '1.0.0';
	
	/**
	 * Unique identifier for the plugin.
	 * The variable name is used as the text domain when internationalizing strings
	 * of text.
	 *
	 * @since    1.0.0
	 * @var      string
	 */
	public const PLUGIN_SLUG = 'tennismembership';
	public const TEXT_DOMAIN = 'tennis_text';

	//This class's singleton
	private static $_instance;

	private $log;

	/**
	 * TennisMembership Singleton
	 *
	 * @since 1.0
	 * @static
	 * @see TM()
	 * @return TennisMembership $_instance --singleton instance.
	 */
	public static function get_instance() {
		if ( is_null( TennisMembership::$_instance ) ) {
			self::$_instance = new self();
		}
		return TennisMembership::$_instance;
	}

	public static function getInstaller() {
		return TM_Install::get_instance();
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
		if ( isset( TennisMembership::$_instance ) ) {
			wp_die( sprintf( esc_html__( '%s is a singleton class and you cannot create a second instance.', 'ten' ),get_class( $this ) ) );
		}
	}

	public function plugin_setup() {
		$this->includes();
		$this->log = new BaseLogger( true );
		$this->setup();
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
		TennisMembership::getInstaller()->activate();
		error_log(">>>>>>>>>>>>>>>>>>>>>>>>>>$loc End>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>");
	}

	static public function on_deactivate() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		error_log(">>>>>>>>>>>>>>>>>>>>>>>>>>$loc Start>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>");
		TennisMembership::getInstaller()->deactivate();
		error_log(">>>>>>>>>>>>>>>>>>>>>>>>>>$loc End>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>");
	}

	static public function on_uninstall() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		error_log(">>>>>>>>>>>>>>>>>>>>>>>>>>$loc Start>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>");
		TennisMembership::getInstaller()->uninstall();
		error_log(">>>>>>>>>>>>>>>>>>>>>>>>>>$loc End>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>");
	}
	
	/**
	 * Init Tennis Events 
	 * 1. Instantiate the installer
	 * 2. Instantiate the Endpoints/routes Controller
	 */
	public function init() {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log( ">>>>>>>>>>>$loc start>>>>>>>>>" );
		//Register various 
		// TennisEventCpt::register();
		// ManageSignup::register();
		// ManageDraw::register();
		// ManageRoundRobin::register();
		$this->log->error_log( "<<<<<<<<<<<$loc end<<<<<<<<<<<" );
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
	
	private function __wakeup() {}
	private function __clone() {}	

}

include_once( 'autoloader.php' );

$tennisMembership = TennisMembership::get_instance();
$GLOBALS['tennisMembership'] = $tennisMembership;
function TM() {
	global $tennisMembership;
	return $tennisMembership;
}

// Register activation/deactivation hooks
register_activation_hook( __FILE__, array( 'TennisMembership', 'on_activate' ) );
register_deactivation_hook ( __FILE__, array( 'TennisMembership', 'on_deactivate' ) );
register_uninstall_hook ( __FILE__, array( 'TennisMembership', 'on_uninstall' ) );
