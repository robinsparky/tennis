<h3 id="bracket-name"><?php 
    echo $tournamentName;?>&#58;&nbsp;<?php echo $bracketName; ?> Bracket
    (<?php echo $scoreRuleDesc; ?>)</h3>
<main id="<?php echo $bracketName;?>" class="bracketrobin" data-format="" data-eventid="<?php echo $this->eventId;?>" data-bracketname="<?php echo $bracketName;?>">
<?php 
    $winnerClass = "matchwinner";
    foreach( $loadedMatches as $roundnum => $matches ) {
        $this->log->error_log("render-RoundRobinReadOnly: round number {$roundnum}" . PHP_EOL)
?>
<section class="roundrobin-round"><span>Round <?php echo $roundnum; ?></span>

<?php foreach( $matches as $match ) { 
    $begin = microtime( true );
    $title = $match->toString();
    $this->log->error_log("render-RoundRobinReadOnly: {$title}");
    
    $eventId = $bracket->getEvent()->getID();
    $bracketNum = $bracket->getBracketNumber();
    $roundNum = $match->getRoundNumber();
    $matchNum = $match->getMatchNumber();

    extract( $umpire->getMatchSummary( $match ) );

    $home    = $match->getHomeEntrant();
    $homeWinner = '';
    $visitorWinner = '';
    $visitor = $match->getVisitorEntrant();
    if( $andTheWinnerIs === 'home') {
        $winner = $home->getName();
        $homeWinner = $winnerClass;
    }
    elseif( $andTheWinnerIs === 'visitor' ) {
        $winner = $visitor->getName();
        $visitorWinner = $winnerClass;
    }
    elseif( $andTheWinnerIs === 'tie') {
        $homeWinner = $winnerClass;
        $visitorWinner = $winnerClass;
    }
    else {
        $winner = 'no winner yet';
    }

    $hname   = !is_null( $home ) ? $home->getName() : 'tba';
    
    $hseed   = !is_null( $home ) && $home->getSeed() > 0 ? $home->getSeed() : '';
    $hname    = empty($hseed) ? $hname : $hname . "($hseed)";

    $vname   = 'tba';
    $vseed   = '';
    if( isset( $visitor ) ) {
        $vname   = $visitor->getName();
        $vseed   = $visitor->getSeed() > 0 ? $visitor->getSeed() : '';
    }
    $vname = empty($vseed) ? $vname : $vname . "($vseed)";
    
    $cmts = $match->getComments();
    $cmts = isset( $cmts ) ? $cmts : '';
    $displayscores = $umpire->tableDisplayScores( $match );   

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
    $this->log->error_log( sprintf("%s: %0.6f for Match(%s)", "render-RoundRobinReadOnly Elapsed time", \commonlib\micro_time_elapsed( $begin ), $title));
?>
<article class="item-player" data-eventid="<?php echo $eventId;?>" 
 data-bracketnum="<?php echo $bracketNum;?>" 
 data-roundnum="<?php echo $roundNum;?>" 
 data-matchnum="<?php echo $matchNum?>" 
 data-majorstatus="<?php echo $majorStatus?>" 
 data-minorstatus="<?php echo $minorStatus?>" >
<div class="matchinfo matchtitle"><span><?php echo $title;?></span></div>
<div class="matchinfo matchstatus"><span><?php echo $status; ?></span></div>
<div class="matchinfo matchstart"><?php echo $startedMess; ?>&nbsp;<?php echo $startDate;?>&nbsp;<?php echo $startTime; ?></div>
<div class="matchinfo matchcomments"><?php echo $cmts; ?></div>
<div class="homeentrant <?php echo $homeWinner; ?>"><?php echo $hname; ?></div>
<div class="displaymatchscores"><!-- Display Scores Container -->
<?php echo $displayscores; ?></div>
<div class="visitorentrant <?php echo $visitorWinner; ?>"><?php echo $vname; ?></div>
</article>
<?php } //end matches ?>
</section>
<?php } //end rounds ?>
</main>
<div id="tennis-event-message"></div>