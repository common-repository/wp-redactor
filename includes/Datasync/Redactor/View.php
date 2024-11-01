<?php
/**
 * The plugin display functions
 */
namespace Datasync\Redactor;

use NooNoo\FluentRegex\Regex as Regex;

if( ! defined( 'WP_REDACTOR' ) ) exit;

/**
 * Handles all the rendering of redactions
 * 
 * Display the different renderings of the redaction based on
 * role.
 *
 * Example usage:
 * View::get_instance();
 *
 * @package  	Redactor
 * @author   	Joseph M. King <michael.king@datasynctech.com>
 * @author   	Thane Durey <tdurey@harding.edu>
 * @copyright   2016 DataSync Technologies
 * @license		GPLv2 or later
 * @access   	public
 * @since    	1.0.0
 */
class View{

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
     * @since 1.0.0
     * @access public
     */
    public static function initialize()
    {
    	$plugin = new self();
    
    	// hooks and filters related to redaction views
      	add_filter( 'the_title', 	    array( $plugin, 'redact_post_title' ) );
      	add_filter( 'the_content',      array( $plugin, 'redact_post_content') );
    	add_filter( 'comment_text',     array( $plugin, 'redact_comment_content') );
    	add_filter( 'comment_text_rss', array( $plugin, 'redact_comment_content') );
    	add_filter( 'do_shortcode_tag',	array( $plugin, 'redact_shortcode_content'), 10, 4 );
    	
    	add_action( 'add_meta_boxes',   array( $plugin, 'metrics_meta_box' ) );
    	
    	add_shortcode('noredact',       array( $plugin, 'noredact_shortcode' ), 5);
    	add_shortcode('redact',         array( $plugin, 'redact_shortcode' ));
    	
    	add_filter( 'wp_redactor_render_solid', array( $plugin, 'render_solid' ), 10, 3 );
    	add_filter( 'wp_redactor_render_hidden', array( $plugin, 'render_hidden' ), 10, 3 );
    	add_filter( 'wp_redactor_render_alttext', array( $plugin, 'render_alttext' ), 10, 3 );
    	add_filter( 'wp_redactor_render_spoiler', array( $plugin, 'render_spoiler' ), 10, 3 );
    }

   /**
     * Not allowed
     *
     * The plugin is a singleton so don't allow cloning.
     *
     * @access private
     * @since 1.0.0
     * @return  void
     */
    final private function __clone() {}


    /**
     * Switches out the content that is in between the redact shortcode with
     * underscores and spaces for users whom are not allowed to see the redacted
     * text. Each "redact" shortcode will call this function when the posting is
     * rendered by wp. We add underscores and spaces as placeholders for the
     * words that would have been displayed and we surround the redaction with
     * an HTML span tag so that we may apply the redacted style to it. The
     * style is defined in the css/style.css file which is loaded during wp's
     * action hook for wp_enqueue_scripts.
     * 
     * @access public
     * @since 1.0.0
     * 
     * @param array $attr
     * @param string $content
     */
    public function redact_shortcode( $attr, $content = null )
    {
    	$supported = shortcode_atts( array(
    			'allow'		=> Options::Get('redact_roles'),
    			'style'		=> Options::Get('redact_style'),
    			'redactor'	=> 'unknown',
    			'date'		=> 'unspecified date'
    	), $attr, 'redact' );

    	// 1.3.0 - resolve user login correctly
    	// TODO: really need to switch to userid
    	if( false !== $user = get_user_by( 'login', $supported['redactor'] ) ) {
    		$supported['redactor'] = $user->display_name;
    	}
        
    	return $this->redact( $supported['allow'], $content, array( 
    		'who' => $supported['redactor'], 
    		'when' => $supported['date'], 
    		'style' => $supported['style'] ) );
    }
    
