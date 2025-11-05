<?php
/**
 * Tests for WP_Gamify_Bridge_Rate_Limiter
 *
 * @package WP_Gamify_Bridge
 */

require_once __DIR__ . '/class-wp-gamify-bridge-test-case.php';

/**
 * Test rate limiting.
 */
class Test_WP_Gamify_Bridge_Rate_Limiter extends WP_Gamify_Bridge_Test_Case {

	/**
	 * Rate limiter instance.
	 *
	 * @var WP_Gamify_Bridge_Rate_Limiter
	 */
	private $limiter;

	/**
	 * Set up test.
	 */
	public function set_up() {
		parent::set_up();
		$this->limiter = WP_Gamify_Bridge_Rate_Limiter::instance();
	}

	/**
	 * Test rate limit allows initial requests.
	 */
	public function test_initial_requests_allowed() {
		$user_id = $this->create_test_user();

		$result = $this->limiter->check_rate_limit( $user_id );
		$this->assertTrue( $result );
	}

	/**
	 * Test rate limit increments counters.
	 */
	public function test_increment_counters() {
		$user_id = $this->create_test_user();

		// Check initial status.
		$status_before = $this->limiter->get_rate_limit_status( $user_id );
		$this->assertEquals( 0, $status_before['requests_this_minute'] );

		// Increment.
		$this->limiter->increment_counters( $user_id );

		// Check updated status.
		$status_after = $this->limiter->get_rate_limit_status( $user_id );
		$this->assertEquals( 1, $status_after['requests_this_minute'] );
		$this->assertEquals( 1, $status_after['requests_this_hour'] );
	}

	/**
	 * Test rate limit blocks after limit exceeded.
	 */
	public function test_rate_limit_blocks_excess_requests() {
		$user_id = $this->create_test_user();

		// Simulate 61 requests in a minute (limit is 60).
		for ( $i = 0; $i < 61; $i++ ) {
			$this->limiter->increment_counters( $user_id );
		}

		$result = $this->limiter->check_rate_limit( $user_id );
		$this->assertWPError( $result );
		$this->assertEquals( 'rate_limit_exceeded', $result->get_error_code() );
	}

	/**
	 * Test hourly rate limit.
	 */
	public function test_hourly_rate_limit() {
		$user_id = $this->create_test_user();

		// Simulate 501 requests in an hour (limit is 500).
		for ( $i = 0; $i < 501; $i++ ) {
			$this->limiter->increment_counters( $user_id );
		}

		$result = $this->limiter->check_rate_limit( $user_id );
		$this->assertWPError( $result );
		$this->assertEquals( 'rate_limit_exceeded', $result->get_error_code() );
	}

	/**
	 * Test rate limit status retrieval.
	 */
	public function test_get_rate_limit_status() {
		$user_id = $this->create_test_user();

		// Increment 5 times.
		for ( $i = 0; $i < 5; $i++ ) {
			$this->limiter->increment_counters( $user_id );
		}

		$status = $this->limiter->get_rate_limit_status( $user_id );

		$this->assertIsArray( $status );
		$this->assertEquals( 5, $status['requests_this_minute'] );
		$this->assertEquals( 5, $status['requests_this_hour'] );
		$this->assertEquals( 60, $status['minute_limit'] );
		$this->assertEquals( 500, $status['hour_limit'] );
		$this->assertEquals( 55, $status['minute_remaining'] );
		$this->assertEquals( 495, $status['hour_remaining'] );
	}

	/**
	 * Test reset counters.
	 */
	public function test_reset_counters() {
		$user_id = $this->create_test_user();

		// Increment counters.
		for ( $i = 0; $i < 10; $i++ ) {
			$this->limiter->increment_counters( $user_id );
		}

		$status_before = $this->limiter->get_rate_limit_status( $user_id );
		$this->assertEquals( 10, $status_before['requests_this_minute'] );

		// Reset.
		$this->limiter->reset_counters( $user_id );

		$status_after = $this->limiter->get_rate_limit_status( $user_id );
		$this->assertEquals( 0, $status_after['requests_this_minute'] );
		$this->assertEquals( 0, $status_after['requests_this_hour'] );
	}

	/**
	 * Test whitelist filter.
	 */
	public function test_whitelist_filter() {
		$user_id = $this->create_test_user();

		// Add user to whitelist.
		add_filter( 'wp_gamify_bridge_rate_limit_whitelist', function( $whitelist ) use ( $user_id ) {
			$whitelist[] = $user_id;
			return $whitelist;
		});

		// Simulate excessive requests.
		for ( $i = 0; $i < 100; $i++ ) {
			$this->limiter->increment_counters( $user_id );
		}

		// Whitelisted user should still pass.
		$result = $this->limiter->check_rate_limit( $user_id );
		$this->assertTrue( $result );
	}

	/**
	 * Test rate limiting can be disabled.
	 */
	public function test_rate_limiting_disabled() {
		$user_id = $this->create_test_user();

		// Disable rate limiting.
		add_filter( 'wp_gamify_bridge_rate_limiting_enabled', '__return_false' );

		// Simulate excessive requests.
		for ( $i = 0; $i < 100; $i++ ) {
			$this->limiter->increment_counters( $user_id );
		}

		// Should pass when disabled.
		$result = $this->limiter->check_rate_limit( $user_id );
		$this->assertTrue( $result );
	}

	/**
	 * Test different users have separate limits.
	 */
	public function test_separate_user_limits() {
		$user_id_1 = $this->create_test_user();
		$user_id_2 = $this->create_test_user();

		// User 1 makes 10 requests.
		for ( $i = 0; $i < 10; $i++ ) {
			$this->limiter->increment_counters( $user_id_1 );
		}

		// User 2 makes 5 requests.
		for ( $i = 0; $i < 5; $i++ ) {
			$this->limiter->increment_counters( $user_id_2 );
		}

		$status_1 = $this->limiter->get_rate_limit_status( $user_id_1 );
		$status_2 = $this->limiter->get_rate_limit_status( $user_id_2 );

		$this->assertEquals( 10, $status_1['requests_this_minute'] );
		$this->assertEquals( 5, $status_2['requests_this_minute'] );
	}
}
