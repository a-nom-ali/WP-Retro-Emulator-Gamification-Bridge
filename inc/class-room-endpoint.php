<?php
/**
 * Room REST API endpoints.
 *
 * @package WP_Gamify_Bridge
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Gamify_Bridge_Room_Endpoint
 */
class WP_Gamify_Bridge_Room_Endpoint {

	/**
	 * Single instance of the class.
	 *
	 * @var WP_Gamify_Bridge_Room_Endpoint
	 */
	private static $instance = null;

	/**
	 * Room manager instance.
	 *
	 * @var WP_Gamify_Bridge_Room_Manager
	 */
	private $room_manager;

	/**
	 * Get the singleton instance.
	 *
	 * @return WP_Gamify_Bridge_Room_Endpoint
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
		$this->room_manager = WP_Gamify_Bridge_Room_Manager::instance();
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		// List/create rooms.
		register_rest_route(
			'gamify/v1',
			'/room',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_rooms' ),
					'permission_callback' => array( $this, 'check_logged_in' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_room' ),
					'permission_callback' => array( $this, 'check_logged_in' ),
					'args'                => $this->get_create_room_args(),
				),
			)
		);

		// Get/update/delete specific room.
		register_rest_route(
			'gamify/v1',
			'/room/(?P<id>[a-zA-Z0-9-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_room' ),
					'permission_callback' => array( $this, 'check_logged_in' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_room' ),
					'permission_callback' => array( $this, 'check_room_permission' ),
					'args'                => $this->get_update_room_args(),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_room' ),
					'permission_callback' => array( $this, 'check_room_permission' ),
				),
			)
		);

		// Join room.
		register_rest_route(
			'gamify/v1',
			'/room/(?P<id>[a-zA-Z0-9-]+)/join',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'join_room' ),
				'permission_callback' => array( $this, 'check_logged_in' ),
			)
		);

		// Leave room.
		register_rest_route(
			'gamify/v1',
			'/room/(?P<id>[a-zA-Z0-9-]+)/leave',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'leave_room' ),
				'permission_callback' => array( $this, 'check_logged_in' ),
			)
		);

		// Update presence.
		register_rest_route(
			'gamify/v1',
			'/room/(?P<id>[a-zA-Z0-9-]+)/presence',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'update_presence' ),
				'permission_callback' => array( $this, 'check_logged_in' ),
			)
		);

		// Get room players.
		register_rest_route(
			'gamify/v1',
			'/room/(?P<id>[a-zA-Z0-9-]+)/players',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_players' ),
				'permission_callback' => array( $this, 'check_logged_in' ),
			)
		);

		// Get room stats.
		register_rest_route(
			'gamify/v1',
			'/room/(?P<id>[a-zA-Z0-9-]+)/stats',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_stats' ),
				'permission_callback' => array( $this, 'check_logged_in' ),
			)
		);
	}

	/**
	 * Check if user is logged in.
	 *
	 * @return bool|WP_Error True if logged in, WP_Error otherwise.
	 */
	public function check_logged_in() {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_not_logged_in',
				__( 'You are not logged in.', 'wp-gamify-bridge' ),
				array( 'status' => 401 )
			);
		}

		return true;
	}

	/**
	 * Check room permission (owner or admin).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if has permission, WP_Error otherwise.
	 */
	public function check_room_permission( $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_not_logged_in',
				__( 'You are not logged in.', 'wp-gamify-bridge' ),
				array( 'status' => 401 )
			);
		}

		$room_id = $request->get_param( 'id' );
		$room    = $this->room_manager->get_room( $room_id );

		if ( ! $room ) {
			return new WP_Error(
				'room_not_found',
				__( 'Room not found.', 'wp-gamify-bridge' ),
				array( 'status' => 404 )
			);
		}

		$user_id = get_current_user_id();

		// Allow room creator or admin.
		if ( $room->created_by === $user_id || current_user_can( 'manage_options' ) ) {
			return true;
		}

		return new WP_Error(
			'rest_forbidden',
			__( 'You do not have permission to modify this room.', 'wp-gamify-bridge' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * Get create room args.
	 *
	 * @return array Arguments.
	 */
	private function get_create_room_args() {
		return array(
			'name'        => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function ( $param ) {
					return ! empty( $param ) && strlen( $param ) <= 255;
				},
			),
			'max_players' => array(
				'default'           => 10,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'validate_callback' => function ( $param ) {
					return $param >= 2 && $param <= 100;
				},
			),
		);
	}

	/**
	 * Get update room args.
	 *
	 * @return array Arguments.
	 */
	private function get_update_room_args() {
		return array(
			'name'        => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function ( $param ) {
					return strlen( $param ) <= 255;
				},
			),
			'max_players' => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'validate_callback' => function ( $param ) {
					return $param >= 2 && $param <= 100;
				},
			),
			'is_active'   => array(
				'type' => 'boolean',
			),
		);
	}

	/**
	 * List rooms.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function list_rooms( $request ) {
		$args = array(
			'limit'     => $request->get_param( 'per_page' ) ?? 20,
			'offset'    => $request->get_param( 'offset' ) ?? 0,
			'is_active' => $request->get_param( 'is_active' ) ?? 1,
		);

		$rooms = $this->room_manager->list_rooms( $args );

		// Add player counts to each room.
		foreach ( $rooms as &$room ) {
			$players             = $this->room_manager->get_room_players( $room->room_id );
			$room->player_count  = count( $players );
			unset( $room->room_data ); // Remove internal data from API response.
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'rooms'   => $rooms,
				'count'   => count( $rooms ),
			)
		);
	}

	/**
	 * Get single room.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function get_room( $request ) {
		$room_id = $request->get_param( 'id' );
		$room    = $this->room_manager->get_room( $room_id );

		if ( ! $room ) {
			return new WP_Error(
				'room_not_found',
				__( 'Room not found.', 'wp-gamify-bridge' ),
				array( 'status' => 404 )
			);
		}

		$players            = $this->room_manager->get_room_players( $room_id );
		$room->player_count = count( $players );
		$room->players      = $players;

		return rest_ensure_response(
			array(
				'success' => true,
				'room'    => $room,
			)
		);
	}

	/**
	 * Create room.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function create_room( $request ) {
		$name        = $request->get_param( 'name' );
		$max_players = $request->get_param( 'max_players' );

		$room_id = $this->room_manager->create_room( $name, $max_players );

		if ( ! $room_id ) {
			return new WP_Error(
				'room_create_failed',
				__( 'Failed to create room.', 'wp-gamify-bridge' ),
				array( 'status' => 500 )
			);
		}

		$room = $this->room_manager->get_room( $room_id );

		return rest_ensure_response(
			array(
				'success' => true,
				'room_id' => $room_id,
				'room'    => $room,
				'message' => __( 'Room created successfully.', 'wp-gamify-bridge' ),
			)
		);
	}

	/**
	 * Update room.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function update_room( $request ) {
		$room_id = $request->get_param( 'id' );

		$data = array();
		if ( $request->has_param( 'name' ) ) {
			$data['name'] = $request->get_param( 'name' );
		}
		if ( $request->has_param( 'max_players' ) ) {
			$data['max_players'] = $request->get_param( 'max_players' );
		}
		if ( $request->has_param( 'is_active' ) ) {
			$data['is_active'] = $request->get_param( 'is_active' ) ? 1 : 0;
		}

		$result = $this->room_manager->update_room( $room_id, $data );

		if ( ! $result ) {
			return new WP_Error(
				'room_update_failed',
				__( 'Failed to update room.', 'wp-gamify-bridge' ),
				array( 'status' => 500 )
			);
		}

		$room = $this->room_manager->get_room( $room_id, false );

		return rest_ensure_response(
			array(
				'success' => true,
				'room'    => $room,
				'message' => __( 'Room updated successfully.', 'wp-gamify-bridge' ),
			)
		);
	}

	/**
	 * Delete room.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function delete_room( $request ) {
		$room_id = $request->get_param( 'id' );

		$result = $this->room_manager->delete_room( $room_id );

		if ( ! $result ) {
			return new WP_Error(
				'room_delete_failed',
				__( 'Failed to delete room.', 'wp-gamify-bridge' ),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Room deleted successfully.', 'wp-gamify-bridge' ),
			)
		);
	}

	/**
	 * Join room.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function join_room( $request ) {
		$room_id = $request->get_param( 'id' );

		$result = $this->room_manager->join_room( $room_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$players = $this->room_manager->get_room_players( $room_id );

		return rest_ensure_response(
			array(
				'success' => true,
				'players' => $players,
				'message' => __( 'Joined room successfully.', 'wp-gamify-bridge' ),
			)
		);
	}

	/**
	 * Leave room.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function leave_room( $request ) {
		$room_id = $request->get_param( 'id' );

		$result = $this->room_manager->leave_room( $room_id );

		if ( ! $result ) {
			return new WP_Error(
				'room_leave_failed',
				__( 'Failed to leave room.', 'wp-gamify-bridge' ),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Left room successfully.', 'wp-gamify-bridge' ),
			)
		);
	}

	/**
	 * Update player presence.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function update_presence( $request ) {
		$room_id = $request->get_param( 'id' );
		$user_id = get_current_user_id();

		$this->room_manager->update_player_presence( $room_id, $user_id );

		return rest_ensure_response(
			array(
				'success'   => true,
				'timestamp' => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Get room players.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_players( $request ) {
		$room_id = $request->get_param( 'id' );
		$players = $this->room_manager->get_room_players( $room_id );

		return rest_ensure_response(
			array(
				'success' => true,
				'players' => $players,
				'count'   => count( $players ),
			)
		);
	}

	/**
	 * Get room stats.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_stats( $request ) {
		$room_id = $request->get_param( 'id' );
		$stats   = $this->room_manager->get_room_stats( $room_id );

		if ( empty( $stats ) ) {
			return new WP_Error(
				'room_not_found',
				__( 'Room not found.', 'wp-gamify-bridge' ),
				array( 'status' => 404 )
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'stats'   => $stats,
			)
		);
	}
}
