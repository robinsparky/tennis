	
<?php
get_header();
?>
<!-- Page Content 
<div class="page-content">
	---->	
<?php

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
	</div>
</div> <!-- /Container -->

<?php // Sidebar Right
//get_template_part( 'templates/sidebars/sidebar', 'right' );
?>
<!-- </div> /Page content -->
<?php get_footer(); ?>