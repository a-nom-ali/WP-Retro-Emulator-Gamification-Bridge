<?php
/**
 * Emulator Manager
 *
 * @package WP_Gamify_Bridge
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Gamify_Bridge_Emulator_Manager
 *
 * Manages emulator adapters and handles event transformations.
 */
class WP_Gamify_Bridge_Emulator_Manager {

	/**
	 * Single instance of the class.
	 *
	 * @var WP_Gamify_Bridge_Emulator_Manager
	 */
	private static $instance = null;

	/**
	 * Registered adapters.
	 *
	 * @var array
	 */
	private $adapters = array();

	/**
	 * Get the singleton instance.
	 *
	 * @return WP_Gamify_Bridge_Emulator_Manager
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
		$this->load_adapters();
		$this->register_adapters();

		add_filter( 'wp_gamify_bridge_transform_event', array( $this, 'transform_event' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_emulator_scripts' ) );
	}

	/**
	 * Load adapter classes.
	 */
	private function load_adapters() {
		require_once WP_GAMIFY_BRIDGE_PLUGIN_DIR . 'inc/adapters/class-emulator-adapter.php';
		require_once WP_GAMIFY_BRIDGE_PLUGIN_DIR . 'inc/adapters/class-jsnes-adapter.php';
		require_once WP_GAMIFY_BRIDGE_PLUGIN_DIR . 'inc/adapters/class-jsnes-snes-adapter.php';
		require_once WP_GAMIFY_BRIDGE_PLUGIN_DIR . 'inc/adapters/class-gba-adapter.php';
		require_once WP_GAMIFY_BRIDGE_PLUGIN_DIR . 'inc/adapters/class-mame-adapter.php';
		require_once WP_GAMIFY_BRIDGE_PLUGIN_DIR . 'inc/adapters/class-retroarch-adapter.php';
		require_once WP_GAMIFY_BRIDGE_PLUGIN_DIR . 'inc/adapters/class-emulatorjs-adapter.php';
	}

	/**
	 * Register emulator adapters.
	 */
	private function register_adapters() {
		$this->register_adapter( new WP_Gamify_Bridge_JSNES_Adapter() );
		$this->register_adapter( new WP_Gamify_Bridge_JSNES_SNES_Adapter() );
		$this->register_adapter( new WP_Gamify_Bridge_GBA_Adapter() );
		$this->register_adapter( new WP_Gamify_Bridge_MAME_Adapter() );
		$this->register_adapter( new WP_Gamify_Bridge_RetroArch_Adapter() );
		$this->register_adapter( new WP_Gamify_Bridge_EmulatorJS_Adapter() );

		// Allow custom adapters to be registered.
		do_action( 'wp_gamify_bridge_register_adapters', $this );
	}

	/**
	 * Register an emulator adapter.
	 *
	 * @param WP_Gamify_Bridge_Emulator_Adapter $adapter Adapter instance.
	 */
	public function register_adapter( $adapter ) {
		if ( ! $adapter instanceof WP_Gamify_Bridge_Emulator_Adapter ) {
			return;
		}

		$this->adapters[ $adapter->get_name() ] = $adapter;
	}

	/**
	 * Get registered adapter by name.
	 *
	 * @param string $name Adapter name.
	 * @return WP_Gamify_Bridge_Emulator_Adapter|null
	 */
	public function get_adapter( $name ) {
		return isset( $this->adapters[ $name ] ) ? $this->adapters[ $name ] : null;
	}

	/**
	 * Get all registered adapters.
	 *
	 * @return array
	 */
	public function get_adapters() {
		return $this->adapters;
	}

	/**
	 * Get enabled adapters.
	 *
	 * @return array
	 */
	public function get_enabled_adapters() {
		return array_filter( $this->adapters, function( $adapter ) {
			return $adapter->is_enabled();
		});
	}

	/**
	 * Transform event data using appropriate adapter.
	 *
	 * @param array  $event_data Event data.
	 * @param string $emulator   Emulator name (optional).
	 * @return array Transformed event data.
	 */
	public function transform_event( $event_data, $emulator = null ) {
		// Detect emulator from event data if not provided.
		if ( ! $emulator && isset( $event_data['data']['emulator'] ) ) {
			$emulator = strtolower( $event_data['data']['emulator'] );
		}

		// Get adapter for this emulator.
		$adapter = $this->get_adapter( $emulator );

		if ( ! $adapter || ! $adapter->is_enabled() ) {
			return $event_data;
		}

		// Validate and transform event data.
		$validation = $adapter->validate_event_data( $event_data );

		if ( is_wp_error( $validation ) ) {
			return $event_data;
		}

		return $adapter->transform_event_data( $event_data );
	}

	/**
	 * Enqueue emulator detection and hook scripts.
	 */
	public function enqueue_emulator_scripts() {
		// Get enabled adapters.
		$enabled_adapters = $this->get_enabled_adapters();

		if ( empty( $enabled_adapters ) ) {
			return;
		}

		// Build JavaScript configuration.
		$config = array(
			'adapters' => array(),
		);

		foreach ( $enabled_adapters as $adapter ) {
			$config['adapters'][ $adapter->get_name() ] = array(
				'name'              => $adapter->get_name(),
				'display_name'      => $adapter->get_display_name(),
				'supported_systems' => $adapter->get_supported_systems(),
				'detection'         => $adapter->get_js_detection(),
			);
		}

		// Localize adapter configuration.
		wp_localize_script( 'wp-gamify-bridge-emulator-hooks', 'wpGamifyAdapters', $config );
	}

	/**
	 * Get emulator statistics.
	 *
	 * @return array
	 */
	public function get_statistics() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'gamify_events';

		$stats = array(
			'total_events'        => 0,
			'events_by_emulator'  => array(),
			'events_by_system'    => array(),
		);

		// Total events.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$stats['total_events'] = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );

		// Events by emulator.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$emulator_counts = $wpdb->get_results(
			"SELECT
				JSON_EXTRACT(event_data, '$.emulator') as emulator,
				COUNT(*) as count
			FROM $table_name
			WHERE JSON_EXTRACT(event_data, '$.emulator') IS NOT NULL
			GROUP BY emulator
			ORDER BY count DESC"
		);

		foreach ( $emulator_counts as $row ) {
			$emulator = trim( $row->emulator, '"' );
			$stats['events_by_emulator'][ $emulator ] = absint( $row->count );
		}

		// Events by system.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$system_counts = $wpdb->get_results(
			"SELECT
				JSON_EXTRACT(event_data, '$.system') as system,
				COUNT(*) as count
			FROM $table_name
			WHERE JSON_EXTRACT(event_data, '$.system') IS NOT NULL
			GROUP BY system
			ORDER BY count DESC"
		);

		foreach ( $system_counts as $row ) {
			$system = trim( $row->system, '"' );
			$stats['events_by_system'][ $system ] = absint( $row->count );
		}

		return $stats;
	}

	/**
	 * Get adapter metadata for all registered adapters.
	 *
	 * @return array
	 */
	public function get_adapters_metadata() {
		$metadata = array();

		foreach ( $this->adapters as $name => $adapter ) {
			$metadata[ $name ] = $adapter->get_metadata();
		}

		return $metadata;
	}
}
