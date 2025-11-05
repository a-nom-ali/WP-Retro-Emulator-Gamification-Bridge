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
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
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
	 * @return bool True if user has permission.
	 */
	public function check_permission( $request ) {
		// User must be logged in.
		if ( ! is_user_logged_in() ) {
			return false;
		}

		// Verify nonce if present.
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( $nonce && ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return false;
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
	 * @return WP_REST_Response Response.
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

		// Log event to database.
		$db     = WP_Gamify_Bridge_Database::instance();
		$log_id = $db->log_event( $event_type, $user_id, $room_id, $data, $score );

		if ( ! $log_id ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Failed to log event', 'wp-gamify-bridge' ),
				),
				500
			);
		}

		// Trigger gamification actions.
		$reward = $this->trigger_gamification( $event_type, $user_id, $score, $data );

		// Prepare response.
		$response = array(
			'success'   => true,
			'event_id'  => $log_id,
			'reward'    => $reward,
			'broadcast' => ! empty( $room_id ),
		);

		// Trigger action for broadcasting (can be used by WebSocket integration).
		if ( ! empty( $room_id ) ) {
			do_action( 'wp_gamify_bridge_broadcast_event', $room_id, $event_type, $user_id, $response );
		}

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
}
