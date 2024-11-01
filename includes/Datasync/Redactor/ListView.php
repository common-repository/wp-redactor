<?php
/**
 * The list view for the dynamic redactions
 */
namespace Datasync\Redactor;

use Datasync\WP\RedactionsListTable;
use NooNoo\FluentRegex\Regex;

if( ! defined( 'WP_REDACTOR' ) ) exit;

/**
 * List view display of redaction patterns
 *
 * Displays an admin page with all the redaction patterns using the
 * WP_List_Table class to allow for all the nice features of
 * Wordpress. Manages all the interaction to add and remove redactions including
 * the admin menus, list view, add and remove pages.
 *
 * Example usage:
 * ListView::get_instance();
 *
 * @package  	Redactor
 * @author   	Joseph M. King <michael.king@datasynctech.com>
 * @copyright   2016 DataSync Technologies                                  
 * @license		GPLv2 or later                                                              
 * @access   	public
 * @since    	1.2.0
 *
 * @link https://github.com/DataSyncTech/wp-redactor/issues/25
 * @link https://github.com/DataSyncTech/wp-redactor/issues/26
 *
 */
class ListView
{
    /**
     * Table list view object
     *
     * @since 1.2.0
     * @access private
     */
    private $list_table = null;

    /**
     * Array of notices
     *
     * @since 1.2.0
     * @access private
     */
    private $admin_notices = array();

    /**
     * A single instance of this class
     *
     * @since 1.2.0
     * @access private
     * 
     * @var Object
     */
    private static $instance = null;

    /**
     * The current page the user requested
     *
     * @since 1.2.0
     * @access private
     * 
     * @var string
     */
    private $page = null;

    /**
     * The allowed pages 
     *
     * @since 1.2.0
     * @access private
     * 
     * @var Array
     */
    private $pages = null;

    /**
     * The current action the user requested
     *
     * @since 1.2.0
     * @access private
     * 
     * @var string
     */
    private $action = null;

    /**
     * The allowed actions
     * 
     * @since 1.2.0
     * @access private
     * 
     * @var Array
     */
    private $actions = null;

    /**
     * Alternative rendering of the current page
     * 
     * @since 1.2.0
     * @access private
     * 
     * @var string
     */
    private $screen = null;
    
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
     * @since 1.2.0
     * @access public
     */
    public static function initialize()
    {
	    $plugin = new self();
	    
	    // list of supported pages
	    $plugin->pages = array( 'wp-redactor' => 'list_table',
	    		'wp-redactor-add-new' => 'add_new',
	    		'wp-redactor-delete' => 'delete',
	    		'wp-redactor-edit' => 'edit' );

	    // handle initializing the admin environment
        add_action( "admin_init", array( $plugin, 'admin_init' ) );

        // add all the admin menus and pages
        add_action( 'admin_menu', array( $plugin, 'add_menu_page' ) );
        
        // handle the submission of the forms
        add_action( 'admin_action_add_new', array( $plugin, 'add_new_handler' ) );
        add_action( 'admin_action_edit', array( $plugin, 'edit_handler' ) );
        add_action( 'admin_action_delete', array( $plugin, 'delete_handler' ) );
        add_action( 'admin_action_bulk_delete', array( $plugin, 'bulk_delete_handler' ) );
    }

    /**
     * Wordpress init hook
     *
     * @since 1.2.0
     * @access public
     */
    public function admin_init()
    {   
        // handle any actionss
        if ( ! empty( $_REQUEST['action'] ) ) {
        	do_action( 'admin_action_' . $_REQUEST['action'] );
        }
        
        $this->action = isset( $_REQUEST['action'] )
        	? $_REQUEST['action'] : false;
    }

    /**
     * Handle pages not handled by menus
     *
     * @since 1.2.0
     * @access public
     */
    public function plugin_pages()
    {
    	if( isset( $_GET['page'] ) && array_key_exists( $_GET['page'], $this->pages ) )
    		$this->page_setup();
    }
    
