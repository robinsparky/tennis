<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dir = plugin_dir_path( __DIR__ );
include_once($dir . '/gw-support.php' );


class FullMatchUmpire extends ChairUmpire
{

    public function __construct( Match $m ) {
        $this->match = $m;
    }

    public function __destruct() {
        $this->match = null;
    }
    
	public function setMaxSets( int $max = 5 ) {
		switch( $max ) {
			case 3:
			case 5:
				$this->MaxSets = $max;
				$result = true;
				break;
			default:
			$result = false;
		}
		return $result;
	}

    /**
     * Record game and tie breaker scores for a given set.
     * @param $setnum The set number 
     * @param ...$scores if 2 args then game scores; if 4 then games and tiebreaker scores
     */
	public function recordScores( int $setnum, int ...$scores ) {

        switch( count( $scores ) ) {
            case 2:
                $homewins = $scores[0];
                $visitorwins = $scores[1];
                $this->match->setScore( $setnum, $homewins, $visitorwins );
                break;
            case 4:
                $homewins = $scores[0];
                $home_tb_pts = $scores[1];
                $visitorwins = $scores[2];
                $visitor_tb_pts = $scores[3];
                $this->match->setScore( $setnum, $homewins, $visitorwins, $home_tb_pts, $visitor_tb_pts );
                break;
            default:
        }

        return $this->match->save();
    }

	public function homeDefault( string $cmts ) {
        $result = false;
        $sets = $match->getSets();
        $size = count( $sets );
        if( $size > 0 ) {
            $sets[$size - 1]->setEarlyEnd( 1 );
            $sets[$size - 1]->setComments( $cmts );
            $sets[$size - 1]->save();
            $result = true;
        }
        return $result;
    }	

    public function visitorDefault( string $cmts ) {
        $result = false;
        $sets = $match->getSets();
        $size = count( $sets );
        if( $size > 0 ) {
            $sets[$size - 1]->setEarlyEnd( 2 );
            $sets[$size - 1]->setComments( $cmts );
            $sets[$size - 1]->save();
            $result = true;
        }
        return $result;
    }
    
	public function matchStatus() {
        $sets = $match->getSets();
        $status = self::NOTSTARTED;
        foreach( $sets as $set ) {
            if( $set->earlyEnd() ) {
                $cmts = $set->getComments();
                $status = self::EARLYEND . ":" . ( isset( $cmts ) ? $cmts : '' );
                break;
            }
            if( $set->getSetNumber() >= $this->getMaxSets() ) {
                $status = self::COMPLETED;
                break;
            }
            $status = self::INPROGRESS;
        }
        return $status;
    }

    public function matchWinner() {
        $andTheWinnerIs = __( 'TBA' );
        $home = $this->match->getHomeEntrant();
        $homeSetsWon = 0;
        $visitor = $this->match->getVisitorEntrant();
        $visitorSetsWon = 0;
        $finished = false;

        if( $this->match->isWaiting() ) {
            $andTheWinnerIs = __( 'Match Not Set' );
        }
        else {
            foreach( $sets as $set ) {
                if( 1 === $set->earlyEnd() ) {
                    //Home defaulted
                    $andTheWinnerIs = $home->getName();
                    $finished = true;
                    break;
                }
                elseif( 2 === $set->earlyEnd() ) {
                    //Visitor defaulted
                    $andTheWinnerIs = $visitor->getName();
                    $finished = true;
                    break;
                }
                else {
                    $homeW = $set->getHomeWins();
                    $homeTB = $set->getHomeTieBreaker();
                    $visitorW = $set->getVisitorWins();
                    $visitorTB = $set->getVisitorTieBreaker();
                    
                    if( ( $homeW + $visitorW ) < $this->GamesPerSet ) break;

                    if( $homeW > $visitorW ) {
                        ++$homeSetsWon;
                    }
                    elseif( $visitorW > $homeW ) {
                        ++$visitorSetsWon;
                    }
                    else { //Tie breaker
                        if( $homeTB > $visitorTB ) {
                        ++$homeSetsWon;
                        }
                        elseif( $homeTB < $visitorTB ) {
                            ++$visitorSetsWon;
                        }
                        else { //match not finished yet
                            break;
                        }
                    }
                }
            }
        }

        if( ( $homeSetsWon + $visitorSetsWon ) === $this->MaxSets ) {
            if( $homeSetsWon > $visitorSetsWon ) {
                $andTheWinnerIs = $home->getName();
            }
            else {
                $andTheWinnerIs = $visitor->getName();
            }
        }
        return $andTheWinnerIs;
    }

	public function matchScore() {
        //$sets = Set::find( $match->getEventId(), $match->getRoundNumber(), $match->getMatchNumber() );
        
        $home = $this->match->getHomeEntrant()->getName();
        $visitor = $this->match->getVisitorEntrant()->getName();
        $sets = $match->getSets();
        $scores = array();

        foreach($sets as $set ) {
            $setnum = (int)$set->getSetNumber();
            $scores[$setnum] = array( $home => $set->getHomeWins(), $visitor => $set->getVisitorWins(), $home . "-Tiebreaker" => $set->getHomeTieBreaker(), $visitor . "-Tiebreaker" => $set->getVisitorTieBreaker() );
        }
        return $scores;
    }

    
}