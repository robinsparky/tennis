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
	
	public static function AllTypes() {
		return array( self::LADDER      => __('Ladder', TennisEvents::TEXT_DOMAIN )
					, self::LEAGUE      => __('League', TennisEvents::TEXT_DOMAIN )
					, self::ROUND_ROBIN => __('Round Robin', TennisEvents::TEXT_DOMAIN )
					, self::TOURNAMENT  => __('Tournament', TennisEvents::TEXT_DOMAIN ) );
	}
}