<?php
use cpt\TennisEventCpt;
get_header();  ?>
<div class="page-title-section">		
	<div class="overlay">
		<div class="container">
			<div class="row">
				<div class="col-md-6">
					<div class="page-title">
					<h1>
						<?php $homeClubId = esc_attr( get_option('gw_tennis_home_club', 0) );
							$club = Club::get( $homeClubId ); 
							$clubName = is_null( $club ) ? __( "Unknown Club", TennisEvents::TEXT_DOMAIN) : $club->getName();
						?>
						<?php _e( "Tennis Events", TennisEvents::TEXT_DOMAIN ); echo( " for {$clubName}") ?>
					</h1>
				</div>
				</div>
				<div class="col-md-6">
					<ul class="page-breadcrumb">
						<?php if ( function_exists('qt_custom_breadcrumbs') ) qt_custom_breadcrumbs();?>
					</ul>
					
				</div>
			</div>
		</div>	
	</div>
</div>
<!-- /Page Title Section ---->
<div class="page-builder">		
	<div class="container">
		<div class="row">
			<!-- Blog Area -->
			<div class="<?php //appointment_post_layout_class(); ?>" >
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
                
                <hr style="clear:left;">
				<h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
				<?php tennis_events_get_term_links( $post->ID, TennisEventCpt::CUSTOM_POST_TYPE_TAX ); ?>	
                <?php the_content() ?> 
				<?php
					$args = array( "post_type" => TennisEventCpt::CUSTOM_POST_TYPE
								 , "meta_key"  => TennisEventCpt::PARENT_EVENT_META_KEY
								 , "meta_value_num" => $eventCPTId
								 , "meta_compare"   => "=" 	
								 , "orderby" => "title"
								 , "order"   => "ASC" );
					$myQuery = new WP_Query( $args );
					//Loop
					if( $myQuery->havePosts() ) {
						while( $myQuery->havePosts() ) { $myQuery->the_post(); 
							$eventType = get_post_meta( get_the_ID(), TennisEventCpt::EVENT_TYPE_META_KEY, true );
							$eventType   = EventType::AllTypes()[$eventType];
							$matchType = get_post_meta( get_the_ID(), TennisEventCpt::MATCH_TYPE_META_KEY, true );
							$matchType   = MatchType::AllTypes()[$matchType];
							$eventFormat = get_post_meta( get_the_ID(), TennisEventCpt::EVENT_FORMAT_META_KEY, true );
							$eventFormat = Format::AllFormats()[$eventFormat];	
							$signupBy = get_post_meta( get_the_ID(), TennisEventCpt::SIGNUP_BY_DATE_META_KEY, true );
							$startDate = get_post_meta( get_the_ID(), TennisEventCpt::START_DATE_META_KEY, true );
							$endDate = get_post_meta( get_the_ID(), TennisEventCpt::END_DATE_META_KEY, true );
						?>
						<a href="<?php the_permalink() ?>"><?php echo the_title() ?></a>
						<ul class='eventmeta eventmetalist'>
							<li><?php echo __("Event Type: ", TennisEvents::TEXT_DOMAIN); echo $eventType; ?></li>
							<li><?php echo __("Match Type: ", TennisEvents::TEXT_DOMAIN);  echo $matchType; ?></li>
							<li><?php echo __("Format: ", TennisEvents::TEXT_DOMAIN);  echo $eventFormat; ?></li>
							<li><?php echo __("Signup Deadline: ", TennisEvents::TEXT_DOMAIN);  echo $signupBy; ?></li>
							<li><?php echo __("Event Starts: ", TennisEvents::TEXT_DOMAIN); echo $startDate; ?></li>
							<li><?php echo __("Event Ends: ", TennisEvents::TEXT_DOMAIN); echo $endDate; ?></li>
						</ul>
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
			</div>
            <?php
                // Reset Post Data 
                wp_reset_postdata();
            ?>	
			
			<!--Sidebar Area-->
			<div class="col-md-4">
				<?php get_sidebar(); ?>
			</div>
			<!--/Sidebar Area-->
		</div>
	
	</div>
</div>
<?php get_footer(); ?>