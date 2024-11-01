<?php


require_once( __DIR__.'/../includes/utils/constants.php' );
require_once __DIR__ . "/../includes/redactor/redactor-controller.php";

class RedactorControllerTest extends WP_UnitTestCase {
    
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

    public function testAddRedactButton() {
        $r = RedactorController::get_instance();
        $a = $r->addRedactButton( array() );
        
        $this->assertEquals("redact", $a[0]);
    }
    
    public function testRedactShortcode(){
        global $wpdb;
        $r = RedactorController::get_instance();
        $a = $r->redactShortcode( array( 'allow' => 'administrator' ), "RedactTest");
        
        $this->assertEquals(1, preg_match("/<span.+class='redacted'.+(&#9608;){10}.+\/span>/", $a));
    }
    
    public function testConvertToRedactStrings(){
        global $wpdb;
        $r = RedactorController::get_instance();
        $a = $r->convertToRedactStrings( "RedactTest" );
        $b = $r->convertToRedactStrings( "RedactorTest" );
        $c = $r->convertToRedactStrings( "redact" );
        
        $this->assertEquals(1, preg_match("/(&#9608;){10}/", $a));
        $this->assertEquals(1, preg_match("/(&#9608;){12}/", $b));
        $this->assertEquals(1, preg_match("/(&#9608;){6}/", $c)); 
    }
    
    public function testRedact(){
        global $wpdb;
        $r = RedactorController::get_instance();
        $a = $r->redact('administrator', 'testuser', '', 'redact');
         
        $this->assertEquals(1, preg_match("/<span.+class='redacted'.+(&#9608;){6}.+\/span>/", $a));
    }
    
    public function testAllowedRedact(){
    	$user_id = $this->factory->user->create(array( 'role' => 'editor' ));
    	wp_set_current_user( $user_id );
        
        global $wpdb;
        $r = RedactorController::get_instance();
        $a = $r->redact('editor', 'testuser', '', 'redact');
         
        $this->assertEquals(0, preg_match("/<span.+class='redacted'.+(&#9608;){6}.+\/span>/", $a));
    }
    
    public function testRedactStringContent(){
        global $wpdb;
        $r = RedactorController::get_instance();
        $a = $r->redact_string_content('RedactorTest');
        $b = $r->redact_string_content('redact');
         
        $this->assertEquals(1, preg_match("/(&#9608;){12}/", $a));
        $this->assertEquals(0, preg_match("/(&#9608;)+/", $b));         
    }
    
    public function testPrivilegedRedactStringContent(){
        global $wpdb;
        $this->author_id = $this->factory->user->create( array( 'role' => 'editor' ) );
        $this->old_current_user = get_current_user_id();
        wp_set_current_user( $this->author_id );
        
        $r = RedactorController::get_instance();
        $a = $r->redact_string_content('RedactorTest');
        $b = $r->redact_string_content('redact');
         
        $this->assertEquals(
                0, 
                preg_match("/(&#9608;){12}/", $a), 
                "The text should not contain redacted content for administrators or editors");
        $this->assertEquals(
                0, 
                preg_match("/(&#9608;)+/", $b),
                "The text should not contain redacted content for administrators or editors");    
    }
    
    public function testRedactPostContent(  ){
        $r = RedactorController::get_instance();       
        
        $o = get_option('redactor_options');
        $o['redact_posts'] = true;
        update_option('redactor_options', $o);
        
        $post_id = wp_insert_post( 
            array( 
                'post_name' => 'This is a test post', 
                'post_status' => 'publish', 
                'post_content' => 'This is the RedactTest content for the post.' ) ); 
        
        $post = get_post( $post_id );
        $post = $r->redact_post_content($post->post_content);
        
        $this->assertEquals(
                1, 
                preg_match("/(&#9608;)+/", $post),
                "The text should contain redacted content");  
    }
    
    public function testRedactPostContentDisabled(  ){
        $r = RedactorController::get_instance();       
        
        $o = get_option('redactor_options');
        $o['redact_posts'] = false;
        update_option('redactor_options', $o);
        
        $post_id = wp_insert_post( 
            array( 
                'post_name'    => 'This is a test post', 
                'post_status'  => 'publish', 
                'post_content' => 'This is the RedactTest content for the post.' ) ); 
        
        $post = get_post( $post_id );
        $post = $r->redact_post_content($post->post_content);
        
        $this->assertEquals(
                0, 
                preg_match("/(&#9608;)+/", $post),
                "The text should contain redacted content");  
    }
    
    public function testRedactCommentContent(  ){
        $r = RedactorController::get_instance();       
        
        $o = get_option('redactor_options');
        $o['redact_comments'] = true;
        update_option('redactor_options', $o);
        
        $time = current_time('mysql');
        
        
        $post_id = wp_insert_post( 
            array( 
                'post_name' => 'This is a test post', 
                'post_status' => 'publish', 
                'post_content' => 'This is the RedactTest content for the post.' ) ); 

        $data = array(
            'comment_post_ID' => $post_id,
            'comment_author' => 'admin',
            'comment_author_email' => 'admin@admin.com',
            'comment_author_url' => 'http://',
            'comment_content' => '  redactTest  ',
            'comment_type' => '',
            'comment_parent' => 0,
            'user_id' => 1,
            'comment_author_IP' => '127.0.0.1',
            'comment_agent' => 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.0.10) Gecko/2009042316 Firefox/3.0.10 (.NET CLR 3.5.30729)',
            'comment_date' => $time,
            'comment_approved' => 1,
        );

        $comment = wp_insert_comment($data);
        
        $comment = get_comment( $comment );
        $comment = $r->redact_comment_content($comment->comment_content);
        
        $this->assertEquals(
                1, 
                preg_match("/(&#9608;)+/", $comment),
                "The text should contain redacted content");  
    }
    
    public function testRedactCommentContentDisabled(  ){
        $r = RedactorController::get_instance();       
        
        $o = get_option('redactor_options');
        $o['redact_comments'] = false;
        update_option('redactor_options', $o);
        
        $time = current_time('mysql');
        
        
        $post_id = wp_insert_post( 
            array( 
                'post_name' => 'This is a test post', 
                'post_status' => 'publish', 
                'post_content' => 'This is the RedactTest content for the post.' ) ); 

        $data = array(
            'comment_post_ID' => $post_id,
            'comment_author' => 'admin',
            'comment_author_email' => 'admin@admin.com',
            'comment_author_url' => 'http://',
            'comment_content' => '  redactTest  ',
            'comment_type' => '',
            'comment_parent' => 0,
            'user_id' => 1,
            'comment_author_IP' => '127.0.0.1',
            'comment_agent' => 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.0.10) Gecko/2009042316 Firefox/3.0.10 (.NET CLR 3.5.30729)',
            'comment_date' => $time,
            'comment_approved' => 1,
        );

        $comment = wp_insert_comment($data);
        
        $comment = get_comment( $comment );
        $comment = $r->redact_comment_content($comment->comment_content);
        
        $this->assertEquals(
                0, 
                preg_match("/(&#9608;)+/", $comment),
                "The text should contain redacted content");  
    }
    
}