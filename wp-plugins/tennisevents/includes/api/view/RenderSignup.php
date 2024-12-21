<?php
namespace api\view;
use commonlib\BaseLogger;
use datalayer\Event;
use \WP_Error;
use \TennisEvents;
use \TE_Install;
use api\TournamentDirector;
use datalayer\Bracket;
use datalayer\Club;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 
 * Renders the signup for and event/bracket via shortcode
 * With management menus/buttons or for viewing only.
 * The required javascript is also rendered for use with ManageSignup ajax calls.
 * @package TennisAdmin
 * @version 1.0.0
 * @since   0.1.0
*/
class RenderSignup
{ 
    public const HOME_CLUBID_OPTION_NAME = 'gw_tennis_home_club';

    /**
     * Action hook used by the AJAX class.
     *
     * @var string
     */
    const ACTION   = 'manageSignup';
    const NONCE    = 'manageSignup';
    const SHORTCODE = 'manage_signup';

    private $ajax_nonce = null;
    private $errobj = null;
    private $errcode = 0;

    private $clubId;
    private $eventId;
    private $bracketName;

    private $signup = array();
    private $nameKeys = array();
    private $log;
    
    /**
     * Register the AJAX handler class with all the appropriate WordPress hooks.
     */
    public static function register() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        error_log( $loc );
        
