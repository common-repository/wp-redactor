<?php
/**
 * The main database functions
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
class Model{

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
     * Queries the database for matching redactions and returns the raw row object array.
     *
     * @access public
     * @since 1.0.0
     * 
     * @param string $content
     * @return array $rows Raw records of matching redactions.
     */
    private function _getMatchingRedactsFromDatabase( $content )
    {
         global $wpdb;

         $table_name = $wpdb->prefix . WP_REDACTOR_TABLENAME;
         
         $sql = $wpdb->prepare(
             "select * from $table_name where %s RLIKE `rx_redaction`",
             $content
         );

         $rows = $wpdb->get_results( $sql );
         
         return $rows;
    }

    /**
     * Accepts an array of strings and translates them into what should be used as the redacted text. This returns
     * redacted versions of the content no matter what the permissions are.
     *
     * @access  public
     * @since 1.0.0
     * 
     * @param  array $arrMatches  The array of strings that are content that matches redacted text.
     * @return array $arrRedacted The array of strings translated into what should be used as redacted text.
     */
    public function convertToRedactStrings ($arrMatches )
    {
        if(is_string($arrMatches)){
            return preg_replace("/[^\s]/", "&#9608;", $arrMatches);
        }

        if(!is_array($arrMatches)){
            $arrMatches =  array(''.$arrMatches.'');
        }

        $arrRedacted = array();

        foreach( $arrMatches as $strMatch ) {
        	
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
     * @since   1.0.0
     * 
     * @param string $content The content to match against.
     * @return array The matches array for filtering content.
     */
    public function getRedactRules( $content )
    {
        $rows = $this->_getMatchingRedactsFromDatabase( $content );       
        
        return $rows;
    }
    
    /**
     * Gets the redaction rules and return the database rows
     * 
     * @access public
     * @since 1.2.0
     * 
     * @param array $ids
     * @return array
     */
    public function getRedactRulesEx( $ids = array() )
    {
    	global $wpdb;
    	
    	$table_name = $wpdb->prefix . WP_REDACTOR_TABLENAME;
    	
    	if( ! empty( $id ) ) {
    		
    		$ids = is_array( $ids ) ? implode( ',', $ids ) : array ( $ids );
    		
    		$sql = "SELECT * FROM {$table_name} WHERE id IN ( {$ids} )";
    	} else {
    		
    		$sql = "SELECT * FROM {$table_name}";
    	}
    	
    	$rows = $wpdb->get_results( $sql );
    	
    	return $rows; 
    }

    /**
     * Returns the total number of rule records in the database.
     * 
     * @access public
     * @since 1.0.0
     *  
     * @param string $search
     * @return int
     */
    public function getRuleRowCount( $search = null )
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . WP_REDACTOR_TABLENAME;

        if( empty( $search ) ) {
        	
        	$countresults = $wpdb->get_results( " SELECT count(*) AS rowcount FROM $table_name " );
        } else {
        	
        	$sql = $sql = $wpdb->prepare( 
        		"SELECT count(*) AS rowcount FROM $table_name WHERE rx_redaction LIKE %s OR str_description LIKE %s", 
        		$search.'%', $search.'%' );
        	$countresults = $wpdb->get_results( $sql );
        }
        $rowcount = $countresults[0]->rowcount;

        return $rowcount;
    }

    /**
     * Returns the raw database object returned from querying rules from the 
     * database.
     * 
     * @access public
     * @since 1.0.0
     * 
     * @param int $offset
     * @param int $limit
     * @return object
     */
    public function getRawRuleRecords( $offset = 0, $limit = 50 )
    {
        global $wpdb;
        $table_name = $wpdb->prefix . WP_REDACTOR_TABLENAME;
        
        //parameter check
        if(is_null($offset) || !is_int($offset)) $offset = 0;
        if(is_null($limit)  || !is_int($limit))  $limit  = 25;
        
        $offset = ($offset >= 0 )? $offset : 0;
        $limit  = ($limit > 0   )? $limit  : 50;

        $sql = $wpdb->prepare(
             " SELECT id, str_description, dt_added, rx_redaction, str_username, str_groups FROM $table_name LIMIT %d, %d ",
             $offset,
             $limit
         );
        $results = $wpdb->get_results( $sql );
        return $results;
    }

    /**
     * Get rules with any possible options
     *
     * @access public
     * @since 1.0.0
     * 
     * @param array $options
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
        
        $table_name = $wpdb->prefix . WP_REDACTOR_TABLENAME;
        $allow_orderby = array('id', 'rx_redaction', 'str_username', 'dt_added', 'str_description');
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
            $sql = "{$sql_start} WHERE rx_redaction LIKE %s OR str_description LIKE %s {$sql_end}";
            $sql = $wpdb->prepare( $sql, $search, $search, $offset, $limit );
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
     * @access private
     * @since 1.0.0
     *
     * @param string $pattern
     * @param int $id
     * @return bool
     */
    function hasRule( $pattern, $id = null )
    {
    	global $wpdb;
    	
    	$table_name = $wpdb->prefix . WP_REDACTOR_TABLENAME;
    	
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
     * @access private
     * @since 1.0.0
     * 
     * @param int $id
     * @return array
     */
    function getRule( $id )
    {
        global $wpdb;

        $table_name = $wpdb->prefix . WP_REDACTOR_TABLENAME;

        $sql = $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $id);

        return $wpdb->get_row($sql, ARRAY_A ); 
    }
 
    /**
     * Adds a rule to the database. On success, it queries the resulting id 
     * and returns the inserted row as a database object.
     * 
     * @access private
     * @since 1.0.0
     * 
     * @param string $rule
     * @param string $permissions
     * @param string $user
     * @param string $description
     * @return object The raw row created.
     */
    public function createRule( $rule, $permissions, $user, $description=null )
    {
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
        
        if( is_null($description) )
        	$description = $rule;
        
        //prepare the data
        $data = array(
        	'str_description' => $description,
            'rx_redaction' => $rule,
            'str_username' => $user,
            'str_groups'   => $permissions,
            'dt_added'     => current_time('mysql', 1)
        );
        
        $format = array(
        	'%s',
            '%s',
            '%s',
            '%s',
            '%s'
        );
        
        //set the tablename and insert the data
        $table_name = $wpdb->prefix . WP_REDACTOR_TABLENAME;        
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
     * 
     * @access public
     * @since 1.0.0
     * 
     * @param int $id
     * @param string $rule
     * @param string $permissions
     * @param string $description
     * @param string $user
     * @return array
     */
    public function updateRule( $id, $rule, $permissions, $user, $description = null )
    {
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
        
        if( is_null($description) )
        	$description = $rule;
        
        //prepare the data 
        $data = array(
        	'str_description' => $description,
            'rx_redaction' => $rule,
            'str_username' => $user,
            'str_groups'   => $permissions,
            'dt_added'     => current_time('mysql', 1)
        );
        
        $format = array(
        	'%s',
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
        $table_name = $wpdb->prefix . WP_REDACTOR_TABLENAME;        
        $success = $wpdb->update($table_name, $data, $where, $format, $whereformat); 

        if($success){  
            $sql = $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $id);
            $results = $wpdb->get_row( $sql, ARRAY_A );
        }
        
        return $results;
    }
    
    /**
     * Deletes a rule from the database with the matching id.
     * 
     * @access public
     * @since 1.0.0
     * 
     * @param int $id
     * @return boolean
     */
    public function deleteRule( $id )
    {
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
        $table_name = $wpdb->prefix . WP_REDACTOR_TABLENAME;
        
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

    /**
     * Takes an array of objects and returns an array of $key from each object.
     *
     * @access public
     * @since 1.0.0
     * 
     * @param string $key
     * @param array $source
     * @return array
     */
    public static function Pluck( $key, $source )
    {
    	$plucked = array();
    	
    	foreach( $source as $row ) {
    		
    		if( array_key_exists( $key, $row ) ) {
    			$plucked[] = $row->$key;
    		}
    	}
    	unset( $source );
    
    	return $plucked;
    }
    
    /**
     * Takes a multi dimensional array and returns an array of $key from each row.
     * Similar to array_column in later versions of PHP
     *
     * @since public
     * @since 1.0.0
     * 
     * @param string $key
     * @param array $source
     * @return array
     */
    public static function PluckColumn( $key, $source )
    {
    	$plucked = array();
    
    	foreach( $source as $row ) {
    		
    		if ( array_key_exists( $key, $row ) ) {
    			
    			$plucked[] = $row[$key];
    		}
    	}
    
    	unset( $source );
    
    	return $plucked;
    }
};
