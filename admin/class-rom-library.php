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
			'retro-rom-upload',
			__( 'ROM File Upload', 'wp-gamify-bridge' ),
			array( $this, 'render_upload_metabox' ),
			'retro_rom',
			'side',
			'high'
		);

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
	 * Render ROM upload meta box.
	 *
	 * @param WP_Post $post Current ROM post.
	 */
	public function render_upload_metabox( $post ) {
		$attachment_id = get_post_meta( $post->ID, $this->meta_keys['source'], true );
		$file_size     = get_post_meta( $post->ID, $this->meta_keys['file_size'], true );
		$checksum      = get_post_meta( $post->ID, $this->meta_keys['checksum'], true );

		// Get adapter to show supported extensions.
		$adapter      = get_post_meta( $post->ID, $this->meta_keys['adapter'], true );
		$adapter_meta = $this->get_adapter_metadata( $adapter );

		?>
		<div class="retro-rom-upload-container">
			<?php if ( is_numeric( $attachment_id ) && $attachment_id > 0 ) : ?>
				<?php
				$file = get_attached_file( (int) $attachment_id );
				$url  = wp_get_attachment_url( (int) $attachment_id );
				?>
				<div class="rom-file-info" style="background: #f0f0f1; padding: 10px; border-radius: 4px; margin-bottom: 10px;">
					<p style="margin: 0 0 5px 0;">
						<strong><?php esc_html_e( 'Current ROM:', 'wp-gamify-bridge' ); ?></strong><br>
						<code><?php echo esc_html( basename( $file ) ); ?></code>
					</p>
					<?php if ( $file_size ) : ?>
						<p style="margin: 0 0 5px 0;">
							<strong><?php esc_html_e( 'Size:', 'wp-gamify-bridge' ); ?></strong>
							<?php echo esc_html( size_format( $file_size, 2 ) ); ?>
						</p>
					<?php endif; ?>
					<?php if ( $checksum ) : ?>
						<p style="margin: 0 0 5px 0;">
							<strong><?php esc_html_e( 'Checksum:', 'wp-gamify-bridge' ); ?></strong><br>
							<code style="font-size: 10px;"><?php echo esc_html( substr( $checksum, 0, 32 ) ); ?></code>
						</p>
					<?php endif; ?>
					<?php if ( $url ) : ?>
						<p style="margin: 5px 0 0 0;">
							<a href="<?php echo esc_url( $url ); ?>" class="button button-small" download><?php esc_html_e( 'Download', 'wp-gamify-bridge' ); ?></a>
							<button type="button" class="button button-small retro-rom-remove-file" style="color: #b32d2e;"><?php esc_html_e( 'Remove', 'wp-gamify-bridge' ); ?></button>
						</p>
					<?php endif; ?>
				</div>
			<?php else : ?>
				<p class="description" style="margin-top: 0;">
					<?php esc_html_e( 'No ROM file uploaded yet.', 'wp-gamify-bridge' ); ?>
				</p>
			<?php endif; ?>

			<button type="button" class="button button-primary button-large retro-rom-upload-button" style="width: 100%;">
				<?php esc_html_e( is_numeric( $attachment_id ) && $attachment_id > 0 ? 'Replace ROM File' : 'Upload ROM File', 'wp-gamify-bridge' ); ?>
			</button>

			<?php if ( ! empty( $adapter_meta['supported_extensions'] ) ) : ?>
				<p class="description" style="margin-top: 10px; margin-bottom: 0;">
					<strong><?php esc_html_e( 'Supported formats:', 'wp-gamify-bridge' ); ?></strong><br>
					<?php echo esc_html( implode( ', ', array_map( function( $ext ) { return '.' . $ext; }, $adapter_meta['supported_extensions'] ) ) ); ?>
				</p>
			<?php endif; ?>

			<input type="hidden" name="retro_rom[attachment_id]" id="retro-rom-attachment-id" value="<?php echo esc_attr( is_numeric( $attachment_id ) ? $attachment_id : '' ); ?>">
		</div>

		<script type="text/javascript">
		jQuery(document).ready(function($) {
			var uploadButton = $('.retro-rom-upload-button');
			var removeButton = $('.retro-rom-remove-file');
			var attachmentInput = $('#retro-rom-attachment-id');
			var frame;

			uploadButton.on('click', function(e) {
				e.preventDefault();

				if (frame) {
					frame.open();
					return;
				}

				frame = wp.media({
					title: '<?php echo esc_js( __( 'Select ROM File', 'wp-gamify-bridge' ) ); ?>',
					button: {
						text: '<?php echo esc_js( __( 'Use this ROM', 'wp-gamify-bridge' ) ); ?>'
					},
					multiple: false,
					library: {
						type: ['application/octet-stream', 'application/zip', 'application/x-zip-compressed']
					}
				});

				frame.on('select', function() {
					var attachment = frame.state().get('selection').first().toJSON();
					attachmentInput.val(attachment.id);

					// Auto-fill source field
					$('#retro-rom-source').val(attachment.id);

					// Show success message
					$('.rom-file-info').remove();
					$('.retro-rom-upload-container').prepend(
						'<div class="notice notice-success inline" style="margin: 0 0 10px 0;"><p>' +
						'<?php echo esc_js( __( 'ROM file selected. Save post to apply changes.', 'wp-gamify-bridge' ) ); ?>' +
						'</p></div>'
					);

					uploadButton.text('<?php echo esc_js( __( 'Replace ROM File', 'wp-gamify-bridge' ) ); ?>');
				});

				frame.open();
			});

			removeButton.on('click', function(e) {
				e.preventDefault();
				if (confirm('<?php echo esc_js( __( 'Remove this ROM file? This will not delete it from the media library.', 'wp-gamify-bridge' ) ); ?>')) {
					attachmentInput.val('');
					$('#retro-rom-source').val('');
					$('.rom-file-info').fadeOut(function() {
						$(this).remove();
					});
					uploadButton.text('<?php echo esc_js( __( 'Upload ROM File', 'wp-gamify-bridge' ) ); ?>');
				}
			});
		});
		</script>
		<?php
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

		// Handle attachment upload.
		$attachment_id = absint( $this->get_value( $data, 'attachment_id' ) );
		if ( $attachment_id > 0 ) {
			// Validate attachment exists and is accessible.
			$file_path = get_attached_file( $attachment_id );
			if ( $file_path && file_exists( $file_path ) ) {
				// Validate MIME type and file size.
				$validation = $this->validate_rom_file( $file_path, $attachment_id );

				if ( is_wp_error( $validation ) ) {
					// Add admin notice for validation error.
					add_settings_error(
						'retro_rom_upload',
						'invalid_rom_file',
						$validation->get_error_message(),
						'error'
					);
				} else {
					// Auto-extract metadata.
					$extracted_meta = $this->extract_rom_metadata( $file_path );

					// Update post meta with attachment info.
					update_post_meta( $post_id, $this->meta_keys['source'], $attachment_id );
					update_post_meta( $post_id, $this->meta_keys['file_size'], $extracted_meta['file_size'] );
					update_post_meta( $post_id, $this->meta_keys['checksum'], $extracted_meta['checksum'] );
				}
			}
		}

		// Simple text/integer fields.
		update_post_meta( $post_id, $this->meta_keys['adapter'], sanitize_text_field( $this->get_value( $data, 'adapter' ) ) );

		// Only update source manually if no attachment provided.
		if ( ! isset( $data['attachment_id'] ) || absint( $data['attachment_id'] ) === 0 ) {
			update_post_meta( $post_id, $this->meta_keys['source'], sanitize_text_field( $this->get_value( $data, 'source' ) ) );
		}

		// Allow manual checksum override.
		if ( ! empty( $data['checksum'] ) ) {
			update_post_meta( $post_id, $this->meta_keys['checksum'], sanitize_text_field( $data['checksum'] ) );
		}

		update_post_meta( $post_id, $this->meta_keys['publisher'], sanitize_text_field( $this->get_value( $data, 'publisher' ) ) );
		update_post_meta( $post_id, $this->meta_keys['notes'], sanitize_textarea_field( $this->get_value( $data, 'notes' ) ) );

		// Allow manual file size override.
		if ( ! empty( $data['file_size'] ) ) {
			$file_size = absint( $data['file_size'] );
			update_post_meta( $post_id, $this->meta_keys['file_size'], $file_size );
		}

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
	 * Get adapter metadata by adapter slug.
	 *
	 * @param string $adapter_slug Adapter slug.
	 * @return array Adapter metadata or empty array.
	 */
	private function get_adapter_metadata( $adapter_slug ) {
		if ( empty( $adapter_slug ) ) {
			return array();
		}

		$manager = WP_Gamify_Bridge_Emulator_Manager::instance();
		if ( ! $manager ) {
			return array();
		}

		$adapter = $manager->get_adapter( $adapter_slug );
		if ( ! $adapter ) {
			return array();
		}

		return $adapter->get_metadata();
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

	/**
	 * Validate ROM file upload.
	 *
	 * @param string $file_path Path to uploaded file.
	 * @param int    $attachment_id Attachment ID.
	 * @return true|WP_Error True if valid, WP_Error otherwise.
	 */
	private function validate_rom_file( $file_path, $attachment_id ) {
		// Check file exists.
		if ( ! file_exists( $file_path ) ) {
			return new WP_Error( 'file_not_found', __( 'Uploaded file not found.', 'wp-gamify-bridge' ) );
		}

		// Get file extension.
		$extension = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );

		// Get allowed extensions (from adapter or default list).
		$allowed_extensions = $this->get_allowed_rom_extensions();

		if ( ! in_array( $extension, $allowed_extensions, true ) ) {
			return new WP_Error(
				'invalid_extension',
				sprintf(
					/* translators: 1: File extension, 2: Allowed extensions */
					__( 'Invalid file extension: .%1$s. Allowed: %2$s', 'wp-gamify-bridge' ),
					$extension,
					implode( ', ', array_map( function( $ext ) { return '.' . $ext; }, $allowed_extensions ) )
				)
			);
		}

		// Check file size.
		$file_size = filesize( $file_path );
		$max_size  = apply_filters( 'wp_gamify_bridge_max_rom_size', 10 * MB_IN_BYTES ); // 10MB default.

		if ( $file_size > $max_size ) {
			return new WP_Error(
				'file_too_large',
				sprintf(
					/* translators: 1: File size, 2: Max allowed size */
					__( 'File size (%1$s) exceeds maximum allowed (%2$s).', 'wp-gamify-bridge' ),
					size_format( $file_size, 2 ),
					size_format( $max_size, 2 )
				)
			);
		}

		// Validate MIME type.
		$mime_type         = get_post_mime_type( $attachment_id );
		$allowed_mime_types = $this->get_allowed_mime_types();

		if ( ! in_array( $mime_type, $allowed_mime_types, true ) ) {
			return new WP_Error(
				'invalid_mime_type',
				sprintf(
					/* translators: %s: MIME type */
					__( 'Invalid file type: %s. Please upload a valid ROM file.', 'wp-gamify-bridge' ),
					$mime_type
				)
			);
		}

		return true;
	}

	/**
	 * Extract ROM metadata from file.
	 *
	 * @param string $file_path Path to ROM file.
	 * @return array Array with file_size and checksum.
	 */
	private function extract_rom_metadata( $file_path ) {
		$metadata = array(
			'file_size' => 0,
			'checksum'  => '',
		);

		if ( ! file_exists( $file_path ) ) {
			return $metadata;
		}

		// Get file size.
		$metadata['file_size'] = filesize( $file_path );

		// Calculate MD5 checksum.
		$metadata['checksum'] = md5_file( $file_path );

		return $metadata;
	}

	/**
	 * Get allowed ROM file extensions.
	 *
	 * @return array Allowed file extensions.
	 */
	private function get_allowed_rom_extensions() {
		$extensions = array(
			// NES/Famicom.
			'nes', 'fds', 'unif', 'unf',
			// SNES/Super Famicom.
			'smc', 'sfc', 'fig', 'swc', 'bs',
			// Game Boy / GBC / GBA.
			'gb', 'gbc', 'gba', 'agb',
			// Genesis / Mega Drive.
			'md', 'gen', 'bin', 'smd',
			// N64.
			'z64', 'n64', 'v64',
			// PlayStation.
			'iso', 'cue', 'bin',
			// Arcade.
			'zip', '7z',
			// Atari.
			'a26', 'a52', 'a78',
		);

		return apply_filters( 'wp_gamify_bridge_allowed_rom_extensions', $extensions );
	}

	/**
	 * Get allowed MIME types for ROM uploads.
	 *
	 * @return array Allowed MIME types.
	 */
	private function get_allowed_mime_types() {
		$mime_types = array(
			'application/octet-stream',
			'application/x-nes-rom',
			'application/x-snes-rom',
			'application/x-gba-rom',
			'application/zip',
			'application/x-zip-compressed',
			'application/x-7z-compressed',
			'application/x-iso9660-image',
		);

		return apply_filters( 'wp_gamify_bridge_allowed_rom_mime_types', $mime_types );
	}
}
