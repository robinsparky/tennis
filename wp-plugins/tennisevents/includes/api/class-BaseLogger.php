<?php

/** 
 * Type for classes to compose to conditionally log errors
 * @class  BaseLogger
 * @package Care
 * @version 1.0.0
 * @since   0.1.0
*/
class BaseLogger {

    public $writelog = true;

    public $recipient = "robin.sparky@gmail.com";
    
	public function __construct( $log = true ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->writelog = $log;
    }
    
    public function error_log( $something, $label = '' ) {
        if( $this->writelog ) {
            if( is_object( $something ) || is_array( $something ) ) {
                if( ! empty( $label ) ) error_log($label);
                error_log( print_r( $something, true) );
            }
            else if(is_string( $something ) ) {
                $label = empty( $label ) ? "" : $label . ":";
                error_log( "$label $something" );
            }
        }
    }
    
    public function error_apache( string $message ) {
        if( $this->writelog ) {
            $stderr = fopen('php://stderr', 'w'); 
            fwrite($stderr,$Message); 
            fclose($stderr); 
        }
    }
    
    public function error_mail( $message, $subject = '' ) {
        if( $this->writelog ) {
            error_log( $message, 1, $this->recipient 
                     , "Subject: $subject\nFrom: log@care4nurses.org\n");
        }
    }

} //end of class