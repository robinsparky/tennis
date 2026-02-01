<?php 
use cpt\TennisEventCpt;
use datalayer\Event;
use datalayer\EventType;
use datalayer\Bracket;
use datalayer\Format;
use datalayer\TennisTeam;
use datalayer\Player;
use commonlib\BaseLogger;

?>
<div id="post-<?php the_ID(); ?>"> <!-- post -->
	<?php
	$logger = new BaseLogger();
		$mode = isset($_GET['mode']) ? $_GET['mode'] : '';
		$bracketName = isset($_GET['bracket']) ? $_GET['bracket'] : '';
		$showTeams = isset($_GET['showteams']) ? $_GET['showteams'] : '';
		$logger->error_log(__FILE__);
		//Get $args
		$args = wp_parse_args(
				$args,
				array(
					'season' => ''
				)
			);

		$season = $args['season'];
		$logger->error_log("season={$season}");
		$logger->error_log("showteams={$showTeams}");
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
				 if($okToProceed && !isset( $event ) ) {
					echo "<h3>Could not find the tennis event</h3>";
					$okToProceed = false;
				 }
				 
				 if($okToProceed && $event->isRoot() ) {
					echo "<h3>Root Tennis Event is not expected here</h3>";
					$okToProceed = false;
				} 

				if($okToProceed && $event->getSeason() != $season ) {
					echo "<h3>Invalid season</h3>";
					$okToProceed = false;
				}
				 $eventType = $event->getParent()->getEventType();
				 $bracket = null;
				 foreach( $event->getBrackets() as $b ) {
					if( $b->getName() === $bracketName ) {
						$bracket = $b;
						break;
					}	
				 }
			if($okToProceed) {
				// Get template file
				$path = TE()->getPluginPath() . 'includes\templates\controls\searchDialog.php';
				$path = str_replace( '\\', DIRECTORY_SEPARATOR, $path );
				$eventsUrl = get_post_type_archive_link( TennisEventCpt::CUSTOM_POST_TYPE ) . "?season={$season}";
				?>
				<!-- tennis event schedule -->
				<div class="tennis-event-schedule">
				<?php
				$titlePrefix = 'Session';
				if($event->getParent()->getEventType() === EventType::TEAMTENNIS ) {
					$titlePrefix = 'Week';
				}
				$bn = urlencode( $bracketName );		
				//echo "<div><a class='link-to-events' href='{$eventsUrl}'>Event Descriptions</a></div>";

				if( $mode === "signup" ) {
					//Include the search button to find entrants
					//$buttonTitle="Find an Entrant"; $container="ul.eventSignup";$target=".entrantName";require( $path );
					echo do_shortcode("[manage_signup eventid={$event->getID()} bracketname={$bn} showteams={$showTeams}]");
					$drawUrl = get_permalink() . "?mode=draw&bracket=" . $bn . "&season={$season}";
					echo "<div class='tennis-link-container'>";
					echo "<a class='link-to-draw' href='{$drawUrl}'>{$bracketName} Draw</a></br>";
					echo "<a class='link-to-events' href='{$eventsUrl}'>Event Descriptions</a>";
					echo "</div>";					
					//TeamTennis only
					if( $eventType === EventType::TEAMTENNIS) {
					$numPrelimMatches = count( $bracket->getMatchesByRound(1) );
						//This section is for team tennis team assignments
						if(current_user_can( TE_Install::MANAGE_EVENTS_CAP ) && !$event->isClosed() && isset( $bracket ) & $numPrelimMatches < 1) {
							//Editable
							$path = TE()->getPluginPath() . 'includes\templates\teams\teamRegistrationEditable.php';
							$path = str_replace( '\\', DIRECTORY_SEPARATOR, $path );
							require($path);
							} else {
							//Readonly
							$path = TE()->getPluginPath() . 'includes\templates\teams\teamRegistrationReadOnly.php';
							$path = str_replace( '\\', DIRECTORY_SEPARATOR, $path );
							require($path);
						}
					} //TeamTennis
				}
				elseif( $mode === "draw" ) {
					//Include the search button to find players
					$buttonTitle="Find a Player"; $container="#{$bn}";$target=".homeentrant,.visitorentrant";require( $path );

					switch( $event->getFormat() ) {
						case Format::ELIMINATION:
							echo do_shortcode("[manage_draw by=match eventid={$event->getID()} bracketname={$bn}]");
						break;
						case Format::ROUNDROBIN:
							echo do_shortcode("[manage_roundrobin eventid={$event->getID()} bracketname={$bn} titleprefix={$titlePrefix}]");
						break;
					}
					$drawUrl = get_permalink() . "?mode=signup&bracket=" . $bn . "&season=$season";
					echo "<div class='tennis-link-container'>";
					echo "<a class='link-to-signup' href='{$drawUrl}'>{$bracketName} Signup</a></br>";
				
					foreach( $event->getBrackets() as $bracket) {
						if( $bracketName !== $bracket->getName() ) {
							$drawUrl = get_permalink() . "?mode=draw&bracket=" . urlencode($bracket->getName()) . "&season=$season";
							echo "<a class='link-to-draw' href='{$drawUrl}'>{$bracket->getName()}&nbsp;Draw</a></br>";
						}
					}
					echo "<a class='link-to-events' href='{$eventsUrl}'>Event Descriptions</a>";
					echo "</div>";
				}
				else {
					echo "<h1>Whoops!</h1>";
				}
				?>
				</div> <!-- /tennis event schedule -->
		</div> <!-- /event event content -->
</div> <!-- /post -->
<?php } //ok to proceed
?>