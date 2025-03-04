<?php
use datalayer\MembershipType;
use commonlib\BaseLogger;
use commonlib\GW_Support;
use cpt\ClubMembershipCpt;
use datalayer\MemberRegistration;
use datalayer\Person;
use datalayer\ExternalMapping;
use datalayer\appexceptions\InvalidRegistrationException;

get_header();  

$logger = new BaseLogger( true );
$support = GW_Support::getInstance();
$allMemTypes = MembershipType::allTypes();

$corporateId = TM()->getCorporationId();
$season = TM()->getSeason();
$current_user = wp_get_current_user();

$queryUserId = array_key_exists('user_id',$_REQUEST) ? (int)$_REQUEST['user_id'] : 0;
$title = !empty($title) ? $title : ($queryUserId > 0 ? "My Registration" : "Registrations");
$targetUser = get_user_by('ID',$queryUserId);
$caption = "Registrations Archive";

if($targetUser instanceof WP_User) {
	$caption = __("Archive for {$targetUser->first_name} {$targetUser->last_name}",TennisClubMembership::TEXT_DOMAIN);
}
else {
	$queryUserId = 0;
}

$heading = "$title&nbsp;Season&nbsp;{$season}";
if(!$current_user->exists()) $heading = "Fuck Off!";
$status = !empty($status) ? $status : '*';
$regType = !empty($regType) ? $regType : '*';
$portal = !empty($portal) ? $portal : '*';

?>

<!-- Page Content ---->
<div class="page-content">	
<h1><?php echo $heading?></h1>
<?php
global $jsRegistrationData;     
wp_enqueue_script( 'digital_clock' );  
wp_enqueue_script( 'manage_registrations' );  
wp_localize_script( 'manage_registrations', 'tennis_membership_obj', $jsRegistrationData );  

if( $current_user->exists() &&  current_user_can( TM_Install::MANAGE_REGISTRATIONS_CAP ) ) {
	echo "<button class='tennis-add-registration'>" . __("Create New Registration",TennisClubMembership::TEXT_DOMAIN) . "</button>";
	include(wp_normalize_path(TM()->getPluginPath() . 'includes\templates\controls\newRegistrationDialog.php'));

// Sidebar Alt 
//get_template_part( 'templates/sidebars/sidebar', 'alt' ); 

// Sidebar Left
//get_template_part( 'templates/sidebars/sidebar', 'left' );

?>	
<form method="post" enctype="multipart/form-data">
<div id="club-registration-upload">
<label class="button registration_upload" for="registration_uploads_file">Upload Registrations</label>
<input style="opacity: 0;" type="file" id="registration_uploads_file" name="registration_uploads_file" accept=".xml"/>
</div>
</form>	
<?php } ?>
<?php if($current_user->exists()) { ?>
<div class="tennis-registrations-container">
	<!-- tennis registrations -->
	<section class="tennis-registrations">
		<div id="tabs" class="tennis-registration-tabs-container">
<table>  
<caption>
    <?php echo $caption;?>
</caption>
<thead>
	<tr>
	<th scope="col">ID</th><th scope="col">Email</th><th scope='col'>Name</th><th scope="col">Membership Type</th><th scope="col">Start</th><th scope="col">Expiry</th><th>Actions</th>
	</tr>
</thead>
<tbody>
	<?php	      
		while ( have_posts() ) {
			the_post();
		
			$regPostId = get_the_ID();
			//pre query filter filters by season
			$regId = get_post_meta(get_the_ID(), ClubMembershipCpt::REGISTRATION_ID, true);
			$reg = MemberRegistration::get( $regId );
			if( is_null( $reg ) ) {
				//TODO: Sydney theme does not display this correctly!?
				$errmess = "Could not find registration for post id={$regPostId} and meta value={$regId}";
				$logger->error_log($errmess);
				echo "<h3 class='tennis-error'>{$errmess}</h3>";
				break;
			}
			$id = $reg->getPersonId();
			$registrant = Person::get($id);
			//$postUser = get_users(['user_email'=>$registrant->getHomeEmail()])[0];//This does not work?
			$postUser = GW_Support::getUserByEmail($registrant->getHomeEmail());
			// $logger->error_log("Comparing {$postUser->ID} with {$queryUserId}");
			// GW_Support::log($postUser);
			if($postUser->ID != $queryUserId &&  0 !== $queryUserId) continue;
			$homelink = GW_Support::getHomeLink($registrant);

		?>	
	<tr id="<?php $reg->getID()?>" class="">
		<th scope="row"><?php echo $reg->getID(); ?></th>
		<td><a href='mailto:<?php echo $registrant->getHomeEmail() ?>'><?php echo $registrant->getHomeEmail();?></a></th>
		<td><a href='<?php echo $homelink; ?>'><?php echo $registrant->getName();?></a></th>
		<td><?php echo $reg->getMembershipType()->getName()?></td>
		<td><?php echo $reg->getStartDate_Str()?></td>
		<td><?php echo $reg->getEndDate_Str()?></td>
		<td><a href="#">...</a></td>
	</tr>
	<?php } ?>
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
	<div class="tennis-registrations-container">
	<!-- tennis registrations -->
	<section class="tennis-registrations">
		<div id="tabs" class="tennis-registration-tabs-container">
		</div>
	</section>  <!-- /Root events -->
	</div> <!-- /Container -->
	<?php } ?>
<?php 
get_footer(); 
?>