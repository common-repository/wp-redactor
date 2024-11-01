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



require_once( 'redactor-controller.php' );
require_once( __DIR__.'/../utils/constants.php' );

/**
* Used to initialize the plugin. It is another layer of abstraction so that
* the plugin will work with early versions of PHP and warn admins of
* compatibility issues.
*
* @since 0.0.1
* @param string $file The file's full path used to access the plugin.
* @return object An instance of the plugin's controller class.
*/
function datasync_init( $file = '' ){
    $dctr = RedactorController::get_instance( $file, datasync_PLUGIN_VERSION );

    return $dctr;
}

?>