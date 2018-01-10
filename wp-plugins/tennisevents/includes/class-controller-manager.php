<?php

/**
 * This plugin class provides logic for Tennis Events:
 *       1. 
 * @class  TennisEndPointManager
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
*/
class TennisControllerManager
{

	/**
	 * A reference to an instance of this class.
	 *
	 * @since 0.1.0
	 *
	 * @var   TennisControllerManager singleton
	 */
	private static $instance;

	/**
	 * Unique identifier for the key to local cache.
	 * The key used to access cache created for identifying templates from this plugin
	 *
	 * @since    1.0.0
	 * @var      string
	 */
	protected $cache_key;

    /**
    * TennisControllerManager Singleton
    *
    * @return   TennisControllerManager
    * @since    1.0.0
    */
    public static function get_instance()
    {
        if (null == self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    } // end getInstance

    /**
    * Initializes the plugin instance
    *
    * @version  1.0.0
    * @since   1.0.0
    */
    private function __construct()
    {
        $this->includes();

    } // end constructor

	private function includes() {
		include_once('gw-support.php');
		include_once('controllers/class-controller-clubs.php');
		include_once('controllers/class-controller-courts.php');
		include_once('controllers/class-controller-events.php');
		include_once('controllers/class-controller-draws.php');
	}

	//Register Routes and Endpoints
	public function register_tennis_rest_routes() {
		
		$controller = new ClubsController();
		$controller->register_routes();

		$controller = new EventsController();
		$controller->register_routes();
		
		$controller = new CourtsController();
		$controller->register_routes();
		
		$controller = new DrawsController();
		$controller->register_routes();
		
	}

} // end class

