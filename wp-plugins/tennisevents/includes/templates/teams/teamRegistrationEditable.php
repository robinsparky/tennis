<?php
use datalayer\Event;
use datalayer\TennisTeam;
use datalayer\Player;
$title = __('Assign Team Members',TennisEvents::TEXT_DOMAIN);
?>
<section class="teamRegistrationSection">
    <h3 style="text-align:center;"><?php echo $title;?></h3>
<div class="teamRegistrationHeader editable">
<?php 
$assignedPlayers = Player::find(['event_ID'=>$event->getID(),'bracket_num'=>$bracket->getBracketNumber(),'isAssignedToTeam'=>true]);
$allPlayers =      Player::find(["event_ID" => $event->getID(), "bracket_num" => $bracket->getBracketNumber(), 'isSpare' => false]);
$availablePlayers = array_udiff($allPlayers,$assignedPlayers,function($p1, $p2){
        if ($p1->getID() === $p2->getID()) {
            return 0; // Players are considered equal
        }
        return ($p1->getID() < $p2->getID()) ? -1 : 1; // Players are different
});
usort( $availablePlayers, function( $a, $b ) {
    return strcmp( $a->getLastName(), $b->getLastName() );
} );
?>
<ul id="sourceList" class="list-container registeredPlayersList">
<?php
foreach( $availablePlayers as $player ) { ?>
    <li id='player<?php echo $player->getID();?>' draggable='true' data-playerid='<?php echo $player->getID(); ?>'><?php echo $player->getName();?></li>
<?php } ?>
</ul>
<?php
$allTeams = TennisTeam::find( array( "event_ID" => $event->getID(), "bracket_num" => $bracket->getBracketNumber() ) );
foreach($allTeams  as $team ) {
    foreach($team->getSquads() as $squad ) {
        error_log("Rendering team {$team->getName()} squad {$squad->getName()}");
        $members = $squad->getMembers();
        $memCount = count($members);
        usort($members,function( $a, $b ) {return strcmp( $a->getLastName(), $b->getLastName() );});
        $teamId = $team->getTeamNum() . $squad->getName(); ?>
    <ul id='team<?php echo $teamId; ?>' class='teamNameList list-container' data-teamnum='<?php echo $team->getTeamNum();?>' data-squad='<?php echo $squad->getID();?>'>
    <div class='team-name-count'><span class='team-name'><?php echo $team->getName() . $squad->getName()?>&nbsp;</span><span class='team-count'>(<?php echo $memCount;?>)</span></div>
    <?php 
        foreach($members as $player) { ?>
        <li id='player<?php echo $player->getID();?>' draggable='true' data-playerid='<?php echo $player->getID(); ?>'><?php echo $player->getName();?></li>
    <?php } ?>
    </ul>
<?php }} ?>
<button class="button closeTeamRegistration" type="button" id="cancelTeams">Close</button> 
<button class="button saveTeamRegistration" type="button" id="saveTeams">Save</button>
<button class="button resetTeamRegistration" type="button" id="resetTeams">Reset</button>
</div>
</section>