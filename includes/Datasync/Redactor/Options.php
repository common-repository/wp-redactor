<?php
/**
 * The plugin options
 */
namespace Datasync\Redactor;

if( ! defined( 'WP_REDACTOR' ) ) exit;

/**
 * Database interaction methods
 *
 * Methods used to create, update and delete redaction patterns.
 *
 * Example usage:
 * Model::get_instance();
 *
 * @package  	Redactor
 * @author   	Joseph M. King <michael.king@datasynctech.com>
 * @author   	Thane Durey <tdurey@harding.edu>
 * @copyright   2016 DataSync Technologies
 * @license		GPLv2 or later
 * @access   	public
 * @since    	1.0.0
 */
class Options{

    /**
     * The singleton instance of the class
     * @access private
     * @since 1.0.0
     * @var object
     */
    private static $instance = null;
    
    /**
     * An object of redactor options
     * @since 1.0.0
     * @access private
     * @var object
     */
    private $options;
    
    /**
     * The array of redaction styles
     * @since 1.4.0
     * @access public
     * @var array
     */
    public static $styles = array( 
    	'solid' => 'Solid Color',
    	'hidden' => 'Hidden',
    	'alttext' => 'Alternate Text',
    	'spoiler' => 'Spoiler'
    );
    
    /**
     * The array of options for tooltips
     * @since 1.4.0
     * @access public
     * @var array
     */
    public static $tooltips = array(
    	'all' => 'All users',
    	'redactors' => 'Redactors only',
    	'none' => 'None'
    );
    
    /**
     * The array of default option values and data types
     * 
     * @var array Defaults for options and their types for verification 
     */
    private $defaults = array(
        'redact_wholeword' => array('type' => 'bool', 'value' => 0),
        'redact_posts'     => array('type' => 'bool', 'value' => 1),
    	'redact_titles'    => array('type' => 'bool', 'value' => 0),
        'redact_comments'  => array('type' => 'bool', 'value' => 0),
    	'redact_shortcodes'=> array('type' => 'bool', 'value' => 0),
    	'redact_roles'     => array('type' => 'array', 'value' => array()),	
    	'redact_categories'=> array('type' => 'array', 'value' => array()),
    	'redact_tags'	   => array('type' => 'array', 'value' => array()),
    	'redact_posttypes' => array('type' => 'array', 'value' => array()),
    	'redact_style'     => array('type' => 'string', 'value' => 'solid'),
    	'redact_color'     => array('type' => 'string', 'value' => '#000000'),
    	'redact_alttext'   => array('type' => 'string', 'value' => 'REDACTED'),
    	'redact_tooltips'  => array('type' => 'string', 'value' => 'redactors')
    );

    /**
     * Gets a singleton of this plugin
     *
     * Retrieves or creates the plugin singleton.
     *
     * @static
     * @access public
     * @since 1.0.0
     * @return plugin singleton
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
     * @since 1.4.0
     * @access public
     */
    public static function initialize()
    {
    	$plugin = new self();
    	
    	if(is_admin()){
    		add_action( 'admin_init', array( $plugin, 'register_settings' ) );
    		add_action( 'admin_menu', array( $plugin, 'add_plugin_options_page' ) );    		
      	}
      	
      	add_filter( 'wp_should_redact', array( $plugin, 'check_redact_categories' ), 10, 2 );
      	add_filter( 'wp_should_redact', array( $plugin, 'check_redact_tags' ), 10, 2 );
      	add_filter( 'wp_should_redact', array( $plugin, 'check_redact_posttypes' ), 10, 2 );
    }
    
    /**
     * Satic methond to get a redactor setting
     * 
     * @access public
     * @since 1.4.0
     * 
     * @param string $name
     * @param mixed $default
     */
    public static function Get( $name, $default = null )
    {
    	return Options::get_instance()->get_option( $name, $default );
    }
    
    /**
    * A helper function to return the redactor_options for classes to use.
    *
    * @access public
    * @since 1.0.0
    * @return array redactor options
    */
    public function get_options()
    {    	
        return $this->sanitize_with_defaults( get_option( 'redactor_options' ) );
        
    }

