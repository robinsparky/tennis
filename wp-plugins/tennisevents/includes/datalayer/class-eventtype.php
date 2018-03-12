<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Event types
 */
class EventType {
	const TOURNAMENT  = 'tournament';
	const LEAGUE      = 'league';
	const LADDER      = 'ladder';
	const ROUND_ROBIN = 'robin';
}