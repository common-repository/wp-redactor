<?php
/**
 * The main controller class
 */
namespace Datasync\Redactor;

if( ! defined( 'WP_REDACTOR' ) ) exit;

/**
 * Report of redactions by time period
 *
 * Displays report for list of redactions broken down by time.
 * Report shows a different table for each year divided into
 * months.
 *
 * Example usage:
 * ByTimePeriod::Render();
 *
 * @package  	Redactor
 * @author   	Joseph M. King <michael.king@datasynctech.com>
 * @copyright   2016 DataSync Technologies                                  
 * @license		GPLv2 or later                                                              
 * @access   	public
 * @since    	1.0.0
 */                                                                     
class Controller{

    /**
    * Plugin singleton
    * @var object
    * @access private
    * @since 1.0.0
    */
    private static $instance = null;

    /**
     * Creates or returns an instance of this class.
     *
     * @since 1.2.0
     * @access public
     *
     * @return object
     */
    public static function get_instance() 
    {
    	if ( null == self::$instance ) {
    		
    		self::$instance = new self;
    		self::initialize();
    	}
    
    	return self::$instance;
    }
    
    /**
     * Initialize the wordpress hooks and filters
     *
     * @since 1.2.0
     * @access public
     */
    public static function initialize()
    {
    	$plugin = new self();
    	 
    	//initializes the languages for the plugin
    	//$plugin->init_i18n_textdomain();
    	//add_action( 'init', array( $plugin, 'init_languages' ), 0 );

    	// load the scripts and styles required to function
        add_action( 'wp_enqueue_scripts',    array( $plugin, 'enqueue_scripts' ) );
    	add_action( 'wp_enqueue_scripts',    array( $plugin, 'enqueue_styles' ) );
    	    	
    	// stuff to only load for admin view
		if( is_admin() ) {
			add_action('admin_init', array( $plugin, 'adminInit' ) );
					
			add_action( 'admin_enqueue_scripts', array( $plugin, 'enqueue_admin_scripts' ) );
			add_action( 'admin_enqueue_scripts', array( $plugin, 'enqueue_admin_styles'  ) );
					
			ListView::get_instance();
			Metrics::get_instance();
			Ajax::get_instance();
		}
    	
		Options::get_instance();
    	View::get_instance();
    }

   /**
     * Registers the plugin's styles with WordPress.
     *
     * @access public
     * @since 1.0.0
     * 
     * @return void
     */
    public function enqueue_styles()
    {
    	wp_enqueue_style( 'style', 
    			WP_REDACTOR_ASSET_URL . '/css/style.css' );
    	wp_enqueue_style( 'tooltipster-style', 
    			WP_REDACTOR_ASSET_URL . '/css/tooltipster.css' );
    }

   /**
     * Registers the plugin's admin styles with WordPress.
     *
     * @access public
     * @since 1.0.0
     * 
     * @param $hook The page the enque hook is being run
     * @return void
     */
    public function enqueue_admin_styles( $hook = '' )
    {
    	wp_enqueue_style( 'style', 
    			WP_REDACTOR_ASSET_URL . '/css/style.css' );
    	wp_enqueue_style( 'admin_style',
    			WP_REDACTOR_ASSET_URL . '/css/admin_style.css' );
    	wp_enqueue_style( 'tooltipster-style', 
    			WP_REDACTOR_ASSET_URL . '/css/tooltipster.css' );
    	
        wp_enqueue_style (  'wp-jquery-ui-dialog');
        
		wp_enqueue_style( 'wp-color-picker' );
    }

   /**
     * Registers the plugin's scripts with WordPress.
     *
     * @access public
     * @since 1.0.0
     * 
     * @return void
     */
    public function enqueue_scripts()
    {
    	wp_enqueue_script('jquery');
    	
    	wp_enqueue_script('tooltipster', 
    			WP_REDACTOR_ASSET_URL . '/js/jquery.tooltipster.min.js', 
    			array('jquery') );

    	wp_enqueue_script('spoiler',
    			WP_REDACTOR_ASSET_URL . '/js/spoiler.min.js',
    			array('jquery') );
    	 
    	wp_enqueue_script('wp-redactor-script', 
    			WP_REDACTOR_ASSET_URL . '/js/wp-redactor.js', 
    			array('jquery','tooltipster') );
    }

