<?php
use cpt\TennisEventCpt;
// $dir = plugin_dir_path( __DIR__ );
// include_once(__DIR__ . '/../commonlib/support.php' );

get_header();  

$homeClubId = esc_attr( get_option('gw_tennis_home_club', 0) );
$club = Club::get( $homeClubId ); 
$homeClubName = is_null( $club ) ? __( "Unknown Club", TennisEvents::TEXT_DOMAIN) : $club->getName();
$season = esc_attr( get_option('gw_tennis_event_season', date('Y') ) );
?>

<!-- Page Content ---->
<div class="page-content">	
<h1> Season <?php echo $season; ?> </h1>
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
					if( is_null( $event ) ) {
						//TODO: Sydney theme does not display this correctly!?
						$errmess = "Could not find default tennis event with external id={$eventCPTId}";
						error_log($errmess);
						echo "<div class='tennis-error'>{$errmess}</div>";					
						get_footer(); 	
						wp_die( $errmess );
					}
					if( $season !== $event->getSeason() ) continue;

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
					$eventType = get_post_meta( get_the_ID(), TennisEventCpt::EVENT_TYPE_META_KEY, true );
					$eventType = EventType::AllTypes()[$eventType];
					$startDate = get_post_meta( get_the_ID(), TennisEventCpt::START_DATE_META_KEY, true );
					if(empty($startDate)) $startDate = 'tba';

				?>	
				<h3>&quot;<?php the_title(); ?>&quot; <?php echo $eventType ?></h3>		
				<?php the_content() ?> 			
				<ul class='tennis-event-meta tennis-event-meta-detail'>							
					<li><?php echo __("Event Type: ", TennisEvents::TEXT_DOMAIN); echo $eventType; ?></li>
					<li><?php echo __("Start Date: ", TennisEvents::TEXT_DOMAIN); echo $startDate; ?></li>
				</ul>
				
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
							$scoreRules  = ScoreType::get_instance()->ScoreRules[$scoreType];
							$signupBy = get_post_meta( get_the_ID(), TennisEventCpt::SIGNUP_BY_DATE_META_KEY, true );
							$startDate = get_post_meta( get_the_ID(), TennisEventCpt::START_DATE_META_KEY, true );
							$endDate = get_post_meta( get_the_ID(), TennisEventCpt::END_DATE_META_KEY, true );
							$leafEvent = Event::getEventByExtRef( get_the_ID() );
						?>
						<section class="tennis-leaf-events"> <!-- Leaf Events -->
							<?php echo the_title("<h3>","</h3>") ?>
							<div><?php the_content() ?> </div>
							<table class='tennis-event-meta'>
							<tbody>
								<tr class="event-meta-detail"><td><strong><?php echo __("Match Type", TennisEvents::TEXT_DOMAIN);?></strong></td><td><?php echo $matchType; ?></td></tr>
								<tr class="event-meta-detail"><td><strong><?php echo __("Format", TennisEvents::TEXT_DOMAIN);?></td></strong><td><?php echo $eventFormat; ?></td></tr>
								<tr class="event-meta-detail"><td><strong><?php echo __("Score Rules", TennisEvents::TEXT_DOMAIN);?></td></strong><td><strong><?php echo $scoreType; ?></strong>
								<ul class="tennis-score-rules">
								<?php foreach($scoreRules as $name=>$rule ) { ?>
										<li><?php echo "{$name}: {$rule}"?></li>
									<?php } ?>
									</ul>
								</td></tr>
								<tr class="event-meta-detail"><td><strong><?php echo __("Signup Deadline", TennisEvents::TEXT_DOMAIN);?></td></strong><td><?php   echo $signupBy; ?></td></tr>
								<tr class="event-meta-detail"><td><strong><?php echo __("Event Starts", TennisEvents::TEXT_DOMAIN);?></td></strong><td><?php  echo $startDate; ?></td></tr>
								<tr class="event-meta-detail"><td><strong><?php echo __("Event Ends", TennisEvents::TEXT_DOMAIN);?></td></strong><td><?php  echo $endDate; ?></td></tr>
							</tbody>
							</table>
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
						<div style="clear:both"></div>
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
get_footer(); 
?>