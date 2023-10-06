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
			$leafEvent = Event::getEventByExtRef( get_the_ID() );					
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
			$minAge = get_post_meta( get_the_ID(), TennisEventCpt::AGE_MIN_META_KEY, true );
			$maxAge = get_post_meta( get_the_ID(), TennisEventCpt::AGE_MAX_META_KEY, true );
		?>
		<!-- Leaf Event -->
		<section class="tennis-leaf-event"> 
			<?php echo the_title("<h3 class='tennis-leaf-event-title'>","</h3>") ?>
			<?php the_content();
				$postId = get_the_ID();
				$evtId = $leafEvent->getID();
				
				$event = Event::get($evtId);
				$td = new TournamentDirector( $event );
				$brackets = $td->getBrackets();
	
				//All Brackets must not be approved and must not have started
				$restrictChanges = false;
				foreach( $brackets as $bracket ) {
					if( $bracket->isApproved() ) {
						$restrictChanges = true;
						break;
					}
			
					//Cannot schedule preliminary rounds if matches have already started
					if( 0 < $td->hasStarted( $bracket->getName() ) ) {
						$restrictChanges = true;
						break;
					}
				}

				if($restrictChanges) {
					//No drop down
					$genderTypeDropDown = $genderType;
				}
				else  {
					//Gender Type Drop Down
					$genderTypes = GenderType::AllTypes();
					$genderTypeDropDown = "<select name='GenderTypes' class='gender_selector' data-origval='{$genderType}'>";
					foreach( $genderTypes as $key=>$value ) {
						$selected = ($value === $genderType) ? "selected" : "";
						$genderTypeDropDown .= "<option value='{$key}' {$selected}>{$value}</option>";
					}
					$genderTypeDropDown .= "</select>";
				}

				if($restrictChanges) {
					//No drop down
					$matchTypeDropDown = $matchType;
				}
				else {
					//Match Type Drop Down
					$matchTypes = MatchType::AllTypes();
					$matchTypeDropDown = "<select name='MatchTypes' class='match_type_selector' data-origval='{$matchType}>";
					foreach( $matchTypes as $key=>$value ) {
						$selected = ($value === $matchType) ? "selected" : "";
						$matchTypeDropDown .= "<option value='{$key}' {$selected}>{$value}</option>";
					}
					$matchTypeDropDown .= "</select>";
				}

				if($restrictChanges) {
					//No drop down
					$formatDropDown = $eventFormat;
				}
				else {
					//Format Drop Down
					$allFormats = Format::AllFormats();
					$formatDropDown = "<select name='AllFormats' class='format_selector' data-origval='{$eventFormat}>";
					foreach( $allFormats as $key=>$value ) {
						$selected = ($value === $eventFormat) ? "selected" : "";
						$formatDropDown .= "<option value='{$key}' {$selected}>{$value}</option>";
					}
					$formatDropDown .= "</select>";
				}

				if($restrictChanges) {
					$scoreRulesDropDown = "<span class='score_rules_text' data-scoretype='{$scoreType}'>{$scoreRuleDesc}</span>";
				}
				else {
					//Score Rules Drop Down
					$scoreRulesDescriptions =  ScoreType::get_instance()->getRuleDescriptions();
					$scoreRulesDropDown = "<select name='ScoreRules' class='score_rules_selector'>";
					foreach( $scoreRulesDescriptions as $key=>$value ) {
						$selected = ($key === $scoreType) ? "selected='true'" : "";
						$scoreRulesDropDown .= "<option value='{$key}' {$selected}>{$value}</option>";
					}
					$scoreRulesDropDown .= "</select>";
				}
			?>
			<table id='<?php echo $evtId ?>' class='tennis-event-meta' data-eventid='<?php echo $evtId; ?>' data-postid='<?php echo $postId; ?>'>
			<tbody>				
				<tr class="event-meta-detail"><td><strong><?php echo __("Gender", TennisEvents::TEXT_DOMAIN);?></strong></td>
					<td><?php echo $genderTypeDropDown; ?></td></tr>
				<tr class="event-meta-detail"><td><strong><?php echo __("Match Type", TennisEvents::TEXT_DOMAIN);?></strong></td>
					<td><?php echo $matchTypeDropDown; ?></td></tr>
				<tr class="event-meta-detail"><td><strong><?php echo __("Categories", TennisEvents::TEXT_DOMAIN);?></strong></td>
					<td><?php echo $terms; ?></td></tr>
				<tr class="event-meta-detail"><td><strong><?php echo __("Min Age", TennisEvents::TEXT_DOMAIN);?></strong></td>
					<td><input class='min_age_input' min="1" max="100" type="number" size="5" value="<?php echo $minAge; ?>" data-origval="<?php echo $minAge;?>"></td></tr>
				<tr class="event-meta-detail"><td><strong><?php echo __("Max Age", TennisEvents::TEXT_DOMAIN);?></strong></td>
					<td><input class='max_age_input' min="1" max="100" type="number" size="5" value="<?php echo $maxAge; ?>" data-origval="<?php echo $maxAge;?>"></td></tr>
				<tr class="event-meta-detail"><td><strong><?php echo __("Signup By", TennisEvents::TEXT_DOMAIN);?></strong></td>
					<td><input class='signup_by_input' type="date" max='<?php echo $startDate;?>' value="<?php echo $signupBy; ?>" data-origval="<?php echo $signupBy;?>"></td></tr>
				<tr class="event-meta-detail"><td><strong><?php echo __("Starts", TennisEvents::TEXT_DOMAIN);?></strong></td>
					<td><input class='start_date_input' type="date" min='<?php echo $signupBy;?>' max='<?php echo $endDate;?>' value="<?php echo $startDate; ?>" data-origval="<?php echo $startDate;?>"></td></tr>
				<tr class="event-meta-detail"><td><strong><?php echo __("Ends", TennisEvents::TEXT_DOMAIN);?></strong></td>
					<td><input class='end_date_input' type="date" min='<?php echo $startDate;?>' value="<?php echo $endDate; ?>" data-origval="<?php echo $endDate;?>"></td></tr>
				<tr class="event-meta-detail"><td><strong><?php echo __("Format", TennisEvents::TEXT_DOMAIN);?></strong></td>
					<td><?php echo $formatDropDown; ?></td></tr>
				<tr class="event-meta-detail"><td><strong><?php echo __("Scoring", TennisEvents::TEXT_DOMAIN);?></strong></td>
					<td><?php echo $scoreRulesDropDown; ?>
					<div class="scoreruleslist">
					<?php 
						foreach(ScoreType::get_instance()->ScoreRules as $ruleId=>$values) { 
							echo "<ul class='scorerulename {$ruleId}'>";?>
							<?php $ruleName = ScoreType::get_instance()->getRuleDescriptions()[$ruleId];  //echo "{$ruleName}";
								foreach($values as $rule=>$value) { echo "<li class='scorerule'>{$rule}: {$value}</li>";}?>
						</ul><?php } ?>
					</div>
					</td>
				</tr>
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
					<span class="bracket-name" contenteditable>
					<?php echo $bracket->getName()?></span>&colon;
					<a class="bracket-signup-link" href="<?php the_permalink(); ?>?season=<?php echo urlencode($season)?>&mode=signup&bracket=<?php echo urlencode($bracket->getName()); ?>">Signup, </a>
					<a class="bracket-draw-link" href="<?php the_permalink() ?>?season=<?php echo urlencode($season)?>&mode=draw&bracket=<?php echo urlencode($bracket->getName()); ?>">Draw</a>
					<img class="remove-bracket" src="<?php echo TE()->getPluginUrl() . 'img/removeIcon.gif' ?>">
					<?php } ?>	
				</li>
			</ul>
			<button type="button" class="button tennis-add-bracket" data-eventid="<?php echo $leafEvent->getID();?>" >Add Bracket</button>
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