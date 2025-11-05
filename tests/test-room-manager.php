<?php
/**
 * Tests for WP_Gamify_Bridge_Room_Manager
 *
 * @package WP_Gamify_Bridge
 */

require_once __DIR__ . '/class-wp-gamify-bridge-test-case.php';

/**
 * Test room management.
 */
class Test_WP_Gamify_Bridge_Room_Manager extends WP_Gamify_Bridge_Test_Case {

	/**
	 * Room manager instance.
	 *
	 * @var WP_Gamify_Bridge_Room_Manager
	 */
	private $manager;

	/**
	 * Set up test.
	 */
	public function set_up() {
		parent::set_up();
		$this->manager = WP_Gamify_Bridge_Room_Manager::instance();
	}

	/**
	 * Test create room.
	 */
	public function test_create_room() {
		$room_id = $this->manager->create_room( 'Test Room', 10 );

		$this->assertNotEmpty( $room_id );
		$this->assertStringStartsWith( 'room-', $room_id );
	}

	/**
	 * Test get room.
	 */
	public function test_get_room() {
		$room_id = $this->manager->create_room( 'Test Room', 10 );
		$room = $this->manager->get_room( $room_id );

		$this->assertNotNull( $room );
		$this->assertEquals( $room_id, $room['room_id'] );
		$this->assertEquals( 'Test Room', $room['name'] );
		$this->assertEquals( 10, $room['max_players'] );
		$this->assertEquals( 1, $room['is_active'] );
	}

	/**
	 * Test get non-existent room returns null.
	 */
	public function test_get_nonexistent_room() {
		$room = $this->manager->get_room( 'room-nonexistent' );
		$this->assertNull( $room );
	}

	/**
	 * Test list rooms.
	 */
	public function test_list_rooms() {
		$this->manager->create_room( 'Room 1', 10 );
		$this->manager->create_room( 'Room 2', 20 );
		$this->manager->create_room( 'Room 3', 30 );

		$rooms = $this->manager->list_rooms();

		$this->assertIsArray( $rooms );
		$this->assertGreaterThanOrEqual( 3, count( $rooms ) );
	}

	/**
	 * Test list rooms with pagination.
	 */
	public function test_list_rooms_pagination() {
		// Create 5 rooms.
		for ( $i = 1; $i <= 5; $i++ ) {
			$this->manager->create_room( "Room $i", 10 );
		}

		$page_1 = $this->manager->list_rooms( array( 'limit' => 2, 'offset' => 0 ) );
		$page_2 = $this->manager->list_rooms( array( 'limit' => 2, 'offset' => 2 ) );

		$this->assertCount( 2, $page_1 );
		$this->assertCount( 2, $page_2 );
		$this->assertNotEquals( $page_1[0]['room_id'], $page_2[0]['room_id'] );
	}

	/**
	 * Test list only active rooms.
	 */
	public function test_list_active_rooms() {
		$room_id_1 = $this->manager->create_room( 'Active Room', 10 );
		$room_id_2 = $this->manager->create_room( 'Inactive Room', 10 );

		// Deactivate room 2.
		$this->manager->update_room( $room_id_2, array( 'is_active' => 0 ) );

		$rooms = $this->manager->list_rooms( array( 'is_active' => 1 ) );

		foreach ( $rooms as $room ) {
			$this->assertEquals( 1, $room['is_active'] );
		}
	}

	/**
	 * Test update room.
	 */
	public function test_update_room() {
		$room_id = $this->manager->create_room( 'Original Name', 10 );

		$result = $this->manager->update_room( $room_id, array(
			'name' => 'Updated Name',
			'max_players' => 20,
		));

		$this->assertTrue( $result );

		$room = $this->manager->get_room( $room_id );
		$this->assertEquals( 'Updated Name', $room['name'] );
		$this->assertEquals( 20, $room['max_players'] );
	}

	/**
	 * Test delete room.
	 */
	public function test_delete_room() {
		$room_id = $this->manager->create_room( 'To Delete', 10 );

		$result = $this->manager->delete_room( $room_id );
		$this->assertTrue( $result );

		$room = $this->manager->get_room( $room_id );
		$this->assertNull( $room );
	}

	/**
	 * Test join room.
	 */
	public function test_join_room() {
		$user_id = $this->create_test_user();
		$room_id = $this->manager->create_room( 'Join Test', 10 );

		$result = $this->manager->join_room( $room_id, $user_id );
		$this->assertTrue( $result );

		$is_in_room = $this->manager->is_user_in_room( $room_id, $user_id );
		$this->assertTrue( $is_in_room );
	}