    /**
     * Render to shortcode to prevent a part of the content from being redacted.
     * 
     * @access public
     * @since 1.5.0
     * 
     * @param array $attr
     * @param string $content
     */
    public function noredact_shortcode( $attr, $content = null )
    {
    	return "<noredact>{$content}</noredact>";
    }
    
    /**
     * Accepts a string to have content redacted. It uses RedactorModel to query the database for
     * matching redactions and based on permissions replaces the matching text with appropriate content.
     * Made to be used with the add_action or add_filter function in WordPress.
     *
     * Returns the string with the content replaced based on permissions
     *
     * @access  public
     * @param  	string $content
     * @since   1.0.0
     * @return  string
     */
    public function redact_comment_content( $content )
    {
    	global $post;
    	
    	// if options set to redact comments then do redaction

    	if( Options::Get( 'redact_comments' ) ) {
    		
    		$should_redact = apply_filters( 'wp_should_redact', null, $post );
    		
    		if( false !== $should_redact ) {
    		
    			$content = $this->redact_content( $content );
    		}
    	}
    
    	return $content;
    }
    
    /**
     * Render the shortcode tag with content redacted
     * 
     * @access public
     * 
     * @param string $output
     * @param string $tag
     * @param unknown $attr
     * @param unknown $m
     * 
     * @since 1.5.0
     * @return string
     */
    public function redact_shortcode_content( $output, $tag, $attr, $m )
    {
    	global $post;
    	
    	if( Options::Get( 'redact_shortcodes' ) ) {
    		
    		$should_redact = apply_filters( 'wp_should_redact', null, $post );
    		
    		if( false !== $should_redact ) {
    			
    			$output = $this->redact_content( $output );
    		}
    	}
    	
    	return $output;
    }
    
    /**
     * Accepts a string to have content redacted. It uses RedactorModel to query the database for
     * matching redactions and based on permissions replaces the matching text with appropriate content.
     * Made to be used with the add_action or add_filter function in WordPress.
     *
     * Retruns the string with the content replaced based on permissions
     *
     * @access	public
     * @since   1.0.0
     * 
     * @param string $content The unredacted content
     * @param array $options Options related to the redaction
     * @return  string
     */
    public function redact_post_content( $content, $options = array() )
    {
    	global $post;
    	
    	$should_redact = apply_filters( 'wp_should_redact', null, $post );

    	if( false !== $should_redact ) {
    		
    		$content = $this->redact_content( $content, $options );
    	}
    	return $content;
    }
    
    /**
     * Redact the post tile based on the redaction rules
     * 
     * @access public
     * @since 1.4.0
     * 
     * @param string $title
     * @return string
     */
    public function redact_post_title( $title )
    {
    	global $post;
    	
    	// don't redact if in wp-admin
    	if( is_admin() ) return $title;
    	
    	if( Options::Get( 'redact_titles' ) ) {
    	
	    	$should_redact = apply_filters( 'wp_should_redact', null, $post );
	    	
	    	if( false !== $should_redact ) {
	    	
	    		$title = $this->redact_content( $title );
	    	}
    	}
    	
    	return $title;
    }
    
