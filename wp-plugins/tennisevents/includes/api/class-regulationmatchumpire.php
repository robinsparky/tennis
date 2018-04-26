<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dir = plugin_dir_path( __DIR__ );
include_once($dir . '/gw-support.php' );


class RegulationMatchUmpire extends ChairUmpire
{
	//This class's singleton
	private static $_instance;

	/**
	 * RegulationMatchUmpire Singleton
	 *
	 * @since 1.0
	 * @static
	 * @return $_instance --Main instance.
	 */
	public static function getInstance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}
	
	/**
	 *  Constructor.
	 */
	public function __construct() {
		// Don't allow more than one instance of the class
		if ( isset( self::$_instance ) ) {
			wp_die( sprintf( esc_html__( '%s is a singleton class and you cannot create a second instance.', 'ten' ), get_class( $this ) ) );
		}

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
     * Record game and tie breaker scores for a given set pf the supplied Match.
     * @param $match The match whose score are recorded
     * @param $setnum The set number 
     * @param ...$scores if 2 args then game scores; if 4 then games and tiebreaker scores
     */
	public function recordScores( Match $match, int $setnum, int ...$scores ) {

        switch( count( $scores ) ) {
            case 2:
                $homewins = $scores[0];
                $visitorwins = $scores[1];
                $match->setScore( $setnum, $homewins, $visitorwins );
                break;
            case 4:
                $homewins = $scores[0];
                $home_tb_pts = $scores[1];
                $visitorwins = $scores[2];
                $visitor_tb_pts = $scores[3];
                $match->setScore( $setnum, $homewins, $visitorwins, $home_tb_pts, $visitor_tb_pts );
                break;
            default:
        }

        return $match->save();
    }

    /**
     * Default the home player/team for this Match
     * @param $match The match being played
     * @param $cmts  Any comments explaining the default.
     */
	public function homeDefault( Match $match, string $cmts ) {
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

    /**
     * Default the visitor player/team for this Match
     * @param $match The match being played
     * @param $cmts  Any comments explaining the default.
     */
    public function visitorDefault( Match $match, string $cmts ) {
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
    
    /**
     * Get status of the Match
     * @param $match Match whose status is calculated
     * @return The status of the given match
     */
	public function matchStatus( Match $match ) {
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

    /**
     * Determine the winner of the given Match
     * @param $match
     */
    public function matchWinner( Match $match ) {
        $andTheWinnerIs = __( 'TBA' );
        $home = $match->getHomeEntrant();
        $homeSetsWon = 0;
        $visitor = $match->getVisitorEntrant();
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

    /**
     * Return the score by set of the given Match
     * @param $match
     */
	public function matchScore( Match $match ) {
        //$sets = Set::find( $match->getEventId(), $match->getRoundNumber(), $match->getMatchNumber() );
        
        $home = $match->getHomeEntrant()->getName();
        $visitor = $match->getVisitorEntrant()->getName();
        $sets = $match->getSets();
        $scores = array();

        foreach($sets as $set ) {
            $setnum = (int)$set->getSetNumber();
            $scores[$setnum] = array( $home => $set->getHomeWins(), $visitor => $set->getVisitorWins(), $home . "-Tiebreaker" => $set->getHomeTieBreaker(), $visitor . "-Tiebreaker" => $set->getVisitorTieBreaker() );
        }
        return $scores;
    }

    
}