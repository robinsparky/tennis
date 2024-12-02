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
<h5 class='tennis-draw-caption-dates'><span>Starts On</span>&nbsp;<span><?php echo $strEventStartDate;?></span>&semi;&nbsp;<span>Ends On</span>&nbsp;<span><?php echo $strEventEndDate;?></span>&semi;&nbsp;<span>And Today Is</span>&nbsp;<span><?php echo $now;?></span></h5>
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
            $this->log->error_log("$loc: Round Number={$match->getRoundNumber()} and TennisMatch Number={$match->getMatchNumber()}");
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
                $generalstatus = "";
                
                $startDate = $match->getMatchDate_Str();
                $startTime = $match->getMatchTime_Str(2);
                $startTimeVal = $match->getMatchTime_Str();
                $startLabel = "Started:";
                if($match->getMatchDateTime() > date_create()) {
                    $startLabel = "Starts:";
                }
                $startDisplay="";
                if(!empty($startDate)) {
                    $startDisplay = "{$startLabel} {$startDate} {$startTime}";
                }
                else {
                    $generalstatus = $statusObj->toString();
                }


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
<div class="match item-player" style="<?php echo $style?>;" data-eventid="<?php echo $eventId; ?>" data-bracketnum="<?php echo $bracketNum; ?>" data-roundnum="<?php echo $roundNum; ?>" data-matchnum="<?php echo $matchNum; ?>" 
        data-majorstatus="<?php echo $majorStatus; ?>"  data-minorstatus="<?php echo $minorStatus; ?>" data-startDate="<?php echo $startDisplay;?>" data-status="<?php echo $generalstatus?>" data-matchtitle="<?php echo $title?>" data-currentscore="<?php echo $matchScore;?>">
<?php if($totalRounds === $roundNum ) { ?>
    <header class="finalroundhdr">Championship</header>
<?php } ?>
    <article class="homeentrant">
    <ul>
        <li><?php echo $hname;?></li>
        <li><?php echo $firstMatchScores;?>
    </ul>
    </article>
    <?php if(!empty($cmts)) { ?>
    <article class="readonly_matchcomments"><?php echo $cmts; ?></article>
    <?php } ?>
<?php if( $totalRounds === $roundNum && !empty($champion) ) { ?>
    <article class="championship_results">
        <?php echo "{$championName}<br>{$championScore}"; ?>        
    </article>
<?php } ?>
    <article class="visitorentrant"> 
        <ul>
            <li><?php echo $vname;?></li>
            <li><?php echo $secondMatchScores;?></li>
        </ul>
    </article>
</div>
<?php
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
