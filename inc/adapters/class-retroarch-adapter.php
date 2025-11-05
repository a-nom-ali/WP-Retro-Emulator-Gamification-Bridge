<?php
/**
 * RetroArch Emulator Adapter
 *
 * @package WP_Gamify_Bridge
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Gamify_Bridge_RetroArch_Adapter
 *
 * Adapter for RetroArch (Multi-system emulator).
 */
class WP_Gamify_Bridge_RetroArch_Adapter extends WP_Gamify_Bridge_Emulator_Adapter {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->name             = 'retroarch';
		$this->display_name     = 'RetroArch';
		$this->description      = __( 'Multi-system emulator frontend - Supports multiple retro gaming systems via cores', 'wp-gamify-bridge' );
		$this->supported_systems = array( 'NES', 'SNES', 'Genesis', 'GBA', 'PlayStation', 'N64', 'Arcade', 'Multiple' );
		$this->js_detection     = 'typeof window.Module !== \'undefined\' && window.Module.canvas';

		$options      = get_option( 'wp_gamify_bridge_emulators', array() );
		$this->config = isset( $options['retroarch'] ) ? $options['retroarch'] : $this->get_default_config();
	}

	/**
	 * Get event mappings.
	 *
	 * @return array
	 */
	public function get_event_mappings() {
		return array(
			'achievement_earned' => 'achievement_unlock',
			'level_beaten'       => 'level_complete',
			'game_finished'      => 'game_over',
			'high_score'         => 'score_milestone',
			'game_loaded'        => 'game_start',
			'player_death'       => 'death',
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
 * Hook into RetroArch emulator.
 */
hookRetroArch: function() {
    const self = this;
    this.emulatorType = 'RetroArch';
    this.log('info', 'RetroArch emulator detected');

    // RetroArch uses Emscripten Module
    if (window.Module) {
        const originalRun = window.Module.run;

        if (originalRun) {
            window.Module.run = function() {
                self.onGameLoad('RetroArch Game', {emulator: 'RetroArch'});
                return originalRun.apply(this, arguments);
            };
        }

        this.log('success', 'RetroArch hooks installed');
    }

    // Listen for RetroArch achievement events (if enabled)
    document.addEventListener('retroarch:achievement', function(e) {
        self.onAchievementUnlock(
            e.detail.achievementId,
            e.detail.achievementTitle || 'Achievement Unlocked'
        );
    });

    document.addEventListener('retroarch:levelComplete', function(e) {
        self.onLevelComplete(e.detail.level, e.detail.score, e.detail.time);
    });

    document.addEventListener('retroarch:gameOver', function(e) {
        self.onGameOver(e.detail.score, e.detail.level, e.detail.time);
    });

    document.addEventListener('retroarch:death', function(e) {
        self.onDeath(e.detail.lives, e.detail.level, e.detail.cause);
    });

    document.addEventListener('retroarch:gameStart', function(e) {
        self.onGameStart(e.detail.game, e.detail.core);
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
				'label'       => __( 'Enable RetroArch Support', 'wp-gamify-bridge' ),
				'type'        => 'checkbox',
				'description' => __( 'Enable hooks for RetroArch emulator', 'wp-gamify-bridge' ),
				'default'     => true,
			),
			array(
				'id'          => 'auto_detect',
				'label'       => __( 'Auto-detect RetroArch', 'wp-gamify-bridge' ),
				'type'        => 'checkbox',
				'description' => __( 'Automatically detect and hook into RetroArch when present', 'wp-gamify-bridge' ),
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
				'id'          => 'track_achievements',
				'label'       => __( 'Track RetroArch Achievements', 'wp-gamify-bridge' ),
				'type'        => 'checkbox',
				'description' => __( 'Track RetroAchievements and sync to WordPress', 'wp-gamify-bridge' ),
				'default'     => true,
			),
		);
	}

	/**
	 * Transform RetroArch event data.
	 *
	 * @param array $event_data Event data.
	 * @return array
	 */
	public function transform_event_data( $event_data ) {
		$event_data = parent::transform_event_data( $event_data );

		if ( ! isset( $event_data['data'] ) ) {
			$event_data['data'] = array();
		}

		$event_data['data']['emulator'] = 'RetroArch';

		// Try to detect the core/system being used.
		if ( isset( $event_data['data']['core'] ) ) {
			$event_data['data']['system'] = $this->get_system_from_core( $event_data['data']['core'] );
		}

		$event_data = $this->apply_score_multiplier( $event_data );

		return $event_data;
	}

	/**
	 * Get system name from RetroArch core.
	 *
	 * @param string $core Core name.
	 * @return string System name.
	 */
	private function get_system_from_core( $core ) {
		$core_map = array(
			'fceumm'     => 'NES',
			'snes9x'     => 'SNES',
			'genesis_plus_gx' => 'Genesis',
			'mgba'       => 'GBA',
			'pcsx_rearmed' => 'PlayStation',
			'mupen64plus' => 'N64',
			'fbneo'      => 'Arcade',
		);

		foreach ( $core_map as $core_pattern => $system ) {
			if ( stripos( $core, $core_pattern ) !== false ) {
				return $system;
			}
		}

		return 'Unknown';
	}
}