    /**
    * Sanitizes user input when parsing option uploads
    * based on defaults and option types.
    *
    * @access public
    * @since 1.0.0
    * 
    * @param $options Array of options to sanitize
    * @return array Sanitized options
    */
    public function sanitize_with_defaults( $options )
    {
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
                    case 'array':
                    	$clean_options[$option_name] = is_array($options[$option_name])
                    		? $options[$option_name]
                    		: array( $options[$option_name] );
                    	break;
                    default:
                    	$clean_options[$option_name] = $options[$option_name];
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
    
    /**
     * Returns the option value from the array or the default value if one is not set.
     * Returns false if invalid setting is specified
     * 
     * @access public
     * @since 1.4.0
     * 
     * @param string $name
     * @return mixed
     */
    public function get_option( $name )
    {
    	$value = false;
    	
    	$this->options = $this->get_options();

    	if( ! isset( $this->options[$name] ) ) {    		
    		$value = $this->defaults[$name]['value'];
    	} else {
    		$value = $this->options[$name];
    	}
    	
    	$styles = apply_filters( 'wp_redactor_get_option', $name, $value );
    	
    	return $value;
    }

    /**
     * Registers the options with wordpress and tells the system how it needs to be rendered.
     *
     * @access public
     * @since 1.0.0
     * @return void
     */
    public function register_settings()
    {
    	// register setting to store in wp_options table
    	register_setting(
            'redactor_options_group', // Option group
            'redactor_options',        // Option name
            array( $this, 'sanitize_with_defaults' ) // Sanitize
        );

    	// 
        add_settings_section(
            'redaction_rule_settings', "", null,
            WP_REDACTOR_PRIVATE_NAME . '_settings_page'
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
        
        // setting fields should follow the following format
        /*
         	add_settings_field(
 				<id>,
 				<label>
 				<render-callback>
 				<settings-page>
 				<setting-section>       
        	);
       	*/

        add_settings_field(
        	'redact_titles',
        	'Titles<p class="helptext"><i>Select if redaction rules should
        	be applied to titles.</i>',
        	array( $this, 'render_redact_titles' ),
        	WP_REDACTOR_PRIVATE_NAME . '_settings_page',
        	'redaction_rule_settings'
        );
        
        add_settings_field(
            'redact_comments',
            'Comments<p class="helptext"><i>Select if redaction rules should 
        		be applied to comments.</i>',
            array( $this, 'render_redact_comments' ),
            WP_REDACTOR_PRIVATE_NAME . '_settings_page',
            'redaction_rule_settings'
        );

        add_settings_field(
        		'redact_shortcodes',
        		'Shortcodes<p class="helptext"><i>Select if redaction rules should
        		be applied to shortcode conents.</i>',
        		array( $this, 'render_redact_shortcodes' ),
        		WP_REDACTOR_PRIVATE_NAME . '_settings_page',
        		'redaction_rule_settings'
        		);
        
        add_settings_field(
        		'redact_roles',
        		'Allowed Roles<p class="helptext"><i>Select the user roles
        		that will see redacted content by default. If none are selected, 
        		redaction rule settings will be applied. Administrators and 
        		editors can always see redacted content.</i>',
        		array( $this, 'render_redact_roles' ),
        		WP_REDACTOR_PRIVATE_NAME . '_settings_page',
        		'redaction_rule_settings'
        		);
        
        add_settings_field(
        	'redact_categories',
        	'Categories<p class="helptext"><i>Select the categories that 
        		redaction rules apply. If none are selected, redaction rules 
        		will be applied to all categories.</i>',
        	array( $this, 'render_redact_categories' ),
        	WP_REDACTOR_PRIVATE_NAME . '_settings_page',
        	'redaction_rule_settings'
        );
        	
        add_settings_field(
        	'redact_tags',
        	'Tags<p class="helptext"><i>Select the tags that 
        		redaction rules apply. If none are selected, redaction rules 
        		will be applied to all tags.</i></p>',
        	array( $this, 'render_redact_tags' ),
        	WP_REDACTOR_PRIVATE_NAME . '_settings_page',
        	'redaction_rule_settings'
        );
        	
        add_settings_field(
        	'redact_posttype',
        	'Post types<p class="helptext"><i>Select the post types that 
        		redaction rules apply. If none are selected, redaction rules 
        		will be applied to all post types.</i></p>',
        	array( $this, 'render_redact_posttypes' ),
        	WP_REDACTOR_PRIVATE_NAME . '_settings_page',
        	'redaction_rule_settings'
        );
        	
        add_settings_section(
        	'redaction_style_settings', "Style",
        	function() { print "<p>Default styles are applied for all redaction
        		rules and where no specific style is specified for individual 
        		redactions.</p>"; },
        	WP_REDACTOR_PRIVATE_NAME . '_settings_page' // Page
        );
        
        add_settings_field(
        	'redact_default_style',
        	'Default style<p class="helptext"><i>Select the default redaction 
        		style. Individual redactions can override the default.</i></p>',
        	array( $this, 'render_redact_style' ),
        	WP_REDACTOR_PRIVATE_NAME . '_settings_page',
        	'redaction_style_settings'
        );
        
        add_settings_field(
        	'redact_default_color_style',
        	'Default color<p class="helptext"><i>Specify the default color for 
        		redactions.</i></p>',
        	array( $this, 'render_redact_color' ),
        	WP_REDACTOR_PRIVATE_NAME . '_settings_page',
        	'redaction_style_settings'
        );
        
        add_settings_field(
        	'redact_default_alttext_style',
        	'Alternate text<p class="helptext"><i>Specify alternate text to 
        		display in place of the redacted text.<i></p>',
        	array( $this, 'render_redact_alttext' ),
        	WP_REDACTOR_PRIVATE_NAME . '_settings_page',
        	'redaction_style_settings'
        );

        add_settings_field(
        	'redact_default_show_tooltips',
        	'Show tooltip<p class="helptext"><i>Specify which users see the
        		tooltip with redaction metadata.<i></p>',
        	array( $this, 'render_redact_tooltips' ),
        	WP_REDACTOR_PRIVATE_NAME . '_settings_page',
        	'redaction_style_settings'
        );
    }
    
    /**
     * Options page callback. Echos the rendering of the options page.
     *
     * @access public
     * @sinces 1.0.0
     * @return void
     */
    public function create_admin_page()
    {
        $this->options = $this->get_options();
        ?>
        <div class="wrap">
            <h2>Redactor Settings</h2>
            <form method="post" class="redactor_options_group" action="options.php">
            <?php
                // This prints out all hidden setting fields
                settings_fields( 'redactor_options_group' );
                do_settings_sections( WP_REDACTOR_PRIVATE_NAME . '_settings_page' );
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
     * @since 1.0.0
     * @return void
     */
    public function add_plugin_options_page()
    {
    	add_submenu_page( 'wp-redactor', 'Settings', 'Settings', 'manage_options',
    			'wp-redactor-settings', array( $this, 'create_admin_page' ) );
    }

    /**
     * Prints the HTML string to render the options.
     * 
     * @access public
     * @since 1.0.0
     */
    public function render_redact_wholeword()
    {
        $selected = $this->get_option('redact_wholeword');

        printf(
            "<input type='checkbox' name='redactor_options[redact_wholeword]' value='1' %s ></input>",
            checked(1, $selected, false)
        );
    }

    /**
     * Prints the HTML string to render the post options.
     * 
     * @access public 
     * @since 1.0.0
     */
    public function render_redact_posts()
    {
        $selected = $this->get_option('redact_posts');

        printf(
            "<input type='checkbox' name='redactor_options[redact_posts]' value='1' %s ></input>",
            checked(1, $selected, false)
        );
    }

    /**
     * Prints the HTML string to render the title options
     *
     * @access public
     * @since 1.4.0
     */
    public function render_redact_titles()
    {
    	$selected = $this->get_option('redact_titles');
    
    	printf(
    			"<input type='checkbox' name='redactor_options[redact_titles]' value='1' %s ></input>",
    			checked(1, $selected, false)
    			);
    }
    
    /**
     * Prints the HTML string to render the comment options
     * 
     * @access public
     * @since 1.0.0
     */
    public function render_redact_comments()
    {
        $selected = $this->get_option('redact_comments');
        
        printf(
            "<input type='checkbox' name='redactor_options[redact_comments]' value='1' %s ></input>",
            checked(1, $selected, false)
        );
    }

    /**
     * Prints the HTML string to render the shortcoee options
     *
     * @access public
     * @since 1.5.0
     */
    public function render_redact_shortcodes()
    {
    	$selected = $this->get_option('redact_shortcodes');
    
    	printf(
    			"<input type='checkbox' name='redactor_options[redact_shortcodes]' value='1' %s ></input>",
    			checked(1, $selected, false)
    			);
    }
    /**
     * Prints the HTML string to render the redact_categories option
     *
     * @access public
     * @since 1.4.0
     */
    public function render_redact_roles()
    {
    	$selected = $this->get_option('redact_roles');
    	 
    	$role_options = array();
    	$roles = apply_filters( 'wp_redactor_redact_roles_options', get_editable_roles() );
    	unset($roles['administrator']);
    	unset($roles['editor']);
    	
    	foreach( $roles as $key=>$role ) {    		
    		$checked = in_array($key, $selected) ? 'selected=selected' : '';
    		$role_options[] = "<option {$checked}
    		value=\"{$key}\">" . esc_html( $role['name'] ) . "</option>";
    	}
    	 
    	$role_options = implode("\n", $role_options);
    	printf(
    			"<select name='redactor_options[redact_roles][]' multiple>%s</select>",
    			$role_options
    			);
    }    
    
    /**
     * Prints the HTML string to render the redact_categories option
     * 
     * @access public
     * @since 1.4.0
     */
    public function render_redact_categories()
    {
    	$selected = $this->get_option('redact_categories');    	
    	
    	$category_options = array();
    	$categories = apply_filters( 'wp_redactor_redact_categories_options', get_categories() );
    	foreach( $categories as $category ) {
    		$checked = in_array($category->term_id, $selected) ? 'selected=selected' : '';
    		$category_options[] = "<option {$checked}
    			value=\"{$category->term_id}\">" . esc_html( $category->name ) . "</option>";
    	}
    	
    	$category_options = implode("\n", $category_options);
    	printf(
    		"<select name='redactor_options[redact_categories][]' multiple>%s</select>",
    		$category_options
    	);
    }
    
    /**
     * Prints the HTML string to render the redact_tags option
     *
     * @access public
     * @since 1.4.0
     */
    public function render_redact_tags()
    {
    	$selected = $this->get_option('redact_tags');    	
    	 
    	$tag_options = array();
    	$categories = apply_filters( 'wp_redactor_redact_tags_options', get_tags() );    	 
    	foreach( get_tags() as $tag ) {
    		$checked = in_array($tag->term_id, $selected) ? 'selected=selected' : '';
    		$tag_options[] = "<option {$checked}
    		value=\"{$tag->term_id}\">" . esc_html( $tag->name ) . "</option>";
    	}

    	$tag_options = implode("\n", $tag_options);
    	printf(
    		"<select name='redactor_options[redact_tags][]' multiple>%s</select>",
    		$tag_options
    	); 
    }
    
    /**
     * Prints the HTML string to render the redact_posttypes option
     *
     * @access public
     * @since 1.4.0
     */
    public function render_redact_posttypes()
    {
    	$selected = $this->get_option('redact_posttypes');
    	 
    	$posttype_options = array();
    	$posttypes = apply_filters( 'wp_redactor_redact_posttypes_options', get_post_types(null, 'objects') );
    	foreach( $posttypes as $key=>$posttype ) {
    		$checked = in_array($key, $selected) ? 'selected=selected' : '';
    		$posttype_options[] = "<option {$checked}
    		value=\"{$key}\">".esc_html( $posttype->label )."</option>";
    	}
    	
    	$posttype_options = implode("\n", $posttype_options);
    	printf(
    		"<select name='redactor_options[redact_posttypes][]' multiple>%s</select>",
    		$posttype_options
    	);
    }
    
    /**
     * Prints the HTML string to render the redact_style option
     *
     * @access public
     * @since 1.4.0
     */
    public function render_redact_style()
    {
    	$selected = $this->get_option('redact_style');
    	
    	$style_options = array();
    	$styles = apply_filters( 'wp_redactor_redact_style_options', self::$styles );
    	foreach( $styles as $key=>$val ) {
    		$checked = ($key == $selected) ? 'selected=selected' : '';
    		$style_options[] = "<option {$checked}
    			value=\"{$key}\">" . esc_html($val) . "</option>";
    	}
    	$style_options = implode("\n", $style_options);
    	
    	printf(
    		"<select name='redactor_options[redact_style]'>%s</select>",
    		$style_options
    	);
    }
    
    /**
     * Printes the HTML string to render the redact_color selector
     * 
     * @access public
     * @since 1.4.0
     */
    public function render_redact_color()
    {
    	$selected = $this->get_option('redact_color');
    	
    	print "<input type=\"text\" value=\"". esc_html( $selected ). "\"
           	name='redactor_options[redact_color]'
    		class=\"redactor-color-selector\"
    		data-default-color=\"#000000\" />";
    }
    
    /**
     * Prints the HTML string to render the redact_text selector
     * 
     * @access public
     * @since 1.4.0
     */
    public function render_redact_alttext()
    {
    	$selected = $this->get_option('redact_alttext');
    	 
    	print "<input type=\"text\" 
    		name='redactor_options[redact_alttext]'
    		value=\"". esc_html($selected). "\">";
    }

    /**
     * Prints the HTML string to render the redact_style option
     *
     * @access public
     * @since 1.4.0
     */
    public function render_redact_tooltips()
    {
    	$selected = $this->get_option('redact_tooltips');
    	 
    	$tooltip_options = array();
    	$tooltips = apply_filters( 'wp_redactor_redact_tooltips_options', self::$tooltips );
    	foreach( $tooltips as $key=>$val ) {
    		$checked = ($key == $selected) ? 'selected=selected' : '';
    		$tooltip_options[] = "<option {$checked}
    		value=\"{$key}\">" . esc_html($val) . "</option>";
    	}
    	$tooltip_options = implode("\n", $tooltip_options);
    	 
    	printf(
    		"<select name='redactor_options[redact_tooltips]'>%s</select>",
    		$tooltip_options
    	);
    }
    
	/**
	 * Check if the post should be redacted based on selected categories
	 * 
	 * Function should only return true if the filter positively identifies
	 * a condition where the content should be redacted
	 * 
	 * @access public
	 * @since 1.4.0
	 * 
     * @param bool $redact
     * @return bool
	 */
    public function check_redact_categories( $redact )
    {
    	global $post;
    	
    	$categories = Options::Get('redact_categories');
    	
    	// if the user didn't specify anything or another filter already
    	// returned true then just return input value
    	if( $redact || empty( $categories ) ) return $redact;
    	
    	// check each category and see if this post has that category
    	foreach( $categories as $category ) {
    	
    		if( in_category( $category, $post ) ) return true;
    	}
    	
    	// return false or whatever was passed in
    	return false || $redact;
    }

    /**
     * Check if the post should be redacted based on selected tags
     *
	 * Function should only return true if the filter positively identifies
	 * a condition where the content should be redacted
	 *
	 * @access public
	 * @since 1.4.0
	 * 
     * @param bool $redact
     * @return bool
     */
    public function check_redact_tags( $redact )
    {
    	global $post;
    	
    	$tags = Options::Get('redact_tags');
    	
    	// if the user didn't specify anything or another filter already
    	// returned true then just return input value
    	if( $redact || empty( $tags ) ) return $redact;
    	 
    	// check each tag and see if this post has that tag
    	foreach( $tags as $tag ) {
    		
    		if( has_tag( $tag, $post ) ) return true;	
    	}
    	
    	// return false or whatever was passed in
    	return false || $redact;
    }
    
    /**
     * Check if the post should be redacted based on selected posttyles
     *
     * Function should only return true if the filter positively identifies
	 * a condition where the content should be redacted
	 *
	 * @access public
	 * @since 1.4.0
	 * 
     * @param bool $redact
     * @return bool
     */
    public function check_redact_posttypes( $redact )
    {
    	global $post;
    	
    	$posttypes = Options::Get('redact_posttypes');
    	
    	// if the user didn't specify anything or another filter already
    	// returned true then just return input value
    	if( $redact || empty( $posttypes ) ) return $redact;
    	
    	// check if this post type is in the selected array
    	if( in_array( $post->post_type, $posttypes ) ) return true;
    	
    	// return false or whatever was passed in
    	return false || $redact;
    }
}