    /**
     * Menu item will allow us to load the page to display the table
     *
     * @since 1.2.0
     * @access public
     */
    public function add_menu_page()
    {
        if( isset( $_GET['page'] ) && array_key_exists( $_GET['page'], $this->pages ) )
	    $this->page_setup();

        // add the main redactions menu
        if( get_bloginfo('version') >= '3.8' ) {
	        $hook = add_menu_page( 'Redactions', 'Redactions', 'manage_options', 
	            'wp-redactor', array($this, 'list_table_page'),
	        	'dashicons-text', 40 );
        } else {
        	$hook = add_menu_page( 'Redactions', 'Redactions', 'manage_options',
        		'wp-redactor', array($this, 'list_table_page'), null, 40 );    	 
        }
       
        // add the redactions submenus
        add_submenu_page( 'wp-redactor', 'Redactions', 'Redactions', 'manage_options', 
            'wp-redactor');
        add_submenu_page( 'wp-redactor', 'Add New', 'Add New', 'manage_options', 
            'wp-redactor-add-new', array( $this, 'add_new_page' ) );
        add_submenu_page( 'wp-redactor', 'Metrics', 'Metrics', 'manage_options',
        	'wp-redactor-metrics', array( $this, 'create_metrics_page' ) );
        
        if( $this->page == 'wp-redactor' && $this->screen != 'edit' ) {
            // add the screen options at the top of the page 
            add_action( "load-$hook", array( $this, 'add_options' ) );
            add_filter( 'set-screen-option', array( $this, 'redaction_table_set_option' ), 10, 3);
        }
    }

    /**
     * Add screen option to specify how many items per page
     *
     * @since 1.2.0
     * @access public
     */
    public function add_options() 
    {
        $option = 'per_page';

        $args = array(
            'label' => 'Items Per Page',
            'default' => 10,
            'option' => 'wp_redactor_per_page'
        );

        //add_screen_option( $option, $args );

        // initialize here to ensure that screen options are created correctly
       $this->list_table = new RedactionsListTable();
    }

    /**
     * Return the screen option value
     *
     * @since 1.2.0
     * @access public
     *
     * @param string $status
     * @param array $options
     * @param string $value
     * @return mixed
     */
    public function redaction_table_set_option( $status, $option, $value )
    {
    	if ( 'wp_redactor_per_page' == $option ) return $value;
    }

    /**
     * Setup page parameters
     *
     * @since 1.2.0
     * @access private
     */
    private function page_setup()
    {
        $plugin = $this;

        // list of supported actions
        $plugin->actions = array( 'add_new', 'edit', 'delete', 'bulk_delete' );

        // set screen
        $plugin->screen = empty( $plugin->screen ) && isset( $_GET['screen'] ) 
        		? $_GET['screen'] : '';
        
        // default list view page
        $plugin->page = isset( $_GET['page'] ) ? $_GET['page'] : 'wp-redactor';
    }

    /**
     * Hide menu items for edit and delete
     * 
     * @since 1.2.0
     * @access public
     * 
     * @param array $items
     * @param array $args
     * @return array
     */
    public function hide_menus( $items, $args )
    {
    	foreach ( $items as $key => $item ) {
    		if ( $item->object_id == 168 ) unset( $items[$key] );
    	}
    	
    	return $items;
    }
    
