<?php
/**
 * Implements WP-CLI commands for Bedrock-based WP instances
 *
 * @package PostExportSubCommand
 */

namespace Ingenyus\WP_CLI;

use WP_CLI;
use WP_CLI\CommandWithDBObject;
use WP_CLI\Formatter;

/**
 * Command to export posts along with their taxonomy terms.
 */
class Post_Export_Subcommand extends CommandWithDBObject {


	/**
	 * Exports posts of specified type and their taxonomy terms.
	 *
	 * ## OPTIONS
	 *
	 * [--post-type=<post-type>]
	 * : The post type to export.
	 * ---
	 * default: post
	 * ---
	 *
	 * [--taxonomies=<taxonomies>]
	 * : Comma-separated list of taxonomies.
	 * ---
	 * default: category,tag
	 * ---
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: json
	 * options:
	 *   - json
	 *   - csv
	 *   - xml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 * wp post export --post-type=post --taxonomies=category,tag --format=csv
	 *
	 * @when after_wp_load
	 */
	public function export( $args, $assoc_args ) {
		$post_type = $assoc_args['post-type'];
		$taxonomies = explode( ',', $assoc_args['taxonomies'] );
        $valid_taxonomies = get_object_taxonomies($post_type, 'names');
        $filtered_taxonomies = array_intersect($valid_taxonomies, $taxonomies);
		$query_args = array(
			'post_type' => $post_type,
			'posts_per_page' => -1, // Retrieve all posts
		);
		$posts = get_posts( $query_args );
		$data = array();

		foreach ( $posts as $post ) {
			$post_data = array(
				'ID' => $post->ID,
				'post_title' => $post->post_title,
				'post_content' => $post->post_content,
			);

			foreach ( $filtered_taxonomies as $taxonomy ) {
				$terms = wp_get_post_terms( $post->ID, $taxonomy, array( 'fields' => 'names' ) );
				$post_data[ $taxonomy ] = implode( ', ', $terms );
			}

			$data[] = $post_data;
		}

		$formatter = new Formatter( $assoc_args, array_merge( array( 'ID', 'post_title', 'post_content' ), $filtered_taxonomies ) );
		$formatter->display_items( $data );
	}
}
