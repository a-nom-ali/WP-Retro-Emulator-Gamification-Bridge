<?php
/**
 * EmulatorJS Adapter
 *
 * @package WP_Gamify_Bridge
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Gamify_Bridge_EmulatorJS_Adapter
 *
 * Adapter for EmulatorJS (Web-based multi-system emulator).
 */
class WP_Gamify_Bridge_EmulatorJS_Adapter extends WP_Gamify_Bridge_Emulator_Adapter {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->name                = 'emulatorjs';
		$this->display_name        = 'EmulatorJS';
		$this->description         = __( 'Web-based multi-system emulator - Supports NES, SNES, GBA, N64, and more', 'wp-gamify-bridge' );
		$this->supported_systems   = array( 'NES', 'SNES', 'GBA', 'N64', 'Genesis', 'PlayStation', 'Atari', 'Multiple' );
		$this->supported_extensions = array( 'nes', 'smc', 'sfc', 'gba', 'z64', 'n64', 'v64', 'md', 'gen', 'bin', 'iso', 'a26', 'a52', 'a78', 'zip' );
		$this->supports_save_state = true;
		$this->control_mappings    = array(
			'up'      => __( 'D-Pad Up / Joystick Up', 'wp-gamify-bridge' ),
			'down'    => __( 'D-Pad Down / Joystick Down', 'wp-gamify-bridge' ),
			'left'    => __( 'D-Pad Left / Joystick Left', 'wp-gamify-bridge' ),
			'right'   => __( 'D-Pad Right / Joystick Right', 'wp-gamify-bridge' ),
			'a'       => __( 'A Button', 'wp-gamify-bridge' ),
			'b'       => __( 'B Button', 'wp-gamify-bridge' ),
			'x'       => __( 'X Button', 'wp-gamify-bridge' ),
			'y'       => __( 'Y Button', 'wp-gamify-bridge' ),
			'l'       => __( 'L Button', 'wp-gamify-bridge' ),
			'r'       => __( 'R Button', 'wp-gamify-bridge' ),
			'start'   => __( 'Start Button', 'wp-gamify-bridge' ),
			'select'  => __( 'Select Button', 'wp-gamify-bridge' ),
		);
		$this->setup_instructions  = __( 'EmulatorJS is a web-based multi-system emulator. Upload a ROM file for NES, SNES, GBA, N64, Genesis, PlayStation, or Atari. The system will be auto-detected based on the core. EmulatorJS supports save states and customizable controls. Default controls: Arrow keys for D-Pad, Z/X for B/A, A/S for Y/X, Q/W for L/R, Enter for Start, Shift for Select.', 'wp-gamify-bridge' );
		$this->js_detection        = 'typeof window.EJS_player !== \'undefined\'';

		$options      = get_option( 'wp_gamify_bridge_emulators', array() );
		$this->config = isset( $options['emulatorjs'] ) ? $options['emulatorjs'] : $this->get_default_config();
	}

	/**
	 * Get event mappings.
	 *
	 * @return array
	 */
	public function get_event_mappings() {
		return array(
			'stage_cleared'    => 'level_complete',
			'game_completed'   => 'game_over',
			'milestone'        => 'score_milestone',
			'game_loaded'      => 'game_start',
			'save_state'       => 'achievement_unlock',
			'player_defeated'  => 'death',
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
 * Hook into EmulatorJS.
 */
hookEmulatorJS: function() {
    const self = this;
    this.emulatorType = 'EmulatorJS';
    this.log('info', 'EmulatorJS emulator detected');

    // EmulatorJS uses EJS_player object
    if (window.EJS_player) {
        // Detect system type
        const systemType = this.detectEmulatorJSSystem();

        // Hook into game loaded event
        if (window.EJS_player.on) {
            window.EJS_player.on('load', function() {
                self.onGameLoad('EmulatorJS Game', {
                    emulator: 'EmulatorJS',
                    system: systemType
                });
            });
        }

        this.log('success', 'EmulatorJS hooks installed for ' + systemType);
    }

    // Listen for custom EmulatorJS events
    document.addEventListener('emulatorjs:stageCleared', function(e) {
        self.onLevelComplete(e.detail.stage, e.detail.score, e.detail.time);
    });

    document.addEventListener('emulatorjs:gameCompleted', function(e) {
        self.onGameOver(e.detail.score, e.detail.stage, e.detail.time);
    });

    document.addEventListener('emulatorjs:milestone', function(e) {
        self.onScoreMilestone(e.detail.milestone, e.detail.score);
    });

    document.addEventListener('emulatorjs:saveState', function(e) {
        self.onAchievementUnlock('save_state_' + e.detail.slot, 'Saved game state');
    });

    document.addEventListener('emulatorjs:playerDefeated', function(e) {
        self.onDeath(e.detail.lives, e.detail.stage, e.detail.cause);
    });
},

/**
 * Detect EmulatorJS system type.
 */
detectEmulatorJSSystem: function() {
    if (!window.EJS_player) return 'Unknown';

    // Check EJS_core or EJS_gameUrl for system indicators
    if (window.EJS_core) {
        const core = window.EJS_core.toLowerCase();
        if (core.includes('nes')) return 'NES';
        if (core.includes('snes')) return 'SNES';
        if (core.includes('gba')) return 'GBA';
        if (core.includes('n64')) return 'N64';
        if (core.includes('genesis') || core.includes('megadrive')) return 'Genesis';
        if (core.includes('psx') || core.includes('playstation')) return 'PlayStation';
        if (core.includes('atari')) return 'Atari';
    }

    return 'Unknown';
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
				'label'       => __( 'Enable EmulatorJS Support', 'wp-gamify-bridge' ),
				'type'        => 'checkbox',
				'description' => __( 'Enable hooks for EmulatorJS emulator', 'wp-gamify-bridge' ),
				'default'     => true,
			),
			array(
				'id'          => 'auto_detect',
				'label'       => __( 'Auto-detect EmulatorJS', 'wp-gamify-bridge' ),
				'type'        => 'checkbox',
				'description' => __( 'Automatically detect and hook into EmulatorJS when present', 'wp-gamify-bridge' ),
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
			array(
				'id'          => 'track_save_states',
				'label'       => __( 'Track Save States as Achievements', 'wp-gamify-bridge' ),
				'type'        => 'checkbox',
				'description' => __( 'Award achievements when players save their game', 'wp-gamify-bridge' ),
				'default'     => false,
			),
		);
	}

	/**
	 * Transform EmulatorJS event data.
	 *
	 * @param array $event_data Event data.
	 * @return array
	 */
	public function transform_event_data( $event_data ) {
		$event_data = parent::transform_event_data( $event_data );

		if ( ! isset( $event_data['data'] ) ) {
			$event_data['data'] = array();
		}

		$event_data['data']['emulator'] = 'EmulatorJS';

		// Detect system if not already set.
		if ( ! isset( $event_data['data']['system'] ) ) {
			$event_data['data']['system'] = 'Unknown';
		}

		$event_data = $this->apply_score_multiplier( $event_data );

		return $event_data;
	}
}
