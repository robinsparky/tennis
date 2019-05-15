<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

 /**
  * BracketTemplate is a template containing
  * a collection of rounds and matches (SplDoublyLinkedList for now)  
  * Instances are typically built using one of the AbstractBuilders
  * namely ByeTemplateBuilder
  */
 class BracketTemplate {

    //Template of the collection of rounds and matches
    private $template = null;
    private $signupSize;
    private $numByes;

    private $log;

    public function __construct() {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        $this->log = new BaseLogger( true );
    }

    /**
     * Put a round (SplDoublyLinkedList) into the template
     * NOTE: The round number is 1 greater than the index into the template which is an array
     * @param $round is an SplDoublyLinkedList containing all the match objects for a round
     */
    public function put( mixed $round ) : void {
        $loc = __CLASS__ . "::" . __FUNCTION__;

        $numRounds = TournamentDirector::calculateExponent( $this->signupSize );
        if(!is_null( $this->template ) && $round instanceof SplDoublyLinkedList ) {
            $obj = $round->offsetGet(0);
            if( isset( $obj->round ) && $obj->round <= $numRounds ) {
                $index = $obj->round - 1;
                $this->$template[ $index ] = $round;
            }
        }
    }

    /**
     * Get the Round as a collection of match objects     
     *  NOTE: The round number is 1 greater than the index into the template which is an array
     * @param $round_num
     * @return SplDoublyLinkedList of match objects
     */
    public function getRound( int $round_num ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;

        if( !is_null( $this->template ) ) {
            if(count( $this->template ) > 1 && array_key_exists( $round_num - 1, $this->template ) ) {
                return $this->template[$round_num - 1];
            }
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
        $loc = __CLASS__ . "::" . __FUNCTION__;
        $this->log->error_log("$loc called with round=$round_num, match=$match_num");

        $round = $this->getRound( $round_num );
        if( !is_null( $round ) ) {
            $this->log->error_log($round, "$loc found this round:");
            $round->setIteratorMode( SplDoublyLinkedList::IT_MODE_FIFO );
            for( $round->rewind(); $round->valid(); $round->next() ) {
                if( $round->current()->round == $round_num 
                && $round->current()->match_num == $match_num ) {
                    $this->log->error_log("$loc: found match obj!");
                    return $round->current();
                }
            }
        }
        else {
            $this->log->error_log("$loc: round($round_num) is null!");
        }
        return null;
    }

    /**
     * Return the underlying collection of rounds and matches
     * @return Array of collections of matches. Each collection is a round.
     */
    public function getTemplate() {
        $loc = __CLASS__ . "::" . __FUNCTION__;

        return $this->template;

    }

    /**
     * Make a copy of the template
     * Necessary if somebody wants to make destructive use of this template
     * @return copy of the underlying template
     */
    public function copyTemplate() {
        $result = clone $this->template;
        return $result;
    }

    /**
     * Build the rounds and matches template
     * Destroys any existing template
     * @param $signupSize is the number of entrants for this bracket
     * @param $numByes is the number of byes in the preliminary round
     */
    public function build( int $signupSize, int $numByes ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;

        if( $signupSize > 1 && $numByes < $signupSize ) {
            $this->signupSize = $signupSize;
            $this->numByes = $numByes;
            $this->log->error_log("$loc: using Bye template builder($signupSize,$numByes)");
            $templateBuilder = new ByeTemplateBuilder( $signupSize, $numByes );
            $this->template = $templateBuilder->build();
        }
    }


    /**
     * Get a an array of Match identifiers M(round number,match number)
     * showing next matches on one line for each initial match
     * @return Array of match identifiers
     */
    public function arrGetTemplate() {
        $result = array();
        foreach( $this->template as $round ) {
            $round->setIteratorMode( SplDoublyLinkedList::IT_MODE_FIFO );
            $str = '';
            for( $round->rewind(); $round->valid(); $round->next() ) {
                $match = $round->current();
                $marker = ' ';
                if( isset( $match->is_bye ) ) {
                    if( $match->is_bye) $marker = '*';
                } 
                elseif( isset( $match->is_waiting ) ) {
                    if( $match->is_waiting ) $marker = '*';
                }
                $str .= sprintf( "%sM(%d,%d)->M(%d,%d)|"
                               , $marker, $match->round, $match->match_num, $match->next_round_num, $match->next_match_num );
            }
            $result[] = $str;
        }
        return $result;
    }

    /**
     * Retrieve all matches that follow from the starting round & match numbers.
     * @param $round_num The round that the starting match is in
     * @param $match_num The match number of the starting match
     * @return array of match objects
     */
    public function getFollowingMatches( int $round_num, int $match_num ) : array {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        $this->log->error_log( "$loc: round=$round_num, match=$match_num" );

        $matchObj = $this->getMatch( $round_num, $match_num );
        $rounds = $this->getTemplate();
        $rounds = array_slice( $rounds, 1 ); //Get all rounds starting after the preliminary round
        return $this->getNextMatches( $matchObj, $rounds );
    }

    /* ----------------------------------Private Functions --------------------------------------*/

    /**
     * Recursive function to extract all following matches from the given preliminary match
     * @param $startObj The starting match in stdClass form
     * @param $rounds Reference to an array of splDoublyLinkedList reprsenting all matches beyond the priliminary one
     * @return array of match objects
     */
    private function getNextMatches( $startObj, array &$rounds ) : array {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log($startObj, "$loc called with startObj");

        $found = array();
        $nr = isset($startObj->next_round_num) ? $startObj->next_round_num : -1;
        $nm = isset($startObj->next_match_num) ? $startObj->next_match_num : -1;
        
        $this->log->error_log("$loc: search next round=$nr, next match=$nm");

        if( $nr != -1 && $nm != -1 ) {
            foreach( $rounds as $round ) {
                //$dlist->setIteratorMode( SplDoublyLinkedList::IT_MODE_FIFO );
                for( $i = 0; $i < $round->count(); $i++ ) {
                    if( !$round->offsetExists( $i ) ) continue;
                    $obj = $round->offsetGet( $i );   
                    $r = isset( $obj->round ) ? $obj->round : -1;
                    $m = isset( $obj->match_num ) ? $obj->match_num : -1;
                    if( $r == $nr && $m == $nm ) {
                        $found[] = $obj;
                        $round->offsetUnset( $i );
                        $more = $this->getNextMatches( $obj, $rounds );
                        foreach($more as $next) {
                            $found[] = $next;
                        }
                        break;
                    }
                }
            }
        }
        return $found;
    }
    
 } //end class