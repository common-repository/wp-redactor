<?php
/**
 * The extension of the WP_List_Table class
 */
namespace Datasync\WP;

use Datasync\Redactor\Model;

if( ! defined( 'WP_REDACTOR' ) ) exit;

// load the list table class from the wordpress source
if( ! class_exists( '\WP_List_Table' ) ) {
	require( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

/**
 * List table implementation for redaction rules
 *
 * Extends the Wordpress WP_List_View class to provide an interactive
 * wordpress style table to manage the redactions.
 *
 * @since 1.2.0
 */
class RedactionsListTable extends \WP_List_Table
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
		$this->model = Model::get_instance();
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
		$redaction_count_results = $this->queryAndUpdateRedactionCount();
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
				'str_description'	=> 'Description',
				'rx_redaction' 	=> 'Pattern',
				'str_groups' 	=> 'Roles',
				'str_username'      => 'Creator',
				'dt_added'       	=> 'Added',
				'int_redaction_count' 	=> 'Total Redactions'
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
				'str_description' => array('str_description', false),
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
	 * @since 1.3.0
	 * @access protected
	 *
	 * @param array $item
	 * @return string
	 */
	protected function column_str_description( $item )
	{
		$current = urlencode( add_query_arg( NULL, NULL ) );
		$actions = array(
				'edit' => sprintf('<a href="?page=%s&id=%s&screen=edit&return_to=%s">Edit</a>',
						'wp-redactor',$item->id, $current),
				'delete' => sprintf('<a href="?page=%s&action=delete&id=%s&return_to=%s">Delete</a>',
						'wp-redactor',$item->id, $current),
		);

		return sprintf('%1$s %2$s', esc_html( $item->str_description ), $this->row_actions($actions) );
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
	 * Used to display the display name of the creator
	 *
	 * @since 1.4.0
	 * @access protected
	 *
	 * @param array $item
	 * @return string
	 */
	protected function column_str_username( $item )
	{
		$user = get_user_by( 'login', $item->str_username );
		return $user->display_name;
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
			case 'str_description':
			case 'rx_redaction':
			case 'str_username':
			case 'str_groups':
			case 'dt_added':
			case 'int_redaction_count':
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