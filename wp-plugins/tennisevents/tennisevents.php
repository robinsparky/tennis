<?php
/*
	Plugin Name: Tennis Events
	Plugin URI: grayware.ca/tennisevents
	Description: Tennis Events Management
	Version: 1.0
	Author: Sparky
	Author URI: grayware.ca
*/
use api\CustomMenu;
use commonlib\GW_Support;
use commonlib\BaseLogger;
use cpt\TennisEventCpt;
use cpt\TennisClubCpt;
use api\view\RenderSignup;
use api\view\RenderDraw;
use api\view\RenderRoundRobin;
use api\ajax\ManageSignup;
use api\ajax\ManageDraw;
use api\ajax\ManageRoundRobin;
use api\ajax\ManageEvents;
use api\rest\TennisControllerManager;
use special\PickleballSurvey;

//-----------------Uncomment this to turn off all logging in this plugin----------------
//$GLOBALS['TennisEventNoLog'] = 1;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
//$wpdb->hide_errors();
$wpdb->show_errors(); 

if (isset($tennisEvents) && is_object($tennisEvents) && is_a($tennisEvents, 'TennisEvents') && function_exists('TE')) return;

/**
 * Main Plugin class.
 *
 * @class TennisEvents
 * @version	1.0.0
*/
class TennisEvents {

	/**
	 * Plugin version
	 * @since   1.0.0
	 * @var     string
	 */
	
	public const OPTION_NAME_VERSION = 'tennis_version';
	public const VERSION = '1.0.1';
	public const OPTION_NAME_SEEDED  = 'data_seeded';
	public const OPTION_HISTORY_RETENTION_DEFAULT = 5;
    public const OPTION_HISTORY_RETENTION = 'gw_tennis_event_history';
	public const OPTION_TENNIS_SEASON = 'gw_tennis_event_season';
	public const OPTION_HOME_TENNIS_CLUB = 'gw_tennis_home_club';
	public const OPTION_MINIMUM_LEADTIME = 'gw_tennis_event_min_leadtime';
	public const OPTION_MINIMUM_DURATION_SUGGESTIONS = 'gw_tennis_event_min_days_suggestions';
	public const OPTION_LOCK_PREVIOUS_SEASONS = 'gw_tennis_lock_previous_seasons';
	
	/**
	 * Unique identifier for the plugin.
	 * The variable name is used as the text domain when internationalizing strings
	 * of text.
	 *
	 * @since    1.0.0
	 * @var      string
	 */
	public const PLUGIN_SLUG = 'tennisevents';
	public const TEXT_DOMAIN = 'tennis_text';

	public const ROOT_PAGE_META_KEY = 'gw_tennis_root_page';
	public const EVENT_PAGE_META_KEY = 'gw_tennis_eventid';


	//This class's singleton
	private static $_instance;

	private $log;

	/**
	 * TennisEvents Singleton
	 *
	 * @since 1.0
	 * @static
	 * @see TE()
	 * @return TennisEvents $_instance --singleton instance.
	 */
	public static function get_instance() {
		if ( is_null( TennisEvents::$_instance ) ) {
			self::$_instance = new self();
		}
		return TennisEvents::$_instance;
	}

	public static function getInstaller() {
		return TE_Install::get_instance();
	}

	static public function getControllerManager() {
		return TennisControllerManager::get_instance();
	}

	/**
	 * Get the DateTimeZone object
	 * as set in the WordPress settings
	 */
	static public function getTimeZone() {
		$tz = wp_timezone();
		return $tz;
	}

	/**
	 * Get the end of signup lead time before draw can be made
	 */
	static public function getLeadTime() {
		return esc_attr( get_option(TennisEvents::OPTION_MINIMUM_LEADTIME, 3) );
	}
	
