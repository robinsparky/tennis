<?php
namespace controllers;
use \TennisEvents;
use api\TournamentDirector;
use datalayer\Club;
use datalayer\Event;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$loc = __FILE__;
error_log( "$loc loaded +++++++++++++++++++++");
/** 
 * This controller provides access to Events 
 * being held at a Club or across many Clubs
 * @class  EventsEndpoint
 * @package Tennis Events
 * @version 1.0.0
 * @since   0.1.0
*/
class BracketsController extends \WP_REST_Controller
{ 
  /**
   * Register the routes for the objects of the controller.
   */
  public function register_routes() {
    $version = '1';
    $namespace = 'tennis/v' . $version;
    $base = 'brackets';

    error_log( "++++++++++++++++++++++Register routes for $namespace/$base" );

    register_rest_route( $namespace, '/' . $base , array(
      array(
        'methods'         => \WP_REST_Server::READABLE,
        'callback'        => array( $this, 'get_items' ),
        'permission_callback' => array( $this, 'get_items_permissions_check' ),
        'args'            => array(
 
        ),
      ),
    ) );
    register_rest_route( $namespace, '/' . $base . '/(?P<clubId>[\d]+/P<eventId>[\d]+)/(?P<id>[\d]+)', array(
      array(
        'methods'         => \WP_REST_Server::READABLE,
        'callback'        => array( $this, 'get_item' ),
        'permission_callback' => array( $this, 'get_item_permissions_check' ),
        'args'            => array(
          'context'          => array(
            'default'      => 'view',
          ),
        ),
      ),
    ) );
    register_rest_route( $namespace, '/' . $base . '/schema', array(
      'methods'         => \WP_REST_Server::READABLE,
      'callback'        => array( $this, 'get_item_schema' ),
    ) );
  }
 
  /**
   * Get a collection of items
   *
   * @param WP_REST_Request $request Full data about the request.
   * @return WP_Error|WP_REST_Response
   */
  public function get_items( $request ) {
    $params = $request->get_params();
    $items = array(); //do a query, call another class, etc
    $data = array();
    $clubId = $params['clubId'];
    $eventId = $params['eventId'];

    $evts = Event::find( array( "club" => $clubId ) );
    $found = false;
    $target = null;
    if( count( $evts ) > 0 ) {
        foreach( $evts as $evt ) {
            $target =  $evt->getDescendant( $eventId );
            if( isset( $target ) ) {
                $found = true;
                break;
            }
        }
        if( $found ) {
            $club = Club::get( $clubId );
            $name = $club->getName();
            $evtName = $target->getName();
            $brackets = $target->getBrackets();
            $td = new TournamentDirector( $target );
            foreach( $brackets as $bracket ) {
                $matches = $td->getMatches();
                $umpire  = $td->getChairUmpire();
                $items   = array();
                foreach( $matches as $match ) {
                    $round   = $match->getRoundNumber();
                    $mn      = $match->getMatchNumber();
                    $status  = $umpire->matchStatusEx( $match )->toString();
                    $score   = $umpire->strGetScores( $match );
                    $winner  = $umpire->matchWinner( $match );
                    $winner  = is_null( $winner ) ? 'tba': $winner->getName();
                    $home    = $match->getHomeEntrant();
                    $hname   = !is_null( $home ) ? sprintf( "%d %s", $home->getPosition(), $home->getName() ) : 'tba';
                    $hseed   = !is_null( $home ) && $home->getSeed() > 0 ? $home->getSeed() : '';

                    $visitor = $match->getVisitorEntrant();
                    $vname   = 'tba';
                    $vseed   = '';
                    if( isset( $visitor ) ) {
                        $vname   = sprintf( "%d %s", $visitor->getPosition(), $visitor->getName()  );
                        $vseed   = $visitor->getSeed() > 0 ? $visitor->getSeed() : '';
                    }

                    $cmts    = $match->getComments();
                    $cmts    = isset( $cmts ) ? $cmts : '';
                    $items[] = array( "Round" => $round
                                    , "TennisMatch Number" => $mn
                                    , "Status" => $status
                                    , "Score" => $score
                                    , "Home Name" => $hname
                                    , "Home Seed" => $hseed
                                    , "Visitor Name" => $vname
                                    , "Visitor Seed" => $vseed 
                                    , "Comments" => $cmts
                                    , "Winner" => $winner );
                }
            }
        }
        else {
          return new \WP_Error( 500, __( "Could not event with Id '$eventId' for club with Id '$clubId'", TennisEvents::TEXT_DOMAIN) );
        }
    }
    else {
      return new \WP_Error( 500, __( "Could not any events for club with Id '$clubId'", TennisEvents::TEXT_DOMAIN ) );
    }

    $data = array();
    foreach( $items as $item ) {
      $itemdata = $this->prepare_item_for_response( $item, $request );
      $data[] = $this->prepare_response_for_collection( $itemdata );
    }
 
    return new \WP_REST_Response( $data, 200 );
  }
 
