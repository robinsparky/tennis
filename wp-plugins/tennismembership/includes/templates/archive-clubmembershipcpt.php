<?php
use datalayer\MembershipType;
use commonlib\BaseLogger;
use commonlib\GW_Support;
use datalayer\MemberRegistration;
use datalayer\ExternalMapping;
use datalayer\appexceptions\InvalidRegistrationException;

get_header();  

$logger = new BaseLogger( true );
$support = GW_Support::getInstance();
$allMemTypes = MembershipType::allTypes();

$title = !empty($title) ? $title : "Registrations";
$status = !empty($status) ? $status : '*';
$regType = !empty($regType) ? $regType : '*';
$portal = !empty($portal) ? $portal : '*';
$corporateId = TM()->getCorporationId();
$season = TM()->getSeason();
?>

<!-- Page Content ---->
<div class="page-content">	
<h1><?php echo $title ?>&nbsp;Season&nbsp;<?php echo $season; ?></h1>
<?php
if( current_user_can( TM_Install::MANAGE_REGISTRATIONS_CAP ) ) {
	echo "<button class='tennis-add-registration root'>" . __("Create New Registration",TennisClubMembership::TEXT_DOMAIN) . "</button>";
	include(wp_normalize_path(TM()->getPluginPath() . 'includes\templates\controls\newRegistrationDialog.php'));
}

// Sidebar Alt 
//get_template_part( 'templates/sidebars/sidebar', 'alt' ); 

// Sidebar Left
//get_template_part( 'templates/sidebars/sidebar', 'left' );

?>
	<div class="tennis-registrations-container">
  		<!-- tennis registrations -->
		<section class="tennis-registrations">
			<div id="tabs" class="tennis-registration-tabs-container">
			<?php	
				wp_enqueue_script( 'manage_registrations' ); 
				wp_enqueue_script( 'digital_clock' );  
				wp_enqueue_script( 'manage_member' );  
				global $jsMemberData;        
				wp_localize_script( 'manage_member', 'tennis_membership_obj', $jsMemberData );  
				//wp_add_inline_script('manage_member', 'tennis_membership_obj');      

				//Not using MemberRegistration posts
				while ( have_posts() ) : 
					the_post();
					global $more;
					$more = 0; 		

					$regCPTId = get_the_ID();	
					$iids = ExternalMapping::fetchInternalIds(MemberRegistration::$tablename,$regCPTId );
					$regId = 0;
					if( count( $iids ) > 1) {
						$mess = __("Too many maps for '{$regCPTId}'");
						$logger->error_log($mess);
						//throw new InvalidRegistrationException($mess);
					}
					elseif( count( $iids ) < 1 ) {
						$mess = __("No map for '{$regCPTId}'");
						$logger->error_log($mess);
						//throw new InvalidRegistrationException($mess);
					}
					else {
						$regId = $iids[0];
					}
					$reg = MemberRegistration::get( $regId );

					if( is_null( $reg ) ) {
						//TODO: Sydney theme does not display this correctly!?
						$errmess = "Could not find default registration with external id={$regCPTId}";
						error_log($errmess);
						echo "<div class='tennis-error'>{$errmess}</div>";					
						get_footer(); 	
						wp_die( $errmess );
					}
					if( $season != $reg->getSeasonId() ) continue;
					//The content is produced here
					// $path = wp_normalize_path( TM()->getPluginPath() . 'includes\templates\controls\readonly-registrations.php');
					// if( current_user_can( TM_Install::MANAGE_EVENTS_CAP ) && !$event->isClosed() ) {
					// 	$path = wp_normalize_path(TM()->getPluginPath() . 'includes\templates\controls\editor-registrations.php');
					// }
					// require($path);
				?>	
				<div>
				<?php endwhile;
				// Previous/next page navigation.
				the_posts_pagination( array(
					'prev_text' => '<div class="fa fa-angle-double-left"></div>',
					'next_text' => '<div class="fa fa-angle-double-right"></div>',
				) );
				?>
				<div id="tennis-member-message"></div>
			<?php
				// Reset Post Data 
				wp_reset_postdata();
			?>	
		</section>  <!-- /Root events -->
		
		</div> <!-- /Tabs -->
	</div> <!-- /Container -->

<?php // Sidebar Right
 //get_template_part( 'templates/sidebars/sidebar', 'right' );
?>
</div> <!-- /Page content -->

<?php 
get_footer(); 
?>