	
<?php
/*
 * Template Name: Tennis Event Template
 * description: Template to display Tennis Event custom post types 
 */
$season = esc_attr( get_option('gw_tennis_event_season', date('Y') ) ); 
$startFuncTime = microtime( true );
get_header();
?>

<div class="main-tennis-content">
<h1> Season <?php echo $season; ?> </h1>

	<?php
		// Sidebar Alt 
		//get_template_part( 'templates/sidebars/sidebar', 'alt' );

		// Sidebar Left
		//get_template_part( 'templates/sidebars/sidebar', 'left' );
	?>

	<!-- Main Container -->
	<div class="main-tennis-container">
		<!-- Tennis Events Draw -->
		<article class="tennis-events-draw">
			<!-- Blog Area -->
			<?php
                if( have_posts() )
                {
				while( have_posts() ) { 
					the_post();
					$postid = get_the_ID();
					tennis_get_template_part('draw','tenniseventcpt'); 
					?>
				<!--/Blog Author-->
			<?php } 
				comments_template('',true);  
			} ?>	
		</article>
		<!-- /Tennis Events Draw -->
	</div> <!-- /main-container -->
</div> <!-- /main-content -->

<?php // Sidebar Right
	//get_template_part( 'templates/sidebars/sidebar', 'right' );
?>
<?php 
get_footer(); 
echo sprintf("Elapsed time: %0.6f", commonlib\micro_time_elapsed( $startFuncTime ));
?>