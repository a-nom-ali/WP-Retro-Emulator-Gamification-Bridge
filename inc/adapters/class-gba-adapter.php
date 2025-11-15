<?php
/**
 * GBA.js Emulator Adapter
 *
 * @package WP_Gamify_Bridge
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Gamify_Bridge_GBA_Adapter
 *
 * Adapter for GBA.js (Game Boy Advance) emulator.
 */
class WP_Gamify_Bridge_GBA_Adapter extends WP_Gamify_Bridge_Emulator_Adapter {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->name                = 'gba';
		$this->display_name        = 'GBA.js';
		$this->description         = __( 'JavaScript Game Boy Advance Emulator - Supports GBA games', 'wp-gamify-bridge' );
		$this->supported_systems   = array( 'GBA', 'Game Boy Advance' );
		$this->supported_extensions = array( 'gba', 'agb', 'bin' );
		$this->supports_save_state = true;
		$this->control_mappings    = array(
			'up'     => __( 'D-Pad Up', 'wp-gamify-bridge' ),
			'down'   => __( 'D-Pad Down', 'wp-gamify-bridge' ),
			'left'   => __( 'D-Pad Left', 'wp-gamify-bridge' ),
			'right'  => __( 'D-Pad Right', 'wp-gamify-bridge' ),
			'a'      => __( 'A Button', 'wp-gamify-bridge' ),
			'b'      => __( 'B Button', 'wp-gamify-bridge' ),
			'l'      => __( 'L Button', 'wp-gamify-bridge' ),
			'r'      => __( 'R Button', 'wp-gamify-bridge' ),
			'start'  => __( 'Start Button', 'wp-gamify-bridge' ),
			'select' => __( 'Select Button', 'wp-gamify-bridge' ),
		);
		$this->setup_instructions  = __( 'GBA.js automatically detects and runs Game Boy Advance ROM files. Upload a .gba file and the emulator will handle the rest. Use arrow keys for D-Pad, Z for B, X for A, A for L, S for R, Enter for Start, and Shift for Select.', 'wp-gamify-bridge' );
		$this->js_detection        = 'typeof window.GBA !== \'undefined\'';

		$options      = get_option( 'wp_gamify_bridge_emulators', array() );
		$this->config = isset( $options['gba'] ) ? $options['gba'] : $this->get_default_config();
	}

	/**
	 * Get event mappings.
	 *
	 * @return array
	 */
	public function get_event_mappings() {
		return array(
			'level_complete'   => 'level_complete',
			'game_complete'    => 'game_over',
			'checkpoint'       => 'score_milestone',
			'player_ko'        => 'death',
			'rom_loaded'       => 'game_start',
			'badge_earned'     => 'achievement_unlock',
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
 * Hook into GBA.js emulator.
 */
hookGBA: function() {
    const self = this;
    this.emulatorType = 'GBA';
    this.log('info', 'GBA.js emulator detected');

    if (window.GBA && window.GBA.prototype) {
        const originalLoadROM = window.GBA.prototype.loadROM;

        // Hook into ROM loading
        window.GBA.prototype.loadROM = function(rom) {
            self.onGameLoad('GBA Game', {emulator: 'GBA', rom: rom.name || 'Unknown'});
            return originalLoadROM.apply(this, arguments);
        };

        this.log('success', 'GBA.js hooks installed');
    }

    // Listen for custom GBA events
    document.addEventListener('gba:levelComplete', function(e) {
        self.onLevelComplete(e.detail.level, e.detail.score, e.detail.time);
    });

    document.addEventListener('gba:gameComplete', function(e) {
        self.onGameOver(e.detail.score, e.detail.level, e.detail.time);
    });

    document.addEventListener('gba:checkpoint', function(e) {
        self.onScoreMilestone(e.detail.checkpoint, e.detail.checkpoint);
    });

    document.addEventListener('gba:playerKO', function(e) {
        self.onDeath(e.detail.lives, e.detail.level, e.detail.cause);
    });

    document.addEventListener('gba:badgeEarned', function(e) {
        self.onAchievementUnlock(e.detail.badgeId, e.detail.badgeName);
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
				'label'       => __( 'Enable GBA.js Support', 'wp-gamify-bridge' ),
				'type'        => 'checkbox',
				'description' => __( 'Enable hooks for GBA.js emulator', 'wp-gamify-bridge' ),
				'default'     => true,
			),
			array(
				'id'          => 'auto_detect',
				'label'       => __( 'Auto-detect GBA.js', 'wp-gamify-bridge' ),
				'type'        => 'checkbox',
				'description' => __( 'Automatically detect and hook into GBA.js when present', 'wp-gamify-bridge' ),
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
	 * Transform GBA event data.
	 *
	 * @param array $event_data Event data.
	 * @return array
	 */
	public function transform_event_data( $event_data ) {
		$event_data = parent::transform_event_data( $event_data );

		if ( ! isset( $event_data['data'] ) ) {
			$event_data['data'] = array();
		}

		$event_data['data']['emulator'] = 'GBA';
		$event_data['data']['system']   = 'Game Boy Advance';

		$event_data = $this->apply_score_multiplier( $event_data );

		return $event_data;
	}
}
