<?php
/**
 * MAME.js Emulator Adapter
 *
 * @package WP_Gamify_Bridge
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Gamify_Bridge_MAME_Adapter
 *
 * Adapter for MAME.js (Multiple Arcade Machine Emulator) emulator.
 */
class WP_Gamify_Bridge_MAME_Adapter extends WP_Gamify_Bridge_Emulator_Adapter {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->name                = 'mame';
		$this->display_name        = 'MAME.js';
		$this->description         = __( 'JavaScript Arcade Emulator - Supports classic arcade games', 'wp-gamify-bridge' );
		$this->supported_systems   = array( 'Arcade' );
		$this->supported_extensions = array( 'zip', '7z' );
		$this->supports_save_state = false;
		$this->control_mappings    = array(
			'up'      => __( 'Joystick Up', 'wp-gamify-bridge' ),
			'down'    => __( 'Joystick Down', 'wp-gamify-bridge' ),
			'left'    => __( 'Joystick Left', 'wp-gamify-bridge' ),
			'right'   => __( 'Joystick Right', 'wp-gamify-bridge' ),
			'button1' => __( 'Button 1', 'wp-gamify-bridge' ),
			'button2' => __( 'Button 2', 'wp-gamify-bridge' ),
			'button3' => __( 'Button 3', 'wp-gamify-bridge' ),
			'coin'    => __( 'Insert Coin (5 key)', 'wp-gamify-bridge' ),
			'start'   => __( 'Start (1 key)', 'wp-gamify-bridge' ),
		);
		$this->setup_instructions  = __( 'MAME.js runs arcade ROM files (typically .zip format). Upload a compatible MAME ROM and the emulator will handle it. Use arrow keys for joystick, Z/X/C for buttons, 5 for coin, and 1 for start. Note: Arcade scores are typically very high, so a default 0.1x multiplier is applied.', 'wp-gamify-bridge' );
		$this->js_detection        = 'typeof window.MAME !== \'undefined\' || typeof window.JSMAME !== \'undefined\'';

		$options      = get_option( 'wp_gamify_bridge_emulators', array() );
		$this->config = isset( $options['mame'] ) ? $options['mame'] : $this->get_default_config();
	}

	/**
	 * Get event mappings.
	 *
	 * @return array
	 */
	public function get_event_mappings() {
		return array(
			'round_complete'   => 'level_complete',
			'game_over'        => 'game_over',
			'high_score'       => 'score_milestone',
			'coin_inserted'    => 'game_start',
			'extra_life'       => 'achievement_unlock',
			'continue'         => 'death',
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
 * Hook into MAME.js emulator.
 */
hookMAME: function() {
    const self = this;
    this.emulatorType = 'MAME';
    this.log('info', 'MAME.js emulator detected');

    // Listen for custom MAME events
    document.addEventListener('mame:roundComplete', function(e) {
        self.onLevelComplete(e.detail.round, e.detail.score, e.detail.time);
    });

    document.addEventListener('mame:gameOver', function(e) {
        self.onGameOver(e.detail.score, e.detail.round, e.detail.time);
    });

    document.addEventListener('mame:highScore', function(e) {
        self.onScoreMilestone(e.detail.score, e.detail.score);
    });

    document.addEventListener('mame:coinInserted', function(e) {
        self.onGameStart(e.detail.gameName, e.detail.difficulty);
    });

    document.addEventListener('mame:extraLife', function(e) {
        self.onAchievementUnlock('extra_life_' + e.detail.score, 'Earned extra life at ' + e.detail.score + ' points');
    });

    document.addEventListener('mame:continue', function(e) {
        self.onDeath(e.detail.credits, e.detail.round, 'continue');
    });

    this.log('success', 'MAME.js hooks installed');
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
				'label'       => __( 'Enable MAME.js Support', 'wp-gamify-bridge' ),
				'type'        => 'checkbox',
				'description' => __( 'Enable hooks for MAME.js emulator', 'wp-gamify-bridge' ),
				'default'     => true,
			),
			array(
				'id'          => 'auto_detect',
				'label'       => __( 'Auto-detect MAME.js', 'wp-gamify-bridge' ),
				'type'        => 'checkbox',
				'description' => __( 'Automatically detect and hook into MAME.js when present', 'wp-gamify-bridge' ),
				'default'     => true,
			),
			array(
				'id'          => 'score_multiplier',
				'label'       => __( 'Score Multiplier', 'wp-gamify-bridge' ),
				'type'        => 'number',
				'description' => __( 'Multiply all scores by this value (default: 0.1 - arcade scores are usually high)', 'wp-gamify-bridge' ),
				'default'     => 0.1,
				'step'        => 0.1,
				'min'         => 0.01,
				'max'         => 10.0,
			),
		);
	}

	/**
	 * Transform MAME event data.
	 *
	 * @param array $event_data Event data.
	 * @return array
	 */
	public function transform_event_data( $event_data ) {
		$event_data = parent::transform_event_data( $event_data );

		if ( ! isset( $event_data['data'] ) ) {
			$event_data['data'] = array();
		}

		$event_data['data']['emulator'] = 'MAME';
		$event_data['data']['system']   = 'Arcade';

		// Apply score multiplier (arcade scores tend to be very high).
		$event_data = $this->apply_score_multiplier( $event_data );

		return $event_data;
	}
}
