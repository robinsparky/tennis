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
        return [self::MENS_SINGLES, self::WOMENS_SINGLES, self::MENS_DOUBLES, self::WOMENS_DOUBLES, self::MIXED_DOUBLES];
    }
}
