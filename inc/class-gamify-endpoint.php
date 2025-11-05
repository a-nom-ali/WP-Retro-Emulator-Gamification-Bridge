<?php
/**
 * REST API endpoint for gamification events.
 *
 * @package WP_Gamify_Bridge
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Gamify_Bridge_Endpoint
 */
class WP_Gamify_Bridge_Endpoint {

	/**
	 * Single instance of the class.
	 *
	 * @var WP_Gamify_Bridge_Endpoint
	 */
	private static $instance = null;

	/**
	 * Event validator instance.
	 *
	 * @var WP_Gamify_Bridge_Event_Validator
	 */
	private $validator;

	/**
	 * Rate limiter instance.
	 *
	 * @var WP_Gamify_Bridge_Rate_Limiter
	 */
	private $rate_limiter;

	/**
	 * Get the singleton instance.
	 *
	 * @return WP_Gamify_Bridge_Endpoint
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
		$this->validator    = new WP_Gamify_Bridge_Event_Validator();
		$this->rate_limiter = new WP_Gamify_Bridge_Rate_Limiter();

		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		// Event endpoint.
		register_rest_route(
			'gamify/v1',
			'/event',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_event' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => $this->get_event_args(),
			)
		);

		// Health check endpoint.
		register_rest_route(
			'gamify/v1',
			'/health',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'health_check' ),
				'permission_callback' => '__return_true',
			)
		);

		// Rate limit status endpoint.
		register_rest_route(
			'gamify/v1',
			'/rate-limit',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_rate_limit_info' ),
				'permission_callback' => array( $this, 'check_authenticated' ),
			)
		);
	}

	/**
	 * Get endpoint arguments.
	 *
	 * @return array Arguments.
	 */
	private function get_event_args() {
		return array(
			'event'   => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => array( $this, 'validate_event_type' ),
			),
			'player'  => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'room_id' => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'score'   => array(
				'required'          => false,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 0,
			),
			'data'    => array(
				'required'          => false,
				'type'              => 'object',
				'sanitize_callback' => array( $this, 'sanitize_event_data' ),
			),
		);
	}

	/**
	 * Check permission for endpoint access.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if user has permission, WP_Error otherwise.
	 */
	public function check_permission( $request ) {
		// User must be logged in.
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You must be logged in to trigger events.', 'wp-gamify-bridge' ),
				array( 'status' => 401 )
			);
		}

		// Verify nonce if present.
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( $nonce && ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Invalid security token.', 'wp-gamify-bridge' ),
				array( 'status' => 403 )
			);
		}

		// Check rate limiting.
		$user_id = get_current_user_id();

		// Skip rate limiting for whitelisted users.
		if ( ! $this->rate_limiter->is_whitelisted( $user_id ) ) {
			$rate_check = $this->rate_limiter->check_rate_limit( $user_id );
			if ( is_wp_error( $rate_check ) ) {
				return $rate_check;
			}
		}

		return true;
	}

	/**
	 * Validate event type.
	 *
	 * @param string $value Event type.
	 * @return bool True if valid.
	 */
	public function validate_event_type( $value ) {
		$allowed_events = array(
			'level_complete',
			'game_over',
			'score_milestone',
			'death',
			'game_start',
			'achievement_unlock',
		);

		return in_array( $value, $allowed_events, true );
	}

	/**
	 * Sanitize event data.
	 *
	 * @param array $data Event data.
	 * @return array Sanitized data.
	 */
	public function sanitize_event_data( $data ) {
		if ( ! is_array( $data ) ) {
			return array();
		}

		return array_map( 'sanitize_text_field', $data );
	}

	/**
	 * Handle event POST request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public function handle_event( $request ) {
		$event_type = $request->get_param( 'event' );
		$player     = $request->get_param( 'player' );
		$room_id    = $request->get_param( 'room_id' );
		$score      = $request->get_param( 'score' );
		$data       = $request->get_param( 'data' );

		// Get user ID.
		$user_id = get_current_user_id();

		// If player username provided, try to get that user.
		if ( $player ) {
			$user = get_user_by( 'login', $player );
			if ( $user ) {
				$user_id = $user->ID;
			}
		}

		// Validate room if provided.
		if ( ! empty( $room_id ) ) {
			$room_manager = WP_Gamify_Bridge_Room_Manager::instance();
			$room         = $room_manager->get_room( $room_id );

			if ( ! $room ) {
				return new WP_Error(
					'invalid_room',
					__( 'Room not found.', 'wp-gamify-bridge' ),
					array( 'status' => 404 )
				);
			}

			if ( ! $room->is_active ) {
				return new WP_Error(
					'room_inactive',
					__( 'This room is not active.', 'wp-gamify-bridge' ),
					array( 'status' => 403 )
				);
			}

			// Optionally verify user is in room (can be disabled via filter).
			$require_membership = apply_filters( 'wp_gamify_bridge_require_room_membership', true );
			if ( $require_membership && ! $room_manager->is_user_in_room( $room_id, $user_id ) ) {
				return new WP_Error(
					'not_in_room',
					__( 'You must join the room before triggering events.', 'wp-gamify-bridge' ),
					array( 'status' => 403 )
				);
			}

			// Update player presence in room.
			$room_manager->update_player_presence( $room_id, $user_id );
		}

		// Validate complete event.
		$validation_result = $this->validator->validate_event(
			array(
				'event'   => $event_type,
				'user_id' => $user_id,
				'room_id' => $room_id,
				'score'   => $score,
				'data'    => $data,
			)
		);

		if ( is_wp_error( $validation_result ) ) {
			return $validation_result;
		}

		// Increment rate limit counters.
		if ( ! $this->rate_limiter->is_whitelisted( $user_id ) ) {
			$this->rate_limiter->increment_counters( $user_id );
		}

		// Log event to database.
		$db     = WP_Gamify_Bridge_Database::instance();
		$log_id = $db->log_event( $event_type, $user_id, $room_id, $data, $score );

		if ( ! $log_id ) {
			return new WP_Error(
				'event_log_failed',
				__( 'Failed to log event to database.', 'wp-gamify-bridge' ),
				array( 'status' => 500 )
			);
		}

		// Trigger gamification actions.
		$reward = $this->trigger_gamification( $event_type, $user_id, $score, $data );

		// Get rate limit status.
		$rate_limit_status = $this->rate_limiter->get_rate_limit_status( $user_id );

		// Prepare response.
		$response = array(
			'success'    => true,
			'event_id'   => $log_id,
			'event_type' => $event_type,
			'reward'     => $reward,
			'broadcast'  => ! empty( $room_id ),
			'rate_limit' => array(
				'remaining_minute' => $rate_limit_status['minute_remaining'],
				'remaining_hour'   => $rate_limit_status['hour_remaining'],
			),
		);

		// Trigger action for broadcasting (can be used by WebSocket integration).
		if ( ! empty( $room_id ) ) {
			do_action( 'wp_gamify_bridge_broadcast_event', $room_id, $event_type, $user_id, $response );
		}

		// Log successful event processing.
		do_action( 'wp_gamify_bridge_event_processed', $log_id, $event_type, $user_id, $response );

		return new WP_REST_Response( $response, 200 );
	}

	/**
	 * Trigger gamification system rewards.
	 *
	 * @param string $event_type Event type.
	 * @param int    $user_id User ID.
	 * @param int    $score Score value.
	 * @param array  $data Event data.
	 * @return string Reward description.
	 */
	private function trigger_gamification( $event_type, $user_id, $score, $data ) {
		$reward = '';

		// Trigger GamiPress if available.
		if ( class_exists( 'GamiPress' ) ) {
			do_action( 'wp_gamify_bridge_gamipress_event', $event_type, $user_id, $score, $data );
			$reward .= 'XP awarded';
		}

		// Trigger MyCred if available.
		if ( defined( 'MYCRED_VERSION' ) ) {
			do_action( 'wp_gamify_bridge_mycred_event', $event_type, $user_id, $score, $data );
			if ( ! empty( $reward ) ) {
				$reward .= ', ';
			}
			$reward .= 'Points awarded';
		}

		return $reward ?: 'Event logged';
	}

	/**
	 * Health check endpoint.
	 *
	 * @return WP_REST_Response Health status response.
	 */
	public function health_check() {
		global $wpdb;

		$health_data = array(
			'status'      => 'ok',
			'version'     => WP_GAMIFY_BRIDGE_VERSION,
			'timestamp'   => current_time( 'mysql' ),
			'database'    => array(
				'connected' => $wpdb->check_connection(),
				'tables'    => array(
					'events' => $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}gamify_events'" ) ? 'exists' : 'missing',
					'rooms'  => $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}gamify_rooms'" ) ? 'exists' : 'missing',
				),
			),
			'integrations' => array(
				'gamipress' => class_exists( 'GamiPress' ),
				'mycred'    => defined( 'MYCRED_VERSION' ),
			),
			'features'    => array(
				'rate_limiting' => $this->rate_limiter->is_enabled(),
				'validation'    => true,
			),
		);

		return new WP_REST_Response( $health_data, 200 );
	}

	/**
	 * Get rate limit info for current user.
	 *
	 * @return WP_REST_Response Rate limit status.
	 */
	public function get_rate_limit_info() {
		$user_id = get_current_user_id();
		$status  = $this->rate_limiter->get_rate_limit_status( $user_id );

		return new WP_REST_Response(
			array(
				'user_id' => $user_id,
				'status'  => $status,
			),
			200
		);
	}

	/**
	 * Check if user is authenticated (simpler permission check).
	 *
	 * @return bool True if authenticated.
	 */
	public function check_authenticated() {
		return is_user_logged_in();
	}
}
