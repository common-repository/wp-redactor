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

/**
*  The minimum version of PHP that the plugin will work with.
*/
define( "WP_REDACTOR_PHP_MIN_VERSION", '5.3.0'               );

/**
*  The public name to show users when referring to the plugin. Populated using Grunt values extracted
* from package.json
*/
define( "WP_REDACTOR_PUBLIC_NAME", 'WP Redactor'    );

/**
*  The short name of the plugin that is code compatible. Populated using Grunt values extracted
* from package.json
*/
define( "WP_REDACTOR_PRIVATE_NAME", 'wpredactor'   );

/**
*  The domain name to use for the i18n translations. Populated using Grunt values extracted
* from package.json
*/
define( "WP_REDACTOR_TEXTDOMAIN", 'wp-redactor'    );

/**
* The plugin's current version number. Populated using Grunt values extracted
* from package.json
*/
define( "WP_REDACTOR_VERSION", '1.5' );

define( 'WP_REDACTOR_REGEX_NOT_IN_HTML_TAG', '(?!([^<]+)?>)');

define( 'WP_REDACTOR_REGEX_NOT_IN_TAG', '(?!([^\[|<]+)?(>|\[\/(no)*redact\]))' );

define( 'WP_REDACTOR_REGEX_NOT_REDACTED', '(?!([^<]+)?<\/(no)*redact>)');

/**********************************************************************************
 * Database constants
 **********************************************************************************/

/**
* The name of the table to create and use for storing redactions.
*/
define( "WP_REDACTOR_TABLENAME", 'datasync_redactions' );

/**
 * Redacted character
 */
define("WP_REDACTOR_FULL_BLOCK", '&#9608;');
