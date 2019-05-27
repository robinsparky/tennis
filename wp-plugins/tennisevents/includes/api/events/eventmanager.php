<?php
namespace api\events;
use api\events\AbstractEvent;

class EventManager
{
    private $events = array();

	private static $_instance;

	/**
	 * EventManager Singleton
	 *
	 * @since 1.0
	 * @static
	 * @see TE()
	 * @return $_instance --Main instance.
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
			wp_die( sprintf( esc_html__( '%s is a singleton class and you cannot create a second instance.', TennisEvents::TEXT_DOMAIN ),get_class( $this ) ) );
        }
    }

    public function listen($name, $callback) {
        $this->events[$name][] = $callback;
    }

    public function trigger($name, $params = array()) {
        foreach ($this->events[$name] as $event => $callback) {
            $e = new AdvanceEvent($name, $params);
            $callback($e);
        }
    }
}