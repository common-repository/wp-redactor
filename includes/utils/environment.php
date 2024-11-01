<?php
/**
 * Functions for checking environment
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
 * Returns true if the PHP version is compatible
 * 
 * @since 1.4.0
 * 
 * @return bool
 */
function wp_redactor_is_compatible_php()
{
    return (version_compare(PHP_VERSION, WP_REDACTOR_PHP_MIN_VERSION) > 0);
}

/**
 * Load the localization files
 * 
 * @since 1.4.0
 */
function wp_redactor_i18n_init()
{
    $pluginDir = dirname( plugin_basename( __FILE__ ) );
    load_plugin_textdomain( WP_REDACTOR_PRIVATE_NAME, false, $pluginDir . '/languages/' );
}