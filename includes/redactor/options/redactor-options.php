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



require_once( __DIR__.'/../../utils/functions.php' );
require_once( __DIR__.'/../../utils/constants.php' );
require_once( __DIR__.'/../redactor-model.php' );
require_once( __DIR__.'/../redactor-ajax-functions.php' );


class RedactorOptions{

    /**
     * The singleton instance of the class
     * @access private
     * @since 0.0.1
     * @var object
     */
    private static $_instance = null;
    
    /**
     * An object of redactor options
     * @since 0.0.1
     * @access private
     * @var object
     */
    private $options;
    
    /**
     * A handle to the RedactorModel instance
     * @access private
     * @since 0.0.1
     * @var object
     */
    private $_model;
    
    /**
     *
     * @var array Defaults for options and their types for verification 
     */
    private $defaults = array(
        'redact_wholeword' => array('type' => 'bool', 'value' => 0),
        'redact_posts'     => array('type' => 'bool', 'value' => 1),
        'redact_comments'  => array('type' => 'bool', 'value' => 0)
    );

    /**
     * Gets a singleton of this plugin
     *
     * Retrieves or creates the plugin singleton.
     *
     * @static
     * @access public
     * @since 0.0.1
     * @return plugin singleton
     */
    public static function get_instance () {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
    * Private constructor to keep the options page a singleton class.
    *
    * @access private
    * @since 0.0.1
    * @return void
    */
    private function __construct(){
        
        if(is_admin()){
            add_action('admin_init', array( $this, 'registerSettings'       ));
            add_action('admin_menu', array( $this, 'add_plugin_options_page' ) );

            add_action( 'wp_ajax_getRules',    'ajax_getRules'    );
            add_action( 'wp_ajax_updateRules', 'ajax_updateRules' );
            add_action( 'wp_ajax_deleteRules', 'ajax_deleteRules' );
            add_action( 'wp_ajax_addRules',    'ajax_addRules'    );
        }
    }

    /**
    * A helper function to return the redactor_options for classes to use.
    *
    * @access public
    * @since 0.0.1
    * @return array redactor options
    */
    public function get_options(){
        return $this->sanitizeWithDefaults( get_option( 'redactor_options' ) );
    }

    /**
    * Sanitizes user input when parsing option uploads
    * based on defaults and option types.
    *
    * @access public
    * @since 0.0.1
    * @return array Sanitized options
    */
    public function sanitizeWithDefaults($options){
        if( !is_array( $options ) || empty( $options ) || ( false === $options ) )
           $options = array();

        //get all known keys to iterate through
       $valid_names = array_keys( $this->defaults );
       $clean_options = array();

        // loop through the valid keys and parse values from the incoming options array
        // into a sanitized array
       foreach( $valid_names as $option_name ) {
           if( isset( $options[$option_name] ) ){
               $def = $this->defaults[$option_name];

                //based on the type of option make sure the incoming option matches expected values
               switch($def['type']){
                    case 'bool':
                        $clean_options[$option_name] = (1==$options[$option_name])? 1 : 0;
                        break;
               }
           }
           else{
               $clean_options[$option_name] = $this->defaults[$option_name]['value'];
           }
       }

       //unset the incoming options array, its sanitized now
       unset( $options );
       return $clean_options;
    }

    /*
     * Registers the options with wordpress and tells the system how it needs to be rendered.
     *
     * @access public
     * @since 0.0.1
     * @return void
     */
    public function registerSettings(){
    	register_setting(
            'redactor_options_group1', // Option group
            'redactor_options',        // Option name
            array( $this, 'sanitizeWithDefaults' ) // Sanitize
        );

        add_settings_section(
            'main_plugin_settings',      // ID
            "", // Title
            null,                       // Callback
            datasync_PLUGIN_PRIVATE_NAME . '_settings_page' // Page
        );

        //add_settings_field(
        //    'redact_wholeword',                              // ID
        //    'Redact whole words',                            // Title
        //    array( $this, 'render_redact_wholeword' ),       // Callback
        //    datasync_PLUGIN_PRIVATE_NAME . '_settings_page', // Page
        //    'main_plugin_settings'                           // Section
        //);

        //add_settings_field(
        //    'redact_posts',                              // ID
        //    'Redact post content',                            // Title
        //    array( $this, 'render_redact_posts' ),       // Callback
        //    datasync_PLUGIN_PRIVATE_NAME . '_settings_page', // Page
        //    'main_plugin_settings'                           // Section
        //);

        add_settings_field(
            'redact_comments',                              // ID
            'Redact post comments',                            // Title
            array( $this, 'render_redact_comments' ),       // Callback
            datasync_PLUGIN_PRIVATE_NAME . '_settings_page', // Page
            'main_plugin_settings'                           // Section
        );
        
    }

    /**
     * Hook to modify the section header
     * 
     * @param string $html
     */
    public function global_setting_header( $setting )
    {
    	echo "<p>Global settings apply to all redactions.</p>";
    }
    
    /**
     * Options page callback. Echos the rendering of the options page.
     *
     * @access public
     * @sinces 0.0.1
     * @return void
     */
    public function create_admin_page()
    {
        $this->options = $this->get_options();
        ?>
        <div class="wrap">
            <h2>Redactor Settings</h2>
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

    /**
     * Registers the options page with the Wordpress settings menu.
     *
     * @access public
     * @since 0.0.1
     * @return void
     */
    public function add_plugin_options_page(){
    	
    	add_submenu_page( 'wp-redactor', 'Settings', 'Settings', 'manage_options',
    			'wp-redactor-settings', array( $this, 'create_admin_page' ) );
    }

    /**
     * Prints the HTML string to render the options.
     * @access public
     * @since 0.0.1
     */
    public function render_redact_wholeword(){
        $options = $this->options;

        printf(
            "<input type='checkbox' name='redactor_options[redact_wholeword]' value='1' %s ></input>",
            checked(1, $options['redact_wholeword'], false)
        );
    }

    /**
     * Prints the HTML string to render the post options.
     * @access public 
     * @since 0.0.1
     */
    public function render_redact_posts(){
        $options = $this->options;

        printf(
            "<input type='checkbox' name='redactor_options[redact_posts]' value='1' %s ></input>",
            checked(1, $options['redact_posts'], false)
        );
    }

    /**
     * Prints the HTML string to render the comment options
     * @access public
     * @since 0.0.1
     */
    public function render_redact_comments(){
        $options = $this->options;
        
        printf(
            "<input type='checkbox' name='redactor_options[redact_comments]' value='1' %s ></input>",
            checked(1, $options['redact_comments'], false)
        );
    }

    /**
     * Prints the HTML string to render the rule table.
     * @access public
     * @since 0.0.1
     */
    public function render_rule_table(){
        ?>
        <div id="autorule-table" style="width:100%;height:400px"></div>
        <?php
    }


}
