<div id="post-<?php the_ID(); ?>" <?php //post_class('blog-lg-area-left'); ?>>
	<div class="event-content">						
	<?php //appointment_aside_meta_content();
		$mode = isset($_GET['manage']) ? $_GET['manage'] : "";
		$bracketName = isset($_GET['bracket']) ? $_GET['bracket'] : '';
		//the_title('<h2>','</h2>'); 
	?>
		<div class="event-content-body">
			<?php 
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
					 wp_die( __("Could not find associated event!", TennisEvents::TEXT_DOMAIN ) );
				 }
				 if( $event->isRoot() ) {
				   wp_die( __("Root Tennis Event not expected here!", TennisEvents::TEXT_DOMAIN ) );
				} 
				// call editor content of post/page	s
				wp_link_pages( );
				?>
				<div class="eventsignup">
				<?php
				if( $mode === "signup" ) {
					echo do_shortcode("[manage_signup club=1 eventid={$event->getID()}, bracketname={$bracketName}]");
					$drawUrl = get_permalink() . "?manage=draw&bracket=" . $bracketName;
					$onClick = "\"window.location.href='" . $drawUrl . "';\"";
					echo "<div class='link-container'><button class='button link-to-draw' onClick={$onClick}>Draw</button></div>";
				}
				elseif( $mode === "draw" ) {
					switch( $event->getFormat() ) {
						case Format::SINGLE_ELIM:
							echo do_shortcode("[manage_draw by=match eventid={$event->getID()}, bracketname={$bracketName}]");
						break;
						case Format::POINTS:
							echo do_shortcode("[manage_roundrobin eventid={$event->getID()}, bracketname={$bracketName}]");
						break;
					}
					$drawUrl = get_permalink() . "?manage=signup&bracket=" . $bracketName;
					$onClick = "\"window.location.href='" . $drawUrl . "';\"";
					echo "<div class='link-container'><button class='button link-to-draw' onClick={$onClick}>Signup</button></div>";
				}
				//the_content( __('Read More', TennisEvents::TEXT_DOMAIN ) );
				?>
				</div>
		</div>
	 </div>
</div>
