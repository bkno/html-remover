<?php
	
/**
 * Plugin Name: HTML Remover
 * Plugin URI:  https://digitalgarden.co
 * Description: Strips HTML tags from post body. Supports posts, pages and LearnDash questions.
 * Author:      DigitalGarden
 * Author URI:  https://digitalgarden.co
 * Version:     1.0.0
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

$post_types = ['post', 'page', 'sfwd-question'];

foreach ( $post_types as $post_type ) {
	add_filter( 'bulk_actions-edit-' . $post_type, 'html_remover_bulk_action' );
	add_filter( 'handle_bulk_actions-edit-' . $post_type, 'html_remover_bulk_action_handler', 10, 3 );
}
 
function html_remover_bulk_action( $bulk_array ) {
	$bulk_array['html_remover'] = 'Remove HTML';
	return $bulk_array;
}
 
function html_remover_bulk_action_handler( $sendback, $doaction, $items ) {
	$sendback = remove_query_arg(
		array(
			'html_remover_status',
			'html_remover_total',
			'html_remover_count',
		),
		$sendback
	);
 
	$html_remover_count = 0;
	foreach ( $items as $post_id ) {
		$post = get_post( $post_id );
		$post_content_html_removed = wp_strip_all_tags( $post->post_content );
		if ( $post->post_content != $post_content_html_removed ) {
			if ( $post->post_type != 'sfwd-question' ) {
				/* Update the post content */
				$post_data = array(
					'ID' => $post_id,
					'post_content' => $post_content_html_removed,
				);
				wp_update_post( $post_data );
			} else {
				/* For LearnDash questions, the post content is stored in two tables so update though LearnDash API instead */
				$request = new WP_REST_Request( 'POST', '/ldlms/v1/sfwd-questions/' . $post_id );
				$request->set_param( '_question', $post_content_html_removed );
				$response = rest_do_request( $request );	
			}
			$html_remover_count++;
		}
	}

	$sendback = add_query_arg(
		array(
			'html_remover_status' => 'complete',
			'html_remover_total' => count( $items ),
			'html_remover_count' => $html_remover_count,
		),
		$sendback
	);
 
	return $sendback;
}

add_action( 'admin_notices', 'html_remover_bulk_action_notices' );
function html_remover_bulk_action_notices() {
	if ( ! empty( $_GET['html_remover_status'] ) ) {
		?>
		<div id="message" class="updated notice is-dismissible">
			<p>HTML Remover: <?php echo $_GET['html_remover_count'] . ' of ' . $_GET['html_remover_total'] . __( ' selected posts contained HTML and have been updated', 'html_remover' ); ?>.</p>
		</div>
		<?php
	}
}