	/**
	 *  Constructor.
	 */
	public function __construct() {
		// Don't allow more than one instance of the class
		if ( isset( TennisEvents::$_instance ) ) {
			wp_die( sprintf( esc_html__( '%s is a singleton class and you cannot create a second instance.', self::TEXT_DOMAIN ),get_class( $this ) ) );
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
		//Temporary test of CF7 filter
		//$this->addCF7Filters();
	}

	/**
	 * Add Contact Form 7 filters
	 * Tests to explore what is possible programmably
	 * You can best capture CF7 posted data using the "save post" action.
	 * See updateTennisDB in the CPT's
	 */
	private function addCF7Filters() {
		/**
		 * Description
		 *
		 * @param $properties
		 * @param $cf7
		 * @return $properties
		 */
		// add_filter('wpcf7_contact_form_properties', function($properties) {
		// 	$this->log->error_log($properties,"wpcf7 Properties");
		// 	return $properties;
		// });
		/**
		 * Filter the form response output
		 *
		 * @param $output 
		 * @param $class 
		 * @param $content 
		 * @param $cf7 
		 * @return @output
		 */
		add_filter('wpcf7_form_response_output', function($output) {
			$this->log->error_log($output,"wpcf7 Output");
			return $output;
		});
		/**
		 * Used to change the form action URL
		 *
		 * @param $url the current URL
		 * @return string The new URL you want
		 */
		add_filter('wpcf7_form_action_url', function($url) {
			$this->log->error_log("wpcf7 Action Url: $url");
			return $url;
		});
		/**
		 * Change de form HTML id value
		 *
		 * @param $html_id The current id value
		 * @return string The new id
		 */
		add_filter('wpcf7_form_id_attr', function($html_id) {
			$this->log->error_log("wpcf7 Id: $html_id");
			return $html_id;
		});
		/**
		 * Change de form HTML name
		 *
		 * @param $html_name The actual name
		 * @return string The new name
		 */
		add_filter('wpcf7_form_name_attr', function($html_name) {
			$this->log->error_log("wpcf7 Id: $html_name");
			return $html_name;
		});
		/**
		 * Change de form class name
		 *
		 * @param $html_class The actual class name
		 * @return string The new class name
		 */
		add_filter('wpcf7_form_class_attr', function($html_class) {
			$this->log->error_log("wpcf7 Id: $html_class");
			return $html_class;
		});
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
		TennisEvents::getInstaller()->activate();
		error_log(">>>>>>>>>>>>>>>>>>>>>>>>>>$loc End>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>");
	}

	static public function on_deactivate() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		error_log(">>>>>>>>>>>>>>>>>>>>>>>>>>$loc Start>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>");
		TennisEvents::getInstaller()->deactivate();
		error_log(">>>>>>>>>>>>>>>>>>>>>>>>>>$loc End>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>");
	}

