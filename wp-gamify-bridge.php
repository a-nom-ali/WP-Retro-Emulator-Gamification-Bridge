<?php
/**
 * Plugin Name: WP Retro Emulator Gamification Bridge
 * Plugin URI: https://github.com/nielowait/WP-Retro-Emulator-Gamification-Bridge
 * Description: Bridges JavaScript-based retro game emulators with WordPress gamification systems (GamiPress, MyCred) supporting real-time XP, achievements, and room-based events.
 * Version: 0.1.2
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
define( 'WP_GAMIFY_BRIDGE_VERSION', '0.1.2' );
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
		$this->register_early_components();
	}

	/**
	 * Include required files.
	 */
	private function includes() {
		// Core classes.
		require_once WP_GAMIFY_BRIDGE_PLUGIN_DIR . 'inc/class-post-types.php';
		require_once WP_GAMIFY_BRIDGE_PLUGIN_DIR . 'inc/class-database.php';
		require_once WP_GAMIFY_BRIDGE_PLUGIN_DIR . 'inc/class-event-validator.php';
		require_once WP_GAMIFY_BRIDGE_PLUGIN_DIR . 'inc/class-rate-limiter.php';
		require_once WP_GAMIFY_BRIDGE_PLUGIN_DIR . 'inc/class-gamify-endpoint.php';
		require_once WP_GAMIFY_BRIDGE_PLUGIN_DIR . 'inc/class-room-manager.php';
		require_once WP_GAMIFY_BRIDGE_PLUGIN_DIR . 'inc/class-room-endpoint.php';
		require_once WP_GAMIFY_BRIDGE_PLUGIN_DIR . 'inc/class-rom-library-service.php';
		require_once WP_GAMIFY_BRIDGE_PLUGIN_DIR . 'inc/class-rom-library-endpoint.php';
		require_once WP_GAMIFY_BRIDGE_PLUGIN_DIR . 'inc/class-emulator-manager.php';
		require_once WP_GAMIFY_BRIDGE_PLUGIN_DIR . 'inc/class-emulator-shortcode.php';
		require_once WP_GAMIFY_BRIDGE_PLUGIN_DIR . 'inc/class-blocks.php';
		require_once WP_GAMIFY_BRIDGE_PLUGIN_DIR . 'inc/class-script-enqueuer.php';

		// Admin classes.
		if ( is_admin() ) {
			require_once WP_GAMIFY_BRIDGE_PLUGIN_DIR . 'admin/class-admin-page.php';
			require_once WP_GAMIFY_BRIDGE_PLUGIN_DIR . 'admin/class-dashboard.php';
			require_once WP_GAMIFY_BRIDGE_PLUGIN_DIR . 'admin/class-rom-library.php';
			require_once WP_GAMIFY_BRIDGE_PLUGIN_DIR . 'admin/class-rom-library-admin.php';
		}

		// Integration classes.
		require_once WP_GAMIFY_BRIDGE_PLUGIN_DIR . 'inc/integrations/gamipress.php';
		require_once WP_GAMIFY_BRIDGE_PLUGIN_DIR . 'inc/integrations/mycred.php';
	}

	/**
	 * Register components that need to hook early (before init).
	 */
	private function register_early_components() {
		// Register custom post types - must be instantiated early so its init hook fires.
		WP_Gamify_Bridge_Post_Types::instance();
	}

	/**
	 * Initialize WordPress hooks.
	 */
	private function init_hooks() {
		register_activation_hook( WP_GAMIFY_BRIDGE_PLUGIN_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( WP_GAMIFY_BRIDGE_PLUGIN_FILE, array( $this, 'deactivate' ) );

		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Initialize plugin components.
	 */
	public function init() {
		// Initialize database.
		WP_Gamify_Bridge_Database::instance();

		// Initialize REST API.
		WP_Gamify_Bridge_Endpoint::instance();
		WP_Gamify_Bridge_Room_Endpoint::instance();
		WP_Gamify_Bridge_Rom_Library_Endpoint::instance();

		// Initialize room manager.
		WP_Gamify_Bridge_Room_Manager::instance();

		// Initialize emulator manager.
		WP_Gamify_Bridge_Emulator_Manager::instance();

		// Register block(s).
		WP_Gamify_Bridge_Blocks::instance();

		// Initialize script enqueuer.
		WP_Gamify_Bridge_Script_Enqueuer::instance();

		// Shortcodes.
		WP_Gamify_Bridge_Emulator_Shortcode::instance();

		// Initialize admin page.
		if ( is_admin() ) {
			WP_Gamify_Bridge_Admin_Page::instance();
			WP_Gamify_Bridge_Dashboard::instance();
			WP_Gamify_Bridge_Rom_Library::instance();
			WP_Gamify_Bridge_ROM_Library_Admin::instance();
		}

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
		// Ensure database class is loaded.
		require_once WP_GAMIFY_BRIDGE_PLUGIN_DIR . 'inc/class-database.php';

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
