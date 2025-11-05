<?php
/**
 * MyCred integration.
 *
 * @package WP_Gamify_Bridge
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Gamify_Bridge_MyCred
 */
class WP_Gamify_Bridge_MyCred {

	/**
	 * Single instance of the class.
	 *
	 * @var WP_Gamify_Bridge_MyCred
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return WP_Gamify_Bridge_MyCred
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
		add_action( 'wp_gamify_bridge_mycred_event', array( $this, 'handle_event' ), 10, 4 );
		add_filter( 'mycred_setup_hooks', array( $this, 'register_hooks' ) );
	}

	/**
	 * Register custom MyCred hooks.
	 *
	 * @param array $hooks Existing hooks.
	 * @return array Modified hooks.
	 */
	public function register_hooks( $hooks ) {
		$hooks['retro_emulator'] = array(
			'title'       => __( 'Retro Emulator Events', 'wp-gamify-bridge' ),
			'description' => __( 'Award points for retro game events.', 'wp-gamify-bridge' ),
			'callback'    => array( $this, 'mycred_hook_callback' ),
		);

		return $hooks;
	}

	/**
	 * MyCred hook callback (placeholder).
	 */
	public function mycred_hook_callback() {
		// MyCred hook configuration would go here.
	}

	/**
	 * Handle gamification event.
	 *
	 * @param string $event_type Event type.
	 * @param int    $user_id User ID.
	 * @param int    $score Score value.
	 * @param array  $data Event data.
	 */
	public function handle_event( $event_type, $user_id, $score, $data ) {
		if ( ! function_exists( 'mycred_add' ) ) {
			return;
		}

		// Point awards based on event type.
		$point_awards = array(
			'level_complete'     => 100,
			'game_over'          => 10,
			'score_milestone'    => 50,
			'achievement_unlock' => 200,
			'death'              => -5,
		);

		$points = isset( $point_awards[ $event_type ] ) ? $point_awards[ $event_type ] : 0;

		if ( $points !== 0 ) {
			mycred_add(
				'retro_emulator_' . $event_type,
				$user_id,
				$points,
				sprintf(
					/* translators: %s: event type */
					__( 'Retro emulator event: %s', 'wp-gamify-bridge' ),
					$event_type
				),
				0,
				'',
				'mycred_default'
			);
		}
	}
}
