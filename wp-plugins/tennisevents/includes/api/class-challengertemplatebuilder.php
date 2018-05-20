<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

 /**
  * ChallengerTemplateBuilder creates a template of the whole tournament
  * assuming that a challenger round is needed
  * Iterates over each round until only 2 players left
  */
 class ChallengerTemplateBuilder extends AbstractBuilder {

    public function build() {
        $loc = __CLASS__ . "::" . __FUNCTION__;

        //Verify the number of rounds
        $numRounds = $this->calculateExponent( $this->numPlayers ) + 1;

        $size = $this->numPlayers;
        $challengers = 2 * $this->special;
        $target = $size - pow( 2, $numRounds - 1 );
        $players = $size - $target;
        $insertPoints = $challengers > 0 ? floor( $players / $challengers ) : 999999;
        $round1Size = $size - $this->special;
        error_log( sprintf( "%s ---- number of players=%d; number of rounds=%d; number of challengers=%d,round1size=%d, insert=%d"
                          , $loc, $size, $numRounds, $challengers, $round1Size, $insertPoints ) );

        //Need to have even number of challengers for the early round
        if( $players & 1 ) {            
            throw new TennisConfigurationException( __( "Cannot create round 1 matches because invalid number of players ($players).", TennisEvents::TEXT_DOMAIN ) );
        }
        
        //Round 0 matches
        $template = new SplDoublyLinkedList();
        $m = 1 - $insertPoints;
        $linkPoints = array();
        while( $challengers > 0 ) {
            $challenge = new stdClass;
            $challenge->round = 0;
            $m += $insertPoints;
            array_push( $linkPoints, $m );
            $challenge->match_num = $m;
            $challenge->players = 2;
            $mess = sprintf( "%s --- inserted challengers: r=%d, m=%d, players=%d"
                           , $loc, $challenge->round, $challenge->match_num, $challenge->players );
            error_log( $mess );
            $template->push( $challenge );
            $challengers -= $challenge->players;
        }
        
        $this->rounds[] = $template;
        $numWaiting = count( $linkPoints );

        //Remaining rounds' matches
        $size = $round1Size;
        for( $r = 1; $r < $numRounds; $r++ ) {
            $template = new SplDoublyLinkedList();
            $m = 1;
            $played = 0;
            error_log( sprintf( "%s --- Start round %d. size=%d, players=%d, challengers=%d, played=%d"
                              , $loc, $r, $size, $players, $challengers, $played ) );

            if( $players & 1 && $r < $numRounds ) {
                throw new TennisConfigurationException( __( "Round $r has odd number of participants $size.") );
            }

            while( $size > 0 ) {
                if( in_array( $m, $linkPoints ) ) {
                    $holder = new stdClass;
                    $holder->round = $r;
                    $test = array_shift( $linkPoints );
                    $holder->match_num = $m++;
                    $holder->players = 2;
                    $holder->is_waiting = true;
                    $mess = sprintf( "%s --- waiting match: r=%d, m=%d(=%d), players=%d"
                                    , $loc, $holder->round, $holder->match_num, $test, $holder->players );
                    error_log( $mess );
                    $template->push( $holder );
                    $players -= $holder->players;
                    $size    -= $holder->players;
                    $played  += $holder->players;
                }
                else {
                    $holder = new stdClass;
                    $holder->round = $r;
                    $holder->match_num = $m++;
                    $holder->players = 2;
                    $mess = sprintf( "%s --- placeholder match: r=%d, m=%d, players=%d"
                                    , $loc, $holder->round, $holder->match_num, $holder->players );
                    error_log( $mess );
                    $template->push( $holder );
                    $players -= $holder->players;
                    $size    -= $holder->players;
                    $played  += $holder->players;
                }
            } //end while

            //At this point: size = 0; players=0; byes=0; played = players that played
            error_log( sprintf( "%s --- End round %d. size=%d, players=%d, challengers=%d, played=%d"
                              , $loc, $r, $size, $players, $challengers, $played ) );
            $size = $players = floor( $played / 2 );
            $this->rounds[] = $template;
        } //next round

        $this->addNextPointers();
    }

    protected function addNextPointers() {
        
        $loc = __CLASS__ . "::" . __FUNCTION__;

        $round0Matches = $this->rounds[0];

        foreach( $round0Matches as $match ) {
            $match->next_round_num = $match->round + 1;
            $match->next_match_num = $match->match_num;
            error_log( sprintf("%s ------- match=%d, next match number=%d", $loc, $match->match_num, $match->next_match_num ) );
        }
        
        foreach( $this->rounds as $round ) {
            $round->rewind();
            if( $round->bottom()->round === 0 ) continue;
            $round->setIteratorMode( SplDoublyLinkedList::IT_MODE_FIFO );
            $ctr = 1;
            $nm = 1;
            while( $round->valid() ) {
                $match = $round->current();
                if( $ctr & 1 ) {
                    $match->next_match_num = $nm;
                    $match->next_round_num = $match->round + 1;
                }
                else {
                    $match->next_match_num = $nm++;
                    $match->next_round_num = $match->round + 1;
                }
                error_log( sprintf("%s ------- match=%d, next match number=%d", $loc, $match->match_num, $match->next_match_num ) );
                ++$ctr;
                $round->next();
            }
        }
    }

}
    