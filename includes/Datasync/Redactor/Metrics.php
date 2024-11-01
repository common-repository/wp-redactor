<?php
/**
 * The metrics calculations and reqports page
 */
namespace Datasync\Redactor;

if( ! defined( 'WP_REDACTOR' ) ) exit;

/**
 * The page to display the reports
 *
 * Displays list of available report and displays the content of
 * the selected report from the list.
 *
 * Example usage:
 * Metrics::get_instance();
 *
 * @package  	Redactor
 * @author   	Joseph M. King <michael.king@datasynctech.com>
 * @author   	Thane Durey <tdurey@harding.edu>
 * @copyright   2016 DataSync Technologies                                  
 * @license		GPLv2 or later                                                              
 * @access   	public
 * @since    	1.3.0
 */
class Metrics
{
    /**
     * The singleton instance of the class
     * @access private
     * @since 1.3.0
     * @var object
     */
    private static $instance = null;
    
    /**
     * The list of reports that can be rendered
     * @access private
     * @since 1.3.0
     * @var array
     */
    private $reports = array(
    	'\Datasync\Redactor\Reports\TopRedactions' => 'Top Redactions',
    	'\Datasync\Redactor\Reports\TopRedactors' => 'Top Redactors',
    	'\Datasync\Redactor\Reports\ByTimePeriod' => 'By Time Period'
    );

    /**
     * Gets a singleton of this plugin
     *
     * Retrieves or creates the plugin singleton.
     *
     * @static
     * @access public
     * @since 1.3.0
     * @return plugin singleton
     */
    public static function get_instance () 
    {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
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

        // put any filters and hooks here
        add_filter('manage_users_columns' , array( $plugin, 'manage_columns' ) );
        add_filter('manage_users_custom_column', array( $plugin, 'user_redaction_count_column' ), 11, 3);
        
        add_filter('manage_pages_columns', array( $plugin, 'manage_columns') );
        add_filter('manage_posts_columns', array( $plugin, 'manage_columns') );
        add_filter('manage_posts_custom_column', array( $plugin, 'posts_redaction_count_column' ), 11, 3);
        
        // any time a post status change occurs, recalculate the metrics
        add_action( 'transition_post_status', array( $plugin, 'post_status_change' ), 10, 3 );
        add_action( 'post_redaction_update', array( $plugin, 'redaction_save' ), 10, 2 );
    }
        
    /**
     * Initialize the wordpress hooks and filters
     *
     * @since 1.3.0
     * @access public
     * 
     * @param string $option
     */
    public function render_report( $option )
    {    	
    	// class names will be 
    	$class_name = str_replace( '\\\\', '\\', $option );
    	
    	if( ! class_exists( $class_name ) ) {
    		
    		$class_name = '\Datasync\Redactor\Reports\TopRedactions';
    	}
    	
    	$metric = new $class_name;
    	$metric->render();
    }
    
    /**
     * Get the list of available reports
     * 
     * @since 1.3.0
     * @access public 
     */
    public function get_reports()
    {
    	
    	return apply_filters( 'wp_redactor_metrics', $this->reports );
    }
    

    /**
     * Add redaction count column to the user table
     *
     * @access public
     * @since   1.3.0
     * 
     * @param array $columns
     */
    public function manage_columns( $columns )
    {
    	$columns["redaction_count"] = "Redactions";
    	return $columns;
    }
    
    /**
     * Return the number of redactions created for each user.
     *
     * @access public
     * @since   1.3.0
     * 
     * @param int $value
     * @param string $column_name
     * @param int $user_id
     * @return int
     */
    public function user_redaction_count_column($value, $column_name, $user_id)
    {
    	global $blog_id;
    	
    	if ( 'redaction_count' == $column_name ) {
    		$count = get_user_meta( $user_id,  'redaction_count_' . $blog_id, true );
    		return ! empty( $count ) ? $count : '0';
    	}
    }

    /**
     * Return the number of redactions created for each post.
     *
     * @access public
     * @since   1.3.0
     * 
     * @param string $column_nane
     * @param int $post_id
     */
    public function posts_redaction_count_column( $column_name, $post_id )
    {
    	if ( 'redaction_count' == $column_name ) {
    		$count = get_post_meta( $post_id,  '_redaction_count', true );
    		echo ( ! empty( $count ) ) ? $count : '0';
    	}
    }
    
