<div id="post-<?php the_ID(); ?>" <?php //post_class('blog-lg-area-left'); ?>>
	<div class="event-content">						
	<?php //appointment_aside_meta_content();
	the_title('<h2>','</h2>'); ?>
		<div class="event-content-body">
			<?php 
				//  $eventType   = get_post_meta( get_the_ID(), TennisEventCpt::EVENT_TYPE_META_KEY, true );
				//  $matchType   = get_post_meta( get_the_ID(), TennisEventCpt::MATCH_TYPE_META_KEY, true );
				//  $eventFormat = get_post_meta( get_the_ID(), TennisEventCpt::EVENT_FORMAT_META_KEY, true );
				//  $parentId    = get_post_meta( get_the_ID(), TennisEventCpt::PARENT_EVENT_META_KEY, true );
				//  $signupBy    = get_post_meta( get_the_ID(), TennisEventCpt::SIGNUP_BY_DATE_META_KEY, true );
				//  $startDate   = get_post_meta( get_the_ID(), TennisEventCpt::START_DATE_META_KEY, true );
				//  $endDate     = get_post_meta( get_the_ID(), TennisEventCpt::END_DATE_META_KEY, true );

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
					 wp_die( __("Could not find underlying event!", TennisEvents::TEXT_DOMAIN ) );
				 }
				 if( $event->isRoot() ) {
				   wp_die( __("Root Tennis Event not expected here!", TennisEvents::TEXT_DOMAIN ) );
				} 
			
				// call editor content of post/page	s
				the_content( __('Read More', TennisEvents::TEXT_DOMAIN ) );
				wp_link_pages( );
				$brackets = $event->getBrackets();
				?>
				<div class="eventsignup">
				<?php
				foreach( $brackets as $bracket ) { 
				 echo do_shortcode("[manage_signup club=1 eventid={$event->getID()}, bracketname={$bracket->getName()}]");
				}
				?>
				</div>
		</div>
	 </div>
</div>
