<?php

class RedactorTest extends WP_UnitTestCase {

    public function testRedactWithNullContent() {
    	$r = new Redaction();
        $this->assertEquals("", $r->redact("one,two", "mr. redactor", "03/29/2016", null));
    }
    
    public function testRedactWithEmptyString() {
    	$r = new Redaction();
    	$this->assertEquals("", $r->redact("one, two", "redactor", "03/29/2016", ""));
    }
    
    public function testRedactWithWhitespaceString() {
    	$r = new Redaction();
    	$this->assertEquals("", $r->redact("one, two", "redactor", "03/29/2016", "\t\n   \n\r   \t "));
    }
    
    public function testRedactWithAdminUser() {
    	$user_id = $this->factory->user->create(array( 'role' => 'administrator' ));
    	wp_set_current_user( $user_id );
    	$r = new Redaction();
    	$this->assertEquals("<span class='allowed' title='Redacted by david on 03/29/2016'>hello world</span>", $r->redact("one, two", "david", "03/29/2016", "hello world"));
    }
    
    public function testRedactWithEditorUser() {
    	$user_id = $this->factory->user->create(array( 'role' => 'editor' ));
    	wp_set_current_user( $user_id );
    	$r = new Redaction();
    	$this->assertEquals("<span class='allowed' title='Redacted by david on 03/29/2016'>hello world</span>", $r->redact("one, two", "david", "03/29/2016", "hello world"));
    }
    
    public function testRedactWithNoAllowedRolesUser() {
    	$user_id = $this->factory->user->create(array( 'role' => 'subscriber' ));
    	wp_set_current_user( $user_id );
    	$r = new Redaction();
    	$this->assertEquals("<span class='redacted' title='Redacted by david on 03/29/2016'>&#9608;&#9608;&#9608;&#9608;&#9608; &#9608;&#9608;&#9608;&#9608; &#9608;&#9608;&#9608;&#9608;&#9608;</span>", $r->redact("one, two", "david", "03/29/2016", "goodbye world123"));
    }
    
    public function testRedactWithNoAllowedRolesUserBecauseNoRedactRoles() {
    	$user_id = $this->factory->user->create(array( 'role' => 'subscriber' ));
    	wp_set_current_user( $user_id );
    	$r = new Redaction();
    	$this->assertEquals("<span class='redacted' title='Redacted by david on 03/29/2016'>&#9608;&#9608;&#9608;&#9608;&#9608; &#9608;&#9608;&#9608;&#9608; &#9608;&#9608;&#9608;&#9608;&#9608;</span>", $r->redact("", "david", "03/29/2016", "goodbye world123"));
    }
    
    public function testRedactWithNoAllowedRolesUserBecauseRedactRolesIsNull() {
    	$user_id = $this->factory->user->create(array( 'role' => 'subscriber' ));
    	wp_set_current_user( $user_id );
    	$r = new Redaction();
    	$this->assertEquals("<span class='redacted' title='Redacted by david on 03/29/2016'>&#9608;&#9608;&#9608;&#9608;&#9608; &#9608;&#9608;&#9608;&#9608; &#9608;&#9608;&#9608;&#9608;&#9608;</span>", $r->redact(null, "david", "03/29/2016", "goodbye world123"));
    }
    
    public function testRedactWithNoAllowedRolesUserAndNullRedactor() {
    	$user_id = $this->factory->user->create(array( 'role' => 'subscriber' ));
    	wp_set_current_user( $user_id );
    	$r = new Redaction();
    	$this->assertEquals("<span class='redacted' title='Redacted by unknown on 03/29/2016'>&#9608;&#9608;&#9608;&#9608;&#9608; &#9608;&#9608;&#9608;&#9608; &#9608;&#9608;&#9608;&#9608;&#9608;</span>", $r->redact("one, two", null, "03/29/2016", "goodbye world123"));
    }
    
    public function testRedactWithNoAllowedRolesUserAndNullDate() {
    	$user_id = $this->factory->user->create(array( 'role' => 'subscriber' ));
    	wp_set_current_user( $user_id );
    	$r = new Redaction();
    	$this->assertEquals("<span class='redacted' title='Redacted by david on unspecified date'>&#9608;&#9608;&#9608;&#9608;&#9608; &#9608;&#9608;&#9608;&#9608; &#9608;&#9608;&#9608;&#9608;&#9608;</span>", $r->redact("one, two", "david", null, "goodbye world123"));
    }
    
    public function testRedactWithAllowedRoleUserAndOneRedactRole() {
    	$user_id = $this->factory->user->create(array( 'role' => 'good' ));
    	wp_set_current_user( $user_id );
    	$r = new Redaction();
    	$this->assertEquals("<span class='allowed' title='Redacted by david on 03/29/2016'>goodbye world123</span>", $r->redact("good", "david", "03/29/2016", "goodbye world123"));
    }
    
    public function testRedactWithAllowedRoleUserAndTwoRedactRolesStartWithGood() {
    	$user_id = $this->factory->user->create(array( 'role' => 'good' ));
    	wp_set_current_user( $user_id );
    	$r = new Redaction();
    	$this->assertEquals("<span class='allowed' title='Redacted by david on 03/29/2016'>goodbye world123</span>", $r->redact("good, bad", "david", "03/29/2016", "goodbye world123"));
    }
    
    public function testRedactWithAllowedRoleUserAndTwoRedactRolesEndWithGood() {
    	$user_id = $this->factory->user->create(array( 'role' => 'good' ));
    	wp_set_current_user( $user_id );
    	$r = new Redaction();
    	$this->assertEquals("<span class='allowed' title='Redacted by david on 03/29/2016'>goodbye world123</span>", $r->redact("bad, good", "david", "03/29/2016", "goodbye world123"));
    }
    
    public function testRedactWithAllowedRoleUserAndThreeRedactRolesStartWithGood() {
    	$user_id = $this->factory->user->create(array( 'role' => 'good' ));
    	wp_set_current_user( $user_id );
    	$r = new Redaction();
    	$this->assertEquals("<span class='allowed' title='Redacted by david on 03/29/2016'>goodbye world123</span>", $r->redact("good, bad1, bad2", "david", "03/29/2016", "goodbye world123"));
    }
    
    public function testRedactWithAllowedRoleUserAndThreeRedactRolesEndWithGood() {
    	$user_id = $this->factory->user->create(array( 'role' => 'good' ));
    	wp_set_current_user( $user_id );
    	$r = new Redaction();
    	$this->assertEquals("<span class='allowed' title='Redacted by david on 03/29/2016'>goodbye world123</span>", $r->redact("bad1, bad2, good", "david", "03/29/2016", "goodbye world123"));
    }
    
    public function testRedactWithAllowedRoleUserAndThreeRedactRolesGoodInBetween() {
    	$user_id = $this->factory->user->create(array( 'role' => 'good' ));
    	wp_set_current_user( $user_id );
    	$r = new Redaction();
    	$this->assertEquals("<span class='allowed' title='Redacted by david on 03/29/2016'>goodbye world123</span>", $r->redact("bad1, good, bad2", "david", "03/29/2016", "goodbye world123"));
    }
}