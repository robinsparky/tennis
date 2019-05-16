<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$p2Dir = plugin_dir_path( plugin_dir_path( __DIR__ ) );
require_once( $p2Dir . 'tennisevents.php' );
require_once('api-exceptions.php');

/** 
 * This is an abstract class from which all other 
 * template builder classes extend
 * @class  AbstractBuilder
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
*/
abstract class AbstractBuilder
{ 

    abstract public function build();
    abstract protected function addNextPointers();
    protected $special = 0;
    protected $numPlayers = 0;
    protected $rounds = array();
    protected $log;

    /**
     * Parent Ctor 
     * @param $n The number of players or teams in the tournament
     * @param $s The number of byes or challengers
     */
    public function __construct( int $n, int $s ) {
        if( $n <= $s ) {
            throw new InvalidTournamentException( __( "Byes/challengers must be less than the number of players", TennisEvents::TEXT_DOMAIN ) );
        }

        $this->log = new BaseLogger( false );

        $this->numPlayers = $n;
        $this->special = $s < 0 ? 0 : $s;
    }

    /**
     * Get the template which is an array of SplDoublyLinkedLists
     * And each linked list is a list of matches in a specific round.
     * The index to the list is the round number.
     */
    public function getTemplate() {
        return $this->rounds;
    }

    /**
     * Get a an array of Match identifiers M(round number,match number)
     * showing next matches on one line for each initial match
     * @return Array of match identifiers
     */
    public function arrGetTemplate() {
        $result = array();
        foreach( $this->rounds as $round ) {
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
     * Given the size of the draw (or any integer), calculate the highest 
     * power of 2 which is less than that size (or integer)
     */
	protected function calculateExponent( int $size, $upper = 8 ) {
        $exponent = 0;
        foreach( range( 1, $upper ) as $exp ) {
            if( pow( 2, $exp ) > $size ) {
                $exponent = $exp - 1;
                break;
            }
        }
        return $exponent;
    }

}