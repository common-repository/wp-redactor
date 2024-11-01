<?php
/**
 * Report of top redactors
 */
namespace Datasync\Redactor\Reports;

if( ! defined( 'WP_REDACTOR' ) ) exit;

/**
 * Report of top redactors
 *
 * Displays report for list of the top redactors. The report
 * is limited to the top 25 redactions
 *
 * Example usage:
 * TopRedactors::Render();
 *
 * @package  	Redactor
 * @author   	Joseph M. King <michael.king@datasynctech.com>
 * @author   	Thane Durey <tdurey@harding.edu>
 * @copyright   2016 DataSync Technologies                                  
 * @license		GPLv2 or later                                                              
 * @access   	public
 * @since    	1.3.0
 */
class TopRedactors
{
	/**
	 * The main function to render the report
	 *
	 * @access public
	 * @since 1.3.0
	 */
	public function Render()
	{
		global $wpdb, $blog_id;

		$table_name = $wpdb->prefix . 'usermeta';

		$top_users = $wpdb->get_results("SELECT user_id, meta_value FROM $table_name WHERE meta_key='redaction_count_{$blog_id}' ORDER BY meta_value DESC LIMIT 25" );

		$user = null;
		?>
        <table class="wp-list-table widefat">
		<thead>
			<tr>
				<th scope="row">User</th>
				<th scope="row">Login</th>
				<th scope="row">Redactions</th>
			</tr>
		</thead>
	
	<tbody style="background-color: #f9f9f9;">
				<?php foreach ( $top_users as $user_info ) {
					
					$user = get_user_by( 'id', $user_info->user_id );
					
					?>
				<tr valign="top" > <?php 
	    	echo '<td>' . esc_html( $user->display_name ) . '</td>';
	    	echo '<td>' . esc_html( $user->user_login ) . '</td>';
	    	echo '<td>' . esc_html( $user_info->meta_value ) . '</td>';
	    	?>
	    	</tr>
	    	<?php 
		}?>
			</tbody>
			
		<tfoot>
			<tr>
				<th scope="row">User</th>
				<th scope="row">Login</th>
				<th scope="row">Redactions</th>
			</tr>
		</tfoot>
		</table>
		<?php 
	}	
}
