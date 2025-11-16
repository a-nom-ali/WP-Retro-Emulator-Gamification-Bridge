<?php
/**
 * Emulator shortcode rendering.
 *
 * @package WP_Gamify_Bridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Gamify_Bridge_Emulator_Shortcode
 */
class WP_Gamify_Bridge_Emulator_Shortcode {

	/**
	 * Singleton instance.
	 *
	 * @var WP_Gamify_Bridge_Emulator_Shortcode
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return WP_Gamify_Bridge_Emulator_Shortcode
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
		add_shortcode( 'retro_emulator', array( $this, 'render_shortcode' ) );
		add_shortcode( 'nes', array( $this, 'render_legacy_shortcode' ) );
		add_shortcode( 'rom_player', array( $this, 'render_rom_player_shortcode' ) );
	}

	/**
	 * Render the emulator shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'             => '',
				'rom'            => '',
				'touch_toggle'   => 'true',
				'auto_touch'     => 'auto',
				'class'          => '',
				'legacy_notice'  => false,
			),
			$atts,
			'retro_emulator'
		);

		$roms = WP_Gamify_Bridge_Rom_Library_Service::get_list(
			array(
				'posts_per_page' => 200,
			)
		);

		if ( empty( $roms ) ) {
			return '<div class="wp-gamify-emulator__empty">' . esc_html__( 'No ROMs available. Upload ROMs via the ROM Library admin screen.', 'wp-gamify-bridge' ) . '</div>';
		}

		$active_rom    = $this->get_active_rom( $roms, $atts );
		$wrapper_id    = 'wp-gamify-emulator-' . wp_rand( 1000, 999999 );
		$show_toggle   = wp_validate_boolean( $atts['touch_toggle'] );
		$auto_touch    = in_array( strtolower( $atts['auto_touch'] ), array( 'mobile', 'always', 'never' ), true ) ? strtolower( $atts['auto_touch'] ) : 'auto';
		$legacy_notice = wp_validate_boolean( $atts['legacy_notice'] );
		$extra_class   = sanitize_html_class( $atts['class'] );

		ob_start();
		$roms_list = $roms;
		include WP_GAMIFY_BRIDGE_PLUGIN_DIR . 'templates/shortcodes/retro-emulator.php';
		return ob_get_clean();
	}

	/**
	 * Render legacy [nes] shortcode using new UI with notice.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_legacy_shortcode( $atts ) {
		$atts['legacy_notice'] = true;
		return $this->render_shortcode( $atts );
	}

	/**
	 * Render the ROM Player shortcode (single ROM without dropdown).
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_rom_player_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'rom'          => '',
				'touch_toggle' => 'true',
				'show_meta'    => 'true',
				'class'        => '',
			),
			$atts,
			'rom_player'
		);

		$rom_id      = sanitize_text_field( $atts['rom'] );
		$show_toggle = wp_validate_boolean( $atts['touch_toggle'] );
		$show_meta   = wp_validate_boolean( $atts['show_meta'] );
		$extra_class = sanitize_html_class( $atts['class'] );

		if ( empty( $rom_id ) ) {
			return '<div class="wp-gamify-rom-player__empty">' . esc_html__( 'No ROM specified. Please provide a ROM slug or ID.', 'wp-gamify-bridge' ) . '</div>';
		}

		// Get the ROM by ID or slug.
		$rom = null;
		if ( is_numeric( $rom_id ) ) {
			$rom_post = get_post( (int) $rom_id );
			if ( $rom_post && 'retro_rom' === $rom_post->post_type ) {
				$rom = WP_Gamify_Bridge_Rom_Library_Service::get_rom_metadata( $rom_post->ID );
			}
		} else {
			// Try to find by slug.
			$roms = WP_Gamify_Bridge_Rom_Library_Service::get_list( array( 'name' => $rom_id ) );
			if ( ! empty( $roms ) ) {
				$rom = $roms[0];
			}
		}

		if ( ! $rom ) {
			return '<div class="wp-gamify-rom-player__empty">' . esc_html__( 'ROM not found.', 'wp-gamify-bridge' ) . '</div>';
		}

		$wrapper_id  = 'wp-gamify-rom-player-' . wp_rand( 1000, 999999 );
		$auto_touch  = 'auto';
		$active_rom  = $rom;

		ob_start();
		include WP_GAMIFY_BRIDGE_PLUGIN_DIR . 'templates/shortcodes/rom-player.php';
		return ob_get_clean();
	}

	/**
	 * Resolve default ROM from shortcode attributes.
	 *
	 * @param array $roms List of ROM arrays.
	 * @param array $atts Shortcode attributes.
	 * @return array
	 */
	private function get_active_rom( $roms, $atts ) {
		$target = '';

		if ( ! empty( $atts['id'] ) ) {
			$target = (int) $atts['id'];
		} elseif ( ! empty( $atts['rom'] ) ) {
			$target = sanitize_title( $atts['rom'] );
		}

		foreach ( $roms as $rom ) {
			if ( $target && ( (int) $rom['id'] === (int) $target || sanitize_title( $rom['slug'] ) === $target ) ) {
				return $rom;
			}
		}

		return $roms[0];
	}
}
