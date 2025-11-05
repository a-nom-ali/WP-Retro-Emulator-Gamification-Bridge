<?php
/**
 * Tests for WP_Gamify_Bridge_Database
 *
 * @package WP_Gamify_Bridge
 */

require_once __DIR__ . '/class-wp-gamify-bridge-test-case.php';

/**
 * Test database operations.
 */
class Test_WP_Gamify_Bridge_Database extends WP_Gamify_Bridge_Test_Case {

	/**
	 * Database instance.
	 *
	 * @var WP_Gamify_Bridge_Database
	 */
	private $database;

	/**
	 * Set up test.
	 */
	public function set_up() {
		parent::set_up();
		$this->database = WP_Gamify_Bridge_Database::instance();
	}

	/**
	 * Test database tables exist.
	 */
	public function test_tables_exist() {
		global $wpdb;

		$events_table = $wpdb->prefix . 'gamify_events';
		$rooms_table  = $wpdb->prefix . 'gamify_rooms';

		$this->assertNotNull( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $events_table ) ) );
		$this->assertNotNull( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $rooms_table ) ) );
	}

	/**
	 * Test log event.
	 */
	public function test_log_event() {
		$user_id = $this->create_test_user();

		$event_id = $this->database->log_event(
			'level_complete',
			$user_id,
			1000,
			array( 'level' => 5 )
		);

		$this->assertGreaterThan( 0, $event_id );
	}

	/**
	 * Test log event with room.
	 */
	public function test_log_event_with_room() {
		$user_id = $this->create_test_user();
		$room_id = $this->create_test_room();

		$event_id = $this->database->log_event(
			'game_over',
			$user_id,
			5000,
			array( 'final_level' => 10 ),
			$room_id
		);

		$this->assertGreaterThan( 0, $event_id );

		// Verify room_id is stored.
		global $wpdb;
		$table_name = $wpdb->prefix . 'gamify_events';
		$stored_room_id = $wpdb->get_var( $wpdb->prepare( "SELECT room_id FROM $table_name WHERE id = %d", $event_id ) );

		$this->assertEquals( $room_id, $stored_room_id );
	}

	/**
	 * Test get events.
	 */
	public function test_get_events() {
		$user_id = $this->create_test_user();

		// Create multiple events.
		$this->database->log_event( 'game_start', $user_id, 0, array() );
		$this->database->log_event( 'level_complete', $user_id, 100, array( 'level' => 1 ) );
		$this->database->log_event( 'level_complete', $user_id, 200, array( 'level' => 2 ) );

		$events = $this->database->get_events( array( 'limit' => 10 ) );

		$this->assertIsArray( $events );
		$this->assertGreaterThanOrEqual( 3, count( $events ) );
	}

	/**
	 * Test get events by user.
	 */
	public function test_get_events_by_user() {
		$user_id_1 = $this->create_test_user();
		$user_id_2 = $this->create_test_user();

		$this->database->log_event( 'game_start', $user_id_1, 0, array() );
		$this->database->log_event( 'game_start', $user_id_2, 0, array() );

		$events = $this->database->get_events( array( 'user_id' => $user_id_1 ) );

		$this->assertIsArray( $events );
		foreach ( $events as $event ) {
			$this->assertEquals( $user_id_1, $event->user_id );
		}
	}

	/**
	 * Test get events by type.
	 */
	public function test_get_events_by_type() {
		$user_id = $this->create_test_user();

		$this->database->log_event( 'level_complete', $user_id, 100, array() );
		$this->database->log_event( 'game_over', $user_id, 500, array() );
		$this->database->log_event( 'level_complete', $user_id, 200, array() );

		$events = $this->database->get_events( array( 'event_type' => 'level_complete' ) );

		$this->assertIsArray( $events );
		foreach ( $events as $event ) {
			$this->assertEquals( 'level_complete', $event->event_type );
		}
	}

	/**
	 * Test get events by room.
	 */
	public function test_get_events_by_room() {
		$user_id = $this->create_test_user();
		$room_id_1 = $this->create_test_room( array( 'name' => 'Room 1' ) );
		$room_id_2 = $this->create_test_room( array( 'name' => 'Room 2' ) );

		$this->database->log_event( 'game_start', $user_id, 0, array(), $room_id_1 );
		$this->database->log_event( 'game_start', $user_id, 0, array(), $room_id_2 );

		$events = $this->database->get_events( array( 'room_id' => $room_id_1 ) );

		$this->assertIsArray( $events );
		foreach ( $events as $event ) {
			$this->assertEquals( $room_id_1, $event->room_id );
		}
	}

	/**
	 * Test get events with pagination.
	 */
	public function test_get_events_pagination() {
		$user_id = $this->create_test_user();

		// Create 5 events.
		for ( $i = 1; $i <= 5; $i++ ) {
			$this->database->log_event( 'level_complete', $user_id, $i * 100, array( 'level' => $i ) );
		}

		$page_1 = $this->database->get_events( array( 'limit' => 2, 'offset' => 0 ) );
		$page_2 = $this->database->get_events( array( 'limit' => 2, 'offset' => 2 ) );

		$this->assertCount( 2, $page_1 );
		$this->assertCount( 2, $page_2 );
		$this->assertNotEquals( $page_1[0]->id, $page_2[0]->id );
	}

	/**
	 * Test event data JSON encoding.
	 */
	public function test_event_data_json() {
		$user_id = $this->create_test_user();
		$data = array(
			'level' => 5,
			'time' => 120,
			'difficulty' => 'hard',
		);

		$event_id = $this->database->log_event( 'level_complete', $user_id, 1000, $data );

		$events = $this->database->get_events( array( 'limit' => 1 ) );
		$event = $events[0];

		$decoded_data = json_decode( $event->event_data, true );
		$this->assertEquals( $data['level'], $decoded_data['level'] );
		$this->assertEquals( $data['time'], $decoded_data['time'] );
		$this->assertEquals( $data['difficulty'], $decoded_data['difficulty'] );
	}
}
