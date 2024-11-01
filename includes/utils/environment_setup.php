<?php
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

function datasync_notifyWrongPhpVersion()
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

function datasync_isCompatiblePhp()
{
    return (version_compare(PHP_VERSION, WP_REDACTOR_PHP_MIN_VERSION) > 0);
}

function datasync_i18n_init()
{
    $pluginDir = dirname( plugin_basename( __FILE__ ) );
    load_plugin_textdomain( WP_REDACTOR_PRIVATE_NAME, false, $pluginDir . '/languages/' );
}