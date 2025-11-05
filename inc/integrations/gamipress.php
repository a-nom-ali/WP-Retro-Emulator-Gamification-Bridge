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
	 * Default XP awards per event type.
	 *
	 * @var array
	 */
	private $default_xp_awards = array(
		'level_complete'     => 100,
		'game_over'          => 10,
		'score_milestone'    => 50,
		'achievement_unlock' => 200,
		'death'              => 5,
		'game_start'         => 25,
	);

	/**
	 * Point type to use for awards.
	 *
	 * @var string
	 */
	private $point_type = 'points';

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
		add_filter( 'gamipress_specific_activity_triggers', array( $this, 'register_specific_triggers' ) );
		add_action( 'init', array( $this, 'register_custom_point_types' ) );

		// Allow customization of point type.
		$this->point_type = apply_filters( 'wp_gamify_bridge_gamipress_point_type', $this->point_type );
	}

	/**
	 * Register custom GamiPress triggers.
	 *
	 * @param array $triggers Existing triggers.
	 * @return array Modified triggers.
	 */
	public function register_triggers( $triggers ) {
		$triggers[ __( 'Retro Emulator', 'wp-gamify-bridge' ) ] = array(
			'wp_gamify_game_start'         => __( 'Start a game', 'wp-gamify-bridge' ),
			'wp_gamify_level_complete'     => __( 'Complete a level', 'wp-gamify-bridge' ),
			'wp_gamify_game_over'          => __( 'Game over', 'wp-gamify-bridge' ),
			'wp_gamify_score_milestone'    => __( 'Reach score milestone', 'wp-gamify-bridge' ),
			'wp_gamify_achievement_unlock' => __( 'Unlock achievement', 'wp-gamify-bridge' ),
			'wp_gamify_death'              => __( 'Player death', 'wp-gamify-bridge' ),
		);

		return $triggers;
	}

	/**
	 * Register specific activity triggers for conditional requirements.
	 *
	 * @param array $specific_triggers Existing specific triggers.
	 * @return array Modified specific triggers.
	 */
	public function register_specific_triggers( $specific_triggers ) {
		// Level complete with specific level number.
		$specific_triggers['wp_gamify_level_complete'] = array( 'level' );

		// Score milestone with minimum score.
		$specific_triggers['wp_gamify_score_milestone'] = array( 'score' );

		// Game over with minimum score.
		$specific_triggers['wp_gamify_game_over'] = array( 'score' );

		return $specific_triggers;
	}

	/**
	 * Register custom point types for retro gaming.
	 */
	public function register_custom_point_types() {
		if ( ! function_exists( 'gamipress_register_points_type' ) ) {
			return;
		}

		// Register "Arcade Tokens" point type.
		gamipress_register_points_type(
			0,
			__( 'Arcade Token', 'wp-gamify-bridge' ),
			__( 'Arcade Tokens', 'wp-gamify-bridge' ),
			'arcade_tokens'
		);

		// Register "Game Coins" point type.
		gamipress_register_points_type(
			0,
			__( 'Game Coin', 'wp-gamify-bridge' ),
			__( 'Game Coins', 'wp-gamify-bridge' ),
			'game_coins'
		);
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
			'death'              => 'wp_gamify_death',
			'game_start'         => 'wp_gamify_game_start',
		);

		if ( ! isset( $trigger_map[ $event_type ] ) ) {
			return;
		}

		$trigger = $trigger_map[ $event_type ];

		// Award points based on event type.
		$this->award_points( $event_type, $user_id, $score, $data );

		// Trigger GamiPress event with additional context.
		do_action( $trigger, $user_id, $score, $data );

		// Log activity for GamiPress reports.
		$this->log_activity( $event_type, $user_id, $score, $data );

		// Check for achievements.
		$this->check_achievements( $event_type, $user_id, $score, $data );
	}

	/**
	 * Award points to user.
	 *
	 * @param string $event_type Event type.
	 * @param int    $user_id User ID.
	 * @param int    $score Score value.
	 * @param array  $data Event data.
	 */
	private function award_points( $event_type, $user_id, $score, $data ) {
		if ( ! function_exists( 'gamipress_award_points_to_user' ) ) {
			return;
		}

		// Get base XP for event type.
		$base_xp = isset( $this->default_xp_awards[ $event_type ] ) ? $this->default_xp_awards[ $event_type ] : 0;

		// Apply multipliers.
		$xp = $this->calculate_xp_with_multipliers( $base_xp, $event_type, $score, $data );

		// Allow filtering of XP amount.
		$xp = apply_filters( 'wp_gamify_bridge_gamipress_xp_award', $xp, $event_type, $user_id, $score, $data );

		if ( $xp > 0 ) {
			gamipress_award_points_to_user( $user_id, $xp, $this->point_type );

			// Log XP award.
			do_action( 'wp_gamify_bridge_gamipress_xp_awarded', $user_id, $xp, $event_type, $data );
		}
	}

	/**
	 * Calculate XP with multipliers based on event context.
	 *
	 * @param int    $base_xp Base XP amount.
	 * @param string $event_type Event type.
	 * @param int    $score Score value.
	 * @param array  $data Event data.
	 * @return int Calculated XP.
	 */
	private function calculate_xp_with_multipliers( $base_xp, $event_type, $score, $data ) {
		$xp = $base_xp;

		// Score-based multiplier for level_complete and game_over.
		if ( in_array( $event_type, array( 'level_complete', 'game_over' ), true ) && $score > 0 ) {
			// Add bonus XP based on score (1 XP per 100 points).
			$score_bonus = floor( $score / 100 );
			$xp         += $score_bonus;
		}

		// Level-based multiplier.
		if ( isset( $data['level'] ) && $data['level'] > 1 ) {
			$level_multiplier = 1 + ( $data['level'] * 0.1 ); // 10% per level.
			$xp               = floor( $xp * $level_multiplier );
		}

		// Difficulty multiplier.
		if ( isset( $data['difficulty'] ) ) {
			$difficulty_multipliers = array(
				'easy'   => 1.0,
				'normal' => 1.5,
				'hard'   => 2.0,
				'expert' => 3.0,
			);

			$difficulty = strtolower( $data['difficulty'] );
			if ( isset( $difficulty_multipliers[ $difficulty ] ) ) {
				$xp = floor( $xp * $difficulty_multipliers[ $difficulty ] );
			}
		}

		// Time-based bonus for fast completions.
		if ( isset( $data['time'] ) && $data['time'] < 60 && 'level_complete' === $event_type ) {
			// Speed bonus: 50% extra XP for completing level in under 60 seconds.
			$xp = floor( $xp * 1.5 );
		}

		return absint( $xp );
	}

	/**
	 * Log activity for GamiPress reporting.
	 *
	 * @param string $event_type Event type.
	 * @param int    $user_id User ID.
	 * @param int    $score Score value.
	 * @param array  $data Event data.
	 */
	private function log_activity( $event_type, $user_id, $score, $data ) {
		if ( ! function_exists( 'gamipress_insert_user_activity' ) ) {
			return;
		}

		$activity_data = array(
			'user_id' => $user_id,
			'type'    => 'retro_game_event',
			'title'   => $this->get_activity_title( $event_type, $data ),
			'data'    => array(
				'event_type' => $event_type,
				'score'      => $score,
				'event_data' => $data,
			),
		);

		gamipress_insert_user_activity( $activity_data );
	}

	/**
	 * Get human-readable activity title.
	 *
	 * @param string $event_type Event type.
	 * @param array  $data Event data.
	 * @return string Activity title.
	 */
	private function get_activity_title( $event_type, $data ) {
		$titles = array(
			'game_start'         => __( 'Started playing a retro game', 'wp-gamify-bridge' ),
			'level_complete'     => sprintf( __( 'Completed level %d', 'wp-gamify-bridge' ), isset( $data['level'] ) ? $data['level'] : 1 ),
			'game_over'          => sprintf( __( 'Game over with score: %d', 'wp-gamify-bridge' ), isset( $data['score'] ) ? $data['score'] : 0 ),
			'score_milestone'    => sprintf( __( 'Reached score milestone: %d', 'wp-gamify-bridge' ), isset( $data['score'] ) ? $data['score'] : 0 ),
			'achievement_unlock' => sprintf( __( 'Unlocked achievement: %s', 'wp-gamify-bridge' ), isset( $data['achievement'] ) ? $data['achievement'] : 'Unknown' ),
			'death'              => __( 'Player died in game', 'wp-gamify-bridge' ),
		);

		return isset( $titles[ $event_type ] ) ? $titles[ $event_type ] : __( 'Retro game event', 'wp-gamify-bridge' );
	}

	/**
	 * Check for and award achievements.
	 *
	 * @param string $event_type Event type.
	 * @param int    $user_id User ID.
	 * @param int    $score Score value.
	 * @param array  $data Event data.
	 */
	private function check_achievements( $event_type, $user_id, $score, $data ) {
		// Custom achievement logic.
		$achievements = $this->get_custom_achievements( $event_type, $score, $data );

		foreach ( $achievements as $achievement ) {
			$this->award_achievement( $user_id, $achievement );
		}
	}

	/**
	 * Get custom achievements based on event.
	 *
	 * @param string $event_type Event type.
	 * @param int    $score Score value.
	 * @param array  $data Event data.
	 * @return array Achievements to award.
	 */
	private function get_custom_achievements( $event_type, $score, $data ) {
		$achievements = array();

		// Score-based achievements.
		if ( 'score_milestone' === $event_type ) {
			if ( $score >= 100000 ) {
				$achievements[] = 'high_score_master';
			} elseif ( $score >= 50000 ) {
				$achievements[] = 'score_champion';
			} elseif ( $score >= 10000 ) {
				$achievements[] = 'score_achiever';
			}
		}

		// Level-based achievements.
		if ( 'level_complete' === $event_type && isset( $data['level'] ) ) {
			if ( $data['level'] >= 50 ) {
				$achievements[] = 'level_master';
			} elseif ( $data['level'] >= 25 ) {
				$achievements[] = 'level_expert';
			} elseif ( $data['level'] >= 10 ) {
				$achievements[] = 'level_veteran';
			}
		}

		// Speed run achievement.
		if ( 'level_complete' === $event_type && isset( $data['time'] ) && $data['time'] < 30 ) {
			$achievements[] = 'speed_runner';
		}

		// Allow filtering of achievements.
		return apply_filters( 'wp_gamify_bridge_gamipress_achievements', $achievements, $event_type, $score, $data );
	}

	/**
	 * Award achievement to user.
	 *
	 * @param int    $user_id User ID.
	 * @param string $achievement Achievement slug.
	 */
	private function award_achievement( $user_id, $achievement ) {
		// This is a simplified version - in production, you'd look up the actual achievement post
		// and use gamipress_award_achievement_to_user().
		do_action( 'wp_gamify_bridge_award_achievement', $user_id, $achievement );

		// Log achievement award.
		if ( function_exists( 'gamipress_insert_user_activity' ) ) {
			gamipress_insert_user_activity(
				array(
					'user_id' => $user_id,
					'type'    => 'achievement_earned',
					'title'   => sprintf( __( 'Earned achievement: %s', 'wp-gamify-bridge' ), $achievement ),
				)
			);
		}
	}

	/**
	 * Get user's total retro gaming XP.
	 *
	 * @param int $user_id User ID.
	 * @return int Total XP.
	 */
	public function get_user_total_xp( $user_id ) {
		if ( ! function_exists( 'gamipress_get_user_points' ) ) {
			return 0;
		}

		return gamipress_get_user_points( $user_id, $this->point_type );
	}

	/**
	 * Get user's rank based on XP.
	 *
	 * @param int $user_id User ID.
	 * @return string User rank.
	 */
	public function get_user_rank( $user_id ) {
		$xp = $this->get_user_total_xp( $user_id );

		$ranks = array(
			10000 => __( 'Arcade Legend', 'wp-gamify-bridge' ),
			5000  => __( 'Gaming Master', 'wp-gamify-bridge' ),
			2000  => __( 'Pro Gamer', 'wp-gamify-bridge' ),
			1000  => __( 'Skilled Player', 'wp-gamify-bridge' ),
			500   => __( 'Regular Gamer', 'wp-gamify-bridge' ),
			100   => __( 'Casual Player', 'wp-gamify-bridge' ),
			0     => __( 'Beginner', 'wp-gamify-bridge' ),
		);

		foreach ( $ranks as $threshold => $rank ) {
			if ( $xp >= $threshold ) {
				return $rank;
			}
		}

		return __( 'Beginner', 'wp-gamify-bridge' );
	}
}
