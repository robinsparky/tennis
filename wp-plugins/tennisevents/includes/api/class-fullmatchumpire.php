<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dir = plugin_dir_path( __DIR__ );
include_once($dir . '/gw-support.php' );


class FullMatchUmpire extends ChairUmpire
{

    public function __construct( Match $m ) {
        $this->match = $m;
    }

    public function __destruct() {
        $this->match = null;
    }

	public function recordScores( int $set, int ...$scores ) {
        switch( count( $scores) ) {
            case 2:
                break;
            case 4:
                break;
            default:
        }
    }

	public function whatIsScore() {

    }

	public function whatIsStatus() {

    }

	public function defaultTheMatch() {

    }	
    
    public function whoWonTheMatch() {

    }

    
}