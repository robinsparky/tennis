<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dir = plugin_dir_path( __DIR__ );
include_once($dir . '/gw-support.php' );


/**
* Fast4
    * Fast4 is a shortened format that offers a "fast" alternative, with four points, four games and four rules: 
    * 1. there are no advantage scores 
    * 2. lets are played
    * 3. tie-breakers apply at three games all 
    * 4. the first to four games wins the set
 */
class Fast4Umpire extends ChairUmpire
{

}