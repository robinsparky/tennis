<?php
use \SplDoublyLinkedList;
use \stdClass;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

 /**
  * BracketTemplate is a template containing
  * a collections of rounds and matches (SplDoublyLinkedList for now)  
  * Instances are typically built using one of the AbstractBuilders
  * namely ByeTemplateBuilder
  */
 class BracketTemplate {

    private $template = array();

    public function __constructor() {

    }

    /**
     * Add a round (SplDoublyLinkedList) to the template
     * @param $round
     */
    public function addRound( mixed $round ) : void {
        if( $round instanceof SplDoublyLinkedList ) {
            if( !$round->isEmpty() ) {
                $obj = $round->offsetGet(0);
                if(isset($obj->round)) {
                    $index = $obj->round;
                    $this->template[$index] = $round;
                }
            }
        }
    }

    /**
     * Push a round (SplDoublyLinkedList for now) to the end of the template
     * @param $round
     */
    public function push( mixed $round ) {
        if( $round instanceof SplDoublyLinkedList ) {
            $this->template->push( $round );
        }
    }

    /**
     * Get the Round as a collection of match objects
     * @param $round_num
     * @return SplDoublyLinkedList of match objects
     */
    public function getRound( int $round_num ) {
        if( array_key_exists( $round_num, $this->template[$round_num] ) ) {
            return $this->template[$round_num];
        }
        return null;
    }


    /**
     * Get the match object identified by the paramaters
     * @param $round_num 
     * @param $match_num
     * @return A match object or null if not found
     */
    public function getMatch( int $round_num, int $match_num ) {
        $round = $this->getRound( $round_num );
        if( !is_null( $round ) ) {
            $round->setIteratorMode( SplDoublyLinkedList::IT_MODE_FIFO );
            for( $round->rewind(); $round->valid(); $round->next() ) {
                if( $round->current()->round == $round_num && $round->current()->match_num ) {
                    return $round->current();
                }
            }
        }
        return null;
    }

    /**
     * Retrieve all matches that follow from the starting round & match numbers.
     * @param $round_num The round that the starting match is in
     * @param $match_num The match number of the starting match
     * @return array of match objects
     */
    public function getFollowingMatches( int $round_num, int $match_num ) : array {
        $matchObj = $this->getMatch( $round_num, $match_num );

    }

    /**
     * Recursive function to extract all following matches from the given preliminary match
     * @param $startObj The starting match in stdClass form
     * @param $rounds Reference to an array of splDoublyLinkedList reprsenting all matches beyond the priliminary one
     * @return array of match objects
     */
    private function getNextMatches( $startObj, array &$rounds ) : array {

        $found = array();
        $nr = isset($startObj->next_round_num) ? $startObj->next_round_num : -1;
        $nm = isset($startObj->next_match_num) ? $startObj->next_match_num : -1;
        $dlist = array_shift( $rounds );
        if( is_null( $dlist ) ) return $found; //early return

        //$dlist->setIteratorMode( SplDoublyLinkedList::IT_MODE_FIFO );
        for( $i = 0; $i < $dlist->count(); $i++ ) {
            if( !$dlist->offsetExists( $i ) ) continue;
            $obj = $dlist->offsetGet( $i );   
            $r = isset( $obj->round ) ? $obj->round : -1;
            $m = isset( $obj->match_num ) ? $obj->match_num : -1;
            if( $r == $nr && $m == $nm ) {
                $dlist->offsetUnset( $i );
                $found[] = $obj;
                $found[] = array_merge( $found, $this->getNextMatches( $obj, $rounds ) );
                break;
            }
        }
        return $found;
    }
    

 }