<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gender Types
 */
class GenderType {
    public const MALES   = "males";
    public const FEMALES = "females";
    public const MIXED   = "mixed";
    public const BOYS    = "boys";
    public const GIRLS   = "girls";

    public static function AllTypes() {
        return [  sprintf("%s", self::MALES)   => __("Men", TennisEvents::TEXT_DOMAIN )
                , sprintf("%s", self::FEMALES) => __("Women", TennisEvents::TEXT_DOMAIN )
                , sprintf("%s", self::MIXED)   => __('Mixed', TennisEvents::TEXT_DOMAIN )
                , sprintf("%s", self::BOYS)   => __('Boys', TennisEvents::TEXT_DOMAIN )
                , sprintf("%s", self::GIRLS)   => __('Girls', TennisEvents::TEXT_DOMAIN ) ];
    }

    public static function isValid( $gt ) {
        return in_array( $gt, array_keys(self::AllTypes()) );
    }
}
