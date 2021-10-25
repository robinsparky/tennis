<?php 
//use \TE_Install;
use datalayer\MatchStatus; 
?>
<?php $now = (new DateTime('now', wp_timezone() ))->format("Y-m-d g:i a") ?>
<h2 id="parent-event-name"><?php echo $parentName; ?></h2>
<table id="<?php echo $bracketName; ?>" class="managedraw" data-eventid="<?php echo $eventId; ?>" data-bracketname="<?php echo $bracketName ?>">
<caption class='tennis-draw-caption'><?php echo $tournamentName ?>&#58;&nbsp;<?php echo $bracketName ?>&nbsp;(<?php echo $scoreRuleDesc ?>)<br><span id='digiclock'></span></caption>
<thead>
<tr>
<?php
    for( $i=1; $i <= $numRounds; $i++ ) {
        $rOf = $bracket->roundOf( $i );
?>
<th>Round Of <?php echo $rOf?></th>
    <?php } ?>
<th>Champion</th>
</tr></thead>"

<?php $cls = ''; if( $bracket->isApproved() ) $cls = 'prelimOnly'; ?>
<tbody class="<?php echo $cls;?>">   

<?php
        $rowEnder = "</tr>" . PHP_EOL;
        //rows
        $row = 0;
        foreach( $preliminaryRound as $match ) {
            ++$row; 
            if( $bracket->isApproved() ) { ?>
                <tr>
            <?php } else { ?>
                <tr data-currentpos="<?php echo $row?>" class='drawRow ui-state-default'>
            <?php }

            $r = 1; //means preliminary round (i.e. first column)
            try {
                $title = $match->toString();
                $eventId = $match->getBracket()->getEvent()->getID();
                $bracketNum = $match->getBracket()->getBracketNumber();
                $roundNum = $match->getRoundNumber();
                $matchNum = $match->getMatchNumber();

                $winner  = $umpire->matchWinner( $match );
                $winner  = empty( $winner ) ? 'no winner yet': $winner->getName();

                $homeWinner = $visitorWinner = '';
                $home    = $match->getHomeEntrant();
                $hname   = !is_null( $home ) ? $home->getName() : 'tba';
                if( $hname === $winner ) $homeWinner = $winnerClass;
                
                $hseed   = !is_null( $home ) && $home->getSeed() > 0 ? $home->getSeed() : '';
                $hname    = empty($hseed) ? $hname : $hname . "($hseed)";

                $visitor = $match->getVisitorEntrant();
                $vname   = $match->isBye() ? '' : 'tba';
                $vseed   = '';
                if( isset( $visitor ) ) {
                    $vname   = $visitor->getName();
                    if( $vname === $winner ) $visitorWinner = $winnerClass;
                    $vseed   = $visitor->getSeed() > 0 ? $visitor->getSeed() : '';
                }
                $vname = empty($vseed) ? $vname : $vname . "($vseed)";
                $cmts = $match->getComments();
                $cmts = isset( $cmts ) ? $cmts : '';

                $displayscores = $umpire->tableDisplayScores( $match );
                if( is_user_logged_in() && current_user_can('manage_options')) {
                    $modifyscores  = $umpire->tableModifyScores( $match );
                } else {
                    $modifyscores   = '';
                }

                $statusObj = $umpire->matchStatusEx( $match );
                $majorStatus = $statusObj->getMajorStatus();
                $minorStatus = $statusObj->getMinorStatus();
                $generalstatus = $statusObj->toString();

                $startDate = $match->getMatchDate_Str();
                $startTime = $match->getMatchTime_Str(2);
                $startTimeVal = $match->getMatchTime_Str();

                // Get menu template file
                $menupath = getMenuPath( $majorStatus );
?>
<td class="item-player sortable-container ui-state-default" rowspan="<?php echo $r; ?>" data-eventid="<?php echo $eventId; ?>" data-bracketnum="<?php echo $bracketNum; ?>" data-roundnum="<?php echo $roundNum; ?>" data-matchnum="<?php echo $matchNum; ?>"  data-majorstatus="<?php echo $majorStatus; ?>"  data-minorstatus="<?php echo $minorStatus; ?>">
<?php if(!empty($menupath)) require $menupath; ?>
<div class="matchinfo matchtitle ui-sortable-handle"><?php echo $title; ?></div>
<div class="matchinfo matchstatus"><?php echo $generalstatus; ?></div>
<div class="matchcomments"><?php echo $cmts; ?></div>
<div class="matchinfo matchstart"><?php echo $startDate; ?>&nbsp;<?php echo $startTime; ?></div>
<div class="changematchstart">
<input type='date' class='changematchstart' name='matchStartDate' value='<?php echo $startDate; ?>'>
<input type='time' class='changematchstart' name='matchStartTime' value='<?php echo $startTimeVal; ?>'>
<button class='button savematchstart'>Save</button>&nbsp;<button class='button cancelmatchstart'>Cancel</button></div>
<div class="homeentrant <?php echo $homeWinner; ?>"><?php echo $hname; ?></div>
<div class="displaymatchscores"><!-- Display Scores Container -->
<?php echo $displayscores; ?></div>
<div class="modifymatchscores tennis-modify-scores"><!-- Modify Scores Container -->
<?php echo $modifyscores; ?></div>
<div class="visitorentrant <?php echo $visitorWinner; ?>"><?php echo $vname; ?></div>
</td>
<?php
                //Future matches following from this match
                $futureMatches = $this->getFutureMatches( $match->getNextRoundNumber(), $match->getNextMatchNumber(), $loadedMatches );
                $rowspan = 1;
                foreach( $futureMatches as $futureMatch ) {
                    $rowspan = pow( 2, $r++ );//The trick!
                    $eventId = $futureMatch->getBracket()->getEvent()->getID();
                    $bracketNum = $futureMatch->getBracket()->getBracketNumber();
                    $roundNum = $futureMatch->getRoundNumber();
                    $matchNum = $futureMatch->getMatchNumber();
                    
                    $winner  = $umpire->matchWinner( $futureMatch );
                    $winner  = is_null( $winner ) ? 'no winner yet': $winner->getName();
                    
                    $homeWinner = $visitorWinner = '';
                    $home    = $futureMatch->getHomeEntrant();
                    $hname   = !is_null( $home ) ? $home->getName() : 'tba';
                    if( $hname === $winner ) $homeWinner = $winnerClass;
                    $hseed   = !is_null( $home ) && $home->getSeed() > 0 ? $home->getSeed() : '';
                    $hname    = empty($hseed) ? $hname : $hname . "($hseed)";
    
                    $visitor = $futureMatch->getVisitorEntrant();      
                    $vname   = $futureMatch->isBye() ? '' : 'tba';
                    $vseed   = '';
                    if( isset( $visitor ) ) {
                        $vname   = $visitor->getName();
                        if( $vname === $winner ) $visitorWinner = $winnerClass;
                        $vseed   = $visitor->getSeed() > 0 ? $visitor->getSeed() : '';
                    }
                    $vname = empty($vseed) ? $vname : $vname . "($vseed)";
                    $cmts = $futureMatch->getComments();
                    $cmts = isset( $cmts ) ? $cmts : '';

                    $startDate = $futureMatch->getMatchDate_Str();
                    $startTime = $futureMatch->getMatchTime_Str(2);
                    $startTimeVal = $futureMatch->getMatchTime_Str();
                    
                    $displayscores = $umpire->tableDisplayScores( $futureMatch );
                    $modifyscores = $umpire->tableModifyScores( $futureMatch );  

                    $statusObj = $umpire->matchStatusEx( $futureMatch );
                    $majorStatus = $statusObj->getMajorStatus();
                    $minorStatus = $statusObj->getMinorStatus();
                    $generalstatus = $statusObj->toString();

                    // Get menu template file
                    $menupath = getMenuPath( $majorStatus );
                ?>
<td class="item-player sortable-container ui-state-default" rowspan="<?php echo $rowspan; ?>" data-eventid="<?php echo $eventId; ?>" data-bracketnum="<?php echo $bracketNum; ?>" data-roundnum="<?php echo $roundNum; ?>" data-matchnum="<?php echo $matchNum; ?>"  data-majorstatus="<?php echo $majorStatus; ?>"  data-minorstatus="<?php echo $minorStatus; ?>">
<?php if( !empty($menupath) ) require $menupath; ?>
<div class="matchinfo matchtitle ui-sortable-handle"><?php echo $futureMatch->toString(); ?></div>
<div class="matchinfo matchstatus"><?php echo $generalstatus; ?></div>
<div class="matchcomments"><?php echo $cmts; ?></div>
<div class="matchinfo matchstart"><?php echo $startDate; ?><br/><?php echo $startTime; ?></div>
<div class="changematchstart">
<input type='date' class='changematchstart' name='matchStartDate' value='<?php echo $startDate; ?>'>
<input type='time' class='changematchstart' name='matchStartTime' value='<?php echo $startTimeVal; ?>'>
<button class='button savematchstart'>Save</button> <button class='button cancelmatchstart'>Cancel</button></div>
<div class="homeentrant <?php echo $homeWinner; ?>"><?php echo $hname; ?></div>
<div class="displaymatchscores"><!-- Display Scores Container -->
<?php echo $displayscores; ?></div>
<div class="modifymatchscores tennis-modify-scores"><!-- Modify Scores Container -->
<?php echo $modifyscores; ?></div>
<div class="visitorentrant <?php echo $visitorWinner; ?>"><?php echo $vname; ?></div>
</td>

<?php } //future matches  
    
//Champion column
if( 1 === $row && $bracket->isApproved() ) { ?>    
<td class="item-player sortable-container ui-state-default" rowspan="<?php echo $rowspan;?>" data-eventid="<?php echo $$eventId;?>" data-bracketnum="<?php echo $bracketNum;?>" data-roundnum="0" data-matchnum="0"  data-majorstatus="0"  data-minorstatus="0">
<div class="tennis-champion"><?php echo $championName;?></div>
</td>
<?php   
    }  //end champion
}
catch( RuntimeException $ex ) {
    $rowEnder = '';
    $this->log->error_log("$loc: preliminary round is empty at row $row");
    $this->log->error_log("$loc: encountered RuntimeException {$ex->getMessage()}");
}
finally {
    echo $rowEnder;
} // try 
} //preliminaryRound  ?>
</tbody><tfooter></tfooter>
</table>	 
<div class='bracketDrawButtons'>
<?php if( current_user_can( TE_Install::MANAGE_EVENTS_CAP )) {
    if( $numPreliminaryMatches > 0 ) {
    if( !$bracket->isApproved() ) { ?>
        <button class="button" type="button" id="approveDraw">Approve</button>
    <?php } else { ?>
        <button class="button" type="button" id="advanceMatches">Advance Matches</button>&nbsp;
    <?php } ?>
    <button class="button" type="button" id="removePrelim">Reset Bracket</button>&nbsp;
<?php }}//if user ?>
</div>
<div id="tennis-event-message"></div>
<?php 
function getMenuPath( int $majorStatus ) {
    $menupath = '';
    
    if( current_user_can( TE_Install::MANAGE_EVENTS_CAP ) 
    || current_user_can( TE_Install::RESET_MATCHES_CAP )
    || current_user_can( TE_Install::SCORE_MATCHES_CAP ) ) {
        switch( $majorStatus ) {
            case MatchStatus::NotStarted:
            case MatchStatus::InProgress:
            case MatchStatus::Completed:
            case MatchStatus::Retired:
                $menupath = TE()->getPluginPath() . 'includes\templates\menus\elimination-menu-template.php';
                $menupath = str_replace( '\\', DIRECTORY_SEPARATOR, $menupath );
                break;
            case MatchStatus::Bye:
            case MatchStatus::Waiting:
            case MatchStatus::Cancelled:
            default:
                $menupath = '';
        }
    }
    return $menupath;
}?>
