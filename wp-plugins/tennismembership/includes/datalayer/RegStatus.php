<?php
namespace datalayer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

//Registration Status
enum RegStatus : string {
	case Active    = "Active";
	case Inactive = "Inactive";
	case Suspended = "Suspended";
}
