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
	 * Default point awards per event type.
	 *
	 * @var array
	 */
	private $default_point_awards = array(
		'level_complete'     => 100,
		'game_over'          => 10,
		'score_milestone'    => 50,
		'achievement_unlock' => 200,
		'death'              => -5,
		'game_start'         => 25,
	);

	/**
	 * Point type to use for awards.
	 *
	 * @var string
	 */
	private $point_type = 'mycred_default';

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
		add_action( 'mycred_init', array( $this, 'register_badges' ) );

		// Allow customization of point type.
		$this->point_type = apply_filters( 'wp_gamify_bridge_mycred_point_type', $this->point_type );
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
	 * MyCred hook callback for admin settings.
	 */
	public function mycred_hook_callback() {
		// This would render admin settings for the hook
		// Not implemented in this version.
	}

	/**
	 * Register custom badges for retro gaming achievements.
	 */
	public function register_badges() {
		if ( ! function_exists( 'mycred_add_badge' ) ) {
			return;
		}

		// Badges would be registered here
		// This is typically done through the WordPress admin, not programmatically.
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

		// Award points.
		$this->award_points( $event_type, $user_id, $score, $data );

		// Check for rank progression.
		$this->check_rank_progression( $user_id );

		// Check for badges.
		$this->check_badges( $event_type, $user_id, $score, $data );

		// Log activity.
		$this->log_activity( $event_type, $user_id, $score, $data );
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
		// Get base points for event type.
		$base_points = isset( $this->default_point_awards[ $event_type ] ) ? $this->default_point_awards[ $event_type ] : 0;

		// Apply multipliers.
		$points = $this->calculate_points_with_multipliers( $base_points, $event_type, $score, $data );

		// Allow filtering of points amount.
		$points = apply_filters( 'wp_gamify_bridge_mycred_points_award', $points, $event_type, $user_id, $score, $data );

		if ( $points !== 0 ) {
			// Generate descriptive log entry.
			$log_entry = $this->generate_log_entry( $event_type, $score, $data );

			mycred_add(
				'retro_emulator_' . $event_type,
				$user_id,
				$points,
				$log_entry,
				0,
				'',
				$this->point_type
			);

			// Log points award.
			do_action( 'wp_gamify_bridge_mycred_points_awarded', $user_id, $points, $event_type, $data );
		}
	}

	/**
	 * Calculate points with multipliers based on event context.
	 *
	 * @param int    $base_points Base points amount.
	 * @param string $event_type Event type.
	 * @param int    $score Score value.
	 * @param array  $data Event data.
	 * @return int Calculated points.
	 */
	private function calculate_points_with_multipliers( $base_points, $event_type, $score, $data ) {
		$points = $base_points;

		// Score-based bonus for level_complete and game_over.
		if ( in_array( $event_type, array( 'level_complete', 'game_over' ), true ) && $score > 0 ) {
			// Add bonus points based on score (1 point per 100 game points).
			$score_bonus = floor( $score / 100 );
			$points     += $score_bonus;
		}

		// Level-based multiplier.
		if ( isset( $data['level'] ) && $data['level'] > 1 ) {
			$level_bonus = $data['level'] * 5; // 5 points per level.
			$points     += $level_bonus;
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
				$points = floor( $points * $difficulty_multipliers[ $difficulty ] );
			}
		}

		// Time-based bonus for fast completions.
		if ( isset( $data['time'] ) && $data['time'] < 60 && 'level_complete' === $event_type ) {
			// Speed bonus: 50% extra points.
			$points = floor( $points * 1.5 );
		}

		// Streak bonus (if implemented).
		if ( isset( $data['streak'] ) && $data['streak'] > 1 ) {
			$streak_multiplier = 1 + ( min( $data['streak'], 10 ) * 0.05 ); // Max 50% streak bonus.
			$points            = floor( $points * $streak_multiplier );
		}

		return absint( $points );
	}

	/**
	 * Generate descriptive log entry.
	 *
	 * @param string $event_type Event type.
	 * @param int    $score Score value.
	 * @param array  $data Event data.
	 * @return string Log entry.
	 */
	private function generate_log_entry( $event_type, $score, $data ) {
		$entries = array(
			'game_start'         => __( 'Started playing retro game', 'wp-gamify-bridge' ),
			'level_complete'     => sprintf(
				__( 'Completed level %d with score %d', 'wp-gamify-bridge' ),
				isset( $data['level'] ) ? $data['level'] : 1,
				$score
			),
			'game_over'          => sprintf(
				__( 'Game over - Final score: %d', 'wp-gamify-bridge' ),
				$score
			),
			'score_milestone'    => sprintf(
				__( 'Reached score milestone: %d points', 'wp-gamify-bridge' ),
				$score
			),
			'achievement_unlock' => sprintf(
				__( 'Unlocked achievement: %s', 'wp-gamify-bridge' ),
				isset( $data['achievement'] ) ? $data['achievement'] : 'Unknown'
			),
			'death'              => sprintf(
				__( 'Player death - Lives remaining: %d', 'wp-gamify-bridge' ),
				isset( $data['lives'] ) ? $data['lives'] : 0
			),
		);

		return isset( $entries[ $event_type ] ) ? $entries[ $event_type ] : sprintf(
			__( 'Retro game event: %s', 'wp-gamify-bridge' ),
			$event_type
		);
	}

	/**
	 * Check and update rank progression.
	 *
	 * @param int $user_id User ID.
	 */
	private function check_rank_progression( $user_id ) {
		if ( ! function_exists( 'mycred_get_users_rank' ) || ! function_exists( 'mycred_save_users_rank' ) ) {
			return;
		}

		$current_points = mycred_get_users_balance( $user_id, $this->point_type );
		$current_rank   = mycred_get_users_rank( $user_id );
		$new_rank       = $this->calculate_rank_from_points( $current_points );

		if ( $new_rank && $new_rank !== $current_rank ) {
			mycred_save_users_rank( $user_id, $new_rank );

			// Log rank change.
			do_action( 'wp_gamify_bridge_mycred_rank_changed', $user_id, $current_rank, $new_rank );
		}
	}

	/**
	 * Calculate rank from points.
	 *
	 * @param int $points User's total points.
	 * @return string|null Rank name or null.
	 */
	private function calculate_rank_from_points( $points ) {
		$ranks = array(
			10000 => 'arcade_legend',
			5000  => 'gaming_master',
			2000  => 'pro_gamer',
			1000  => 'skilled_player',
			500   => 'regular_gamer',
			100   => 'casual_player',
			0     => 'beginner',
		);

		foreach ( $ranks as $threshold => $rank ) {
			if ( $points >= $threshold ) {
				return $rank;
			}
		}

		return 'beginner';
	}

	/**
	 * Check for and award badges.
	 *
	 * @param string $event_type Event type.
	 * @param int    $user_id User ID.
	 * @param int    $score Score value.
	 * @param array  $data Event data.
	 */
	private function check_badges( $event_type, $user_id, $score, $data ) {
		// Badge checking logic.
		$badges = $this->get_earned_badges( $event_type, $score, $data, $user_id );

		foreach ( $badges as $badge ) {
			$this->award_badge( $user_id, $badge );
		}
	}

	/**
	 * Get badges earned from event.
	 *
	 * @param string $event_type Event type.
	 * @param int    $score Score value.
	 * @param array  $data Event data.
	 * @param int    $user_id User ID.
	 * @return array Badge slugs.
	 */
	private function get_earned_badges( $event_type, $score, $data, $user_id ) {
		$badges = array();

		// Score-based badges.
		if ( 'score_milestone' === $event_type ) {
			if ( $score >= 100000 && ! $this->user_has_badge( $user_id, 'high_score_master' ) ) {
				$badges[] = 'high_score_master';
			} elseif ( $score >= 50000 && ! $this->user_has_badge( $user_id, 'score_champion' ) ) {
				$badges[] = 'score_champion';
			} elseif ( $score >= 10000 && ! $this->user_has_badge( $user_id, 'score_achiever' ) ) {
				$badges[] = 'score_achiever';
			}
		}

		// Level-based badges.
		if ( 'level_complete' === $event_type && isset( $data['level'] ) ) {
			if ( $data['level'] >= 50 && ! $this->user_has_badge( $user_id, 'level_master' ) ) {
				$badges[] = 'level_master';
			} elseif ( $data['level'] >= 25 && ! $this->user_has_badge( $user_id, 'level_expert' ) ) {
				$badges[] = 'level_expert';
			}
		}

		// Allow filtering of badges.
		return apply_filters( 'wp_gamify_bridge_mycred_badges', $badges, $event_type, $score, $data, $user_id );
	}

	/**
	 * Check if user has badge.
	 *
	 * @param int    $user_id User ID.
	 * @param string $badge_slug Badge slug.
	 * @return bool True if user has badge.
	 */
	private function user_has_badge( $user_id, $badge_slug ) {
		// This is a simplified check
		// In production, you'd check actual badge awards.
		$badges = get_user_meta( $user_id, 'mycred_badges', true );
		return is_array( $badges ) && in_array( $badge_slug, $badges, true );
	}

	/**
	 * Award badge to user.
	 *
	 * @param int    $user_id User ID.
	 * @param string $badge_slug Badge slug.
	 */
	private function award_badge( $user_id, $badge_slug ) {
		// Simplified badge award
		// In production, use myCred's badge functions.
		$badges   = get_user_meta( $user_id, 'mycred_badges', true );
		$badges   = is_array( $badges ) ? $badges : array();
		$badges[] = $badge_slug;

		update_user_meta( $user_id, 'mycred_badges', $badges );

		// Log badge award.
		do_action( 'wp_gamify_bridge_mycred_badge_awarded', $user_id, $badge_slug );
	}

	/**
	 * Log activity.
	 *
	 * @param string $event_type Event type.
	 * @param int    $user_id User ID.
	 * @param int    $score Score value.
	 * @param array  $data Event data.
	 */
	private function log_activity( $event_type, $user_id, $score, $data ) {
		// Activity logging would integrate with myCred's log system.
		do_action( 'wp_gamify_bridge_mycred_activity_logged', $user_id, $event_type, $score, $data );
	}

	/**
	 * Get user's total points.
	 *
	 * @param int $user_id User ID.
	 * @return int Total points.
	 */
	public function get_user_total_points( $user_id ) {
		if ( ! function_exists( 'mycred_get_users_balance' ) ) {
			return 0;
		}

		return mycred_get_users_balance( $user_id, $this->point_type );
	}

	/**
	 * Get user's rank.
	 *
	 * @param int $user_id User ID.
	 * @return string User rank.
	 */
	public function get_user_rank( $user_id ) {
		if ( function_exists( 'mycred_get_users_rank' ) ) {
			return mycred_get_users_rank( $user_id );
		}

		// Fallback to calculating from points.
		$points = $this->get_user_total_points( $user_id );
		return $this->calculate_rank_from_points( $points );
	}

	/**
	 * Get user's rank title.
	 *
	 * @param int $user_id User ID.
	 * @return string Rank title.
	 */
	public function get_user_rank_title( $user_id ) {
		$rank = $this->get_user_rank( $user_id );

		$rank_titles = array(
			'arcade_legend'  => __( 'Arcade Legend', 'wp-gamify-bridge' ),
			'gaming_master'  => __( 'Gaming Master', 'wp-gamify-bridge' ),
			'pro_gamer'      => __( 'Pro Gamer', 'wp-gamify-bridge' ),
			'skilled_player' => __( 'Skilled Player', 'wp-gamify-bridge' ),
			'regular_gamer'  => __( 'Regular Gamer', 'wp-gamify-bridge' ),
			'casual_player'  => __( 'Casual Player', 'wp-gamify-bridge' ),
			'beginner'       => __( 'Beginner', 'wp-gamify-bridge' ),
		);

		return isset( $rank_titles[ $rank ] ) ? $rank_titles[ $rank ] : __( 'Beginner', 'wp-gamify-bridge' );
	}
}
