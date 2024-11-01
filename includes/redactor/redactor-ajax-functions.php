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


require_once( __DIR__ . '/../redactor/redactor-model.php' );


/**
 * Return the roles available for selection in the redaction dialog box.
 * Do not include adminstrator or editor because they always have access
 * to the content.
 * @global $wp_roles;
 * @since 0.0.1
 */
function ajax_getRoles() {
    global $wp_roles;
    $roles = $wp_roles->get_names();
    unset($roles['administrator'], $roles['editor']); // they always have access to the content
    echo json_encode($roles);
    wp_die();
}

/**
 * Return general info about the user and today's date so that it
 * can be added to a tooltip for the redaction.
 * @since 0.0.1
 */
function ajax_getCurrentUserNameAndDate() {
    $current_user = wp_get_current_user();
    $date = current_time('m/d/Y');
    echo json_encode(array(
            'name' => $current_user->display_name,
            'date' => $date
    ));
    wp_die();
}



/**
 * An AJAX endpoint for updating a rule in the database.
 * @since 0.0.1
 */
function ajax_updateRules(){
    $redactorModel = RedactorModel::get_instance();
    
    if(!current_user_can("administrator")){
        //die("Not permitted");
    }

    //parse and validate parameters for the database
    $id          = ( isset($_POST['id'])           && is_string($_POST['id'])           )? intval($_POST['id'],10) : -1;
    $user        = ( isset($_POST['str_username']) && is_string($_POST['str_username']) )? stripslashes($_POST['str_username']) : wp_get_current_user();
    $permissions = ( isset($_POST['str_groups'])   && is_string($_POST['str_groups'])   )? stripslashes($_POST['str_groups']) : '';
    $rule        = ( isset($_POST['rx_redaction']) && is_string($_POST['rx_redaction']) )? stripslashes($_POST['rx_redaction']) : '';

    //Update the rule in the database
    $results = false;
    
    if($id > 0){
        $results = $redactorModel->updateRule($id, $rule, $permissions, $user);
    }

    echo json_encode($results);

    wp_die();
}

/**
 * An AJAX endpoint for deleting rules from the database by id.
 */
function ajax_deleteRules(){
    $redactorModel = RedactorModel::get_instance();
    
    if(!current_user_can("administrator")){
        die("Not permitted");
    }

    $idInput = ( isset($_POST['id']))? $_POST['id'] : array();
    if(is_integer($idInput)){
        $idInput = array($idInput);

    }
    $results = false;

    if( is_array($idInput) ){
        $deleteRows = array_filter($idInput, "datasync_isPositiveInteger");

        $resultArray = array();
        foreach($deleteRows as $id){
            $resultArray[] = array(
                    'id' => $id,
                    'result' => $redactorModel->deleteRule($id) );
        }

        $results = $resultArray;
    }

    echo json_encode($results);

    wp_die();
}

/**
 * AJAX function to add rules to the database.
 * @since 0.0.1
 */
function ajax_addRules(){
    $redactorModel = RedactorModel::get_instance();
    
    if(!current_user_can("administrator")){
        die("Not permitted");
    }

    $user        = ( isset($_POST['str_username']) && is_string($_POST['str_username']) )? stripslashes($_POST['str_username']) : wp_get_current_user();
    $permissions = ( isset($_POST['str_groups'])   && is_string($_POST['str_groups'])   )? stripslashes($_POST['str_groups']) : '';
    $rule        = ( isset($_POST['rx_redaction']) && is_string($_POST['rx_redaction']) )? stripslashes($_POST['rx_redaction']) : '';

    $results = array();
    if($rule != ''){
        $results = $redactorModel->createRule($rule, $permissions, $user);
    }           

    echo json_encode($results);

    wp_die();
}

/**
 * AJAX function to retrieve rules from the database using limits and offsets
 * @since 0.0.1
 */
function ajax_getRules(){
    $redactorModel = RedactorModel::get_instance();
    
    if(!current_user_can("administrator")){
        die("Not permitted");
    }

    $offset = ( isset($_GET['offset']) && is_integer($_GET['offset']))? $_GET['offset'] : 0;
    $limit  = ( isset($_GET['limit'])  && is_integer($_GET['limit']))?  $_GET['limit'] : 50;

    $rowCount = $redactorModel->getRuleRowCount();
    //$results = $redactorModel->getRawRuleRecords($offset, $limit);

    $results = $redactorModel->getRawRuleRecords(0, 50000);


    $output = array(
        'offset' => $offset,
        'limit' => $limit,
        'total' => $rowCount,
        'results' => $results
    );

    echo json_encode($output);
    wp_die();
}

