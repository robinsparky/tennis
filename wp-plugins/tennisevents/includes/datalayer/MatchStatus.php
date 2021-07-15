<?php
namespace datalayer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 
 * Data and functions for a Tennis Event Match Status
 * @class  MatchStatus
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
*/
class MatchStatus {

    //Major statuses
    const NotStarted = 1;
    const InProgress = 2;
    const Completed  = 3;
    const Bye        = 4;
    const Waiting    = 5;
    const Cancelled  = 6;
    const Retired    = 7;

    //Minor Statuses
    const DefaultHome = 1;
    const DefaultVisitor = 2;

    //Status strings
	public const NOTSTARTED = "Not started";
	public const INPROGRESS = "In progress";
	public const COMPLETED  = "Completed";
	public const EARLYEND   = "Retired";
	public const BYE        = "Bye";
	public const WAITING    = "Waiting";
    public const CANCELLED  = "Cancelled";
    public const DEFAULTHOME = "Home Default";
    public const DEFAULTVISITOR = "Visitor Default";
    

    //Array of major statuses
    public static $majors = array( self::NotStarted
                                 , self::InProgress
                                 , self::Completed
                                 , self::Bye
                                 , self::Waiting
                                 , self::Cancelled
                                 , self::Retired);
    //Array of major status strings
    public static $strMajorStatus = array( self::NotStarted => self::NOTSTARTED
                                        , self::InProgress => self::INPROGRESS
                                        , self::Completed  => self::COMPLETED
                                        , self::Bye        => self::BYE
                                        , self::Waiting    => self::WAITING
                                        , self::Cancelled  => self::CANCELLED
                                        , self::Retired    => self::EARLYEND );
                                               
    //Array of minor statuses
    public static $minors = array(self::DefaultHome, self::DefaultVisitor);
    //Array of minor status strings
    public static $strMinorStatus = array( self::DefaultHome => self::DEFAULTHOME
                                         , self::DefaultVisitor => self::DEFAULTVISITOR );
                                  
    //private fields
    private $majorstatus = 0;
    private $minorstatus = 0;
    private $explanation = '';

	/**
	 *  Constructor.
	 */
	public function __construct() {

    }

    /**
     * Set the major status
     * @param int $major
     */
    public function setMajor( int $major ) {
        if( in_array( $major, self::$majors ) ) {
            $this->majorstatus = $major;
        }
    }

    /**
     * Set the minor status
     * @param int $minor
     */
    public function setMinor( int $minor ) {
        if( $this->majorstatus > 0 ) {
            if( in_array( $minor, self::$minors ) ) {
                $this->minorstatus = $minor;
            }
        }
    }

    /**
     * Set the explanation for the minor status
     * @param string $explanation
     */
    public function setExplanation( string $explanation ) {
        $this->explanation = $explanation;
    }

    /**
     * Has the status been set?
     * @return bool true if has major status false otherwise
     */
    public function isSet() {
        return ($this->majorstatus > 0 );
    }

    /**
     * Get the status as a number
     * @return float status
     */
    public function getStatus() : float {
        return $this->minorstatus > 0 ? $this->majorstatus + ($this->minorstatus / 10) : $this->majorstatus + 0.0;
    }

    public function getMajorStatus() {
        return $this->majorstatus;
    }

    public function getMinorStatus() {
        return $this->minorstatus;
    }

    /**
     * Get the status as a string
     */
    public function toString() : string {
        $major = $this->majorstatus > 0 ? self::$strMajorStatus[ $this->majorstatus ] : '';
        $minor = $this->minorstatus > 0 ? '-' . self::$strMinorStatus[ $this->minorstatus ] : '';
        $desc  = empty($this->explanation) ? '' : ':' . $this->explanation;
        return $major . $minor . $desc;
    }
    
}