    /**
     * Accepts a string to have content redacted. It uses RedactorModel to query the database for
     * matching redactions and based on permissions replaces the matching text with appropriate content.
     * Made to be used with the add_action or add_filter function in WordPress.
     *
     * @access  public
     * @since   1.0.0
     * 
     * @param string $content The unredacted content
     * @param array $options Options related to the redaction
     * @return  string The string with the content replaced based on permissions
     */
    public function redact_content( $content, $options = array() )
    {
    	// don't redact content if in wp-admin
    	if( is_admin() ) return $content;
    	
    	$redacted_content = null; 
    	
    	// remove any content in shortcodes and tags
    	$stripped_content = strip_shortcodes( strip_tags( $content) );

    	// get any matching rules
    	$rules = $this->get_redactions( $stripped_content );
    	 
    	// TODO: detect conflicting regex expressions
    	// We were making a determination on least/most restrictive
    	// but that doesn't really mean anything. Maybe we need to 
    	// add a field that indicates the rule priority
    	
    	$redacted_content = $content;
    	
    	// apply each of the rules
    	foreach( $rules as $rule ) {
    		
    		$hash = md5( $redacted_content );

    		$pattern = $rule->rx_redaction;
    		
    		$regex = $this->build_regex( $pattern );
    		
    		$matched_strings = null;
    		
    		if( false !== $ret = preg_match_all( $regex , $content, $matched_strings ) ) {
    			
    			$matches = array_unique( $matched_strings[0] );
    			
    			// sort the matches by the longest first
    			usort($matches,function ($a,$b) {
    				return strlen($b)-strlen($a);
    			} );
    			
    			foreach( $matches as $match ) {
    				
    				if( trim( strlen( $match ) ) == 0 ) continue;
    				
    				$replacement = $this->redact( $rule->str_groups, $match, array(
    					'who' => $rule->str_username,
    					'when' => $rule->dt_added
    				) );
    				
    				$regex = new Regex();
    				$regex->raw( WP_REDACTOR_REGEX_NOT_REDACTED );
    				$regex->raw( WP_REDACTOR_REGEX_NOT_IN_TAG );
    				$regex->then( $match );
    				$regex = sprintf("/%s/", $regex);
    				
    				
    				$redacted_content = preg_replace( $regex, $replacement, $redacted_content );
    				
    				$changed = ( $hash == md5( $redacted_content ) );
    			}
    		} else {
    			
    			// something went wrong
    		}    		
    	}
    	
    	return $redacted_content;
    }
    
    /**
     * Returns the content if the current user is assigned a role that
     * is allowed to read the content, otherwise return a string with
     * the same number of characters as the content but contains only
     * underscores and spaces.
     *
     * @access public
     * @since 1.0.0
     *
     * @param array $allowed_roles An array of allowed roles that will 
     * be compared to the current user's roles
     * @param string $content The content to redact or display.
     * @param $meta Meta data related to the redaction 
     */
    public function redact( $allowed_roles, $content = null, $meta = array() )
    {
    	$style = 'redacted allowed';
    	$meta_defaults = array(
    		'who' => '',
    		'when' => '',
    		'style' => Options::Get('redact_style')
    	);
    	$meta = array_merge( $meta_defaults, $meta );
    	
    	// if no content passed in return
    	if( strlen( trim( $content ) )  && empty( $content ) )  return '';
    
    	// determine if the user is allowed to see readactions
    	// administrators and editors can by default
    	$allowed = current_user_can( 'administrator' )
    		|| current_user_can( 'editor' );    	
    	$allowed = apply_filters( 'wp_redactor_allowed_user', $allowed );
    		
    	if( ! $allowed ) {
    		// expand the allowed roles if they are a serialized array or split
    		// a comma separated list (not preferable)
    		if( false === $allowedRoles = @maybe_unserialize( $allowed_roles ) ) {
    
    			$allowed_roles = explode( ',', $allowed_roles );
    		} elseif( ! is_array( $allowed_roles ) ) {
    
    			$allowed_roles = array( $allowed_roles );
    		}
    		$allowed_roles = apply_filters( 'wp_redactor_allowed_roles', $allowed_roles );
    			
    		// determine if user is allowed based on roles
    		foreach ( $allowed_roles as $allowed_roles ) {
    
    			$allowed |= current_user_can( $allowed_roles );
    			if( $allowed ) {
    				break;
    			}
    		}
    	}
    	
    	return apply_filters( "wp_redactor_render_{$meta['style']}", $allowed, $content , $meta );
    }
    
