<?php

use cpt\ClubMembershipCpt;
use cpt\TennisMemberCpt;
use datalayer\Person;
use commonlib\GW_Support;

get_header();  

$postId = get_the_ID();
$post = get_post($postId);
$personId = get_post_meta($postId,TennisMemberCpt::USER_PERSON_ID,true);
$person = Person::get($personId);
$reglink = GW_Support::getRegLink($person);

?>
<section id="<?php echo $person->getID()?>" class="registrant" data-userid="<?php echo $person->getID()?>">
<ul>
	<li><span>Id:</span><?php echo $person->getID(); echo " - $postId";?></li>
	<li><?php echo $person->getName();?></li>
	<li>Gender: <?php echo $person->getGender()?></li>
	<li>Email: <a href='<?php echo $person->getHomeEmail();?>'><?php echo $person->getHomeEmail()?></a></li>
	<li>Birthdate: <?php echo $person->getBirthDate_Str()?></li>
	<li><a href='<?php echo $reglink;?>'>Registrations</a></li>
</ul>
</section>
<div><?php get_footer()?></div>