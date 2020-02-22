<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Match Types
 */
class MatchType {
    public const MENS_SINGLES   = 1.1;
    public const WOMENS_SINGLES = 1.2;
    public const MENS_DOUBLES   = 2.1;
    public const WOMENS_DOUBLES = 2.2;
    public const MIXED_DOUBLES  = 2.3;

    public static function AllTypes() {
        return [  sprintf("%s", self::MENS_SINGLES)   => __('Mens Singles', TennisEvents::TEXT_DOMAIN )
                , sprintf("%s", self::WOMENS_SINGLES) => __('Womens Singles', TennisEvents::TEXT_DOMAIN )
                , sprintf("%s", self::MENS_DOUBLES)   => __('Mens Doubles', TennisEvents::TEXT_DOMAIN )
                , sprintf("%s", self::WOMENS_DOUBLES) => __('Womens Doubles', TennisEvents::TEXT_DOMAIN )
                , sprintf("%s", self::MIXED_DOUBLES)  => __('Mixed Doubles', TennisEvents::TEXT_DOMAIN ) ];
    }
}
