<?php
namespace api\ajax;
use commonlib\BaseLogger;
use \WP_Error;
use \Exception;
use \TennisEvents;
use \TE_Install;
use \DateTime;
use api\TournamentDirector;
use datalayer\Event;
use datalayer\EventType;
use datalayer\GenderType;
use datalayer\MatchType;
use datalayer\Bracket;
use datalayer\Club;
use datalayer\InvalidBracketException;
use datalayer\InvalidEventException;
use cpt\TennisEventCpt;
use InvalidArgumentException;
use RuntimeException;
use TypeError;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
global $jsDataForTennisBrackets;

/** 
 * Manage brackets by responding to ajax requests from template
 * with actions to manage the Events brackets such as add new bracket
 * @class  ManageEvents
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
*/
class ManageEvents
{ 
    public const HOME_CLUBID_OPTION_NAME = 'gw_tennis_home_club';

    const ACTION    = 'manageTennisEvents';
    const NONCE     = 'manageTennisEvents';

    private $eventId = 0;
    private $errobj = null;
    private $errcode = 0;
    private $log;

    public static function register() {
        $handle = new self();
        add_action( 'wp_enqueue_scripts', array( $handle, 'registerScripts' ) );
        $handle->registerHandlers();
        
        global $jsDataForTennisBrackets;
        $jsDataForTennisBrackets = $handle->get_ajax_data();
    }

	/*************** Instance Methods ****************/
	public function __construct( ) {
	    $this->errobj = new WP_Error();	
        $this->log = new BaseLogger( true );
    }


    public function registerScripts() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log( $loc );
        
        $jsurl =  TE()->getPluginUrl() . 'js/events.js';
        wp_register_script( 'manage_brackets', $jsurl, array('jquery','jquery-ui-draggable','jquery-ui-droppable', 'jquery-ui-sortable', 'jquery-ui-tabs'), TennisEvents::VERSION, true );
        
