<?php
namespace api;

use \TennisEvents;
use InvalidArgumentException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ELO Rating scheme
 *
 * @package TennisAdmin
 * @version 1.0.0
 * @since   0.1.0
 * @class ELO
 */
class ELO 
{
    //Winner - Loser
    private $exchangeTable= [
        [[-2000, -720],-720, 32]
       ,[[-719, -524],-523, 31]
       ,[[-523, -428],-427,30]
       ,[[-427, -365],-364,29]
       ,[[-364, -315],-314,27]
       ,[[-273, -238],-237,26]
       ,[[-237, -206],-205,25]
       ,[[-205, -177],-176,24]
       ,[[-176, -150],-149,23]
       ,[[-149, -125],-124,22]
       ,[[-124, -101],-100,21]
       ,[[-100, -78],-77,20]
       ,[[-77, -55],-54,19]
       ,[[-54, -33],-32,18]
       ,[[-32, -11],-10,17]
       ,[[-10, 0],0,16]
       ,[[0, 10],11,16]
       ,[[11, 32],33,15]
       ,[[33, 54],55,14]
       ,[[55, 77],78,13]
       ,[[78, 100],101,12]
       ,[[101, 124],125,11]
       ,[[125, 149],150,10]
       ,[[150, 176],177,9]
       ,[[177, 205],206,8]
       ,[[206, 237],238,7]
       ,[[238, 273],274,6]
       ,[[274, 314],315,5]
       ,[[315, 364],365,4]
       ,[[365, 427],428,3]
       ,[[428, 523],524,2]
       ,[[524, 719],720,1]
       ,[[720,	2000],2001,0]
       ];
    
	//This class's singleton
	private static $_instance;

    /**
     * Get the singleton instance
     */
    public static function get_instance() {
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
			wp_die( sprintf( esc_html__( '%s is a singleton class and you cannot create a second instance.', TennisEvents::TEXT_DOMAIN ),get_class( $this ) ) );
		}
	}
    
    /**
     * Calculate the new ELO rating based on results of a match between Player A and Player B
     * @param $Ra the rating of Player A before the match
     * @param $Rb the rating of Player B before the match
     * @param $d is true if Player A wins; false when Player B wins.
     * @return array of two new ratings; first element is the new rating of Player A and second is the new rating of Player B
     */
	public function calcRating(float $Ra, float $Rb, bool $d) : array {
		// To calculate the Winning
		// Probability of Player B
		$Pb = $this->probability($Ra, $Rb);

		// To calculate the Winning
		// Probability of Player A
		$Pa = $this->probability($Rb, $Ra);

		// Case 1 When Player A wins
		// Updating the Elo Ratings
		if ($d == true) {
            $K = $this->getKFactor($Ra,$Rb);
			$RaNew = $Ra + $K * (1 - $Pa);
			$RbNew = $Rb + $K * (0 - $Pb);
		}
		// Case 2 When Player B wins
		// Updating the Elo Ratings
		else {
            $K = $this->getKFactor($Rb,$Ra);
			$RaNew = $Ra + $K * (0 - $Pa);
			$RbNew = $Rb + $K * (1 - $Pb);
		}

        return[$RaNew,$RbNew];
	}

    private function getKFactor(float $winnerRating,float $loserRating):int {
        $kFactor = -1;
        $diff = $winnerRating - $loserRating;
        foreach($this->exchangeTable as $range) {
            if( $diff >= $range[0][0] && $diff <= $range[0][1]) {
                $kFactor = $range[2];
                break;
            }
        }
        if($kFactor === -1 ) throw new InvalidArgumentException(__("ELO couldn't calculate K factor", TennisEvents::TEXT_DOMAIN));

        return $kFactor;
    }

	/**
     * Function to calculate the Probability
     * @param $r1 is the rating of a player
     * @param $r2 is the rating of the second player
     * @return float the probability of either player winning
     */
	private function probability(float $r1, float $r2) : float
	{
		return 1.0 / (1.0 + pow(10,($r1 - $r2) / 400));
	}

}
?>
