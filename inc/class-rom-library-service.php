<?php
/**
 * ROM Library service helpers.
 *
 * @package WP_Gamify_Bridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Gamify_Bridge_Rom_Library_Service
 *
 * Provides read-only helpers for ROM metadata used across REST endpoints and frontend scripts.
 */
class WP_Gamify_Bridge_Rom_Library_Service {

	/**
	 * Fetch ROM collection with pagination aware response.
	 *
	 * @param array $args Query args (posts_per_page, paged, search, adapter, system).
	 * @return array
	 */
	public static function get_collection( $args = array() ) {
		$query = self::build_query( $args );

		$items = array();
		foreach ( $query->posts as $post ) {
			$items[] = self::format_rom( $post );
		}

		return array(
			'items'       => $items,
			'total'       => (int) $query->found_posts,
			'total_pages' => (int) $query->max_num_pages,
		);
	}

	/**
	 * Get ROM list without pagination payload (useful for localization).
	 *
	 * @param array $args Query args.
	 * @return array
	 */
	public static function get_list( $args = array() ) {
		$args  = wp_parse_args(
			$args,
			array(
				'posts_per_page' => 100,
				'paged'          => 1,
			)
		);
		$query = self::build_query( $args );

		$items = array();
		foreach ( $query->posts as $post ) {
			$items[] = self::format_rom( $post );
		}

		return $items;
	}

	/**
	 * Get a single ROM by ID.
	 *
	 * @param int $rom_id ROM post ID.
	 * @return array|WP_Error
	 */
	public static function get_rom( $rom_id ) {
		$post = get_post( $rom_id );

		if ( ! $post || 'retro_rom' !== $post->post_type ) {
			return new WP_Error(
				'rom_not_found',
				__( 'ROM not found.', 'wp-gamify-bridge' ),
				array( 'status' => 404 )
			);
		}

		return self::format_rom( $post );
	}

	/**
	 * Build WP_Query for ROMs.
	 *
	 * @param array $args Query args.
	 * @return WP_Query
	 */
	private static function build_query( $args = array() ) {
		$defaults = array(
			'post_type'      => 'retro_rom',
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'paged'          => 1,
			's'              => '',
		);

		$args = wp_parse_args( $args, $defaults );

		$tax_query = array();

		if ( ! empty( $args['system'] ) ) {
			$tax_query[] = array(
				'taxonomy' => 'retro_system',
				'field'    => 'slug',
				'terms'    => (array) $args['system'],
			);
		}

		if ( ! empty( $args['difficulty'] ) ) {
			$tax_query[] = array(
				'taxonomy' => 'retro_difficulty',
				'field'    => 'slug',
				'terms'    => (array) $args['difficulty'],
			);
		}

		if ( ! empty( $args['multiplayer_mode'] ) ) {
			$tax_query[] = array(
				'taxonomy' => 'retro_multiplayer_mode',
				'field'    => 'slug',
				'terms'    => (array) $args['multiplayer_mode'],
			);
		}

		if ( ! empty( $tax_query ) ) {
			$args['tax_query'] = $tax_query;
		}

		if ( ! empty( $args['adapter'] ) ) {
			$args['meta_query'][] = array(
				'key'     => '_retro_rom_adapter',
				'value'   => (array) $args['adapter'],
				'compare' => 'IN',
			);
		}

		return new WP_Query( $args );
	}

	/**
	 * Format ROM data for output.
	 *
	 * @param WP_Post|int $post Post object or ID.
	 * @return array
	 */
	public static function format_rom( $post ) {
		$post = get_post( $post );

		if ( ! $post ) {
			return array();
		}

		$adapter        = get_post_meta( $post->ID, '_retro_rom_adapter', true );
		$rom_source     = get_post_meta( $post->ID, '_retro_rom_source', true );
		$file_size      = (int) get_post_meta( $post->ID, '_retro_rom_file_size', true );
		$release_year   = get_post_meta( $post->ID, '_retro_rom_release_year', true );
		$publisher      = get_post_meta( $post->ID, '_retro_rom_publisher', true );
		$checksum       = get_post_meta( $post->ID, '_retro_rom_checksum', true );
		$notes          = get_post_meta( $post->ID, '_retro_rom_notes', true );
		$gamification   = get_post_meta( $post->ID, '_retro_rom_gamification', true );
		$control_layout = get_post_meta( $post->ID, '_retro_rom_control_profile', true );
		$touch_settings = get_post_meta( $post->ID, '_retro_rom_touch_settings', true );
		$save_state     = (bool) get_post_meta( $post->ID, '_retro_rom_save_state', true );

		$rom_url = self::resolve_rom_url( $rom_source );

		return array(
			'id'              => $post->ID,
			'title'           => get_the_title( $post ),
			'slug'            => $post->post_name,
			'adapter'         => $adapter,
			'rom_source'      => $rom_source,
			'rom_url'         => $rom_url,
			'systems'         => self::get_term_names( $post->ID, 'retro_system' ),
			'difficulties'    => self::get_term_names( $post->ID, 'retro_difficulty' ),
			'multiplayer'     => self::get_term_names( $post->ID, 'retro_multiplayer_mode' ),
			'file_size'       => $file_size,
			'file_size_human' => $file_size ? size_format( $file_size ) : null,
			'release_year'    => $release_year ? (int) $release_year : null,
			'publisher'       => $publisher,
			'checksum'        => $checksum,
			'notes'           => $notes,
			'gamification'    => self::maybe_decode( $gamification ),
			'controls'        => self::maybe_decode( $control_layout ),
			'touch'           => self::maybe_decode( $touch_settings ),
			'save_state'      => $save_state,
			'updated'         => get_post_modified_time( 'c', true, $post ),
		);
	}

	/**
	 * Resolve ROM URL from stored source reference.
	 *
	 * @param string $source Source reference.
	 * @return string|null
	 */
	private static function resolve_rom_url( $source ) {
		if ( empty( $source ) ) {
			return null;
		}

		if ( is_numeric( $source ) ) {
			return wp_get_attachment_url( (int) $source );
		}

		if ( filter_var( $source, FILTER_VALIDATE_URL ) ) {
			return esc_url_raw( $source );
		}

		$uploads = wp_upload_dir();

		return trailingslashit( $uploads['baseurl'] ) . ltrim( $source, '/' );
	}

	/**
	 * Maybe decode JSON stored values.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	private static function maybe_decode( $value ) {
		if ( empty( $value ) ) {
			return null;
		}

		if ( is_array( $value ) ) {
			return $value;
		}

		$decoded = json_decode( $value, true );

		return ( json_last_error() === JSON_ERROR_NONE ) ? $decoded : $value;
	}

	/**
	 * Get term names for taxonomy.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @return array
	 */
	private static function get_term_names( $post_id, $taxonomy ) {
		$terms = wp_get_object_terms(
			$post_id,
			$taxonomy,
			array(
				'fields' => 'names',
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		return $terms;
	}
}
