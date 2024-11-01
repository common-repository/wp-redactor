<?php
/**
 * Report of redactions by time period
 */
namespace Datasync\Redactor\Reports;

use \Datasync\Redactor\Metrics;

if( ! defined( 'WP_REDACTOR' ) ) exit;

/**
 * Report of redactions by time period
 *
 * Displays report for list of redactions broken down by time.
 * Report shows a different table for each year divided into
 * months.
 *
 * Example usage:
 * ByTimePeriod::Render();
 *
 * @package  	Redactor
 * @author   	Joseph M. King <michael.king@datasynctech.com>
 * @author   	Thane Durey <tdurey@harding.edu>
 * @copyright   2016 DataSync Technologies                                  
 * @license		GPLv2 or later                                                              
 * @access   	public
 * @since    	1.3.0
 */
class ByTimePeriod
{
	/**
	 * The main function to render the report
	 *
	 * @access public
	 * @since 1.3.0
	 */
	public function Render()
	{
		$metrics = $this->get_redactions_by_date();

		foreach( $metrics as $key=>$metrics_year ) {
				
			echo '<h3>' . $key . '</h3>';
			?>
		        <table class="wp-list-table widefat">
				<thead>
					<tr>
						<th scope="row" style="width: 150px">Month</th>
						<th scope="row">Redacted Posts</th>
						<th scope="row">Total Redactions</th>
					</tr>
				</thead>
			
				<tbody style="background-color: #f9f9f9;">
				
	    	<?php 
	    	ksort($metrics_year);
	    	foreach( $metrics_year as $month => $metrics ) {
	    		$month_name = date('F', mktime(0, 0, 0, intval($month), 10));
	    		?>
	    		<tr valign="top" > <?php
	    		echo '<td>' .  $month_name  . '</td>';
	    		echo '<td>' .  $metrics['posts']  . '</td>';
	    		echo '<td>' .  $metrics['redactions']  . '</td>';
	    		?>
	    		</tr>
	    		<?php 
	    	}	
	    	?>
			</tbody>
				
			<tfoot>
				<tr>
					<th scope="row">Month</th>
					<th scope="row">Total Redactions</th>
					<th scope="row">Total Redactions</th>
				</tr>
			</tfoot>
			</table>
			<br><br>
			<?php 
		}

	}
	
	/**
	 * Method to get redactins by date and sort them by year/month
	 * 
	 * @access public
	 * @since 1.3.0
	 * 
	 * @return array
	 */
	public function get_redactions_by_date()
	{
		$metrics = array();

		$args = array( 'numberposts' => -1 );
		
		foreach( get_posts( $args ) as $post ) {
			
			// TODO: don't want to run this every time
			Metrics::get_instance()->update_post_metrics( $post );
			
			$r_count = get_post_meta( $post->ID, '_redaction_count', true );
			
			$d = date_parse( $post->post_date );
				
			if( $r_count > 0 ) {
				
				$redaction_count = ( isset( $metrics[$d['year']][$d['month']]['redactions'] ))
					? $metrics[$d['year']][$d['month']]['redactions'] : $r_count;
				
				$post_count = ( isset( $metrics[$d['year']][$d['month']]['posts'] ))
					? $metrics[$d['year']][$d['month']]['posts'] : 1;
				
				$metrics[$d['year']][$d['month']]['redactions'] = 
					(int) $redaction_count + (int) $r_count;
				$metrics[$d['year']][$d['month']]['posts'] = 
					(int) $post_count + 1;
			}
		}
		
		// sort keys 
		krsort($metrics);

		return $metrics;
		
	}
}