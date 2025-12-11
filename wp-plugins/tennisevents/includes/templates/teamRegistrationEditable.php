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
$assignedPlayers = Player::find(['event_ID'=>$event->getID(),'bracket_num'=>$bracket->getBracketNumber(),'assigned'=>true]);
$allPlayers = Player::find( array( "event_ID" => $event->getID(), "bracket_num" => $bracket->getBracketNumber() ) );
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
    <li id='player<?php echo $player->getID();?>' draggable='true'><?php echo $player->getName();?></li>
<?php } ?>
</ul>
<?php
$allTeams = TennisTeam::find( array( "event_ID" => $event->getID(), "bracket_num" => $bracket->getBracketNumber() ) );
foreach($allTeams  as $team ) {
        $members = $team->getMembers($team->getSquad());
        $memCount = count($members);
        usort($members,function( $a, $b ) {return strcmp( $a->getLastName(), $b->getLastName() );});
    $teamId = $team->getTeamNum() . $team->getSquad(); ?>
    <ul id='team<?php echo $teamId; ?>' class='teamNameList list-container' data-teamnum='<?php echo $team->getTeamNum();?>' data-squad='<?php echo $team->getSquad();?>'>
    <div class='team-name-count'><span class='team-name'><?php echo $team->getName() . $team->getSquad()?>&nbsp;</span><span class='team-count'>(<?php echo $memCount;?>)</span></div>
    <?php 
        foreach($members as $member) { ?>
        <li id='player<?php echo $member->getID();?>' draggable='true' data-playerid='<?php echo $member->getID(); ?>'><?php echo $member->getName();?></li>
    <?php } ?>
    </ul>
<?php } ?>
<button class="button closeTeamRegistration" type="button" id="cancelTeams">Teams</button> 
<button class="button saveTeamRegistration" type="button" disabled id="saveTeams">Save</button>
<button class="button resetTeamRegistration" type="button" id="resetTeams">Reset</button>
</div>
</section>