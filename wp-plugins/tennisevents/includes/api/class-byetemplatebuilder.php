<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

 /**
  * ByeTemplateBuilder creates a template of the whole tournament
  * assuming that a number of byes are to be used
  * Iterates over each round until only 2 players left
  */
 class ByeTemplateBuilder extends AbstractBuilder {

    public function build() {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        static $numCalls = 0;

        ++$numCalls;

        //Verify the number of rounds
        $numRounds = $this->calculateExponent( $this->numPlayers ) + 1;


        $size = $this->numPlayers;
        $byes = $this->special;
        $players = $size - $byes;
        $insertPoints = $byes > 0 ? ceil( $players / $byes ) : 999999;
        
        $this->log->error_log( sprintf( "%s ---- number of players=%d; number of rounds=%d; number of byes=%d, insert=%d"
                          , $loc, $size, $numRounds, $byes, $insertPoints ) );

        //Need to have even number of players for first round
        if( $players & 1 ) {            
            throw new TennisConfigurationException( __( "Cannot create round 1 matches because invalid number of players.", TennisEvents::TEXT_DOMAIN ) );
        }

        $firstBye = null;
        $lastBye = null;
        switch( $byes ) {
            case 0:
                $firstBye = null;
                $lastBye = null;
                break;
            case 1:
                $firstBye = new stdClass;
                $firstBye->round = 1;
                $firstBye->match_num = 1;
                $firstBye->is_bye = true;
                $firstBye->players = 1;
                $lastBye = null;
                --$byes;
                break;
            default:
                $firstBye = new stdClass;
                $firstBye->round = 1;
                $firstBye->match_num = 1;
                $firstBye->is_bye = true;
                $firstBye->players = 1;
                --$byes;

                $lastBye = new stdClass;
                $lastBye->round = 1;
                $lastBye->match_num =$players/2 + 1;
                $lastBye->is_bye = true;
                $lastBye->players = 1;
                --$byes;
                break; 
        }

        for( $r = 1; $r <= $numRounds; $r++ ) {
            $template = new SplDoublyLinkedList();
            $m = 1;
            $played = 0;
            $this->log->error_log( sprintf( "%s --- Start round %d. size=%d, players=%d, byes=%d, played=%d"
                              , $loc, $r, $size, $players, $byes, $played ) );

            if( $players & 1 && $r < $numRounds ) {
                throw new TennisConfigurationException( __( "Round $r has odd number of participants $size.") );
            }

            while( $size > 0 ) {
                if( !is_null( $firstBye ) ) {
                    $firstBye->match_num = $m++;
                    $mess = sprintf( "%s --- first bye: r=%d, m=%d, bye=%d, players=%d"
                                   , $loc, $firstBye->round, $firstBye->match_num, $firstBye->is_bye, $firstBye->players );
                    $this->log->error_log( $mess );
                    $size -= $firstBye->players;
                    $template->push( $firstBye );
                    $firstBye = null;
                }
                elseif( !is_null( $lastBye ) && $size <= 2 ) {
                    $lastBye->match_num = $m++;
                    $mess = sprintf( "%s --- last bye: r=%d, m=%d, bye=%d, players=%d"
                                   , $loc, $lastBye->round, $lastBye->match_num, $lastBye->is_bye, $lastBye->players );
                    $this->log->error_log( $mess );
                    $size -= $lastBye->players;
                    $template->push( $lastBye );
                    $lastBye = null;
                }
                elseif( ( $played % $insertPoints === 0 ) && $byes > 0 ) {
                    $bye = new stdClass;
                    $bye->round = $r;
                    $bye->match_num = $m++;
                    $bye->is_bye = true;
                    $bye->players = 1;
                    $mess = sprintf( "%s --- inserted bye: r=%d, m=%d, bye=%d, players=%d"
                                   , $loc, $bye->round, $bye->match_num, $bye->is_bye, $bye->players );
                    $this->log->error_log( $mess );
                    $template->push( $bye );
                    --$byes;
                    $size -= $bye->players;
                } 
                else {
                    $holder = new stdClass;
                    $holder->round = $r;
                    $holder->match_num = $m++;
                    $holder->is_bye = false;
                    $holder->players = 2;
                    $players -= $holder->players;
                    $size    -= $holder->players;
                    $played  += $holder->players;
                    $mess = sprintf( "%s --- placeholder match: r=%d, m=%d, bye=%d, players=%d"
                                , $loc, $holder->round, $holder->match_num, $holder->is_bye, $holder->players );
                    $this->log->error_log( $mess );
                    $template->push( $holder );
                }
            } //end while
            //At this point: size = 0; players=0; byes=0; played = players that played
            $this->log->error_log( sprintf( "%s --- End round %d. size=%d, players=%d, byes=%d, played=%d"
                              , $loc, $r, $size, $players, $byes, $played ) );
            $size = $players = $r === 1 ? ceil( $played / 2 ) + $this->special : floor( $played / 2 );
            $this->rounds[] = $template;
        } //next round

        $this->addNextPointers();
        return $this->rounds;
    }

    protected function addNextPointers( ) {
        
        $loc = __CLASS__ . "::" . __FUNCTION__;
        $this->log->error_log("$loc -----------starting");

        foreach( $this->rounds as $round ) {
            $round->rewind();
            $round->setIteratorMode( SplDoublyLinkedList::IT_MODE_FIFO );
            $ctr = 1;
            $nm = 1;
            while( $round->valid() ) {
                $match = $round->current();
                if( $ctr & 1 ) {
                    $match->next_round_num = $match->round + 1;
                    $match->next_match_num = $nm;
                }
                else {
                    $match->next_round_num = $match->round + 1;
                    $match->next_match_num = $nm++;
                }
                $this->log->error_log( sprintf("%s ------- match=%d, next match number=%d", $loc, $match->match_num, $match->next_match_num ) );
                ++$ctr;
                $round->next();
            }
        }
    }

}
    