	static public function on_uninstall() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
		error_log(">>>>>>>>>>>>>>>>>>>>>>>>>>$loc Start>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>");
		TennisEvents::getInstaller()->uninstall();
		error_log(">>>>>>>>>>>>>>>>>>>>>>>>>>$loc End>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>");
	}

	/**
	 * Init Tennis Events 
	 */
	public function init() {
		$loc = __CLASS__ . '::' . __FUNCTION__;
		$this->log->error_log( ">>>>>>>>>>>$loc start>>>>>>>>>" );

		//Register custom post types
		TennisEventCpt::register();
		TennisClubCpt::register();

		//Register the signup
		RenderSignup::register();
		ManageSignup::register();

		//Register Elimination Draws
		RenderDraw::register();
		ManageDraw::register();

		//Register Round Robin Draws
		RenderRoundRobin::register();
		ManageRoundRobin::register();

		//Event and bracket maintenance
		ManageEvents::register();

		//NOTE: Should call addCaps to cover case where admin is added after activation

		flush_rewrite_rules(); //necessary to make permlinks work for tennis templates
		$this->seedData();

		//Test for ImageMagick
		// $image = new Imagick();
		// $image->newImage(1, 1, new ImagickPixel('#ffffff'));
		// $image->setImageFormat('png');
		// $pngData = $image->getImagesBlob();
		// $magicmess = strpos($pngData, "\x89PNG\r\n\x1a\n") === 0 ? 'ImageMagick Ok' : 'ImageMagick Failed';
		// $this->log->error_log("$loc: $magicmess"); 

		$this->log->error_log( "<<<<<<<<<<<$loc end<<<<<<<<<<<" );
	}
	
	/**
	 * Seed the newly created schema with a club custom post type
	 */ 
	public function seedData() {
		$loc = __CLASS__ . '::' . __FUNCTION__;

		if( false === get_option(TennisEvents::OPTION_NAME_SEEDED) ) {
			$post_data = ['post_content'=>'Seeded data for default tennis club'
						,'post_title' => get_bloginfo('name')
						,'post_name' => get_bloginfo('name')
						,'post_type' => TennisClubCpt::CUSTOM_POST_TYPE
						,'post_status' => 'publish'
						,'comment_status' => 'closed'
						,'ping_status' => 'closed'];
			$postId = wp_insert_post($post_data);
			if( is_wp_error($postId) ) {
				throw new Exception($postId->get_error_message());
			}
			if( $postId === 0 ){
				throw new Exception("Failed to seed tennis club");
			}
			update_option(TennisEvents::OPTION_NAME_SEEDED, "yes");
		}
	}
	
	public function getPluginPath() {
		return plugin_dir_path( __FILE__ );
	}

	public function getPluginUrl() {
		return trailingslashit(plugins_url()) . trailingslashit(self::PLUGIN_SLUG);
	}

	/**
	 * Customize the Query for Tennis Event Archives
	 * Only want root events (i.e. no leaf events)
	 * @param object $query 
	 *
	 */
	public function archive_tennisevent_query( $query ) {
		$loc = __FILE__ . '::' . __FUNCTION__;
		
		$post_type = $query->get( 'post_type' );
		//$this->log->error_log("$loc: post_type='{$post_type}'");

		if( $query->is_main_query() && !$query->is_feed() && !is_admin() 
		&& $query->is_post_type_archive( TennisEventCpt::CUSTOM_POST_TYPE ) ) {
			$this->log->error_log("$loc: post_type='{$post_type}' is post type archive!");
			//$this->log->error_log($query, "Query Object Before");
			$query->set('posts_per_page',-1);//Unlimited number of posts
			$query->set('meta_key',TennisEventCpt::START_DATE_META_KEY);
			$query->set('orderby',"meta_value");
			$query->set('type','DATE');
			$query->set('order',"ASC");

			$meta_query = array(
								array(								
									'key' => TennisEventCpt::PARENT_EVENT_META_KEY
									,'compare' => 'NOT EXISTS'
								)
						);

			$query->set( 'meta_query', $meta_query );
			//$this->log->error_log($query, "Query Object After");
		}
	}
		
	private function includes() {
		//include_once( 'includes/class-controller-manager.php' );
		include_once( 'includes/functions-admin-menu.php' );
		include_once( 'includes/tennis-template-loader.php' );

		if ( defined( 'WP_CLI' ) /**&& WP_CLI**/ ) {
			include_once( 'includes/commandline/ClubCommands.php' );
			include_once( 'includes/commandline/EventCommands.php' );
			include_once( 'includes/commandline/CmdlineSupport.php' );
			include_once( 'includes/commandline/EnvironmentCommands.php' );
			include_once( 'includes/commandline/ShowCommands.php' );
			include_once( 'includes/commandline/TournamentCommands.php' );
			include_once( 'includes/commandline/SignupCommands.php' );
		}
	}

	/**
	 * Are previous seasons' events locked from edit?
	 */
	public function lockOldEvents() {
		$opt = get_option(self::OPTION_LOCK_PREVIOUS_SEASONS,'no');
		return 'yes' === $opt;
	}
		
	/**
	 * Check version and run the updater if required.
	 * This check is done on all requests but only runs if the versions do not match.
	 */
	private function check_version() {
		$loc = __FILE__ . '::' . __FUNCTION__;
		$this->log->error_log("{$loc}");
		if ( get_option( self::OPTION_NAME_VERSION ) !== TennisEvents::VERSION ) {
			try {
				$version = TennisEvents::VERSION;
				$path = $this->getPluginPath() . 'versions\\' . "{$version}" . '\versionUpgrader.php';
				$path = str_replace( '\\', DIRECTORY_SEPARATOR, $path );
				if(file_exists($path)) {
					require $path;
					update_option( self::OPTION_NAME_VERSION , TennisEvents::VERSION );
				}
			}
			catch(Exception | WP_Error $ex) {
				$mess = "???";
				if(is_wp_error($ex)) {
					$mess = $ex->get_error_message();
				}
				else {
					$mess = $ex->getMessage();
				}
				$this->log->error_log("{$loc}: {$mess}");
				wp_die($mess);
			}
		}
	}
	 
	/**
	 * Setup this plugin
	 * @since  1.0
	 */
	private function setup() {
		$loc = __CLASS__ . "::" . __FUNCTION__;
		$this->log->error_log("<<<<<<<<<<<$loc start<<<<<<<<<<<");
		
		//Set the default time zone
		//date_default_timezone_set(ini_get('date.timezone'));
		$tz = wp_timezone_string();
		$this->log->error_log("$loc: time zone={$tz}");
		//$tz = wp_timezone();

        // Add actions
		add_action( 'init', array( $this, 'init') );
		//add_action( 'rest_api_init', array( self::getControllerManager(), 'register_tennis_rest_routes' ) );
		add_action( 'admin_enqueue_scripts', array( $this,'enqueue_admin') );
		add_action( 'pre_get_posts', array( $this, 'archive_tennisevent_query' ) );
				
		$this->log->error_log("<<<<<<<<<<<$loc end<<<<<<<<<<<");
	}   
	
	public function __wakeup() {}
	public function __clone() {}



	private function inspectMenus( $title ) {
		$loc = __CLASS__ . ":" . __FUNCTION__;
		
		//Long version
		// error_log("$loc: Long version+++++++++++++++++++++++");
		// $navMenus = get_registered_nav_menus(); // returns global $_wp_registered_nav_menus;
		// error_log( "$loc: navMenus (from get_registered_nav_menus):");
		// error_log( print_r($navMenus, true ) );

		// $menuLocations = get_nav_menu_locations();
		// error_log("$loc: menu locations:");
		// foreach( $menuLocations as $location ) {
		// 	error_log("$loc: location=$location");
		// 	$menuName = wp_get_nav_menu_name( $location );
		// 	error_log("$loc: menuName=$menuName");
		// }

		// $navMenus = wp_get_nav_menus();
		// error_log("$loc: navMenus (from wp_get_nav_menus)");
		// foreach( $navMenus as $navMenu ) {
		// 	error_log("$loc: navMenu...");
		// 	error_log(print_r($navMenu, true));
		// 	$menuItems = wp_get_nav_menu_items( $navMenu );
		// 	error_log("$loc: menuItems...");
		// 	error_log( print_r( $menuItems, true ) );
		// }

		//Short version
		$this->log->error_log($title);

		$navMenus = wp_get_nav_menus();
		foreach( $navMenus as $navMenu ) {
			$this->log->error_log( $navMenu, "$loc: navMenu ..." );

			$menuItems = wp_get_nav_menu_items($navMenu->slug);
			$menu_lists = [];
	
			foreach($menuItems as $menuItem) {
				if($menuItem->menu_item_parent === "0"){            
					$menu_lists[$menuItem->ID][] = [$menuItem->title, $menuItem->url];
				}else{
					$menu_lists[$menuItem->menu_item_parent][] = [$menuItem->title, $menuItem->url];
				}   
			}

			$this->log->error_log( $menu_lists, "$loc: menu lists from '{$navMenu->slug}'..." );
		}
	}
}

