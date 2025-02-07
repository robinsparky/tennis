<?php
/**
 * Use template files within this plugin.
 */

use cpt\TennisEventCpt;

/**
 * Locate template.
 *
 * Locate the called template.
 * Search Order:
 * 1. /themes/theme/tennisevents-plugin-templates/$template_name
 * 2. /themes/theme/$template_name
 * 3. /plugins/tennisevents/include/templates/$template_name.
 *
 * @since 1.0.0
 *
 * @param 	string 	$template_name			Template to load.
 * @param 	string 	$template_path      	Path to templates.
 * @param 	string	$default_path			Default path to template files.
 * @return 	string 							Path to the template file.
 */

function tennis_template_locate( $template_name, $template_path = '', $default_path = '' ) {
	$loc = __FUNCTION__;
	
	// Set variable to search in tennisevents-plugin-templates folder of THEME.
	if ( ! $template_path ) :
		$template_path = 'tennisevent-plugin-templates/';
	endif;

	// Set default plugin templates path.
	if ( ! $default_path ) :
		$default_path = plugin_dir_path( __FILE__ ) . 'templates/'; // Path to the template folder
	endif;
	error_log("$loc: template_name='{$template_name}', template_path='{$template_path}', default_path='{$default_path}'");

	// Search template file in theme folder.
	$template = locate_template( array( $template_path . $template_name, $template_name	) );

	// Get plugins template file.
	if ( ! $template ) {
		$template = $default_path . $template_name;
	}

	return $template;
	//return apply_filters( 'tennis_template_locate', $template, $template_name, $template_path, $default_path );

}

/**
 * NOT USED
 * Get template.
 *
 * Search for the template and include the file.
 *
 * @since 1.0.0
 *
 * @see tennis_locate_template()
 *
 * @param string 	$template_name			Template to load.
 * @param array 	$args					Args passed for the template file.
 * @param string 	$string $template_path	Path to templates.
 * @param string	$default_path			Default path to template files.
 */
function tennis_get_template( $template_name, $args = null, $template_path = '', $default_path = '' ) {
	$loc = __FUNCTION__;

	if ( isset( $args ) && is_array( $args ) ) :
		extract( $args );
	endif;
	error_log("$loc: template_name='{$template_name}', template_path='{$template_path}', default_path='{$default_path}'");

	$template_file = tennis_template_locate( $template_name, $template_path, $default_path );
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
 * @see tennis_locate_template()
 *
 * @param string 	$slug The slug name for the generic template.
 * @param string 	$name The name of the specialised template. Defaults to null
 * @param array     $args array of additional args
 */
function tennis_get_template_part( $slug, $name = null, $args = null ) {
	$loc = __FUNCTION__;
	error_log( "$loc: slug='{$slug}', name='{$name}'" );

	$template_name = "{$slug}.php";
    $name  = (string) $name;
    if ( '' !== $name ) {
        $template_name = "{$slug}-{$name}.php";
	}
 
	tennis_get_template( $template_name, $args );
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
function tennis_template_loader( $template ) {
	$loc =  __FUNCTION__;	
	error_log( "$loc: template='$template'" );

	$file = '';

	$my_post_types = array( TennisEventCpt::CUSTOM_POST_TYPE );

	if( ! is_singular( $my_post_types) && ! is_post_type_archive( $my_post_types ) ) {
		error_log("$loc: you're not my type!");
	  return $template;
	}

	$provided_template_array = explode( '/', $template );

	$file = end( $provided_template_array );
	if( is_post_type_archive( $my_post_types ) ) {
		$file = 'archive-tenniseventcpt.php';
	}
	elseif( is_singular( $my_post_types ) ) {
		$file = 'single-tenniseventcpt.php';
	}
	error_log("$loc: looking for file='$file'");

	$template = tennis_template_locate( $file );

	if ( ! file_exists( $template ) ) :
		error_log("$loc: file not exists '$template'");
		_doing_it_wrong( __FUNCTION__, sprintf( '<code>%s</code> does not exist.', $template ), '1.0.0' );
		return;
	endif;

	return $template;

}
add_filter( 'template_include', 'tennis_template_loader' );
