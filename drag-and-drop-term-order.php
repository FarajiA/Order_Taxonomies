<?php
defined( 'ABSPATH' ) OR exit;

/*
	Plugin Name: Drag-and-Drop Term Ordering
	Plugin URI: https://github.com/FarajiA/Order_Taxonomies
	Description: Allows you to easily change the display order of terms (categories, etc.) on your site via a drag-and-drop interface.
	Version: 1
	Author: James Currie / wunderdojo based on the work of Risto Niinemets
	Author URI: http://wunderdojo.com
	License: GPLv2 or later
	License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

/** register an activation hook to run when the plugin is activated */
register_activation_hook(__FILE__, array( 'customTermOrder', 'network_activate' ) );
/** in case of network install on multisite, hook in to make sure the table is created. Passes the id of the new blog */
add_action('wpmu_new_blog', array('customTermOrder', 'create_table'), 10, 1);

class customTermOrder {
	/**
	 * Simple class constructor
	 */
	function __construct() {
		// admin initialize
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_init', array( $this, 'create_table' ) );
		// front-end initialize
		add_action( 'init', array( $this, 'init' ) );
	}

	function network_activate($networkwide) {
	global $wpdb;

	if (is_multisite()==true) {

		// check if it is a network activation - if so, run the activation function for each blog id
		if ($networkwide) {
			$old_blog = $wpdb->blogid;
			// Get all blog ids
			$blogids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
			foreach ($blogids as $blog_id) {
				switch_to_blog($blog_id);
				self::create_table();
			}
			switch_to_blog($old_blog);
			return;
		}
	}
	self::create_table();
}


	/**
	 * Create a custom dojo_term_order table to store the sort order
	 */
	 public static function create_table($blog_id=''){
		global $wpdb;
		$prefix = ($blogid!='') ? $wpdb->prefix.$blogid."_" : $wpdb->prefix;
		$table_name = "{$prefix}dojo_term_order";
		if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
			require_once(ABSPATH.'/wp-admin/includes/upgrade.php');
			$sql = "CREATE TABLE ".$table_name."(
			meta_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			term_id bigint(20) unsigned NOT NULL DEFAULT 0,
			meta_key varchar(255) DEFAULT NULL,
			meta_value int(11),
			PRIMARY KEY  (meta_id),
			KEY  term_id (term_id),
			KEY  meta_key (meta_key) )";
			dbDelta($sql);
		}
	 }

	/**
	 * Initialize administration
	 *
	 * @return void
	 */
	function admin_init() {
		// load scripts
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// load inline CSS style
		add_action( 'admin_print_styles', array( $this, 'print_styles' ) );

		// ajax to save the sorting
		add_action( 'wp_ajax_get_inline_boxes', array( $this, 'inline_edit_boxes' ) );

		/** properly filter the terms */
		//add_filter('terms_clauses', array($this, 'dojo_reorder_terms'), 10, 1);
	}

	/**
	 * Initialize front-page
	 *
	 * @return void
	 */
	function init() {
		// reorder terms when someone tries to get terms
		add_filter( 'terms_clauses', array( $this, 'dojo_reorder_terms' ) );
	}

	/**
	 * Load scripts
	 *
	 * @return void
	 */
	function enqueue_scripts() {
		// allow enqueue only on tags/taxonomy page
		if ( get_current_screen()->base != 'edit-tags' ) return;

		// load jquery and plugin's script
		wp_enqueue_script( 'termorder', plugins_url( 'drag-and-drop-term-order.js', __FILE__ ), array( 'jquery', 'jquery-ui-sortable' ) );
	}

	/**
	 * Print styles
	 *
	 * @return void
	 */
	function print_styles() {
		// show drag cursor
		echo '<style>.wp-list-table.tags td { cursor: move; }</style>';
	}

	/**
	 * Do the sorting
	 *
	 * @return void
	 */
	function inline_edit_boxes() {
		global $wpdb;
		$msg = FALSE;
		// loop through rows
		foreach ( $_POST['rows'] as $key => $row ) {
			// skip empty

			if ( !isset( $row ) || $row == "" ) continue;

			// update order
			try{
				$current_row = $wpdb->get_row("SELECT meta_id, term_id FROM {$wpdb->prefix}dojo_term_order WHERE term_id = {$row}");
				if(NULL != $current_row)
					$meta_id = $current_row->meta_id;

				if($result = $wpdb->replace($wpdb->prefix."dojo_term_order", array('meta_id'=>$meta_id, 'term_id'=>$row, 'meta_key'=>'term_order', 'meta_value'=> $key+1), array('%d', '%d', '%s', '%d'))){
					throw new Exception("Updating term order failed. The query attempted was ".$wpdb->last_query);
				}
			  }
			catch(Exception $e){
			 $msg = $e->getMessage()."  Error on line ".$e->getLine().' in '.$e->getFile();
			 }
			}
			if($msg){ echo json_encode(array('msg'=>$msg));}
			die(0);
	}

	/**
	 *	Reorder the taxonomy terms based on the custom table order
	 *  the get_terms_fields filter is found in wp_includes/taxonomies.php on line 1465
	 *  it passes an array, $pieces, with each of the query parameters: $fields, $join, $where, $orderby
	 */

	function dojo_reorder_terms($pieces){
		global $wpdb;
			$pieces['join'] .= " LEFT JOIN {$wpdb->prefix}dojo_term_order AS tto ON t.term_id = tto.term_id ";
			$pieces['orderby'] = 'ORDER BY tto.meta_value';
		return $pieces;
	}

}

/**
 * Uninstall the plugin
 *
 * @return void
 */
function dojo_uninstall_plugin() {
	// check if user has permissions
	if ( ! current_user_can( 'activate_plugins' ) ) return;

	/** delete the custom table */
}
//register_uninstall_hook( __FILE__, 'dojo_uninstall_plugin' );

// Start our plugin
new customTermOrder;
