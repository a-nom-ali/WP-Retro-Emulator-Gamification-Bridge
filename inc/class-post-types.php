<?php
/**
 * Custom Post Types registration.
 *
 * @package WP_Gamify_Bridge
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Gamify_Bridge_Post_Types
 */
class WP_Gamify_Bridge_Post_Types {

	/**
	 * Single instance of the class.
	 *
	 * @var WP_Gamify_Bridge_Post_Types
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return WP_Gamify_Bridge_Post_Types
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'register_post_types' ) );
	}

	/**
	 * Register custom post types.
	 */
	public function register_post_types() {
		$this->register_room_post_type();
		$this->register_event_post_type();
	}

	/**
	 * Register Room post type.
	 */
	private function register_room_post_type() {
		$labels = array(
			'name'                  => _x( 'Rooms', 'Post Type General Name', 'wp-gamify-bridge' ),
			'singular_name'         => _x( 'Room', 'Post Type Singular Name', 'wp-gamify-bridge' ),
			'menu_name'             => __( 'Rooms', 'wp-gamify-bridge' ),
			'name_admin_bar'        => __( 'Room', 'wp-gamify-bridge' ),
			'archives'              => __( 'Room Archives', 'wp-gamify-bridge' ),
			'attributes'            => __( 'Room Attributes', 'wp-gamify-bridge' ),
			'parent_item_colon'     => __( 'Parent Room:', 'wp-gamify-bridge' ),
			'all_items'             => __( 'All Rooms', 'wp-gamify-bridge' ),
			'add_new_item'          => __( 'Add New Room', 'wp-gamify-bridge' ),
			'add_new'               => __( 'Add New', 'wp-gamify-bridge' ),
			'new_item'              => __( 'New Room', 'wp-gamify-bridge' ),
			'edit_item'             => __( 'Edit Room', 'wp-gamify-bridge' ),
			'update_item'           => __( 'Update Room', 'wp-gamify-bridge' ),
			'view_item'             => __( 'View Room', 'wp-gamify-bridge' ),
			'view_items'            => __( 'View Rooms', 'wp-gamify-bridge' ),
			'search_items'          => __( 'Search Room', 'wp-gamify-bridge' ),
			'not_found'             => __( 'Not found', 'wp-gamify-bridge' ),
			'not_found_in_trash'    => __( 'Not found in Trash', 'wp-gamify-bridge' ),
			'featured_image'        => __( 'Featured Image', 'wp-gamify-bridge' ),
			'set_featured_image'    => __( 'Set featured image', 'wp-gamify-bridge' ),
			'remove_featured_image' => __( 'Remove featured image', 'wp-gamify-bridge' ),
			'use_featured_image'    => __( 'Use as featured image', 'wp-gamify-bridge' ),
			'insert_into_item'      => __( 'Insert into room', 'wp-gamify-bridge' ),
			'uploaded_to_this_item' => __( 'Uploaded to this room', 'wp-gamify-bridge' ),
			'items_list'            => __( 'Rooms list', 'wp-gamify-bridge' ),
			'items_list_navigation' => __( 'Rooms list navigation', 'wp-gamify-bridge' ),
			'filter_items_list'     => __( 'Filter rooms list', 'wp-gamify-bridge' ),
		);

		$args = array(
			'label'               => __( 'Room', 'wp-gamify-bridge' ),
			'description'         => __( 'Game rooms for multiplayer sessions', 'wp-gamify-bridge' ),
			'labels'              => $labels,
			'supports'            => array( 'title', 'author', 'custom-fields' ),
			'hierarchical'        => false,
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => 'gamify-bridge',
			'menu_position'       => 30,
			'menu_icon'           => 'dashicons-groups',
			'show_in_admin_bar'   => false,
			'show_in_nav_menus'   => false,
			'can_export'          => true,
			'has_archive'         => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'capability_type'     => 'post',
			'show_in_rest'        => true,
			'rest_base'           => 'gamify-rooms',
		);

		register_post_type( 'gamify_room', $args );
	}

	/**
	 * Register Event post type.
	 */
	private function register_event_post_type() {
		$labels = array(
			'name'                  => _x( 'Events', 'Post Type General Name', 'wp-gamify-bridge' ),
			'singular_name'         => _x( 'Event', 'Post Type Singular Name', 'wp-gamify-bridge' ),
			'menu_name'             => __( 'Events', 'wp-gamify-bridge' ),
			'name_admin_bar'        => __( 'Event', 'wp-gamify-bridge' ),
			'archives'              => __( 'Event Archives', 'wp-gamify-bridge' ),
			'attributes'            => __( 'Event Attributes', 'wp-gamify-bridge' ),
			'parent_item_colon'     => __( 'Parent Event:', 'wp-gamify-bridge' ),
			'all_items'             => __( 'Event Logs', 'wp-gamify-bridge' ),
			'add_new_item'          => __( 'Add New Event', 'wp-gamify-bridge' ),
			'add_new'               => __( 'Add New', 'wp-gamify-bridge' ),
			'new_item'              => __( 'New Event', 'wp-gamify-bridge' ),
			'edit_item'             => __( 'Edit Event', 'wp-gamify-bridge' ),
			'update_item'           => __( 'Update Event', 'wp-gamify-bridge' ),
			'view_item'             => __( 'View Event', 'wp-gamify-bridge' ),
			'view_items'            => __( 'View Events', 'wp-gamify-bridge' ),
			'search_items'          => __( 'Search Event', 'wp-gamify-bridge' ),
			'not_found'             => __( 'Not found', 'wp-gamify-bridge' ),
			'not_found_in_trash'    => __( 'Not found in Trash', 'wp-gamify-bridge' ),
			'featured_image'        => __( 'Featured Image', 'wp-gamify-bridge' ),
			'set_featured_image'    => __( 'Set featured image', 'wp-gamify-bridge' ),
			'remove_featured_image' => __( 'Remove featured image', 'wp-gamify-bridge' ),
			'use_featured_image'    => __( 'Use as featured image', 'wp-gamify-bridge' ),
			'insert_into_item'      => __( 'Insert into event', 'wp-gamify-bridge' ),
			'uploaded_to_this_item' => __( 'Uploaded to this event', 'wp-gamify-bridge' ),
			'items_list'            => __( 'Events list', 'wp-gamify-bridge' ),
			'items_list_navigation' => __( 'Events list navigation', 'wp-gamify-bridge' ),
			'filter_items_list'     => __( 'Filter events list', 'wp-gamify-bridge' ),
		);

		$args = array(
			'label'               => __( 'Event', 'wp-gamify-bridge' ),
			'description'         => __( 'Game event logs', 'wp-gamify-bridge' ),
			'labels'              => $labels,
			'supports'            => array( 'title', 'author', 'custom-fields' ),
			'hierarchical'        => false,
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => 'gamify-bridge',
			'menu_position'       => 30,
			'menu_icon'           => 'dashicons-list-view',
			'show_in_admin_bar'   => false,
			'show_in_nav_menus'   => false,
			'can_export'          => true,
			'has_archive'         => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'capability_type'     => 'post',
			'show_in_rest'        => true,
			'rest_base'           => 'gamify-events',
		);

		register_post_type( 'gamify_event', $args );
	}
}
