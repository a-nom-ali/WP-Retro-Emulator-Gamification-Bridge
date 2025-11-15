<?php
/**
 * ROM Library admin helpers.
 *
 * @package WP_Gamify_Bridge
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Gamify_Bridge_Rom_Library
 *
 * Enhances the retro_rom custom post type with meta boxes,
 * custom columns, and helper UI so admins/editors can manage ROM metadata.
 */
class WP_Gamify_Bridge_Rom_Library {

	/**
	 * Single instance of the class.
	 *
	 * @var WP_Gamify_Bridge_Rom_Library
	 */
	private static $instance = null;

	/**
	 * Meta keys used by the ROM CPT.
	 *
	 * @var array
	 */
	private $meta_keys = array(
		'adapter'         => '_retro_rom_adapter',
		'source'          => '_retro_rom_source',
		'checksum'        => '_retro_rom_checksum',
		'file_size'       => '_retro_rom_file_size',
		'release_year'    => '_retro_rom_release_year',
		'publisher'       => '_retro_rom_publisher',
		'notes'           => '_retro_rom_notes',
		'gamification'    => '_retro_rom_gamification',
		'control_profile' => '_retro_rom_control_profile',
		'touch_settings'  => '_retro_rom_touch_settings',
		'save_state'      => '_retro_rom_save_state',
	);

	/**
	 * Get singleton instance.
	 *
	 * @return WP_Gamify_Bridge_Rom_Library
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
		add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
		add_action( 'save_post_retro_rom', array( $this, 'save_rom_meta' ) );
		add_filter( 'manage_retro_rom_posts_columns', array( $this, 'register_list_columns' ) );
		add_action( 'manage_retro_rom_posts_custom_column', array( $this, 'render_list_columns' ), 10, 2 );
		add_filter( 'manage_edit-retro_rom_sortable_columns', array( $this, 'register_sortable_columns' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_scripts( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || 'retro_rom' !== $screen->post_type ) {
			return;
		}

		// Localize adapter metadata for JavaScript.
		$manager = WP_Gamify_Bridge_Emulator_Manager::instance();
		$metadata = $manager ? $manager->get_adapters_metadata() : array();

		wp_add_inline_script(
			'jquery',
			'var wpGamifyAdapterMetadata = ' . wp_json_encode( $metadata ) . ';',
			'before'
		);

		// Add inline script for dynamic adapter tooltips.
		wp_add_inline_script(
			'jquery',
			"
			jQuery(document).ready(function($) {
				var adapterSelect = $('#retro-rom-adapter');
				var metadataContainer = $('#adapter-metadata-display');

				function updateAdapterMetadata() {
					var selectedAdapter = adapterSelect.val();
					if (!selectedAdapter || !wpGamifyAdapterMetadata[selectedAdapter]) {
						metadataContainer.html('').hide();
						return;
					}

					var meta = wpGamifyAdapterMetadata[selectedAdapter];
					var html = '<div class=\"adapter-metadata-info\" style=\"background: #f0f0f1; border-left: 4px solid #2271b1; padding: 12px; margin-top: 10px;\">';
					html += '<h4 style=\"margin: 0 0 10px 0;\">' + meta.display_name + ' Details</h4>';

					// File extensions
					if (meta.supported_extensions && meta.supported_extensions.length > 0) {
						html += '<p><strong>Supported File Extensions:</strong> ';
						html += meta.supported_extensions.map(function(ext) { return '.' + ext; }).join(', ');
						html += '</p>';
					}

					// Save-state support
					html += '<p><strong>Save-State Support:</strong> ';
					html += meta.supports_save_state ? '<span style=\"color: #46b450;\">✓ Yes</span>' : '<span style=\"color: #999;\">✗ No</span>';
					html += '</p>';

					// Control mappings
					if (meta.control_mappings && Object.keys(meta.control_mappings).length > 0) {
						html += '<p><strong>Control Mappings:</strong><br>';
						var controls = [];
						for (var key in meta.control_mappings) {
							controls.push('<code>' + key + '</code>: ' + meta.control_mappings[key]);
						}
						html += controls.join(', ');
						html += '</p>';
					}

					// Setup instructions
					if (meta.setup_instructions) {
						html += '<p><strong>Setup Instructions:</strong><br>' + meta.setup_instructions + '</p>';
					}

					// Score multiplier
					if (meta.score_multiplier) {
						html += '<p><strong>Default Score Multiplier:</strong> ' + meta.score_multiplier + 'x</p>';
					}

					html += '</div>';
					metadataContainer.html(html).show();
				}

				adapterSelect.on('change', updateAdapterMetadata);
				updateAdapterMetadata(); // Run on load
			});
			"
		);
	}

	/**
	 * Register ROM meta boxes.
	 */
	public function register_meta_boxes() {
		add_meta_box(
			'retro-rom-details',
			__( 'ROM Details', 'wp-gamify-bridge' ),
			array( $this, 'render_details_metabox' ),
			'retro_rom',
			'normal',
			'high'
		);

		add_meta_box(
			'retro-rom-gamification',
			__( 'Gamification & Controls', 'wp-gamify-bridge' ),
			array( $this, 'render_gamification_metabox' ),
			'retro_rom',
			'normal',
			'default'
		);
	}