	/**
	 * Test join room at capacity fails.
	 */
	public function test_join_room_at_capacity() {
		$room_id = $this->manager->create_room( 'Full Room', 2 );

		// Fill room.
		$user_id_1 = $this->create_test_user();
		$user_id_2 = $this->create_test_user();
		$this->manager->join_room( $room_id, $user_id_1 );
		$this->manager->join_room( $room_id, $user_id_2 );

		// Try to join full room.
		$user_id_3 = $this->create_test_user();
		$result = $this->manager->join_room( $room_id, $user_id_3 );

		$this->assertWPError( $result );
		$this->assertEquals( 'room_full', $result->get_error_code() );
	}

	/**
	 * Test leave room.
	 */
	public function test_leave_room() {
		$user_id = $this->create_test_user();
		$room_id = $this->manager->create_room( 'Leave Test', 10 );

		$this->manager->join_room( $room_id, $user_id );
		$this->assertTrue( $this->manager->is_user_in_room( $room_id, $user_id ) );

		$result = $this->manager->leave_room( $room_id, $user_id );
		$this->assertTrue( $result );

		$this->assertFalse( $this->manager->is_user_in_room( $room_id, $user_id ) );
	}

	/**
	 * Test get room players.
	 */
	public function test_get_room_players() {
		$room_id = $this->manager->create_room( 'Players Test', 10 );
		$user_id_1 = $this->create_test_user();
		$user_id_2 = $this->create_test_user();

		$this->manager->join_room( $room_id, $user_id_1 );
		$this->manager->join_room( $room_id, $user_id_2 );

		$players = $this->manager->get_room_players( $room_id );

		$this->assertIsArray( $players );
		$this->assertCount( 2, $players );
	}

	/**
	 * Test update player presence.
	 */
	public function test_update_player_presence() {
		$user_id = $this->create_test_user();
		$room_id = $this->manager->create_room( 'Presence Test', 10 );

		$this->manager->join_room( $room_id, $user_id );

		// Wait 1 second.
		sleep( 1 );

		$result = $this->manager->update_player_presence( $room_id, $user_id );
		$this->assertTrue( $result );

		$players = $this->manager->get_room_players( $room_id );
		$player = $players[0];

		// last_seen should be updated.
		$this->assertNotEquals( $player['joined_at'], $player['last_seen'] );
	}

	/**
	 * Test get room stats.
	 */
	public function test_get_room_stats() {
		$room_id = $this->manager->create_room( 'Stats Test', 10 );
		$user_id = $this->create_test_user();

		$this->manager->join_room( $room_id, $user_id );

		// Log an event.
		$db = WP_Gamify_Bridge_Database::instance();
		$db->log_event( 'game_start', $user_id, 0, array(), $room_id );

		$stats = $this->manager->get_room_stats( $room_id );

		$this->assertIsArray( $stats );
		$this->assertEquals( 1, $stats['player_count'] );
		$this->assertGreaterThanOrEqual( 1, $stats['event_count'] );
	}

	/**
	 * Test room caching.
	 */
	public function test_room_caching() {
		$room_id = $this->manager->create_room( 'Cache Test', 10 );

		// First call - from database.
		$room_1 = $this->manager->get_room( $room_id, true );

		// Second call - from cache.
		$room_2 = $this->manager->get_room( $room_id, true );

		$this->assertEquals( $room_1, $room_2 );
	}

	/**
	 * Test cleanup inactive players.
	 */
	public function test_cleanup_inactive_players() {
		$user_id = $this->create_test_user();
		$room_id = $this->manager->create_room( 'Cleanup Test', 10 );

		$this->manager->join_room( $room_id, $user_id );

		// Manually set last_seen to 2 hours ago.
		global $wpdb;
		$table_name = $wpdb->prefix . 'gamify_rooms';
		$room = $this->manager->get_room( $room_id );
		$room_data = json_decode( $room['room_data'], true );
		$room_data['players'][0]['last_seen'] = gmdate( 'Y-m-d H:i:s', time() - 7200 );

		$wpdb->update(
			$table_name,
			array( 'room_data' => wp_json_encode( $room_data ) ),
			array( 'room_id' => $room_id ),
			array( '%s' ),
			array( '%s' )
		);

		// Run cleanup.
		$this->manager->cleanup_inactive_players();

		// Player should be removed.
		$is_in_room = $this->manager->is_user_in_room( $room_id, $user_id );
		$this->assertFalse( $is_in_room );
	}
}
