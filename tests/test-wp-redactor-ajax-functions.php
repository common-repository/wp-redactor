<?php


require_once( __DIR__.'/../includes/utils/constants.php' );
require_once __DIR__ . "/../includes/redactor/redactor-controller.php";
require_once __DIR__ . "/../includes/redactor/redactor-ajax-functions.php";

class RedactorAjaxFunctionsTest extends WP_Ajax_UnitTestCase {
    
    private $numrows = 0;
    
    public function __construct(){
        RedactorModel::install_database();
    }
    public function setUp() {
        parent::setUp();
        $this->author_id = $this->factory->user->create( array( 'role' => 'user' ) );
        $this->old_current_user = get_current_user_id();
        wp_set_current_user( $this->author_id );
        
        global $wpdb;
        $table_name      = $wpdb->prefix . datasync_DATABASE_REDACT_TABLENAME;

        
        $rowcount = $wpdb->query("truncate table $table_name");
        
        
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
        
        

        $this->numrows = count($rows);
        $rowcount = $wpdb->get_results("select count(1) as rowcount from $table_name");
        if($rowcount && count($rowcount) > 0 && $rowcount[0]->rowcount == "0"){
            foreach($rows as $row){
                $wpdb->insert($table_name, $row, array( '%s' ));
            }
        } 
        
  
    }
    
    
    function tearDown() {
            wp_set_current_user( $this->old_current_user );
            parent::tearDown();
    }

    function testAjaxGetRoles(){
        
        
        try {
            $this->_handleAjax( 'get_roles' );
        } catch ( WPAjaxDieContinueException $e ) {
            // We expected this, do nothing.
        }  
                
        // Check that the exception was thrown.
        $this->assertTrue( isset( $e ) );
        
        $response = json_decode( $this->_last_response );
        $this->assertTrue( !is_null($response) , "Response is null" );
        $this->assertTrue( is_object($response) , "Response is not an object" );
        
    }
    

    function testAjaxCurrentUserNameAndDate(){
        
        
        try {
            $this->_handleAjax( 'get_username_date' );
        } catch ( WPAjaxDieContinueException $e ) {
            // We expected this, do nothing.
        }  
                
        // Check that the exception was thrown.
        $this->assertTrue( isset( $e ) );
        
        $response = json_decode( $this->_last_response );
        $this->assertTrue( !is_null($response)  , "Response is null");
        $this->assertTrue( is_object($response), "Response is not an object" );
        $this->assertTrue( is_string($response->name), "Response->name is not a string" );
        
    }
    

    function testAjaxUpdateRules(){
                
        $this->_setRole( 'administrator' );
        
 
        add_action( 'wp_ajax_updateRules', 'ajax_updateRules' );   
        
       
        $_POST['id'] = "1";
        $_POST['str_username'] = 'user';
        $_POST['str_groups']   = 'administrators';
        $_POST['rx_redaction'] = 'updatedThroughAjax';
        
        try {
            $this->_handleAjax( 'updateRules' );
        } catch ( WPAjaxDieContinueException $e ) {
            // We expected this, do nothing.
        }  
                 
        // Check that the exception was thrown.
        $this->assertTrue( isset( $e ) );
        
        
        $response = json_decode( $this->_last_response );
        $this->assertTrue( !is_null($response)  , "Response is null");
        $this->assertTrue(is_object($response), "Response is not an object" );
    }
    
    
}