  /**
   * Get one item from the collection
   *
   * @param WP_REST_Request $request Full data about the request.
   * @return WP_Error|WP_REST_Response
   */
  public function get_item( $request ) {
    //get parameters from request
    $params = $request->get_params();
    $item = array();//do a query, call another class, etc
    $items = array(); //do a query, call another class, etc
    $data = array();
    $clubId = $params['clubId'];
    $eventId = $params['eventId'];
    $bracketId = $params['id'];
    
    $evts = Event::find( array( "club" => $clubId ) );
    $found = false;
    $target = null;
    if( count( $evts ) > 0 ) {
        foreach( $evts as $evt ) {
            $target =  $evt->getDescendant( $eventId );
            if( isset( $target ) ) {
                $found = true;
                break;
            }
        }
        if( $found ) {
            $club = Club::get( $clubId );
            $name = $club->getName();
            $evtName = $target->getName();
            $bracket = $target->getBracket( $bracketId );
            $td = new TournamentDirector( $target );
            $matches = $td->getMatches( $bracket->$name );
            $umpire  = $td->getChairUmpire();
            $items   = array();
            foreach( $matches as $match ) {
                $round   = $match->getRoundNumber();
                $mn      = $match->getMatchNumber();
                $status  = $umpire->matchStatusEx( $match )->toString();
                $score   = $umpire->strGetScores( $match );
                $winner  = $umpire->matchWinner( $match );
                $winner  = is_null( $winner ) ? 'tba': $winner->getName();
                $home    = $match->getHomeEntrant();
                $hname   = !is_null( $home ) ? sprintf( "%d %s", $home->getPosition(), $home->getName() ) : 'tba';
                $hseed   = !is_null( $home ) && $home->getSeed() > 0 ? $home->getSeed() : '';

                $visitor = $match->getVisitorEntrant();
                $vname   = 'tba';
                $vseed   = '';
                if( isset( $visitor ) ) {
                    $vname   = sprintf( "%d %s", $visitor->getPosition(), $visitor->getName()  );
                    $vseed   = $visitor->getSeed() > 0 ? $visitor->getSeed() : '';
                }

                $cmts    = $match->getComments();
                $cmts    = isset( $cmts ) ? $cmts : '';
                $items   = array( "Round" => $round
                                , "TennisMatch Number" => $mn
                                , "Status" => $status
                                , "Score" => $score
                                , "Home Name" => $hname
                                , "Home Seed" => $hseed
                                , "Visitor Name" => $vname
                                , "Visitor Seed" => $vseed 
                                , "Comments" => $cmts
                                , "Winner" => $winner );
                                
                  //return a response or error based on some conditional
                  if ( 1 == 1 ) {
                    return new \WP_REST_Response( $items, 200 );
                  } else {
                    return new \WP_Error( 'code', __( 'message', 'text-domain' ) );
                  }
            }
        }
        else {
          return new \WP_Error( 500, __( "Could not event with Id '$eventId' for club with Id '$clubId'", TennisEvents::TEXT_DOMAIN) );
        }
    }
    else {
      return new \WP_Error( 500, __( "Could not any events for club with Id '$clubId'", TennisEvents::TEXT_DOMAIN ) );
    }
  }
 
  /**
   * Check if a given request has access to get items
   *
   * @param WP_REST_Request $request Full data about the request.
   * @return WP_Error|bool
   */
  public function get_items_permissions_check( $request ) {
    return true; //<--use to make readable by all
    //return current_user_can( 'edit_something' );
  }
 
  /**
   * Check if a given request has access to get a specific item
   *
   * @param WP_REST_Request $request Full data about the request.
   * @return WP_Error|bool
   */
  public function get_item_permissions_check( $request ) {
    return $this->get_items_permissions_check( $request );
  }

	/**
	 * Prepare the item for create or update operation
	 *
	 * @param WP_REST_Request $request Request object
	 * @return WP_Error|object $prepared_item
	 */
	protected function prepare_item_for_database( $request ) {
    return array();
	}
 
  /**
   * Prepare the item for the REST response
   *
   * @param mixed $item WordPress representation of the item.
   * @param WP_REST_Request $request Request object.
   * @return mixed
   */
  public function prepare_item_for_response( $item, $request ) {
    
		$schema = $this->get_item_schema();
		$data   = array();
		$data = $item;
		// $team = new Sports_Bench_Team( (int)$item[ 'team_id' ] );

		// $data[ 'team_link' ] = $team->get_permalink();
		// $data[ 'team_link' ] = str_replace( '&#038;', '&', $data[ 'team_link' ] );

		return $data;
  }
  
