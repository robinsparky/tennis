<?php
namespace commonlib;

/** 
 * Type for classes to compose to conditionally log errors
 * @class  BaseLogger
 * @package Tennis Common Library
 * @version 1.0.0
 * @since   0.1.0
*/
class BaseLogger {

    public $writelog = true;

    public $recipient = "robin.sparky@gmail.com";
    
	public function __construct( $log = true ) {
        global $TennisEventNoLog;
        if( isset( $TennisEventNoLog) ) $log = false;
        $this->writelog = $log;
    }

    public function error_log() {
        $something = null;
        $label = '';
        $backtrace = false;
        $numargs = func_num_args();
        $arg_list = func_get_args();

        if( $this->writelog ) {
            switch( $numargs ) {
                case 0:
                    return;
                case 1:
                    if( is_object( $arg_list[0]) || is_array($arg_list[0])) {
                        $something = $arg_list[0];
                        $label = '';
                        $backtrace = false;
                    }
                    else{
                        $something = null;
                        $label = $arg_list[0];
                        $backtrace = false;
                    }
                    break;
                case 2:
                    if( is_object( $arg_list[0] ) || is_array( $arg_list[0] ) ) {
                        $something = $arg_list[0];
                        if( is_string($arg_list[1]) ) {
                            $label = $arg_list[1];
                            $backtrace = false;
                        }
                        else {
                            $label = '';
                            $backtrace = $arg_list[1];
                        }
                    }
                    else {
                        $something = null;
                        $label = $arg_list[0];
                        $backtrace = $arg_list[1];
                    }
                    break;
                case 3:
                    $something = $arg_list[0];
                    $label = $arg_list[1];
                    $backtrace = $arg_list[2];
                    break;
                default:
                $something = $arg_list[0];
                $label = $arg_list[1];
                $backtrace = $arg_list[2];
            }

            if( is_object( $something ) || is_array( $something ) ) {
                if( ! empty( $label ) ) error_log($label);
                error_log( print_r( $something, true ) );
            }
            else if(is_string( $something ) || is_numeric( $something ) ) {
                $label = empty( $label ) ? "" : $label . ":";
                error_log( "$label $something" );
            }
            else {
                //$label =  empty( $label ) ? "" : $label . ":";
                error_log("$label");
            }

            $backtrace = false;
            if( $backtrace ) {
                error_log("****Backtrace:");
                error_log( print_r($this->get_debug_trace(), true ) );
            }
        }
    }
    
    public function error_apache( string $message ) {
        if( $this->writelog ) {
            $stderr = fopen('php://stderr', 'w'); 
            fwrite($stderr,$message); 
            fclose($stderr); 
        }
    }
    
    public function error_mail( $message, $subject = '' ) {
        if( $this->writelog ) {
            error_log( $message, 1, $this->recipient 
                     , "Subject: $subject\nFrom: log@care4nurses.org\n");
        }
    }
    
    private function get_debug_trace( $frames = 4) {
 
        $frames += 2;
        $trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, $frames );
        array_shift($trace);
        array_shift($trace);
        array_shift($trace);

        return $trace;
    }

} //end of class