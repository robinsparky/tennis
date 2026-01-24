<?php
use datalayer\Event;
use datalayer\TennisTeam;
use datalayer\Player;
$title = __('Team Spares',TennisEvents::TEXT_DOMAIN);
?>
<section class="teamSparesSection">
<div style="text-align:center;float:right;margin-right:10px;"><a href="#" onclick="closeSpares(); return false;">Close</a>
<script>
  function closeSpares() {
    let section = document.querySelector('.teamSparesSection');
    section.style.display = 'none';
  }
</script></div>
<h3 style="text-align:center;"><?php echo $title;?></h3>
<?php
$allSpares = Player::find( array( "event_ID" => $event->getID(), "bracket_num" => $bracket->getBracketNumber(),'spares'=>'yes' ) );
usort($allSpares,function( $a, $b ) {return strcmp( $a->getLastName(), $b->getLastName() );});
foreach($allSpares  as $spare ) {
 ?>
<ul id='spareList' class='sparesNameList list-container'>
    <li id='spare<?php echo $spare->getID();?>'><span><?php echo $spare->getName();?></span>&nbsp;<span><?php echo ' (' . $spare->getHomeEmail() . ')';?></span>&nbsp;<span><?php echo $spare->getHomePhone();?></span></li>
   <?php  }   ?>
</ul>
</section>