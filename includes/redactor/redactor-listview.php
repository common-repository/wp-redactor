<?php

if( ! class_exists( 'WP_List_Table' ) ) {
    require( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

/**
 * The list view for managing redactions
 *
 * Manages all the interaction to add and remove redactions including
 * the admin menus, list view, add and remove pages.
 *
 * @since 1.2.0
 *
 * @link https://github.com/DataSyncTech/wp-redactor/issues/25
 * @link https://github.com/DataSyncTech/wp-redactor/issues/26
 *
 */
class RedactorListView
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
       $this->list_table = new Redactions_List_Table();
    }

    /**
     * Return the screen option value
     *
     * @since 1.2.0
     * @access public
     *
     * @return Mixed
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
    	$model = RedactorModel::get_instance();
    	
        $defaults = array( 'pattern' => '', 'roles' => array() );
        $data = array_merge( $defaults, $_POST );

        if( empty( $data['pattern'] ) ) {
            $this->add_notice( 'error', 'The pattern cannot be blank' );
        }
        
        if( $model->hasRule( $data['pattern'] ) ) {
        	$this->add_notice( 'error', 'The rule already exist' );
        }
        
        $error = ( bool ) count( $this->admin_notices );

        if( ! $error ) {
            $user = wp_get_current_user();
            if( false === $model->createRule( sanitize_text_field( $data['pattern'] ), 
                $data['roles'], $user->data->user_login ) ) {

                $this->add_notice( 'error', 'Rule <b>' . esc_html( $data['pattern'] ) . 
                    '</b>failed to create successfully' );

                return;
            }
       
            $_POST = array(); 
            $this->add_notice( 'success', 'New pattern <b>' . esc_html($data['pattern']) .
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
        $model = RedactorModel::get_instance();

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
    	
        $model = RedactorModel::get_instance();

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
        $model = RedactorModel::get_instance();

        $defaults = array( 'id' => null, 'pattern' => '', 'roles' => array() );
        $data = array_merge( $defaults, $_POST );

        if( empty( $data['id'] ) ) {
            $this->add_notice( 'error', 'The redaction id is invalid' );
        }

        if( empty( $data['pattern'] ) ) {
            $this->add_notice( 'error', 'The pattern cannot be blank' );
        }

        if( $model->hasRule( $data['pattern'], $data['id'] ) ) {
        	$this->add_notice( 'error', 'The rule already exist' );
        }
        
        $error = ( bool ) count( $this->admin_notices );

        if( ! $error ) {
            $user = wp_get_current_user();
            $model = RedactorModel::get_instance();
            if( false === $model->updateRule( $data['id'], 
                sanitize_text_field( $data['pattern'] ),
                $data['roles'], $user->data->user_login ) ) {
                	
                $this->screen = 'edit';
                	
                $this->add_notice( 'error', 'Rule ' . esc_html( $data['pattern'] ) .
                    'failed to update' );
            }

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
             <form method="post" action="<?php print $action ?>">
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
        $model = RedactorModel::get_instance();

        $id = isset( $_GET['id'] ) ? $_GET['id'] : false;

        if( ! $id || ! is_numeric( $id ) || null === $rule = $model->getRule( $id ) ) {
            $this->add_notice( 'error', 'Unable to find the item' );

            return;
        }

        $this->page_header( 'Delete Redaction' );

        print "<p>Confirm that you want to permanently delete the redaction rule ";
        print "<b>{$rule['rx_redaction']}</b>.</p>";

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
        $model = RedactorModel::get_instance();

        if( isset( $_POST['submit'] ) ) {

            $data = $_POST;
        } else {

            $id = isset( $_REQUEST['id'] ) ? $_REQUEST['id'] : false;

            if( ! $id || ! is_numeric( $id ) || null === $rule = $model->getRule( $id ) ) {
                $this->add_notice( 'error', 'Unable to find the item' );

                $this->list_table_page();
                return;
            }

            $data = array( 'id' => $id, 
                'pattern' => $rule['rx_redaction'],
                'roles' => @unserialize( $rule['str_groups'] ) );
        }

        $this->page_header( 'Modify Redaction' );
        
        ?>
        <p>Enter a string or regular expression and select the roles allowed to view the redaction. 
        <br>Administrators and Editors can always see the content.</p> 
        <table class="form-table">
             <tr valign="top">
                 <th scope="row">Pattern</th>
                 <td><input type="text" name="pattern" class="regular-text"
                     value="<?php print esc_attr($data['pattern']) ?>" /></td>
             </tr>

             <tr valign="top">
                 <th scope="row">Allowed Roles</th>
                 <td><select name="roles[]" multiple size="5" style="width: 350px">
                     <?php ds_dropdown_roles( $data['roles'] ) ?>
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
        $defaults = array( 'pattern' => '', 'roles' => array() );
        $data = array_merge( $defaults, $_POST );

        $this->page_header( 'Add Redaction', admin_url( 'admin.php?page=wp-redactor-add-new' ) );
        ?>
        <p>Enter a string or regular expression and select the roles allowed to view the redaction. 
        <br>Administrators and Editors can always see the content.</p> 
        <input type="hidden" name="action" value="add_new"/>
        <table class="form-table">
             <tr valign="top">
                 <th scope="row">Pattern</th>
                 <td><input type="text" name="pattern" class="regular-text"
                     value="<?php print $data['pattern']?>" /></td>
             </tr>
         
             <tr valign="top">
                 <th scope="row">Allowed Roles</th>
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

/**
 * List table implementation for redaction rules
 *
 * Extends the Wordpress WP_List_View class to provide an interactive
 * wordpress style table to manage the redactions.
 *
 * @since 1.2.0
 */
class Redactions_List_Table extends WP_List_Table
{
    /**
     * Data model
     *
     * @since 1.2.0
     * @access private
     */
    private $model = null;
    
    /**
     * Initialize class 
     *
     * @since 1.2.0
     */
    function __construct()
    {
        parent::__construct();
	$this->model = RedactorModel::get_instance();
    }

    /**
     * Utility function because data model want as offset
     *
     * @since 1.2.0
     * @access private
     * 
     * @param int $per_page
     * @return integer
     */
    private function get_pageoffset( $per_page )
    {
        return $per_page * ( $this->get_pagenum() - 1 );
    }

    /**
     * Prepare the items for the table to process
     *
     * @since 1.2.0
     * @access public
     *
     * @return Void
     */
    public function prepare_items()
    {
        $columns = $this->get_columns();
        $hidden = $this->get_hidden_columns();
        $sortable = $this->get_sortable_columns();

	    $per_page = $this->get_items_per_page('wp_redactor_per_page', 10);
        $offset = $this->get_pageoffset( $per_page );

        $search = isset( $_POST['s'] ) ? $_POST['s'] : null;
        
        $this->set_pagination_args( array(
            'total_items' => $this->model->getRuleRowCount( $search ),
            'per_page'    => $per_page
        ) );
        
        $orderby = isset( $_GET['orderby'] ) ? $_GET['orderby'] : 'id';
        $order = isset( $_GET['order'] ) ? strtoupper( $_GET['order'] ) : 'ASC';

        $this->_column_headers = $this->get_column_info();
    	$this->items = $this->model->getRawRuleRecordsEx( array(
            'search'  => $search,
            'offset'  => $offset,
            'limit'   => $per_page,
            'orderby' => $orderby,
            'order'   => $order
        ) );
    }

    /**
     * Override the parent columns method. Defines the columns to use in your 
     * listing table
     *
     * @since 1.2.0
     * @access public
     *
     * @return Array
     */
    public function get_columns()
    {
        $columns = array(
            'cb'		=> '<input type="checkbox" />',
            //'str_title'     	=> 'Title',
            //'str_description'	=> 'Description',
	        'rx_redaction' 	=> 'Pattern',
            'str_groups' 	=> 'Roles',
            'str_username'      => 'Creator',
            'dt_added'       	=> 'Added',
	    //'int_affected' 	=> 'Articles Affected'
        );

        return $columns;
    }

    /**
     * Define which columns are sortable
     *
     * @since 1.2.0
     * @access protected
     *
     * @return Array
     */
    public function get_sortable_columns() 
    {
        $sortable_columns = array(
            'rx_redaction'  => array('rx_redaction',false),
            'str_username'  => array('str_username',false),
            //'str_groups'    => array('str_groups',false),
            'dt_added'      => array('dt_added',false)
        );

        return $sortable_columns;
    }   

    /**
     * Define which columns are hidden
     *
     * @since 1.2.0
     * @access public
     *
     * @return Array
     */
    public function get_hidden_columns()
    {
        return array();
    }

    /**
     * Used to display the value of the id column
     *
     * @since 1.2.0
     * @access protected
     * 
     * @param array $item
     * @return string
     */
    protected function column_rx_redaction( $item )
    {
        $current = urlencode( add_query_arg( NULL, NULL ) );
        $actions = array(
            'edit' => sprintf('<a href="?page=%s&id=%s&screen=edit&return_to=%s">Edit</a>',
            	'wp-redactor',$item->id, $current),
            'delete' => sprintf('<a href="?page=%s&action=delete&id=%s&return_to=%s">Delete</a>',
		        'wp-redactor',$item->id, $current),
        );

        return sprintf('%1$s %2$s', esc_html( $item->rx_redaction ), $this->row_actions($actions) );
    }

    /**
     * Add a checkbox to the first column
     *
     * @since 1.2.0
     * @access protected
     * 
     * @param array $item
     * @return string
     */
    protected function column_cb( $item )
    {
        return sprintf(
            '<input type="checkbox" name="redaction[]" value="%s" />', 
		$item->id
        );    
    }

    /**
     * Used to display the list of roles allowed to view the redaction
     *
     * @since 1.2.0
     * @access protected
     *
     * @param array $item
     * @return string
     */
    protected function column_str_groups( $item )
    {
        $display = $item->str_groups;

        if( false !== $selected_roles = @unserialize( $item->str_groups ) ) {
        
            $roles = get_editable_roles();

            $display = array();

            foreach( $selected_roles as $role ) {

                if( isset( $roles[$role] ) ) {

                    $display[] = $roles[$role]['name'];
                }
            }
            asort( $display );
        }

        return is_array( $display ) ? implode( ', ', $display ) : $display;

    }

   /**
     * Define what data to show on each column of the table
     *
     * @since 1.2.0
     * @access protected
     *
     * @param  Array $item        Data
     * @param  String $column_name - Current column name
     *
     * @return Mixed
     */
    protected function column_default( $item, $column_name )
    {
        switch( $column_name ) {
            case 'id':
            //case 'str_title':
            //case 'str_description':
	    case 'rx_redaction':
            case 'str_username':
            case 'str_groups':
            case 'dt_added':
		return esc_html($item->$column_name);
            default:
		return '';
        }
    }

    /**
     * Define the available bulk actions
     *
     * @since 1.2.0
     * @access protected
     *
     * @return Mixed
     */
    public function get_bulk_actions() 
    {
        $actions = array(
            'bulk_delete'    => 'Delete'
        );
  
        return $actions;
    }
}

RedactorListView::get_instance();
