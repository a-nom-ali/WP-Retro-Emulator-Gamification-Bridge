<?php
/**
 * JSNES Emulator Adapter
 *
 * @package WP_Gamify_Bridge
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Gamify_Bridge_JSNES_Adapter
 *
 * Adapter for JSNES (Nintendo Entertainment System) emulator.
 */
class WP_Gamify_Bridge_JSNES_Adapter extends WP_Gamify_Bridge_Emulator_Adapter {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->name             = 'jsnes';
		$this->display_name     = 'JSNES';
		$this->description      = __( 'JavaScript NES Emulator - Supports Nintendo Entertainment System games', 'wp-gamify-bridge' );
		$this->supported_systems = array( 'NES', 'Famicom' );
		$this->js_detection     = 'typeof window.JSNES !== \'undefined\'';

		// Load configuration from options.
		$options      = get_option( 'wp_gamify_bridge_emulators', array() );
		$this->config = isset( $options['jsnes'] ) ? $options['jsnes'] : $this->get_default_config();
	}

	/**
	 * Get event mappings.
	 *
	 * @return array
	 */
	public function get_event_mappings() {
		return array(
			'level_cleared'    => 'level_complete',
			'game_completed'   => 'game_over',
			'high_score'       => 'score_milestone',
			'player_died'      => 'death',
			'game_loaded'      => 'game_start',
			'achievement'      => 'achievement_unlock',
		);
	}

	/**
	 * Get JavaScript hook code.
	 *
	 * @return string
	 */
	public function get_js_hooks() {
		return <<<'JS'
/**
 * Hook into JSNES emulator.
 */
hookJSNES: function() {
    const self = this;
    this.emulatorType = 'JSNES';
    this.log('info', 'JSNES emulator detected');

    // Store original JSNES instance if available
    if (window.JSNES && window.JSNES.prototype) {
        const originalReset = window.JSNES.prototype.reset;

        // Hook into reset (game start)
        window.JSNES.prototype.reset = function() {
            self.onGameLoad('NES Game', {emulator: 'JSNES'});
            return originalReset.apply(this, arguments);
        };

        this.log('success', 'JSNES hooks installed');
    }

    // Listen for custom JSNES events
    document.addEventListener('jsnes:levelComplete', function(e) {
        self.onLevelComplete(e.detail.level, e.detail.score, e.detail.time);
    });

    document.addEventListener('jsnes:gameOver', function(e) {
        self.onGameOver(e.detail.score, e.detail.level, e.detail.time);
    });

    document.addEventListener('jsnes:death', function(e) {
        self.onDeath(e.detail.lives, e.detail.level, e.detail.cause);
    });

    document.addEventListener('jsnes:scoreUpdate', function(e) {
        if (e.detail.score % 10000 === 0) {
            self.onScoreMilestone(e.detail.score, e.detail.score);
        }
    });
},
JS;
	}

	/**
	 * Get configuration fields.
	 *
	 * @return array
	 */
	public function get_config_fields() {
		return array(
			array(
				'id'          => 'enabled',
				'label'       => __( 'Enable JSNES Support', 'wp-gamify-bridge' ),
				'type'        => 'checkbox',
				'description' => __( 'Enable hooks for JSNES emulator', 'wp-gamify-bridge' ),
				'default'     => true,
			),
			array(
				'id'          => 'auto_detect',
				'label'       => __( 'Auto-detect JSNES', 'wp-gamify-bridge' ),
				'type'        => 'checkbox',
				'description' => __( 'Automatically detect and hook into JSNES when present', 'wp-gamify-bridge' ),
				'default'     => true,
			),
			array(
				'id'          => 'score_multiplier',
				'label'       => __( 'Score Multiplier', 'wp-gamify-bridge' ),
				'type'        => 'number',
				'description' => __( 'Multiply all scores by this value (default: 1.0)', 'wp-gamify-bridge' ),
				'default'     => 1.0,
				'step'        => 0.1,
				'min'         => 0.1,
				'max'         => 10.0,
			),
		);
	}

	/**
	 * Validate NES-specific event data.
	 *
	 * @param array $event_data Event data.
	 * @return bool|WP_Error
	 */
	public function validate_event_data( $event_data ) {
		$validation = parent::validate_event_data( $event_data );

		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// NES-specific validation.
		if ( isset( $event_data['data']['system'] ) && ! in_array( $event_data['data']['system'], array( 'NES', 'Famicom' ), true ) ) {
			return new WP_Error( 'invalid_system', __( 'System must be NES or Famicom', 'wp-gamify-bridge' ) );
		}

		return true;
	}

	/**
	 * Transform NES event data.
	 *
	 * @param array $event_data Event data.
	 * @return array
	 */
	public function transform_event_data( $event_data ) {
		$event_data = parent::transform_event_data( $event_data );

		// Add NES-specific metadata.
		if ( ! isset( $event_data['data'] ) ) {
			$event_data['data'] = array();
		}

		$event_data['data']['emulator'] = 'JSNES';
		$event_data['data']['system']   = 'NES';

		// Apply score multiplier.
		$event_data = $this->apply_score_multiplier( $event_data );

		return $event_data;
	}
}
