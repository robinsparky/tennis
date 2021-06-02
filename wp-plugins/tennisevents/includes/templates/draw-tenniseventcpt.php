<div id="post-<?php the_ID(); ?>"> <!-- post -->
	<?php
		$mode = isset($_GET['manage']) ? $_GET['manage'] : "";
		$bracketName = isset($_GET['bracket']) ? $_GET['bracket'] : '';
	?>
		<!-- tennis event content -->
		<div class="tennis-event-content">
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
				 if( empty( $event ) ) {
					 wp_die( __("Could not find associated event!", TennisEvents::TEXT_DOMAIN ) );
				 }
				 if( $event->isRoot() ) {
				   wp_die( __("Root Tennis Event is not expected here!", TennisEvents::TEXT_DOMAIN ) );
				} 

				?>
				<!-- tennis event schedule -->
				<div class="tennis-event-schedule">
				<?php
				if( $mode === "signup" ) {
					echo do_shortcode("[manage_signup eventid={$event->getID()}, bracketname={$bracketName}]");
					$drawUrl = get_permalink() . "?manage=draw&bracket=" . $bracketName;
					$onClick = "\"window.location.href='" . $drawUrl . "';\"";
					//echo "<div class='tennis-link-container'><button class='button link-to-draw' onClick={$onClick}>Go to Draw</button></div>";
					echo "<div class='tennis-link-container'><a class='link-to-draw' href='{$drawUrl}'>{$bracketName} Draw</a>&nbsp;";
				}
				elseif( $mode === "draw" ) {
					switch( $event->getFormat() ) {
						case Format::ELIMINATION:
							echo do_shortcode("[manage_draw by=match eventid={$event->getID()}, bracketname={$bracketName}]");
						break;
						case Format::ROUNDROBIN:
							echo do_shortcode("[manage_roundrobin eventid={$event->getID()}, bracketname={$bracketName}]");
						break;
					}
					$drawUrl = get_permalink() . "?manage=signup&bracket=" . $bracketName;
					$onClick = "\"window.location.href='" . $drawUrl . "';\"";
					echo "<div class='tennis-link-container'><a class='link-to-draw' href='{$drawUrl}'>{$bracketName} Signup</a>&nbsp;";
				
					foreach( $event->getBrackets() as $bracket) {
						if( $bracketName !== $bracket->getName() ) {
							$drawUrl = get_permalink() . "?manage=draw&bracket=" . $bracket->getName();
							echo "<a class='link-to-draw' href='{$drawUrl}'>{$bracket->getName()}</a>&nbsp;";
						}
					}
					echo "</div>";
				}
				?>
				</div> <!-- /tennis event schedule -->
		</div> <!-- /event event content -->
</div> <!-- /post -->

