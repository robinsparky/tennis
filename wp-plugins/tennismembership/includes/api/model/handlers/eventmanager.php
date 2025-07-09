<?php
namespace api\model\handlers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * EventManager class to handle event registration and triggering.
 *
 * This class allows you to register event listeners and trigger events with parameters.
 * It is a simple implementation of the observer pattern.
 */
class EventManager
{
    private static $events = array();

    public static function listen($name, $callback) {
        self::$events[$name][] = $callback;
    }

    public static function trigger($name, $params = array()) {
        foreach (self::$events[$name] as $event => $callback) {
            if($params && is_array($params)) {
                call_user_func_array($callback, $params);
            }
            elseif ($params && !is_array($params)) {
                call_user_func($callback, $params);
            }
            else {
                call_user_func($callback);
            }
        }
    }
}