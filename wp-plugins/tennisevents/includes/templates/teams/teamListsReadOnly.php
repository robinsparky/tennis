<?php
use datalayer\Event;
use datalayer\TennisTeam;
use datalayer\Player;
$allTeams = TennisTeam::find( array( "event_ID" => $event->getID(), "bracket_num" => $bracket->getBracketNumber() ) );
foreach($allTeams  as $team ) {
    foreach( $team->getSquads() as $squad ) {
    $members = $team->getMembers($team->getSquad());
    $memCount = count($members);
    usort($members,function( $a, $b ) {return strcmp( $a->getLastName(), $b->getLastName() );});
    $teamId = $team->getTeamNum() . $squad->getName(); ?>
    <ul id='team<?php echo $teamId; ?>' class='teamListSection teamNameList list-container' data-teamnum='<?php echo $team->getTeamNum();?>' data-squad='<?php echo $squad->getID();?>'>
    <div style="text-align:center;float:right;margin-right:10px;"><a href="#" onclick="closeTeamList(); return false;">Close</a>
    <script>
    function closeTeamList() {
        console.log('close team list');
        let sections = document.querySelectorAll('.teamListSection');
        sections.forEach(section => {
            section.style.display = 'none';
        });
    }
    </script></div>
    <div class='team-name-count'><span class='team-name'><?php echo $team->getName() . $team->getSquad()?>&nbsp;</span><span class='team-count'>(<?php echo $memCount;?>)</span></div>
<?php
    foreach($team->getMembers($team->getSquad()) as $member) { ?>
        <li id='player<?php echo $member->getID();?>'><span><?php echo $member->getName();?></span>&nbsp;<a href="mailto:<?php echo $member->getHomeEmail();?>">Email</a>&nbsp;<span><?php echo $member->getHomePhone();?></span></li>
   <?php } ?>
</ul>
<?php }} ?>
