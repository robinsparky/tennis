<?php
namespace api\events;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class AbstractEvent {
    private $name;
    private $params;

    public function __construct( $name, $params = array() ) {
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