        // $cssurl = TE()->getPluginUrl() . 'css/tennisevents.css';
        // wp_enqueue_style( 'tennis_css', $cssurl );
    }
    
    public function registerHandlers() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log($loc);

        //add_shortcode( self::SHORTCODE, array( $this, 'renderShortcode' ) );
        add_action( 'wp_ajax_' . self::ACTION, array( $this, 'performTask' ));
        add_action( 'wp_ajax_nopriv_' . self::ACTION, array( $this, 'noPrivilegesHandler' ));
    }
    
    public function noPrivilegesHandler() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log($loc);

        // Handle the ajax request
        check_ajax_referer(  self::NONCE, 'security'  );
        $this->errobj->add( $this->errcode++, __( 'You have been reported to the authorities!', TennisEvents::TEXT_DOMAIN ));
        $this->handleErrors("You've been a bad boy.");
    }
    
    /**
     * Perform the tasks as indicated by the Ajax request
     */
    public function performTask() {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $this->log->error_log("$loc");
        $this->log->error_log( $_POST, "$loc: _POST:"  );

        // Handle the ajax request
        check_ajax_referer( self::NONCE, 'security' );

        if( defined( 'DOING_AJAX' ) && ! DOING_AJAX ) {
            $this->handleErrors('Not Ajax');
        }
        
        $ok = false;
        if( current_user_can( TE_Install::MANAGE_EVENTS_CAP ) ) $ok = true;
        
        if ( !$ok ) {         
            $this->errobj->add( $this->errcode++, __( 'Insufficient privileges to modify brackets.', TennisEvents::TEXT_DOMAIN ));
        }
        
        if(count($this->errobj->errors) > 0) {
            $this->handleErrors(__("Errors were encountered", TennisEvents::TEXT_DOMAIN  ) );
        }

        $this->log->error_log("$loc: action={$_POST['action']}");
        if( self::ACTION !== $_POST['action']) return;

        $response = array();

        $data = $_POST["data"];
        $task = $data["task"];
        // $this->eventId = $data["eventId"];
        // $event = Event::get( $this->eventId );
        // $bracketName = $data["bracketName"];
        // $bracket = $event->getBracket( $bracketName );
        $returnData = $task;
        $mess = '';
        switch( $task ) {
            case 'editname':
                $mess = $this->modifyBracketName( $data );
                $returnData = $data;
                break;
            case 'addbracket':
                $mess = $this->addBracket( $data );
                $returnData = $data;
                break;
            case 'removebracket':
                $mess = $this->removeBracket( $data );
                $returnData = $data;
                break;
            case 'makecopy':
                $mess = $this->makeCopy( $data );
                $returnData = $data;
                break;
            case 'preparenextmonth':
                $mess = $this->prepareLadderNextMonth( $data );
                $returnData = $data;
                break;
            case 'addrootevent':
                $mess = $this->addRootEvent( $data );
                $returnData = $data;
                break;
            case 'modifyrooteventtype':
                $mess = $this->updateRootEventType( $data );
                $returnData = $data;
                break;
            case 'modifyrooteventtitle':
                $mess = $this->updateRootEventName( $data );
                $returnData = $data;
                break;
            case 'modifyrootstartdate':
                $mess = $this->updateRootEventStart( $data );
                $returnData = $data;
                break;
            case 'modifyrootenddate':
                $mess = $this->updateRootEventEnd( $data );
                $returnData = $data;
                break;
            case 'deleterootevent':
                $mess = $this->deleteRootEvent( $data );
                $returnData = $data;
                break;
            case 'addleafevent':
                $mess = $this->addLeafEvent( $data );
                $returnData = $data;
                break;
            case 'deleteleafevent':
                $mess = $this->deleteLeafEvent( $data );
                $returnData = $data;
                break;
            case 'modifyeventtitle':
                $mess = $this->updateLeafEventName( $data );
                $returnData = $data;
                break;
            case 'modifyminage':
            case 'modifymaxage':
            case 'modifysignupby':
            case 'modifystartdate':
            case 'modifyenddate':
                $mess = $this->updateSimpleAttribute( $data );
                $returnData = $data;
                break;
            case 'modifygender':
            case 'modifymatchtype':
            case 'modifyformat':
            case 'modifyscorerule':
                $mess = $this->updateDrawAttributes( $data );
                $returnData = $data;
                break;
            default:
                $mess =  __( 'Illegal Event task.', TennisEvents::TEXT_DOMAIN );
                $this->errobj->add( $this->errcode++, $mess );
        }

        if(count($this->errobj->errors) > 0) {
            $this->handleErrors( $mess );
        }
        
        $response["message"] = $mess;
        $response["returnData"] = $returnData;

        //Send the response
        wp_send_json_success( $response );

        // All ajax handlers die when finished
        wp_die(); 
    }

    /**
     * Add a new root event
     * @param array $data A reference to a dictionary containing event data
     * @return string A message describing success or failure
     */
    private function addRootEvent( &$data ) {
        $loc = __CLASS__. "::" .__FUNCTION__;
        $this->log->error_log($data,"$loc: data...");
        
        $task = $data["task"];
        $mess = "";
        try {
            $data["title"] =  htmlspecialchars(strip_tags($data["title"]));

            $homeClubId = esc_attr(get_option('gw_tennis_home_club', 0));
            $this->log->error_log("$loc: home Club Id={$homeClubId}");
            if (0 === $homeClubId) {
                $this->log->error_log("$loc - Home club id is not set."); 
                throw new InvalidEventException(__("Home club id is not set.",TennisEvents::TEXT_DOMAIN));
            }
            $homeClub = Club::get($homeClubId);
            if( !isset($homeClub) ) {				
                throw new InvalidEventException(__( 'Home club is not set.', TennisEvents::TEXT_DOMAIN ));
            }

            $event = new Event($data["title"]);
			//Set the parent event of the Event before setting other props
            $event->addClub($homeClub);

            $data['eventType'] = strip_tags($data['eventType']);
            $eventType = $data['eventType'];
            if(!$event->setEventType($eventType)) throw new InvalidArgumentException(__("Illegal event type '{$eventType}'", TennisEvents::TEXT_DOMAIN));
            
            $data['startDate'] = strip_tags($data['startDate']);
            $startDate = $data['startDate'];
            if(!$event->setStartDate($startDate)) throw new InvalidArgumentException(__("Illegal start date '{$startDate}'", TennisEvents::TEXT_DOMAIN));
            $dateStartDate = $event->getStartDate();

            //Event's dates must be within the given season
            $season = $this->getSeason();
            if($season !== $dateStartDate->format('Y')) {
                $this->log->error_log("$loc: '{$startDate}' is in the wrong season '{$season}'");
                throw new InvalidArgumentException(__("'{$dateStartDate->format('Y-m-d')}' is in the wrong season '{$season}'",TennisEvents::TEXT_DOMAIN));
            }

            $data['endDate'] = strip_tags($data['endDate']);
            $endDate = $data['endDate'];
            if(!$event->setEndDate($endDate)) throw new InvalidArgumentException(__("Illegal end date '{$endDate}'", TennisEvents::TEXT_DOMAIN));
            $dateEndDate = $event->getEndDate();

            $dummy = null;
            $this->validateEventDates($dummy, $dateStartDate, $dateEndDate);
 
            $endDate = $dateEndDate->format("Y-m-d");
            $event->setEndDate($endDate);

            $current_user = wp_get_current_user();
            if ( $current_user->exists() ) {
                $author = $current_user->ID;
            }
            else {
                throw new InvalidArgumentException(__("User does not exist",Tennisevents::TEXT_DOMAIN));
            }

            //Setup the corresponding custom post type
            $currentTime = new DateTime('NOW');
            $postData = array(
                        'post_title' => $data['title'],
                        'post_status' => 'publish',
                        'post_date_gmt' => $currentTime->format('Y-m-d G:i:s'),
                        'post_content' => '',
                        'post_type' => TennisEventCpt::CUSTOM_POST_TYPE,
                        'post_author' => $author,
                        'post_date'   => $currentTime->format('Y-m-d G:i:s'),
                        'post_modified' => $currentTime->format('Y-m-d G:i:s')
                        );
    
            $newPostId = wp_insert_post($postData);//NOTE: This triggers updateDB in TennisEventCpt
            if($newPostId instanceof WP_Error) {
                $mess = $newPostId->get_error_message();
                throw new InvalidEventException(__("{$mess}",TennisEvents::TEXT_DOMAIN));
            }
            update_post_meta( $newPostId, TennisEventCpt::EVENT_TYPE_META_KEY, $event->getEventType());
            update_post_meta( $newPostId, TennisEventCpt::START_DATE_META_KEY, $startDate );
            update_post_meta( $newPostId, TennisEventCpt::END_DATE_META_KEY, $endDate );
            
			$tennis_season = $event->getSeason();			
            update_post_meta($newPostId, TennisEventCpt::TENNIS_SEASON, $tennis_season);

            $event->addExternalRef((string)$newPostId);
            $event->save();
            $mess = __("Created new root event with {$event->toString()}.",TennisEvents::TEXT_DOMAIN);
        }
        catch (RuntimeException | TypeError | InvalidEventException | InvalidArgumentException $ex ) {
            $this->errobj->add( $this->errcode++, $ex->getMessage() );
            $mess = $ex->getMessage();
        }
        return $mess;
    }

    /**
     * Update the Event name and the associated custom post's title of a root event
     * NOTE: Not modifying the post's slug at this time 
     * @param array $data A reference to a dictionary containing data for update
     * @return string A message describing success or failure
     */
    private function updateRootEventName( &$data ) {
        $loc = __CLASS__. "::" .__FUNCTION__;
        $this->log->error_log($data,"$loc: data...");

        $postId =  $data['postId'];
        $eventId = $data['eventId'];
        $task = $data['task'];
        $mess = "";
        try {
            $event = Event::get($eventId);
            if(!isset($event)) {
                throw new InvalidArgumentException(__("Invalid event id.", TennisEvents::TEXT_DOMAIN));
            }

            if(!$event->isRoot()) {
                throw new InvalidArgumentException(__("Not a root event.", TennisEvents::TEXT_DOMAIN));
            }
            
            $refs = $event->getExternalRefs();
            $postId2 = 0;
            if( count($refs) > 0 ) {
                $postId2 = $refs[0];
            }
            else {	
                throw new InvalidEventException(__( 'Corresponding post is missing.', TennisEvents::TEXT_DOMAIN ));
            }     

            if($postId !== $postId2) {
                $this->log->error_log("{$loc}: Different postIds: given '{$postId}' vs database '{$postId2}'");
                throw new InvalidEventException(__( "Different postIds: given '{$postId}' vs database '{$postId2}'", TennisEvents::TEXT_DOMAIN ));
            }
            $data['newTitle'] = htmlspecialchars(strip_tags($data['newTitle']));
            $newTitle = $data['newTitle'];
            $data['oldTitle'] = htmlspecialchars(strip_tags($data['oldTitle']));
            $oldTitle = $data['oldTitle'];
            if(!$event->setName($newTitle)) throw new InvalidArgumentException(__("Could not change event name from '{$oldTitle}' to '{$newTitle}'", TennisEvents::TEXT_DOMAIN));

            $postData = array('ID'=>$postId, 'post_title'=>$newTitle);
            wp_update_post($postData);
            if (is_wp_error($postId)) {
                $errors = $postId->get_error_messages();
                foreach ($errors as $error) {
                    $this->log->error_log($error, $loc);
                    throw new InvalidArgumentException($error->getMessage());
                }
            }
            $event->save();
            $mess = __("Successfully modified event title from '{$oldTitle}' to '{$newTitle}'",TennisEvents::TEXT_DOMAIN);
        } 
        catch (RuntimeException | InvalidEventException | InvalidArgumentException $ex ) {
            $this->errobj->add( $this->errcode++, $ex->getMessage() );
            $mess = $ex->getMessage();
        }
        return $mess;
    }

    /**
     * Update the Event's EventType
     * NOTE: Not modifying the post's slug at this time 
     * @param array $data A reference to a dictionary containing data for update
     * @return string A message describing success or failure
     */
    private function updateRootEventType( &$data ) {
        $loc = __CLASS__. "::" .__FUNCTION__;
        $this->log->error_log($data,"$loc: data...");

        $postId =  $data['postId'];
        $eventId = $data['eventId'];
        $task = $data['task'];
        $mess = "";
        try {
            $event = Event::get($eventId);
            if(!isset($event)) {
                throw new InvalidEventException(__("Invalid event id.", TennisEvents::TEXT_DOMAIN));
            }

            if(!$event->isRoot()) {
                throw new InvalidEventException(__("Not a root event.", TennisEvents::TEXT_DOMAIN));
            }

            if(count($event->getChildEvents()) > 0) {
                throw new InvalidEventException(__("Cannot change event type when event already has tournaments.", TennisEvents::TEXT_DOMAIN));
            }
            
            $refs = $event->getExternalRefs();
            $postId2 = 0;
            if( count($refs) > 0 ) {
                $postId2 = $refs[0];
            }
            else {	
                throw new InvalidEventException(__( 'Corresponding post is missing.', TennisEvents::TEXT_DOMAIN ));
            }     

            if($postId !== $postId2) {
                $this->log->error_log("{$loc}: Different postIds: given '{$postId}' vs database '{$postId2}'");
                throw new InvalidEventException(__( "Different postIds: given '{$postId}' vs database '{$postId2}'", TennisEvents::TEXT_DOMAIN ));
            }

            $data['eventType'] = htmlspecialchars(strip_tags($data['eventType']));
            $eventType = $data['eventType'];
            $oldEventType = $event->getEventType();
            if(!$event->setEventType($eventType)) throw new InvalidArgumentException(__("Could not change event name from '{$oldEventType}' to '{$eventType}'", TennisEvents::TEXT_DOMAIN));
            update_post_meta($postId, TennisEventCpt::EVENT_TYPE_META_KEY, $eventType);

            $event->save();
            $mess = __("Successfully modified event type from '{$oldEventType}' to '{$eventType}'",TennisEvents::TEXT_DOMAIN);
        } 
        catch (RuntimeException | InvalidEventException | InvalidArgumentException $ex ) {
            $this->errobj->add( $this->errcode++, $ex->getMessage() );
            $mess = $ex->getMessage();
        }
        return $mess;
    }

    /**
     * Update the start date of a root event   
     * @param array $data A reference to a dictionary containing data for update
     * @return string A message describing success or failure
     */
    private function updateRootEventStart( &$data ) {
        $loc = __CLASS__. "::" .__FUNCTION__;
        $this->log->error_log($data,"$loc: data...");

        $postId =  $data['postId'];
        $eventId = $data['eventId'];
        $task = $data['task'];
        $mess = "";
        try {
            $event = Event::get($eventId);
            if(!isset($event)) {
                throw new InvalidArgumentException(__("Invalid event id.",TennisEvents::TEXT_DOMAIN));
            }
            if(!$event->isRoot()) {
                throw new InvalidArgumentException(__("Not a root event.",TennisEvents::TEXT_DOMAIN));
            }
            
            $refs = $event->getExternalRefs();
            $postId2 = 0;
            if( count($refs) > 0 ) {
                $postId2 = $refs[0];
            }
            else {	
                throw new InvalidEventException(__( 'Corresponding post is missing.', TennisEvents::TEXT_DOMAIN ));
            }     

            if($postId !== $postId2) {
                $this->log->error_log("{$loc}: Different postIds: given '{$postId}' vs database '{$postId2}'");
                throw new InvalidEventException(__( "Different postIds: given '{$postId}' vs database '{$postId2}'", TennisEvents::TEXT_DOMAIN ));
            }

            $strStartDate = $data['startDate'];
            $dateStartDate = new \DateTime($strStartDate);//Throws exception if mal formed string. php 8.3+ DateMalformedStringException
            $dateEndDate = $event->getEndDate();
            if(empty($dateEndDate)) {
                $dateEndDate = new \DateTime($strStartDate);
                $dateEndDate->modify("+5 days");
            }
            $strEndDate = $dateEndDate->format('Y-m-d');

            //Event's dates must be within the given season
            $season = $this->getSeason();
            if($season !== $dateStartDate->format('Y')) {
                $this->log->error_log("$loc: '{$strStartDate}' is in the wrong season '{$season}'");
                throw new InvalidArgumentException(__("'{$strStartDate}' is in the wrong season '{$season}'",TennisEvents::TEXT_DOMAIN));
            }

            $postSeason = get_post_meta($postId, TennisEventCpt::TENNIS_SEASON, true);
            if($postSeason !== $season) {                
                $this->log->error_log("$loc: Season mismatch between post and event. Post was '{$postSeason}' Using '{$season}'");
                update_post_meta($postId, TennisEventCpt::TENNIS_SEASON, $season);
            }

            if($dateStartDate > $dateEndDate) {
                $dateEndDate = $dateStartDate;
                $dateEndDate->modify("+2 days");//Default end date to 2 days after start date
                $strEndDate = $dateEndDate->format("Y-m-d");
            }

            if(!$event->setStartDate($strStartDate)) throw new InvalidArgumentException(__("Illegal start date '{$strStartDate}'", TennisEvents::TEXT_DOMAIN));
            if(!$event->setEndDate($strEndDate)) throw new InvalidArgumentException(__("Illegal end date '{$strEndDate}'", TennisEvents::TEXT_DOMAIN));
            update_post_meta( $postId, TennisEventCpt::START_DATE_META_KEY, $strStartDate );
            update_post_meta( $postId, TennisEventCpt::END_DATE_META_KEY, $strEndDate );
            $data['endDate'] = $strEndDate;
            
            //$postSeason = get_post_meta($postId, TennisEventCpt::TENNIS_SEASON, true);
            update_post_meta($postId, TennisEventCpt::TENNIS_SEASON, $season);

            $mess = __("Successfully updated the start date", TennisEvents::TEXT_DOMAIN);
            $event->save();
        } 
        catch (RuntimeException | TypeError | InvalidEventException | InvalidArgumentException $ex ) {
            $this->errobj->add( $this->errcode++, $ex->getMessage() );
            $mess = $ex->getMessage();
        }
        return $mess;
    }
    
    /**
     * Update the end date of a root event   
     * @param array $data A reference to a dictionary containing data for update
     * @return string A message describing success or failure
     */
    private function updateRootEventEnd( &$data ) {
        $loc = __CLASS__. "::" .__FUNCTION__;
        $this->log->error_log($data,"$loc: data...");

        $postId =  $data['postId'];
        $eventId = $data['eventId'];
        $task = $data['task'];
        $mess = "";
        try {
            $event = Event::get($eventId);
            if(!isset($event)) {
                throw new InvalidEventException(__("Invalid event id.",TennisEvents::TEXT_DOMAIN));
            }
            if(!$event->isRoot()) {
                throw new InvalidEventException(__("Not a root event.",TennisEvents::TEXT_DOMAIN));
            }
            
            $refs = $event->getExternalRefs();
            $postId2 = 0;
            if( count($refs) > 0 ) {
                $postId2 = $refs[0];
            }
            else {	
                throw new InvalidEventException(__( 'Corresponding post is missing.', TennisEvents::TEXT_DOMAIN ));
            }     

            if($postId !== $postId2) {
                $this->log->error_log("{$loc}: Different postIds: given '{$postId}' vs database '{$postId2}'");
                throw new InvalidEventException(__( "Different postIds: given '{$postId}' vs database '{$postId2}'", TennisEvents::TEXT_DOMAIN ));
            }

            $strEndDate = $data['endDate'];
            $dateEndDate = new \DateTime($strEndDate);
            $dateStartDate = $event->getStartDate();
            if(empty($dateStartDate)) {
                throw new InvalidArgumentException(__("Start date must be set first.",TennisEvents::TEXT_DOMAIN));
            }
            $strStartDate = $dateStartDate->format('Y-m-d');

            //Event's start date must be within the given season
            $season = $this->getSeason();
            if($season !== $dateStartDate->format('Y')) {
                throw new InvalidArgumentException(__("Season '{$season}' does not match year in '{$strStartDate}'.",TennisEvents::TEXT_DOMAIN));
            }
            $postSeason = get_post_meta($postId, TennisEventCpt::TENNIS_SEASON, true);
            if($postSeason !== $season) {                
                $this->log->error_log("$loc: Season mismatch between post and event. Was '{$postSeason}' Using '{$season}'");
                update_post_meta($postId, TennisEventCpt::TENNIS_SEASON, $season);
            }

            $this->validateEventDates(null, $dateStartDate, $dateEndDate);
            $strEndDate = $dateEndDate->format('Y-m-d');

            if(!$event->setEndDate($strEndDate)) throw new InvalidArgumentException(__("Illegal end date '{$strEndDate}'", TennisEvents::TEXT_DOMAIN));
            update_post_meta( $postId, TennisEventCpt::END_DATE_META_KEY, $strEndDate );    

            $mess = __("Successfully updated the end date", TennisEvents::TEXT_DOMAIN);
            $event->save();
        } 
        catch (RuntimeException | TypeError | InvalidEventException | InvalidArgumentException $ex ) {
            $this->errobj->add( $this->errcode++, $ex->getMessage() );
            $mess = $ex->getMessage();
        }
        return $mess;
    }

    /**
     * Delete a root event. Must not have any child events.
     * @param array $data A reference to a dictionary containing data for update
     * @return string A message describing success or failure
     */
    private function deleteRootEvent( &$data ) {
        $loc = __CLASS__. "::" .__FUNCTION__;
        $this->log->error_log($data,"$loc: data...");
        
        $eventId = $data["eventId"];
        $task = $data["task"];
        $mess = "";
        try {
            $event = Event::get($eventId);
            if(!isset($event)) {
                throw new InvalidEventException(__("Invalid event id.",TennisEvents::TEXT_DOMAIN));
            }
            if(!$event->isRoot()) {
                throw new InvalidEventException(__("Not a root event.",TennisEvents::TEXT_DOMAIN));
            }
            if(count($event->getChildEvents()) > 0 ) {
                throw new InvalidEventException(__("Cannot have child events.",TennisEvents::TEXT_DOMAIN));
            }

            $refs = $event->getExternalRefs();
            $postId = 0;
            if( count($refs) > 0 ) {
                $postId = $refs[0];
            }
            else {	
                throw new InvalidEventException(__( 'Corresponding post is missing.', TennisEvents::TEXT_DOMAIN ));
            }

            wp_delete_post($postId, true);
            $event->delete();
            $mess = __("Successfully deleted root event {$event->toString()}", TennisEvents::TEXT_DOMAIN );
        }
        catch(RuntimeException | InvalidEventException | InvalidArgumentException $ex) {
            $this->errobj->add( $this->errcode++, $ex->getMessage() );
            $mess = $ex->getMessage();
        }
        return $mess;
    }

    /**
     * Delete a  leaf event. Must not have any Brackets that have started matches.
     * @param array $data A reference to a dictionary containing data for update
     * @return string A message describing success or failure
     */
    private function deleteLeafEvent( &$data ) {
        $loc = __CLASS__. "::" .__FUNCTION__;
        $this->log->error_log($data,"$loc: data...");
        
        $eventId = $data["eventId"];
        $task = $data["task"];
        $mess = "";
        try {
            $event = Event::get($eventId);
            if(!isset($event)) {
                throw new InvalidEventException(__("Invalid event id.",TennisEvents::TEXT_DOMAIN));
            }
            if($event->isRoot()) {
                throw new InvalidEventException(__("Not a leaf event.",TennisEvents::TEXT_DOMAIN));
            }

            $td = new TournamentDirector($event);
            $brackets = $td->getBrackets();
            //All Brackets must not be approved and must not have started
            foreach( $brackets as $bracket ) {
                if($bracket->isApproved() || (0 < $td->hasStarted( $bracket->getName()) ) ) {
                    throw new InvalidEventException( __("Please reset the draw for '{$bracket->getName()}' first.",TennisEvents::TEXT_DOMAIN) );
                }
            }
            $refs = $event->getExternalRefs();
            $postId = 0;
            if( count($refs) > 0 ) {
                $postId = $refs[0];
            }
            else {	
                throw new InvalidEventException(__( 'Corresponding post is missing.', TennisEvents::TEXT_DOMAIN ));
            }

            wp_delete_post($postId, true);
            $event->delete();
            $mess = __("Successfully deleted leaf event {$event->toString()}", TennisEvents::TEXT_DOMAIN );
        }
        catch(RuntimeException | InvalidEventException | InvalidArgumentException $ex) {
            $this->errobj->add( $this->errcode++, $ex->getMessage() );
            $mess = $ex->getMessage();
        }

        return $mess;
    }

    /**
     * Add a new leaf event (usually a tournament)
     * @param array $data A reference to a dictionary containing data for update
     * @return string A message describing success or failure
     */
    private function addLeafEvent( &$data ) {
        $loc = __CLASS__. "::" .__FUNCTION__;
        $this->log->error_log($data,"$loc: data...");
        
        $parentId = $data["parentId"];
        $task = $data["task"];
        $mess = "";
        try {
            $parentEvent = Event::get($parentId);
            if( !$parentEvent->isRoot() ) {
                throw new InvalidEventException(__("Event id given must be for a root event .",TennisEvents::TEXT_DOMAIN));
            }
 
            if( $parentEvent->getEventType() === EventType::LADDER && count($parentEvent->getChildEvents()) > 0) {

                throw new InvalidEventException(__("Cannot add more than one event for type '{EventType::LADDER}'.",TennisEvents::TEXT_DOMAIN));
            }

            $homeClubId = esc_attr(get_option('gw_tennis_home_club', 0));
            $this->log->error_log("$loc: home Club Id={$homeClubId}");
            if (0 === $homeClubId) {
                $this->log->error_log("$loc - Home club id is not set."); 
                throw new InvalidEventException(__("Home club id is not set.",TennisEvents::TEXT_DOMAIN));
            }
            $homeClub = Club::get($homeClubId);
            if( !isset($homeClub) ) {				
                throw new InvalidEventException(__( 'Home club is not set.', TennisEvents::TEXT_DOMAIN ));
            }
            $data['title'] = htmlspecialchars_decode(strip_tags($data['title']));
            $event = new Event($data["title"]);
			//Set the parent event of the Event before setting other props
			$event->setParent($parentEvent);
            $event->addClub($homeClub);
            $refs = $parentEvent->getExternalRefs();
            $parentPostId = 0;
            if( count($refs) > 0 ) {
                $parentPostId = $refs[0];
            }
            else {	
                throw new InvalidEventException(__( 'Parent post id is missing.', TennisEvents::TEXT_DOMAIN ));
            }

            //TODO Ensure that start and end dates conform to parent event's
            $data['startDate'] = strip_tags($data['startDate']);
            $startDate = $data['startDate'];
            if(!$event->setStartDate($startDate)) throw new InvalidArgumentException(__("Illegal start date '{$startDate}'", TennisEvents::TEXT_DOMAIN));
            $dateStartDate = $event->getStartDate();
            
            $data['signupBy'] = strip_tags($data['signupBy']);
            $signupBy = $data['signupBy'];
            if(!$event->setSignupBy($signupBy)) throw new InvalidArgumentException(__("Illegal signup by date '{$signupBy}'", TennisEvents::TEXT_DOMAIN));
            $dateSignupBy = $event->getSignupBy();

            $data['endDate'] = strip_tags($data['endDate']);
            $endDate = $data['endDate'];
            if(!$event->setEndDate($endDate)) throw new InvalidArgumentException(__("Illegal end date '{$endDate}'", TennisEvents::TEXT_DOMAIN));
            $dateEndDate = $event->getEndDate();

            $this->validateEventDates($dateSignupBy, $dateStartDate, $dateEndDate);
            $signupBy = $dateSignupBy->format("Y-m-d");
            $data['signupBy'] = $signupBy;
            $event->setSignupBy($signupBy);
            $startDate = $dateStartDate->format("Y-m-d");
            $data['startDate'] =  $startDate;
            $endDate = $dateEndDate->format("Y-m-d");
            $data['endDate'] = $endDate;
            $event->setEndDate($endDate);
            
            //Event's start date must be within the given season
            $season = $this->getSeason();
            if($season !== $dateStartDate->format('Y')) {
                throw new InvalidArgumentException(__("Season '{$season}' does not match year in '{$dateStartDate->format("Y-m-d")}'.",TennisEvents::TEXT_DOMAIN));
            }

            $gender = $data['gender'];
            if( !$event->setGenderType($data['gender'])) throw new InvalidArgumentException(__("Illegal gender '{$gender}'", TennisEvents::TEXT_DOMAIN));
            $matchType = $data['matchType'];
            if(!$event->setMatchType($matchType)) throw new InvalidArgumentException(__("Illegal match type '{$matchType}'", TennisEvents::TEXT_DOMAIN));
            $eventFormat = $data['format'];
            if(!$event->setFormat($eventFormat)) throw new InvalidArgumentException(__("Illegal format '{$eventFormat}'", TennisEvents::TEXT_DOMAIN));
            $scoreType = $data['scoreType'];
            if(!$event->setScoreType($scoreType)) throw new InvalidArgumentException(__("Illegal score rule '{$scoreType}'", TennisEvents::TEXT_DOMAIN));

            $data["title"] =  htmlspecialchars(strip_tags($data["title"]));
 
            
            //Ladder events must have the month as the name
            if($parentEvent->getEventType() === EventType::LADDER ) {
                $data['title']=$dateStartDate->format('F');
            }
            elseif(empty($data['title'])) {
                $genderDisp = GenderType::AllTypes()[$gender] . " " . MatchType::AllTypes()[$matchType];
                $data['title'] = $genderDisp;
            }
            $event->setName($data['title']);

            $current_user = wp_get_current_user();
            if ( $current_user->exists() ) {
                $author = $current_user->ID;
            }
            else {
                throw new InvalidArgumentException(__("User does not exist",Tennisevents::TEXT_DOMAIN));
            }

            //Setup the corresponding custom post type
            $currentTime = new DateTime('NOW');
            $postData = array(
                        'post_title' => $data['title'],
                        'post_status' => 'publish',
                        'post_date_gmt' => $currentTime->format('Y-m-d G:i:s'),
                        'post_content' => '',
                        'post_type' => TennisEventCpt::CUSTOM_POST_TYPE,
                        'post_author' => $author,
                        'post_date'   => $currentTime->format('Y-m-d G:i:s'),
                        'post_modified' => $currentTime->format('Y-m-d G:i:s')
                        );
    
            $newPostId = wp_insert_post($postData);//NOTE: This triggers updateDB in TennisEventCpt
            if($newPostId instanceof WP_Error) {
                $mess = $newPostId->get_error_message();
                throw new InvalidEventException(__("{$mess}",TennisEvents::TEXT_DOMAIN));
            }
            update_post_meta( $newPostId, TennisEventCpt::PARENT_EVENT_META_KEY, $parentPostId );
            update_post_meta( $newPostId, TennisEventCpt::SIGNUP_BY_DATE_META_KEY, $signupBy );
            update_post_meta( $newPostId, TennisEventCpt::START_DATE_META_KEY, $startDate );
            update_post_meta( $newPostId, TennisEventCpt::END_DATE_META_KEY, $endDate );
            update_post_meta( $newPostId, TennisEventCpt::GENDER_TYPE_META_KEY, $gender );
            update_post_meta( $newPostId, TennisEventCpt::EVENT_FORMAT_META_KEY, $eventFormat );
            update_post_meta( $newPostId, TennisEventCpt::MATCH_TYPE_META_KEY, $matchType );
            update_post_meta( $newPostId, TennisEventCpt::SCORE_TYPE_META_KEY, $scoreType );
            
			$tennis_season = $event->getSeason();			
            update_post_meta($newPostId, TennisEventCpt::TENNIS_SEASON, $tennis_season);

            //Default values
            $event->setMaxAge(99);
            $event->setMinAge(5);
            update_post_meta($newPostId, TennisEventCpt::AGE_MAX_META_KEY, 99);
            update_post_meta($newPostId, TennisEventCpt::AGE_MIN_META_KEY, 5);

            $event->addExternalRef((string)$newPostId);
            $event->save();
            $newEventId = $event->getID();
            $mess = __("Created new leaf event with id={$newEventId}.",TennisEvents::TEXT_DOMAIN);
        }
        catch (RuntimeException | TypeError | InvalidEventException | InvalidArgumentException $ex ) {
            $this->errobj->add( $this->errcode++, $ex->getMessage() );
            $mess = $ex->getMessage();
        }
        return $mess;
    }

    /**
     * Update the Event name and the associated custom post's title of a leaf event
     * NOTE: Not modifying the post's slug at this time 
     * @param array $data A reference to a dictionary containing data for update
     * @return string A message describing success or failure
     */
    private function updateLeafEventName( &$data ) {
        $loc = __CLASS__. "::" .__FUNCTION__;
        $this->log->error_log($data,"$loc: data...");

        $postId =  $data['postId'];
        $eventId = $data['eventId'];
        $task = $data['task'];
        $mess = "";
        try {
            $event = Event::get($eventId);
            if(!isset($event)) {
                throw new InvalidEventException(__("Invalid event id.", TennisEvents::TEXT_DOMAIN));
            }
            
            $refs = $event->getExternalRefs();
            $postId2 = 0;
            if( count($refs) > 0 ) {
                $postId2 = $refs[0];
            }
            else {	
                throw new InvalidEventException(__( 'Corresponding post is missing.', TennisEvents::TEXT_DOMAIN ));
            }     

            if($postId !== $postId2) {
                $this->log->error_log("{$loc}: Different postIds: given '{$postId}' vs database '{$postId2}'");
                throw new InvalidEventException(__( "Different postIds: given '{$postId}' vs database '{$postId2}'", TennisEvents::TEXT_DOMAIN ));
            }

            $gender = $event->getGenderType();
            $matchType = $event->getMatchType();
            $data['newTitle'] = htmlspecialchars(strip_tags($data['newTitle']));
            if(empty($data['newTitle'])) {
                $data['newTitle'] = GenderType::AllTypes()[$gender] . " " . MatchType::AllTypes()[$matchType];
            }
            $eventType = $event->getParent()->getEventType();
            if($eventType === EventType::LADDER) {
                $data['newTitle'] = $event->getStartDate()->format('F');
            }
            $newTitle = $data['newTitle'];
            $oldTitle = strip_tags($data['oldTitle']);
            if(!$event->setName($newTitle)) throw new InvalidArgumentException(__("Could not change event name from '{$oldTitle}' to '{$newTitle}'", TennisEvents::TEXT_DOMAIN));

            $postData = array('ID'=>$postId, 'post_title'=>$newTitle);
            wp_update_post($postData);
            if (is_wp_error($postId)) {
                $errors = $postId->get_error_messages();
                foreach ($errors as $error) {
                    $this->log->error_log($error, $loc);
                    throw new InvalidArgumentException($error->getMessage());
                }
            }
            $event->save();
            $mess = __("Successfully modified '{$oldTitle}' to '{$newTitle}'",TennisEvents::TEXT_DOMAIN);
        } 
        catch (RuntimeException | InvalidEventException | InvalidArgumentException $ex ) {
            $this->errobj->add( $this->errcode++, $ex->getMessage() );
            $mess = $ex->getMessage();
        }
        return $mess;
    }

    /**
     * Update simple attributes of a leaf event which do not affect the draw   
     * @param array $data A reference to a dictionary containing data for update
     * @return string A message describing success or failure
     */
    private function updateSimpleAttribute( &$data ) {
        $loc = __CLASS__. "::" .__FUNCTION__;
        $this->log->error_log($data,"$loc: data...");

        $postId =  $data['postId'];
        $eventId = $data['eventId'];
        $task = $data['task'];
        $mess = "";
        try {
            //TODO Ensure that start and end dates conform to parent event's
            //TODO Evnts cannot be created for the next season ... i.e. calendar year
            $event = Event::get($eventId);
            if(!isset($event)) {
                throw new InvalidEventException(__("Invalid event id.", TennisEvents::TEXT_DOMAIN));
            }
            
            $refs = $event->getExternalRefs();
            $postId2 = 0;
            if( count($refs) > 0 ) {
                $postId2 = $refs[0];
            }
            else {	
                throw new InvalidEventException(__( 'Corresponding post is missing.', TennisEvents::TEXT_DOMAIN ));
            }     

            if($postId !== $postId2) {
                $this->log->error_log("{$loc}: Different postIds: given '{$postId}' vs database '{$postId2}'");
                throw new InvalidEventException(__( "Different postIds: given '{$postId}' vs database '{$postId2}'", TennisEvents::TEXT_DOMAIN ));
            }

            switch($task) {
                case 'modifyminage':
                    $minAge = $data['minAge'];
                    if(!$event->setMinAge((int)$minAge)) throw new InvalidArgumentException(__("Illegal min age '{$minAge}'", TennisEvents::TEXT_DOMAIN));
                    update_post_meta( $postId, TennisEventCpt::AGE_MIN_META_KEY, $minAge );
                    $mess = __("Successfully updated the min age",TennisEvents::TEXT_DOMAIN);
                    break;
                case 'modifymaxage':
                    $maxAge = $data['maxAge'];
                    if(!$event->setMaxAge((int)$maxAge)) throw new InvalidArgumentException(__("Illegal max age '{$maxAge}'", TennisEvents::TEXT_DOMAIN));
                    update_post_meta( $postId, TennisEventCpt::AGE_MAX_META_KEY, $maxAge );
                    $mess = __("Successfully updated the max age", TennisEvents::TEXT_DOMAIN);
                    break;
                case 'modifysignupby':
                    $signupBy = $data['signupBy'];
                    $dateSignupBy = new \DateTime($signupBy);
                    $dateStartDate = $event->getStartDate();
                    $dateEndDate = $event->getEndDate();
                    $this->validateEventDates( $dateSignupBy, $dateStartDate, $dateEndDate );
                    
                    //Event's start date must be within the given season
                    $season = $this->getSeason();
                    if($season !== $dateStartDate->format('Y')) {
                        throw new InvalidArgumentException(__("Season '{$season}' does not match year in '{$dateStartDate->format('Y-m-d')}'.",TennisEvents::TEXT_DOMAIN));
                    }
                    $postSeason = get_post_meta($postId, TennisEventCpt::TENNIS_SEASON, true);
                    if($postSeason !== $season) {                
                        $this->log->error_log("$loc: Season mismatch between post and event. Was '{$postSeason}' Using '{$season}'");
                        update_post_meta($postId, TennisEventCpt::TENNIS_SEASON, $season);
                    }
                    $event->setSignupBy($dateSignupBy->format('Y-m-d'));
                    $event->setStartDate($dateStartDate->format('Y-m-d'));
                    $event->setEndDate($dateEndDate->format('Y-m-d'));
                    update_post_meta( $postId, TennisEventCpt::SIGNUP_BY_DATE_META_KEY, $dateSignupBy->format('Y-m-d') );
                    update_post_meta( $postId, TennisEventCpt::START_DATE_META_KEY, $dateStartDate->format('Y-m-d') );
                    update_post_meta( $postId, TennisEventCpt::END_DATE_META_KEY, $dateEndDate->format('Y-m-d') );
                    $data['signupBy'] = $dateSignupBy->format('Y-m-d');
                    $data['startDate'] = $dateStartDate->format('Y-m-d');
                    $data['endDate'] = $dateEndDate->format('Y-m-d');
                    $mess = __("Successfully updated signup by", TennisEvents::TEXT_DOMAIN);
                    break;
                case 'modifystartdate':
                    $startDate = $data['startDate'];
                    $dateStartDate = new \DateTime($startDate);
                    $dateSignupBy = $event->getSignupBy();
                    $dateEndDate = $event->getEndDate();
                    $this->validateEventDates( $dateSignupBy, $dateStartDate, $dateEndDate );
                    //Event's start date must be within the given season
                    $season = $this->getSeason();
                    if($season !== $dateStartDate->format('Y')) {
                        throw new InvalidArgumentException(__("Season '{$season}' does not match year in '{$dateStartDate->format('Y-m-d')}'.",TennisEvents::TEXT_DOMAIN));
                    }
                    $postSeason = get_post_meta($postId, TennisEventCpt::TENNIS_SEASON, true);
                    if($postSeason !== $season) {                
                        $this->log->error_log("$loc: Season mismatch between post and event. Was '{$postSeason}' Using '{$season}'");
                        update_post_meta($postId, TennisEventCpt::TENNIS_SEASON, $season);
                    }
                    $event->setStartDate($dateStartDate->format('Y-m-d'));
                    $event->setSignupBy($dateSignupBy->format('Y-m-d'));
                    $event->setEndDate($dateEndDate->format('Y-m-d'));
                    update_post_meta( $postId, TennisEventCpt::SIGNUP_BY_DATE_META_KEY, $dateSignupBy->format('Y-m-d') );
                    update_post_meta( $postId, TennisEventCpt::START_DATE_META_KEY, $dateStartDate->format('Y-m-d') );
                    update_post_meta( $postId, TennisEventCpt::END_DATE_META_KEY, $dateEndDate->format('Y-m-d') );
                    $data['signupBy'] = $dateSignupBy->format('Y-m-d');
                    $data['startDate'] = $dateStartDate->format('Y-m-d');
                    $data['endDate'] = $dateEndDate->format('Y-m-d');
                    $mess = __("Successfully updated the start date", TennisEvents::TEXT_DOMAIN);
                    break;
                case 'modifyenddate':
                    $endDate = $data['endDate'];
                    $dateEndDate = new \DateTime($endDate);
                    $dateSignupBy = $event->getSignupBy();
                    $dateStartDate = $event->getStartDate();
                    $this->validateEventDates( $dateSignupBy, $dateStartDate, $dateEndDate );
                    $season = $this->getSeason();
                    if($season !== $dateStartDate->format('Y')) {
                        throw new InvalidArgumentException(__("Season '{$season}' does not match year in '{$dateStartDate->format('Y-m-d')}'.",TennisEvents::TEXT_DOMAIN));
                    }
                    $postSeason = get_post_meta($postId, TennisEventCpt::TENNIS_SEASON, true);
                    if($postSeason !== $season) {                
                        $this->log->error_log("$loc: Season mismatch between post and event. Was '{$postSeason}' Using '{$season}'");
                        update_post_meta($postId, TennisEventCpt::TENNIS_SEASON, $season);
                    }
                    $event->setStartDate($dateStartDate->format('Y-m-d'));
                    $event->setSignupBy($dateSignupBy->format('Y-m-d'));
                    $event->setEndDate($dateEndDate->format('Y-m-d'));
                    update_post_meta( $postId, TennisEventCpt::SIGNUP_BY_DATE_META_KEY, $dateSignupBy->format('Y-m-d') );
                    update_post_meta( $postId, TennisEventCpt::START_DATE_META_KEY, $dateStartDate->format('Y-m-d') );
                    update_post_meta( $postId, TennisEventCpt::END_DATE_META_KEY, $dateEndDate->format('Y-m-d') );
                    $data['signupBy'] = $dateSignupBy->format('Y-m-d');
                    $data['startDate'] = $dateStartDate->format('Y-m-d');
                    $data['endDate'] = $dateEndDate->format('Y-m-d');
                    $mess = __("Successfully updated the end date", TennisEvents::TEXT_DOMAIN);
                    break;
                default:
                throw new InvalidArgumentException(__("Illegal task '{$task}' for {$loc}", TennisEvents::TEXT_DOMAIN));
            }
            $event->save();
        } 
        catch (RuntimeException | TypeError | InvalidEventException | InvalidArgumentException $ex ) {
            $this->errobj->add( $this->errcode++, $ex->getMessage() );
            $mess = $ex->getMessage();
        }
        return $mess;
    }
    
    /**
     * Update the the charactistics of the event that affect the draw     
     * @param array $data A reference to a dictionary containing data for update
     * @return string A message describing success or failure
     */
    private function updateDrawAttributes( &$data ) {
        $loc = __CLASS__. "::" .__FUNCTION__;
        $this->log->error_log($data,"$loc: data...");

        $postId = $data['postId'];
        $eventId = $data['eventId'];
        $task = $data['task'];
        $mess = "";
        try {
            if( !current_user_can( TE_Install::MANAGE_EVENTS_CAP ) ) {
                throw new RuntimeException(__("Unauthorized for this operation.", TennisEvents::TEXT_DOMAIN ) );
            }

            //TODO: nonces??
            $event = Event::get($eventId);
            if(!isset($event)) {
                throw new InvalidEventException(__("Invalid event id.", TennisEvents::TEXT_DOMAIN));
            }
            
            $refs = $event->getExternalRefs();
            $postId2 = 0;
            if( count($refs) > 0 ) {
                $postId2 = $refs[0];
            }
            else {	
                throw new InvalidEventException(__( 'Corresponding post is missing.', TennisEvents::TEXT_DOMAIN ));
            }     

            if($postId !== $postId2) {
                $this->log->error_log("{$loc}: Different postIds: given '{$postId}' vs database '{$postId2}'");
                throw new InvalidEventException(__( "Different postIds: given '{$postId}' vs database '{$postId2}'", TennisEvents::TEXT_DOMAIN ));
            }

            $td = new TournamentDirector( $event );
            $brackets = $td->getBrackets();

            //All Brackets must not be approved and must not have started
            foreach( $brackets as $bracket ) {
                if($bracket->isApproved() || (0 < $td->hasStarted( $bracket->getName()) ) ) {
                    throw new InvalidEventException( __('Cannot modify the event because at least one draw is published.',TennisEvents::TEXT_DOMAIN) );
                }
            }

            //Now update the field
            switch($task) {
                case 'modifygender':
                    $gender = $data['gender'];
                    if( !$event->setGenderType($gender)) throw new InvalidArgumentException(__("Illegal gender '{$gender}'", TennisEvents::TEXT_DOMAIN));
                    update_post_meta( $postId, TennisEventCpt::GENDER_TYPE_META_KEY, $gender );
                    $mess = __("Successfully updated gender",TennisEvents::TEXT_DOMAIN);
                    break;
                case 'modifymatchtype':
                    $matchType = $data['matchType'];
                    if(!$event->setMatchType($matchType)) throw new InvalidArgumentException(__("Illegal match type '{$matchType}'", TennisEvents::TEXT_DOMAIN));
                    update_post_meta( $postId, TennisEventCpt::MATCH_TYPE_META_KEY, $matchType );
                    $mess = __("Successfully updated match type",TennisEvents::TEXT_DOMAIN);
                    break;
                case 'modifyformat':
                    $eventFormat = $data['eventFormat'];
                    if(!$event->setFormat($eventFormat)) throw new InvalidArgumentException(__("Illegal format '{$eventFormat}'", TennisEvents::TEXT_DOMAIN));
                    update_post_meta($postId, TennisEventCpt::EVENT_FORMAT_META_KEY, $eventFormat);
                    $mess = __("Successfully updated the format",TennisEvents::TEXT_DOMAIN);
                    break;
                case 'modifyscorerule':
                    $scoreType = $data['scoreType'];
                    if(!$event->setScoreType($scoreType)) throw new InvalidArgumentException(__("Illegal score rule '{$scoreType}'", TennisEvents::TEXT_DOMAIN));
                    update_post_meta( $postId, TennisEventCpt::SCORE_TYPE_META_KEY, $scoreType );
                    $mess = __("Successfully updated the score type",TennisEvents::TEXT_DOMAIN);
                    break;
                default:
                throw new InvalidArgumentException(__("Illegal task '{$task}' for {$loc}", TennisEvents::TEXT_DOMAIN));
            }
            $event->save();
        } 
        catch (RuntimeException | InvalidEventException | InvalidArgumentException $ex ) {
            $this->errobj->add( $this->errcode++, $ex->getMessage() );
            $mess = $ex->getMessage();
        }
        return $mess;
    }

    /**
     * Change and existing bracket's name
     */
    private function modifyBracketName( &$data ) {
        $loc = __CLASS__. "::" .__FUNCTION__;
        $this->log->error_log($data,"$loc: data...");

        $eventId = $data["eventId"];
        $newBracketName = strip_tags( htmlspecialchars( $data["bracketName"] ));
        $oldBracketName = strip_tags( htmlspecialchars( $data['oldBracketName'] ));
        $bracketNum = $data["bracketNum"];
        $mess = "";
        try {
            $event = Event::get( $eventId );
            if(!isset($event)) {
                throw new InvalidEventException(__("Invalid event id.", TennisEvents::TEXT_DOMAIN));
            }
            
            $td = new TournamentDirector( $event );
            $bracket = $td->getBracket( $bracketNum );
            if( is_null( $bracket ) ) {
                throw new InvalidBracketException(__("No such bracket", TennisEvents::TEXT_DOMAIN) );
            }
            $mess = "Changed bracket name from '{$oldBracketName}' to '{$newBracketName}'";
            $bracket->setName($newBracketName);
            $data["signuplink"] = $td->getPermaLink() . "?mode=signup&bracket={$bracket->getName()}";
            $data["drawlink"]   = $td->getPermaLink() . "?mode=draw&bracket={$bracket->getName()}";
            $td->save();
        } 
        catch (RuntimeException | InvalidEventException | InvalidBracketException $ex ) {
            $this->errobj->add( $this->errcode++, $ex->getMessage() );
            $mess = $ex->getMessage();
        }
        return $mess;
    }

    /**
     * Add a new bracket to the event
     */
    private function addBracket( &$data ) {
        $loc = __CLASS__. "::" .__FUNCTION__;
        $this->log->error_log($data,"$loc: data...");

        $eventId = $data["eventId"];
        $newBracketName = strip_tags( htmlspecialchars( $data["bracketName"] ));
        try {
            $event = Event::get( $eventId );
            if(!isset($event)) {
                throw new InvalidEventException(__("Invalid event id.", TennisEvents::TEXT_DOMAIN));
            }

            $td = new TournamentDirector( $event );
            $bracket = $td->addBracket( $newBracketName ); //automatically saves
            if( is_null( $bracket ) ) {
                throw new InvalidBracketException(__("Bracket not created or found", TennisEvents::TEXT_DOMAIN) );
            }
            $data["bracketNum"] = $bracket->getBracketNumber();
            $data["bracketName"] = $bracket->getName();
            $data["imgsrc"] = TE()->getPluginUrl() . 'img/removeIcon.gif';
 
            $data["signuplink"] = $td->getPermaLink() . "?mode=signup&bracket={$bracket->getName()}";
            $data["drawlink"]   = $td->getPermaLink() . "?mode=draw&bracket={$bracket->getName()}";

            $mess = "Successfully Added bracket '{$newBracketName}' (bracket number '{$bracket->getBracketNumber()}')";
        } 
        catch (RuntimeException | InvalidEventException | InvalidBracketException $ex ) {
            $this->errobj->add( $this->errcode++, $ex->getMessage() );
            $mess = $ex->getMessage();
        }
        return $mess;
    } 

    /**
     * Remove a bracket by name
     */
    private function removeBracket( &$data ) {
        $loc = __CLASS__. "::" .__FUNCTION__;
        $this->log->error_log($data,"$loc: data...");

        $eventId = $data["eventId"];
        $bracketNum    = $data["bracketNum"];
        $bracketName = strip_tags( htmlspecialchars( $data["bracketName"] ));
        $event = Event::get( $eventId );
        try {
            $event = Event::get( $eventId );
            if(!isset($event)) {
                throw new InvalidEventException(__("Invalid event id.", TennisEvents::TEXT_DOMAIN));
            }

            $td = new TournamentDirector( $event );
            $bracket = $td->getBracket( $bracketNum );
            //Cannot schedule preliminary rounds if matches have already started
            if($bracket->isApproved() || (0 < $td->hasStarted( $bracket->getName()) ) ) {
                throw new InvalidEventException( __('Cannot remove the bracket because the draw is published.') );
            }
            $event = Event::get( $eventId );
            $td = new TournamentDirector( $event );
            $td->removeBracket( $bracketName );
            $td->save();
            $mess = "Removed bracket '{$bracketName}'";
        } 
        catch (RuntimeException | InvalidEventException | InvalidEventException $ex ) {
            $this->errobj->add( $this->errcode++, $ex->getMessage() );
            $mess = $ex->getMessage();
        }
        return $mess;
    } 
    
    /**
     * Make a copy of an Event and it's doppleganger custom post type identified by is ID     
     * @param array $data A reference to an array of event/match identifiers and new visitor player name
     * @return string A message describing success or failure
     */
    private function makeCopy( &$data ) {
        //Tennis Event
        $this->eventId = $data["eventId"];
        $postId = $data["postId"];
        $mess          = __("Copy succeeded for post id='{$postId}' and event id='{$this->eventId}'.", TennisEvents::TEXT_DOMAIN );
        try {
           $event = Event::get($this->eventId);
           if(!isset($event)) {
               throw new InvalidEventException(__("Invalid event id.", TennisEvents::TEXT_DOMAIN));
           }
           $copy = new Event('','',$event);//copy constructor
   
           //Custom post type
           $testId = Event::getExtEventRefByEventId($this->eventId);
           if( $testId !== $postId ) {
               throw new InvalidEventException("Custom post id={$postId} does not match database ext ref={$testId}");
           }

           $eventCPT = get_post($postId);
           if(empty($eventCPT)) {
               throw new InvalidEventException("Could not find custom post for event id={$this->eventId} with post id={$postId}");
           }
   
           $copyCptId = $this->copyPost( $eventCPT->ID );
           if( 0 === $copyCptId ) {
               throw new InvalidEventException("Could not duplicate custom post for event id={$this->eventId}");
           }
           $copyCpt = get_post($copyCptId);
   
           $copy->addExternalRef((string)$copyCptId);
           $copy->save();
       }
       catch( RuntimeException | InvalidEventException $ex ) {
           $this->errobj->add( $this->errcode++, $ex->getMessage() );
           $mess = $ex->getMessage();
       }
       return $mess;
   }
   
    /**
    * Make a copy of an Event and it's doppleganger custom post type identified by is ID     
    * @param array $data A reference to an array of event/match identifiers and new visitor player name
    * @return string A message describing success or failure
    */
    private function prepareLadderNextMonth( &$data ) {
       $loc = __CLASS__ . "::" . __FUNCTION__;
       $this->log->error_log($data,$loc);

       //Tennis Event
       $this->eventId = (int)$data["eventId"]; //The parent event's id
       $mess          = __("Prepare next month for parent event id '{$this->eventId}' succeeded.", TennisEvents::TEXT_DOMAIN );
       try {
           $parentEvent = Event::get($this->eventId);
           if( !$parentEvent->isParent() ) {
               throw new InvalidEventException(__("Event must be a 'Parent'.",TennisEvents::TEXT_DOMAIN));
           }

           if( $parentEvent->getEventType() !== EventType::LADDER) {
               throw new InvalidEventException(__("Event type must be 'Ladder'.",TennisEvents::TEXT_DOMAIN));
           }

           //Get the most recent (i.e. youngest) child event
           $youngestChild = null;
           $ctr = 0;
           foreach($parentEvent->getChildEvents() as $child ) {
               if( 0 === $ctr++ ) {
                   $youngestChild = $child;
                   continue;
               }
               if(!is_null($youngestChild) && ($child->getStartDate() > $youngestChild->getStartDate()) ) {
                   $youngestChild = $child;
               }
           }
           if( empty( $youngestChild ) ) {
               throw new InvalidEventException(__("You must prepare initial ladder event manually.",TennisEvents::TEXT_DOMAIN));
           }

           //Copy the child event
           $nextEvent = new Event('','',$youngestChild);//copy constructor
           $nextStartDate = $youngestChild->getStartDate();
           $nextStartDate->modify('+1 month');
           if( $nextStartDate > $parentEvent->getEndDate() ) {
               $parentName = $parentEvent->getName();
               $parentEnd = $parentEvent->getEndDate()->format("Y-m-d");
               throw new InvalidEventException(__("Parent event '$parentName' ends on '$parentEnd'.",TennisEvents::TEXT_DOMAIN));
           }
           $newName = $nextStartDate->format("F");
           $nextEvent->setName( $newName );
           //Modify dates to next event's time frame
           $nextEvent->setStartDate($nextStartDate->format("Y-m-d"));
           $nextEndDate = $youngestChild->getEndDate();
           $intervalToEndDate = $this->getInterval($nextStartDate);
           $nextEndDate->add($intervalToEndDate);
           $nextEvent->setEndDate($nextEndDate->format("Y-m-d"));
           $nextSignupDate = $youngestChild->getSignupBy();
           $nextSignupDate->modify('+1 month');
           $nextEvent->setSignupBy($nextSignupDate->format("Y-m-d"));

           //Copy tennis event cpt using youngestChild's ID and external reference
           $youngestCptId = Event::getExtEventRefByEventId( $youngestChild->getID() );
           $nextCptId = $this->copyPost( $youngestCptId, $newName );
           if(0 === $nextCptId ) {
               throw new InvalidEventException(__("Could not duplicate custom post for event using youngest post id={$youngestCptId}",TennisEvents::TEXT_DOMAIN));
           }
           $nextCPT = get_post($nextCptId);
           if(empty($nextCPT)) {
               throw new InvalidEventException(__("Could not duplicate custom post for event using new post id={$nextCptId}",TennisEvents::TEXT_DOMAIN));
           }
           update_post_meta( $nextCptId, TennisEventCpt::SIGNUP_BY_DATE_META_KEY, $nextSignupDate->format('Y-m-d') );
           update_post_meta( $nextCptId, TennisEventCpt::START_DATE_META_KEY, $nextStartDate->format('Y-m-d') );
           update_post_meta( $nextCptId, TennisEventCpt::END_DATE_META_KEY, $nextEndDate->format('Y-m-d') );
   
           $nextEvent->addExternalRef((string)$nextCptId);
           $nextEvent->save();
       }
       catch( RuntimeException | InvalidEventException $ex ) {
           $this->errobj->add( $this->errcode++, $ex->getMessage() );
           $mess = $ex->getMessage();
           $this->log->error_log("$loc: caught this exception $mess");
       }
       return $mess;
  }
   
   /**
    * Copies a post & its meta and it returns the new new Post ID
    * @param  [int] $post_id The Post you want to clone
    * @param  [string] $newTitle The new title for the post.
    * @return [int] The duplicated Post ID
   */
   private function copyPost($post_id, $newTitle = '') {
       $loc = __CLASS__ . "::" . __FUNCTION__;
       $this->log->error_log("{$loc}({$post_id},{$newTitle})");

       if(empty($newTitle)) return 0;

       $oldpost = get_post($post_id);
       if(empty($oldpost)) return 0;

       if(empty($newTitle)) {
           $newTitle = $oldpost->post_title . "_copy";
       }

       $terms = get_the_terms($oldpost, TennisEventCpt::CUSTOM_POST_TYPE_TAX );
       if( is_wp_error($terms) ) {
           throw new InvalidEventException(__("Could not get terms for {$post_id}", TennisEvents::TEXT_DOMAIN));
       }
       $term_slugs = wp_list_pluck( $terms, 'slug' );

       $current_user = wp_get_current_user();
       $author = $oldpost->author;    
       if ( $current_user->exists() ) {
            $author = $current_user->ID;
        }

       $currentTime = new DateTime('NOW');
       $post    = array(
        'post_title' => $newTitle,
        'post_status' => 'publish',
        'post_date_gmt' => $currentTime,
        'post_content' => $oldpost->post_content,
        'post_type' => $oldpost->post_type,
        'post_author' => $author,
        'post_parent' => $oldpost->post_parent,
        'post_date'   => $currentTime->format('Y-m-d G:i:s'),
        'post_modified' => $currentTime->format('Y-m-d G:i:s')
       );

       $new_post_id = wp_insert_post($post);
       wp_set_object_terms( $new_post_id, $term_slugs, TennisEventCpt::CUSTOM_POST_TYPE_TAX );
       // Copy post metadata
       $data = get_post_custom($post_id);
       foreach ( $data as $key => $values) {
        foreach ($values as $value) {
            add_post_meta( $new_post_id, $key, wp_slash($value) );
        }
       }
       return $new_post_id;
   }

   /**
    * Retrieve the season
    * If not in the URL then retrieved from options
    * @return season
    */
    private function getSeason() {
        $loc = __CLASS__. "::" .__FUNCTION__;
        $this->log->error_log("$loc");
        
        $seasonDefault = esc_attr( get_option(TennisEvents::OPTION_TENNIS_SEASON, date('Y') ) ); 
        $season = isset($_GET['season']) ? $_GET['season'] : '';
        if(empty($season)) {
            $season = $seasonDefault;
            $this->log->error_log("$loc: Using default season='{$seasonDefault}'");
        }
        else {
            $this->log->error_log("$loc: Using given season='{$season}'");    
        }
        return $season;
    }

    /**
     * Make sure the dates have the correct order and spacing
     * @param $signupBy
     * @param $startDate
     * @param $endDate
     */
    private function validateEventDates(&$signupBy, &$startDate, &$endDate ) {
        $loc = __CLASS__. "::" .__FUNCTION__;
        $this->log->error_log("$loc");

        if($startDate instanceof \DateTime && $endDate instanceof \DateTime) {
            if($startDate >= $endDate) {
                $endDate = new \DateTime($startDate->format("Y-m-d"));
                $endDate->modify("+2 days");
            }
        }
        
        if($startDate instanceof \DateTime && $signupBy instanceof \DateTime) {
            if($signupBy >= $startDate) {    
                $signupBy = new \DateTime($startDate->format("Y-m-d"));
                $leadTime = TennisEvents::getLeadTime();
                $signupBy->modify("-{$leadTime} days");
            }
        }
    }
    
    /**
        * Determines the interval in days to the end of the month in the given date
        * @param DateTime $initDate
        * @return DateInterval
        */
    private function getInterval( DateTime $initDate ) : \DateInterval {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        
        $month = +$initDate->format("n");
        $numDays = 31;
        switch($month) {
            case 2:
                $year = +$initDate->format('Y');
                $isLeap = ($year % 4 === 0) ? true : false;
                $numDays = $isLeap ? 29 : 28;
                break;
            case 4:
            case 6:
            case 9:
            case 11;
                $numDays = 30;
                break;
            case 1:
            case 3:
            case 5:
            case 7:
            case 8:
            case 10:
            case 12:
            default:
                $numDays = 31;
        }
        $interval = new \DateInterval("P{$numDays}D");
        return $interval;
    }

    /**
     * Get the last day of the month found in the given date
     * @param DateTime $initDate
     * @return int The last day of the month
     */
    private function lastDayOfMonth( DateTime $initDate ) : int {
        $loc = __CLASS__ . "::" . __FUNCTION__;
        
        $month = +$initDate->format("n");
        $lastDay = 31;
        switch($month) {
            case 2:
                $year = +$initDate->format('Y');
                $isLeap = ($year % 4 === 0) ? true : false;
                $lastDay = $isLeap ? 29 : 28;
                break;
            case 4:
            case 6:
            case 9:
            case 11;
                $lastDay = 30;
                break;
            case 1:
            case 3:
            case 5:
            case 7:
            case 8:
            case 10:
            case 12:
            default:
                $lastDay = 31;
        }
        return $lastDay;
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

    private function handleErrors( string $mess ) {
        $loc = __CLASS__ . '::' . __FUNCTION__;
        $response = array();
        $response["message"] = $mess;
        $response["exception"] = $this->errobj;
        $this->log->error_log($response, "$loc: error response...");
        wp_send_json_error( $response );
        wp_die($mess);
    }
}