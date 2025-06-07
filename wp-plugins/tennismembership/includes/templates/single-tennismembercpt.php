<?php
get_header();  
$postId = get_the_ID();
?>
<div class="wrapper">
  <?php echo do_shortcode("[render_member_sponsor post_id={$postId}]");?>
  <?php echo do_shortcode("[render_member_registration post_id={$postId}]");?>
  <?php echo do_shortcode("[render_member_sponsored post_id={$postId}]");?>
  <?php echo do_shortcode("[render_member_menu post_id={$postId}]");?>
  <?php echo do_shortcode("[render_reg_history post_id={$postId}]");?>
  <?php echo do_shortcode("[render_member_address post_id={$postId}]");?>
  <?php echo do_shortcode("[render_member_emergency post_id={$postId}]");?>
</div> <!-- end wrapper -->
<div><?php get_footer()?></div>