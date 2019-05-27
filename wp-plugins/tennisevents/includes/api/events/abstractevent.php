<?php
namespace api\events;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class AbstractEvent extends OverloadedConstructors {
    private $name;
    private $params;

    public function __construct2( $name, $params = array() ) {
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

