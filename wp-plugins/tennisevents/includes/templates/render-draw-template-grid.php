<?php 
//use \TE_Install;
//use datalayer\MatchStatus; 
//use datalayer\InvalidMatchException;

use datalayer\MatchStatus;
use datalayer\InvalidMatchException;

$now = (new DateTime('now', wp_timezone() ))->format("Y-m-d g:i a");
$cols = "grid-template-columns: repeat({$totalRounds}, 1fr)";
switch($totalRounds) {
    case 1:
        $gridWidth="200px";
        break;
    case 2:
        $gridWidth="400px";
        break;
    case 3:
        $gridWidth="700px";
        break;
    case 4:
        $gridWidth="1050px";
        break;       
    case 5:
        $gridWidth="1250px";
        break;      
    case 6:
        $gridWidth="1350px";
        break;      
    case 7:
        $gridWidth="1450px";
        break;
    default: 
        $gridwith="1250px";
}
//
?>
<h2 id="parent-event-name"><?php echo $parentName; ?></h2>
<h3 class='tennis-draw-caption'><?php echo $tournamentName ?>&#58;&nbsp;<?php echo $bracketName ?>&nbsp;(<?php echo $scoreRuleDesc ?>)</h3>
<h4><?php echo $now;?></h4>
<!--Start of Grid-->
<div id="<?php echo $bracketName;?>" class="drawgrid" style="<?php echo $cols;?>;" data-eventid="<?php echo $eventId;?>" 
data-bracketname="<?php echo $bracketName ?>" data-champion="<?php echo $championName;?>" data-championscore="<?php echo $championScore;?>" data-tournament="<?php echo $tournamentName; ?>">
<?php
    $menupath = '';
    $numRounds = 0;
    foreach($loadedMatches as $round) {
        ++$numRounds;
        $numMatches = 0;
        $prevMatch1 = $prevMatch2 = null;
        foreach( $round as $match ) {
            ++$numMatches;
            $this->log->error_log("$loc: numRounds={$numRounds} and numMatches={$numMatches}");
            $this->log->error_log("$loc: Round Number={$match->getRoundNumber()} and Match Number={$match->getMatchNumber()}");
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

                // $displayscores = $umpire->tableDisplayScores( $match );
                // if( is_user_logged_in() && current_user_can('manage_options')) {
                //     $modifyscores  = $umpire->tableModifyScores( $match );
                // } else {
                //     $modifyscores   = '';
                // }

                $statusObj = $umpire->matchStatusEx( $match );
                $majorStatus = $statusObj->getMajorStatus();
                $minorStatus = $statusObj->getMinorStatus();
                $generalstatus = $statusObj->toString();
                
                $startDate = $match->getMatchDate_Str();
                $startTime = $match->getMatchTime_Str(2);
                $startTimeVal = $match->getMatchTime_Str();
                $matchScore = $umpire->strGetScores($match);

                //Get the previous 2 matches that this one is based on
                $firstMatchScores='';
                $secondMatchScores='';
                if( $match->getRoundNumber() > 1 ) {
                    $pivotMNum = $match->getMatchNumber() * 2;
                    $pivotRNum = $match->getRoundNumber() - 1;
                    $prevMatch2 = $loadedMatches[$pivotRNum][$pivotMNum];
                    $prevMatch1 = $loadedMatches[$pivotRNum][$pivotMNum - 1];
                    $prev1 = $pivotMNum - 1;
                    $this->log->error_log("$loc: prev match #1 round={$pivotRNum}, matchNum={$prev1}");
                    $this->log->error_log("$loc: prev match #2 round={$pivotRNum}, matchNum={$pivotMNum}");
                    if(empty($prevMatch1) || empty($prevMatch2)) {
                        $mess = __("Previous matches could not be found.", TennisEvents::TEXT_DOMAIN);
                        $this->log->error_log("$loc: {$mess}");
                        throw new InvalidMatchException($mess);
                    }
                    $firstMatchScores = $umpire->strGetScores($prevMatch1,true);
                    $secondMatchScores = $umpire->strGetScores($prevMatch2,true);
                    $this->log->error_log("$loc: first match winner scores='{$firstMatchScores}'");
                    $this->log->error_log("$loc: second match winner scores='{$secondMatchScores}'");
                    $firstMatchScores= strlen($firstMatchScores) > 1 ? "{$firstMatchScores}" : '';
                    $secondMatchScores= strlen($secondMatchScores) > 1 ? "{$secondMatchScores}" : '';
                    if( is_null($home)) $firstMatchScores = '';
                    if( is_null($visitor)) $secondMatchScores = '';
                }
       
                // Get the grid style attributes
                $style = $this->getGridStyle($match->getRoundNumber(), $match->getMatchNumber());
?>
 <?php if($match->isBye()) { ?>
    <div class="match item-player byematch" style="<?php echo $style?>; border: none;box-shadow: none;" data-eventid="<?php echo $eventId; ?>" data-bracketnum="<?php echo $bracketNum; ?>" data-roundnum="<?php echo $roundNum; ?>" data-matchnum="<?php echo $matchNum; ?>" 
        data-majorstatus="<?php echo $majorStatus; ?>"  data-minorstatus="<?php echo $minorStatus; ?>" data-matchtitle="<?php echo $title?>" data-currentscore="<?php echo $matchScore;?>">
 </div>
<?php } 
else {
?>
<div class="match item-player" style="<?php echo $style?>;" data-eventid="<?php echo $eventId; ?>" data-bracketnum="<?php echo $bracketNum; ?>" data-roundnum="<?php echo $roundNum; ?>" data-matchnum="<?php echo $matchNum; ?>" 
        data-majorstatus="<?php echo $majorStatus; ?>"  data-minorstatus="<?php echo $minorStatus; ?>" data-status="<?php echo $generalstatus?>" data-matchtitle="<?php echo $title?>" data-currentscore="<?php echo $matchScore;?>">
    <article class="homeentrant">
        <ul>
            <li><?php echo $hname;?></li>
            <li><?php echo $firstMatchScores;?>
        </ul>
    </article>
    <div style="display:none;">
    <article class="matchinfo matchstatus"><?php echo $generalstatus; ?></article>
        <article class="matchinfo matchtitle"><?php echo $title; ?></article>
        <article class="matchcomments"><?php echo $cmts; ?></article>
        <article class="matchinfo matchstart"  style="display: none;"><?php echo $startDate; ?>&nbsp;<?php echo $startTime; ?></article>
        <div class="changematchstart">
            <input type='date' class='changematchstart' name='matchStartDate' value='<?php echo $startDate; ?>'>
            <input type='time' class='changematchstart' name='matchStartTime' value='<?php echo $startTimeVal; ?>'>
            <button class='button savematchstart'>Save</button>&nbsp;<button class='button cancelmatchstart'>Cancel</button>
            <article class="homeentrant <?php echo $homeWinner; ?>"><?php echo $hname;?></article>
        </div>
        <div class="displaymatchscores"><!-- Display Scores Container -->
            <?php echo $displayscores; ?>
        </div>
        <div class="modifymatchscores tennis-modify-scores"><!-- Modify Scores Container -->
            <?php echo $modifyscores; ?>
        </div>
    </div>
    <article class="visitorentrant"> 
        <ul>
            <li><?php echo $vname;?></li>
            <li><?php echo $secondMatchScores;?></li>
        </ul>
    </article>
</div>
<?php
}
    } catch( RuntimeException $ex ) {
        $this->log->error_log("$loc: encountered RuntimeException {$ex->getMessage()}");
        throw $ex;
    } // try 
 } //round 
} //loadedMatches ?>
</div> <!--End of Grid-->
	 
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