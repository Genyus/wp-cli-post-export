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
		global $wpdb;

		$post_type = $assoc_args['post-type'];
		$taxonomies = explode( ',', $assoc_args['taxonomies'] );
		$valid_taxonomies = get_object_taxonomies( $post_type, 'names' );
		$filtered_taxonomies = array_intersect( $valid_taxonomies, $taxonomies );
		$sql =
			"SELECT
		SQL_CALC_FOUND_ROWS {$wpdb->prefix}posts.*,
		tribe_event_start_date.meta_value as EventStartDate,
		tribe_event_end_date.meta_value as EventEndDate
	FROM
		{$wpdb->prefix}posts
		LEFT JOIN {$wpdb->prefix}postmeta AS tribe_event_start_date ON {$wpdb->prefix}posts.ID = tribe_event_start_date.post_id
		AND tribe_event_start_date.meta_key = '_EventStartDate'
		LEFT JOIN {$wpdb->prefix}postmeta AS tribe_event_end_date ON {$wpdb->prefix}posts.ID = tribe_event_end_date.post_id
		AND tribe_event_end_date.meta_key = '_EventEndDate'
	WHERE
		1 = 1
		AND (
			(
				{$wpdb->prefix}posts.post_type = 'tribe_events'
				AND (
					{$wpdb->prefix}posts.post_status = 'publish'
					OR {$wpdb->prefix}posts.post_status = 'tribe-ea-success'
					OR {$wpdb->prefix}posts.post_status = 'tribe-ea-failed'
					OR {$wpdb->prefix}posts.post_status = 'tribe-ea-schedule'
					OR {$wpdb->prefix}posts.post_status = 'tribe-ea-pending'
					OR {$wpdb->prefix}posts.post_status = 'tribe-ea-draft'
					OR {$wpdb->prefix}posts.post_status = 'future'
					OR {$wpdb->prefix}posts.post_status = 'draft'
					OR {$wpdb->prefix}posts.post_status = 'pending'
					OR {$wpdb->prefix}posts.post_status = 'private'
				)
			)
		)
		AND post_parent = 0
	ORDER BY
		tribe_event_start_date.meta_value DESC,
		tribe_event_end_date.meta_value DESC,
		{$wpdb->prefix}posts.post_date DESC
	LIMIT
		%d, %d";
		$data = array();
		$posts = array();
		$page_number = 1;
		$records_per_page = 20;

		do {
				$offset = ( $page_number - 1 ) * $records_per_page;
				$prepared_sql = $wpdb->prepare(
					$sql, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,
					$offset,
					$records_per_page
				);
				$posts = $wpdb->get_results( $prepared_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

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
			$page_number++;
		} while ( ! empty( $posts ) );

		$formatter = new Formatter( $assoc_args, array_merge( array( 'ID', 'post_title', 'post_content' ), $filtered_taxonomies ) );
		$formatter->display_items( $data );
	}
}