    /**
     * Recalculate the metrics for the post
     * 
     * If all the posts have a _redaction_count metric, post and user metrics
     * can be calculated by iterating over all posts. This is better than doing
     * large sql queries in the post table
     * 
     * @param string $new_status
     * @param string $old_status
     * @param object $post
     */
    function post_status_change( $new_status, $old_status, $post ) 
    {
    	$this->update_post_metrics( $post );
    }
    
    /**
     * uUpdate all the metrics for the post
     * 
     * @access public
     * @since 1.3.0
     * 
     * @param WP_Post $post
     */
    public function update_post_metrics( $post )
    {
    	global $blog_id;
	    	
    	// save the old metrics
    	$past_metrics = get_post_meta( $post->ID, '_redaction_metrics', true );
    	$past_metrics['users'] = isset($past_metrics['users']) ? $past_metrics['users'] : array();
    	$past_metrics['redactions'] = isset($past_metrics['redactions']) ? $past_metrics['redactions'] : array();
    	 
    	// calculate the new metrics
    	$metrics = $this->get_post_metrics( $post );
    	 
    	// get an array of user_ids that need to have their metrics updated
    	$users = array_unique( array_merge(
    			array_keys( $past_metrics['users'] ),
    			array_keys( $metrics['users'] ) ) );
    	
    	// loop through affected users and update their redaction counts
    	foreach( $users as $user_id ) {
    	
    		$count = $this->get_user_pattern_count( $user_id );
    		update_user_meta( $user_id, 'redaction_count_' . $blog_id, $count );
    	}
    	 
    	$patterns = array_unique( array_merge(
    			array_keys( $past_metrics['redactions'] ),
    			array_keys( $metrics['redactions'] ) ) );
    	
    	// update any affected pattern counts
    	foreach( $patterns as $pattern_id ) {
    		$count = $this->get_pattern_count( $pattern_id );
    		// TODO: move to redactor-model.php
    		global $wpdb;
    		$wpdb->update( $wpdb->prefix . WP_REDACTOR_TABLENAME,
    				array( 'int_redaction_count' => $count ),
    				array( 'ID' => $pattern_id ),
    				array( '%d' ), array( '%d' )
    				);
    	}
    	
    	// add the metrics to the post metadata
    	// keys that start with an '_' will be hidded from the custom post data
    	update_post_meta( $post->ID, '_redaction_metrics', $metrics );
    	update_post_meta( $post->ID, '_redaction_count', array_sum( $metrics['redactions'] ) );	    	
    }
    
    /**
     * Any time a redaction is modified update the metrics
     * 
     * @access public
     * @since 1.3.0
     * 
     * @param int $id
     * @param int $user_id
     */
    public function redaction_save( $id, $user_id )
    {
    	$args = array( 'numberposts' => -1 );
    	
    	$user_id = self::get_user_id( $user_id );
    	
    	foreach( get_posts( $args ) as $post ) {
    		
    		$this->get_post_metrics( $post );
    	}
    	
    }
    
