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
        add_filter( 'the_content', array( $this, 'render_blocks_as_shortcodes' ), 8 );
    }

    public function register_blocks() {
        if ( ! function_exists( 'register_block_type' ) ) {
            return;
        }

        // Register blocks using block.json files with render callbacks.
        register_block_type(
            WP_GAMIFY_BRIDGE_PLUGIN_DIR . 'blocks/retro-emulator',
            array(
                'render_callback' => array( $this, 'render_emulator_block' ),
            )
        );

        register_block_type(
            WP_GAMIFY_BRIDGE_PLUGIN_DIR . 'blocks/rom-player',
            array(
                'render_callback' => array( $this, 'render_rom_player_block' ),
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
        $atts = array();
        if ( ! empty( $attributes['romId'] ) ) {
            $atts['rom'] = $attributes['romId'];
        }
        if ( isset( $attributes['touchToggle'] ) ) {
            $atts['touch_toggle'] = $attributes['touchToggle'] ? 'true' : 'false';
        }
        if ( isset( $attributes['showMeta'] ) ) {
            $atts['show_meta'] = $attributes['showMeta'] ? 'true' : 'false';
        }
        if ( ! empty( $attributes['className'] ) ) {
            $atts['class'] = $attributes['className'];
        }

        return WP_Gamify_Bridge_Emulator_Shortcode::instance()->render_rom_player_shortcode( $atts );
    }

    /**
     * Convert block markup to shortcodes as a fallback for theme compatibility.
     *
     * @param string $content Post content.
     * @return string Modified content.
     */
    public function render_blocks_as_shortcodes( $content ) {
        // Convert Retro Emulator blocks to shortcodes.
        $content = preg_replace_callback(
            '/<!-- wp:wp-gamify\/retro-emulator\s+(\{[^}]+\})\s+-->\s*<!-- \/wp:wp-gamify\/retro-emulator -->/s',
            function( $matches ) {
                $attrs = json_decode( $matches[1], true );
                $shortcode_atts = array();

                if ( ! empty( $attrs['rom'] ) ) {
                    $shortcode_atts[] = 'rom="' . esc_attr( $attrs['rom'] ) . '"';
                }
                if ( isset( $attrs['touchToggle'] ) ) {
                    $shortcode_atts[] = 'touch_toggle="' . ( $attrs['touchToggle'] ? 'true' : 'false' ) . '"';
                }

                return '[retro_emulator ' . implode( ' ', $shortcode_atts ) . ']';
            },
            $content
        );

        // Convert ROM Player blocks to shortcodes.
        $content = preg_replace_callback(
            '/<!-- wp:wp-gamify\/rom-player\s+(\{[^}]+\})\s+-->\s*<!-- \/wp:wp-gamify\/rom-player -->/s',
            function( $matches ) {
                $attrs = json_decode( $matches[1], true );
                $shortcode_atts = array();

                if ( ! empty( $attrs['romId'] ) ) {
                    $shortcode_atts[] = 'rom="' . esc_attr( $attrs['romId'] ) . '"';
                }
                if ( isset( $attrs['touchToggle'] ) ) {
                    $shortcode_atts[] = 'touch_toggle="' . ( $attrs['touchToggle'] ? 'true' : 'false' ) . '"';
                }
                if ( isset( $attrs['showMeta'] ) ) {
                    $shortcode_atts[] = 'show_meta="' . ( $attrs['showMeta'] ? 'true' : 'false' ) . '"';
                }

                return '[rom_player ' . implode( ' ', $shortcode_atts ) . ']';
            },
            $content
        );

        return $content;
    }
}
