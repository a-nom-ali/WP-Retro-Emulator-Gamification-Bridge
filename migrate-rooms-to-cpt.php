<?php
/**
 * Hybrid Migration: Rooms → CPT (Events stay in custom table)
 *
 * This script ONLY migrates rooms to CPT.
 * Events remain in the custom table (wp_gamify_events).
 *
 * Access via: /wp-content/plugins/wp-retro-emulator-gamification-bridge/migrate-rooms-to-cpt.php
 * DELETE THIS FILE AFTER RUNNING!
 *
 * @package WP_Gamify_Bridge
 */

// Load WordPress.
require_once '../../../wp-load.php';

// Security check.
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( 'Unauthorized access.' );
}

// Increase limits.
set_time_limit( 300 );
ini_set( 'memory_limit', '512M' );

global $wpdb;
$rooms_table = $wpdb->prefix . 'gamify_rooms';

?>
<!DOCTYPE html>
<html>
<head>
	<title>Hybrid Migration: Rooms to CPT</title>
	<style>
		body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 20px; max-width: 800px; margin: 0 auto; }
		h1 { color: #23282d; }
		.step { background: #f8f9fa; padding: 15px; margin: 15px 0; border-left: 4px solid #2271b1; }
		.success { color: #008a00; }
		.error { color: #d63638; }
		.warning { color: #dba617; }
		.info { color: #2271b1; }
		pre { background: #f0f0f1; padding: 10px; overflow-x: auto; }
		.button { display: inline-block; padding: 8px 16px; background: #2271b1; color: white; text-decoration: none; border-radius: 3px; margin: 5px; }
		.button:hover { background: #135e96; }
	</style>
</head>
<body>
	<h1>🔄 Hybrid Migration: Rooms → CPT</h1>
	<p><strong>This migration ONLY converts rooms to Custom Post Types.</strong></p>
	<p>Events will remain in the custom table (wp_gamify_events) for performance.</p>

	<?php
	// Check if rooms table exists.
	$rooms_exists = $wpdb->get_var( "SHOW TABLES LIKE '$rooms_table'" ) === $rooms_table;

	if ( ! $rooms_exists ) {
		echo '<div class="step error">';
		echo '<h2>❌ Rooms Table Not Found</h2>';
		echo '<p>The wp_gamify_rooms table does not exist. Either it was already migrated or never created.</p>';
		echo '</div>';
		echo '<p><a href="' . admin_url( 'admin.php?page=gamify-bridge' ) . '" class="button">Go to Plugin Admin →</a></p>';
		echo '</body></html>';
		exit;
	}

	$rooms_count = $wpdb->get_var( "SELECT COUNT(*) FROM $rooms_table" );

	echo '<div class="step info">';
	echo '<h2>📊 Current Data</h2>';
	echo '<p>Rooms in custom table: <strong>' . $rooms_count . '</strong></p>';
	echo '<p>Events table: <strong>Will NOT be migrated (stays custom table)</strong></p>';
	echo '</div>';

	// Check existing CPT rooms.
	$existing_rooms = wp_count_posts( 'gamify_room' );
	$total_cpt_rooms = isset( $existing_rooms->publish ) ? ( $existing_rooms->publish + $existing_rooms->draft ) : 0;

	if ( $total_cpt_rooms > 0 ) {
		echo '<div class="step warning">';
		echo '<h2>⚠️ Existing CPT Rooms Found</h2>';
		echo '<p>There are already <strong>' . $total_cpt_rooms . '</strong> rooms as CPT.</p>';
		echo '<p>Running migration will add more. Duplicates may occur.</p>';
		echo '</div>';
	}

	// Run migration.
	if ( isset( $_GET['run'] ) && $_GET['run'] === 'migrate' ) {
		echo '<div class="step">';
		echo '<h2>🚀 Migrating Rooms...</h2>';

		$migrated = 0;
		$errors = array();

		$rooms = $wpdb->get_results( "SELECT * FROM $rooms_table" );

		foreach ( $rooms as $room ) {
			// Create post.
			$post_id = wp_insert_post(
				array(
					'post_title'   => sanitize_text_field( $room->name ),
					'post_type'    => 'gamify_room',
					'post_status'  => $room->is_active ? 'publish' : 'draft',
					'post_author'  => absint( $room->created_by ),
					'post_date'    => $room->created_at,
					'post_content' => '',
				),
				true
			);

			if ( is_wp_error( $post_id ) ) {
				$errors[] = 'Room "' . $room->name . '": ' . $post_id->get_error_message();
				continue;
			}

			// Add meta.
			update_post_meta( $post_id, '_room_id', sanitize_text_field( $room->room_id ) );
			update_post_meta( $post_id, '_max_players', absint( $room->max_players ) );
			update_post_meta( $post_id, '_room_data', $room->room_data ? $room->room_data : '{"players":[]}' );

			// Calculate player count.
			$room_data = json_decode( $room->room_data, true );
			$player_count = isset( $room_data['players'] ) ? count( $room_data['players'] ) : 0;
			update_post_meta( $post_id, '_player_count', $player_count );

			$migrated++;

			if ( $migrated % 5 === 0 ) {
				echo '<p class="info">Migrated ' . $migrated . ' rooms...</p>';
				flush();
			}
		}

		echo '<p class="success">✅ Migrated ' . $migrated . ' rooms to CPT</p>';

		if ( ! empty( $errors ) ) {
			echo '<h3 class="error">Errors:</h3>';
			echo '<pre>' . implode( "\n", $errors ) . '</pre>';
		}

		echo '</div>';

		// Verification.
		$final_rooms = wp_count_posts( 'gamify_room' );
		$final_total = $final_rooms->publish + $final_rooms->draft;

		echo '<div class="step success">';
		echo '<h2>✅ Migration Complete!</h2>';
		echo '<p>Rooms migrated: <strong>' . $migrated . '</strong></p>';
		echo '<p>Total CPT Rooms: <strong>' . $final_total . '</strong></p>';
		echo '<p>Active: <strong>' . $final_rooms->publish . '</strong>, Inactive: <strong>' . $final_rooms->draft . '</strong></p>';
		echo '</div>';

		echo '<div class="step warning">';
		echo '<h2>⚠️ Next Steps</h2>';
		echo '<ol>';
		echo '<li><strong>Test room functionality</strong> (create, edit, join, dashboard)</li>';
		echo '<li><strong>Verify all rooms migrated correctly</strong></li>';
		echo '<li>Once verified, <strong>drop the old rooms table</strong>:';
		echo '<pre>DROP TABLE IF EXISTS ' . $rooms_table . ';</pre>';
		echo '</li>';
		echo '<li><strong>DO NOT drop wp_gamify_events</strong> - it\'s still used!</li>';
		echo '<li><strong>Delete this migration file</strong> for security</li>';
		echo '</ol>';
		echo '</div>';

		echo '<p><a href="' . admin_url( 'edit.php?post_type=gamify_room' ) . '" class="button">View Rooms (CPT) →</a></p>';
		echo '<p><a href="' . admin_url( 'admin.php?page=gamify-bridge' ) . '" class="button">Plugin Dashboard →</a></p>';

	} else {
		// Show migration options.
		echo '<div class="step warning">';
		echo '<h2>⚠️ Before You Begin</h2>';
		echo '<ol>';
		echo '<li><strong>Backup your database first!</strong>';
		echo '<pre>wp db export backup-hybrid-migration.sql</pre></li>';
		echo '<li>Make sure you\'ve renamed the Room Manager file:<br>';
		echo '<code>mv class-room-manager.php class-room-manager-old.php</code><br>';
		echo '<code>mv class-room-manager-cpt.php class-room-manager.php</code></li>';
		echo '<li>This will take a few seconds</li>';
		echo '</ol>';
		echo '</div>';

		echo '<div class="step info">';
		echo '<h2>📝 What Will Happen</h2>';
		echo '<ul>';
		echo '<li>✅ Rooms will be migrated to CPT (gamify_room)</li>';
		echo '<li>✅ Room metadata preserved (_room_id, _max_players, etc.)</li>';
		echo '<li>✅ Player data preserved in _room_data meta</li>';
		echo '<li>⏭️ Events table (wp_gamify_events) will NOT be touched</li>';
		echo '<li>⏭️ Old rooms table will remain (you can drop it later)</li>';
		echo '</ul>';
		echo '</div>';

		echo '<div class="step">';
		echo '<h2>Ready to Migrate?</h2>';
		echo '<p><a href="?run=migrate" class="button">▶️ Start Migration</a></p>';
		echo '</div>';
	}
	?>

</body>
</html>
