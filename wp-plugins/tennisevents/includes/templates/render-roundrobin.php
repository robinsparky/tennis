<table id="<?php echo $bracketName;?>" class="bracketrobin" data-eventid="<?php echo $eventId;?>" data-bracketname="<?php echo $bracketName;?>">
<caption><?php echo $tournamentName . ': '; echo $bracketName; ?></caption>
<thead>
<?php 
    foreach( $loadedMatches as $roundnum => $matches ) {
?>
<tr><th>Round <?php echo $roundnum; ?></th></tr></thead>
<tbody>
<?php foreach( $matches as $match ) { 
    $title = $match->title();
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
    $score   = $umpire->tableGetScores( $match );                        
    $status  = $umpire->matchStatus( $match );
    $startDate = $match->getMatchDate_Str();
    $startTime = $match->getMatchTime_Str();
?>

<tr>
<td class="item-player" data-eventid="<?php echo $eventId;?>" 
    data-bracketnum="<?php echo $bracketNum;?>" 
    data-roundnum="<?php echo $roundNum;?>" 
    data-matchnum="<?php echo $matchNum?>" >
<div class="menu-icon">
<div class="bar1"></div>
<div class="bar2"></div>
<div class="bar3"></div>
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
<div class="matchinfo matchtitle"><?php echo $title; ?></div>
<div class="matchinfo matchstatus"><?php echo $status; ?></div>
<div class="matchinfo matchstart"><?php echo $startDate;?> &nbsp; <?php echo $startTime; ?></div>
<input type='date' class='changematchstart' name='matchStartDate' value='<?php echo $startDate;?>'>
<input type='time' class='changematchstart' name='matchStartTime' value='<?php echo $startTime;?>'>
<div class="changematchstart"><button class='savematchstart'>Save</button> <button class='cancelmatchstart'>Cancel</button></div>
<div class="matchinfo matchcomments"><?php echo $cmts; ?></div>
<div class="homeentrant <?php echo $homeWinner; ?>"><?php echo $hname; ?></div>
<div class="matchscore"><?php echo $score;?></div>
<div class="visitorentrant <?php echo $homeWinner; ?>"><?php echo $vname; ?></div>
</td>
</tr>
<?php } //end matches ?>

<?php } //end rounds ?>
</tbody><tfooter></tfooter>
</table>
<div class='bracketDrawButtons'>
<?php 
    if( !$bracket->isApproved() ) { ?>
        <button class="button" type="button" id="approveDraw">Approve</button>
    <?php } ?>
    <button class="button" type="button" id="removePrelim">Reset</button><br/>
</div>
<div id="tennis-event-message"></div>