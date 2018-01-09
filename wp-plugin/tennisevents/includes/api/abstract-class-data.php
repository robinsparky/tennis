<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 
 * This is an abstract class from which all other 
 * data-based classes should inherit
 * @class  AbstractData
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
*/
abstract class AbstractData
{ 
    abstract static public function search($criteria);
    abstract static public function find($fk_id);
    abstract static public function get($id);
    abstract static protected function mapData($obj,$row);

    abstract public function getChildren();
    abstract public function save();
    abstract public function delete();

	private $isdirty = FALSE;
	private $isnew   = TRUE;

} //end class
 