    /**
     * Render the redaction as a solid block
     * 
     * @param bool $allowed
     * @param string $content
     * @param array $meta
     */
    public function render_solid( $allowed, $content, $meta = array() ) 
    {
    	$attr = array();
    	$class = array( 'redacted' );
    	
    	// if the user isn't allowed to see the content then redact it
    	if( ! $allowed ) {

    		$class[] = 'restricted';
    		
    		$class[] = 'redact-solid';
    		
    		// set the title attr if tooltips are on for everyone
    		if( 'all' == Options::Get('redact_tooltips') ) {
    			$attr['title'] = sprintf(
    					"title='Redacted by %s on %s'",
    					$meta['who'], $meta['when'] );
    			    			
    			$class[] = 'tooltip';
    		}
    		
    		// set the color if default defined
    		if( '' !== $color = Options::Get('redact_color') ) {
    		
    			$attr['style'] = sprintf(
    					"style='color:{$color};background-color:{$color}'",
    					$color );
    		}
    		
    		$content = $this->convert_to_redact_strings( $content );
    	} else {
    		
    		// set the title attr if tooltips are not turned off
    		if( 'none' != Options::Get('redact_tooltips') ) {
    			$attr['title'] = sprintf(
    					"title='Redacted by %s on %s'",
    					$meta['who'], $meta['when'] );
    			
    			$class[] = 'tooltip';
    		}
    		
    		$class[] = 'allowed';
		}
    	
    	$attr['class'] = sprintf( "class='%s'", implode(' ', $class) );
    	
    	return sprintf( "<redact %s>%s</redact>", implode(' ', $attr), $content );
    }

    /**
     * Render the output HTML with no content for users
     * 
     * @param boolean $allowed
     * @param string $content
     * @param array $meta
     * @return string
     */
    public function render_hidden( $allowed, $content, $meta = array() )
    {
    	$attr = array();
    	$class = array( 'redacted' );
    	
    	// if the user isn't allowed to see the content then redact it
    	if( ! $allowed ) {

    		$class[] = 'redact-hidden';
    		
    		$content = '';
    	} else {
    		
    		// set the title attr if tooltips are not turned off
    		if( 'none' != Options::Get('redact_tooltips') ) {
    			$attr['title'] = sprintf(
    					"title='Redacted by %s on %s'",
    					$meta['who'], $meta['when'] );
    			
    			$class[] = 'tooltip';
    		}
    		
    		$class[] = 'allowed';
    	}
    	
    	$attr['class'] = sprintf( "class='%s'", implode(' ', $class) );
    	
    	return sprintf( "<redact %s>%s</redact>", implode(' ', $attr), $content );
    }
    
    /**
     * Render the output text with the default string
     * 
     * @param boolean $allowed
     * @param string $content
     * @param array $meta
     * @return string
     */
    public function render_alttext( $allowed, $content, $meta = array() )
    {
    	$attr = array();
    	$class = array( 'redaction' );
    	
    	// if the user isn't allowed to see the content then redact it
    	if( ! $allowed ) {

    		$alttext = trim(Options::Get('redact_alttext')) . ' ';
    		
    		// get the lengths of the strings
    		$len = strlen($content);
    		$altlen = strlen($alttext);
    		
    		// create new string the same lenth as the old string
    		$alttext = str_repeat( $alttext, round( $len/$altlen ) +1 );
    		$alttext = trim( substr( $alttext, 0, $len ) ); 
    		
    	    // set the color if default defined
    		if( '' !== $color = Options::Get('redact_color') ) {
    		
    			$attr['style'] = sprintf(
    					"style='color:{$color};border-color:{$color}'",
    					$color );
    		}
    		
    		
    		$class[] = 'redacted';
    		$class[] = 'alttext';
    		
    		$content = $alttext;
    	} else {
    		
    		// set the title attr if tooltips are not turned off
    		if( 'none' != Options::Get('redact_tooltips') ) {
    			$attr['title'] = sprintf(
    					"title='Redacted by %s on %s'",
    					$meta['who'], $meta['when'] );
    			
    			$class[] = 'tooltip';
    		}
    		
    		$class[] = 'allowed';
    	}
    	
    	$attr['class'] = sprintf( "class='%s'", implode(' ', $class) );
    	
    	return sprintf( "<redact %s>%s</redact>", implode(' ', $attr), $content );
    }
    
