<?php
/**
 * The methods for all the database migrations
 */

/**
 * Run the incremental updates one by one.
 *
 * For example, if the current DB version is 3, and the target DB version is 6,
 * this function will execute update routines if they exist:
 *  - solis_update_routine_4()
 *  - solis_update_routine_5()
 *  - solis_update_routine_6()
 */
function wp_redactor_update() 
{
	// no PHP timeout for running updates
	set_time_limit( 0 );

	global $wp_redactor_plugin;

	// this is the current database schema version number
	$current_db_ver = intval( get_option( 'wp_redactor_db_ver' ) );

	// this is the target version that we need to reach
	$target_db_ver = WP_Redactor_Plugin::DB_VER;

	// run update routines one by one until the current version number
	// reaches the target version number
	while ( $current_db_ver < $target_db_ver ) {
		// increment the current db_ver by one
		$current_db_ver ++;

		// each db version will require a separate update function
		// for example, for db_ver 3, the function name should be solis_update_routine_3
		$func = "wp_redactor_update_{$current_db_ver}";
		if ( function_exists( $func ) ) {
			call_user_func( $func );
		}

		// update the option in the database, so that this process can always
		// pick up where it left off
		update_option( 'wp_redactor_db_ver', $current_db_ver );
	}
}

/**
 * Initial database
 */
function wp_redactor_update_1() 
{
	global $wpdb;
	
	$charset_collate = $wpdb->get_charset_collate();
	$table_name      = $wpdb->prefix . 'datasync_redactions';
	
	$sql = "CREATE TABLE {$table_name} (
	id int(11) NOT NULL AUTO_INCREMENT,
	dt_added timestamp NOT NULL,
	rx_redaction varchar(500) NOT NULL,
	str_username varchar(50) NOT NULL,
	str_groups varchar(500) NOT NULL,
	PRIMARY KEY  (id)
	) COLLATE {$charset_collate}; ";
	
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
}

/**
 * Cleanup of garbage in the database
 * Add redaction description field
 * Default description to whatever was in the redaction field
 */
function wp_redactor_update_2()
{
	global $wpdb;
	
	delete_option( 'wordactor_version' );
	
	$charset_collate = $wpdb->get_charset_collate();
	$table_name      = $wpdb->prefix . 'datasync_redactions';
		
	$sql = "CREATE TABLE {$table_name} (
	id int(11) NOT NULL AUTO_INCREMENT,
	str_description varchar(256) DEFAULT '' NOT NULL,
	dt_added timestamp NOT NULL,
	rx_redaction varchar(500) NOT NULL,
	str_username varchar(50) NOT NULL,
	str_groups varchar(500) NOT NULL,
	PRIMARY KEY  (id)
	) COLLATE {$charset_collate};";
	
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
	
	$sql = "UPDATE {$table_name} SET str_description = rx_redaction;";
	
	$wpdb->query( $sql );
}

/**
 * Add redaction count field
 */
function wp_redactor_update_3()
{
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();
	$table_name      = $wpdb->prefix . 'datasync_redactions';

	$sql = "CREATE TABLE {$table_name} (
	id int(11) NOT NULL AUTO_INCREMENT,
	str_description varchar(256) DEFAULT '' NOT NULL,
	dt_added timestamp NOT NULL,
	rx_redaction varchar(500) NOT NULL,
	str_username varchar(50) NOT NULL,
	int_redaction_count int(11) DEFAULT '0' NOT NULL,
	str_groups varchar(500) NOT NULL,
	PRIMARY KEY  (id),
	KEY userid (int_userid)
	) COLLATE {$charset_collate};";

	
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
		
	$sql = "DROP COLUMN str_username from {$table_name}";
	//$wpdb->query( $sql );
}
