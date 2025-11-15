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
		add_action( 'init', array( $this, 'register_taxonomies' ) );
		add_action( 'init', array( $this, 'register_rom_meta' ) );
	}

	/**
	 * Register custom post types.
	 */
	public function register_post_types() {
		$this->register_room_post_type();
		$this->register_event_post_type();
		$this->register_rom_post_type();
	}

	/**
	 * Register taxonomies.
	 */
	public function register_taxonomies() {
		$this->register_rom_taxonomies();
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

	/**
	 * Register ROM post type for managed emulator assets.
	 */
	private function register_rom_post_type() {
		$labels = array(
			'name'                  => _x( 'ROM Library', 'Post Type General Name', 'wp-gamify-bridge' ),
			'singular_name'         => _x( 'ROM', 'Post Type Singular Name', 'wp-gamify-bridge' ),
			'menu_name'             => __( 'ROM Library', 'wp-gamify-bridge' ),
			'name_admin_bar'        => __( 'ROM', 'wp-gamify-bridge' ),
			'archives'              => __( 'ROM Archives', 'wp-gamify-bridge' ),
			'attributes'            => __( 'ROM Attributes', 'wp-gamify-bridge' ),
			'parent_item_colon'     => __( 'Parent ROM:', 'wp-gamify-bridge' ),
			'all_items'             => __( 'All ROMs', 'wp-gamify-bridge' ),
			'add_new_item'          => __( 'Add New ROM', 'wp-gamify-bridge' ),
			'add_new'               => __( 'Add New', 'wp-gamify-bridge' ),
			'new_item'              => __( 'New ROM', 'wp-gamify-bridge' ),
			'edit_item'             => __( 'Edit ROM', 'wp-gamify-bridge' ),
			'update_item'           => __( 'Update ROM', 'wp-gamify-bridge' ),
			'view_item'             => __( 'View ROM', 'wp-gamify-bridge' ),
			'view_items'            => __( 'View ROMs', 'wp-gamify-bridge' ),
			'search_items'          => __( 'Search ROM Library', 'wp-gamify-bridge' ),
			'not_found'             => __( 'No ROMs found', 'wp-gamify-bridge' ),
			'not_found_in_trash'    => __( 'No ROMs found in Trash', 'wp-gamify-bridge' ),
			'featured_image'        => __( 'Cover Art', 'wp-gamify-bridge' ),
			'set_featured_image'    => __( 'Set cover art', 'wp-gamify-bridge' ),
			'remove_featured_image' => __( 'Remove cover art', 'wp-gamify-bridge' ),
			'use_featured_image'    => __( 'Use as cover art', 'wp-gamify-bridge' ),
			'insert_into_item'      => __( 'Insert into ROM', 'wp-gamify-bridge' ),
			'uploaded_to_this_item' => __( 'Uploaded to this ROM', 'wp-gamify-bridge' ),
			'items_list'            => __( 'ROM list', 'wp-gamify-bridge' ),
			'items_list_navigation' => __( 'ROM list navigation', 'wp-gamify-bridge' ),
			'filter_items_list'     => __( 'Filter ROM list', 'wp-gamify-bridge' ),
		);

		$args = array(
			'label'               => __( 'ROM', 'wp-gamify-bridge' ),
			'description'         => __( 'Retro emulator ROM definitions and metadata', 'wp-gamify-bridge' ),
			'labels'              => $labels,
			'supports'            => array( 'title', 'editor', 'thumbnail', 'author', 'custom-fields' ),
			'hierarchical'        => false,
			'public'              => true,
			'show_ui'             => true,
			'show_in_menu'        => 'gamify-bridge',
			'menu_position'       => 40,
			'menu_icon'           => 'dashicons-archive',
			'show_in_admin_bar'   => false,
			'show_in_nav_menus'   => false,
			'can_export'          => true,
			'has_archive'         => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'capability_type'     => 'post',
			'show_in_rest'        => true,
			'rest_base'           => 'retro-roms',
			'map_meta_cap'        => true,
		);

		register_post_type( 'retro_rom', $args );
	}

	/**
	 * Register ROM taxonomies (system, difficulty, multiplayer mode).
	 */
	private function register_rom_taxonomies() {
		$system_labels = array(
			'name'              => _x( 'Systems', 'Taxonomy General Name', 'wp-gamify-bridge' ),
			'singular_name'     => _x( 'System', 'Taxonomy Singular Name', 'wp-gamify-bridge' ),
			'search_items'      => __( 'Search Systems', 'wp-gamify-bridge' ),
			'all_items'         => __( 'All Systems', 'wp-gamify-bridge' ),
			'parent_item'       => __( 'Parent System', 'wp-gamify-bridge' ),
			'parent_item_colon' => __( 'Parent System:', 'wp-gamify-bridge' ),
			'edit_item'         => __( 'Edit System', 'wp-gamify-bridge' ),
			'update_item'       => __( 'Update System', 'wp-gamify-bridge' ),
			'add_new_item'      => __( 'Add New System', 'wp-gamify-bridge' ),
			'new_item_name'     => __( 'New System Name', 'wp-gamify-bridge' ),
			'menu_name'         => __( 'Systems', 'wp-gamify-bridge' ),
		);

		register_taxonomy(
			'retro_system',
			array( 'retro_rom' ),
			array(
				'labels'            => $system_labels,
				'hierarchical'      => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'show_in_quick_edit'=> false,
				'rewrite'           => false,
				'capabilities'      => array(
					'manage_terms' => 'manage_options',
					'edit_terms'   => 'manage_options',
					'delete_terms' => 'manage_options',
					'assign_terms' => 'edit_posts',
				),
			)
		);

		$difficulty_labels = array(
			'name'          => _x( 'Difficulties', 'Taxonomy General Name', 'wp-gamify-bridge' ),
			'singular_name' => _x( 'Difficulty', 'Taxonomy Singular Name', 'wp-gamify-bridge' ),
			'search_items'  => __( 'Search Difficulties', 'wp-gamify-bridge' ),
			'all_items'     => __( 'All Difficulties', 'wp-gamify-bridge' ),
			'edit_item'     => __( 'Edit Difficulty', 'wp-gamify-bridge' ),
			'update_item'   => __( 'Update Difficulty', 'wp-gamify-bridge' ),
			'add_new_item'  => __( 'Add New Difficulty', 'wp-gamify-bridge' ),
			'new_item_name' => __( 'New Difficulty Name', 'wp-gamify-bridge' ),
			'menu_name'     => __( 'Difficulties', 'wp-gamify-bridge' ),
		);

		register_taxonomy(
			'retro_difficulty',
			array( 'retro_rom' ),
			array(
				'labels'            => $difficulty_labels,
				'hierarchical'      => false,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'show_in_quick_edit'=> false,
				'rewrite'           => false,
				'capabilities'      => array(
					'manage_terms' => 'manage_options',
					'edit_terms'   => 'manage_options',
					'delete_terms' => 'manage_options',
					'assign_terms' => 'edit_posts',
				),
			)
		);

		$mode_labels = array(
			'name'          => _x( 'Multiplayer Modes', 'Taxonomy General Name', 'wp-gamify-bridge' ),
			'singular_name' => _x( 'Multiplayer Mode', 'Taxonomy Singular Name', 'wp-gamify-bridge' ),
			'search_items'  => __( 'Search Multiplayer Modes', 'wp-gamify-bridge' ),
			'all_items'     => __( 'All Multiplayer Modes', 'wp-gamify-bridge' ),
			'edit_item'     => __( 'Edit Multiplayer Mode', 'wp-gamify-bridge' ),
			'update_item'   => __( 'Update Multiplayer Mode', 'wp-gamify-bridge' ),
			'add_new_item'  => __( 'Add New Multiplayer Mode', 'wp-gamify-bridge' ),
			'new_item_name' => __( 'New Multiplayer Mode', 'wp-gamify-bridge' ),
			'menu_name'     => __( 'Multiplayer Modes', 'wp-gamify-bridge' ),
		);

		register_taxonomy(
			'retro_multiplayer_mode',
			array( 'retro_rom' ),
			array(
				'labels'            => $mode_labels,
				'hierarchical'      => false,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'show_in_quick_edit'=> false,
				'rewrite'           => false,
				'capabilities'      => array(
					'manage_terms' => 'manage_options',
					'edit_terms'   => 'manage_options',
					'delete_terms' => 'manage_options',
					'assign_terms' => 'edit_posts',
				),
			)
		);
	}

	/**
	 * Register ROM post meta definitions.
	 */
	public function register_rom_meta() {
		$meta_fields = array(
			'_retro_rom_adapter'          => array(
				'type'              => 'string',
				'description'       => __( 'Adapter slug that should boot this ROM', 'wp-gamify-bridge' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'_retro_rom_source'           => array(
				'type'              => 'string',
				'description'       => __( 'ROM source reference (attachment ID, URL, or bucket key)', 'wp-gamify-bridge' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'_retro_rom_checksum'         => array(
				'type'              => 'string',
				'description'       => __( 'Checksum hash for integrity verification', 'wp-gamify-bridge' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'_retro_rom_file_size'        => array(
				'type'        => 'integer',
				'description' => __( 'File size in bytes', 'wp-gamify-bridge' ),
			),
			'_retro_rom_release_year'     => array(
				'type'        => 'integer',
				'description' => __( 'Original release year', 'wp-gamify-bridge' ),
			),
			'_retro_rom_publisher'        => array(
				'type'              => 'string',
				'description'       => __( 'Publisher / Developer credit', 'wp-gamify-bridge' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'_retro_rom_notes'            => array(
				'type'              => 'string',
				'description'       => __( 'Internal notes or legal notices', 'wp-gamify-bridge' ),
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'_retro_rom_gamification'     => array(
				'type'        => 'array',
				'description' => __( 'Per-ROM gamification overrides (XP multipliers, badges)', 'wp-gamify-bridge' ),
			),
			'_retro_rom_control_profile'  => array(
				'type'        => 'array',
				'description' => __( 'Custom control layout metadata', 'wp-gamify-bridge' ),
			),
			'_retro_rom_touch_settings'   => array(
				'type'        => 'array',
				'description' => __( 'Touch control visibility/sensitivity preferences', 'wp-gamify-bridge' ),
			),
			'_retro_rom_save_state'       => array(
				'type'        => 'boolean',
				'description' => __( 'Whether save-state support is enabled for this ROM', 'wp-gamify-bridge' ),
				'default'     => false,
			),
		);

		foreach ( $meta_fields as $meta_key => $schema ) {
			register_post_meta(
				'retro_rom',
				$meta_key,
				array(
					'single'            => true,
					'type'              => isset( $schema['type'] ) ? $schema['type'] : 'string',
					'description'       => isset( $schema['description'] ) ? $schema['description'] : '',
					'default'           => isset( $schema['default'] ) ? $schema['default'] : null,
					'sanitize_callback' => isset( $schema['sanitize_callback'] ) ? $schema['sanitize_callback'] : null,
					'show_in_rest'      => true,
					'auth_callback'     => function() {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}
	}
}
