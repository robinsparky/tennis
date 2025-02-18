<?php
namespace api\view;
use commonlib\BaseLogger;
use commonlib\GW_Debug;
use \WP_Error;
use TennisClubMembership;
use TM_Install;
use datalayer\MemberRegistration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $jsMemberData;
/** 
 * Renders Club Member Registrations using shortcode
 * Is is also invoked by the Tennis Club Member templates
 * @class  RenderRegistrations
 * @package Tennis Members
 * @version 1.0.0
 * @since   0.1.0
*/
class RenderRegistrations
{ 
    public const HOME_CLUBID_OPTION_NAME = 'gw_tennis_home_club';

    const ACTION    = 'manageTennisRegistrations';
    const NONCE     = 'manageTennisRegistrations';
    const SHORTCODE = 'manage_registrations';

    private $eventId = 0;
    private $errobj = null;
    private $errcode = 0;
    private $log;

    public static function register() {
        $handle = new self();
        add_action( 'wp_enqueue_scripts', array( $handle, 'registerScripts' ) );
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
        
        $cturl =  TM()->getPluginUrl() . 'js/digitalclock.js';
        wp_register_script( 'digital_clock', $cturl, array('jquery'), TennisClubMembership::VERSION, true );

        $jsurl =  TM()->getPluginUrl() . 'js/tennisregs.js';
        wp_register_script( 'manage_member', $jsurl, array('jquery','jquery-ui-draggable','jquery-ui-droppable', 'jquery-ui-sortable'), TennisClubMembership::VERSION, true );
        
        // $cssurl = TM()->getPluginUrl() . 'css/tennismembership.css';
        // wp_enqueue_style( 'tennis_css', $cssurl );
    }
    
    public function registerHandlers() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log($loc);

        add_shortcode( self::SHORTCODE, array( $this, 'renderShortcode' ) );
    }
     
	public function renderShortcode( $atts = [], $content = null ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $atts = array_change_key_case((array)$atts, CASE_LOWER);
        $this->log->error_log( $atts, $loc );

        if( $_POST ) {
            $this->log->error_log($_POST, "$loc: POST:");
        }

        if( $_GET ) {
            $this->log->error_log($_GET, "$loc: GET:");
        }

        $short_atts = shortcode_atts( array(
            'title'=>'Member Registrations',
            'status' => '',
            'regtype' => 0,
            'portal' => ''
        ), $atts, 'render_reg' );

        $this->log->error_log( $short_atts, "$loc: My Atts" );
        $title = $short_atts['title'];
        $status = $short_atts['status'];
        $regType = $short_atts['regtype'];
        $portal = $short_atts['portal'];

        //Get the Club from attributes
        // $club = null;
        // if(!empty( $my_atts['clubname'] ) ) {
        //     $cname = filter_var($my_atts['clubname'], FILTER_SANITIZE_STRING);
        //     $arrClubs = Club::search( $cname );
        //     if( count( $arrClubs) > 0 ) {
        //         $club = $arrClubs[0];
        //     }
        // }
        // else {
        //     $homeClubId = esc_attr( get_option(self::HOME_CLUBID_OPTION_NAME, 0) );
        //     $club = Club::get( $homeClubId );
        // }
        // if( is_null( $club ) ) return __('Please set home club id or specify name in shortcode', TennisEvents::TEXT_DOMAIN );
        //Go

        return $this->renderRegistrations($short_atts);

    }

    private function renderRegistrations( $args ) {        
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log( $loc );
        GW_Debug::gw_print_mem();

        $title = "Membership Management";
        if(is_array($args)) {
            $title = urldecode($args['title']);
            $status = $args['status'];
            $regType = $args['regtype'];
            $portal = $args['portal'];
        }

        global $jsMemberData;
        $jsMemberData = $this->get_ajax_data();
        wp_enqueue_script( 'digital_clock' );  
        wp_enqueue_script( 'manage_member' );         
        wp_localize_script( 'manage_member', 'tennis_membership_obj', $jsMemberData );  
        //wp_add_inline_script('tennis_membership_obj',$jsMemberData);      
        
	    // Start output buffering we don't output to the page
        ob_start();
        
        // Get template file to render the registrations
        $path = wp_normalize_path( TM()->getPluginPath() . 'includes\templates\archive_clubmembershipcpt.php');
        require $path;

        // Save output and stop output buffering
        $output = ob_get_clean();

        GW_Debug::gw_print_mem();
        $this->log->error_log( sprintf("%0.6f",GW_Debug::micro_time_elapsed( $startFuncTime ) ), $loc . ": Elapsed Micro Elapsed Time");
        return $output;
    }

    /**
     * Get the AJAX data that WordPress needs to output.
     *
     * @return array
     */
    private function get_ajax_data()
    {        
        $mess = '';
        return array ( 
             'ajaxurl' => admin_url( 'admin-ajax.php' )
            ,'action' => self::ACTION
            ,'security' => wp_create_nonce( self::NONCE )
            ,'message' => $mess
        );
    }
}