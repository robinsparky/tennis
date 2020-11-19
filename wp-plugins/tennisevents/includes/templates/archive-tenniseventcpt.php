<?php
use cpt\TennisEventCpt;
// $dir = plugin_dir_path( __DIR__ );
// include_once(__DIR__ . '/../commonlib/support.php' );

get_header();  

$homeClubId = esc_attr( get_option('gw_tennis_home_club', 0) );
$club = Club::get( $homeClubId ); 
$clubName = is_null( $club ) ? __( "Unknown Club", TennisEvents::TEXT_DOMAIN) : $club->getName();
?>

<!-- Page Content ---->
<div class="page-content">	

<?php

// Sidebar Alt 
//get_template_part( 'templates/sidebars/sidebar', 'alt' ); 

// Sidebar Left
//get_template_part( 'templates/sidebars/sidebar', 'left' );

?>
	<div class="tennis-events-container">
		<section class="tennis-events"> <!-- Root events -->
			<?php				
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
					if( is_null( $event ) ) wp_die("Could not find event with external id=$eventCPTId");
				?>
				
				<hr style="clear:left;" class='root-event-divider'>
				<h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
				<?php commonlib\tennis_events_get_term_links( $post->ID, TennisEventCpt::CUSTOM_POST_TYPE_TAX ); 
					$eventType = get_post_meta( get_the_ID(), TennisEventCpt::EVENT_TYPE_META_KEY, true );
					$eventType   = EventType::AllTypes()[$eventType];
				?>							
				<ul class='tennis-event-meta tennis-event-meta-detail'>							
					<li><?php echo __("Event Type: ", TennisEvents::TEXT_DOMAIN); echo $eventType; ?></li>
				</ul>
				<?php the_content() ?> 
				<!-- Now the child events -->
				<?php
					$args = array( "post_type" => TennisEventCpt::CUSTOM_POST_TYPE
								, "orderby" => "title"
								, "order"   => "ASC" 
								, "meta_query" => array( "relation" => "OR"
										,array(
											'key' => TennisEventCpt::PARENT_EVENT_META_KEY
											,'value' => $eventCPTId
											,'compare' => '='
										)
									)
								);
					$myQuery = new WP_Query( $args );
					// echo $myQuery->request;
					// echo "<br>Found?" . $myQuery->have_posts();
					//Loop
					if( $myQuery->have_posts() ) {
						while( $myQuery->have_posts() ) { 
							$myQuery->the_post(); 
							$matchType = get_post_meta( get_the_ID(), TennisEventCpt::MATCH_TYPE_META_KEY, true );
							$matchType   = MatchType::AllTypes()[$matchType];
							$eventFormat = get_post_meta( get_the_ID(), TennisEventCpt::EVENT_FORMAT_META_KEY, true );
							$eventFormat = Format::AllFormats()[$eventFormat];	
							$scoreType   = get_post_meta( get_the_ID(), TennisEventCpt::SCORE_TYPE_META_KEY, true );
							$signupBy = get_post_meta( get_the_ID(), TennisEventCpt::SIGNUP_BY_DATE_META_KEY, true );
							$startDate = get_post_meta( get_the_ID(), TennisEventCpt::START_DATE_META_KEY, true );
							$endDate = get_post_meta( get_the_ID(), TennisEventCpt::END_DATE_META_KEY, true );
							$leafEvent = Event::getEventByExtRef( get_the_ID() );
						?>
						<hr style="clear:left;" class="leaf-event-divider">
						<section class="tennis-leaf-events"> <!-- Leaf Events -->
							<?php echo the_title("<h3>","</h3>") ?>
							<div><?php the_content() ?> </div>
							<table class='tennis-event-meta'>
							<h5>Info</h5>
							<tbody>
								<tr class="event-meta-detail"><td><strong><?php echo __("Match Type", TennisEvents::TEXT_DOMAIN);?></strong></td><td><?php echo $matchType; ?></td></tr>
								<tr class="event-meta-detail"><td><strong><?php echo __("Format", TennisEvents::TEXT_DOMAIN);?></td></strong><td><?php echo $eventFormat; ?></td></tr>
								<tr class="event-meta-detail"><td><strong><?php echo __("Score Type", TennisEvents::TEXT_DOMAIN);?></td></strong><td><?php echo $scoreType; ?></td></tr>
								<tr class="event-meta-detail"><td><strong><?php echo __("Signup Deadline", TennisEvents::TEXT_DOMAIN);?></td></strong><td><?php   echo $signupBy; ?></td></tr>
								<tr class="event-meta-detail"><td><strong><?php echo __("Event Starts", TennisEvents::TEXT_DOMAIN);?></td></strong><td><?php  echo $startDate; ?></td></tr>
								<tr class="event-meta-detail"><td><strong><?php echo __("Event Ends", TennisEvents::TEXT_DOMAIN);?></td></strong><td><?php  echo $endDate; ?></td></tr>
							</tbody>
							</table>
							<h5>Actions</h5>
							<ul class="tennis-event-brackets">
							<?php 
								$td = new TournamentDirector( $leafEvent );
								$brackets = $td->getBrackets( );
								foreach( $brackets as $bracket ) {
							?>
								<li><a href="<?php the_permalink() ?>?manage=signup&bracket=<?php echo $bracket->getName() ?>"><?php echo $bracket->getName()?> Signup</a></li>
								<li><a href="<?php the_permalink() ?>?manage=draw&bracket=<?php echo $bracket->getName() ?>"><?php echo $bracket->getName()?> Draw</a></li>	
							<?php } ?>
							</ul>	
						</section> <!-- /leaf events -->	
						<?php } ?>
					<?php }
					else {
						echo "<div class='eventmessage'>NO LEAF EVENTS FOUND!</div>";
					}
						/* Restore original Post Data */
						wp_reset_postdata();
					?>

				<?php endwhile;
				// Previous/next page navigation.
				the_posts_pagination( array(
					'prev_text'          => '<i class="fa fa-angle-double-left"></i>',
					'next_text'          => '<i class="fa fa-angle-double-right"></i>',
				) );
				?>

			<?php
				// Reset Post Data 
				wp_reset_postdata();
			?>	
		</section>  <!-- /Root events -->
	</div> <!-- /Container -->

<?php // Sidebar Right
 //get_template_part( 'templates/sidebars/sidebar', 'right' );
?>
</div> <!-- /Page content -->

<?php 
//echo "FOOTER*****************************";
get_footer(); 
?>