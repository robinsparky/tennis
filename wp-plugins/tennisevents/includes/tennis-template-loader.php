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

function tennis_locate_template( $template_name, $template_path = '', $default_path = '' ) {
	$loc = __FUNCTION__;
	
	// Set variable to search in tennisevents-plugin-templates folder of theme.
	if ( ! $template_path ) :
		$template_path = 'tennisevents-plugin-templates/';
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

	return apply_filters( 'tennis_locate_template', $template, $template_name, $template_path, $default_path );

}

/**
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
function tennis_get_template( $template_name, $args = array(), $template_path = '', $default_path = '' ) {
	$loc = __FUNCTION__;

	if ( isset( $args ) && is_array( $args ) ) :
		extract( $args );
	endif;
	error_log("$loc: template_name='{$template_name}', template_path='{$template_path}', default_path='{$default_path}'");

	$template_file = tennis_locate_template( $template_name, $template_path, $default_path );
	error_log("$loc: template_file='{$template_file}'");

	if ( ! file_exists( $template_file ) ) :
		_doing_it_wrong( __FUNCTION__, sprintf( '<code>%s</code> does not exist.', $template_file ), '1.0.0' );
		return;
	endif;

	load_template( $template_file, true );
	//include $template_file;

}

/**
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
 */
function tennis_get_template_part( $slug, $name = null ) {
	$loc = __FUNCTION__;
	error_log( "$loc: slug='{$slug}', name='{$name}'" );

	$template_name = "{$slug}.php";
    $name  = (string) $name;
    if ( '' !== $name ) {
        $template_name = "{$slug}-{$name}.php";
	}
 
	tennis_get_template( $template_name );

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

	$find = array();
	$file = '';

	$cptName =  TennisEventCpt::CUSTOM_POST_TYPE;
	$my_post_types = array( $cptName );
	  
	if( ! is_singular( $my_post_types) && ! is_post_type_archive( $my_post_types ) ) {
		error_log("$loc: E A R L Y   R E T U R N");
	  return $template;
	}
	
	// if ( is_singular( 'post' ) ) :
	// 	$file = 'post-override.php';
	// elseif ( is_singular( 'page' ) ) :
	// 	$file = 'page-override.php';
	// endif;
	  // Provided Template $template: /Users/you/Sites/your_site/wp-content/themes/your_theme/archive.php
	  $provided_template_array = explode( '/', $template );
	  
	  /* Provided Template Array:
	  *  Array ( 
	      [0] => 
	      [1] => Users 
	      [2] => you 
	      [3] => Sites 
	      [4] => your_site 
	      [5] => wp-content 
	      [6] => themes 
	      [7] => your_theme 
	      [8] => archive.php )
	  **/
	  // This will give us archive.php
	  $file = end( $provided_template_array );
	  if( is_post_type_archive( $my_post_types ) ) {
		  $file = 'archive-tenniseventcpt.php';
	  }
	  elseif( is_singular( $my_post_types ) ) {
		  $file = 'single-tenniseventcpt.php';
	  }
	  error_log("$loc: looking for file='$file'");
	
	  $templateFile = tennis_locate_template( $file );
	  if ( file_exists( $templateFile ) ) {
		$template = $templateFile;
	  }

	return $template;

}
add_filter( 'template_include', 'tennis_template_loader' );



// define( 'MY_PLUGIN_DIR', plugin_dir_path( __FILE__) );
// define( 'MY_PLUGIN_TEMPLATE_DIR', MY_PLUGIN_DIR . '/templates/' );
// add_filter( 'template_include', 'ibenic_include_from_plugin', 99 );

// function ibenic_include_from_plugin( $template ) {
	  
// 	  $our_post_types = array( 'portfolio', 'services' );
	  
// 	  if( ! is_singular( $our_post_types) && ! is_post_type_archive( $our_post_types ) ){
// 	    return $template;
// 	  }
	  
// 	  // Provided Template $template: /Users/you/Sites/your_site/wp-content/themes/your_theme/archive.php
// 	  $provided_template_array = explode( '/', $template );
	  
// 	  /* Provided Template Array:
// 	  *  Array ( 
// 	      [0] => 
// 	      [1] => Users 
// 	      [2] => you 
// 	      [3] => Sites 
// 	      [4] => your_site 
// 	      [5] => wp-content 
// 	      [6] => themes 
// 	      [7] => your_theme 
// 	      [8] => archive.php )
// 	  **/
// 	  // This will give us archive.php
// 	  $new_template = end( $provided_template_array );
	  
// 	  // Getting the post type slug for folder name
//   	$subfolder = get_post_type();
  	
//  	  $plugin_template = MY_PLUGIN_TEMPLATE_DIR . $subfolder . '/' . $new_template;
 	
//   	if( file_exists( $plugin_template ) ) {
//   	  return $plugin_template;
//   	}
	
// 	  return $template;
// }