	/**
	 * Render ROM details meta box.
	 *
	 * @param WP_Post $post Current ROM post.
	 */
	public function render_details_metabox( $post ) {
		wp_nonce_field( 'retro_rom_meta', 'retro_rom_meta_nonce' );

		$adapter      = get_post_meta( $post->ID, $this->meta_keys['adapter'], true );
		$source       = get_post_meta( $post->ID, $this->meta_keys['source'], true );
		$checksum     = get_post_meta( $post->ID, $this->meta_keys['checksum'], true );
		$file_size    = get_post_meta( $post->ID, $this->meta_keys['file_size'], true );
		$release_year = get_post_meta( $post->ID, $this->meta_keys['release_year'], true );
		$publisher    = get_post_meta( $post->ID, $this->meta_keys['publisher'], true );
		$notes        = get_post_meta( $post->ID, $this->meta_keys['notes'], true );

		$adapters = $this->get_adapter_choices();
		?>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="retro-rom-adapter"><?php esc_html_e( 'Emulator Adapter', 'wp-gamify-bridge' ); ?></label>
				</th>
				<td>
					<select name="retro_rom[adapter]" id="retro-rom-adapter" class="regular-text">
						<option value=""><?php esc_html_e( 'Select adapter', 'wp-gamify-bridge' ); ?></option>
						<?php foreach ( $adapters as $slug => $label ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $adapter, $slug ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Determines which emulator adapter boots this ROM and how events are transformed.', 'wp-gamify-bridge' ); ?></p>
					<div id="adapter-metadata-display"></div>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="retro-rom-source"><?php esc_html_e( 'ROM Source', 'wp-gamify-bridge' ); ?></label>
				</th>
				<td>
					<input type="text" name="retro_rom[source]" id="retro-rom-source" class="regular-text" value="<?php echo esc_attr( $source ); ?>" placeholder="<?php esc_attr_e( 'Attachment ID or external URL', 'wp-gamify-bridge' ); ?>">
					<p class="description"><?php esc_html_e( 'Upload the ROM via Media Library and paste the attachment ID, or reference a signed URL/object key.', 'wp-gamify-bridge' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="retro-rom-release-year"><?php esc_html_e( 'Release Year', 'wp-gamify-bridge' ); ?></label>
				</th>
				<td>
					<input type="number" name="retro_rom[release_year]" id="retro-rom-release-year" class="small-text" min="1950" max="<?php echo esc_attr( gmdate( 'Y' ) ); ?>" value="<?php echo esc_attr( $release_year ); ?>">
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="retro-rom-publisher"><?php esc_html_e( 'Publisher / Developer', 'wp-gamify-bridge' ); ?></label>
				</th>
				<td>
					<input type="text" name="retro_rom[publisher]" id="retro-rom-publisher" class="regular-text" value="<?php echo esc_attr( $publisher ); ?>">
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="retro-rom-file-size"><?php esc_html_e( 'File Size (bytes)', 'wp-gamify-bridge' ); ?></label>
				</th>
				<td>
					<input type="number" name="retro_rom[file_size]" id="retro-rom-file-size" class="regular-text" min="0" value="<?php echo esc_attr( $file_size ); ?>">
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="retro-rom-checksum"><?php esc_html_e( 'Checksum (MD5/SHA)', 'wp-gamify-bridge' ); ?></label>
				</th>
				<td>
					<input type="text" name="retro_rom[checksum]" id="retro-rom-checksum" class="regular-text" value="<?php echo esc_attr( $checksum ); ?>">
					<p class="description"><?php esc_html_e( 'Optional integrity hash for migration verification.', 'wp-gamify-bridge' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="retro-rom-notes"><?php esc_html_e( 'Internal Notes / Legal', 'wp-gamify-bridge' ); ?></label>
				</th>
				<td>
					<textarea name="retro_rom[notes]" id="retro-rom-notes" class="large-text" rows="3"><?php echo esc_textarea( $notes ); ?></textarea>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render gamification meta box.
	 *
	 * @param WP_Post $post Current ROM post.
	 */
	public function render_gamification_metabox( $post ) {
		$gamification    = get_post_meta( $post->ID, $this->meta_keys['gamification'], true );
		$control_profile = get_post_meta( $post->ID, $this->meta_keys['control_profile'], true );
		$touch_settings  = get_post_meta( $post->ID, $this->meta_keys['touch_settings'], true );
		$save_state      = get_post_meta( $post->ID, $this->meta_keys['save_state'], true );

		?>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="retro-rom-gamification"><?php esc_html_e( 'Gamification Overrides', 'wp-gamify-bridge' ); ?></label>
				</th>
				<td>
					<textarea name="retro_rom[gamification]" id="retro-rom-gamification" class="large-text code" rows="4" placeholder='<?php esc_attr_e( '{"xp_multiplier":1.5,"badge":"Retro Master"}', 'wp-gamify-bridge' ); ?>'><?php echo esc_textarea( is_array( $gamification ) ? wp_json_encode( $gamification ) : $gamification ); ?></textarea>
					<p class="description"><?php esc_html_e( 'JSON object describing per-ROM XP multipliers, badge IDs, or reward logic. Leave blank to use defaults.', 'wp-gamify-bridge' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="retro-rom-control-profile"><?php esc_html_e( 'Control Layout', 'wp-gamify-bridge' ); ?></label>
				</th>
				<td>
					<textarea name="retro_rom[control_profile]" id="retro-rom-control-profile" class="large-text code" rows="4" placeholder='<?php esc_attr_e( '{"a":"Z","b":"X","start":"Enter"}', 'wp-gamify-bridge' ); ?>'><?php echo esc_textarea( is_array( $control_profile ) ? wp_json_encode( $control_profile ) : $control_profile ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Optional JSON map describing desktop key bindings or alternate labels.', 'wp-gamify-bridge' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="retro-rom-touch-settings"><?php esc_html_e( 'Touch Controls', 'wp-gamify-bridge' ); ?></label>
				</th>
				<td>
					<textarea name="retro_rom[touch_settings]" id="retro-rom-touch-settings" class="large-text code" rows="3" placeholder='<?php esc_attr_e( '{"autoShow":true,"sensitivity":1}', 'wp-gamify-bridge' ); ?>'><?php echo esc_textarea( is_array( $touch_settings ) ? wp_json_encode( $touch_settings ) : $touch_settings ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Define auto-show behavior, toggle labels, or sensitivity overrides for on-screen controls.', 'wp-gamify-bridge' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable Save States', 'wp-gamify-bridge' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="retro_rom[save_state]" value="1" <?php checked( (bool) $save_state ); ?>>
						<?php esc_html_e( 'Allow save/load state actions for this ROM', 'wp-gamify-bridge' ); ?>
					</label>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save ROM metadata.
	 *
	 * @param int $post_id Current post ID.
	 */
	public function save_rom_meta( $post_id ) {
		// Bail on autosave or invalid nonce.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! isset( $_POST['retro_rom_meta_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['retro_rom_meta_nonce'] ), 'retro_rom_meta' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( empty( $_POST['retro_rom'] ) || ! is_array( $_POST['retro_rom'] ) ) {
			return;
		}

		$data = wp_unslash( $_POST['retro_rom'] );

		// Simple text/integer fields.
		update_post_meta( $post_id, $this->meta_keys['adapter'], sanitize_text_field( $this->get_value( $data, 'adapter' ) ) );
		update_post_meta( $post_id, $this->meta_keys['source'], sanitize_text_field( $this->get_value( $data, 'source' ) ) );
		update_post_meta( $post_id, $this->meta_keys['checksum'], sanitize_text_field( $this->get_value( $data, 'checksum' ) ) );
		update_post_meta( $post_id, $this->meta_keys['publisher'], sanitize_text_field( $this->get_value( $data, 'publisher' ) ) );
		update_post_meta( $post_id, $this->meta_keys['notes'], sanitize_textarea_field( $this->get_value( $data, 'notes' ) ) );

		$file_size = absint( $this->get_value( $data, 'file_size' ) );
		update_post_meta( $post_id, $this->meta_keys['file_size'], $file_size );

		$release_year = absint( $this->get_value( $data, 'release_year' ) );
		if ( $release_year > 0 ) {
			update_post_meta( $post_id, $this->meta_keys['release_year'], $release_year );
		} else {
			delete_post_meta( $post_id, $this->meta_keys['release_year'] );
		}

		// JSON fields.
		$this->update_json_meta( $post_id, $this->meta_keys['gamification'], $this->get_value( $data, 'gamification' ) );
		$this->update_json_meta( $post_id, $this->meta_keys['control_profile'], $this->get_value( $data, 'control_profile' ) );
		$this->update_json_meta( $post_id, $this->meta_keys['touch_settings'], $this->get_value( $data, 'touch_settings' ) );

		update_post_meta( $post_id, $this->meta_keys['save_state'], ! empty( $data['save_state'] ) );
	}

	/**
	 * Register admin list table columns.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function register_list_columns( $columns ) {
		$new_columns = array();

		foreach ( $columns as $key => $label ) {
			$new_columns[ $key ] = $label;

			if ( 'title' === $key ) {
				$new_columns['adapter']  = __( 'Adapter', 'wp-gamify-bridge' );
				$new_columns['system']   = __( 'Systems', 'wp-gamify-bridge' );
				$new_columns['source']   = __( 'Source', 'wp-gamify-bridge' );
				$new_columns['updated']  = __( 'Last Updated', 'wp-gamify-bridge' );
			}
		}

		return $new_columns;
	}

	/**
	 * Render custom column content.
	 *
	 * @param string $column Column name.
	 * @param int    $post_id Post ID.
	 */
	public function render_list_columns( $column, $post_id ) {
		switch ( $column ) {
			case 'adapter':
				$adapter = get_post_meta( $post_id, $this->meta_keys['adapter'], true );
				echo esc_html( $this->get_adapter_label( $adapter ) );
				break;
			case 'system':
				$terms = get_the_term_list( $post_id, 'retro_system', '', ', ', '' );
				if ( $terms ) {
					echo wp_kses_post( $terms );
				} else {
					echo '<span class="description">' . esc_html__( 'None', 'wp-gamify-bridge' ) . '</span>';
				}
				break;
			case 'source':
				$source = get_post_meta( $post_id, $this->meta_keys['source'], true );
				if ( empty( $source ) ) {
					echo '<span class="description">' . esc_html__( 'Not set', 'wp-gamify-bridge' ) . '</span>';
					break;
				}
				if ( is_numeric( $source ) ) {
					$file = get_attached_file( (int) $source );
					echo '<code>' . esc_html( basename( $file ) ) . '</code>';
				} else {
					echo '<code>' . esc_html( wp_parse_url( $source, PHP_URL_PATH ) ?? $source ) . '</code>';
				}
				break;
			case 'updated':
				$time = get_post_modified_time( 'U', true, $post_id );
				echo esc_html( $time ? human_time_diff( $time ) . ' ' . __( 'ago', 'wp-gamify-bridge' ) : __( '—', 'wp-gamify-bridge' ) );
				break;
		}
	}

	/**
	 * Make columns sortable.
	 *
	 * @param array $columns Sortable columns.
	 * @return array
	 */
	public function register_sortable_columns( $columns ) {
		$columns['adapter'] = 'adapter';
		$columns['updated'] = 'updated';
		return $columns;
	}

	/**
	 * Retrieve adapters available for selection.
	 *
	 * @return array
	 */
	private function get_adapter_choices() {
		$choices  = array();
		$manager  = WP_Gamify_Bridge_Emulator_Manager::instance();
		$adapters = $manager ? $manager->get_adapters() : array();

		foreach ( $adapters as $adapter ) {
			if ( ! is_object( $adapter ) || ! method_exists( $adapter, 'get_name' ) ) {
				continue;
			}

			$choices[ $adapter->get_name() ] = method_exists( $adapter, 'get_display_name' )
				? $adapter->get_display_name()
				: $adapter->get_name();
		}

		ksort( $choices );

		return $choices;
	}

	/**
	 * Return human-readable adapter label.
	 *
	 * @param string $adapter Adapter slug.
	 * @return string
	 */
	private function get_adapter_label( $adapter ) {
		$choices = $this->get_adapter_choices();

		return isset( $choices[ $adapter ] ) ? $choices[ $adapter ] : __( 'Unknown', 'wp-gamify-bridge' );
	}

	/**
	 * Helper to get array value safely.
	 *
	 * @param array  $data Array of submitted data.
	 * @param string $key Key to fetch.
	 * @return mixed
	 */
	private function get_value( $data, $key ) {
		return isset( $data[ $key ] ) ? $data[ $key ] : '';
	}

	/**
	 * Helper to store JSON meta (accept JSON string or array).
	 *
	 * @param int    $post_id Post ID.
	 * @param string $meta_key Meta key.
	 * @param mixed  $value Submitted value.
	 */
	private function update_json_meta( $post_id, $meta_key, $value ) {
		if ( empty( $value ) ) {
			delete_post_meta( $post_id, $meta_key );
			return;
		}

		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			if ( json_last_error() === JSON_ERROR_NONE ) {
				update_post_meta( $post_id, $meta_key, $decoded );
			} else {
				// Store raw string to avoid data loss; surface errors in UI later.
				update_post_meta( $post_id, $meta_key, $value );
			}
		} elseif ( is_array( $value ) ) {
			update_post_meta( $post_id, $meta_key, $value );
		}
	}
}
