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

    public static function AllTypes() {
        return [  sprintf("%s", self::MALES)   => __('Males', TennisEvents::TEXT_DOMAIN )
                , sprintf("%s", self::FEMALES) => __('Females', TennisEvents::TEXT_DOMAIN )
                , sprintf("%s", self::MIXED)   => __('Mixed', TennisEvents::TEXT_DOMAIN ) ];
    }

    public static function isValid( $gt ) {
        return in_array( $gt, array_keys(self::AllTypes()) );
    }
}
