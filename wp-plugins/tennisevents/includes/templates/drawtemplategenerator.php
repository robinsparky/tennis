<?php
namespace templates;
use api\BaseLoggerEx;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Using the number of entrants
 * this class provides for generating tables
 * which can be used as templates for a Draw
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
 */
class DrawTemplateGenerator
{

    private $name;
    private $size = 0;
    private $eventId = 0;
    private $bracketName = 0;
    private $includeMatrix = array();
    private $rows = 0;
    private $cols = 0;

    private $log;

    /**
     * Given the size of the draw (or any integer), calculate the highest 
     * power of 2 which is greater than or equal to that size (or integer)
     * @param $size 
     * @param $upper The upper limit of the search; default is 8
     * @return The exponent if found; zero otherwise
     */
	public static function calculateExponent( int $size, $upper = 8 ) {
        $exponent = 0;
        foreach( range( 1, $upper ) as $exp ) {
            if( pow( 2, $exp ) >= $size ) {
                $exponent = $exp;
                break;
            }
        }
        return $exponent;
    }

    public function __construct( string $name = 'Generator', int $size = 4, int $eventId = 0, string $bracketName = '' ) {
        $this->log = new BaseLoggerEx( false );
        $this->name = $name;
        $this->size = $size;
        $this->eventId = $eventId;
        $this->bracketName = $bracketName;
    }

    public function getName() {
        return $this->name;
    }

    public function getSize() {
        return $this->size;
    }

    public function setEventId( int $id ) {
        $this->eventId = $id;
    }

    public function getEventId() {
        return $this->eventId;
    }

    public function setBracketName( string $name ) {
        $this->bracketName = $name;
    }

    public function getBracketName() {
        return $this->bracketName;
    }


    public function setSize( int $size ) {
        $this->size = $size;
    }

    public function getRows() {
        return $this->rows;
    }

    public function getColumns() {
        return $this->cols;
    }

    public function getIncludeMatrix() {
        return $this->includeMatrix;
    }

    /**
     * Generate an html table for a draw of the given size.
     * @param $size The size of the draw. Any number greater than 4.
     */
    public function generateTable() {
        $loc = __CLASS__ . "::" . __FUNCTION__;

        if( $this->size <  4 ) return '';

        $numRounds = self::calculateExponent( $this->size );
        $numCols = $numRounds + 1;
        $numRows = pow( 2, $numRounds );

        $template = "<table class='bracketdraw' data-numrounds='$numRounds' data-eventid='$this->eventId' data-bracketname='$this->bracketName'>" . PHP_EOL;
        $template .= "<caption>$this->name</caption>" . PHP_EOL;
        $template .= "<thead><tr>" . PHP_EOL;


        $rowspan = 1;
        $upperRowSpan = pow(2, $numRounds );
        $this->log->error_log("$loc: numRounds=$numRounds; numRows=$numRows; numCols=$numCols; upperRowSpan=$upperRowSpan");

        
        for( $i=1; $i <= $numRounds; $i++ ) {
            $rOf = $this->roundOf( $i );
            $template .= sprintf( "<th class='drawRound'>Round Of %d</th>", $rOf );
        }
        $template .= "<th>Champion</th>";
        $template .= "</tr></thead>" . PHP_EOL;

        $template .= "<tbody>" . PHP_EOL;

        $this->includeMatrix = $this->generateIncludeMatrix();
        $this->log->error_log("$loc: include:");
        //print_r($include);
        //$this->printTable($this->includeMatrix);

        $m = 0;
        $prev_m = 1;

        for( $row = 1; $row <= $numRows; $row++ ) {
            $template .= "<tr id='row$row' class='drawRow'>";

            for( $col = 1; $col <= $numCols; $col++ ) {
                $rowspan = pow( 2, $col - 1 );
                if( $col === 1 && ($row & 1) )  {
                    ++$m;
                }
                if(  $this->includeMatrix[$row][$col] == 1 ) {
                    $template .= "<td rowspan='$rowspan' class='drawPlayer' data-round='$col' data-entrantNum='$row'>($row,$col) M($col,$m) </td>";
                }
            }

            $template .= "</tr>" . PHP_EOL;
        }

        $this->rows = $numRows;
        $this->cols = $numCols;

        $template .= "</tbody></table>" . PHP_EOL;
        return $template;
    }

    /**
     * Iterate over the size of the draw
     * and calculate which cells out of the entire 
     * table should be included when rendering the draw
     * @return Array of rows and columns containing either 0 or 1
     */
    private function generateIncludeMatrix( ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;

        if( $this->size < 4 ) return array();

        $numRounds = self::calculateExponent( $this->size );
        $numCols = $numRounds + 1;
        $numRows = pow(2, $numRounds);
        $this->log->error_log("$loc: numRounds:$numRounds; numRows:$numRows; numCols:$numCols");

        //Make the table all zeros to start
        $includeMatrix = array();
        for($row = 1; $row <= $numRows; $row++ ) {
            for($col = 1; $col <= $numCols; $col++ ) {
                $includeMatrix[$row][$col] = 0;
            }
        }
        
        //Set only those cells which are to be included
        // in the table to a value of 1
        for( $col = 1; $col <= $numCols; $col++ ) {
            $rowspan = pow( 2, $col - 1 );
            $len = $numRows / $rowspan;
            $step = $rowspan;    
            //$this->log->error_log("$loc: col: $col; rowspan=$rowspan; len=$len; step=$step");   
            for( $row = 1; $row <= $numRows; $row += $step ) {    
                $this->log->error_log("$loc:row=$row; col=$col set to 1");   
                $includeMatrix[$row][$col] = 1;
            }
        }

        return $includeMatrix;
    }
    
    /**
     * Get the round of number.
     * If it is the first round, then round of is number who signed up
     * Otherwise it is the number expected to be playing in the given round.
     * @param $r The round number
     */
    public function roundOf( int $r ) : int {
        $loc = __CLASS__ . "::" . __FUNCTION__;

        // $result = $this->size;
        // if( $r <= 1 ) return $result;

        $exp = self::calculateExponent( $this->size );
        $result = pow( 2, $exp ) / pow( 2, $r - 1 );
        return $result;        
    }

    /**
     * For debugging only
     */
    private function printTable( $table ) {
        $loc = __CLASS__ . "::" . __FUNCTION__;

        foreach($table as $row ) {
            $line = '';
            foreach($row as $col) {
                $line .= sprintf(" %d ", $col);
            }
            error_log($line . PHP_EOL);
        }
        error_log(PHP_EOL);
    }

}