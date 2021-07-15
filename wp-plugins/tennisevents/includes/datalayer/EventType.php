<?php
namespace datalayer;
use TennisEvents;

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
	
	public static function AllTypes() {
		return array( self::LADDER      => __('Ladder', TennisEvents::TEXT_DOMAIN )
					, self::LEAGUE      => __('League', TennisEvents::TEXT_DOMAIN )
					, self::TOURNAMENT  => __('Tournament', TennisEvents::TEXT_DOMAIN ) );
	}

	public static function isValid( $possible ) {
		return in_array( $possible, array_keys(self::AllTypes()));
	}
}