    /**
     * Handle the submission of the add form
     *
     * @since 1.2.0
     * @access private
     */
    public function add_new_handler()
    {
    	$model = Model::get_instance();
    	
    	$defaults = array( 'pattern' => '', 'roles' => array() );
        $data = array_merge( $defaults, $_POST );

        if( empty( $data['description'] ) ) {
        	$this->add_notice( 'error', 'The description cannot be blank' );
        }
        
        if( empty( $data['pattern'] ) ) {
            $this->add_notice( 'error', 'The pattern cannot be blank' );
        }

        if( strpos( $data['pattern'], '/' ) === 0) {
        
        	$name = strtoupper( substr( $data['pattern'], 1 ) );
        	
        	if( ! defined('REGEX_' . $name) ) {
        
        		$this->add_notice( 'error', 'The pattern <b> '. esc_html($data['pattern']) .'</b> is not a valid regex.' );
        	}
        } else if( strpos( $data['pattern'], '#' ) === 0 ) {
        	 
        	$charlist = '09AS!@#$%^&*()_+-=[]\{}}|;:",./<>?\'" ';
        	$string = substr( $data['pattern'], 1 );
        	 
        	if( strlen( $string ) != strspn( $string, $charlist ) ) {
        		$this->add_notice( 'error', 'The pattern <b> '. esc_html($data['pattern']) .'</b> is not a valid mask.' );
        	}
        } else {
        	$data['pattern'] = sanitize_text_field( $data['pattern'] );
        }
        
        if( $model->hasRule( $data['pattern'] ) ) {
        	$this->add_notice( 'error', 'The pattern <b> '. esc_html($data['pattern']) .'</b> already exists in a different rule' );
        }
        
        $error = ( bool ) count( $this->admin_notices );

        if( ! $error ) {
            $user = wp_get_current_user();
            if( false === $model->createRule( $data['pattern'], $data['roles'], 
                $user->data->user_login, sanitize_text_field( $data['description'] ) ) ) {

                $this->add_notice( 'error', 'Rule <b>' . esc_html( $data['pattern'] ) . 
                    '</b>failed to create successfully' );

                return;
            }
       
            $_POST = array(); 
            $this->add_notice( 'success', 'New pattern <b>' . esc_html($data['description']) .
                 '</b> added successfully' );

            if( $data['return_to'] != urlencode( add_query_arg( NULL, NULL ) ) ) {
                wp_redirect( urldecode( $data['return_to'] ).'&notice=add_new_success' );
            }
        }

        return 'add_new';
    }

    /**
     * Handle the delete submission
     *
     * @since 1.2.0
     * @access public
     */
    public function delete_handler()
    {
        $model = Model::get_instance();

        $id = isset( $_REQUEST['id'] ) ? $_REQUEST['id'] : false;

        if( ! $id || ! is_numeric( $id ) || null === $rule = $model->deleteRule( $id ) ) {
            $this->add_notice( 'error', 'Error deleting the rule' );
            return;
        }

        $this->add_notice( 'success', 'Rule deleted successfully' );
    }

    /**
     * Handle multiple rule deletions
     * 
     * @since 1.2.0
     * @access public
     */
    public function bulk_delete_handler()
    {
    	
        $model = Model::get_instance();

        if( isset( $_POST['redaction'] ) && is_array( $_POST['redaction'] ) ) {

            if( $model->bulkDeleteRules( $_POST['redaction'] ) ) {

                $this->add_notice( 'success', 'Rules deleted successfully' );
            } else {

                $this->add_notice( 'error', 'There was an error deleting one or more rules.' );
            }
        } else {

            $this->add_notice( 'error', 'No rules selected to delete.' );
        }
        return 'list_table';
    }

