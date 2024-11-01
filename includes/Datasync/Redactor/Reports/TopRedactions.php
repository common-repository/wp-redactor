<?php
/**
 * Report of top redactions
 */
namespace Datasync\Redactor\Reports;

if( ! defined( 'WP_REDACTOR' ) ) exit;

/**
 * Report of top redactions
 *
 * Displays report for list of top redactions. This is
 * limited to the top 25 redactions used.
 *
 * Example usage:
 * TopRedactions::Render();
 *
 * @package  	Redactor
 * @author   	Joseph M. King <michael.king@datasynctech.com>
 * @author   	Thane Durey <tdurey@harding.edu>
 * @copyright   2016 DataSync Technologies                                  
 * @license		GPLv2 or later                                                              
 * @access   	public
 * @since    	1.3.0
 */
class TopRedactions
{
	/**
	 * The main function to render the report
	 * 
	 * @access public
	 * @since 1.3.0
	 */
	public function Render()
	{
		global $wpdb;

		//prepare to query the Redaction table
		$table_name = $wpdb->prefix . WP_REDACTOR_TABLENAME;

		$top_redaction_results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY int_redaction_count DESC" );
		?>
        <table class="wp-list-table widefat">
		<thead>
				<tr>
					<th scope="row">Pattern</th>
					<th scope="row">Creator</th>
					<th scope="row">Added</th>
					<th scope="row">Total Redactions</th>
				</tr>
			</thead>
		
			<tbody style="background-color: #f9f9f9;">
				<?php foreach ( $top_redaction_results as $redaction ) {
					?>
				<tr valign="top" > <?php 
				$user = get_user_by( 'login', $redaction->str_username );
	    	echo '<td>' . esc_html( $redaction->str_description ) . '</td>';
	    	echo '<td>' . esc_html( $user->display_name ) . '</td>';
	    	echo '<td>' . esc_html( $redaction->dt_added ) . '</td>';
	    	echo '<td>' . esc_html( $redaction->int_redaction_count ) . '</td>';
	    	?>
	    	</tr>
	    	<?php 
		}?>
			</tbody>
			<tfoot>
				<tr>
					<th scope="row">Pattern</th>
					<th scope="row">Creator</th>
					<th scope="row">Added</th>
					<th scope="row">Total Redactions</th>
				</tr>
			</tfoot>
		</table>
			<?php 
	}
}
