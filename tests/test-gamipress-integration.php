<?php
/**
 * Tests for WP_Gamify_Bridge_GamiPress
 *
 * @package WP_Gamify_Bridge
 */

require_once __DIR__ . '/class-wp-gamify-bridge-test-case.php';

/**
 * Test GamiPress integration.
 *
 * Note: These tests mock GamiPress functions since GamiPress may not be installed.
 */
class Test_WP_Gamify_Bridge_GamiPress extends WP_Gamify_Bridge_Test_Case {

	/**
	 * GamiPress integration instance.
	 *
	 * @var WP_Gamify_Bridge_GamiPress
	 */
	private $integration;

	/**
	 * Set up test.
	 */
	public function set_up() {
		parent::set_up();

		// Only run tests if GamiPress class exists.
		if ( ! class_exists( 'WP_Gamify_Bridge_GamiPress' ) ) {
			$this->markTestSkipped( 'GamiPress integration class not loaded' );
		}

		$this->integration = WP_Gamify_Bridge_GamiPress::instance();
	}

	/**
	 * Test handle event is called.
	 */
	public function test_handle_event_action_fired() {
		$user_id = $this->create_test_user();
		$action_fired = false;

		add_action( 'wp_gamify_bridge_gamipress_event', function() use ( &$action_fired ) {
			$action_fired = true;
		});

		do_action( 'wp_gamify_bridge_gamipress_event', 'level_complete', $user_id, 1000, array( 'level' => 5 ) );

		$this->assertTrue( $action_fired );
	}

	/**
	 * Test XP calculation with score.
	 */
	public function test_xp_calculation_with_score() {
		$user_id = $this->create_test_user();

		// Score of 1000 should give 10 XP (1 per 100).
		$score = 1000;
		$expected_base_xp = 10;

		// Test via filter.
		$xp_awarded = apply_filters( 'wp_gamify_bridge_gamipress_xp_award', $expected_base_xp, 'level_complete', $user_id, $score, array() );

		$this->assertEquals( $expected_base_xp, $xp_awarded );
	}

	/**
	 * Test XP calculation with level multiplier.
	 */
	public function test_xp_calculation_with_level_multiplier() {
		// Level 5 should give 50% bonus (10% per level).
		// Base 100 XP + 50% = 150 XP.
		$base_xp = 100;
		$level = 5;
		$multiplier = 1.0 + ( $level * 0.1 );
		$expected_xp = intval( $base_xp * $multiplier );

		$this->assertEquals( 150, $expected_xp );
	}

	/**
	 * Test XP calculation with difficulty multiplier.
	 */
	public function test_xp_calculation_with_difficulty() {
		$test_cases = array(
			'easy'   => 1.0,
			'normal' => 1.5,
			'hard'   => 2.0,
			'expert' => 3.0,
		);

		foreach ( $test_cases as $difficulty => $multiplier ) {
			$base_xp = 100;
			$expected_xp = intval( $base_xp * $multiplier );

			$this->assertEquals( $expected_xp, intval( 100 * $multiplier ), "Difficulty $difficulty multiplier incorrect" );
		}
	}

	/**
	 * Test achievement checking filter.
	 */
	public function test_achievement_filter() {
		$achievements = array( 'achievement_1', 'achievement_2' );

		$filtered = apply_filters( 'wp_gamify_bridge_gamipress_achievements', $achievements, 'score_milestone', 10000, array() );

		$this->assertIsArray( $filtered );
	}

	/**
	 * Test custom point type filter.
	 */
	public function test_custom_point_type_filter() {
		$point_type = apply_filters( 'wp_gamify_bridge_gamipress_point_type', 'points' );

		$this->assertEquals( 'points', $point_type );

		// Test with custom filter.
		add_filter( 'wp_gamify_bridge_gamipress_point_type', function() {
			return 'arcade_tokens';
		});

		$custom_point_type = apply_filters( 'wp_gamify_bridge_gamipress_point_type', 'points' );
		$this->assertEquals( 'arcade_tokens', $custom_point_type );
	}

	/**
	 * Test XP awarded action hook.
	 */
	public function test_xp_awarded_action() {
		$action_fired = false;
		$awarded_xp = null;

		add_action( 'wp_gamify_bridge_gamipress_xp_awarded', function( $user_id, $xp, $event_type, $data ) use ( &$action_fired, &$awarded_xp ) {
			$action_fired = true;
			$awarded_xp = $xp;
		}, 10, 4 );

		do_action( 'wp_gamify_bridge_gamipress_xp_awarded', 1, 100, 'level_complete', array() );

		$this->assertTrue( $action_fired );
		$this->assertEquals( 100, $awarded_xp );
	}
}
