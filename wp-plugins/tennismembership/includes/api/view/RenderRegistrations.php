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

global $jsRegistrationData;
/** 
 * Renders Club Registrations using shortcode
 * Registrations are the link between People and Membership
 * Is is also invoked by the Tennis Club Registration templates
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
    const SHORTCODE  = 'manage_registrations';

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
        wp_register_script( 'manage_registrations', $jsurl, array('jquery','jquery-ui-draggable','jquery-ui-droppable', 'jquery-ui-sortable'), TennisClubMembership::VERSION, true );
        
        $cssurl = TM()->getPluginUrl() . 'css/tennismembership.css';
        wp_enqueue_style( 'membership_css', $cssurl );
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

        $short_atts = shortcode_atts( 
            array(
                'title'=>'All Registrations',
                'status' => '*',
                'regtype' => '*',
                'season' => '*',
                'user' => '*'
        ), $atts, 'render_reg' );

        $this->log->error_log( $short_atts, "$loc: Shortcode Atts" );

        return $this->renderRegistrations($short_atts);
    }

    private function renderRegistrations(array $args ) {        
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log( $loc );
		$startFuncTime = microtime( true );
        GW_Debug::gw_print_mem();
  
        $title = $args['title'];
        $status = $args['status'];
        $regType = $args['regtype'];
        $season = $args['season'];
        $targetUserId = $args['user'];
   
        global $jsRegistrationData;
        wp_enqueue_script( 'digital_clock' );  
        wp_enqueue_script( 'manage_registrations' );  
        $jsRegistrationData = $this->get_ajax_data();       
        wp_localize_script( 'manage_registrations', 'tennis_membership_obj', $jsRegistrationData );  
        
	    // Start output buffering we don't output to the page
        ob_start();
        
        // Get template file to render the registration home page or workflow
        $path = TM()->getPluginPath() . 'includes\templates\archive_clubmembershipcpt.php';
        $path = str_replace( '\\', DIRECTORY_SEPARATOR, $path );
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
            ,'season'   => TM()->getSeason()
            ,'corporateId' => TM()->getCorporationId()
            ,'message' => $mess
        );
    }
}