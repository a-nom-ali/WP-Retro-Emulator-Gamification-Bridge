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

		$roms = array();
		if ( class_exists( 'WP_Gamify_Bridge_Rom_Library_Service' ) ) {
			$roms = WP_Gamify_Bridge_Rom_Library_Service::get_list(
				array(
					'posts_per_page' => 100,
				)
			);
		}

		wp_localize_script(
			'wp-gamify-emulator-hooks',
			'wpGamifyBridge',
			array(
				'apiUrl'   => rest_url( 'gamify/v1/event' ),
				'romsApi'  => rest_url( 'gamify/v1/roms' ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'userId'   => get_current_user_id(),
				'userName' => wp_get_current_user()->user_login,
				'roomId'   => $this->get_current_room_id(),
				'debug'    => defined( 'WP_DEBUG' ) && WP_DEBUG,
				'roms'     => $roms,
			)
		);

		if ( $this->is_emulator_page() ) {
			wp_enqueue_style( 'dashicons' );
			wp_enqueue_script(
				'wp-gamify-jsnes',
				WP_GAMIFY_BRIDGE_PLUGIN_URL . 'js/vendor/jsnes.min.js',
				array(),
				'1.2.0',
				true
			);

			wp_enqueue_script(
				'wp-gamify-retro-player',
				WP_GAMIFY_BRIDGE_PLUGIN_URL . 'js/retro-emulator-player.js',
				array( 'wp-gamify-jsnes', 'wp-gamify-emulator-hooks' ),
				WP_GAMIFY_BRIDGE_VERSION,
				true
			);
		}
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

	/**
	 * Determine if current page contains the emulator shortcode or block.
	 *
	 * @return bool
	 */
	private function is_emulator_page() {
		global $post;

		if ( ! $post ) {
			return false;
		}

		// Check for shortcodes.
		if ( has_shortcode( $post->post_content, 'retro_emulator' ) || has_shortcode( $post->post_content, 'nes' ) ) {
			return true;
		}

		// Check for blocks.
		if ( has_block( 'wp-gamify/retro-emulator', $post ) || has_block( 'wp-gamify/rom-player', $post ) ) {
			return true;
		}

		return false;
	}
}