    /**
     * Handle the rule update
     *
     * @since 1.2.0
     * @access private
     */
    public function edit_handler()
    {
        $model = Model::get_instance();

        $defaults = array( 'id' => null, 'pattern' => '', 'roles' => array() );
        $data = array_merge( $defaults, $_POST );

        if( empty( $data['id'] ) ) {
            $this->add_notice( 'error', 'The redaction id is invalid' );
        }

        if( empty( $data['description'] ) ) {
        	$this->add_notice( 'error', 'The description cannot be blank' );
        }
        
        if( empty( $data['pattern'] ) ) {
            $this->add_notice( 'error', 'The pattern cannot be blank' );
        }

        if( strpos( $data['pattern'], '/' ) === 0) {

        	$name = strtoupper( substr( $data['pattern'], 1 ) );
        	
        	if( ! defined('REGEX_' . $name) ) {
        		
        		$this->add_notice( 'error', 'The pattern <b> '. esc_html($data['pattern']) .'</b> is not a valid regex.' );
        	}
        } else if( strpos( $data['pattern'], '#' ) === 0 ) {
        	
        	$charlist = '09AS!@#$%^&*()_+-=[]\{}}|;:",./<>?\'" ';
        	$string = substr( $data['pattern'], 1 );
        	
        	if( strlen( $string ) != strspn( $string, $charlist ) ) {
        		$this->add_notice( 'error', 'The pattern <b> '. esc_html($data['pattern']) .'</b> is not a valid mask.' );
        	}
        } else {
        	$data['pattern'] = sanitize_text_field( $data['pattern'] );        	
        }

        
        if( $model->hasRule( $data['pattern'], $data['id'] ) ) {
        	$this->add_notice( 'error', 'The rule already exist' );
        }
        
        $error = ( bool ) count( $this->admin_notices );

        if( ! $error ) {
            $user = wp_get_current_user();
            $model = Model::get_instance();
            
            do_action( 'pre_redaction_update', $data['id'], $user->ID );
            // TODO: determine if there was an actual change
            if( false === $model->updateRule( $data['id'], 
                $data['pattern'],
                $data['roles'], $user->data->user_login,
            	sanitize_text_field( $data['description'] ))) {
                	
                $this->screen = 'edit';
                	
                $this->add_notice( 'error', 'Rule ' . esc_html( $data['description'] ) .
                    'failed to update' );
            }

            do_action( 'post_redaction_update', $data['id'], $user->ID );
            $this->add_notice( 'success', 'Redaction <b>' . esc_html( $data['pattern'] ).
                '</b> updated' );
        } else {
        	$this->screen = 'edit';
        }
    }

    /**
     * Page header
     *
     * @since 1.2.0
     * @access private
     *
     * @param string $title
     * @param string $action
     */
     private function page_header( $title, $action = null )
     {
         $action = empty( $action ) ? admin_url( 'admin.php?page=wp-redactor' ) : $action;

         $current = urlencode( add_query_arg( NULL, NULL ) );
         $return_to = isset( $_GET['return_to'] ) ? $_GET['return_to'] : $current;

         ?>
         <div class="wrap">
             <div id="icon-users" class="icon32"></div>
             <?php
                 if( get_bloginfo('version') >= '4.3' ) {
                     print "<h1>{$title}</h1>";
                 } else {
                     print "<h2>{$title}</h2>";                 
                 }
             ?>             
             <?php $this->admin_notices() ?>
             <form method="post" action="<?php print $action ?>" class="redactor_options_group">
             <input type="hidden" name="return_to"
                 value="<?php print $return_to ?>"/>
         <?php
     }

    /**
     * Page footer
     *
     * @since 1.2.0
     * @access private
     *
     * @param string $title
     */
     private function page_footer()
     {
         ?>
             </form>
         </div>
         <?php
     }

