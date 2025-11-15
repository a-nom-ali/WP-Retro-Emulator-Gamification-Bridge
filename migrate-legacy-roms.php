<?php
/**
 * Legacy ROM Migration Utility
 *
 * Imports ROM files from /wp-content/uploads/retro-game-emulator
 * into the new retro_rom custom post type with metadata.
 *
 * Access via: /wp-content/plugins/wp-retro-emulator-gamification-bridge/migrate-legacy-roms.php
 *
 * @package WP_Gamify_Bridge
 */

//require_once '../../../wp-load.php';
require_once '/Users/nielowait/Local Sites/campaign-forge/app/public/wp-load.php';

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( 'Unauthorized access.' );
}

// Ensure taxonomies are registered before migration runs.
// The plugin's init hook may not have fired yet when this script is accessed directly.
if ( class_exists( 'WP_Gamify_Bridge_Post_Types' ) ) {
	$post_types_instance = WP_Gamify_Bridge_Post_Types::instance();
	// Force taxonomy registration if not already done.
	if ( ! taxonomy_exists( 'retro_system' ) ) {
		do_action( 'init' );
	}
}

set_time_limit( 300 );
ini_set( 'memory_limit', '512M' );

$uploads     = wp_upload_dir();
$legacy_path = trailingslashit( $uploads['basedir'] ) . 'retro-game-emulator';
$legacy_url  = trailingslashit( $uploads['baseurl'] ) . 'retro-game-emulator';

$supported_extensions = array(
	'nes' => array(
		'adapter'    => 'jsnes',
		'systems'    => array( 'NES' ),
		'difficulty' => 'normal',
		'modes'      => array( 'Single Player' ),
	),
	'smc' => array(
		'adapter' => 'jsnes_snes',
		'systems' => array( 'SNES' ),
	),
	'sfc' => array(
		'adapter' => 'jsnes_snes',
		'systems' => array( 'SNES' ),
	),
	'fig' => array(
		'adapter' => 'jsnes_snes',
		'systems' => array( 'SNES' ),
	),
	'gba' => array(
		'adapter' => 'gba',
		'systems' => array( 'GBA' ),
		'modes'   => array( 'Single Player', 'Link Cable' ),
	),
	'gb'  => array(
		'adapter' => 'gba',
		'systems' => array( 'Game Boy' ),
	),
	'gbc' => array(
		'adapter' => 'gba',
		'systems' => array( 'Game Boy Color' ),
	),
);

/**
 * Ensure taxonomy term exists.
 *
 * @param string $name Term name.
 * @param string $taxonomy Taxonomy slug.
 * @return int|WP_Error Term ID.
 */
function wp_gamify_bridge_ensure_term( $name, $taxonomy ) {
	$term = term_exists( $name, $taxonomy );

	if ( ! $term ) {
		$term = wp_insert_term( $name, $taxonomy );
	}

	if ( is_wp_error( $term ) ) {
		return $term;
	}

	return (int) $term['term_id'];
}

/**
 * Format bytes.
 *
 * @param int $bytes Bytes.
 * @return string
 */
function wp_gamify_bridge_human_bytes( $bytes ) {
	if ( $bytes < 1024 ) {
		return $bytes . ' B';
	}

	$units = array( 'KB', 'MB', 'GB' );
	$pow   = floor( log( $bytes, 1024 ) );
	$pow   = min( $pow, count( $units ) );

	$bytes /= ( 1024 ** $pow );

	return round( $bytes, 2 ) . ' ' . $units[ $pow - 1 ];
}

/**
 * Create WordPress attachment from ROM file.
 *
 * @param string $file_path Full path to ROM file.
 * @param string $title Post title for attachment.
 * @return int|WP_Error Attachment ID or error.
 */
function wp_gamify_bridge_create_attachment( $file_path, $title ) {
	if ( ! file_exists( $file_path ) ) {
		return new WP_Error( 'file_not_found', 'File not found: ' . $file_path );
	}

	// Get file type.
	$filetype = wp_check_filetype( basename( $file_path ), null );

	// Prepare attachment data.
	$attachment = array(
		'guid'           => wp_upload_dir()['url'] . '/' . basename( $file_path ),
		'post_mime_type' => $filetype['type'] ? $filetype['type'] : 'application/octet-stream',
		'post_title'     => $title,
		'post_content'   => '',
		'post_status'    => 'inherit',
	);

	// Insert attachment.
	$attach_id = wp_insert_attachment( $attachment, $file_path );

	if ( is_wp_error( $attach_id ) ) {
		return $attach_id;
	}

	// Generate metadata.
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$attach_data = wp_generate_attachment_metadata( $attach_id, $file_path );
	wp_update_attachment_metadata( $attach_id, $attach_data );

	return $attach_id;
}

