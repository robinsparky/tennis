<?php
namespace datalayer;
use \TennisEvents;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Match Types
 */
class MatchType {
    public const SINGLES   = "singles";
    public const DOUBLES   = "doubles";

    public static function AllTypes() {
        return [  sprintf("%s", self::SINGLES)   => __('Singles', TennisEvents::TEXT_DOMAIN )
                , sprintf("%s", self::DOUBLES)   => __('Doubles', TennisEvents::TEXT_DOMAIN )];
    }

    public static function isValid( $mt ) {
        return in_array($mt, array_keys(MatchType::AllTypes()));
    }
}
