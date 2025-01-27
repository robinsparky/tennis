<?php
/**
 * This file provides example of how to setup query vars
 * and include them into the WP_Query querying
 * NOT USED so far.:-)
 */

/**
 * Register custom query vars
 *
 * @param array $vars The array of available query variables
 * 
 * @link https://codex.wordpress.org/Plugin_API/Filter_Reference/query_vars
 */
function tennis_register_query_vars( $vars ) {
	$vars[] = 'manage';
	return $vars;
}
add_filter( 'query_vars', 'tennis_register_query_vars' );

/**
 * Add rewrite tags and rules
 *
 * @link https://codex.wordpress.org/Rewrite_API/add_rewrite_tag
 * @link https://codex.wordpress.org/Rewrite_API/add_rewrite_rule
 */
/**
 * Add rewrite tags and rules
 */
function tennis_rewrite_tag_rule() {
	add_rewrite_tag( '%manage%', '([^&]+)' );
	add_rewrite_rule( '^manage/([^/]*)/?', 'single_tenniseventcpt.php?manage=$matches[1]','top' );
	
	// remove comments and customize for custom post types
	// add_rewrite_rule( '^event/city/([^/]*)/?', 'index.php?post_type=event&city=$matches[1]','top' );
}
add_action('init', 'tennis_rewrite_tag_rule', 10, 0);

/**
 * Build a custom query
 *
 * @param $query obj The WP_Query instance (passed by reference)
 *
 * @link https://codex.wordpress.org/Class_Reference/WP_Query
 * @link https://codex.wordpress.org/Class_Reference/WP_Meta_Query
 * @link https://codex.wordpress.org/Plugin_API/Action_Reference/pre_get_posts
 */
function tennis_pre_get_posts( $query ) {
	// check if the user is requesting an admin page 
	// or current query is not the main query
	if ( is_admin() || ! $query->is_main_query() ){
		return;
	}

	$my_post_types = array( 'tenniseventcpt' );
	  
	if( ! is_singular( $my_post_types) && ! is_post_type_archive( $my_post_types ) ) {
	  return $query;
	}

	$manage = get_query_var( 'manage' );

	// add meta_query elements
	if( !empty( $manage ) ){
		$query->set( 'meta_key', 'manage' );
		$query->set( 'meta_value', $manage );
		$query->set( 'meta_compare', '=' );
	}

}
add_action( 'pre_get_posts', 'tennis_pre_get_posts', 1 );