<?php
//use \TennisEvents;
//use \TE_Install;
use api\TournamentDirector;
use cpt\TennisEventCpt;
use commonlib\BaseLogger;
use commonlib\GW_Support;
use datalayer\Club;
use datalayer\Event;
use datalayer\EventType;
use datalayer\Format;
use datalayer\MatchType;
use datalayer\ScoreType;
use datalayer\GenderType;

// $dir = plugin_dir_path( __DIR__ );
// include_once(__DIR__ . '/../commonlib/support.php' );

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
<h1>Season <?php echo $season; ?> </h1>
<?php

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
				$args = array( "post_type" => TennisEventCpt::CUSTOM_POST_TYPE
								, "meta_key" => TennisEventCpt::START_DATE_META_KEY
								, "orderby" => "meta_value"
								, "order"   => "ASC" 
							);
				query_posts($args);
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
					if(empty($startDate)) $startDate = 'tba';
					if(empty($endDate)) $endDate = 'tba';

				?>	
				 <!-- Root event -->
				<div id="<?php echo get_the_ID()?>" class="tennis-parent-event" data-event-id="<?php echo $event->getID();?>">
				<h3 class="tennis-parent-event-title"><?php the_title(); ?></h3>					
				<ul class='tennis-event-meta tennis-event-meta-detail'>		
					<li><?php the_content(); ?></li>					
					<li><?php echo __("Event Type: ", TennisEvents::TEXT_DOMAIN); echo $eventType; ?></li>
					<li><?php echo __("Start Date: ", TennisEvents::TEXT_DOMAIN); echo $startDate; ?></li>
                    <li><?php echo __("End Date: ", TennisEvents::TEXT_DOMAIN); echo $endDate; ?></li>
					<?php if( $eventTypeRaw === EventType::LADDER && $support->userIsTournamentDirector()) : ?>
						<li><button type="button" class="button tennis-ladder-next-month">Prepare Next Month</button> </li>
					<?php endif; ?>
				</ul>
				
				<!-- leaf event container -->
				<section class="tennis-leaf-event-container">
				<?php
					$args = array( "post_type" => TennisEventCpt::CUSTOM_POST_TYPE
								, "meta_key" => TennisEventCpt::START_DATE_META_KEY
								, "orderby" => "meta_value"
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
							$genderType = get_post_meta( get_the_ID(), TennisEventCpt::GENDER_TYPE_META_KEY, true);
							$genderType = GenderType::AllTypes()[$genderType];
							$matchType = get_post_meta( get_the_ID(), TennisEventCpt::MATCH_TYPE_META_KEY, true );
							$matchType   = MatchType::AllTypes()[$matchType];
							$terms = $support->tennis_events_get_term_names( get_the_ID(), TennisEventCpt::CUSTOM_POST_TYPE_TAX );
							$terms = implode(";", $terms);
							$eventFormat = get_post_meta( get_the_ID(), TennisEventCpt::EVENT_FORMAT_META_KEY, true );
							$eventFormat = Format::AllFormats()[$eventFormat];	
							$scoreType   = get_post_meta( get_the_ID(), TennisEventCpt::SCORE_TYPE_META_KEY, true );
							$scoreRules  = ScoreType::get_instance()->ScoreRules[$scoreType];
							$scoreRuleDesc = ScoreType::get_instance()->getRuleDescriptions()[$scoreType];
							$signupBy = get_post_meta( get_the_ID(), TennisEventCpt::SIGNUP_BY_DATE_META_KEY, true );
							$startDate = get_post_meta( get_the_ID(), TennisEventCpt::START_DATE_META_KEY, true );
							$endDate = get_post_meta( get_the_ID(), TennisEventCpt::END_DATE_META_KEY, true );
							$leafEvent = Event::getEventByExtRef( get_the_ID() );					
							$minAge = get_post_meta( get_the_ID(), TennisEventCpt::AGE_MIN_META_KEY, true );
							$maxAge = get_post_meta( get_the_ID(), TennisEventCpt::AGE_MAX_META_KEY, true );
		
						?>
						<!-- Leaf Event -->
						<section class="tennis-leaf-event"> 
							<?php echo the_title("<h3 class='tennis-leaf-event-title'>","</h3>") ?>
							<?php the_content() ?>
							<table class='tennis-event-meta'>
							<tbody>									
								<tr class="event-meta-detail"><td><strong><?php echo __("Gender", TennisEvents::TEXT_DOMAIN);?></strong></td><td><?php echo $genderType; ?></td></tr>
								<tr class="event-meta-detail"><td><strong><?php echo __("Match Type", TennisEvents::TEXT_DOMAIN);?></strong></td><td><?php echo $matchType; ?></td></tr>
								<tr class="event-meta-detail"><td><strong><?php echo __("Categories", TennisEvents::TEXT_DOMAIN);?></strong></td><td><?php echo $terms; ?></td></tr>
								<tr class="event-meta-detail"><td><strong><?php echo __("Min Age", TennisEvents::TEXT_DOMAIN);?></strong></td><td><?php echo $minAge; ?></td></tr>
								<tr class="event-meta-detail"><td><strong><?php echo __("Max Age", TennisEvents::TEXT_DOMAIN);?></strong></td><td><?php echo $maxAge; ?></td></tr>
								<tr class="event-meta-detail"><td><strong><?php echo __("Signup By", TennisEvents::TEXT_DOMAIN);?></td></strong><td><?php   echo $signupBy; ?></td></tr>
								<tr class="event-meta-detail"><td><strong><?php echo __("Starts", TennisEvents::TEXT_DOMAIN);?></td></strong><td><?php  echo $startDate; ?></td></tr>
								<tr class="event-meta-detail"><td><strong><?php echo __("Ends", TennisEvents::TEXT_DOMAIN);?></td></strong><td><?php  echo $endDate; ?></td></tr>
								<tr class="event-meta-detail"><td><strong><?php echo __("Format", TennisEvents::TEXT_DOMAIN);?></td></strong><td><?php echo $eventFormat; ?></td></tr>
								<tr class="event-meta-detail"><td><strong><?php echo __("Scoring", TennisEvents::TEXT_DOMAIN);?></td></strong><td><strong><?php echo $scoreRuleDesc; ?></strong>
								<ul class="tennis-score-rules">
								<?php foreach($scoreRules as $name=>$rule ) { ?>
										<li><?php echo "{$name}: {$rule}"?></li>
									<?php } ?>
									</ul>
								</td></tr>
							</tbody>
							</table>
							<?php						
								if( empty($leafEvent)) {
									$err = __("Cannot find the actual tennis event for this custom post type", TennisEvents::TEXT_DOMAIN);
									echo "<h3 class='tennis-error'>" . $err . "</h3>";
								}
								else {
							?>
							<!-- Brackets for a leaf event -->
							<ul id="tennis-event-brackets" class="tennis-event-brackets" data-eventid="<?php echo $leafEvent->getID();?>">
							<?php 	
								$td = new TournamentDirector( $leafEvent );
								$brackets = $td->getBrackets( );
								foreach( $brackets as $bracket ) {
							?>
								<li class="item-bracket" data-eventid="<?php echo $leafEvent->getID();?>" data-bracketnum="<?php echo $bracket->getBracketNumber(); ?>">
									<?php if( current_user_can( TE_Install::MANAGE_EVENTS_CAP ) ) : ?>
										<span class="bracket-name" contenteditable>
									<?php else: ?>
										<span class="bracket-name">
									<?php endif ?>
									<?php echo $bracket->getName()?></span>&colon;
									<a class="bracket-signup-link" href="<?php the_permalink(); ?>?season=<?php echo urlencode($season)?>&mode=signup&bracket=<?php echo urlencode($bracket->getName()); ?>">Signup, </a>
									<a class="bracket-draw-link" href="<?php the_permalink() ?>?season=<?php echo urlencode($season)?>&mode=draw&bracket=<?php echo urlencode($bracket->getName()); ?>">Draw</a>
									<?php if( current_user_can( TE_Install::MANAGE_EVENTS_CAP ) ) : ?>
										<img class="remove-bracket" src="<?php echo TE()->getPluginUrl() . 'img/removeIcon.gif' ?>">
									<?php endif ?>
									<?php } ?>	
								</li>
							</ul>
								<?php if( current_user_can( TE_Install::MANAGE_EVENTS_CAP ) ) : ?>
									<button type="button" class="button tennis-add-bracket" data-eventid="<?php echo $leafEvent->getID();?>" >Add Bracket</button>
								<?php endif ?>
							<!-- /Brackets -->
							<?php } ?>	
						</section> <!-- /leaf events -->	
						<?php } ?>
					</section> <!-- /leaf event container-->
					<?php }
					else {
						echo "<div class='eventmessage'>NO LEAF EVENTS FOUND!</div>";
					}
						/* Restore original Post Data */
						wp_reset_postdata();
					?>
				</div>
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