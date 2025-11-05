<?php
/**
 * Script enqueuing class.
 *
 * @package WP_Gamify_Bridge
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Gamify_Bridge_Script_Enqueuer
 */
class WP_Gamify_Bridge_Script_Enqueuer {

	/**
	 * Single instance of the class.
	 *
	 * @var WP_Gamify_Bridge_Script_Enqueuer
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return WP_Gamify_Bridge_Script_Enqueuer
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
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Enqueue scripts.
	 */
	public function enqueue_scripts() {
		// Enqueue plugin styles.
		wp_enqueue_style(
			'wp-gamify-bridge',
			WP_GAMIFY_BRIDGE_PLUGIN_URL . 'css/wp-gamify-bridge.css',
			array(),
			WP_GAMIFY_BRIDGE_VERSION
		);

		// Enqueue emulator hooks script.
		wp_enqueue_script(
			'wp-gamify-emulator-hooks',
			WP_GAMIFY_BRIDGE_PLUGIN_URL . 'js/emulator-hooks.js',
			array( 'jquery' ),
			WP_GAMIFY_BRIDGE_VERSION,
			true
		);

		// Localize script with data.
		wp_localize_script(
			'wp-gamify-emulator-hooks',
			'wpGamifyBridge',
			array(
				'apiUrl'   => rest_url( 'gamify/v1/event' ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'userId'   => get_current_user_id(),
				'userName' => wp_get_current_user()->user_login,
				'roomId'   => $this->get_current_room_id(),
				'debug'    => defined( 'WP_DEBUG' ) && WP_DEBUG,
			)
		);
	}

	/**
	 * Get current room ID from page context.
	 *
	 * @return string|null Room ID or null.
	 */
	private function get_current_room_id() {
		global $post;

		if ( ! $post || ! has_shortcode( $post->post_content, 'retro_room' ) ) {
			return null;
		}

		// Extract room ID from shortcode.
		if ( preg_match( '/\[retro_room[^\]]*id=["\']([^"\']+)["\']/', $post->post_content, $matches ) ) {
			return $matches[1];
		}

		return null;
	}
}
