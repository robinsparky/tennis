<?php
use cpt\TennisEventCpt;
use commonlib\BaseLogger;
use commonlib\GW_Support;
use datalayer\Club;
use datalayer\Event;
use datalayer\EventType;

get_header();  

$logger = new BaseLogger( true );
$support = GW_Support::getInstance();

$homeClubId = esc_attr( get_option('gw_tennis_home_club', 0) );
$club = Club::get( $homeClubId ); 
$homeClubName = is_null( $club ) ? __( "Unknown Club", TennisEvents::TEXT_DOMAIN) : $club->getName();
$season = esc_attr( get_option('gw_tennis_event_season', date('Y') ) ); 
$prevSeason = isset($_GET['season']) ? $_GET['season'] : '';
if(!empty($prevSeason)) {
	$season = $prevSeason;
	$logger->error_log("****Previous season='{$prevSeason}'");
} 
?>

<!-- Page Content ---->
<div class="page-content">	
<h1>Tennis Events for Season <?php echo $season; ?> </h1>
<?php
	if( current_user_can( TE_Install::MANAGE_EVENTS_CAP ) ) {
		echo "<button class='tennis-add-event root'>" . __("Create New Event",TennisEvents::TEXT_DOMAIN) . "</button>";
		include(TE()->getPluginPath() . 'includes\templates\controls\newRootEventDialog.php');
	}

// Sidebar Alt 
//get_template_part( 'templates/sidebars/sidebar', 'alt' ); 

// Sidebar Left
//get_template_part( 'templates/sidebars/sidebar', 'left' );

?>
	<div class="tennis-events-container">
  		<!-- Root tennis events -->
		<section class="tennis-events">
			<div id="tabs" class="tennis-event-tabs-container">
			<?php	
				wp_enqueue_script( 'manage_brackets' ); 
				global $jsDataForTennisBrackets;        
				wp_localize_script( 'manage_brackets', 'tennis_bracket_obj', $jsDataForTennisBrackets );

				while ( have_posts() ) : 
					the_post();
					global $more;
					$more = 0; 					
					$eventCPTId = get_the_ID(); 
					$event = null;
					$events = Event::getEventByExtRef( $eventCPTId );
					if( is_array( $events ) ) {
						$event = $events[0];
					}
					else {
						$event = $events;
					}
					if( is_null( $event ) ) {
						//TODO: Sydney theme does not display this correctly!?
						$errmess = "Could not find default tennis event with external id={$eventCPTId}";
						error_log($errmess);
						echo "<div class='tennis-error'>{$errmess}</div>";					
						get_footer(); 	
						wp_die( $errmess );
					}
					if( $season != $event->getSeason() ) continue;

					//Have a valid parent Event
					$allClubs = $event->getClubs();
					$thisClubName = $homeClubName;
					//TODO: Take action if club id is not same as home club id
					foreach( $allClubs as $club ) {
						if( $homeClubId === $club->getID() ) {
							$thisClubName = $club->getName();
							break;
						}
					}
					//commonlib\tennis_events_get_term_links( $post->ID, TennisEventCpt::CUSTOM_POST_TYPE_TAX ); 
					$eventTypeRaw = get_post_meta( get_the_ID(), TennisEventCpt::EVENT_TYPE_META_KEY, true );
					$eventType = EventType::AllTypes()[$eventTypeRaw];
					$startDate = get_post_meta( get_the_ID(), TennisEventCpt::START_DATE_META_KEY, true );
					$endDate   = get_post_meta( get_the_ID(), TennisEventCpt::END_DATE_META_KEY, true );
					$eventTitle = $event->getName();
					$eventId = $event->getID();
					$postId = get_the_ID();
					$editTitle = __("Edit Event '{$event->getName()}'",TennisEvents::TEXT_DOMAIN);
					if(empty($startDate)) $startDate = '';
					if(empty($endDate)) $endDate = '';

					//The content is produced here
					if( current_user_can( TE_Install::MANAGE_EVENTS_CAP ) ) {
						require(TE()->getPluginPath() . 'includes\templates\controls\editor-tenniseventcpt.php');
					}
					else {
						require( TE()->getPluginPath() . 'includes\templates\controls\readonly-tenniseventcpt.php');
					}
				?>	

				<?php endwhile;
				// Previous/next page navigation.
				the_posts_pagination( array(
					'prev_text'          => '<i class="fa fa-angle-double-left"></i>',
					'next_text'          => '<i class="fa fa-angle-double-right"></i>',
				) );
				?>
				<div id="tennis-event-message"></div>
			<?php
				// Reset Post Data 
				wp_reset_postdata();
			?>	
		</section>  <!-- /Root events -->
		
		</div> <!-- /Tabs -->
	</div> <!-- /Container -->

<?php // Sidebar Right
 //get_template_part( 'templates/sidebars/sidebar', 'right' );
?>
</div> <!-- /Page content -->

<?php 
get_footer(); 
?>