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
	const CONSOLATION = 'consolation';
	const GAMES       = 'games';
	const SETS        = 'sets';

	public static function AllFormats() {
		return [ self::SINGLE_ELIM  => __( 'Single Elimination', TennisEvents::TEXT_DOMAIN )
			   , self::DOUBLE_ELIM  => __( 'Double Elimination', TennisEvents::TEXT_DOMAIN )
			   , self::CONSOLATION  => __( 'Consolation', TennisEvents::TEXT_DOMAIN )
			   , self::GAMES        => __( 'Total Games', TennisEvents::TEXT_DOMAIN )
			   , self::SETS         => __( 'Total Sets', TennisEvents::TEXT_DOMAIN ) ];
	}
}
