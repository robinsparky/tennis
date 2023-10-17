<?php
use api\TournamentDirector;
use cpt\TennisEventCpt;
use datalayer\Event;
use datalayer\EventType;
use datalayer\Format;
use datalayer\MatchType;
use datalayer\ScoreType;
use datalayer\GenderType;
?>

<!-- Root event -->
<div id="<?php echo get_the_ID()?>" class="tennis-parent-event" data-event-id="<?php echo $event->getID();?>">
<ul class='tennis-event-meta tennis-event-meta-detail'>		
	<li class="tennis-parent-event-title"><?php echo __("Title: ", TennisEvents::TEXT_DOMAIN);?><span><?php the_title();?></span></li>		
	<li class='tennis-root-event-type'><?php echo __("Event Type: ", TennisEvents::TEXT_DOMAIN); echo $eventType; ?></li>
	<li class='tennis-root-event-start'><?php echo __("Start Date: ", TennisEvents::TEXT_DOMAIN); echo $startDate; ?></li>
	<li class='tennis-root-event-end'><?php echo __("End Date: ", TennisEvents::TEXT_DOMAIN); echo $endDate; ?></li>
	<li class='tennis-root-event-description'><?php echo __("Description: ", TennisEvents::TEXT_DOMAIN);?><p><?php the_content();?></p></li>
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
					<span class="bracket-name">
					<?php echo $bracket->getName()?></span>&colon;
					<a class="bracket-signup-link" href="<?php the_permalink(); ?>?season=<?php echo urlencode($season)?>&mode=signup&bracket=<?php echo urlencode($bracket->getName()); ?>">Signup, </a>
					<a class="bracket-draw-link" href="<?php the_permalink() ?>?season=<?php echo urlencode($season)?>&mode=draw&bracket=<?php echo urlencode($bracket->getName()); ?>">Draw</a>
					<?php } ?>	
				</li>
			</ul>
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