/**
 * Download a file based on a moniker which identifies 
 * the enumeration of allowable files to download
 */
function tennis_send_file( $moniker = 'signupschema') {

	$path = '';
	switch($moniker) {
		case 'signupschema':
			$path = wp_normalize_path(plugin_dir_path( __FILE__ ) . "{$moniker}.xsd");
			break;
	}
	if(empty($path)) wp_die("Invalid file!");

	$info = pathinfo($path);
	error_log("download file ...  " . print_r($info,true));

	$file_extension = pathinfo($path)['extension'];
	$file_name = pathinfo($path)['filename'];
	$basename = pathinfo($path)['basename'];
	$dirname = pathinfo($path)['dirname'];
	

	if( strpos($path,"tennisevents") == FALSE ) {
		wp_die("Invalid tennisevents file!");
	}

	if(in_array(strtolower($file_extension),['php','html','css','js'])) {
		wp_die("Invalid extension!");
	}
	
	$theFile = fopen($path,"r") or wp_die("Unable to open {$path}");
	if( FALSE == $theFile ) {
		wp_die("Could not open the file.");
	}

	$content_type = "";
	//check filetype
	switch( $file_extension ) {
		case "png": 
			$content_type="image/png"; 
			break;
		case "gif": 
			$content_type="image/gif"; 
			break;
		case "tiff": 
			$content_type="image/tiff"; 
			break;
		case "jpeg":
		case "jpg": 
			$content_type="image/jpg"; 
			break;
		case "xsd": 
			$content_type="text/xsd"; 
			break;
			case "xml": 
			$content_type="text/xml"; 
			break;	
		default: 
			$content_type="application/force-download";
	}

	header("Expires: 0");
	header("Cache-Control: no-cache, no-store, must-revalidate"); 
	header('Cache-Control: pre-check=0, post-check=0, max-age=0', false); 
	header("Pragma: no-cache");	
	header("Content-type: {$content_type}");
	header("Content-Disposition:attachment; filename={$basename}");
	header("Content-Type: application/force-download");
    header('Content-Length: ' . filesize($path));

	readfile($path);
	exit();
}