    /**
     * Render the content as spoiler text. The user will be able to see
     * the content by hovering or clicking on the blurred text.
     * 
     * @param boolean $allowed
     * @param string $content
     * @param array $meta
     * return string
     */
    public function render_spoiler( $allowed, $content, $meta = array() )
    {
    	$attr = array();
    	$class = array( 'redacted' );
    	
    	// if the user isn't allowed to see the content then redact it
    	if( ! $allowed ) {

    		$class[] = 'spoiler';
    	} else {
    		
    		// set the title attr if tooltips are not turned off
    		if( 'none' != Options::Get('redact_tooltips') ) {
    			$attr['title'] = sprintf(
    					"title='Redacted by %s on %s'",
    					$meta['who'], $meta['when'] );
    			
    			$class[] = 'tooltip';
    		}
    		
    		$class[] = 'allowed';
    	}
    	
    	$attr['class'] = sprintf( "class='%s'", implode(' ', $class) );
    	
    	return sprintf( "<redact %s>%s</redact>", implode(' ', $attr), $content );
    }
    
    /**
     * Accepts an array of strings and translates them into what should be used as the redacted text. This returns
     * redacted versions of the content no matter what the permissions are.
     *
     * @access  public
     * @since   1.0.0
     * 
     * @param  array $matches

     * @return array $arrRedacted The array of strings translated into what should be used as redacted text.
     */
    public function convert_to_redact_strings( $matches )
    {
    	if( is_string( $matches ) ) {
    		
    		return preg_replace("/[^\s]/", "&#9608;", $matches);
    	}
    
    	if( ! is_array( $matches ) ) {
    		
    		$matches =  array(''.$matches.'');
    	}
    
    	$redacted = array();
    
    	foreach( $matches as $match ) {
    		
    		$redacted[] = preg_replace( "/[^\s]/", "&#9608;", $match );
    	}
    
    	return $redacted;
    }
    
    /**
     * Get an array of the redactions that match this content
     *
     * @access public
     * @since 1.3.0
     *
     * @param string $content
     * @return array
     */
    public function get_redactions( $content )
    {
    	$matches = array();
    	
    	// remove the tags before getting the associated redaction rules
    	$rules = Model::get_instance()->getRedactRulesEx();
    	 
    	// check each of the rule to see if they apply
    	foreach( $rules as $rule ) {
    		
    		$count = $this->check_pattern( $rule->rx_redaction, $content );
    		
    		if( $count ) {
    			
    			$rule->post_count = $count;
    			$matches[$rule->id] = $rule;
    		}
    	}
        
    	return $matches;
    }
    
    /**
     * Compare a pattern against the content
     * 
     * @param string $pattern
     * @param string $content
     * @return mixed
     */
    public function check_pattern( $pattern, $content )
    {
    	
    	$regex = $this->build_regex( $pattern );
    	
    	@preg_match_all( $regex, $content, $out, PREG_PATTERN_ORDER);

    	// return false if no count or integer
    	return ( empty ( $out[0] ) ) ? false : count( $out[0] );
    }
    
