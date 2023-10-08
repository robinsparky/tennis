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
use datalayer\Bracket;
use datalayer\Club;
use datalayer\InvalidBracketException;
use datalayer\InvalidEventException;
use cpt\TennisEventCpt;
use InvalidArgumentException;
use RuntimeException;

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
            case 'modifyeventtitle':
                $mess = $this->updateEventName( $data );
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
     * Update the Event name and the associated custom post's title
     * NOTE: Not modifying the post's slug at this time 
     * @param array $data A reference to a dictionary containing data for update
     * @return string A message describing success or failure
     */
    private function updateEventName( &$data ) {
        $loc = __CLASS__. "::" .__FUNCTION__;
        $this->log->error_log($data,"$loc: data...");

        $postId =  $data['postId'];
        $eventId = $data['eventId'];
        $task = $data['task'];
        $mess = "";
        try {
            $event = Event::get($eventId);
            $data['newTitle'] = htmlspecialchars(strip_tags($data['newTitle']));
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
        catch (Exception | InvalidArgumentException $ex ) {
            $this->errobj->add( $this->errcode++, $ex->getMessage() );
            $mess = $ex->getMessage();
        }
        return $mess;
    }

    /**
     * Update simple attributes which do not affect the draw   
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
            $event = Event::get($eventId);
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
                    if(!$event->setSignupBy($signupBy)) throw new InvalidArgumentException(__("Illegal signup by '{$signupBy}'", TennisEvents::TEXT_DOMAIN));
                    update_post_meta( $postId, TennisEventCpt::SIGNUP_BY_DATE_META_KEY, $signupBy );
                    $mess = __("Successfully updated signup by", TennisEvents::TEXT_DOMAIN);
                    break;
                case 'modifystartdate':
                    $startDate = $data['startDate'];
                    if(!$event->setStartDate($startDate)) throw new InvalidArgumentException(__("Illegal start date '{$startDate}'", TennisEvents::TEXT_DOMAIN));
                    update_post_meta( $postId, TennisEventCpt::START_DATE_META_KEY, $startDate );
                    $mess = __("Successfully updated the start date", TennisEvents::TEXT_DOMAIN);
                    break;
                case 'modifyenddate':
                    $endDate = $data['endDate'];
                    if(!$event->setEndDate($endDate)) throw new InvalidArgumentException(__("Illegal end date '{$endDate}' for {$loc}", TennisEvents::TEXT_DOMAIN));
                    update_post_meta($postId, TennisEventCpt::END_DATE_META_KEY, $endDate);
                    $mess = __("Successfully updated the end date", TennisEvents::TEXT_DOMAIN);
                    break;
                default:
                throw new InvalidArgumentException(__("Illegal task '{$task}' for {$loc}", TennisEvents::TEXT_DOMAIN));
            }
            $event->save();
        } 
        catch (Exception | InvalidArgumentException $ex ) {
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
            $td = new TournamentDirector( $event );
            $brackets = $td->getBrackets();

            //All Brackets must not be approved and must not have started
            foreach( $brackets as $bracket ) {
                if($bracket->isApproved() || (0 < $td->hasStarted( $bracket->getName()) ) ) {
                    throw new InvalidEventException( __('Cannot modify the event because at least one draw is published.') );
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
        catch (Exception | InvalidEventException | InvalidArgumentException $ex ) {
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
        catch (Exception | InvalidBracketException $ex ) {
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
        catch (Exception | InvalidBracketException $ex ) {
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
        catch (Exception | InvalidEventException $ex ) {
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
       catch( Exception | InvalidEventException $ex ) {
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
       catch( Exception $ex ) {
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
        $this->log->error_log("$loc");
        $response = array();
        $response["message"] = $mess;
        $response["exception"] = $this->errobj;
        wp_send_json_error( $response );
        wp_die($mess);
    }
}