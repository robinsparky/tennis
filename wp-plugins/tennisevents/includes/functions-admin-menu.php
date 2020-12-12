<?php

/* WP Admin Menu positions
2 - Dashboard
4 - Separator
5 - Posts
10 - Media
20 - Pages
25 - Comments
59 - Separator
60 - Appearance
65 - Plugins
70 - Users
75 - Tools
80 - Settings
99 - Separator
*/

/* ================================
    Admin Menus
   ================================   
*/
function gw_tennis_admin_page() {
    //Generate Tennis Events admin page
    add_menu_page( 'Tennis Admin Options' //title
                 , 'Tennis Settings' //menu name
                 , 'manage_options' //capabilities required of user
                 , 'gwtennissettings' //slug
                 , 'gw_tennis_create_page' // function to create page
                 , 'dashicons-admin-generic' //icon
                 , 90 //menu position
    );

    //Generate admin sub pages
    add_submenu_page( 'gwtennissettings' //parent slug
                    , 'Tennis Admin Options' //page title
                    , 'Settings' // menu title
                    , 'manage_options' //capabilities
                    , 'gwtennissettings' // menu slug
                    , 'gw_tennis_create_page' //callback
    );

    add_action( 'admin_init', 'gw_tennis_custom_settings' );
}

//Activate custom settings
add_action( 'admin_menu', 'gw_tennis_admin_page' );

function gw_tennis_create_page() {
    //generation of our admin page
    $pluginsPath = plugin_dir_path( __FILE__ );
    //Tennis settings template
    $pathToTemplate = $pluginsPath . 'templates/tennis-settings.php';
    require_once $pathToTemplate;
}

function gw_tennis_custom_settings() {
    register_setting( 'gw-tennis-settings-group' //Options group
                    , 'gw_tennis_home_club' //Option name used in get_option
                    , 'gw_sanitize_clubId' //sanitize call back
                    );
                    
    register_setting( 'gw-tennis-settings-group' //Options group
                    , 'gw_tennis_main_event' //Option name used in get_option
                    , 'gw_sanitize_eventId' //sanitize call back
                    );

    add_settings_section( 'gw-tennis-options' //id
                        , 'Tennis Options' //title
                        , 'gw_tennis_options' //callback to generate html
                        , 'gwtennissettings' //page
                    );
    
    add_settings_field( 'gw_tennis_home_club' // id
                      , 'Home Club' // title
                      , 'gw_tennisHomeClub' // callback
                      , 'gwtennissettings' // page
                      , 'gw-tennis-options' // section
                      //,  array of args
                );
                
    add_settings_field( 'gw_tennis_main_event' // id
                    , 'Main Event' // title
                    , 'gw_tennisMainEvent' // callback
                    , 'gwtennissettings' // page
                    , 'gw-tennis-options' // section
                    //,  array of args
                );
}

function gw_tennis_options() {
    echo "Manage your Tennis Administration Options";
}

function gw_tennisHomeClub() {
    // $homeClubId = esc_attr( get_option('gw_tennis_home_club', 0) );
    // echo '<input type="number" placeholder="Min: 1, max: 100"
    // min="1" max="100" step="1" name="gw_tennis_home_club" value="' . $homeClubId . '" /><p>Max 100 and at least 1</p>';
    echo '<select name="gw_tennis_home_club">' . gw_get_clubs() . '</select>';
}

function gw_tennisMainEvent() {
    // $mainEventId = esc_attr( get_option('gw_tennis_main_event', 0) );
    // echo '<input type="number" placeholder="Min: 1, max: 100"
    // min="1" max="100" step="1" name="gw_tennis_main_event" value="' . $mainEventId . '" /><p>Max 100 and at least 1</p>';
    echo '<select name="gw_tennis_main_event">' . gw_get_events() . '</select>';
}

function gw_sanitize_clubId( $input ) {
    $output = 0;
    $message = null;
    $type = null;

    if( !is_null( $input ) ) {
        try {
            $club = Club::get( $input );
            if( !is_null( $club ) ) {
                $output = $club->getID();
                update_option('blogname', $club->getName());
                $type = 'success';
                $message = __('Default tennis club setting updated', TennisEvents::TEXT_DOMAIN );
            }
            else {
                $type = 'error';
                $message = __('Default tennis club setting failed to find club.', TennisEvents::TEXT_DOMAIN );

            }
        }
        catch( Exception $ex ) {
            $output = 0;
            $type = 'error';
            $message = __('Default tennis club setting failed to find club: ', TennisEvents::TEXT_DOMAIN ) . $ex->getMessage();
        }
    }    

    add_settings_error('gw_tennisHomeClub', esc_attr('default_club_updated'), $message, $type);

    return $output;
}

function gw_sanitize_eventId( $input ) {
    $output = 0;
    $message = null;
    $type = null;

    if( !is_null( $input ) ) {
        try {
            $event = Event::get( $input );
            if( !is_null( $event ) ) {
                $output = $event->getID();
                $type = 'success';
                $message = __('Default tennis event setting updated', TennisEvents::TEXT_DOMAIN );
            }
            else {
                $type = 'error';
                $message = __('Default event setting failed to find event.', TennisEvents::TEXT_DOMAIN );
            }
        }
        catch( Exception $ex ) {
            $output = 0;
            $type = 'error';
            $message = __('Default tennis event setting failed: ', TennisEvents::TEXT_DOMAIN ) . $ex->getMessage();
        }
    }
    else {
        $type = 'error';
        $message = __('Default tennis event event cannot be empty', TennisEvents::TEXT_DOMAIN );
    }

    add_settings_error('gw_tennisMainEvent', esc_attr('main_tennis_event_updated'), $message, $type);

    return $output;
}

function gw_get_clubs() { 
    $homeClubId = esc_attr( get_option('gw_tennis_home_club', 0) );
    $out = '';
    $allClubs = Club::search('');
    foreach( $allClubs as $club ) {
        if($club->getID() == $homeClubId ) {
            $out .= "<option selected value='{$club->getID()}'>{$club->getName()}</option>" . PHP_EOL;
        }
        else {
            $out .= "<option value='{$club->getID()}'>{$club->getName()}</option>" . PHP_EOL;
        }
    }
    return $out;
}

function gw_get_events() {
    $homeClubId = esc_attr( get_option('gw_tennis_home_club', 0) );
    $mainEventId = esc_attr( get_option('gw_tennis_main_event', 0) );
    $out = '';
    $allEvents = Event::find( ['club' => $homeClubId] );
    foreach( $allEvents as $event ) {
        if( $event->isLeaf()) continue;
        
        if( $mainEventId == $event->getID() ) {
            $out .= "<option selected value='{$event->getID()}'>{$event->getName()}</option>" . PHP_EOL;
        }
        else {
            $out .= "<option value='{$event->getID()}'>{$event->getName()}</option>" . PHP_EOL;
        }
    }
    return $out;
}



