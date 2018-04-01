<?php
/*
	Plugin Name: Tennis Events
	Plugin URI: xlconsultinggroup.com
	Description: Tennis Events front-end support
	Version: 1.0
	Author: Robin Smith
	Author URI: xlconsultinggroup.com
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$wpdb->hide_errors(); 

if ( !class_exists( 'TennisEvents' ) ) :

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
	
	/**
	 * Unique identifier for the plugin.
	 * The variable name is used as the text domain when internationalizing strings
	 * of text.
	 *
	 * @since    1.0.0
	 * @var      string
	 */
	public const PLUGIN_SLUG = 'tennis';
	public const TEXT_DOMAIN = 'tennis_text';
	
	
	/**
	 * Installer singleton
	 */
	public static $TE_Installer;

	
	/**
	 * Endpoint Controller Manager singleton
	 */
	public static $ControllerManager;

	//This class's singleton
	private static $_instance;

	/**
	 * TennisEvents Singleton
	 *
	 * @since 1.0
	 * @static
	 * @see TE()
	 * @return $_instance --Main instance.
	 */
	public static function get_instance() {
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

		$this->includes();
		$this->init_hooks();
	}
	
	public function includes() {
		include_once('autoloader.php');
		include_once( 'includes/gw-support.php' );
		include_once( 'includes/class-controller-manager.php' );
		include_once( 'includes/class-tennis-install.php' );
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			include_once( 'includes/commandline/class-displaydraw.php' );
		}
	}

	/**
	 * Hook into actions and filters.
	 * @since  1.0
	 */
	private function init_hooks() {
		add_action('init',array($this,'init'));
		
		self::$TE_Installer = TE_Install::get_instance();
		register_activation_hook( __FILE__, array( self::$TE_Installer , 'on_activate' ) );
		register_deactivation_hook (__FILE__, array( self::$TE_Installer ,'on_deactivate' ) );
		register_uninstall_hook (__FILE__,array( __class__ ,'on_uninstall' ) );
		add_action( 'rest_api_init', array(self::$ControllerManager, 'register_tennis_rest_routes') );
	}

	/**
	 * Init Tennis Events 
	 * 1. Instantiate the installer
	 * 2. Instantiate the Endpoints/routes Controller
	 */
	public function init() {
		self::$TE_Installer = TE_Install::get_instance();
		self::$ControllerManager = TennisControllerManager::get_instance();
	}

	public static function on_uninstall() {
		error_log(__class__ . ": on_uninstall");
		self::$TE_Installer->uninstall();
	}
	
	public function get_plugin_path() {
		return plugin_dir_path(__FILE__);
	}
}
endif;

function TE() {
	return TennisEvents::get_instance();
}

$tennis = TE();
