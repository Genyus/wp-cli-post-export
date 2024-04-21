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

		$this->add_filters();
		$post_type = $assoc_args['post-type'];
		$taxonomies = explode( ',', $assoc_args['taxonomies'] );
		$valid_taxonomies = get_object_taxonomies( $post_type, 'names' );
		$filtered_taxonomies = array_intersect( $valid_taxonomies, $taxonomies );

		$query_args = array(
			'post_type' => $post_type,
			'posts_per_page' => 20, // Retrieve all posts
			// 'end_date' => '2025-04-15',
			// 'start_date' => '2019-04-15',
			'post_status' => array(), // array( 'publish', 'tribe-ea-success', 'tribe-ea-failed', 'tribe-ea-schedule', 'tribe-ea-pending', 'tribe-ea-draft', 'future', 'draft', 'pending', 'private' ),
		);

		$query = new \WP_Query( $query_args );
		$posts = $query->posts;//get_posts( $query_args );
		// WP_CLI::log( "Last SQL-Query: {$query->request}" );
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

	protected function add_filters() {
		if ( class_exists( 'Tribe__Events__Admin_List' ) ) {
			global $current_wp_query;
			// Logic for filtering events by aggregator record.
			WP_CLI::add_wp_hook( 'posts_clauses', array( \Tribe__Events__Admin_List::class, 'filter_by_aggregator_record' ), 98, 2 );

			// Logic for sorting events by event category or tags
			WP_CLI::add_wp_hook( 'posts_clauses', array( \Tribe__Events__Admin_List::class, 'sort_by_tax' ), 99, 2 );

			// Logic for sorting events by start or end date
			WP_CLI::add_wp_hook( 'posts_clauses', array( \Tribe__Events__Admin_List::class, 'sort_by_event_date' ), 99, 2 );

			WP_CLI::add_wp_hook( 'posts_fields', array( __CLASS__, 'events_search_fields' ), 99, 2 );

			// Pagination
			WP_CLI::add_wp_hook( 'post_limits', array( \Tribe__Events__Admin_List::class, 'events_search_limits' ), 99, 2 );

			WP_CLI::add_wp_hook(
				'pre_get_posts',
				function ( $query ) {
					global $current_wp_query;
					if ( $query->query['post_type'] == 'tribe_events' ) {
						$current_wp_query = $query;  // Store the query object
					}
				}
			);

			WP_CLI::add_wp_hook(
				'all',
				function () {
					global $current_wp_query;

					if ( ! isset( $current_wp_query ) ) {
						return;  // Ensure the WP_Query object is set
					}

					$tag = current_filter();
					// WP_CLI::log('current query: ' . var_export( $query->query['post_type'], true ) );
					// Optionally, check if the WP_Query object is related to the current operation
					if ( $current_wp_query->query['post_type'] == 'tribe_events' ) {
						// Perform specific actions
						WP_CLI::log( $tag . ': ' . $current_wp_query->query_vars );
					}
				}
			);

		}
	}

	/**
	 * Fields filter for standard wordpress templates.  Adds the start and end date to queries in the
	 * events category
	 *
	 * @param string   $fields The current fields query part.
	 * @param WP_Query $query
	 *
	 * @return string The modified form.
	 */
	public static function events_search_fields( $fields, $query ) {
		if ( $query->get( 'post_type' ) != \Tribe__Events__Main::POSTTYPE ) {
			return $fields;
		}

		$fields .= ', tribe_event_start_date.meta_value as EventStartDate, tribe_event_end_date.meta_value as EventEndDate ';
		WP_CLI::log( '**** fields: ' . $fields );
		return $fields;
	}
}
