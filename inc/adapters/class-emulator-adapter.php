<?php
/**
 * Base Emulator Adapter Interface
 *
 * @package WP_Gamify_Bridge
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract class WP_Gamify_Bridge_Emulator_Adapter
 *
 * Base class for all emulator adapters.
 */
abstract class WP_Gamify_Bridge_Emulator_Adapter {

	/**
	 * Emulator name.
	 *
	 * @var string
	 */
	protected $name = '';

	/**
	 * Emulator display name.
	 *
	 * @var string
	 */
	protected $display_name = '';

	/**
	 * Emulator description.
	 *
	 * @var string
	 */
	protected $description = '';

	/**
	 * Supported systems.
	 *
	 * @var array
	 */
	protected $supported_systems = array();

	/**
	 * JavaScript detection code.
	 *
	 * @var string
	 */
	protected $js_detection = '';

	/**
	 * Configuration options.
	 *
	 * @var array
	 */
	protected $config = array();

	/**
	 * Get emulator name.
	 *
	 * @return string
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Get emulator display name.
	 *
	 * @return string
	 */
	public function get_display_name() {
		return $this->display_name;
	}

	/**
	 * Get emulator description.
	 *
	 * @return string
	 */
	public function get_description() {
		return $this->description;
	}

	/**
	 * Get supported systems.
	 *
	 * @return array
	 */
	public function get_supported_systems() {
		return $this->supported_systems;
	}

	/**
	 * Get JavaScript detection code.
	 *
	 * @return string
	 */
	public function get_js_detection() {
		return $this->js_detection;
	}

	/**
	 * Get configuration.
	 *
	 * @return array
	 */
	public function get_config() {
		return $this->config;
	}

	/**
	 * Get event mappings for this emulator.
	 *
	 * Maps emulator-specific events to standard WP Gamify Bridge events.
	 *
	 * @return array
	 */
	abstract public function get_event_mappings();

	/**
	 * Get JavaScript hook code.
	 *
	 * Returns JavaScript code to hook into emulator events.
	 *
	 * @return string
	 */
	abstract public function get_js_hooks();

	/**
	 * Validate event data from this emulator.
	 *
	 * @param array $event_data Event data to validate.
	 * @return bool|WP_Error True if valid, WP_Error otherwise.
	 */
	public function validate_event_data( $event_data ) {
		// Default validation - can be overridden by specific adapters.
		if ( ! isset( $event_data['event'] ) ) {
			return new WP_Error( 'missing_event', __( 'Event type is required', 'wp-gamify-bridge' ) );
		}

		return true;
	}

	/**
	 * Transform event data from emulator format to standard format.
	 *
	 * @param array $event_data Event data from emulator.
	 * @return array Transformed event data.
	 */
	public function transform_event_data( $event_data ) {
		// Default transformation - can be overridden by specific adapters.
		$mappings = $this->get_event_mappings();

		if ( isset( $event_data['event'] ) && isset( $mappings[ $event_data['event'] ] ) ) {
			$event_data['event'] = $mappings[ $event_data['event'] ];
		}

		return $event_data;
	}

	/**
	 * Get configuration form fields.
	 *
	 * Returns array of configuration fields for admin settings.
	 *
	 * @return array
	 */
	public function get_config_fields() {
		return array();
	}

	/**
	 * Get default configuration.
	 *
	 * @return array
	 */
	public function get_default_config() {
		return array(
			'enabled'        => true,
			'auto_detect'    => true,
			'event_prefix'   => '',
			'score_multiplier' => 1.0,
		);
	}

	/**
	 * Is emulator enabled?
	 *
	 * @return bool
	 */
	public function is_enabled() {
		$config = $this->get_config();
		return isset( $config['enabled'] ) && $config['enabled'];
	}

	/**
	 * Get score multiplier.
	 *
	 * @return float
	 */
	public function get_score_multiplier() {
		$config = $this->get_config();
		return isset( $config['score_multiplier'] ) ? (float) $config['score_multiplier'] : 1.0;
	}

	/**
	 * Apply score multiplier to event data.
	 *
	 * @param array $event_data Event data.
	 * @return array Modified event data.
	 */
	public function apply_score_multiplier( $event_data ) {
		$multiplier = $this->get_score_multiplier();

		if ( isset( $event_data['score'] ) && $multiplier !== 1.0 ) {
			$event_data['score'] = absint( $event_data['score'] * $multiplier );
		}

		return $event_data;
	}

	/**
	 * Get emulator-specific metadata.
	 *
	 * @return array
	 */
	public function get_metadata() {
		return array(
			'name'              => $this->get_name(),
			'display_name'      => $this->get_display_name(),
			'description'       => $this->get_description(),
			'supported_systems' => $this->get_supported_systems(),
			'enabled'           => $this->is_enabled(),
			'config'            => $this->get_config(),
		);
	}
}
