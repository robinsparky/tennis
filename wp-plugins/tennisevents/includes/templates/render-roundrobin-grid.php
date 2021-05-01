<h2 id="parent-event-name"><?php echo $parentName ?></h2>
<h3 id="bracket-name"><?php  
    echo $tournamentName;?>&#58;&nbsp;<?php echo $bracketName; ?>
    (<?php echo $scoreRuleDesc; ?>)</h3>

<main id="<?php echo $bracketName;?>" class="bracketrobin" data-format="" data-eventid="<?php echo $this->eventId;?>" data-bracketname="<?php echo $bracketName;?>">

<?php 
    $winnerClass = "matchwinner";
    foreach( $loadedMatches as $roundnum => $matches ) {
?>
<section class="roundrobin-round"><span>Round <?php echo $roundnum; ?></span>

<?php foreach( $matches as $match ) { 
    $begin = microtime( true );

    $title = $match->toString();
    $this->log->error_log("render-RoundRobin: {$title}");

    $eventId = $match->getBracket()->getEvent()->getID();
    $bracketNum = $match->getBracket()->getBracketNumber();
    $roundNum = $match->getRoundNumber();
    $matchNum = $match->getMatchNumber();
    
    extract( $umpire->getMatchSummary( $match ) );
    
    $home    = $match->getHomeEntrant();
    $homeWinner = '';
    $visitor = $match->getVisitorEntrant();
    $visitorWinner = '';
    if( $andTheWinnerIs === 'home') {
        $winner = $home->getName();
        $homeWinner = $winnerClass;
    }
    elseif( $andTheWinnerIs === 'visitor' ) {
        $winner = $visitor->getName();
        $visitorWinner = $winnerClass;
    }
    elseif( $andTheWinnerIs === 'tie' ) {
        $homeWinner = $winnerClass;
        $visitorWinner = $winnerClass;
    }
    else {
        $winner = 'no winner yet';
    }

    $home    = $match->getHomeEntrant();
    $hname   = !is_null( $home ) ? $home->getName() : 'tba';
    
    $hseed   = !is_null( $home ) && $home->getSeed() > 0 ? $home->getSeed() : '';
    $hname    = empty($hseed) ? $hname : $hname . "($hseed)";

    $visitor = $match->getVisitorEntrant();
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
    $modifyscores = $umpire->tableModifyScores( $match ); 

    $statusObj = $umpire->matchStatusEx( $match );
    $majorStatus = $statusObj->getMajorStatus();
    $minorStatus = $statusObj->getMinorStatus();
    $status = $statusObj->toString();

    $startDate = $match->getMatchDate_Str();
    $startTime = $match->getMatchTime_Str();
    $this->log->error_log("$loc: {$match->toString()} start date: '{$startDate}'; start time: '{$startTime}'");
    $startedMess = '';
    // if( !empty($startDate) > 0 ) {
    //     $startedMess = __("Started:", TennisEvents::TEXT_DOMAIN);
    // }
    $this->log->error_log( sprintf("%s: %0.6f for Match(%s)", "render-RoundRobin Elapsed time", \commonlib\micro_time_elapsed( $begin ), $title));
?>

<article class="item-player" data-eventid="<?php echo $eventId;?>" 
 data-bracketnum="<?php echo $bracketNum;?>" 
 data-roundnum="<?php echo $roundNum;?>" 
 data-matchnum="<?php echo $matchNum;?>" 
 data-majorstatus="<?php echo $majorStatus;?>" 
 data-minorstatus="<?php echo $minorStatus;?>">
<div class="menu-icon">
 <svg class="dots" viewBox="0 0 16 16" version="1.1" width="16" height="16" aria-hidden="true">
    <path d="M8 9a1.5 1.5 0 100-3 1.5 1.5 0 000 3zM1.5 9a1.5 1.5 0 100-3 1.5 1.5 0 000 3zm13 0a1.5 1.5 0 100-3 1.5 1.5 0 000 3z">
    </path>
 </svg>
 <ul class="matchaction unapproved">
  <li><a class="changehome">Replace Home</a></li>
  <li><a class="changevisitor">Replace Visitor</a><li></ul>
 <ul class="matchaction approved">
  <li><a class="recordscore">Enter Score</a></li>
  <li><a class="defaulthome">Default Home</a></li>
  <li><a class="defaultvisitor">Default Visitor</a></li>
  <li><a class="setmatchstart">Start Date &amp; Time</a></li>
  <li><a class="setcomments">Comment Match</a></li></ul>
</div>
<div class="matchinfo matchtitle"><?php echo $title; ?>&nbsp;<span class="matchinfo matchstatus"><?php echo $status; ?></span></div>
<div class="matchinfo matchstart"><?php echo $startedMess; ?> &nbsp; <?php echo $startDate;?> &nbsp; <?php echo $startTime; ?></div>
<div class="changematchstart">
<input type='date' class='changematchstart' name='matchStartDate' value='<?php echo $startDate;?>'>
<input type='time' class='changematchstart' name='matchStartTime' value='<?php echo $startTime;?>'>
<button class='savematchstart'>Save</button> <button class='cancelmatchstart'>Cancel</button>
</div>
<div class="matchinfo matchcomments"><?php echo $cmts; ?></div>
<div class="homeentrant <?php echo $homeWinner; ?>"><?php echo $hname; ?></div>
<div class="displaymatchscores"><!-- Display Scores Container -->
<?php echo $displayscores; ?></div>
<div class="modifymatchscores tennis-modify-scores"><!-- Modify Scores Container -->
<?php echo $modifyscores; ?></div>
<div class="visitorentrant <?php echo $visitorWinner; ?>"><?php echo $vname; ?></div>
</article>
<?php } //end matches ?>
</section>
<?php } //end rounds ?>
</main>

<div class='bracketDrawButtons'>
<?php 
    if( count( $loadedMatches ) > 1 ) {
    if( !$bracket->isApproved() ) { ?>
        <button class="button" type="button" id="approveDraw">Approve</button>
    <?php } ?>
    <button class="button" type="button" id="removePrelim">Reset</button><br/>
<?php } ?>
</div>
<div id="tennis-event-message"></div>