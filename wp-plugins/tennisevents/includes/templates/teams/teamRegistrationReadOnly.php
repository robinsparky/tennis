<?php
use datalayer\Event;
use datalayer\TennisTeam;
use datalayer\Player;
$title = __('Team Members',TennisEvents::TEXT_DOMAIN);
?>
<section class="teamRegistrationSection">
    <h3 style="text-align:center;"><?php echo $title;?></h3>
<div class="teamRegistrationHeader">
<?php
$allTeams = TennisTeam::find( array( "event_ID" => $event->getID(), "bracket_num" => $bracket->getBracketNumber() ) );
foreach($allTeams  as $team ) {
    foreach($team->getSquads() as $squad ) {
        $members = $squad->getMembers();
        $memCount = count($members);
        usort($members,function( $a, $b ) {return strcmp( $a->getLastName(), $b->getLastName() );});
    $teamId = $team->getTeamNum() . $squad->getName(); ?>
    <ul id='team<?php echo $teamId; ?>' class='teamNameList list-container' data-teamnum='<?php echo $team->getTeamNum();?>' data-squad='<?php echo $squad->getName();?>'>
    <div class='team-name-count'><span class='team-name'><?php echo $team->getName() . $squad->getName()?>&nbsp;</span><span class='team-count'>(<?php echo $memCount;?>)</span></div>
<?php
    foreach($members as $player) { ?>
        <li id='player<?php echo $player->getID();?>'><?php echo $player->getName();?></li>
   <?php } ?>
</ul>
<?php }} ?>
<button class="button closeTeamRegistration" type="button" id="cancelTeams">Teams</button>
</div>
</section>