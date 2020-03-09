<div id="post-<?php the_ID(); ?>" <?php //post_class('blog-lg-area-left'); ?>>
	<div class="event-content">						
	<?php //appointment_aside_meta_content(); ?>
		<div class="event-content-body">
			<?php 
				 $eventType   = get_post_meta( get_the_ID(), TennisEventCpt::EVENT_TYPE_META_KEY, true );
				 $matchType   = get_post_meta( get_the_ID(), TennisEventCpt::MATCH_TYPE_META_KEY, true );
				 $eventFormat = get_post_meta( get_the_ID(), TennisEventCpt::EVENT_FORMAT_META_KEY, true );
				 $parentId    = get_post_meta( get_the_ID(), TennisEventCpt::PARENT_EVENT_META_KEY, true );
				 $signupBy    = get_post_meta( get_the_ID(), TennisEventCpt::SIGNUP_BY_DATE_META_KEY, true );
				 $startDate   = get_post_meta( get_the_ID(), TennisEventCpt::START_DATE_META_KEY, true );
				 $endDate     = get_post_meta( get_the_ID(), TennisEventCpt::END_DATE_META_KEY, true );

				 $eventCPTId = get_the_ID(); 
				 $event = null;
				 $events = Event::getEventByExtRef( $eventCPTId );
				 if( is_array( $events ) ) {
					 $event = $events[0];
				 }
				 else {
					 $event = $events;
				 }
				 if( $event->isRoot() ) {
				   wp_die( __("Root Tennis Event not expected here!", TennisEvents::TEXT_DOMAIN ) );
				} 
			
				// call editor content of post/page	s
				the_content( __('Read More', TennisEvents::TEXT_DOMAIN ) );
				wp_link_pages( );
				?>
				<h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
				<h4><span>Match Type: $<?php echo $matchType ?></span>
					&nbsp;<span>Format: <?php echo $eventFormat ?></span>
				</h4>
				<h4><span>Sign Up By: $<?php echo $signupBy ?></span>
					&nbsp;<span>Starts: <?php echo $startDate ?></span>
					&nbsp;<span>Ends: <?php echo $endDate ?></span>
				</h4>
				<table class="brackets signup"> 
					<caption><?php echo __( "Brackets Signup", TennisEvents::TEXT_DOMAIN ) ?></caption>
					<thead>
					<tr>
						<th>Bracket</th>
						<th>Signup</th>
					</tr>
					</thead>
					<tbody>
				<?php
					$brackets = $event->getBrackets();
					foreach( $brackets as $bracket ) {
					?>
					<tr>
						<td><?php echo $bracket->getName()?></td>
						<td><?php do_shortcode("[manage_signup club=1 eventid={$event->getID()}, bracketname={$bracket->getName()}]")?></td>
					</tr>
				<?php } ?>
					</tbody>
				</table>
		</div>
	 </div>
</div>
