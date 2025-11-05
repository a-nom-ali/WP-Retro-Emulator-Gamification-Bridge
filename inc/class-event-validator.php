<?php
/**
 * Event validation class.
 *
 * @package WP_Gamify_Bridge
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Gamify_Bridge_Event_Validator
 */
class WP_Gamify_Bridge_Event_Validator {

	/**
	 * Allowed event types.
	 *
	 * @var array
	 */
	private $allowed_events = array(
		'level_complete',
		'game_over',
		'score_milestone',
		'death',
		'game_start',
		'achievement_unlock',
	);

	/**
	 * Maximum score value to prevent abuse.
	 *
	 * @var int
	 */
	private $max_score = 999999999;

	/**
	 * Maximum event data size (in characters).
	 *
	 * @var int
	 */
	private $max_data_size = 10000;

	/**
	 * Validate event type.
	 *
	 * @param string $event_type Event type to validate.
	 * @return bool|WP_Error True if valid, WP_Error if invalid.
	 */
	public function validate_event_type( $event_type ) {
		if ( empty( $event_type ) ) {
			return new WP_Error(
				'empty_event_type',
				__( 'Event type is required.', 'wp-gamify-bridge' ),
				array( 'status' => 400 )
			);
		}

		if ( ! in_array( $event_type, $this->allowed_events, true ) ) {
			return new WP_Error(
				'invalid_event_type',
				sprintf(
					/* translators: %s: event type */
					__( 'Invalid event type: %s', 'wp-gamify-bridge' ),
					$event_type
				),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * Validate score value.
	 *
	 * @param int $score Score value to validate.
	 * @return bool|WP_Error True if valid, WP_Error if invalid.
	 */
	public function validate_score( $score ) {
		if ( ! is_numeric( $score ) ) {
			return new WP_Error(
				'invalid_score',
				__( 'Score must be a numeric value.', 'wp-gamify-bridge' ),
				array( 'status' => 400 )
			);
		}

		$score = absint( $score );

		if ( $score > $this->max_score ) {
			return new WP_Error(
				'score_too_high',
				sprintf(
					/* translators: %d: maximum score */
					__( 'Score exceeds maximum allowed value of %d.', 'wp-gamify-bridge' ),
					$this->max_score
				),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * Validate user ID.
	 *
	 * @param int $user_id User ID to validate.
	 * @return bool|WP_Error True if valid, WP_Error if invalid.
	 */
	public function validate_user_id( $user_id ) {
		if ( empty( $user_id ) ) {
			return new WP_Error(
				'empty_user_id',
				__( 'User ID is required.', 'wp-gamify-bridge' ),
				array( 'status' => 400 )
			);
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return new WP_Error(
				'invalid_user_id',
				__( 'Invalid user ID.', 'wp-gamify-bridge' ),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * Validate room ID.
	 *
	 * @param string $room_id Room ID to validate.
	 * @return bool|WP_Error True if valid, WP_Error if invalid.
	 */
	public function validate_room_id( $room_id ) {
		if ( empty( $room_id ) ) {
			return true; // Room ID is optional.
		}

		// Validate format (room-XXXXXXXX).
		if ( ! preg_match( '/^room-[a-zA-Z0-9]{8,}$/', $room_id ) ) {
			return new WP_Error(
				'invalid_room_id_format',
				__( 'Invalid room ID format.', 'wp-gamify-bridge' ),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * Validate event data.
	 *
	 * @param mixed $data Event data to validate.
	 * @return bool|WP_Error True if valid, WP_Error if invalid.
	 */
	public function validate_event_data( $data ) {
		if ( empty( $data ) ) {
			return true; // Event data is optional.
		}

		if ( ! is_array( $data ) ) {
			return new WP_Error(
				'invalid_event_data',
				__( 'Event data must be an object.', 'wp-gamify-bridge' ),
				array( 'status' => 400 )
			);
		}

		// Check data size.
		$data_json = wp_json_encode( $data );
		if ( strlen( $data_json ) > $this->max_data_size ) {
			return new WP_Error(
				'event_data_too_large',
				sprintf(
					/* translators: %d: maximum data size */
					__( 'Event data exceeds maximum size of %d characters.', 'wp-gamify-bridge' ),
					$this->max_data_size
				),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * Validate complete event request.
	 *
	 * @param array $event_data Complete event data array.
	 * @return bool|WP_Error True if valid, WP_Error if invalid.
	 */
	public function validate_event( $event_data ) {
		// Validate event type.
		$event_type_validation = $this->validate_event_type( $event_data['event'] ?? '' );
		if ( is_wp_error( $event_type_validation ) ) {
			return $event_type_validation;
		}

		// Validate user ID.
		$user_id_validation = $this->validate_user_id( $event_data['user_id'] ?? 0 );
		if ( is_wp_error( $user_id_validation ) ) {
			return $user_id_validation;
		}

		// Validate score.
		if ( isset( $event_data['score'] ) ) {
			$score_validation = $this->validate_score( $event_data['score'] );
			if ( is_wp_error( $score_validation ) ) {
				return $score_validation;
			}
		}

		// Validate room ID.
		if ( isset( $event_data['room_id'] ) ) {
			$room_id_validation = $this->validate_room_id( $event_data['room_id'] );
			if ( is_wp_error( $room_id_validation ) ) {
				return $room_id_validation;
			}
		}

		// Validate event data.
		if ( isset( $event_data['data'] ) ) {
			$data_validation = $this->validate_event_data( $event_data['data'] );
			if ( is_wp_error( $data_validation ) ) {
				return $data_validation;
			}
		}

		return true;
	}

	/**
	 * Get allowed event types.
	 *
	 * @return array Allowed event types.
	 */
	public function get_allowed_events() {
		return apply_filters( 'wp_gamify_bridge_allowed_events', $this->allowed_events );
	}

	/**
	 * Add custom event type.
	 *
	 * @param string $event_type Event type to add.
	 * @return bool True if added, false if already exists.
	 */
	public function add_event_type( $event_type ) {
		if ( in_array( $event_type, $this->allowed_events, true ) ) {
			return false;
		}

		$this->allowed_events[] = sanitize_text_field( $event_type );
		return true;
	}

	/**
	 * Set maximum score value.
	 *
	 * @param int $max_score Maximum score value.
	 */
	public function set_max_score( $max_score ) {
		$this->max_score = absint( $max_score );
	}

	/**
	 * Set maximum event data size.
	 *
	 * @param int $max_size Maximum data size in characters.
	 */
	public function set_max_data_size( $max_size ) {
		$this->max_data_size = absint( $max_size );
	}
}
