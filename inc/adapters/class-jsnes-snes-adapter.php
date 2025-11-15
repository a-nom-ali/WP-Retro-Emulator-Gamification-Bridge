<?php
/**
 * jSNES Emulator Adapter
 *
 * @package WP_Gamify_Bridge
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Gamify_Bridge_JSNES_SNES_Adapter
 *
 * Adapter for jSNES (Super Nintendo Entertainment System) emulator.
 */
class WP_Gamify_Bridge_JSNES_SNES_Adapter extends WP_Gamify_Bridge_Emulator_Adapter {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->name                = 'jsnes_snes';
		$this->display_name        = 'jSNES';
		$this->description         = __( 'JavaScript SNES Emulator - Supports Super Nintendo Entertainment System games', 'wp-gamify-bridge' );
		$this->supported_systems   = array( 'SNES', 'Super Famicom' );
		$this->supported_extensions = array( 'smc', 'sfc', 'fig', 'swc', 'bs' );
		$this->supports_save_state = true;
		$this->control_mappings    = array(
			'up'     => __( 'D-Pad Up', 'wp-gamify-bridge' ),
			'down'   => __( 'D-Pad Down', 'wp-gamify-bridge' ),
			'left'   => __( 'D-Pad Left', 'wp-gamify-bridge' ),
			'right'  => __( 'D-Pad Right', 'wp-gamify-bridge' ),
			'a'      => __( 'A Button', 'wp-gamify-bridge' ),
			'b'      => __( 'B Button', 'wp-gamify-bridge' ),
			'x'      => __( 'X Button', 'wp-gamify-bridge' ),
			'y'      => __( 'Y Button', 'wp-gamify-bridge' ),
			'l'      => __( 'L Button', 'wp-gamify-bridge' ),
			'r'      => __( 'R Button', 'wp-gamify-bridge' ),
			'start'  => __( 'Start Button', 'wp-gamify-bridge' ),
			'select' => __( 'Select Button', 'wp-gamify-bridge' ),
		);
		$this->setup_instructions  = __( 'jSNES automatically detects and runs SNES ROM files. Upload a .smc or .sfc file and the emulator will handle the rest. Use arrow keys for D-Pad, Z/X for B/A, A/S for Y/X, Q/W for L/R, Enter for Start, and Shift for Select.', 'wp-gamify-bridge' );
		$this->js_detection        = 'typeof window.jSNES !== \'undefined\'';

		$options      = get_option( 'wp_gamify_bridge_emulators', array() );
		$this->config = isset( $options['jsnes_snes'] ) ? $options['jsnes_snes'] : $this->get_default_config();
	}

	/**
	 * Get event mappings.
	 *
	 * @return array
	 */
	public function get_event_mappings() {
		return array(
			'stage_complete'   => 'level_complete',
			'game_complete'    => 'game_over',
			'boss_defeated'    => 'achievement_unlock',
			'continue_used'    => 'death',
			'game_start'       => 'game_start',
			'high_score'       => 'score_milestone',
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
 * Hook into jSNES emulator.
 */
hookjSNES: function() {
    const self = this;
    this.emulatorType = 'jSNES';
    this.log('info', 'jSNES (SNES) emulator detected');

    // Listen for custom jSNES events
    document.addEventListener('jsnes:stageComplete', function(e) {
        self.onLevelComplete(e.detail.stage, e.detail.score, e.detail.time);
    });

    document.addEventListener('jsnes:gameComplete', function(e) {
        self.onGameOver(e.detail.score, e.detail.stage, e.detail.time);
    });

    document.addEventListener('jsnes:bossDefeated', function(e) {
        self.onAchievementUnlock('boss_' + e.detail.bossName, 'Defeated ' + e.detail.bossName);
    });

    document.addEventListener('jsnes:continueUsed', function(e) {
        self.onDeath(e.detail.continues, e.detail.stage, 'game_over');
    });

    document.addEventListener('jsnes:gameStarted', function(e) {
        self.onGameStart(e.detail.gameName, e.detail.difficulty);
    });

    this.log('success', 'jSNES hooks installed');
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
				'label'       => __( 'Enable jSNES Support', 'wp-gamify-bridge' ),
				'type'        => 'checkbox',
				'description' => __( 'Enable hooks for jSNES (SNES) emulator', 'wp-gamify-bridge' ),
				'default'     => true,
			),
			array(
				'id'          => 'auto_detect',
				'label'       => __( 'Auto-detect jSNES', 'wp-gamify-bridge' ),
				'type'        => 'checkbox',
				'description' => __( 'Automatically detect and hook into jSNES when present', 'wp-gamify-bridge' ),
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
	 * Transform SNES event data.
	 *
	 * @param array $event_data Event data.
	 * @return array
	 */
	public function transform_event_data( $event_data ) {
		$event_data = parent::transform_event_data( $event_data );

		if ( ! isset( $event_data['data'] ) ) {
			$event_data['data'] = array();
		}

		$event_data['data']['emulator'] = 'jSNES';
		$event_data['data']['system']   = 'SNES';

		$event_data = $this->apply_score_multiplier( $event_data );

		return $event_data;
	}
}