        $handler = new self();
        add_action( 'wp_enqueue_scripts', array( $handler, 'registerScripts' ) );
        $handler->registerHandlers();
    }

	/*************** Instance Methods ****************/
	public function __construct( ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;

	    $this->errobj = new WP_Error();		
        $this->log = new BaseLogger( true );
    }

    /**
     * Register css and javascript scripts.
     * The javavscript calls methods in ManageSignup via ajax
     */
    public function registerScripts() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log( $loc );

        $jsurl =  TE()->getPluginUrl() . 'js/signup.js';
        wp_register_script( 'manage_signup', $jsurl, array('jquery','jquery-ui-draggable','jquery-ui-droppable', 'jquery-ui-sortable'), TennisEvents::VERSION, true );
        
        $cssurl = TE()->getPluginUrl() . 'css/tennisevents.css';
        wp_enqueue_style( 'tennis_css', $cssurl );
    }
    
    /**
     * The shortcode method is added to WP's shortcode handler
     */
    public function registerHandlers() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log( $loc );

        add_shortcode( self::SHORTCODE, array( $this, 'signupShortcode' ) );
    }
     
    /**
     * Render list of players signed up
     * @param array $atts array of shortcode attributes
     * @param mixed $content
     * @returns string html for list of players
     */
	public function signupShortcode( $atts, $content = null )  {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log($atts, "$loc ..." );

        //The following was setting user_id to 0
        $my_shorts = shortcode_atts( array(
            'clubname' => '',
            'eventid' => 0,
            'bracketname' => Bracket::WINNERS
        ), $atts, 'manage_signup' );

        $club = null;
        if(!empty( $my_shorts['clubname'] ) ) {
            $arrClubs = Club::search( $my_shorts['clubName'] );
            if( count( $arrClubs) > 0 ) {
                $club = $arrClubs[0];
            }
        }
        else {
            $homeClubId = esc_attr( get_option(self::HOME_CLUBID_OPTION_NAME, 0) );
            $club = Club::get( $homeClubId );
        }

        if( is_null( $club ) ) return __('Please set home club id in options or specify name in shortcode', TennisEvents::TEXT_DOMAIN );
        $this->clubId = $club->getID();

        $this->eventId = (int)$my_shorts['eventid'];
        $this->log->error_log("$loc: EventId=$this->eventId");
        if( $this->eventId < 1 ) return __('Invalid event Id', TennisEvents::TEXT_DOMAIN );

        $this->log->error_log($my_shorts, "$loc: My Shorts" );   

        //TODO: Put all references into functions in TD
        $evts = Event::find( array( "club" => $club->getID() ) );
        $found = false;
        $target = null;
        if( count( $evts ) > 0 ) {
            foreach( $evts as $evt ) {
                $target = Event::getEventRecursively( $evt, $this->eventId );//gw_support
                if( isset( $target ) ) {
                    $found = true;
                    break;
                }
            }
        } 
        
        if( !$found || is_null($target) ) return __('No such event for this club', TennisEvents::TEXT_DOMAIN );

        //Get the bracket from attributes
        $bracketName = urldecode($my_shorts["bracketname"]);
        $bracket = $target->getBracket( $bracketName );
        if( is_null( $bracket ) ) {
            //$bracket = $target->getWinnersBracket();   
            $mess =  __('No such bracket:', TennisEvents::TEXT_DOMAIN ); 
            $mess .= $bracketName;
            return $mess;    
        }
        
        $td = new TournamentDirector( $target );
        $eventName = str_replace("\'", "&apos;", $td->getName());
        $parentName = str_replace("\'", "&apos;", $td->getParentEventName());
        $clubName = $club->getName();
        $isApproved = $bracket->isApproved();
        $numPrelimMatches = count( $bracket->getMatchesByRound(1) );
        //Get the signup for this bracket
        $this->signup = $bracket->getSignup();
        $this->log->error_log( $this->signup, "$loc: Signup");
        $numSignedUp = count( $this->signup );

        $jsData = $this->get_ajax_data();
        $jsData["clubId"] = $club->getID();
        $jsData["eventId"] = $this->eventId;
        $jsData["bracketName"] = $bracketName;
        $jsData["numSignedUp"] = $numSignedUp;
        $jsData["numPreliminary"] = $numPrelimMatches;
        $jsData["isBracketApproved"] = $isApproved ? 1:0;
        wp_enqueue_script( 'manage_signup' );   
        wp_localize_script( 'manage_signup', 'tennis_signupdata_obj', $jsData );
        
        //Signup
        $out = '';
        $out .= '<div class="signupContainer" data-eventid="' . $this->eventId . '" ';
        $out .= 'data-clubid="' . $this->clubId . '" data-bracketname="' . $bracketName . '">' . PHP_EOL;
        $out .= "<h2 class='tennis-signup-title'>{$parentName}</h2>" . PHP_EOL;
        $out .= "<h3 class='tennis-signup-title'>{$eventName}&#58;&nbsp;&lsquo;{$bracketName} Bracket&rsquo;&nbsp;Sign Up Sheet</h3>" . PHP_EOL;
        $out .= '<ul class="eventSignup tennis-event-signup">' . PHP_EOL;
        
        $templr = <<<EOT
<li id="%s" class="entrantSignupReadOnly">
<div class="entrantPosition">%d.</div>
<div class="entrantName">%s</div>
</li>
EOT;
       
    $templu = <<<EOT
<li id="%s" class="entrantSignup" data-currentpos="%d">
<div class="entrantPosition">%d.</div>
<input name="entrantName" type="text" maxlength="35" size="15" class="entrantName" data-oldname="%s" value="%s">
</li>
EOT;

        $templw = <<<EOT
<li id="%s" class="entrantSignup" data-currentpos="%d">
<div class="entrantPosition">%d.</div>
<input name="entrantName" type="text" maxlength="35" size="15" class="entrantName" data-oldname="%s" value="%s">
<input name="entrantSeed" type="number" maxlength="2" size="2" class="entrantSeed" step="any" value="%d">
<button class="button entrantDelete" type="button" id="%s">Delete</button>
</li>
EOT;

$templfile = <<<EOT
    <input
      type="file"
      id="entrant_uploads_file"
      name="entrant_uploads_file"
      accept=".xml"/>
EOT;

        $ctr = 1;
        foreach( $this->signup as $entrant ) {
            $pos = $entrant->getPosition();
            $name = str_replace("\'","&apos;", $entrant->getName());
            $nameId = str_replace( [' ',"\'","'",'&'], ['_','','',''], $entrant->getName() );
            $seed = $entrant->getSeed();
            $rname = ( $seed > 0 ) ? $name . '(' . $seed . ')' : $name;
            if( $numPrelimMatches > 0 )
                if( current_user_can( 'manage_options' ) ) {
                    $htm = sprintf( $templu, $nameId, $pos, $pos, $name, $name );
                }
                else {
                    $htm = sprintf( $templr, $nameId, $pos, $rname );
                }
            else {
                $htm = sprintf( $templw, $nameId, $pos, $pos, $name, $name, $seed, $nameId );
            }
            $out .= $htm;
        }
        $out .= '</ul>' . PHP_EOL;
        $link = get_bloginfo('url');
        if( $numPrelimMatches < 1 && current_user_can( TE_Install::MANAGE_EVENTS_CAP )  ) {
            $out .= '<button class="button addentrant" type="button" id="addEntrant">Add Entrant</button> <label class="button addentrant" for="entrant_uploads_file">Upload Entrants</label>' . PHP_EOL;
            $out .= '&nbsp;<a class="download" id="downloadtennisfile" href="' . $link . '?moniker=signupschema">(Download schema)</a><br>' . PHP_EOL;
            $out .= '<button class="button resequence" type="button" id="reseqSignup">Resequence Signup</button><br/>' . PHP_EOL;
            $out .= '<button class="button randomize" type="button" id="createPrelimRandom">Randomize and Initialize Draw</button>' . PHP_EOL;
            $out .= '<button class="button initialize" type="button" id="createPrelimNoRandom">Initialize Draw</button>' . PHP_EOL;
            $out .= $templfile . PHP_EOL;
        }
        $out .= '</div>'; //container
        $out .= '<div id="tennis-event-message"></div>';

        return $out;
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