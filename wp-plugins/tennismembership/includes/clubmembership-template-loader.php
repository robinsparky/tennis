<?php
/**
 * Use template files within this plugin.
 */

use commonlib\GW_Debug;
use cpt\TennisMemberCpt;
use cpt\ClubMembershipCpt;
//use commonlib\GW_Support;

/**
 * Locate template.
 *
 * Locate the called template.
 * Search Order:
 * 1. /themes/theme/tennisclubmembership-plugin-templates/$template_name
 * 2. /themes/theme/$template_name
 * 3. /plugins/tennisclubmembership/include/templates/$template_name.
 *
 * @since 1.0.0
 *
 * @param 	string 	$template_name			Template to load.
 * @param 	string 	$template_path      	Path to templates.
 * @param 	string	$default_path			Default path to template files.
 * @return 	string 							Path to the template file.
 */

function clubmembership_template_locate( $template_name, $template_path = '', $default_path = '' ) {
	$loc = __FUNCTION__;
	error_log("$loc: template_name={$template_name}");
	//error_log(GW_Debug::get_debug_trace_Str());

	// Set variable to search in clubmembership-plugin-templates folder of the THEME.
	if ( ! $template_path ) :
		$template_path = 'tennisclubmembership-plugin-templates/';
	endif;

	// Set default plugin templates path.
	if ( ! $default_path ) :
		$default_path = plugin_dir_path( __FILE__ ) . 'templates/'; // Path to the template this plugins folder
	endif;
	error_log("$loc: template_name='{$template_name}', template_path='{$template_path}', default_path='{$default_path}'");

	// Search template file in theme folder.
	$template = locate_template( array( $template_path . $template_name, $template_name	) );

	// Get plugins template file.
	if ( ! $template ) {
		$template = $default_path . $template_name;
	}
	error_log("$loc: apply filter to '{$template}'");

	return $template;
	//return apply_filters( 'clubmembership_template_locate', $template, $template_name, $template_path, $default_path );
}

/**
 * NOT USED
 * Get template.
 *
 * Search for the template and include the file.
 *
 * @since 1.0.0
 *
 * @see clubmembership_locate_template()
 *
 * @param string 	$template_name			Template to load.
 * @param array 	$args					Args passed for the template file.
 * @param string 	$string $template_path	Path to templates.
 * @param string	$default_path			Default path to template files.
 */
function clubmembership_get_template( $template_name, $args = null, $template_path = '', $default_path = '' ) {
	$loc = __FUNCTION__;

	if ( isset( $args ) && is_array( $args ) ) :
		extract( $args );
	endif;
	error_log("$loc: template_name='{$template_name}', template_path='{$template_path}', default_path='{$default_path}'");

	$template_file = clubmembership_template_locate( $template_name, $template_path, $default_path );
	error_log("$loc: template_file='{$template_file}'");

	if ( ! file_exists( $template_file ) ) :
		_doing_it_wrong( __FUNCTION__, sprintf( '<code>%s</code> does not exist.', $template_file ), '1.0.0' );
		return;
	endif;

	load_template( $template_file, true, $args );
}

/**
 * NOT USED
 * Get template part.
 *
 * Search for the template and include the file.
 *
 * @since 1.0.0
 *
 * @see clubmembership_locate_template()
 *
 * @param string 	$slug The slug name for the generic template.
 * @param string 	$name The name of the specialised template. Defaults to null
 * @param array     $args array of additional args
 */
function clubmembership_get_template_part( $slug, $name = null, $args = null ) {
	$loc = __FUNCTION__;
	error_log( "$loc: slug='{$slug}', name='{$name}'" );

	$template_name = "{$slug}.php";
    $name  = (string) $name;
    if ( '' !== $name ) {
        $template_name = "{$name}-{$slug}.php";
	}
 
	clubmembership_get_template( $template_name, $args );
}

/**
 * Template loader.
 *
 * The template loader will check if WP is loading a template
 * for a specific Post Type and will try to load the template
 * from our 'templates' directory.
 *
 * @since 1.0.0
 *
 * @param	string	$template	Template file that is being loaded.
 * @return	string				Template file that should be loaded.
 */
function clubmembership_template_include( $template ) {
	$loc =  __FUNCTION__;	
	error_log( "$loc: template='$template'" );
	//error_log(GW_Debug::get_debug_trace_Str());
	$forUser = array_key_exists( 'userId',$_REQUEST ) ? $_REQUEST['userId'] : '';

	$myArchiveTemplates = array(ClubMembershipCpt::CUSTOM_POST_TYPE => 'archive-' . ClubMembershipCpt::CUSTOM_POST_TYPE . '.php'
						       ,TennisMemberCpt::CUSTOM_POST_TYPE   => 'archive-' . TennisMemberCpt::CUSTOM_POST_TYPE . '.php');
	$mySingleTemplates = array(ClubMembershipCpt::CUSTOM_POST_TYPE  => 'single-'  . ClubMembershipCpt::CUSTOM_POST_TYPE . '.php'
						      ,TennisMemberCpt::CUSTOM_POST_TYPE    => 'single-'  . TennisMemberCpt::CUSTOM_POST_TYPE . '.php');

	$file = '';

	//Add custom post types here
	$my_post_types = array( ClubMembershipCpt::CUSTOM_POST_TYPE, TennisMemberCpt::CUSTOM_POST_TYPE );
	  
	if( !is_singular( $my_post_types) && !is_post_type_archive( $my_post_types ) ) {
		error_log("$loc: you're not my type!!");
	  return $template;
	}
	
	$provided_template_array = explode( '/', $template );
	
	$file = end( $provided_template_array );
	if( is_post_type_archive( ClubMembershipCpt::CUSTOM_POST_TYPE ) ) {
		$file = $myArchiveTemplates[ClubMembershipCpt::CUSTOM_POST_TYPE];
		error_log("$loc: archive using $file");
	}
	elseif( is_post_type_archive( TennisMemberCpt::CUSTOM_POST_TYPE ) ) {
		$file = $myArchiveTemplates[TennisMemberCpt::CUSTOM_POST_TYPE];
		error_log("$loc: archive using $file");
	}
	elseif( is_singular( ClubMembershipCpt::CUSTOM_POST_TYPE  ) ) {
		$file = $mySingleTemplates[ClubMembershipCpt::CUSTOM_POST_TYPE];
		error_log("$loc: single using $file");
	}
	elseif( is_singular( TennisMemberCpt::CUSTOM_POST_TYPE  ) ) {
		$file = $mySingleTemplates[TennisMemberCpt::CUSTOM_POST_TYPE];
		error_log("$loc: single using $file");
	}
	else {
	//Not one of ours
		return $template;
	}

	$template = clubmembership_template_locate($file);

	if ( ! file_exists( $template ) ) :
		error_log("$loc: file not exists '$template'");
		_doing_it_wrong( __FUNCTION__, sprintf( '<code>%s</code> does not exist.', $template ), '1.0.0' );
		return $template;
	endif;

	error_log("$loc: returning '$template'");
	return $template;
}

add_filter( 'template_include', 'clubmembership_template_include' );

