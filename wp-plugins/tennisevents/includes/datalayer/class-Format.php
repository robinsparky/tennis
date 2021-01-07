<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Formats
 * Data and functions for Bracket formats
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
 */
class Format {
	const TOURNAMENT  = 'tournament';
	const ROUNDROBIN  = 'roundrobin';

	public static function AllFormats() {
		return [ self::TOURNAMENT   => __( 'Tournament', TennisEvents::TEXT_DOMAIN )
			   , self::ROUNDROBIN   => __( 'Round Robin', TennisEvents::TEXT_DOMAIN )];
	}

	public static function isValid( $possible ) {
		return in_array( $possible, array_keys(self::AllFormats()) );
	}
}
