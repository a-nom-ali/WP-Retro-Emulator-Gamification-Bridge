<?php
/**
 * Retro Emulator shortcode template.
 *
 * @var array  $roms_list   List of ROM metadata arrays.
 * @var array  $active_rom  Active ROM metadata.
 * @var string $wrapper_id  Unique DOM id.
 * @var bool   $show_toggle Whether to render touch toggle button.
 * @var string $auto_touch  Touch behaviour mode.
 * @var bool   $legacy_notice Show legacy notice.
 * @var string $extra_class Additional wrapper class.
 *
 * @package WP_Gamify_Bridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rom_id       = isset( $active_rom['id'] ) ? (int) $active_rom['id'] : 0;
$auto_touch   = esc_attr( $auto_touch );
$default_meta = array(
	'systems'      => array(),
	'file_size'    => null,
	'release_year' => null,
	'publisher'    => '',
	'touch'        => array(),
);
$active_rom   = wp_parse_args( $active_rom, $default_meta );
$notes_text   = '';
if ( ! empty( $active_rom['notes'] ) ) {
	$notes_text = is_array( $active_rom['notes'] ) ? wp_json_encode( $active_rom['notes'] ) : $active_rom['notes'];
}
?>
<div id="<?php echo esc_attr( $wrapper_id ); ?>"
	class="wp-gamify-emulator <?php echo $legacy_notice ? 'is-legacy' : ''; ?> <?php echo $extra_class ? esc_attr( $extra_class ) : ''; ?>"
	data-default-rom="<?php echo esc_attr( $rom_id ); ?>"
	data-auto-touch="<?php echo esc_attr( $auto_touch ); ?>">

	<div class="wp-gamify-emulator__screen">
		<canvas width="256" height="240" class="wp-gamify-emulator__canvas"></canvas>
		<div class="wp-gamify-emulator__status" role="status" aria-live="polite"><?php esc_html_e( 'Select a ROM to begin.', 'wp-gamify-bridge' ); ?></div>

		<div class="wp-gamify-emulator__touch" aria-hidden="true">
			<div class="wp-gamify-emulator__touch-dpad">
				<button type="button" class="touch-btn touch-btn--up" data-button="up" aria-label="<?php esc_attr_e( 'Up', 'wp-gamify-bridge' ); ?>"></button>
				<button type="button" class="touch-btn touch-btn--down" data-button="down" aria-label="<?php esc_attr_e( 'Down', 'wp-gamify-bridge' ); ?>"></button>
				<button type="button" class="touch-btn touch-btn--left" data-button="left" aria-label="<?php esc_attr_e( 'Left', 'wp-gamify-bridge' ); ?>"></button>
				<button type="button" class="touch-btn touch-btn--right" data-button="right" aria-label="<?php esc_attr_e( 'Right', 'wp-gamify-bridge' ); ?>"></button>
			</div>
			<div class="wp-gamify-emulator__touch-actions">
				<button type="button" class="touch-btn touch-btn--a" data-button="a">A</button>
				<button type="button" class="touch-btn touch-btn--b" data-button="b">B</button>
				<button type="button" class="touch-btn touch-btn--start" data-button="start"><?php esc_html_e( 'Start', 'wp-gamify-bridge' ); ?></button>
				<button type="button" class="touch-btn touch-btn--select" data-button="select"><?php esc_html_e( 'Select', 'wp-gamify-bridge' ); ?></button>
			</div>
		</div>
	</div>

	<div class="wp-gamify-emulator__panel">
		<header class="wp-gamify-emulator__header">
			<h3><?php esc_html_e( 'Game Settings', 'wp-gamify-bridge' ); ?></h3>
			<div class="wp-gamify-emulator__actions">
				<?php if ( $show_toggle ) : ?>
					<button type="button" class="button button-secondary wp-gamify-emulator__touch-toggle">
						<?php esc_html_e( 'Toggle Touch Controls', 'wp-gamify-bridge' ); ?>
					</button>
				<?php endif; ?>
				<button type="button" class="button button-secondary wp-gamify-emulator__settings" title="<?php esc_attr_e( 'Control Settings (coming soon)', 'wp-gamify-bridge' ); ?>">
					<span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
					<span class="screen-reader-text"><?php esc_html_e( 'Control Settings', 'wp-gamify-bridge' ); ?></span>
				</button>
			</div>
		</header>

		<?php if ( $legacy_notice ) : ?>
			<div class="notice notice-warning">
				<p><?php esc_html_e( 'Heads up: [nes] is deprecated. Please update your shortcode to [retro_emulator] to unlock new features.', 'wp-gamify-bridge' ); ?></p>
			</div>
		<?php endif; ?>

		<label class="wp-gamify-emulator__field">
			<span><?php esc_html_e( 'ROM', 'wp-gamify-bridge' ); ?></span>
			<select class="wp-gamify-emulator__rom-select">
				<?php foreach ( $roms_list as $rom ) : ?>
					<option value="<?php echo esc_attr( $rom['id'] ); ?>" <?php selected( $rom_id, $rom['id'] ); ?>>
						<?php echo esc_html( $rom['title'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</label>

		<div class="wp-gamify-emulator__meta">
			<div class="meta-row">
				<span class="label"><?php esc_html_e( 'System', 'wp-gamify-bridge' ); ?></span>
				<span class="value" data-meta="system"><?php echo esc_html( implode( ', ', $active_rom['systems'] ) ); ?></span>
			</div>
			<div class="meta-row">
				<span class="label"><?php esc_html_e( 'Release', 'wp-gamify-bridge' ); ?></span>
				<span class="value" data-meta="release"><?php echo $active_rom['release_year'] ? esc_html( $active_rom['release_year'] ) : '—'; ?></span>
			</div>
			<div class="meta-row">
				<span class="label"><?php esc_html_e( 'Publisher', 'wp-gamify-bridge' ); ?></span>
				<span class="value" data-meta="publisher"><?php echo $active_rom['publisher'] ? esc_html( $active_rom['publisher'] ) : '—'; ?></span>
			</div>
			<div class="meta-row">
				<span class="label"><?php esc_html_e( 'File Size', 'wp-gamify-bridge' ); ?></span>
				<span class="value" data-meta="size"><?php echo ! empty( $active_rom['file_size_human'] ) ? esc_html( $active_rom['file_size_human'] ) : '—'; ?></span>
			</div>
		</div>

		<?php if ( $notes_text ) : ?>
			<div class="wp-gamify-emulator__notes" data-meta="notes">
				<strong><?php esc_html_e( 'Notes', 'wp-gamify-bridge' ); ?>:</strong>
				<p><?php echo esc_html( $notes_text ); ?></p>
			</div>
		<?php else : ?>
			<div class="wp-gamify-emulator__notes" data-meta="notes" hidden></div>
		<?php endif; ?>
	</div>
</div>
