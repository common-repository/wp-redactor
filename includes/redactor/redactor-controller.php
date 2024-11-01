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



require_once( __DIR__.'/../utils/functions.php' );
require_once( __DIR__.'/../Redaction.php' );
require_once( 'redactor-model.php' );
require_once( 'redactor-view.php' );
require_once( 'redactor-listview.php' );
require_once( 'options/redactor-options.php' );

class RedactorController{

    /**
    * Plugin singleton
    * @var object
    * @access private
    * @since 0.0.1
    */
    private static $_instance = null;

    /**
    * Plugin version
    * @var string
    * @access private
    * @since 0.0.1
    */
    private $_version;

    /**
    * Plugin's file path
    * @var string
    * @access private
    * @since 0.0.1
    */
    private $_file;

    /**
    * Plugin directory
    * @var string
    * @access private
    * @since 0.0.1
    */
    private $_dir;

    /**
    * Plugin assets web path
    * @var string
    * @access private
    * @since 0.0.1
    */
    private $_assets_url;

    /**
    * Plugin assets file path
    * @var string
    * @access private
    * @since 0.0.1
    */
    private $_assets_dir;

    /**
    * Plugin languages path
    * @var string
    * @access private
    * @since 0.0.1
    */
    private $_languages_dir;

    private $_className;

    private $_model;

    private $_view;

    private $_optionsPage;

