<?php
/**
 * Generic functions in the global space
 */

/**                                                                    
* Redactor                                                      
*                                                                      
* @package     Redactor                                         
* @author      DataSync Technologies                                       
* @copyright   2016 DataSync Technologies                                  
* @license                                                             
*                                                                      
*/                                                                     
if( ! defined( 'ABSPATH' ) ) exit;                                   

/**
 * Escapes a string slashes to use in a regex
 * 
 * @since 1.0.0
 * 
 * @param string $content
 * @return string
 */
function wp_redactor_escape_regex( $content )
{    
        $escapedContent = preg_quote( $content );
        $escapedContent = str_replace( "/", "\/", $escapedContent );
        
        return $escapedContent;
}

/**
 * Print the options available to the interface
 *
 * @since 1.2.0
 * 
 * @param Mixed $selected
 */
function ds_dropdown_roles( $selected_items = array() )
{
	$hidden_roles = array( 'administrator', 'editor' );
    if ( ! is_array( $selected_items ) ) $selected_items = array( $selected_items );

    $roles = apply_filters( 'redactor_roles', get_editable_roles() );
    $role_list = array();
    foreach ( $roles as $role => $details ) {
    	if( in_array( $role,  $hidden_roles ) ) continue;
        $name = translate_user_role( $details['name'] );
        $selected = ( in_array( $role, $selected_items ) ) ? 'selected="selected"' : '';
        $role = esc_attr( $role );
        $role_list[] = "<option value=\"{$role}\" {$selected}>{$name}</option>";

    }

    print implode( "\n", $role_list );
}

/**
 * Print the options available to the interface
 *
 * @since 1.2.0
 * 
 * @param mixed $selected
 */
function ds_dropdown_metrics( $selected_item = null )
{
	$reports = Datasync\Redactor\Metrics::get_instance()->get_reports();
		
	$metrics_list = array();
	foreach( $reports as $key => $name ) {
		$selected = ($key == $selected_item) ? ' selected="selected"' : '';
		$metrics_list[] = "<option value=\"{$key}\"{$selected}>{$name}</option>";
    }
    
    print implode( "\n", $metrics_list );
}

/**
 * Return notice for an incompatible PHP version
 *
 * @since 1.4.0
 *
 * @return string
 */
function wp_redactor_notify_wrong_php_version()
{
	$message = '<div class="notice notice-success is-dismissible">';
	$message .= _e(
			'Error: plugin "'.
			WP_REDACTOR_PUBLIC_NAME.
			'" is not compatible with the current version of PHP '.PHP_VERSION.'. (requires php > '.
			WP_REDACTOR_PHP_MIN_VERSION .
			')',
			WP_REDACTOR_PRIVATE_NAME
			);
	$message .= '<div>';

	echo $message;
}