// Start the download if there is a request for it
function tennis_download_request(){
   
	if( isset( $_GET["moniker"] ) ) {
		  tennis_send_file($_GET["moniker"]);
	  }
  }

include( 'autoloader.php' );//Need to include otherwise otherwise BaseLogger cannot be found (i.e. it is under 'includes')

$tennisEvents = TennisEvents::get_instance();
$GLOBALS['tennisEvents'] = $tennisEvents;
function TE() {
	global $tennisEvents;
	return $tennisEvents;
}

//Add init action hooks which are outside the normal functioning of the plugin
add_action('init','tennis_download_request');

// Register this plugin's	 hooks
register_activation_hook( __FILE__, array( 'TennisEvents', 'on_activate' ) );
register_deactivation_hook ( __FILE__, array( 'TennisEvents', 'on_deactivate' ) );
register_uninstall_hook ( __FILE__, array( 'TennisEvents', 'on_uninstall' ) );
add_action(	'plugins_loaded', array ( $tennisEvents, 'plugin_setup' ) );

function tl_save_error() {
	update_option('plugin_error', '');
    update_option( 'plugin_error',  ob_get_contents() );
}

//add_action( 'activated_plugin', 'tl_save_error' );

function handleExtraChars() {
	$extra = get_option( 'plugin_error' );
	if(strlen($extra) > 0 ) {
		error_log('+++++++++++++++++++++Start Extra Chars++++++++++++++++++++++++++++++++++');
		error_log( $extra );
		error_log('+++++++++++++++++++++End Extra Chars++++++++++++++++++++++++++++++++++');
		//echo $extra;
	}
}

//handleExtraChars();


//gc_enable();

//Temporary functionality for Pickleball
add_action('admin_menu', 'create_tools_submenu');
function create_tools_submenu() {
    add_management_page( 'Pickleball', 'Pickleball Survey', 'manage_options', 'pickleball', 'generate_page_survey_content' );
}

function generate_page_survey_content() {

	$psurvey = new PickleballSurvey();
	$psurvey->run();

	echo '<div>';
	echo $psurvey->getSurvey();
	echo '</div>';
	
}

//Temporary functionality for roles & caps
add_action('admin_menu', 'create_tools_submenu2');
function create_tools_submenu2() {
    add_management_page( 'Roles & Caps', 'Roles & Caps', 'manage_options', 'customroles', 'generate_page_role_content' );
}
function generate_page_role_content() {

    $blog = get_bloginfo("name");
	$blogId = get_current_blog_id();
    $custom_cap = 'score_matches';
	//print_r($GLOBALS['wp_roles'] );

    $html = "<hr /><table id={$blogId}>";
    $html .= "<caption>Roles in '{$blog}' ID={$blogId} </caption>";
    $html .= "<thead><tr><th>Role Name</th><th>Capabilties</th></tr></thead><tbody>";
	$ro = $GLOBALS['wp_roles'];
	$roles = $ro->roles;
    foreach ( $roles as $name => $role_obj )
    {
		$stuff = print_r($role_obj, true);
        // $cap = in_array( $custom_cap, $role_obj->caps ) ? $custom_cap : 'n/a';
        // $cap = $cap OR in_array( $custom_cap, $role_obj->allcaps ) ? $custom_cap : 'n/a';
		$cap = 'n/a';
        $html .= "<tr><td>{$name}</td><td>{$stuff}</td></tr>";
    }
    $html .= '</tbody></table>';

	echo $html;
}

//5.8 gutenberg fails totally except in Chrome when using localhost
//add_filter( 'use_block_editor_for_post', '__return_false' );