<?php
/**
 * Rate limiting class.
 *
 * @package WP_Gamify_Bridge
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Gamify_Bridge_Rate_Limiter
 */
class WP_Gamify_Bridge_Rate_Limiter {

	/**
	 * Maximum requests per user per minute.
	 *
	 * @var int
	 */
	private $max_requests_per_minute = 60;

	/**
	 * Maximum requests per user per hour.
	 *
	 * @var int
	 */
	private $max_requests_per_hour = 500;

	/**
	 * Transient prefix for rate limiting.
	 *
	 * @var string
	 */
	private $transient_prefix = 'wp_gamify_rate_';

	/**
	 * Check if user has exceeded rate limit.
	 *
	 * @param int $user_id User ID to check.
	 * @return bool|WP_Error True if within limits, WP_Error if exceeded.
	 */
	public function check_rate_limit( $user_id ) {
		// Check minute limit.
		$minute_check = $this->check_minute_limit( $user_id );
		if ( is_wp_error( $minute_check ) ) {
			return $minute_check;
		}

		// Check hour limit.
		$hour_check = $this->check_hour_limit( $user_id );
		if ( is_wp_error( $hour_check ) ) {
			return $hour_check;
		}

		return true;
	}

	/**
	 * Check minute rate limit.
	 *
	 * @param int $user_id User ID.
	 * @return bool|WP_Error True if within limits, WP_Error if exceeded.
	 */
	private function check_minute_limit( $user_id ) {
		$transient_key = $this->transient_prefix . 'min_' . $user_id;
		$current_count = get_transient( $transient_key );

		if ( false === $current_count ) {
			$current_count = 0;
		}

		if ( $current_count >= $this->max_requests_per_minute ) {
			return new WP_Error(
				'rate_limit_exceeded',
				sprintf(
					/* translators: %d: maximum requests per minute */
					__( 'Rate limit exceeded. Maximum %d requests per minute.', 'wp-gamify-bridge' ),
					$this->max_requests_per_minute
				),
				array(
					'status'      => 429,
					'retry_after' => 60,
				)
			);
		}

		return true;
	}

	/**
	 * Check hour rate limit.
	 *
	 * @param int $user_id User ID.
	 * @return bool|WP_Error True if within limits, WP_Error if exceeded.
	 */
	private function check_hour_limit( $user_id ) {
		$transient_key = $this->transient_prefix . 'hour_' . $user_id;
		$current_count = get_transient( $transient_key );

		if ( false === $current_count ) {
			$current_count = 0;
		}

		if ( $current_count >= $this->max_requests_per_hour ) {
			return new WP_Error(
				'rate_limit_exceeded',
				sprintf(
					/* translators: %d: maximum requests per hour */
					__( 'Rate limit exceeded. Maximum %d requests per hour.', 'wp-gamify-bridge' ),
					$this->max_requests_per_hour
				),
				array(
					'status'      => 429,
					'retry_after' => 3600,
				)
			);
		}

		return true;
	}

	/**
	 * Increment rate limit counters.
	 *
	 * @param int $user_id User ID.
	 */
	public function increment_counters( $user_id ) {
		// Increment minute counter.
		$minute_key   = $this->transient_prefix . 'min_' . $user_id;
		$minute_count = get_transient( $minute_key );

		if ( false === $minute_count ) {
			set_transient( $minute_key, 1, MINUTE_IN_SECONDS );
		} else {
			set_transient( $minute_key, $minute_count + 1, MINUTE_IN_SECONDS );
		}

		// Increment hour counter.
		$hour_key   = $this->transient_prefix . 'hour_' . $user_id;
		$hour_count = get_transient( $hour_key );

		if ( false === $hour_count ) {
			set_transient( $hour_key, 1, HOUR_IN_SECONDS );
		} else {
			set_transient( $hour_key, $hour_count + 1, HOUR_IN_SECONDS );
		}
	}

	/**
	 * Get current rate limit status for user.
	 *
	 * @param int $user_id User ID.
	 * @return array Rate limit status.
	 */
	public function get_rate_limit_status( $user_id ) {
		$minute_key   = $this->transient_prefix . 'min_' . $user_id;
		$hour_key     = $this->transient_prefix . 'hour_' . $user_id;
		$minute_count = get_transient( $minute_key );
		$hour_count   = get_transient( $hour_key );

		return array(
			'requests_this_minute' => $minute_count ? $minute_count : 0,
			'requests_this_hour'   => $hour_count ? $hour_count : 0,
			'minute_limit'         => $this->max_requests_per_minute,
			'hour_limit'           => $this->max_requests_per_hour,
			'minute_remaining'     => max( 0, $this->max_requests_per_minute - ( $minute_count ? $minute_count : 0 ) ),
			'hour_remaining'       => max( 0, $this->max_requests_per_hour - ( $hour_count ? $hour_count : 0 ) ),
		);
	}

	/**
	 * Reset rate limit counters for user.
	 *
	 * @param int $user_id User ID.
	 */
	public function reset_counters( $user_id ) {
		delete_transient( $this->transient_prefix . 'min_' . $user_id );
		delete_transient( $this->transient_prefix . 'hour_' . $user_id );
	}

	/**
	 * Set maximum requests per minute.
	 *
	 * @param int $max_requests Maximum requests.
	 */
	public function set_max_requests_per_minute( $max_requests ) {
		$this->max_requests_per_minute = absint( $max_requests );
	}

	/**
	 * Set maximum requests per hour.
	 *
	 * @param int $max_requests Maximum requests.
	 */
	public function set_max_requests_per_hour( $max_requests ) {
		$this->max_requests_per_hour = absint( $max_requests );
	}

	/**
	 * Check if rate limiting is enabled.
	 *
	 * @return bool True if enabled.
	 */
	public function is_enabled() {
		return apply_filters( 'wp_gamify_bridge_rate_limiting_enabled', true );
	}

	/**
	 * Whitelist a user from rate limiting.
	 *
	 * @param int $user_id User ID.
	 * @return bool True if user is whitelisted.
	 */
	public function is_whitelisted( $user_id ) {
		$whitelisted_users = apply_filters( 'wp_gamify_bridge_rate_limit_whitelist', array() );
		return in_array( $user_id, $whitelisted_users, true );
	}
}