    /**
     * Calculate post metrics from pattern and user selected redactions
     * 
     * @access public
     * @since 1.3.0
     * 
     * @param string $content
     * @return array
     */
    public function get_post_metrics( $post )
    {
    	$metrics = array(
    			'redactions' => array(),
    			'users' => array()
    	);
    	
    	$should_redact = apply_filters( 'wp_should_redact', null, $post );
    	
    	if( false !== $should_redact ) {
	    	
	    	$stripped_content = strip_shortcodes( strip_tags( $post->post_content) );
	    	
	    	// get the redactions based on patern
	    	$redactions = View::get_instance()->get_redactions( $stripped_content );
	
	    	// get just the id and count from the returned array
	    	foreach( $redactions as $redaction ) {
	    		
	    		$user_id = self::get_user_id( $redaction->str_username );
	    		
	    		$count = isset( $metrics['redactions'][$redaction->id] ) ? $metrics['redactions'][$redaction->id] : 0;
	    		$metrics['redactions'][$redaction->id] = $count + $redaction->post_count;
	    		
	    		$count = isset( $metrics['users'][$user_id] ) ? $metrics['users'][$user_id] : 0;
	    		$metrics['users'][$user_id] = $count + $redaction->post_count;
	    	}
    	}
    	
    	// get all the shortcode redactions
    	foreach( $this->get_shortcode_redactions( $post->post_content ) as $redaction ) {
    	
    		$user_id = self::get_user_id( $redaction['redactor'] );

    		$count = isset( $metrics['redactions']['shortcode'] ) ? $metrics['redactions']['shortcode'] : 0;
    		$metrics['redactions']['shortcode'] = $count + 1;
    		
    		$count = isset( $metrics['users'][$user_id] ) ? $metrics['users'][$user_id] : 0;
    		$metrics['users'][$user_id] = $count + 1;
    	}
    	
    	return $metrics;    	
    }
    
    /**
     * Recalculate user metrics
     * 
     * @access public
     * @since 1.3.0
     * 
     * @param int $user_id
     * @return array
     */
    public function get_user_pattern_count( $user_id )
    {
    	$count = 0;
    	
    	$args = array( 'numberposts' => -1 );
    	
    	// loop through all posts
    	// TODO: can we shortcut this somehow
    	foreach( get_posts( $args ) as $post ) {
    		
    		// if there are redaction metrics
    		if( false !== $metrics = get_post_meta( $post->ID, '_redaction_metrics', true ) ) {
    			
    			if( empty( $metrics ) ) continue;
    			
    			// if this post has any metrics for this user
    			if( isset( $metrics['users'][$user_id] ) ) {
    			
    				$count = $count + $metrics['users'][$user_id];
    			}
    		}
    	}
    	
    	return $count;
    }
    
    /**
     * Recalculate the pattern metrics
     * 
     * @param unknown $redaction_id
     */
    public function get_pattern_count( $id )
    {
    	$count = 0;
    	 
    	$args = array( 'numberposts' => -1 );
    	
    	// loop through all posts
    	// TODO: can we shortcut this somehow
    	foreach( get_posts( $args ) as $post ) {
    	
    		// if there are redaction metrics
    		if( false !== $metrics = get_post_meta( $post->ID, '_redaction_metrics', true ) ) {
    			 
    			// if this post has any metrics for this user
    			if( isset( $metrics['redactions'][$id] ) ) {
    				 
    				$count = $count + $metrics['redactions'][$id];
    			}
    		}
    	}
    	 
    	return $count;
    }
    
    /**
     * Resolve username to user id to support old code
     * 
     * @access public
     * @since 1.3.0
     * 
     * @param mixed $username
     * @return int
     */
    public static function get_user_id( $user_id ) 
    {
    	// if not already a user id see if this is a login
    	if( ! is_numeric( $user_id ) ) {
    		
    		$user = get_user_by( 'login', $user_id );
    		$user_id = is_a( $user, 'WP_User' ) ? $user->ID : 0;
    	}
    	
    	return $user_id;
    }
    
    /**
     * Count all the user redactions in a post
     * 
     * @access public
     * @since 1.3.0
     * 
     * @param string $content
     * @return array
     */
    public function get_shortcode_redactions( $content )
    {
    	$ret = array();
    	
    	// if there are any manual redactions
    	// TODO: too much code here. break into separate function
    	if ( has_shortcode( $content, 'redact' ) ) {

    		$pattern = get_shortcode_regex();
    	
    		// get all the redact
    		preg_match_all('/'.$pattern.'/', $content, $matches);
    	
    		// get a count of all the matching patterns
    		$count = count( $matches[2] );
    	
    		$attrs = array();
    	
    		// loop through the matches
    		for( $i=0; $i<$count; $i++ ) {
    			 
    			// test if this shortcode is a redaction
    			if( $matches[2][$i] == 'redact' ) {
    	
    				$ret[] = shortcode_parse_atts( $matches[3][$i] );
    			}
    		}
    	}
    	
    	return $ret;
    }
}

