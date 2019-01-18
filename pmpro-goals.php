<?php
/**
 * Plugin Name: Paid Memberships Pro Goal Progress
 * Description: Track Membership and Revenue Goals with Progress Bars.
 * Plugin URI: https://paidmembershipspro.com
 * Author: Stranger Studios
 * Author URI: https://paidmembershipspro.com
 * Version: 1.0
 * License: GPL2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: pmpro-goals
 * Domain Path: /languages
 * Network: false
 *
 * Paid Memberships Pro Goal Progress is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * Paid Memberships Pro Goal Progress is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Paid Memberships Pro Goal Progress. If not, see https://www.gnu.org/licenses/gpl-2.0.html.
 */

defined( 'ABSPATH' ) or exit;

function pmpro_goals_load_text_domain() {
	load_plugin_textdomain( 'pmpro-goals', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'pmpro_goals_load_text_domain' );


/**
 * Register gutenberg block.
 * @since 1.0
 */
function pmpro_goals_register_block() {

	// register script for Gutenberg
	wp_register_script( 
		'pmpro-goals-gutenberg', 
		plugins_url( 'js/gutenberg.build.js', __FILE__ ), 
		array( 'wp-blocks', 'wp-element', 'wp-editor' )
	);

	register_block_type( 'pmpro-goals/goal-progress', array(
		'editor_script' => 'pmpro-goals-gutenberg',
		'render_callback' => 'pmpro_goal_progress_bar_shortcode'
	) );

	add_shortcode( 'pmpro_goal', 'pmpro_goal_progress_bar_shortcode' );
}
add_action( 'init', 'pmpro_goals_register_block' );


function pmpro_goals_register_scripts() {

	wp_register_script( 'pmpro-goals-progress-js', plugins_url( '/js/goalProgress.js', __FILE__ ), array( 'jquery' ), '1.0.0', true );

}
add_action( 'wp_enqueue_scripts', 'pmpro_goals_register_scripts' );

// Show goals for PMPro levels funds raised. Quick example.
function pmpro_goal_progress_bar_shortcode( $atts ) {

	global $wpdb, $pmpro_currency_symbol;

	// enqueue script when shortcode is called.
	wp_enqueue_script( 'pmpro-goals-progress-js' );

	extract( shortcode_atts( array(
		'level' => NULL,
		'levels' => NULL,
		'goal' => NULL,
		'after' => NULL,
		'fill_color' => '#f7f7f7',
		'background_color' => '#ff008c',
		'font_color' => '#FFF',
		'type' => NULL, 
		'before' => NULL,
	), $atts ) );
	//if levels is used instead of level
	if ( isset( $levels ) && ! isset( $level ) ) {
		$level = $levels;
	}

	$goal = intval( $goal );
	$after = esc_attr( $after );
	$fill_color = esc_attr( $fill_color );
	$background_color = esc_attr( $background_color );
	$font_color = esc_attr( $font_color );
	$type = esc_attr( $type );
	$total = 0;
	$goal_reached = false;

	
	if ( empty( $levels ) ) {
		return "<span class='pmpro-warning'>" . __( 'Please insert a valid level(s)', 'pmpro-goals' ) . "</span>";
	}

	if ( empty( $type ) || 'members' !== $type ) {
		$type = 'revenue';
	}

	// This is used to create a level string that can be hashed.
	$levels_for_hash = '';
	if ( is_array( $levels ) ) {
		foreach( $levels as $key => $value ) {
			$levels_for_hash .= $value;
		}
	} else {
		$levels_for_hash = $levels;
	}

	// Check hash for transients.
	$to_hash = md5( $goal . $after . $fill_color . $background_color . $font_color . $type . $levels_for_hash );
	$hashkey = substr( $to_hash, 0, 10);

	if ( 'revenue' === $type ) {

		if ( false === get_transient( "pmpro_goals_" . $hashkey )  ) {

			$sql = "SELECT total FROM $wpdb->pmpro_membership_orders WHERE membership_id IN(" . implode(",", $levels) . ") AND status = 'success'";

			$results = $wpdb->get_results( $sql );

			if ( ! empty( $results ) && is_array( $results ) ) {
				foreach ( $results as $key => $value ) {
					$total += floatval( $value->total);
				}
			}

			if ( $total > 0 ) {
				$total = round( $total );
			} else {
				$total = 0;
			}

			set_transient( 'pmpro_goals_' . $hashkey, $total, 12 * HOUR_IN_SECONDS );	

		} else {
			$total = get_transient( 'pmpro_goals_' . $hashkey );
		}

		$after_total_amount_text =  ' / ' . $before . $goal . ' ' . $after;

	} else {

		if ( false === get_transient( "pmpro_goals_" . $hashkey )  ) {
			$sql = "SELECT COUNT(user_id) AS total FROM $wpdb->pmpro_memberships_users WHERE membership_id IN(" . implode( ",", $levels ) . ") AND status = 'active'"; 

			$total = $wpdb->get_var( $sql );

			set_transient( 'pmpro_goals_' . $hashkey, $total, 12 * HOUR_IN_SECONDS );	

		} else {

			$total = get_transient( "pmpro_goals_" . $hashkey );

		}

		$after_total_amount_text =  ' / ' . $goal . ' ' . $after;
	}

	/**
	 * Filter to adjust the text after the total amount inside the goal progress bar.
	 * @return string The text after the total amount.
	 * @since 1.0
	 */
	$after = apply_filters( 'pmpro_goals_after', $after_total_amount_text );
	ob_start();
	?>
	<script type="text/javascript">
		jQuery(document).ready(function(){
		    jQuery('#pmpro_goal_progress_bar').goalProgress({
		        goalAmount: <?php echo $goal; ?>,
		        currentAmount: <?php echo $total; ?>,
		        textBefore: "<?php echo $before; ?>",
		        textAfter: "<?php echo $after; ?> ",
		    });
		});
	</script>

	<style type="text/css">
		.goalProgress {
			background: <?php echo $background_color; ?>;
			margin-top:2%;
			margin-bottom:2%;
			padding: 5px;
		}
		div.progressBar {
			background: <?php echo $fill_color; ?>;
			color: <?php echo $font_color; ?>;
			font-size: 2rem;
			font-family: 'helvetica neue', helvetica, arial, sans-serif;
			letter-spacing: -1px;
			font-weight: 700;
			padding: 10px;
			display: block;
			width: 5px;
		}
	</style>	
	
	<div class="pmpro_goal_container">
		<?php do_action( 'pmpro_before_progress_bar' ); ?>
			<div id="pmpro_goal_progress_bar"></div>
		<?php do_action( 'pmpro_after_progress_bar' ); ?>
	</div>

<?php

	$shortcode_content = ob_get_clean();

	return $shortcode_content;
}


function pmpro_goals_delete_transients_when_orders() {
	pmpro_goals_delete_transients();
}
add_action( 'pmpro_added_order', 'pmpro_goals_delete_transients_when_orders' );
add_action( 'pmpro_updated_order', 'pmpro_goals_delete_transients_when_orders' );

function pmpro_goals_delete_transients() {
	global $wpdb;

	$sql = "SELECT `option_name` FROM $wpdb->options WHERE `option_name` LIKE '%_pmpro_goal_%'";

	$results = $wpdb->get_results( $sql );

	foreach( $results as $key => $value ) {
		if ( strpos( $value->option_name, 'timeout' ) === false ) {
			$transient = ltrim( $value->option_name, '_transient_' );
			delete_transient( $transient );
		}
	}
	
}