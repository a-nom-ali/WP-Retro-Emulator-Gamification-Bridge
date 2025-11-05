<?php
/**
 * Base Test Case for WP Gamify Bridge
 *
 * @package WP_Gamify_Bridge
 */

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Base test case class for WP Gamify Bridge tests.
 */
abstract class WP_Gamify_Bridge_Test_Case extends TestCase {

	/**
	 * Set up test environment.
	 */
	public function set_up() {
		parent::set_up();

		// Create test user.
		$this->factory = new WP_UnitTest_Factory();
	}

	/**
	 * Tear down test environment.
	 */
	public function tear_down() {
		parent::tear_down();
	}

	/**
	 * Create a test user.
	 *
	 * @param string $role User role.
	 * @return int User ID.
	 */
	protected function create_test_user( $role = 'subscriber' ) {
		return $this->factory->user->create( array( 'role' => $role ) );
	}

	/**
	 * Create a test room.
	 *
	 * @param array $args Room arguments.
	 * @return string Room ID.
	 */
	protected function create_test_room( $args = array() ) {
		$defaults = array(
			'name'        => 'Test Room',
			'max_players' => 10,
		);

		$args = wp_parse_args( $args, $defaults );

		$manager = WP_Gamify_Bridge_Room_Manager::instance();
		return $manager->create_room( $args['name'], $args['max_players'] );
	}

	/**
	 * Clean up test data.
	 */
	protected function clean_up_global_scope() {
		parent::clean_up_global_scope();

		// Clean up transients.
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wp_gamify_rate_%'" );
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_wp_gamify_rate_%'" );
	}
}