    private $_options;

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
    public static function get_instance ( $file = '', $version = '0.0.1' ) {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self( $file, $version );
        }
        return self::$_instance;
    }

   /**
     * Create and initializes the plugin
     *
     * The plugin is a singleton so the constructor remains private
     *
     * @access private
     * @since 0.0.1
     * @return  void
     */
    final private function __construct( $file = '', $version = '0.0.1' ) {
        $this->_className = 'redactorController';
        $this->_model = RedactorModel::get_instance( $file, $version );
        $this->_view  = RedactorView::get_instance(  $file, $version );

        $this->_optionsPage = RedactorOptions::get_instance();
        $this->_options = RedactorOptions::get_instance()->get_options();

        //sets all of the plugin urls so it can reach out to other places
        $this->_createURLs( $file );

        //sets the plugin version for backwards compat checks
        $this->_version = $version;

        //initializes the languages for the plugin
        $this->init_i18n_textdomain();
        add_action( 'init', array( $this, 'init_languages' ), 0 );

        // load the scripts and styles required to function
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        add_action( 'wp_enqueue_scripts',    array( $this, 'enqueue_scripts' ) );

        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles'  ) );
        add_action( 'wp_enqueue_scripts',    array( $this, 'enqueue_styles' ) );


        add_action('wp_ajax_get_roles',         'ajax_getRoles' );
        add_action('wp_ajax_get_username_date', 'ajax_getCurrentUserNameAndDate' );

        add_action('admin_init', array( $this, 'adminInit'       ));
        add_shortcode('redact',  array( $this, 'redactShortcode' ));

        //add_filter( 'the_content' , array($this, 'redact_string_content') );
        add_filter( 'comment_text',     array( $this, 'redact_comment_content') );
        add_filter( 'comment_text_rss', array( $this, 'redact_comment_content') );
        
        add_filter( 'the_content',     array( $this, 'redact_post_content') );

        register_activation_hook( $this->_file, array( 'RedactorModel', 'install_database' ) );
    }


   /**
     * Registers the plugin's styles with WordPress.
     *
     * @access public
     * @since 0.0.1
     * @return void
     */
    public function enqueue_styles(){
    	wp_enqueue_style( 'style',             $this->_assets_url . 'css/style.css'         );
    	wp_enqueue_style( 'tooltipster-style', $this->_assets_url . 'css/tooltipster.css' );
    }


   /**
     * Registers the plugin's admin styles with WordPress.
     *
     * @access public
     * @since 0.0.1
     * @return void
     */
    public function enqueue_admin_styles( $hook = '' ){
    	wp_enqueue_style( 'style',             $this->_assets_url . 'css/style.css'         );
    	wp_enqueue_style( 'tooltipster-style', $this->_assets_url . 'css/tooltipster.css'   );
                        
        wp_enqueue_style (  'wp-jquery-ui-dialog');
    }


   /**
     * Registers the plugin's scripts with WordPress.
     *
     * @access public
     * @since 0.0.1
     * @return void
     */
    public function enqueue_scripts(){
    	wp_enqueue_script('jquery');
    	wp_enqueue_script('tooltipster',        $this->_assets_url . 'js/jquery.tooltipster.min.js', array('jquery') );
    	
    	wp_enqueue_script('wp-redactor-script', $this->_assets_url . 'js/wp-redactor.js',            array('jquery','tooltipster') );
    }


   /**
     * Registers the plugin's admin scripts with WordPress.
     *
     * @access public
     * @since 0.0.1
     * @return void
     */
    public function enqueue_admin_scripts( $hook = '' ){
    	wp_enqueue_script('jquery');
    	wp_enqueue_script(
                'tooltipster',          
                $this->_assets_url . 'js/jquery.tooltipster.min.js', 
                array('jquery') );
        wp_enqueue_script(
                'momentjs',  
                $this->_assets_url . 'js/moment.js');
    	wp_enqueue_script(
                'wp-redactor-script',   
                $this->_assets_url . 'js/wp-redactor.js',            
                array('jquery', 'underscore') );
    } 

   /**
     * Helper function on whether SCRIPT_DEBUG is set
     *
     * Returns whether or not SCRIPT_DEBUG is set.
     *
     * @static
     * @since 0.0.1
     * @return  boolean
     */
    static function is_script_debug(){
        return defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;
    }


   /**
     * Helper function on whether WP_DEBUG is set
     *
     * Returns whether or not WP_DEBUG is set.
     *
     * @static
     * @since 0.0.1
     * @return  boolean
     */
    static function is_wordpress_debug(){
        return defined( 'WP_DEBUG' ) && WP_DEBUG;
    }

   /**
     * Create and stores all of the paths that the plugin will use to access the world
     *
     * @access private
     * @since 0.0.1
     * @return  void
     */
    private function _createURLs( $file ) {
        $this->_file        = $file;
        $this->_dir         = dirname($file);
        $this->_assets_dir  = trailingslashit( $this->_dir ) ;
        $this->_assets_url  = esc_url(
            trailingslashit(
                plugins_url( '', $this->_file )
            )
        );

        $this->_languages_dir = trailingslashit( $this->_dir ) . 'languages';
    }

   /**
     * Initializes the textdomain for the plugin
     *
     * @access public
     * @since 0.0.1
     * @return  void
     */
    public function init_i18n_textdomain () {
    	    $lang = apply_filters( 'plugin_locale', get_locale(), datasync_PLUGIN_TEXTDOMAIN );

    	    load_textdomain( datasync_PLUGIN_TEXTDOMAIN, WP_LANG_DIR . '/' . datasync_PLUGIN_TEXTDOMAIN . '/' . datasync_PLUGIN_TEXTDOMAIN . '-' . $lang . '.mo' );
    	    load_plugin_textdomain( datasync_PLUGIN_TEXTDOMAIN, false, $this->_languages_dir );
    	}

    /**
     * Initialize lanaguages
     * @access  public
     * @since   0.0.1
     * @return  void
     */
    public function init_languages () {
        load_plugin_textdomain( datasync_PLUGIN_PRIVATE_NAME, false, $this->_languages_dir );
    }


   /**
     * Not allowed
     *
     * The plugin is a singleton so don't allow cloning.
     *
     * @access private
     * @since 0.0.1
     * @return  void
     */
    final private function __clone() {}

    /**
     * A callback function during the mce_buttons filter hook that will
     * add our redact button name to the array of buttons in the text editor.
     * @param array $buttons An array of button IDs to be rendered in the text editor HTML.
     */
    public function addRedactButton ( $buttons ) {
        array_push($buttons, 'redact');
        return $buttons;
    }

    /**
     * A callback function during the mce_external_plugins filter hook that
     * will assign our javascript to be executed by TinyMCE when it renders
     * itself. Our javascript will add the redact button to the editor.
     * @param array $plugins A map of TinyMCE plugin names to their implementations in a javascript.
     */
    public function addCustomMcePlugin( $plugins ) {
        $plugins['redactor'] = $this->_assets_url . "js/redactorButton.js";
        return $plugins;
    }


    /**
     * This function will execute on the admin_init action hook and will
     * provide the current user with the redact button if the current user
     * has the edit post and edit page capability. The redact button is
     * added by supplying callbacks to the text editor filter hooks.
     */
    public function adminInit() {
    	if ( is_admin() ) {
    		if (current_user_can ( "edit_posts" ) && current_user_can ( "edit_pages" )) {

    			add_filter ( 'mce_external_plugins', array( $this, 'addCustomMcePlugin' ));
    			add_filter ( 'mce_buttons',          array( $this, 'addRedactButton'    ));

    		}

    		add_editor_style ( $this->_assets_url . 'css/style.css');
    	}

    }
    


    /**
     * Switches out the content that is in between the redact shortcode with
     * underscores and spaces for users whom are not allowed to see the redacted
     * text. Each "redact" shortcode will call this function when the posting is
     * rendered by wp. We add underscores and spaces as placeholders for the
     * words that would have been displayed and we surround the redaction with
     * an HTML span tag so that we may apply the redacted style to it. The
     * style is defined in the css/style.css file which is loaded during wp's
     * action hook for wp_enqueue_scripts.
     * @param array $attr Any attributes provided in the shortcode, we look for 'allow'
     * @param string $content The original text in between the shortcode
     */
    public function redactShortcode($attr, $content = null) {

        $supported = shortcode_atts(array(
                'allow'    => 'administrator',
                'redactor' => 'unknown',
                'date'     => 'unspecified date'
        ), $attr, 'redact');

        return $this->redact($supported['allow'], $supported['redactor'], $supported['date'], $content);
    }
    
    
    /**
     * Translates the raw rows of the database and string content into a redaction array of matching text
     * and the permissions around the redaction. The array is in the form of array(matches[], permissions, redacted[], pattern)
     * foreach matching row of the database.
     *
     * @access  public
     * @param  array  $dbMatchingRows Raw rows from the database
     * @param  string $strContent     String content to match against.
     * @since   0.0.1
     * @return  array $arrResults An array of results  in the form of array(matches[], permissions[] , redacted[]) foreach matching row of the database.
     */
    private function _getMatchingStringsAndPermissions($dbMatchingRows, $strContent){
        $arrPatterns    = datasync_pluckAttribute('rx_redaction', $dbMatchingRows);
        $arrDateAdded   = datasync_pluckAttribute('dt_added',     $dbMatchingRows);
        $arrUsername    = datasync_pluckAttribute('str_username', $dbMatchingRows);
        $arrPermissions = datasync_pluckAttribute('str_groups',   $dbMatchingRows);
        $arrResults     = array();

        for($i = 0; $i < count($arrPatterns); $i++ ){
            $pattern     = $arrPatterns[$i];
            $permissions = $arrPermissions[$i];
            $dateAdded   = $arrDateAdded[$i];
            $username    = $arrUsername[$i];

            if( $this->_options['redact_wholeword'] ){
                preg_match_all("/[^\s]*".$pattern."[^\s]*/i", $strContent, $out, PREG_PATTERN_ORDER);
            }else{
                preg_match_all("/".$pattern."/i", $strContent, $out, PREG_PATTERN_ORDER);
            }

            $arrRedactions = $this->convertToRedactStrings($out[0]);

            for($j = 0; $j < count($out[0]); $j++){
                $arrResults[] = array(
                    'matches'     => $out[0][$j],
                    'permissions' => $permissions,
                    'redacted'    => $arrRedactions[$j],
                    'username'    => $username,
                    'pattern'     => $pattern,
                    'dateAdded'   => $dateAdded
                );
            }

        }
        
        
        return $this->_dedupeMatches($arrResults);
    }
    
    

    /**
     * Accepts an array of strings and translates them into what should be used as the redacted text. This returns
     * redacted versions of the content no matter what the permissions are.
     *
     * @access  public
     * @param  array $arrMatches  The array of strings that are content that matches redacted text.
     * @since   0.0.1
     * @return array $arrRedacted The array of strings translated into what should be used as redacted text.
     */
    public function convertToRedactStrings($arrMatches){
        if(is_string($arrMatches)){
            return preg_replace("/[^\s]/", "&#9608;", $arrMatches);
        }

        if(!is_array($arrMatches)){
            $arrMatches =  array(''.$arrMatches.'');
        }

        $arrRedacted = array();

        foreach($arrMatches as $strMatch){
            $arrRedacted[] = preg_replace("/[^\s]/", "&#9608;", $strMatch);
        }

        return $arrRedacted;
    }

    /**
     * Iterates through the list of matches and removes duplicate matches altering
     * the permissions of the unique match to be most restrictive of the removed
     * duplicates.
     * @access private
     * @param array $arrMatches
     * @since 0.0.1
     * @return array
     */
    private function _dedupeMatches($arrMatches){
        if(!is_array($arrMatches) || count($arrMatches) <= 1){
            return $arrMatches;
        }
        
        usort($arrMatches, "datasync_redactResultSort");
        
        $arrDeduped = array();
        $arrDeduped[] = $arrMatches[0];
        $dupedIndex = 0;
        for($i = 1; $i < count($arrMatches); $i++){  
            if(strcasecmp ($arrDeduped[$dupedIndex]['matches'], $arrMatches[$i]['matches']) == 0){
                $arrDeduped[$dupedIndex]['permissions'] = datasync_getMostRestrictive(
                        $arrDeduped[$dupedIndex]['permissions'], 
                        $arrMatches[$i]['permissions']);
            }else{
                $arrDeduped[] = $arrMatches[$i];
                $dupedIndex++;
            }
        }
        
        unset($arrMatches);
        
        return $arrDeduped;
    }



	/**
	 * Returns the content if the current user is assigned a role that
	 * is allowed to read the content, otherwise return a string with
	 * the same number of characters as the content but contains only
	 * underscores and spaces.
         * @since 0.0.1
         * @access public
	 * @param array $csv A comma separated list of allowed roles that will be compared to the current user's roles
	 * @param string $content The content to redact or display.
	 */
	public function redact($allowedRoles, $who = "unknown", $when = "unspecified date", $content = null) {
		if ($content == null || strlen(trim($content)) == 0) {
			return "";
		}
		$allowed = current_user_can ('administrator') | current_user_can ('editor');
		
		
		if( false === $allowedRoles = @maybe_unserialize( $allowedRoles ) ) {
			$allowedRoles = explode( ',', $allowedRoles );
		} elseif( !is_array( $allowedRoles ) ) {
			$allowedRoles = array( $allowedRoles );
		}
		//die(var_dump($allowedRoles));
		
		foreach ($allowedRoles as $allowedRole) {
			$allowed |= current_user_can ($allowedRole);
			if ($allowed) {
				break;
			}
		}
		$str   = "";
		$style = 'redacted';
		if ($allowed) {
			$style = 'allowed';
			$str   = $content;
		}else{
            $str = $this->_model->convertToRedactStrings($content);
		}
		return $this->_view->redactStringToHTML($style, $who, $when, $str);
	}

    /**
     * Accepts a string to have content redacted. It uses RedactorModel to query the database for
     * matching redactions and based on permissions replaces the matching text with appropriate content.
     * Made to be used with the add_action or add_filter function in WordPress.
     *
     * @access  public
     * $param  string $post_content
     * @since   0.0.1
     * @return  $strResult The string with the content replaced based on permissions
     */
    public function redact_string_content( $strContent ){
        $arrRedactRules = $this->_model->getRedactRules(strip_tags($strContent));
        
        //$arrRedactedContent = $this->_getMatchingStringsAndPermissions($arrRedactRules, strip_tags(strip_shortcodes($strContent)));
        $arrRedactedContent = $this->_getMatchingStringsAndPermissions($arrRedactRules, strip_shortcodes(strip_tags($strContent)));

        $strResult = $strContent;
        for($i = 0; $i < count($arrRedactedContent); $i++){
            $redactedStrings = array();
            $escapedContent = datasync_escapeRegexString($arrRedactedContent[$i]['matches']);
            
            $strResult = preg_replace(
                '/'.datasync_REGEX_NOT_IN_HTML_TAG.$escapedContent.'/',
                $this->redact(
                    $arrRedactedContent[$i]['permissions'],
                    $arrRedactedContent[$i]['username'],
                    $arrRedactedContent[$i]['dateAdded'],
                    $arrRedactedContent[$i]['matches']
                ),
                $strResult);
        }

        return  $strResult ;
    }

    /**
     * Accepts a string to have content redacted. It uses RedactorModel to query the database for
     * matching redactions and based on permissions replaces the matching text with appropriate content.
     * Made to be used with the add_action or add_filter function in WordPress.
     *
     * @access  public
     * $param  string $post_content
     * @since   0.0.1
     * @return  $strResult The string with the content replaced based on permissions
     */
    public function redact_comment_content( $strContent ){
        $options = RedactorOptions::get_instance();
        if($options->get_options()['redact_comments']){
            return $this->redact_string_content( $strContent );
        }
        
        return $strContent;
    }

    /**
     * Accepts a string to have content redacted. It uses RedactorModel to query the database for
     * matching redactions and based on permissions replaces the matching text with appropriate content.
     * Made to be used with the add_action or add_filter function in WordPress.
     *
     * @access  public
     * $param  string $post_content
     * @since   0.0.1
     * @return  $strResult The string with the content replaced based on permissions
     */
    public function redact_post_content( $strContent ){
        //$options = RedactorOptions::get_instance();
        //if($options->get_options()['redact_posts']){
            //return $this->redact_string_content( strip_shortcodes($strContent) );            
            return $this->redact_string_content( $strContent );            
        //}
        
        //return $strContent;
        
    }
};
