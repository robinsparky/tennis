<?php
use api\ajax\ManageRegistrations;
use commonlib\GW_Support;
use cpt\ClubMembershipCpt;
use datalayer\MemberRegistration;
use datalayer\Person;

get_header();

//print_r($wp_query->query_vars);
$postId = get_the_ID();
echo 'postId=' . $postId;
$regId = get_post_meta($postId,ClubMembershipCpt::REGISTRATION_ID,true);
echo 'regId=' . $regId;
$registration = MemberRegistration::get($regId);
$regType = $registration->getMembershipType()->getName();
$personId = $registration->getPersonId();
echo "Personid=$personId";
$registrant = Person::get($personId);
$homeLink = GW_Support::getHomeLink($person);
?>
<section id="<?php $registration->getID()?>" class="registration" data-userid="<?php echo $registrant->getID()?>">
	<div><span>Id:</span><?php echo $registration->getID();?></div>
	<div><a href="/wp-admin/users.php"><?php echo $registrant->getName();?></a></div>
	<div><?php echo $regType?></div>
	<div><?php echo $registrant->getHomeEmail()?></div>
	<div><?php echo $registration->getStartDate_Str()?></div>
	<div><?php echo $registration->getEndDate_Str()?></div>
</section>
<div><?php get_footer()?></div>
<?php

