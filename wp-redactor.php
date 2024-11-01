<?php
/*                                                     
Plugin Name: WP Redactor                            
Plugin URI:  https://github.com/DataSyncTech/wp-redactor/                         
Description: A Wordpress plugin to enable redaction of text from published posts                     
Version:     1.5.2                   
Author:      DataSync Technologies                          
Author URI:  http://www.datasynctech.com/                   
License:     GPL2                         
License URI: https://www.gnu.org/licenses/gpl-2.0.html                  
Domain Path: /languages                                 
Text Domain: wp-redactor                      
*/                                                       
if( ! defined( 'ABSPATH' ) ) exit;     

define( 'WP_REDACTOR', true );

/**
 * The WP Redactor plugin class
 *
 * The main plugin class. The plugin class should handle all the
 * botstrapping:
 * - activation and deactivation
 * - database emigration
 * - loading of other classes
 *
 * Example usage:
 * WP_Redactor_Plugin::get_instance();
 *
 * @package  	Redactor
 * @author   	Joseph M. King <michael.king@datasynctech.com>
 * @author   	Thane Durey <tdurey@harding.edu>
 * @copyright   2016 DataSync Technologies
 * @license		GPLv2 or later
 * @access   	public
 * @since    	1.0.0
 */
class WP_Redactor_Plugin
{
	/**
	 * Plugin version
	 * @var string
	 */
	const VER = '1.5.2';
	
	/**
	 * Plugin database version
	 * @var int
	 */
	const DB_VER = 3;
	
	/**
	 * Plugin singleton
	 * @var object
	 */
	private static $instance = null;
	
	/**
	 * Creates or returns an instance of this class.
	 *
	 * @since 1.3.0
	 * @access public
	 *
	 * @return object
	 */
	public static function get_instance() {
	
		if ( null == self::$instance ) {
			self::$instance = new self;
			self::initialize();
		}
	
		return self::$instance;
	}
	
	/**
	 * Initialize the wordpress hooks and filters
	 *
	 * @since 1.3.0
	 * @access public
	 */
	public static function initialize()
	{
		$plugin = new self();

		// load vendor files if packaged with plugin
		if( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
			
			require_once( __DIR__ . '/vendor/autoload.php' );
		}
		
		// load activation methods
		register_activation_hook( __FILE__, array( $plugin, 'activate' ) );
		
		// initialize plugin paths
		$plugin->init_paths();
		
		// load the internationalizations
		add_action( 'plugins_loaded', 'wp_redactor_i18n_init' );
		
	    // load the plugin or CLI
	    if( defined( 'WP_CLI' ) && WP_CLI ) {
	      
	      Datasync\WP\CLI::get_instance();
	    } else {
	      
	      Datasync\Redactor\Controller::get_instance();
	    }
	}
	
	/**
	 * Do some stuff upon activation
	 * 
	 * @since 1.3.0
	 * @access public
	 */
	public function activate() 
	{
		// check plugin dependencies
		$this->check_dependencies();
		
		// initialize plugin options
		$this->init_options();
		
		// check if the plugin needs to be updated
		$this->maybe_update();	
	}
	
	/**
	 * Make sure dependencies requirements are met
	 * 
	 * @since 1.3.0
	 * @access public
	 */
	public function check_dependencies() 
	{
		// do nothing if class bbPress exists
		if ( ! wp_redactor_is_compatible_php()  ) {
			
			trigger_error( 'PHP version is not supported.', E_USER_ERROR );
		}
		
		// make sure the vendor director is there
		if( ! file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
			
			trigger_error( 'Vendor files are missing.', E_USER_ERROR );
		}
	}
	
	/**
	 * Check uf the plugin needs to be updated
	 * 
	 * @since 1.3.0
	 * @access public
	 */
	public function maybe_update()
	{
		// bail if this plugin data doesn't need updating
		if ( get_option( 'wp_redactor_db_ver' ) >= self::DB_VER ) {
			return;
		}
		
		require_once( __DIR__ . '/update.php' );		
		wp_redactor_update();
	}
	
	/**
	 * Initialize plugin options
	 * 
	 * @since 1.3.0
	 * @access public
	 */
	public function init_options()
	{
		update_option( 'wp_redactor_ver', self::VER );
		add_option( 'wp_redactor_db_ver', 0 );
	}
	
	/**
	 * Initialize plugin paths
	 * 
	 * @since 1.3.0
	 * @access public
	 */
	public function init_paths()
	{
		define( 'WP_REDACTOR_DIR', dirname( __FILE__ ) );
		define( 'WP_REDACTOR_ASSET_DIR', WP_REDACTOR_DIR );
		define( 'WP_REDACTOR_ASSET_URL',  plugins_url( 'wp-redactor' ) );
		define( 'WP_REDACTOR_LANGUAGE_DIR', WP_REDACTOR_DIR . '/languages' );
	}
}

$wp_redactor_plugin = WP_Redactor_Plugin::get_instance();
