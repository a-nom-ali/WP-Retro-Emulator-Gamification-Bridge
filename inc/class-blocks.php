<?php
/**
 * Gutenberg block registrations.
 *
 * @package WP_Gamify_Bridge
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WP_Gamify_Bridge_Blocks {

    private static $instance = null;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        add_action( 'init', array( $this, 'register_blocks' ) );
        add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );
    }

    public function register_blocks() {
        if ( ! function_exists( 'register_block_type' ) ) {
            return;
        }

        register_block_type(
            'wp-gamify/retro-emulator',
            array(
                'render_callback' => array( $this, 'render_emulator_block' ),
                'attributes'      => array(
                    'rom'        => array( 'type' => 'string', 'default' => '' ),
                    'className'  => array( 'type' => 'string' ),
                    'touchToggle'=> array( 'type' => 'boolean', 'default' => true ),
                ),
            )
        );

        register_block_type(
            'wp-gamify/rom-player',
            array(
                'render_callback' => array( $this, 'render_rom_player_block' ),
                'attributes'      => array(
                    'romId'      => array( 'type' => 'string', 'default' => '' ),
                    'className'  => array( 'type' => 'string' ),
                    'touchToggle'=> array( 'type' => 'boolean', 'default' => true ),
                    'showMeta'   => array( 'type' => 'boolean', 'default' => true ),
                ),
            )
        );
    }

    public function enqueue_block_editor_assets() {
        wp_enqueue_script(
            'wp-gamify-retro-emulator-block',
            WP_GAMIFY_BRIDGE_PLUGIN_URL . 'js/retro-emulator-block.js',
            array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-editor', 'wp-i18n' ),
            WP_GAMIFY_BRIDGE_VERSION,
            true
        );

        wp_enqueue_script(
            'wp-gamify-rom-player-block',
            WP_GAMIFY_BRIDGE_PLUGIN_URL . 'js/rom-player-block.js',
            array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-editor', 'wp-i18n' ),
            WP_GAMIFY_BRIDGE_VERSION,
            true
        );

        $roms = class_exists( 'WP_Gamify_Bridge_Rom_Library_Service' ) ? WP_Gamify_Bridge_Rom_Library_Service::get_list( array( 'posts_per_page' => 200 ) ) : array();

        wp_localize_script(
            'wp-gamify-retro-emulator-block',
            'wpGamifyEmulatorBlock',
            array(
                'roms' => $roms,
            )
        );
    }

    public function render_emulator_block( $attributes ) {
        $atts = array();
        if ( ! empty( $attributes['rom'] ) ) {
            $atts['rom'] = $attributes['rom'];
        }
        if ( isset( $attributes['touchToggle'] ) ) {
            $atts['touch_toggle'] = $attributes['touchToggle'] ? 'true' : 'false';
        }
        if ( ! empty( $attributes['className'] ) ) {
            $atts['class'] = $attributes['className'];
        }

        return WP_Gamify_Bridge_Emulator_Shortcode::instance()->render_shortcode( $atts );
    }

    public function render_rom_player_block( $attributes ) {
        $rom_id      = ! empty( $attributes['romId'] ) ? $attributes['romId'] : '';
        $show_toggle = isset( $attributes['touchToggle'] ) ? wp_validate_boolean( $attributes['touchToggle'] ) : true;
        $show_meta   = isset( $attributes['showMeta'] ) ? wp_validate_boolean( $attributes['showMeta'] ) : true;
        $extra_class = ! empty( $attributes['className'] ) ? sanitize_html_class( $attributes['className'] ) : '';

        if ( empty( $rom_id ) ) {
            return '<div class="wp-gamify-rom-player__empty">' . esc_html__( 'No ROM selected. Please select a ROM in the block settings.', 'wp-gamify-bridge' ) . '</div>';
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
}
