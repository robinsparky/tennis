	
<?php
get_header();
?>
<!-- Page Content 
<div class="page-content">
	---->	
<?php

$startFuncTime = microtime( true );
// Sidebar Alt 
//get_template_part( 'templates/sidebars/sidebar', 'alt' ); 

// Sidebar Left
//get_template_part( 'templates/sidebars/sidebar', 'left' );

?>
<div class="container">
		<section class="tennis-events-draw">
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
		</section>
</div> <!-- /Container -->

<?php // Sidebar Right
//get_template_part( 'templates/sidebars/sidebar', 'right' );
?>
<!-- </div> /Page content -->
<?php 		
echo sprintf("Elapsed time: %0.6f", micro_time_elapsed( $startFuncTime ));

get_footer(); ?>