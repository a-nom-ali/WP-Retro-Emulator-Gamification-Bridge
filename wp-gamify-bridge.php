<?php
/**
 * Plugin Name: WP Retro Emulator Gamification Bridge
 * Plugin URI: https://github.com/nielowait/WP-Retro-Emulator-Gamification-Bridge
 * Description: Bridges JavaScript-based retro game emulators with WordPress gamification systems (GamiPress, MyCred) supporting real-time XP, achievements, and room-based events.
 * Version: 0.1.0
 * Author: Nielo Wait
 * Author URI: https://github.com/nielowait
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-gamify-bridge
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * @package WP_Gamify_Bridge
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'WP_GAMIFY_BRIDGE_VERSION', '0.1.0' );
define( 'WP_GAMIFY_BRIDGE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_GAMIFY_BRIDGE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_GAMIFY_BRIDGE_PLUGIN_FILE', __FILE__ );

/**
 * Main plugin class.
 */
class WP_Gamify_Bridge {

	/**
	 * Single instance of the class.
	 *
	 * @var WP_Gamify_Bridge
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return WP_Gamify_Bridge
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
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Include required files.
	 */
	private function includes() {
		// Core classes.
		require_once WP_GAMIFY_BRIDGE_PLUGIN_DIR . 'inc/class-database.php';
		require_once WP_GAMIFY_BRIDGE_PLUGIN_DIR . 'inc/class-gamify-endpoint.php';
		require_once WP_GAMIFY_BRIDGE_PLUGIN_DIR . 'inc/class-room-manager.php';
		require_once WP_GAMIFY_BRIDGE_PLUGIN_DIR . 'inc/class-script-enqueuer.php';

		// Integration classes.
		require_once WP_GAMIFY_BRIDGE_PLUGIN_DIR . 'inc/integrations/gamipress.php';
		require_once WP_GAMIFY_BRIDGE_PLUGIN_DIR . 'inc/integrations/mycred.php';
	}

	/**
	 * Initialize WordPress hooks.
	 */
	private function init_hooks() {
		register_activation_hook( WP_GAMIFY_BRIDGE_PLUGIN_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( WP_GAMIFY_BRIDGE_PLUGIN_FILE, array( $this, 'deactivate' ) );

		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	/**
	 * Initialize plugin components.
	 */
	public function init() {
		// Initialize database.
		WP_Gamify_Bridge_Database::instance();

		// Initialize REST API.
		WP_Gamify_Bridge_Endpoint::instance();

		// Initialize room manager.
		WP_Gamify_Bridge_Room_Manager::instance();

		// Initialize script enqueuer.
		WP_Gamify_Bridge_Script_Enqueuer::instance();

		// Initialize integrations.
		if ( class_exists( 'GamiPress' ) ) {
			WP_Gamify_Bridge_GamiPress::instance();
		}

		if ( defined( 'MYCRED_VERSION' ) ) {
			WP_Gamify_Bridge_MyCred::instance();
		}

		// Load text domain.
		load_plugin_textdomain( 'wp-gamify-bridge', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Plugin activation hook.
	 */
	public function activate() {
		// Create database tables.
		WP_Gamify_Bridge_Database::create_tables();

		// Set default options.
		add_option( 'wp_gamify_bridge_version', WP_GAMIFY_BRIDGE_VERSION );

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation hook.
	 */
	public function deactivate() {
		// Flush rewrite rules.
		flush_rewrite_rules();
	}
}

/**
 * Initialize the plugin.
 */
function wp_gamify_bridge() {
	return WP_Gamify_Bridge::instance();
}

// Start the plugin.
wp_gamify_bridge();