    /**
     * Build regex from pattern supplied
     *  
     * https://github.com/thomascgray/NooNooFluentRegex
     *      
     * @param unknown $pattern
     * 
     * @return Regex
     */
    public function build_regex( $pattern ) 
    {
    	$pattern = apply_filters( 'wp_pre_build_regex', $pattern );
    	
    	$regex = new Regex();
    	
    	$regex->raw( WP_REDACTOR_REGEX_NOT_IN_TAG );    	
    	
    	// convert mask to a regex
    	if( 0 === strpos( $pattern, '#' ) ) {
    		
    		$is_literal = false;
    		
    		for( $i = 1; $i < strlen( $pattern ); $i++ ) {
    			$char = substr( $pattern, $i, 1 );
    			
    			// if the previus character was a \ treat the following
    			// character as a literal
    			//if( $is_literal ) {
    			//	$regex->then( $char );
    			//	$is_literal = false;
    			//	continue;
    			//}
    			
    			// translate as mask
    			switch( $char ) {
    				case '0':
    					$regex->digit();
    					break;
    				case '9':
    					$regex->optional()->digit();
    					break;
    				case 'A':
    					$regex->alpha();
    					break;
    				case 'B':
    					$regex->uppercase()->alpha();
    					break;
    				case 'b':
    					$regex->lowercase()->alpha();
    					break;
    				case 'S':
    					$regex->alphanumeric();
    					break;
    				case 'T':
    					$regex->uppercase()->alphanumeric();
    					break;
    				case 't':
    					$regex->lowercase()->alphanumeric();
    					break;
    				case 'T':
    					$regex->optional()->uppercase()->alphanumeric();
    					break;
    				case 't':
    					$regex->optional()->lowercase()->alphanumeric();
    					break;
    				case 'Y':
    					$regex->optional()->uppercase()->alpha();
						break;
    				case 'y':
    					$regex->optional()->lowercase()->alpha();
    					break;
    				case 'Z':
    					$regex->optional()->alpha();
    					break;
    				//case '+':
    				//	$regex->oneOrMore();
    				//	break;
    				//case '*':
    				//	$regex->zeroOrMore();
    				//	break;
    				//case '?':
    				//	$regex->optional();
    				//	break;
    				//case '\\':
					//	$is_literal = true;    					
    				//	break;
    				default:
    					$regex->then($char);
    			}
    		}
    	
    		$regex = "/{$regex}/";
    	// conver to predefined patterns
    	} elseif( 0 === strpos( $pattern, '/' ) ) {
    		
			$name = strtoupper( substr( $pattern, 1 ) );
    		
			if( defined('REGEX_' . $name) ) {
    				 
				$regex->raw( constant( 'REGEX_' . $name ) );
				
				$regex = "/{$regex}/";
    		}
    	} else {
    		
    		$regex->then( $pattern );
    		
    		$regex = "/{$regex}/i";
    	}

    	return apply_filters( 'wp_post_build_regex', $regex );
    }
    
    /** 
     * Adds a box to the main column on the Post and Page edit screens 
     * 
     * 
     */
    function metrics_meta_box() 
    {
    	add_meta_box(
    			'wp_redactor_metrics',
    			__( 'Redactions', 'wp_redactor_textdomain' ),
    			array( $this, 'metrics_meta_custom_box' ),
    			'post', 'side'
    			);
    }
    
    /**
     *  Prints the box content */
    function metrics_meta_custom_box( $post ) 
    {
    	$model = Model::get_instance();

    	$metrics = Metrics::get_instance()->get_post_metrics( $post );
    	
    	$total = array_sum( $metrics['redactions'] );
    	
    	echo "<div><strong>Redactions ({$total})</strong></div>";
    	print "<ul>";
    	foreach($metrics['redactions'] as $key=>$val) {
    		
    		if(is_numeric( $key ) ) {
    			$redaction = $model->getRule($key);
    			print "<li>{$redaction['str_description']} <span>({$val})</span></li>";
    		} else {
    			
    			print "<li>Shortcodes <span>({$val})</span></li>";
    		}
    	}
    	print "</ul>";
    	
    	echo '<div><strong>Users</strong></div>';
    	print "<ul>";
    	foreach($metrics['users'] as $key=>$val) {
    		
    		if( false !== $user_info = get_userdata( $key )) {
    			print "<li>{$user_info->display_name} <span>({$val})</span></li>";
    		}
    	}
    	print "</ul>";
    	
    }
}