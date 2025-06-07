<?php
use commonlib\BaseLogger;
use commonlib\GW_Support;
use cpt\TennisMemberCpt;
use datalayer\Person;
use api\ajax\ManagePeople;
use cpt\ClubMembershipCpt;
use datalayer\ExternalMapping;
use datalayer\appexceptions\InvalidPersonException;

get_header();  

$logger = new BaseLogger( true );
$support = GW_Support::getInstance();
$corporateId = TM()->getCorporationId();
$season = TM()->getSeason();
$current_user = wp_get_current_user();
$queryPersonId = array_key_exists('person_id',$_REQUEST) ? (int)$_REQUEST['person_id'] : 0;
$title = !empty($title) ? $title : ($queryPersonId > 0 ? "My Info" : "People");
$caption = "??????????";
$queryPerson = Person::get((int)$queryPersonId);
if(null !== $queryPerson) {
	$mess = __("Archive for {$queryPerson->getFirstName()} {$queryPerson->getLastName()}",TennisClubMembership::TEXT_DOMAIN);
}
else {
    $mess = "All People";
}
$caption = __("Archive for {$mess}",TennisClubMembership::TEXT_DOMAIN);

$heading = "$title&nbsp;Season&nbsp;{$season}";
if(!$current_user->exists()) $heading = "Piss Off!";
$status = !empty($status) ? $status : '*';
$portal = !empty($portal) ? $portal : '*'; 
?>

<!-- Page Content ---->
<div class="page-content">	
<h1><?php echo $heading?></h1>
<?php
global $jsMemberData;        
wp_enqueue_script( 'digital_clock' );  
wp_enqueue_script( 'managepeople' );  
wp_localize_script( 'managepeople', 'tennis_member_obj', $jsMemberData ); 
if( in_array('administrator',$current_user->roles) || current_user_can( TM_Install::MANAGE_REGISTRATIONS_CAP ) ) {
	echo "<button class='tennis-add-person'>" . __("Create New Person",TennisClubMembership::TEXT_DOMAIN) . "</button>";
	include(wp_normalize_path(TM()->getPluginPath() . 'includes\templates\controls\newPersonDialog.php'));

// Sidebar Alt 
get_template_part( 'templates/sidebars/sidebar', 'alt' ); 

// Sidebar Left
get_template_part( 'templates/sidebars/sidebar', 'left' );

?>	
<form method="post" enctype="multipart/form-data">
<div id="club-user-upload">
<label class="button user_upload" for="user_uploads_file">Upload Users</label>
<input style="opacity: 0;" type="file" id="user_uploads_file" name="user_uploads_file" accept=".xml"/>
</div>
</form>
<?php }
if($current_user->exists()) { ?>
<div class="tennis-people-container">
	<!-- tennis registrations -->
	<section class="tennis-people">
		<div id="tabs" class="tennis-people-tabs-container">
<table>  
<caption>
    <?php echo $caption;?>
</caption>
<thead>
	<tr>
	<th scope="col">ID</th><th scope="col">Email</th><th scope="col">Name</th><th scope="col">Gender</th><th  scope='col'>Birthdate</th><th scope='col'>Registrations</th><th  scope='col'>Actions</th>
	</tr>
</thead>
<tbody>
	<?php

		while ( have_posts() ) : 
			the_post();
			global $more;
			$more = 0; 		
			$personPostId = get_the_ID();
			//pre query filter filters by season
			$personId = (int)get_post_meta(get_the_ID(), TennisMemberCpt::USER_PERSON_ID, true);
			$person = Person::get( $personId );
			if( is_null( $person ) ) {
				//TODO: Sydney theme does not display this correctly!?
				$errmess = "Could not find person for post id={$personPostId} and post meta value={$personId}";
				$logger->error_log($errmess);
				echo "<h3 class='tennis-error'>{$errmess}</h3>";
				break;
			}
            else {
				$errmess = "Found person from post with id={$personPostId} and post meta value={$personId}";
                $logger->error_log($errmess);
            }
            if($queryPersonId !== $personId && $queryPersonId > 0) {
                $logger->error_log("query Person different from loop Person");
                continue;
            }
			// $user = GW_Support::getUserByEmail($person->getHomeEmail());
			// $reglink = get_bloginfo('url') . '/' . ClubMembershipCpt::CLUBMEMBERSHIP_SLUG . '/?user_id=' . $user->ID;
			$reglink = GW_Support::getRegLink($person);
		?>	
	<tr id="<?php echo $person->getID()?>" class="">
		<th scope="row"><?php echo $person->getID();?></th>
		<td><a href="mailto:<?php echo $person->getHomeEmail();?>"><?php echo $person->getHomeEmail();?></a></th>
		<td><a href='<?php the_permalink() ?>'><?php echo $person->getName()?></a></td>
		<td><?php echo $person->getGender()?></td>
		<td><?php echo $person->getBirthDate_Str()?></td>
		<td><a href='<?php echo $reglink;?>'><?php echo 'Registrations'?></a></td>
		<td><a href="#">...</a></td>
	</tr>
	<?php endwhile;?>
	</tbody>
	</table>
	<div>
		<?php // Previous/next page navigation.
			the_posts_pagination( array(
			'prev_text' => '<i class="fa fa-angle-double-left">Prev</i>',
			'next_text' => '<i class="fa fa-angle-double-right">Next</i>',
		) );
		?>
	</div>
	<div id="tennis-member-message"></div>
		<?php
			// Reset Post Data 
			wp_reset_postdata();
		?>	
	</section>  <!-- /Registratons -->
		<!-- </div> /Tabs -->
	</div> <!-- /Container -->

<?php // Sidebar Right
 //get_template_part( 'templates/sidebars/sidebar', 'right' );
?>
</div> <!-- /Page content -->

<?php } else { ?> <!-- /User exists -->
	<div class="tennis-people-container">
	<!-- tennis registrations -->
	<section class="tennis-people">
		<div id="tabs" class="tennis-people-tabs-container">
		</div>
	</section>  <!-- /Root events -->
	</div> <!-- /Container -->
	<?php } ?>
<?php 
get_footer(); 
?>