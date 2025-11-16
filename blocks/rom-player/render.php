<?php
/**
 * ROM Player block render template.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block default content.
 * @var WP_Block $block      Block instance.
 *
 * @package WP_Gamify_Bridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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

echo WP_Gamify_Bridge_Emulator_Shortcode::instance()->render_rom_player_shortcode( $atts );
