
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$p2Dir = plugin_dir_path( plugin_dir_path( __DIR__ ) );
require_once( $p2Dir . 'tennisevents.php' );
require_once( 'api-exceptions.php' );

/** 
 * A Bracket composes SplDoublyLinkedList 
 * @class  Bracket
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
*/
class Bracket 
{
    public $name;

    private $adjacencyMatrix = array();

    public function __construct( string $name = 'My Bracket' ) {
        $this->name = $name;
    }

    public function putMatch( Match &$match ) {
        if( ! array_key_exists( $match) ) {
            $this->adjacencyMatrix[ $match ] = new SplDoublyLinkedList();
        }
        $this->fillOut( $match );
    }

    public function fillOut( Match &$match ) {
        $next = $match->getNextMatchNumber();
        $nextMatch = $next > 0 ? Match::get( $next ) : null;
        if( is_null( $nextMatch ) ) {
            return;
        }
        if( $this->adjacencyMatrix[$match]->offsetExists( $nextMatch ) ) {
            $this->adjacencyMatrix[$match]->offsetUnSet( $nextMatch );
        }
        $this->adjacencyMatrix[$match]->offsetSet( $nextMatch );
        $this->fillOut( $nextMatch );
    }
}
