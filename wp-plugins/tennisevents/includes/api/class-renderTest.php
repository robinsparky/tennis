<?php

use templates\DrawTemplateGenerator;

class RenderTest {

    private $ajax_nonce = null;
    private $errobj = null;
    private $errcode = 0;

    private $log;

    public static function register() {
        $handle = new self();
        add_action('wp_enqueue_scripts', array( $handle, 'registerScripts' ) );
        $handle->registerHandlers();
    }

	/*************** Instance Methods ****************/
	public function __construct( ) {
	    $this->errobj = new WP_Error();	
        $this->log = new BaseLogger( true );
    }


    public function registerScripts() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log( $loc );
    }
    
    public function registerHandlers( ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log($loc);

        add_shortcode( 'render_test', array( $this, 'renderTestShortcode' ) );
    }

    public function renderTestShortcode( $atts = [], $content = null ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $atts = array_change_key_case((array)$atts, CASE_LOWER);
        $this->log->error_log( $atts, $loc );

        // if( !is_user_logged_in() ) {
        //     return '';
        // }

        $my_atts = shortcode_atts( array(
            'size' => 8,
        ), $atts, 'render_test' );

        $size = $my_atts["size"];

        if( is_null( $size ) ) return 'Bad size';

        $gen = new DrawTemplateGenerator();
        $template = $gen->generateTable( $size );

        return $template;

    }
}