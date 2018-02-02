<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class IEvent {
    private $name;
    private $params;

    public function __construct($name, $params = array()) {
        $this->name = $name;
        $this->params = $params;
    }

    public function getName() {
        return $this->name;
    }

    public function getParams() {
        return $this->params;
    }
}

class EventManager
{
    private $events = array();

    public function attach($name, $callback) {
        $this->events[$name][] = $callback;
    }

    public function trigger($name, $params = array()) {
        foreach ($this->events[$name] as $event => $callback) {
            $e = new IEvent($name, $params);
            $callback($e);
        }
    }
}

$events = new EventManager;

$events->attach('do', function($e) {
    echo $e->getName() . "\n";
    print_r($e->getParams());
});

$events->trigger('do', array('a', 'b', 'c'));