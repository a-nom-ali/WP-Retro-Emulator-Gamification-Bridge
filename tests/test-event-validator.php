<?php
/**
 * Tests for WP_Gamify_Bridge_Event_Validator
 *
 * @package WP_Gamify_Bridge
 */

require_once __DIR__ . '/class-wp-gamify-bridge-test-case.php';

/**
 * Test event validation.
 */
class Test_WP_Gamify_Bridge_Event_Validator extends WP_Gamify_Bridge_Test_Case {

	/**
	 * Validator instance.
	 *
	 * @var WP_Gamify_Bridge_Event_Validator
	 */
	private $validator;

	/**
	 * Set up test.
	 */
	public function set_up() {
		parent::set_up();
		$this->validator = WP_Gamify_Bridge_Event_Validator::instance();
	}

	/**
	 * Test valid event passes validation.
	 */
	public function test_valid_event() {
		$user_id = $this->create_test_user();

		$data = array(
			'event'  => 'level_complete',
			'player' => $user_id,
			'score'  => 1000,
			'data'   => array( 'level' => 5 ),
		);

		$result = $this->validator->validate( $data );
		$this->assertNotWPError( $result );
	}

	/**
	 * Test missing event type fails.
	 */
	public function test_missing_event_type() {
		$data = array(
			'score' => 1000,
		);

		$result = $this->validator->validate( $data );
		$this->assertWPError( $result );
		$this->assertEquals( 'missing_event', $result->get_error_code() );
	}

	/**
	 * Test invalid event type fails.
	 */
	public function test_invalid_event_type() {
		$data = array(
			'event' => 'invalid_event_type',
			'score' => 1000,
		);

		$result = $this->validator->validate( $data );
		$this->assertWPError( $result );
		$this->assertEquals( 'invalid_event', $result->get_error_code() );
	}

	/**
	 * Test all allowed event types pass.
	 */
	public function test_all_allowed_event_types() {
		$allowed_events = array(
			'level_complete',
			'game_over',
			'score_milestone',
			'death',
			'game_start',
			'achievement_unlock',
		);

		foreach ( $allowed_events as $event_type ) {
			$data = array( 'event' => $event_type );
			$result = $this->validator->validate( $data );
			$this->assertNotWPError( $result, "Event type '$event_type' should be valid" );
		}
	}

	/**
	 * Test score validation.
	 */
	public function test_score_validation() {
		// Valid score.
		$data = array(
			'event' => 'level_complete',
			'score' => 1000,
		);
		$result = $this->validator->validate( $data );
		$this->assertNotWPError( $result );

		// Negative score should fail.
		$data['score'] = -100;
		$result = $this->validator->validate( $data );
		$this->assertWPError( $result );

		// Score too large should fail.
		$data['score'] = 1000000000; // Over max.
		$result = $this->validator->validate( $data );
		$this->assertWPError( $result );
	}

	/**
	 * Test user ID validation.
	 */
	public function test_user_id_validation() {
		$valid_user_id = $this->create_test_user();

		// Valid user ID.
		$data = array(
			'event'  => 'level_complete',
			'player' => $valid_user_id,
		);
		$result = $this->validator->validate( $data );
		$this->assertNotWPError( $result );

		// Invalid user ID should fail.
		$data['player'] = 999999;
		$result = $this->validator->validate( $data );
		$this->assertWPError( $result );
		$this->assertEquals( 'invalid_user', $result->get_error_code() );
	}

	/**
	 * Test room ID validation.
	 */
	public function test_room_id_validation() {
		$room_id = $this->create_test_room();

		// Valid room ID.
		$data = array(
			'event'   => 'level_complete',
			'room_id' => $room_id,
		);
		$result = $this->validator->validate( $data );
		$this->assertNotWPError( $result );

		// Invalid room ID should fail.
		$data['room_id'] = 'invalid-room-id';
		$result = $this->validator->validate( $data );
		$this->assertWPError( $result );
		$this->assertEquals( 'invalid_room', $result->get_error_code() );
	}

	/**
	 * Test event data size limit.
	 */
	public function test_event_data_size_limit() {
		// Valid data.
		$data = array(
			'event' => 'level_complete',
			'data'  => array( 'level' => 5 ),
		);
		$result = $this->validator->validate( $data );
		$this->assertNotWPError( $result );

		// Data too large should fail.
		$data['data'] = array( 'huge' => str_repeat( 'x', 15000 ) );
		$result = $this->validator->validate( $data );
		$this->assertWPError( $result );
		$this->assertEquals( 'data_too_large', $result->get_error_code() );
	}

	/**
	 * Test custom event type via filter.
	 */
	public function test_custom_event_type_filter() {
		add_filter( 'wp_gamify_bridge_allowed_events', function( $events ) {
			$events[] = 'custom_event';
			return $events;
		});

		$data = array( 'event' => 'custom_event' );
		$result = $this->validator->validate( $data );
		$this->assertNotWPError( $result );
	}

	/**
	 * Test sanitization of event data.
	 */
	public function test_event_data_sanitization() {
		$data = array(
			'event' => 'level_complete',
			'score' => '1000', // String should be converted to int.
			'data'  => array(
				'level' => '5', // Should remain as string in JSON.
			),
		);

		$result = $this->validator->validate( $data );
		$this->assertNotWPError( $result );
		$this->assertIsInt( $result['score'] );
	}
}