    /**
     * Display the list table page
     *
     * @since 1.2.0
     * @access public
     *
     * @return void
     */
    public function list_table_page()
    {
    	if( $this->screen == 'edit' ) {
    	
    		$this->edit_page();
    		return;
    	}
    	
    	if( ! is_object( $this->list_table ) ) return;
    	
        $this->list_table->prepare_items();

        $current = urlencode( add_query_arg( NULL, NULL ) );

        $class_name = ( get_bloginfo('version') >= '4.3' ) ? 'page-title-action' : 'add-new-h2';
        
        $this->page_header( 'Redactions <a href="?page=wp-redactor-add-new"
                    class="'.$class_name.'">Add New</a>' );
        
        print "<input type=\"hidden\" name=\"page\" value=\"wp-redactor\" />";
        $this->list_table->search_box('search', 'search_id');
        $this->list_table->display();
        $this->page_footer();
    }

    /**
     * Display the page to confirm deletion
     *
     * @since 1.2.0
     * @access private
     */
    private function delete_page()
    {
        $model = Model::get_instance();

        $id = isset( $_GET['id'] ) ? $_GET['id'] : false;

        if( ! $id || ! is_numeric( $id ) || null === $rule = $model->getRule( $id ) ) {
            $this->add_notice( 'error', 'Unable to find the item' );

            return;
        }

        $this->page_header( 'Delete Redaction' );

        print "<p>Confirm that you want to permanently delete the redaction rule ";
        print "<b>{$rule['str_description']}</b>.</p>";

        print '<input type="hidden" name="id" value="'.esc_attr($id).'"/>';
        print '<input type="hidden" name="action" value="delete"/>';
        
        submit_button( 'Delete' );
        $this->page_footer();
    }
    
    /**
     * Confirm bulk deletion
     *
     * @since 1.2.0
     * @access private
     */
    private function bulk_delete_page()
    {
        $this->page_header( 'Delete Redactions' );

        print '<p>Confirm that you want to permanently delete ' . 
            count( $_POST['redaction'] ) . ' redaction rules.</p>';

        print '<input type="hidden" name="action" value="bulk_delete"/>';
        foreach( $_POST['redaction'] as $val ) {
            print '<input type="hidden" name="redaction[]" value="'.esc_attr($val).'"/>';
        }

        submit_button( 'Delete All' );
        $this->page_footer();
    }

    /**
     * Display the page to edit the redaction
     *
     * @since 1.2.0
     * @access private
     */
    public function edit_page()
    {
        $model = Model::get_instance();

        if( isset( $_POST['submit'] ) ) {

            $data = $_POST;
            
            if( strpos( $data['pattern'], '/' ) === 0) {
            	$data['pattern'] = stripslashes_deep( $_POST['pattern'] );
            }
        } else {

            $id = isset( $_REQUEST['id'] ) ? $_REQUEST['id'] : false;

            if( ! $id || ! is_numeric( $id ) || null === $rule = $model->getRule( $id ) ) {
                $this->add_notice( 'error', 'Unable to find the item' );

                $this->list_table_page();
                return;
            }

            $data = array( 'id' => $id, 
            	'description' => $rule['str_description'],
                'pattern' => $rule['rx_redaction'],
                'roles' => @unserialize( $rule['str_groups'] ) );
        }

        $this->page_header( 'Modify Redaction' );
        
        ?>
        <p>Enter a string or regular expression and select the roles allowed to view the redaction. 
        <br>Administrators and Editors can always see the content.</p> 
        <table class="form-table">
             <tr valign="top">
                 <th scope="row">Description<p class="helptext"><i>A short
                 description of the rule</i></p></th>
                 <td><input type="text" name="description" class="regular-text"
                     value="<?php print esc_attr($data['description']) ?>" /></td>
             </tr>

             <tr valign="top">
                 <th scope="row">Pattern<p class="helptext"><i>Select the string
                 to redact in the post content.</i></p></th>
                 <td><input type="text" name="pattern" class="regular-text"
                     value="<?php print esc_attr($data['pattern']) ?>" /></td>
                 
             </tr>
             <tr style="margin-top:0;padding-top:0">
             	<td colspan="2" style="padding-left:220px;margin-top:0;padding-top:0">
             	<?php 
             		if( isset( $_GET['debug'] ) )
             		echo View::get_instance()->build_regex( $data['pattern'] );
             	?>
             </td></tr>
             
             <tr valign="top">
                 <th scope="row">Allowed Roles<p class="helptext"><i>Select the 
                 roles that are allow to view redacted content if different than
                 site defaults.</i></p></th>
                 <td><select name="roles[]" multiple size="5" style="width: 350px">
                     <?php ds_dropdown_roles( isset($data['roles']) ? $data['roles'] : array() ) ?>
                 </select></td>
             </tr>
         </table>
         <input type="hidden" name="id" value="<?php print $data['id']?>">
         <input type="hidden" name="action" value="edit">
         <?php
         wp_nonce_field( 'wp-redactor-edit-'.$data['id'] );
         submit_button( 'Save Changes' );
         $this->page_footer();
    }

    /**
     * Display the page to add a new redaction
     *
     * @since 1.2.0
     * @access private
     */
    public function add_new_page()
    {
        $defaults = array( 'description' => '', 'pattern' => '', 'roles' => array() );
        $data = array_merge( $defaults, $_POST );

        if( strpos( $data['pattern'], '/' ) === 0) {
        	$data['pattern'] = stripslashes_deep( $_POST['pattern'] );
        }
        
        $this->page_header( 'Add Redaction', admin_url( 'admin.php?page=wp-redactor-add-new' ) );
        ?>
        <p>Enter a string or regular expression and select the roles allowed to view the redaction. 
        <br>Administrators and Editors can always see the content.</p> 
        <input type="hidden" name="action" value="add_new"/>
        <table class="form-table redactor_options_group">
             <tr valign="top">
                 <th scope="row">Description<p class="helptext"><i>A short
                 description of the rule</i></p></th>
                 <td><input type="text" name="description" class="regular-text"
                     value="<?php print $data['description']?>" /></td>
             </tr>

             <tr valign="top">
                 <th scope="row">Pattern<p class="helptext"><i>Select the string
                 to redact in the post content.</i></p></th>
                 <td><input type="text" name="pattern" class="regular-text"
                     value="<?php print $data['pattern']?>" /></td>
             </tr>
         
             <tr valign="top">
                 <th scope="row">Allowed Roles<p class="helptext"><i>Select the 
                 roles that are allow to view redacted content if different than
                 site defaults.</i></p></th>
                 <td><select name="roles[]" multiple size="5" style="width: 350px">
                     <?php ds_dropdown_roles( $data['roles'] ) ?>
                 </select></td>
             </tr>
         </table>
         <?php
         wp_nonce_field( 'wp-redactor-add-new' );
         submit_button( 'Save New' );
         $this->page_footer();
    }

    /**
     * Display the page to add a new redaction
     *
     * @since 1.2.0
     * @access private
     */
    public function create_metrics_page()
    {
    	$defaults = array( 'metric_options' => array() );
    	
    	$data = array_merge( $defaults, $_POST );

    	$selected_report = null;
    	
    	// Check whether the button has been pressed AND also check the nonce
    	if (isset($_POST['view_report_button']) ) {
    		// the button has been pressed AND we've passed the security check
    		$selected_report = $_REQUEST['report_key'];
    	}
        $this->page_header( 'Metrics', admin_url( 'admin.php?page=wp-redactor-metrics' ) );
        ?>
        <p>Select a Metric Option from the dropdown menu and click 'Apply Metrics' to view the metric option.</p>
        <form action="" method="post">
	        <select name="report_key">
	        	<?php ds_dropdown_metrics( $selected_report ) ?>
	        </select>
	        <input type="hidden" value="true" name="view_report_button" />
		    <?php
		    	wp_nonce_field('view_report_button');
		        submit_button( 'View Report' ); ?>
		</form>
        <?php 
    	Metrics::get_instance()->render_report( $selected_report );
		$this->page_footer ();
	} 

    /**
     * Create an admin notification for display
     *
     * @since 1.2.0
     * @access public
     * 
     * @param String $type
     * @param String $message
     * @return null
     */
    private function add_notice( $type, $message )
    {
        $types = array( 'error', 'notice', 'success', 'updated', 'info' );
        $type = ( in_array( strtolower( $type ), $types ) ) ? $type : 'updated';

        if ( get_bloginfo('version') <= '4.1' ) {
        	if( $type == 'success') $type = 'updated';
        } else {
        	$type = "notice-{$type}";
        }
        
        $this->admin_notices[] = array( 'type' => $type, 'message' => $message );
    }

    /**
     * Display admin notifications
     *
     * @since 1.2.0
     * @access public
     */
    private function admin_notices()
    {
        if( ! is_array( $this->admin_notices ) ) return;

        foreach( $this->admin_notices as $notice ) {
        ?>
            <div id="message" class="<?php echo $notice['type']?> notice is-dismissible">
                <p><?php _e( $notice['message'], 'wp_redactor' ); ?></p>
            </div>
        <?php
        }
    }
}
