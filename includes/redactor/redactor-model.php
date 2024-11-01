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
require_once( __DIR__.'/../utils/constants.php' );

class RedactorModel{

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
        $this->_className = 'redactorModel';

        //sets all of the plugin urls so it can reach out to other places
        $this->_createURLs( $file );

        //sets the plugin version for backwards compat checks
        $this->_version = $version;

        //$this->_options = RedactorOptions::get_instance()->get_options();
    }



   /**
     * Helper function on whether SCRIPT_DEBUG is set
     *
     * Returns whether or not SCRIPT_DEBUG is set or not
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
     * Create and initializes paths for the plugin
     *
     * Stores all of the paths that the plugin will use to access the world
     *
     * @access private
     * @since 0.0.1
     * @return  void
     */
    private function _createURLs( $file ) {
        $this->_file        = $file;
        $this->_dir         = dirname($file);
        $this->_assets_dir  = trailingslashit( $this->_dir );
        $this->_assets_url  = esc_url(
            trailingslashit(
                plugins_url( '', $this->_file )
            )
        );

        $this->_languages_dir = trailingslashit( $this->_dir ) . 'languages';
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
     * Creates the database table that contains the redact rules and patterns.
     *
     * @static
     * @access  public
     * @since   0.0.1
     * @return  void
     */
    static function install_database(){
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name      = $wpdb->prefix . datasync_DATABASE_REDACT_TABLENAME;

        $sql =
            "CREATE TABLE {$table_name} (                                                 \n".
            "id int(11) NOT NULL AUTO_INCREMENT, ".
            "dt_added timestamp NOT NULL,".
            "rx_redaction varchar(500) NOT NULL, ".
            "str_username varchar(50) NOT NULL , ".
            "str_groups   varchar(500) NOT NULL, ".
            "PRIMARY KEY (id) ".
            ") $charset_collate; ";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );

        //if wordpress is in debug mode then insert some default redaction values for testing.
        /*
        if(RedactorModel::is_wordpress_debug()){
            $rows[] = array(
                'rx_redaction' => 'RedactTest',
                 'str_username' => 'user',
                 'str_groups' => 'administrator,editor'
            );
            $rows[] = array(
                'rx_redaction' => 'RedactorTest',
                 'str_username' => 'user',
                 'str_groups' => 'administrator,editor'
            );

            $rowcount = $wpdb->get_results("select count(1) as rowcount from $table_name");
            if($rowcount && count($rowcount) > 0 && $rowcount[0]->rowcount == 0){
                foreach($rows as $row){
                    $wpdb->insert($table_name, $row, array( '%s' ));
                }
            }
            
        }*/

        add_option( 'wordactor_version', datasync_PHP_MINIMUM_VERSION );
    }



    /**
     * Queries the database for matching redactions and returns the raw row object array.
     *
     * @access  public
     * @param  string $strContent
     * @since   0.0.1
     * @return  array $rows Raw records of matching redactions.
     */
    private function _getMatchingRedactsFromDatabase($strContent){
         global $wpdb;

         $table_name = $wpdb->prefix . datasync_DATABASE_REDACT_TABLENAME;
         $sql = $wpdb->prepare(
             "select * from $table_name where %s RLIKE `rx_redaction`",
             $strContent
         );

         $rows = $wpdb->get_results( $sql );
         return $rows;
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
            $arrRedacted[] = preg_replace("/[^\s]/", datasync_FULL_BLOCK, $strMatch);
        }

        return $arrRedacted;
    }


    /**
     * Accepts a string to have content redacted. Queries the database for
     * matching redactions and returns a multidimensional array of content,
     * redacted content, pattern, and permissions. Each row in the array was
     * a matching redaction rule in the database.
     *
     * @access  public
     * $param  string $strContent The content to match against.
     * @since   0.0.1
     * @return array  $arrMatches The matches array for filtering content.
     */
    public function getRedactRules($strContent){
        $rows = $this->_getMatchingRedactsFromDatabase( $strContent );       
        
        return $rows;
    }


    /**
     * Returns the total number of rule records in the database.
     * @since 0.0.1
     * @access public
     * @global type $wpdb
     * @return int
     */
    public function getRuleRowCount( $search = null ){
        global $wpdb;
        $table_name = $wpdb->prefix . datasync_DATABASE_REDACT_TABLENAME;

        if( empty( $search ) ) {
        	$countresults = $wpdb->get_results( " SELECT count(*) AS rowcount FROM $table_name " );
        } else {
        	$sql = $sql = $wpdb->prepare( 
        		"SELECT count(*) AS rowcount FROM $table_name WHERE rx_redaction LIKE %s", 
        		$search.'%' );
        	$countresults = $wpdb->get_results( $sql );
        }
        $rowcount = $countresults[0]->rowcount;

        return $rowcount;
    }

    /**
     * Returns the raw database object returned from querying rules from the database.
     * @global type $wpdb
     * @param int $offset
     * @param int $limit
     * @return object
     */
    public function getRawRuleRecords($offset = 0, $limit = 50){
        global $wpdb;
        $table_name = $wpdb->prefix . datasync_DATABASE_REDACT_TABLENAME;
        
        //parameter check
        if(is_null($offset) || !is_int($offset)) $offset = 0;
        if(is_null($limit)  || !is_int($limit))  $limit  = 25;
        
        $offset = ($offset >= 0 )? $offset : 0;
        $limit  = ($limit > 0   )? $limit  : 50;

        $sql = $wpdb->prepare(
             " SELECT id, dt_added, rx_redaction, str_username, str_groups FROM $table_name LIMIT %d, %d ",
             $offset,
             $limit
         );
        $results = $wpdb->get_results( $sql );
        return $results;
    }

    /**
     * Get rules with any possible options
     *
     * @param Array $options
     * @return Object
     */
    public function getRawRuleRecordsEx( $options = array() )
    {
        global $wpdb;

        $default_options = array(
            'search'  => null,
            'offset'  => 0,
            'limit'   => 100,
            'orderby' => 'id',
            'order'   => 'ASC'
        );
        
        $table_name = $wpdb->prefix . datasync_DATABASE_REDACT_TABLENAME;
        $allow_orderby = array('id', 'rx_redaction', 'str_username', 'dt_added');
        $allow_order = array( 'ASC', 'DESC' );

        $sql = null;

        // set input defaults and extract to local variables
        extract( array_merge( $default_options, $options ) );
        
        // check bounds for input variables
        $offset = ( is_numeric( $offset ) && $offset >=0 )
            ? $offset
            : $default_options['offset'];
            
        $limit = ( is_numeric( $limit ) && $limit >= 0  && $limit <= 100 )
            ? $limit
            : $default_options['limit'];

        $orderby = ( in_array( $orderby, $allow_orderby ) )
            ? $orderby
            : $default_options['orderby'];

        $order = ( in_array( $order, $allow_order ) )
            ? $order
            : $default_options['order'];
       
        $search = $search;

        $sql_start = "SELECT * FROM {$table_name}";
        $sql_end = "ORDER BY {$orderby} {$order} LIMIT %d, %d";

        if( ! empty( $search ) ) {
            $search = trim( $search ) . '%';
            $sql = "{$sql_start} WHERE rx_redaction LIKE %s {$sql_end}";
            $sql = $wpdb->prepare( $sql, $search, $offset, $limit );
        } else {
            $sql = "{$sql_start} {$sql_end}";
            $sql = $wpdb->prepare( $sql, $offset, $limit );
        }

        $results = $wpdb->get_results( $sql );
	
        return $results;
    }
    
    /**
     * Test if a rule exists
     * 
     * If an id is passed in we exclude that record from the test
     * 
     * @param string $pattern
     * @params int $id
     * 
     * @return bool
     */
    function hasRule( $pattern, $id = null )
    {
    	global $wpdb;
    	
    	$table_name = $wpdb->prefix . datasync_DATABASE_REDACT_TABLENAME;
    	
    	if( is_numeric( $id ) ) {
    		
    		$sql = $wpdb->prepare( "SELECT id FROM $table_name WHERE rx_redaction=%s AND id != %d", $pattern, $id );
    	} else {
    	
    		$sql = $wpdb->prepare( "SELECT id FROM $table_name WHERE rx_redaction=%s", $pattern );
    	}
    	
    	return $wpdb->get_row( $sql, ARRAY_A );
    }
   
    /**
     * Get a rule by the id
     *
     * @param int $id
     * @return array
     */
    function getRule( $id )
    {
        global $wpdb;

        $table_name = $wpdb->prefix . datasync_DATABASE_REDACT_TABLENAME;

        $sql = $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $id);

        return $wpdb->get_row($sql, ARRAY_A ); 
    }
 
    /**
     * Adds a rule to the database. On success, it queries the resulting id and returns
     * the inserted row as a database object.
     * @global type $wpdb
     * @param string $rule
     * @param string $permissions
     * @param string $user
     * @return object The raw row created.
     */
    public function createRule($rule, $permissions, $user){
        global $wpdb;
        
        $results = array();
        
        if( is_null($rule) || !is_string($rule) )
            $rule = '';
        
        if( is_array($permissions) ) 
            $permissions = serialize( $permissions );

        if( is_null($permissions) )
            $permissions = '';
        
        if( is_null($user) || !is_string($user) )
            $user = '';
        
        //prepare the data
        $data = array(
            'rx_redaction' => $rule,
            'str_username' => $user,
            'str_groups'   => $permissions,
            'dt_added'     => current_time('mysql', 1)
        );
        
        $format = array(
            '%s',
            '%s',
            '%s',
            '%s'
        );
        
        //set the tablename and insert the data
        $table_name = $wpdb->prefix . datasync_DATABASE_REDACT_TABLENAME;        
        $success = $wpdb->insert($table_name, $data, $format); 
        
        if($success){            
            $id = $wpdb->insert_id;  
             $sql = $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $id);
            $results = $wpdb->get_row($sql, ARRAY_A );
        }
        
        return $results;
    }
    
    /**
     * Updates a row in the rules table with the provided id and returns the changed row.
     * @global type $wpdb
     * @param int $id
     * @param string $rule
     * @param string $permissions
     * @param string $user
     * @return array
     */
    public function updateRule($id, $rule, $permissions, $user){
        global $wpdb;
        
        $results = array();    
        
        //translate a string to an integer
        if( is_string($id) ){
            $id = intval($id, 10);
        }
        
        //if not a valid id just return a blank array
       if(!is_integer($id) || $id <= 0)
            return $results;
       
        
        if( is_null($rule) || !is_string($rule) )
            $rule = '';

        if( is_array($permissions) )
            $permissions = serialize( $permissions ); 

        if( is_null($permissions) )
            $permissions = '';
        
        if( is_null($user) || !is_string($user) )
            $user = '';
        
        //prepare the data 
        $data = array(
            'rx_redaction' => $rule,
            'str_username' => $user,
            'str_groups'   => $permissions,
            'dt_added'     => current_time('mysql', 1)
        );
        
        $format = array(
            '%s',
            '%s',
            '%s',
            '%s'   
        );
        
        //replace where id = value
        $where = array(
            'id' => $id
        );
        
        $whereformat = array(
            '%d' 
        );
        
        //set the tablename and update the matching row
        $table_name = $wpdb->prefix . datasync_DATABASE_REDACT_TABLENAME;        
        $success = $wpdb->update($table_name, $data, $where, $format, $whereformat); 

        if($success){  
            $sql = $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $id);
            $results = $wpdb->get_row( $sql, ARRAY_A );
        }
        
        return $results;
    }
    
    /**
     * Deletes a rule from the database with the matching id.
     * @global type $wpdb
     * @param int $id
     * @return boolean
     */
    public function deleteRule( $id ){
        global $wpdb;
        
        //convert from string to id
        if( is_string($id) ){
            $id = intval($id, 10);
        } 
        
        //checks if id is an integer
        if( !is_integer($id) || $id <= 0){
            return false;
        }
        
        $data = array( 'id' => $id );        
        $table_name = $wpdb->prefix . datasync_DATABASE_REDACT_TABLENAME;
        
        return $wpdb->delete($table_name, $data, '%d');          
    }

    /**
     * Delete all ids in array
     *
     * @since 1.2.0
     * @access public
     *
     * @global type $wpdb
     * @param array $ids
     * @return boolean
     */ 
    public function bulkDeleteRules( $ids )
    {
        $ret = false;

        if( is_array( $ids ) ) {

            $ret = true;

            foreach( $ids as $id ) {

                $ret = $ret && $this->deleteRule( $id );
            }
        }

        return $ret;
    }

};