   /**
     * Registers the plugin's admin scripts with WordPress.
     *
     * @access public
     * @since 1.0.0
     * 
     * @param $hook The page the enque hook is being run
     * @return void
     */
    public function enqueue_admin_scripts( $hook = '' )
    {
    	wp_enqueue_script('jquery');
    	    	
    	wp_enqueue_script(
                'tooltipster',          
                WP_REDACTOR_ASSET_URL . '/js/jquery.tooltipster.min.js', 
                array('jquery') );
    	
        wp_enqueue_script(
                'momentjs',  
                WP_REDACTOR_ASSET_URL . '/js/moment.js');

        if( get_bloginfo('version') >= '3.5' ) {
    		wp_enqueue_script(
                'wp-redactor-script',   
                WP_REDACTOR_ASSET_URL . '/js/wp-redactor.js',            
                array('jquery', 'underscore', 'wp-color-picker') );
        } else {
        	wp_enqueue_script(
        		'wp-redactor-script',
        		WP_REDACTOR_ASSET_URL . '/js/wp-redactor.js',
        		array('jquery', 'underscore') );
        	 
        }
    } 

   /**
     * Helper function on whether SCRIPT_DEBUG is set
     * 
     * @static
     * @since 1.0.0
     * 
     * @return  boolean
     */
    static function is_script_debug()
    {
        return defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;
    }

   /**
     * Helper function on whether WP_DEBUG is set
     *
     * @static
     * @since 1.0.0
     * 
     * @return  boolean
     */
    static function is_wordpress_debug()
    {
        return defined( 'WP_DEBUG' ) && WP_DEBUG;
    }

   /**
     * Initializes the textdomain for the plugin
     *
     * @access public
     * @since 1.0.0
     * @return  void
     */
    public function init_i18n_textdomain () 
    {
		$lang = apply_filters( 'plugin_locale', 
			get_locale(), WP_REDACTOR_TEXTDOMAIN );

		//
		$mofile = wp_lang_dir();
		$mofile .= DIRECTORY_SEPARATOR . WP_REDACTOR_TEXTDOMAIN; 
		$mofile .= DIRECTORY_SEPARATOR . WP_REDACTOR_TEXTDOMAIN;
		$mofile .= '-' . $lang . '.mo';
    	    
		load_textdomain( WP_REDACTOR_TEXTDOMAIN, $mofile );
		load_plugin_textdomain( WP_REDACTOR_TEXTDOMAIN, 
			false, $this->_languages_dir );
	}

    /**
     * Initialize lanaguages
     * @access  public
     * @since   1.0.0
     * @return  void
     */
    public function init_languages () 
    {
        load_plugin_textdomain( WP_REDACTOR_PRIVATE_NAME, 
        		false, WP_REDACTOR_LANGUAGE_DIR );
    }

   /**
     * Not allowed
     *
     * The plugin is a singleton so don't allow cloning.
     *
     * @access private
     * @since 1.0.0
     * 
     * @return  void
     */
    final private function __clone() {}

    /**
     * A callback function during the mce_buttons filter hook that will
     * add our redact button name to the array of buttons in the text editor.
     * 
     * @param array $buttons An array of button IDs to be rendered in the text editor HTML.
     */
    public function addRedactButton ( $buttons ) 
    {
        array_push( $buttons, 'redact' );
        return $buttons;
    }

    /**
     * A callback function during the mce_external_plugins filter hook that
     * will assign our javascript to be executed by TinyMCE when it renders
     * itself. Our javascript will add the redact button to the editor.
     * 
     * @param array $plugins A map of TinyMCE plugin names to their implementations in a javascript.
     */
    public function addCustomMcePlugin( $plugins ) 
    {
        $plugins['redactor'] = WP_REDACTOR_ASSET_URL . "/js/redactorButton.js";
        return $plugins;
    }


    /**
     * This function will execute on the admin_init action hook and will
     * provide the current user with the redact button if the current user
     * has the edit post and edit page capability. The redact button is
     * added by supplying callbacks to the text editor filter hooks.
     */
    public function adminInit() 
    {
    	if ( is_admin() ) {
    		if (current_user_can ( "edit_posts" ) && current_user_can ( "edit_pages" )) {

    			add_filter ( 'mce_external_plugins', array( $this, 'addCustomMcePlugin' ));
    			add_filter ( 'mce_buttons',          array( $this, 'addRedactButton'    ));
    		}

    		add_editor_style ( WP_REDACTOR_ASSET_URL . '/css/style.css');
    	}
    }
}
