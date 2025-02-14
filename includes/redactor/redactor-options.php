<?php
/**                                                                    
* wp-redactor                                                      
*                                                                      
* @package     wp-redactor                                         
* @author      DataSync Technologies                                       
* @copyright   2016 DataSync Technologies                                  
* @license                                                             
*                                                                      
*/                                                                     
if( ! defined( 'ABSPATH' ) ) exit;                                   



require_once( __DIR__.'/../utils/functions.php' );
require_once( __DIR__.'/../utils/constants.php' );


class RedactorOptions{

    private static $_instance = null;
    private $options;
    private $defaults = array( 'redact_wholeword' => 0 );



    /**
     * Gets a singleton of this plugin
     *
     * Retrieves or creates the plugin singleton.
     *
     * @static
     * @access public
     * @since 0.0.1
     * @return plugin singleton
     * @return  void
     */
    public static function get_instance () {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    private function __construct(){

        if(is_admin()){
            add_action('admin_init', array( $this, 'registerSettings'       ));
            add_action('admin_menu', array( $this, 'add_plugin_options_page' ) );
        }

    }

    public function get_options(){
        return get_option( 'redactor_options' );
    }


    public function sanitize($options){
        if( !is_array( $options ) || empty( $options ) || ( false === $options ) )
           $options = array();

       $valid_names = array_keys( $this->defaults );
       $clean_options = array();

       foreach( $valid_names as $option_name ) {
           if( isset( $options[$option_name] ) && ( 1 == $options[$option_name] ) )
               $clean_options[$option_name] = 1;
           else
               $clean_options[$option_name] = 0;
       }
       unset( $options );
       return $clean_options;
    }

    public function registerSettings(){
    	register_setting(
            'redactor_options_group1', // Option group
            'redactor_options', // Option name
            array( $this, 'sanitize' ) // Sanitize
        );

        add_settings_section(
            'main_plugin_settings', // ID
            datasync_PLUGIN_PUBLIC_NAME . " Plugin Settings", // Title
             null, // Callback
            datasync_PLUGIN_PRIVATE_NAME . '_settings_page' // Page
        );

        add_settings_field(
            'redact_wholeword', // ID
            'Redact whole words', // Title
            array( $this, 'render_redact_wholeword' ), // Callback
            datasync_PLUGIN_PRIVATE_NAME . '_settings_page', // Page
            'main_plugin_settings' // Section
        );
    }

    /**
     * Options page callback
     */
    public function create_admin_page()
    {
        // Set class property
        $this->options = $this->get_options();
        ?>
        <div class="wrap">
            <h2>My Settings</h2>
            <form method="post" action="options.php">
            <?php
                // This prints out all hidden setting fields
                settings_fields( 'redactor_options_group1' );
                do_settings_sections( datasync_PLUGIN_PRIVATE_NAME . '_settings_page' );
                submit_button();
            ?>
            </form>
        </div>
        <?php
    }


    public function add_plugin_options_page(){
        // This page will be under "Settings"
        add_options_page(
            datasync_PLUGIN_PUBLIC_NAME . ' Settings Admin',
            'Redactions',
            'manage_options',
            datasync_PLUGIN_PRIVATE_NAME . '_settings_page',
            array( $this, 'create_admin_page' )
        );
    }


    public function render_plugin_options_main(){
        echo "Enter your settings below";
    }

    public function render_redact_wholeword(){
        printf(
            "<input type='checkbox' name='redactor_options[redact_wholeword]' value='1' %s ></input>",
            ( 1 ==  $this->options['redact_wholeword'] )? "checked='checked'" : ''
        );
    }

}
