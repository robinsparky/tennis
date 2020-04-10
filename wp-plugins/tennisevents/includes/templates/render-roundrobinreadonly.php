<table id="<?php echo $bracketName;?>" class="bracketrobin" data-format="" data-eventid="<?php echo $this->eventId;?>" data-bracketname="<?php echo $bracketName;?>">
<caption><?php echo $tournamentName;?>&#58;&nbsp;<?php echo $bracketName; ?> Bracket</caption>
<thead>
<?php 
    $winnerClass = "matchwinner";
    foreach( $loadedMatches as $roundnum => $matches ) {
?>
<tr><th>Round <?php echo $roundnum; ?></th></tr></thead>
<tbody>
<?php foreach( $matches as $match ) { 
    $title = $match->toString();
    $eventId = $match->getBracket()->getEvent()->getID();
    $bracketNum = $match->getBracket()->getBracketNumber();
    $roundNum = $match->getRoundNumber();
    $matchNum = $match->getMatchNumber();

    $winner  = $umpire->matchWinner( $match );
    $winner  = is_null( $winner ) ? 'no winner yet': $winner->getName();

    $homeWinner = $visitorWinner = '';
    $home    = $match->getHomeEntrant();
    $hname   = !is_null( $home ) ? $home->getName() : 'tba';
    if( $hname === $winner ) $homeWinner = $winnerClass;
    
    $hseed   = !is_null( $home ) && $home->getSeed() > 0 ? $home->getSeed() : '';
    $hname    = empty($hseed) ? $hname : $hname . "($hseed)";

    $visitor = $match->getVisitorEntrant();
    $vname   = 'tba';
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

    //$status  = $umpire->matchStatus( $match );
    $statusObj = $umpire->matchStatusEx( $match );
    $majorStatus = $statusObj->getMajorStatus();
    $minorStatus = $statusObj->getMinorStatus();
    $status = $statusObj->toString();

    $startDate = $match->getMatchDate_Str();
    $startTime = $match->getMatchTime_Str();
    $startedMess = '';
    if( strlen( $startDate ) > 0 ) {
        $startedMess = __("Started:", TennisEvents::TEXT_DOMAIN);
    }
?>

<tr>
<td class="item-player" data-eventid="<?php echo $eventId;?>" 
    data-bracketnum="<?php echo $bracketNum;?>" 
    data-roundnum="<?php echo $roundNum;?>" 
    data-matchnum="<?php echo $matchNum?>" 
    data-majorstatus="<?php echo $majorStatus?>" 
    data-minorstatus="<?php echo $minorStatus?>" >
<div class="matchinfo matchtitle"><?php echo $title; ?>&nbsp;<span class="matchinfo matchstatus"><?php echo $status; ?></span></div>

<div class="matchinfo matchstart"><?php echo $startedMess; ?>&nbsp;<?php echo $startDate;?>&nbsp;<?php echo $startTime; ?></div>
<div class="matchinfo matchcomments"><?php echo $cmts; ?></div>
<div class="homeentrant <?php echo $homeWinner; ?>"><?php echo $hname; ?></div>
<div class="displaymatchscores"><!-- Display Scores Container -->
<?php echo $displayscores; ?></div>
<div class="visitorentrant <?php echo $homeWinner; ?>"><?php echo $vname; ?></div>
</td>
</tr>
<?php } //end matches ?>

<?php } //end rounds ?>
</tbody><tfooter></tfooter>
</table>
<div class='bracketDrawButtons'>

</div>
<div id="tennis-event-message"></div>