<?php
/**
 * The redactor CLI commands
 */
namespace Datasync\WP;

use \WP_CLI;
use \WP_CLI_Command;
use \WP_CLI\Utils;
use \Datasync\Redactor\Metrics;

if( ! defined( 'WP_REDACTOR' ) ) exit;

/**
 * Handle WP ClI commands related to WP Redactor
 *
 * Example usage:
 * wp redactor metrics
 *
 * @package  	Redactor
 * @author   	Joseph M. King <michael.king@datasynctech.com>
 * @copyright   2016 DataSync Technologies
 * @license		GPLv2 or later
 * @access   	public
 * @since    	1.0.0
 */
class CLI extends WP_CLI_Command
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
		
		WP_CLI::add_command( 'redactor', $plugin );		
	}
	
	/**
	 * Return array of all blog ids
	 * 
	 * @since 1.3.0
	 * @access private
	 * 
	 * @return array
	 */
	private function get_network_blog_ids()
	{
		$ret = array();
		
		// if not multisite just return blog 1
		if( ! is_multisite() ) {
			$ret[] = 1;
		}
		
		// WordPress 4.6
		if ( function_exists( 'get_sites' ) && class_exists( 'WP_Site_Query' ) ) {
			$sites = get_sites();
			foreach ( $sites as $site ) {
				$ret[] = $site->blog_id;
			}
		}
		
		// WordPress < 4.6
		if ( function_exists( 'wp_get_sites' ) ) {
			$sites = wp_get_sites();
			foreach ( $sites as $site ) {
				$ret[] = $site['blog_id'];
			}
		}

		return $ret;
	}
	
	/**
	 * Handle CLI commands for metrics
	 * 
	 * @since 1.3.0
	 * @access public
	 * 
	 * @subcommand metrics
	 * 
	 * @param mixed $args
	 * @param mixed $assoc_args
	 */
	public function metrics( $args, $assoc_args )
	{
		$blog_ids = array( 1 );

		// see if we are recalculating entire network
		if( key_exists( 'network', $assoc_args ) ) {
			
			$blog_ids = $this->get_network_blog_ids();
		// get the selected blog ids
		} elseif( isset( $assoc_args['blog-id'] ) ) {
			
			$blog_ids = explode(',', $assoc_args['blog-id'] );
		}
		
		// loop over all blogs
		foreach( $blog_ids as $blog_id ) {
		
			if( isset( $args[0] ) && $args[0] == 'recalculate' ) {
				$this->metrics_recalculate( $blog_id );
			}
		}
		
		WP_CLI::success( "Redaction metrics recalculated." );
	}
	
	/**
	 * Recalculate all post metrics
	 * 
	 * @since 1.3.0
	 * @access private
	 * 
	 * @param int $blog_id
	 */
	private function metrics_recalculate( $blog_id = 1 ) 
	{
		if( is_multisite() ) switch_to_blog( $blog_id );
		
		$post_args = array( 'numberposts' => -1 );

		$all_posts = get_posts( $post_args );
		
		$progress = Utils\make_progress_bar( "Calculating metrics", count( $all_posts ) );
		
		foreach( $all_posts as $post ) {
		
			Metrics::get_instance()->get_post_metrics( $post );
			
			$progress->tick();
		}
		
		$progress->finish();
		
		if( is_multisite() ) restore_current_blog();
	}
}