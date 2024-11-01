<?php


require_once __DIR__ . "/../includes/redactor/redactor-controller.php";

class RedactorViewTest extends WP_UnitTestCase {
    
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
    
    function testInstallDatabase(){
        $rv = RedactorView::get_instance();
        
        $html = $rv->redactStringToHTML('redact', 'user', '2013', 'Redactor');
        $this->assertEquals(
                "<span class='redact' title='Redacted by user on 2013'>Redactor</span>", 
                $html);
    }
}

