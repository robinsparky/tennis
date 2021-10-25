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
use commonlib\BaseLogger;
use cpt\TennisEventCpt;
use cpt\TennisClubCpt;
use api\RenderSignup;
use api\ajax\ManageSignup;
use api\RenderDraw;
use api\ajax\ManageDraw;
use api\RenderRoundRobin;
use api\ajax\ManageRoundRobin;
use api\ajax\ManageBrackets;
use api\rest\TennisControllerManager;
use special\PickleballSurvey;

//Uncomment this to turn off all logging in this plugin
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
	public const VERSION = '1.0.0';
	public const OPTION_NAME_SEEDED  = 'data_seeded';
	
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

	
	// Aspects: namely -> main signup,consolation signup, main matches, consolation matches, main draw, consolation draw
	//private $aspects;

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
		include_once( 'includes/class-tennis-install.php' );
		return TE_Install::get_instance();
	}

	static public function getControllerManager() {
		//include_once( 'includes/class-controller-manager.php' );
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
	 *  Constructor.
	 */
	public function __construct() {
		// Don't allow more than one instance of the class
		if ( isset( TennisEvents::$_instance ) ) {
			wp_die( sprintf( esc_html__( '%s is a singleton class and you cannot create a second instance.', 'ten' ),get_class( $this ) ) );
		}
	}

	public function plugin_setup() {
		$this->includes();
		$this->log = new BaseLogger( true );//Must come after includes
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
	
	private function includes() {
		//include_once( 'includes/class-controller-manager.php' );
		//include_once( 'includes/class-tennis-install.php' );
		include_once( 'includes/functions-admin-menu.php' );
		include_once( 'includes/tennis-template-loader.php' );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			include_once( 'includes/commandline/ClubCommands.php' );
			include_once( 'includes/commandline/EventCommands.php' );
			include_once( 'includes/commandline/CmdlineSupport.php' );
			include_once( 'includes/commandline/EnvironmentCommands.php' );
			include_once( 'includes/commandline/ShowCommands.php' );
			include_once( 'includes/commandline/TournamentCommands.php' );
			include_once( 'includes/commandline/SignupCommands.php' );
		}
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

		//Bracket maintenance
		ManageBrackets::register();

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
	 * Seed the newly created schema
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
		$this->log->error_log("$loc: post_type='{$post_type}'");

		if( $query->is_main_query() && !$query->is_feed() && !is_admin() 
		&& $query->is_post_type_archive( TennisEventCpt::CUSTOM_POST_TYPE ) ) {
			$this->log->error_log("$loc: post_type='{$post_type}' is post type archive!");
			//$this->log->error_log($query, "Query Object Before");
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
	
	/**
	 * Setup this plugin
	 * @since  1.0
	 */
	private function setup() {
		$loc = __CLASS__ . "::" . __FUNCTION__;
		
		//Set the default time zone
		//date_default_timezone_set(ini_get('date.timezone'));
		$tz = wp_timezone_string();
		$this->log->error_log("$loc: time zone={$tz}");
		$tz = wp_timezone();
		$this->log->error_log($tz,"$loc: time zone object:");

        // Add actions
		add_action( 'init', array( $this, 'init') );
		//add_action( 'rest_api_init', array( self::getControllerManager(), 'register_tennis_rest_routes' ) );
		add_action( 'admin_enqueue_scripts', array( $this,'enqueue_admin') );
		add_action( 'pre_get_posts', array( $this, 'archive_tennisevent_query' ) );

		// $this->aspects = array('Main Signup' => array("shortcode"=>ManageSignup::SHORTCODE, 'bracket'=>Bracket::WINNERS)
		// 					  ,'Main Matches' => array("shortcode"=>ManageDraw::SHORTCODE . ' by=match', 'bracket'=>Bracket::WINNERS)
		// 					  ,'Main Draw' => array("shortcode"=>ManageDraw::SHORTCODE . ' by=entrant', 'bracket'=>Bracket::WINNERS)
		// 					  ,'Consolation Signup' => array("shortcode"=>ManageSignup::SHORTCODE, 'bracket'=>Bracket::CONSOLATION)
		// 					  ,'Consolaton Matches' => array("shortcode"=>ManageDraw::SHORTCODE . ' by=match', 'bracket'=>Bracket::CONSOLATION)
		// 					  ,'Consolation Draw' => array("shortcode"=>ManageDraw::SHORTCODE . ' by=entrant', 'bracket'=>Bracket::CONSOLATION)
		// 					);

		//add_action( 'admin_init', array($this, 'generatePages') );
		//add_action( 'admin_init', array($this, 'removePages') );
		//add_action( 'wp', array( $this, 'generateMenu' ) );
		
	}   
	
	private function __wakeup() {}
	private function __clone() {}

	/**
	 * Generate Pages based on definition of Tennis Events
	 */
	public function generatePages() {
		$loc = __CLASS__ . ":" . __FUNCTION__;

		//Get the root page
		$args = array(
			'meta_key'     => self::ROOT_PAGE_META_KEY,
			'meta_value'   => 1,
			'post_type'    => 'page',
			'post_status'  => 'publish',
		); 
		$rootPage = get_posts( $args )[0];
		$title =  __('Tennis Events', TennisEvents::TEXT_DOMAIN );
		// $this->log->error_log("$loc: Testing for root page '{$title}'");		
		// $rootPage = get_page_by_title( $title, OBJECT, 'page' );
		if( is_null( $rootPage ) ) {
			$rootPage = array(
				'post_title'    => $title,
				'post_content'  => $title,
				'post_status'   => 'publish',
				'post_author'   => 1,
				'post_type'     => 'page',
				'meta_input'	=> [self::ROOT_PAGE_META_KEY => 1]
			);
			// Insert the post into the database.
			$rootPageId = wp_insert_post( $rootPage );		
		}
		else {
			$this->log->error_log("$loc: root page {$title} already exists.");
			$rootPageId = $rootPage->ID;
		}

		//Get all parent pages
		// i.e. pages based on parent tennis events
		$allEvents = Event::search('');
		$parentEvents = array_filter( $allEvents, function( $evt ) { return $evt->isParent(); });
		foreach( $parentEvents as $parent ) {	
			$clubs = $parent->getClubs();
			$club = $clubs[0];
			$title = $parent->getName();
			$parentPageId = $this->getPageId( $parent->getID() );
			if( $parentPageId < 1 ) {
				//Parent Page does not exist
				$parentArgs = array(
					'post_title'    => $title,
					//'post_content'  => $title,
					'post_status'   => 'publish',
					'post_author'   => 1,
					'post_type'     => 'page',
					'post_parent'	=> $rootPageId,
					'meta_input'	=> [self::EVENT_PAGE_META_KEY => $parent->getID()]
				);			 
				// Insert the post into the database.
				$parentPageId = wp_insert_post( $parentArgs );
			}

			//Now get all child events such as Mens Singles, Mens Doubles, Womens Singles, etc.
			foreach( $parent->getChildEvents() as $childEvent ) {
				$childPageId = $this->getPageId( $childEvent->getID() );
				if( $childPageId < 1 ) {
					//Page does not exist
					$childArgs = array(
						'post_title'    => $childEvent->getName(),
						//'post_content'  => $title,
						'post_status'   => 'publish',
						'post_author'   => 1,
						'post_type'     => 'page',
						'post_parent'	=> $parentPageId,
						'meta_input'	=> [self::EVENT_PAGE_META_KEY => $childEvent->getID()]
					);			 
					// Insert the post into the database.
					$childPageId = wp_insert_post( $childArgs );
				}
				
				//Now create all of the "aspects" of a tournament
				foreach( $this->aspects as $aspect => $detail ) {
					$title = $aspect;
					$metaValue = $childEvent->getID() . ':' . $aspect;
					$aspectPageId = $this->getPageId( $metaValue );
					if( $aspectPageId < 1 ) {
						//Aspect Page does not exist
						$signupshortcode = $detail['shortcode'];
						$bracketName = $detail['bracket'];
						$eventArgs = array(
							'post_title'    =>  $title,
							'post_content'  => "[$signupshortcode eventid={$childEvent->getID()} bracketname={$bracketName} ]",
							'post_status'   => 'publish',
							'post_author'   => 1,
							'post_type'     => 'page',
							'post_parent'	=> $childPageId,
							'meta_input'	=> [self::EVENT_PAGE_META_KEY => $metaValue]
						);			 
						$aspectPageId = wp_insert_post( $eventArgs );
						$this->log->error_log( "$loc: inserted aspect page '{$title}' with Id='$aspectPageId' and meta value='$metaValue'" );
					}
				}
			}
		}
	}
	
	//Remove all generated pages
	public function removePages() {
		$loc = __CLASS__ . ":" . __FUNCTION__;
		
		$allEvents = Event::search('');
		$parentEvents = array_filter( $allEvents, function( $evt ) { return $evt->isParent(); });
		foreach( $parentEvents as $parent ) {
			foreach( $parent->getChildEvents() as $childEvent ) {
				$childPageId = $this->getPageId( $childEvent->getID() );
				foreach( $this->aspects as $aspect => $detail ) {
					$metaValue = $childEvent->getID() . ':' . $aspect;
					$aspectPageId = $this->getPageId( $metaValue );
					wp_delete_post( $aspectPageId, true );
				}
				wp_delete_post( $childPageId, true );
			}
			$parentPageId = $this->getPageId( $parent->getID() );
			wp_delete_post( $parentPageId, true );
		}
	}

	
	/**
	 * Generate Menu using the generated pages
	 */
	public function generateMenu() {
		$loc = __CLASS__ . ":" . __FUNCTION__;

		$meta_key = self::EVENT_PAGE_META_KEY;
		$menuSlug = 'main-menu';
		
		$rootSeed = 1000000;
		$rootMenuId = $rootSeed + 99999;

		$args = array(
			'meta_key'     => self::ROOT_PAGE_META_KEY,
			'meta_value'   => 1,
			'post_type'    => 'page',
			'post_status'  => 'publish',
		); 
		$rootPage = get_posts( $args )[0];
		if( is_null( $rootPage ) ) return;

		//$this->inspectMenus("$loc: menus before generation.......................................................");

		//Add the first level menu for this root page
		CustomMenu::add_object($menuSlug, $rootPage->ID, 'post', 9999, 0, $rootMenuId );
		$allEvents = Event::search('');		
		$parentEvents = array_filter( $allEvents, function( $evt ) { return $evt->isParent(); });
		$porder = 1;
		foreach( $parentEvents as $parentEvent ) {
			$parentPage = $this->getPage( $parentEvent->getID() );
			if( is_null( $parentPage ) ) continue;
			CustomMenu::add_object( $menuSlug, $parentPage->ID, 'post', $porder++, $rootMenuId, $parentEvent->getID() );
			$parentEventId = $parentEvent->getID();
			$corder = 1;
			foreach( $parentEvent->getChildEvents() as $childEvent ) {
				$childPage = $this->getPage( $childEvent->getID() );
				if( !is_null( $childPage ) ) {
					CustomMenu::add_object( $menuSlug, $childPage->ID, 'post', $corder++, $parentEventId, $childEvent->getID() );
					$aorder = 1;
					foreach( $this->aspects as $aspect => $detail ) {
						$metaValue = $childEvent->getID() . ':' . $aspect;
						$aspectPage = $this->getPage( $metaValue );
						if( !is_null( $aspectPage ) ) {
							CustomMenu::add_object( $menuSlug, $aspectPage->ID, 'post', $aorder++, $childEvent->getID(), $metaValue );
						}
					}
				}
				wp_reset_postdata();
			}
		}
		$this->inspectMenus("$loc: menus after generation.......................................................");

		// Single item
		/**
		 * @param $menu_slug
		 * @param $title
		 * @param $url
		 * @param $order
		 * @param $parent
		 * @param null $ID
		 */
		//EventMenu::add_item('menu-1', 'My Profile', get_author_posts_url( get_current_user_id() ), 3  );

		// Item with children
		// note: the ID is manually set for the top level item
		//EventMenu::add_item('menu-1', 'Top Level', '/some-url', 0, 0, 9876 ); 
		// note: this and other children know the parent ID
		//EventMenu::add_item('menu-1', 'Child 1', '/some-url/child-1', 0, 9876 ); 
		//EventMenu::add_item('menu-1', 'Child 2', '/some-url/child-2', 0, 9876 );
		//EventMenu::add_item('menu-1', 'Child 3', '/some-url/child-3', 0, 9876 );

		/**
		 * Add object by ID
		 *
		 * @param $menu_slug
		 * @param $object_ID
		 * @param string $object_type
		 * @param $order
		 * @param $parent
		 * @param null $ID
		 */
		// Add the post w/ ID 1 to the menu
		//EventMenu::add_object('menu-1', 1, 'post');
		// Add the taxonomy term with ID "3" to the menu as a top-level item with the ID of 9876
		//EventMenu::add_object('menu-1', 3, 'term', 0, 0, 9876);
		// Add the taxonomy term with ID "4" to the menu as a child of item 9876 
		//EventMenu::add_object('menu-1', 4, 'term', 0, 9876);
	} 

	/**
	 * Get a page based on a given meta value for the event meta key name
	 * @param mixed $metaValue
	 * @return int page ID
	 */
	private function getPageId( $metaValue ) {				
		$loc = __CLASS__ . ":" . __FUNCTION__;

		$pageId = 0;
		$page = $this->getPage( $metaValue );
		if( !is_null( $page ) ) {
			$pageId = $page->ID;
		}
		return $pageId;
	}
	
	/**
	 * Get a page based on a given meta value for the event meta key name
	 * @param mixed $metaValue
	 * @return mixed page
	 */
	private function getPage( $metaValue ) {				
		$loc = __CLASS__ . ":" . __FUNCTION__;
		$meta_key = self::EVENT_PAGE_META_KEY;
		$this->log->error_log( "$loc: searching for page with {$meta_key}='{$metaValue}'");

		$args = array('meta_key'   => self::EVENT_PAGE_META_KEY,
					'meta_value'   => $metaValue,
					'post_type'    => 'page',
					'post_status'  => 'publish',
					); 

		$pages = get_posts( $args );
		$ct = count( $pages );
		$this->log->error_log("$loc: found $ct pages using meta value='{$metaValue}'");
		$page = null;
		if( $ct > 1 ) {
			//Error condition

		}
		else if( 1 == $ct ) {
			//Page exists
			$page = $pages[0];
		}
		return $page;
	}


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
$tennisEvents = TennisEvents::get_instance();
$GLOBALS['tennisEvents'] = $tennisEvents;
function TE() {
	global $tennisEvents;
	return $tennisEvents;
}

include_once( 'autoloader.php' );
require_once( 'includes/commonlib/GW_Support.php' );

// Register activation/deactivation hooks
register_activation_hook( __FILE__, array( 'TennisEvents', 'on_activate' ) );
register_deactivation_hook ( __FILE__, array( 'TennisEvents', 'on_deactivate' ) );
register_uninstall_hook ( __FILE__, array( 'TennisEvents', 'on_uninstall' ) );
add_action(	'plugins_loaded', array ( $tennisEvents, 'plugin_setup' ) );

// $dir = plugin_dir_path( __DIR__ );
// include_once(__DIR__ . '/includes/commonlib/support.php' );

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