<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dir = plugin_dir_path( __DIR__ );
include_once($dir . '/gw-support.php' );


/**
* Pro set
    * Instead of playing multiple sets, players may play one "pro set". 
    * A pro set is first to 8 (or 10) games by a margin of two games, instead of first to 6 games. 
    * A 12-point tie-break is usually played when the score is 8–8 (or 10–10). These are often played with no-ad scoring.
 */
class ProSetUmpire extends ChairUmpire
{
    
}