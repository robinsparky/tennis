<?php 
use datalayer\Event;
use datalayer\EventType;
use datalayer\Bracket;
use datalayer\Format;
?>
<div id="post-<?php the_ID(); ?>"> <!-- post -->
	<?php
		$mode = isset($_GET['mode']) ? $_GET['mode'] : '';
		$bracketName = isset($_GET['bracket']) ? $_GET['bracket'] : '';
		//Get $args
		$args = wp_parse_args(
				$args,
				array(
					'season' => ''
				)
			);
		$season = $args['season'];
		error_log(__FILE__ . ": season={$season}");
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

				 $okToProceed = true;
				 if( empty( $event ) ) {
					echo "<h3>Could not find the tennis event</h3>";
					$okToProceed = false;
				 }
				 
				 if( $event->isRoot() ) {
					echo "<h3>Root Tennis Event is not expected here</h3>";
					$okToProceed = false;
				} 

				if( $event->getSeason() != $season ) {
					echo "<h3>Invalid season</h3>";
					$okToProceed = false;
				}

			if($okToProceed) {
				?>
				<!-- tennis event schedule -->
				<div class="tennis-event-schedule">
				<?php
				$titlePrefix = 'Session';
				if($event->getParent()->getEventType() === EventType::LEAGUE ) {
					$titlePrefix = 'Week';
				}
				$bn = urlencode( $bracketName );
				if( $mode === "signup" ) {
					echo do_shortcode("[manage_signup eventid={$event->getID()} bracketname={$bn}]");
					$drawUrl = get_permalink() . "?mode=draw&bracket=" . $bn;
					$onClick = "\"window.location.href='" . $drawUrl . "';\"";
					//echo "<div class='tennis-link-container'><button class='button link-to-draw' onClick={$onClick}>Go to Draw</button></div>";
					echo "<div class='tennis-link-container'><a class='link-to-draw' href='{$drawUrl}'>{$bracketName} Draw</a>&nbsp;";
				}
				elseif( $mode === "draw" ) {
					switch( $event->getFormat() ) {
						case Format::ELIMINATION:
							echo do_shortcode("[manage_draw by=match eventid={$event->getID()} bracketname={$bn}]");
						break;
						case Format::ROUNDROBIN:
							echo do_shortcode("[manage_roundrobin eventid={$event->getID()} bracketname={$bn} titleprefix={$titlePrefix}]");
						break;
					}
					$drawUrl = get_permalink() . "?mode=signup&bracket=" . $bn;
					$onClick = "\"window.location.href='" . $drawUrl . "';\"";
					echo "<div class='tennis-link-container'><a class='link-to-signup' href='{$drawUrl}'>{$bracketName} Signup</a>&nbsp;";
				
					foreach( $event->getBrackets() as $bracket) {
						if( $bracketName !== $bracket->getName() ) {
							$drawUrl = get_permalink() . "?mode=draw&bracket=" . urlencode($bracket->getName());
							echo "<a class='link-to-draw' href='{$drawUrl}'>{$bracket->getName()}</a>&nbsp;";
						}
					}
					echo "</div>";
				}
				?>
				</div> <!-- /tennis event schedule -->
		</div> <!-- /event event content -->
</div> <!-- /post -->
<?php } //ok to proceed ?>

