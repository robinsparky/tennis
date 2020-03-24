<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Formats
 * Data and functions for tournament formats
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
 */
class Format {
	const SINGLE_ELIM = 'selim';
	const DOUBLE_ELIM = 'delim';
	const POINTS      = 'points'; //round robin
	const GAMES       = 'games'; //round robin
	const OPEN        = 'open';

	public static function AllFormats() {
		return [ self::SINGLE_ELIM  => __( 'Single Elimination', TennisEvents::TEXT_DOMAIN )
			   , self::DOUBLE_ELIM  => __( 'Double Elimination', TennisEvents::TEXT_DOMAIN )
			   , self::POINTS       => __( 'Total Points', TennisEvents::TEXT_DOMAIN )
			   , self::GAMES        => __( 'Total Games', TennisEvents::TEXT_DOMAIN ) 
			   , self::OPEN         => __( 'Open', TennisEvents::TEXT_DOMAIN ) ];
	}

	public static function isValid( $possible ) {
		return in_array( $possible, array_keys(self::AllFormats()) );
	}
}
