<?php
/**
 * The main ajax request handler class
 */
namespace Datasync\Redactor;

if( ! defined( 'WP_REDACTOR' ) ) exit;

/**
 * Handle ajax requests
 *
 * Ajax request allow for interaction between javascript
 * and back end.
 *
 * Example usage:
 * Ajax::get_instance();
 *
 * @package  	Redactor
 * @author   	Joseph M. King <michael.king@datasynctech.com>
 * @copyright   2016 DataSync Technologies
 * @license		GPLv2 or later
 * @access   	public
 * @since    	1.0.0
 */
class Ajax
{
	/**
	 * A single instance of this class
	 *
	 * @since 1.3.0
	 * @access private
	 *
	 * @var Object
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
    	
    	add_action( 'wp_ajax_get_roles', array( $plugin, 'get_roles' ) );
    	
    	add_action( 'wp_ajax_get_username_date', 
    			array( $plugin, 'get_username_date' ) );
    	
    	add_action( 'wp_ajax_get_redact_dialog', array( $plugin, 'get_redact_dialog' ) );
    }
	
	/**
	 * Return the roles available for selection in the redaction dialog box.
	 * Do not include adminstrator or editor because they always have access
	 * to the content.
	 * 
	 * @access public 
	 * @since 1.0.0
	 * 
	 * @global $wp_roles;
	 */
	public function get_roles() 
	{
	    global $wp_roles;
	    
	    $roles = $wp_roles->get_names();
	    
	    // they always have access to the content
	    unset( $roles['administrator'], $roles['editor'] );
	    
	    echo json_encode($roles);
	    
	    wp_die();
	}
	
	/**
	 * Return general info about the user and today's date so that it
	 * can be added to a tooltip for the redaction.
	 * 
	 * @access public
	 * @since 1.0.0
	 */
	public function get_username_date() 
	{
	    $current_user = wp_get_current_user();
	    
	    $date = current_time( 'm/d/Y' );
	    
	    echo json_encode(array(
	            'name' => $current_user->user_login,
	            'date' => $date
	    ));
	    
	    wp_die();
	}
	
	/**
	 * Return the dialog HTML for the redact shortcode
	 * 
	 * @access public
	 * @since 1.4.0
	 */
	public function get_redact_dialog()
	{
		$mce_widgets = array();
		
		$mce_widgets[] = array(
			'type' => 'label',
			'text' => 'Select roles allowed to view redacted content:',
			'multiline' => true
		);
		
		$mce_widgets[] = array(
			'type' => 'label',
			'text' => 'Administrators and editors can always view redacted text.',
			'multiline' => true,
			'style' => 'font-size: 8pt;font-style: italic'
		);
				
		global $wp_roles;
		$roles = $wp_roles->get_names();
		unset( $roles['administrator'], $roles['editor'] );
		$mce_roles = array();
		foreach( $roles as $key=>$role ) {
			$mce_roles[] = array(
				'type' => 'checkbox',
				'name' => "role[$key]",
				'text' => $role,
				'style' => 'margin-bottom: 2px'
			);	
		}
		
		$mce_widgets[] = array(
				'type' => 'buttongroup',
				'items' => $mce_roles,
				'name' => 'role'
		);
		
		$mce_widgets[] = array(
				'type' => 'listbox',
				'name' => 'style',
				'label' => 'Style',
				'values' => array(
						array( 'text' => 'Default', 'value' => 'default' ),
						array( 'text' => 'Solid', 'value' => 'solid' ),
						array( 'text' => 'Hidden', 'value' => 'hidden' ),
						array( 'text' => 'Alternate Text', 'value' => 'alttext' ),
						array( 'text' => 'Spoiler', 'value' => 'spoiler' )
				)
		);


		echo json_encode( $mce_widgets );
		
		wp_die();
	}
	
	/**
	 * AJAX function to retrieve rules from the database using limits and offsets
	 * 
	 * @access public
	 * @since 1.0.0
	 */
	public function get_rules()
	{
	    $redactorModel = Model::get_instance();
	    
	    // don't allow non admins to make this ajax request
	    if( ! current_user_can( "administrator" ) ) {
	        die( "Not permitted" );
	    }
	
	    // get all rules from the database
	    $results = $redactorModel->getRawRuleRecords( 0, 50000 );
	
	    // format the output
	    $output = array(
	        'offset' => $offset,
	        'limit' => $limit,
	        'total' => $rowCount,
	        'results' => $results
	    );
	
	    echo json_encode( $output );
	    
	    wp_die();
	}
}