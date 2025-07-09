<?php
namespace api\model\handlers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class AbstractEvent {
    private $name;
    private $params;

    /**
     * Constructor for the AbstractEvent class.
     *
     * @param string $name The name of the event.
     * @param array $params Optional parameters for the event.
     */
    public function __construct( $name, $params = array() ) {
        $this->name = $name;
        $this->params = $params;
    }

    /**
     * Get the name of the event.
     *
     * @return string The name of the event.
     */
    public function getName() {
        return $this->name;
    }

    /**
     * Get the parameters of the event.
     *
     * @return array The parameters of the event.
     */
    public function getParams() {
        return $this->params;
    }
}

