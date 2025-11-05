<?php
/**
 * GamiPress integration.
 *
 * @package WP_Gamify_Bridge
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Gamify_Bridge_GamiPress
 */
class WP_Gamify_Bridge_GamiPress {

	/**
	 * Single instance of the class.
	 *
	 * @var WP_Gamify_Bridge_GamiPress
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return WP_Gamify_Bridge_GamiPress
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
		add_action( 'wp_gamify_bridge_gamipress_event', array( $this, 'handle_event' ), 10, 4 );
		add_filter( 'gamipress_activity_triggers', array( $this, 'register_triggers' ) );
	}

	/**
	 * Register custom GamiPress triggers.
	 *
	 * @param array $triggers Existing triggers.
	 * @return array Modified triggers.
	 */
	public function register_triggers( $triggers ) {
		$triggers[ __( 'Retro Emulator', 'wp-gamify-bridge' ) ] = array(
			'wp_gamify_level_complete'     => __( 'Complete a level', 'wp-gamify-bridge' ),
			'wp_gamify_game_over'          => __( 'Game over', 'wp-gamify-bridge' ),
			'wp_gamify_score_milestone'    => __( 'Reach score milestone', 'wp-gamify-bridge' ),
			'wp_gamify_achievement_unlock' => __( 'Unlock achievement', 'wp-gamify-bridge' ),
		);

		return $triggers;
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
		// Map event types to GamiPress triggers.
		$trigger_map = array(
			'level_complete'     => 'wp_gamify_level_complete',
			'game_over'          => 'wp_gamify_game_over',
			'score_milestone'    => 'wp_gamify_score_milestone',
			'achievement_unlock' => 'wp_gamify_achievement_unlock',
		);

		if ( ! isset( $trigger_map[ $event_type ] ) ) {
			return;
		}

		$trigger = $trigger_map[ $event_type ];

		// Award XP based on event type.
		$xp_awards = array(
			'level_complete'     => 100,
			'game_over'          => 10,
			'score_milestone'    => 50,
			'achievement_unlock' => 200,
		);

		$xp = isset( $xp_awards[ $event_type ] ) ? $xp_awards[ $event_type ] : 0;

		if ( $xp > 0 && function_exists( 'gamipress_award_points_to_user' ) ) {
			gamipress_award_points_to_user( $user_id, $xp, 'points' );
		}

		// Trigger GamiPress event.
		if ( function_exists( 'do_action' ) ) {
			do_action( $trigger, $user_id, $score, $data );
		}
	}
}