  /**
   * Get our schema for a bracket.
   *
   * @param WP_REST_Request $request Current request.
   */
  public function get_item_schema( ) {
      $schema = array(
          // This tells the spec of JSON Schema we are using which is draft 4.
          '$schema'              => 'http://json-schema.org/draft-04/schema#',
          // The title property marks the identity of the resource.
          'title'                => 'Bracket'
          // The definitions are resuable schemae
          ,'definitions' => array(
            'match' => array(
                      'description' => 'A match within a tennis bracket'
                      ,'type'       => 'object' 
                      ,'properties' => array(
                          'round_num' => array(
                                            'description' =>  esc_html__( 'Round number.', TennisEvents::TEXT_DOMAIN )
                                            ,'type'        => 'integer' )
                          ,'match_num' => array(
                                            'description' => esc_html__( 'TennisMatch Number.', TennisEvents::TEXT_DOMAIN )
                                            ,'type'       => 'integer' )
                          ,'home'     => array(
                                            'description' => esc_html__( 'Home team or player', TennisEvents::TEXT_DOMAIN )
                                            ,'type'       => 'string' )
                          ,'home_seed'=> array(
                                            'description' => esc_html__( 'Home player seeding', TennisEvents::TEXT_DOMAIN )
                                            ,'type'       => 'string' )
                          ,'visitor'  => array(
                                            'description' => esc_html__( 'Visiting team or player', TennisEvents::TEXT_DOMAIN )
                                            ,'type'       => 'string' )
                          ,'visitor_seed'=> array(
                                            'description' => esc_html__( 'Visitor player seeding', TennisEvents::TEXT_DOMAIN )
                                            ,'type'       => 'string' )
                          ,'score'  => array(
                                            'description' => esc_html__( 'TennisMatch score', TennisEvents::TEXT_DOMAIN )
                                            ,'type' => 'string' )
                          ,'winner' => array(
                                            'description' => esc_html__( 'TennisMatch winner', TennisEvents::TEXT_DOMAIN )
                                            ,'type' => 'string' )
                        ) //match properties
            ) //end match
          ) //end definitions
          ,'description'          => "A tennis tournament's bracket."
          ,'type'                 => 'object'
          // In JSON Schema you can specify object properties in the properties attribute.
          ,'properties'           => array(
            'club_id' => array(
                'description'  => esc_html__( 'Unique identifier for the tennis club.',  TennisEvents::TEXT_DOMAIN ),
                'type'         => 'integer',
                'context'      => array( 'view', 'edit', 'embed' ),
                'readonly'     => true )
            ,'event_id' => array(
                'description'  => esc_html__( 'Unique identifier for the event within the club.',  TennisEvents::TEXT_DOMAIN  ),
                'type'         => 'integer',
                'context'      => array( 'view', 'edit', 'embed' ),
                'readonly'     => true )
            ,'bracket_num' => array(
                  'description'  => esc_html__( 'Unique identifier for the bracket within the event.',  TennisEvents::TEXT_DOMAIN  ),
                  'type'         => 'integer',
                  'context'      => array( 'view', 'edit', 'embed' ),
                  'readonly'     => true )
            ,'name' => array(
                  'description'  => esc_html__( 'The name of the bracket.', TennisEvents::TEXT_DOMAIN )
                  ,'context'     => array( 'view', 'edit', 'embed' )
                  ,'type'        => 'string' )
            ,'is_approved' => array(
                  'description'  => esc_html__( 'Indicates if the bracket has been approved.', TennisEvents::TEXT_DOMAIN )
                  ,'type'         => 'boolean' )
            ,'matches' => array( 
              'description'  => esc_html__( 'Collection of matches for this bracket.', TennisEvents::TEXT_DOMAIN )
              ,'type'         => 'array' 
              ,'properties'   => array( '$ref'=> '#definitions/match' )
              )
            ) // end bracket properties
          ,"required"  => array("club_id", "event_id", "bracket_num", "name")
      ); //end schema

      return $schema;
  }

  // Sets up the proper HTTP status code for authorization.
  public function authorization_status_code() {

      $status = 401;

      if ( is_user_logged_in() ) {
          $status = 403;
      }

      return $status;
  }
 
  /**
   * Get the query params for collections
   *
   * @return array
   */
  public function get_collection_params() {
      return array(
        'club_Id' => array(
          'description'        => 'The id for the tennis club in the search.',
          'type'               => 'integer',
          'default'            => 1,
          'sanitize_callback'  => 'absint',
        ),
        'event_Id' => array(
          'description'       => 'The id for the event in the search',
          'type'               => 'integer',
          'default'            => 2,
          'sanitize_callback'  => 'absint',
        )
    );
  }
} //end class