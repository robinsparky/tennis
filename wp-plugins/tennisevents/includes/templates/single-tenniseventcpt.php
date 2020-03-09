	
<?php
get_header();
//get_template_part('index','banner'); ?>
<!-- Blog Section Right Sidebar -->
<div class="page-builder">
	<div class="container">
		<div class="row">
			<!-- Blog Area -->
			<div class="<?php //appointment_post_layout_class(); ?>" >
			<?php
                if( have_posts() )
                {
				while( have_posts() ) { 
					the_post();
					$postid = get_the_ID();
					get_template_part('content','tennisevent'); 
					?>
				<!--/Blog Author-->
			<?php } 
				comments_template('',true);  
			} ?>	
		</div>
			<!-- /Blog Area -->		
			<!--Sidebar Area-->
			<div class="col-md-4">
			<?php get_sidebar(); ?>	
			</div>
			<!--Sidebar Area-->
		</div>
	</div>
</div>
<!-- /Blog Section Right Sidebar -->
<?php get_footer(); ?>