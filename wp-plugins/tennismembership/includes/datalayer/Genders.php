<?php
namespace datalayer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides allowable gender values
 * @class  Genders
 * @package Tennis Members
 * @version 1.0.0
 * @since   0.1.0
 */
class Genders 
{
    public const Male = "Male";
    public const Female = "Female";
    public const Other = "Other";
    public static $genders = [self::Male,self::Female,self::Other];

    public static function getGendersDropDown($current='', $style='float:none; margin-left: 5px;') : string {
        $sel = "<select class='membership gender-selector' name='user_gender[]' style='{$style}'>Gender&hellip;";
        $sel .= "<option value=''>Gender&hellip;</option>";
        foreach(self::$genders as $gender) {
            $selected = $gender == $current ? 'selected' : '';
            $sel .= "<option value='{$gender}' {$selected}>{$gender}</option>";
        }
        $sel .= "</select>";
        return $sel;
    }
    private function __construct() {}
}