?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<title>Legacy ROM Migration</title>
	<style>
		body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 30px; max-width: 900px; margin: 0 auto; }
		h1 { margin-bottom: 0; }
		.notice { padding: 15px; border-left: 4px solid #007cba; background: #f0f6fc; margin: 20px 0; }
		.notice.error { border-color: #d63638; background: #fff8f7; }
		.notice.success { border-color: #1a7f37; background: #f0fdf4; }
		.notice.warning { border-color: #dba617; background: #fff9e6; }
		.button { background: #007cba; color: #fff; text-decoration: none; padding: 10px 18px; border-radius: 4px; display: inline-block; margin-right: 10px; }
		.button:hover { background: #135e96; }
		table { width: 100%; border-collapse: collapse; margin-top: 15px; }
		th, td { border: 1px solid #dcdcde; padding: 8px; text-align: left; }
		th { background: #f6f7f7; }
		code { background: #f0f0f1; padding: 2px 4px; border-radius: 3px; }
	</style>
</head>
<body>
	<h1>Legacy ROM Migration (Enhanced)</h1>
	<p>Scans <code><?php echo esc_html( $legacy_path ); ?></code> for ROM files and creates:</p>
	<ul>
		<li><strong>WordPress Attachments</strong> for each ROM file (proper Media Library integration)</li>
		<li><strong>retro_rom</strong> posts with full metadata (adapter, checksum, file size, taxonomies)</li>
	</ul>
	<p><em>Uses the new upload infrastructure from Phase 2 for proper attachment handling.</em></p>

	<?php
	if ( ! is_dir( $legacy_path ) ) {
		echo '<div class="notice error"><strong>Directory not found:</strong> ' . esc_html( $legacy_path ) . '</div>';
		echo '</body></html>';
		exit;
	}

	$files = glob( $legacy_path . '/*.*' );

	if ( empty( $files ) ) {
		echo '<div class="notice warning"><strong>No ROM files found.</strong> Add files to ' . esc_html( $legacy_path ) . ' and reload.</div>';
		echo '</body></html>';
		exit;
	}

	$total_files = count( $files );

	echo '<div class="notice"><strong>Found ' . esc_html( $total_files ) . ' files.</strong> Supported extensions: ' . implode( ', ', array_keys( $supported_extensions ) ) . '.</div>';

	if ( isset( $_GET['run'] ) && 'migrate' === $_GET['run'] ) {
		$migrated = 0;
		$skipped  = array();
		$errors   = array();

		$system_terms = array();

		foreach ( $files as $file ) {
			$extension = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );

		if ( ! isset( $supported_extensions[ $extension ] ) ) {
			$skipped[] = basename( $file ) . ' (unsupported extension)';
			continue;
		}

			// Check if ROM already exists by checking for matching checksum.
			$checksum = md5_file( $file );
			$existing = get_posts(
				array(
					'post_type'  => 'retro_rom',
					'meta_key'   => '_retro_rom_checksum',
					'meta_value' => $checksum,
					'fields'     => 'ids',
					'numberposts'=> 1,
				)
			);

			if ( ! empty( $existing ) ) {
				$skipped[] = basename( $file ) . ' (already imported - matching checksum)';
				continue;
			}

			$title = ucwords( preg_replace( '/[-_]+/', ' ', pathinfo( $file, PATHINFO_FILENAME ) ) );
			$title = trim( $title ) ? $title : basename( $file );

			$post_id = wp_insert_post(
				array(
					'post_title'  => $title,
					'post_type'   => 'retro_rom',
					'post_status' => 'publish',
				),
				true
			);

			if ( is_wp_error( $post_id ) ) {
				$errors[] = basename( $file ) . ': ' . $post_id->get_error_message();
				continue;
			}

		$config  = $supported_extensions[ $extension ];
		$adapter = $config['adapter'];
		$systems = isset( $config['systems'] ) ? (array) $config['systems'] : array();

		// Create WordPress attachment for ROM file.
		$attachment_id = wp_gamify_bridge_create_attachment( $file, $title );

		if ( is_wp_error( $attachment_id ) ) {
			$errors[] = basename( $file ) . ': ' . $attachment_id->get_error_message();
			wp_delete_post( $post_id, true );
			continue;
		}

		// Store adapter and attachment ID.
		update_post_meta( $post_id, '_retro_rom_adapter', $adapter );
		update_post_meta( $post_id, '_retro_rom_source', $attachment_id );

		// Auto-extract metadata (checksum, file size).
		update_post_meta( $post_id, '_retro_rom_file_size', filesize( $file ) );
		update_post_meta( $post_id, '_retro_rom_checksum', md5_file( $file ) );

		foreach ( $systems as $system ) {
			$term_key = strtolower( $system );
			if ( ! isset( $system_terms[ $term_key ] ) ) {
				$system_terms[ $term_key ] = wp_gamify_bridge_ensure_term( $system, 'retro_system' );
			}

			if ( ! is_wp_error( $system_terms[ $term_key ] ) ) {
				wp_set_object_terms( $post_id, (int) $system_terms[ $term_key ], 'retro_system', true );
			}
		}

		if ( ! empty( $config['difficulty'] ) ) {
			wp_set_object_terms( $post_id, $config['difficulty'], 'retro_difficulty', true );
		}

		if ( ! empty( $config['modes'] ) ) {
			wp_set_object_terms( $post_id, (array) $config['modes'], 'retro_multiplayer_mode', true );
		}

		$migrated++;
	}

		echo '<div class="notice success"><strong>Migration complete.</strong> Imported ' . esc_html( $migrated ) . ' ROMs.</div>';

		if ( ! empty( $skipped ) ) {
			echo '<div class="notice warning"><strong>Skipped files:</strong><br><pre>' . esc_html( implode( "\n", $skipped ) ) . '</pre></div>';
		}

		if ( ! empty( $errors ) ) {
			echo '<div class="notice error"><strong>Errors:</strong><br><pre>' . esc_html( implode( "\n", $errors ) ) . '</pre></div>';
		}

		echo '<p>';
		echo '<a class="button" href="' . esc_url( admin_url( 'edit.php?post_type=retro_rom' ) ) . '">View ROM Library →</a>';
		echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=gamify-bridge' ) ) . '">Back to Plugin Dashboard →</a>';
		echo '</p>';

	} else {
		echo '<div class="notice warning">';
		echo '<strong>Before you migrate:</strong>';
		echo '<ol>';
		echo '<li>Back up your database (<code>wp db export</code>).</li>';
		echo '<li>Ensure files exist under <code>' . esc_html( $legacy_path ) . '</code>.</li>';
		echo '<li>Supported extensions today: ' . implode( ', ', array_keys( $supported_extensions ) ) . '.</li>';
		echo '<li>Each file will create:';
		echo '<ul>';
		echo '<li>A WordPress <strong>attachment</strong> (Media Library entry)</li>';
		echo '<li>A <code>retro_rom</code> post with adapter, checksum, file size</li>';
		echo '<li>Taxonomy terms for system, difficulty, multiplayer mode</li>';
		echo '</ul>';
		echo '</li>';
		echo '<li>Duplicate detection: ROMs with matching MD5 checksums will be skipped.</li>';
		echo '</ol>';
		echo '</div>';

		echo '<table>';
		echo '<thead><tr><th>Filename</th><th>Size</th><th>Adapter</th><th>Systems</th><th>Status</th></tr></thead>';
		echo '<tbody>';
		foreach ( $files as $file ) {
			$extension = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
			$config    = isset( $supported_extensions[ $extension ] ) ? $supported_extensions[ $extension ] : null;
			echo '<tr>';
			echo '<td><code>' . esc_html( basename( $file ) ) . '</code></td>';
			echo '<td>' . esc_html( wp_gamify_bridge_human_bytes( filesize( $file ) ) ) . '</td>';
			echo '<td>' . esc_html( $config ? strtoupper( $config['adapter'] ) : '—' ) . '</td>';
			echo '<td>' . esc_html( $config && ! empty( $config['systems'] ) ? implode( ', ', (array) $config['systems'] ) : '—' ) . '</td>';
			echo '<td>' . esc_html( $config ? 'Ready' : 'Unsupported' ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody>';
		echo '</table>';

		echo '<p><a class="button" href="?run=migrate">▶️ Start Migration</a></p>';
	}
	?>
</body>
</html>
