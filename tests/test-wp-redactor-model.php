<?php

require_once __DIR__ . "/../includes/redactor/redactor-controller.php";

class RedactorModelTest extends WP_UnitTestCase {
    
    private $numRows = 0;
    
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

        $this->numRows = count($rows);
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
        global $wpdb;
        $rm = RedactorModel::get_instance();
        
        $rm->install_database();
        
        $table_name      = $wpdb->prefix . datasync_DATABASE_REDACT_TABLENAME;
        $dbname          = $wpdb->dbname;
         
        $query = " SELECT *                             ".
                 " FROM information_schema.tables       ".     
                 " WHERE table_schema = '${dbname}'     ".
                 "   AND table_name = '${table_name}'  ".
                 " LIMIT 1;";
        
        $rows = $wpdb->get_results($query);
                                
        $this->assertGreaterThan(
                0, 
                count($rows),
                "The redactor table was not found in the information_schema.");        
    }
    
    function testConvertToRedactStringsWithInt(){
        $rm = RedactorModel::get_instance();
        
        $a = $rm->convertToRedactStrings(2);        
        $this->assertEquals(1, preg_match("/(&#9608;){1}/", $a[0]));        
    }
    
    function testConvertToRedactStringsWithString(){
        $rm = RedactorModel::get_instance();
        
        $a = $rm->convertToRedactStrings('redactors123456');        
        $this->assertEquals(1, preg_match("/(&#9608;){15}/", $a));        
    }
    
    function testConvertToRedactStringsWithArray(){
        $rm = RedactorModel::get_instance();
        
        $a = 'redactors123456';
        $b = 'redactors#@!$%^';        
        $ar = $rm->convertToRedactStrings(array($a,$b));
        $this->assertEquals(
                1, 
                preg_match("/(&#9608;){15}/", $ar[0]),
                "The first element does not match");     
        $this->assertEquals(
                1, 
                preg_match("/(&#9608;){15}/", $ar[1]),
                "The second element does not match");        
    }
    
    function testGetRedactRules(){
        $rm = RedactorModel::get_instance();
        
        $a = $rm->getRedactRules("RedactTest");
        $this->assertEquals(1, count($a));
    }
    
    function testGetRedactRulesNoMatch(){
        $rm = RedactorModel::get_instance();
        
        $a = $rm->getRedactRules("Redactor");
        $this->assertEquals(0, count($a));
    }
    
    function testGetRuleRowCount(){
        $rm = RedactorModel::get_instance();
        
        $a = $rm->getRuleRowCount();
        $this->assertEquals( $this->numRows, $a );
    }
    
    function testGetRawRuleRecords(){
        $rm = RedactorModel::get_instance();
        
        $a = $rm->getRawRuleRecords();
        $this->assertEquals( $this->numRows, count($a) );
    }
    
    function testCreateRule(){
        $rm = RedactorModel::get_instance();
        
        $a = $rm->getRuleRowCount();
        $rm->createRule('newRule', 'administrator,editor', 'admin');
        $b = $rm->getRuleRowCount();
        
        $this->assertGreaterThan($a, $b);
    }
    
    function testCreateRuleWithNulls(){
        $rm = RedactorModel::get_instance();
        
        $a = $rm->getRuleRowCount();
        $rm->createRule(null, null, null);
        $b = $rm->getRuleRowCount();
        
        $this->assertGreaterThan($a, $b);
    }
    
    function testUpdateRule(){
        $rm = RedactorModel::get_instance();
        
        $rule = $rm->createRule('updateRule', 'administrator,editor', 'admin');
        $rm->updateRule( $rule['id'], 'RuleIsUpdated', $rule['str_groups'], $rule['str_groups']);
        $a = $rm->getRedactRules("RuleIsUpdated");
        
        $this->assertEquals( 1, count($a) );
    }
    
    function testUpdateRuleWithNulls(){
        $rm = RedactorModel::get_instance();
        
        $rule = $rm->createRule('updateRule', 'administrator,editor', 'admin');
        $rm->updateRule( $rule['id'], null, null, null);
        $a = $rm->getRedactRules("updateRule");
        
        $this->assertEquals( 0, count($a) );
    }
    
    function testDeleteRule(){
        $rm = RedactorModel::get_instance();
        
        $rule = $rm->createRule('RuleToDelete', 'administrator,editor', 'admin');
        $a = $rm->getRuleRowCount();
        $rm->deleteRule( $rule['id'] );
        $b = $rm->getRuleRowCount();
        $c = $rm->getRedactRules("RuleToDelete");
        
        $this->assertLessThan( $a, $b );
        $this->assertEquals( 0, count($c) );
    }

}