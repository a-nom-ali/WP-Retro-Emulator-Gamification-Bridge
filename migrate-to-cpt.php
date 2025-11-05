<?php
/**
 * Migration Script: Custom Tables → Custom Post Types
 *
 * WARNING: Backup your database before running this script!
 *
 * Access via: /wp-content/plugins/wp-retro-emulator-gamification-bridge/migrate-to-cpt.php
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

// Increase limits for large migrations.
set_time_limit( 300 ); // 5 minutes.
ini_set( 'memory_limit', '512M' );

global $wpdb;

?>
<!DOCTYPE html>
<html>
<head>
	<title>WP Gamify Bridge - Migration to CPT</title>
	<style>
		body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; padding: 20px; max-width: 800px; margin: 0 auto; }
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
	<h1>🔄 Migration: Custom Tables → Custom Post Types</h1>
	<p><strong>Important:</strong> This script will migrate data from custom tables to WordPress CPTs.</p>

	<?php
	// Check if tables exist.
	$rooms_table  = $wpdb->prefix . 'gamify_rooms';
	$events_table = $wpdb->prefix . 'gamify_events';

	$rooms_exists  = $wpdb->get_var( "SHOW TABLES LIKE '$rooms_table'" ) === $rooms_table;
	$events_exists = $wpdb->get_var( "SHOW TABLES LIKE '$events_table'" ) === $events_table;

	if ( ! $rooms_exists && ! $events_exists ) {
		echo '<div class="step error">';
		echo '<h2>❌ No Custom Tables Found</h2>';
		echo '<p>Custom tables do not exist. Either they were already migrated or never created.</p>';
		echo '</div>';
		echo '<p><a href="' . admin_url( 'admin.php?page=gamify-bridge' ) . '" class="button">Go to Plugin Admin →</a></p>';
		echo '</body></html>';
		exit;
	}

	// Count existing data.
	$rooms_count  = $rooms_exists ? $wpdb->get_var( "SELECT COUNT(*) FROM $rooms_table" ) : 0;
	$events_count = $events_exists ? $wpdb->get_var( "SELECT COUNT(*) FROM $events_table" ) : 0;

	echo '<div class="step info">';
	echo '<h2>📊 Current Data</h2>';
	echo '<p>Rooms in custom table: <strong>' . $rooms_count . '</strong></p>';
	echo '<p>Events in custom table: <strong>' . $events_count . '</strong></p>';
	echo '</div>';

	// Check if already migrated.
	$existing_rooms  = wp_count_posts( 'gamify_room' );
	$existing_events = wp_count_posts( 'gamify_event' );
	$total_rooms     = isset( $existing_rooms->publish ) ? $existing_rooms->publish : 0;
	$total_events    = isset( $existing_events->publish ) ? $existing_events->publish : 0;

	if ( $total_rooms > 0 || $total_events > 0 ) {
		echo '<div class="step warning">';
		echo '<h2>⚠️ Existing CPT Data Found</h2>';
		echo '<p>Rooms as CPT: <strong>' . $total_rooms . '</strong></p>';
		echo '<p>Events as CPT: <strong>' . $total_events . '</strong></p>';
		echo '<p>Running migration will add more posts. Duplicate data may occur.</p>';
		echo '</div>';
	}

	// Run migration.
	if ( isset( $_GET['run'] ) && $_GET['run'] === 'migrate' ) {
		echo '<div class="step">';
		echo '<h2>🚀 Running Migration...</h2>';

		$migrated_rooms  = 0;
		$migrated_events = 0;
		$errors          = array();

		// Migrate rooms.
		if ( $rooms_exists && $rooms_count > 0 ) {
			echo '<h3>Migrating Rooms...</h3>';

			$rooms = $wpdb->get_results( "SELECT * FROM $rooms_table" );

			foreach ( $rooms as $room ) {
				// Create post.
				$post_id = wp_insert_post( array(
					'post_title'   => sanitize_text_field( $room->name ),
					'post_type'    => 'gamify_room',
					'post_status'  => $room->is_active ? 'publish' : 'draft',
					'post_author'  => absint( $room->created_by ),
					'post_date'    => $room->created_at,
					'post_content' => '',
				), true );

				if ( is_wp_error( $post_id ) ) {
					$errors[] = 'Room "' . $room->name . '": ' . $post_id->get_error_message();
					continue;
				}

				// Add meta.
				update_post_meta( $post_id, '_room_id', sanitize_text_field( $room->room_id ) );
				update_post_meta( $post_id, '_max_players', absint( $room->max_players ) );
				update_post_meta( $post_id, '_room_data', $room->room_data ? $room->room_data : '{}' );

				// Calculate player count from room_data.
				$room_data = json_decode( $room->room_data, true );
				$player_count = isset( $room_data['players'] ) ? count( $room_data['players'] ) : 0;
				update_post_meta( $post_id, '_player_count', $player_count );

				$migrated_rooms++;

				if ( $migrated_rooms % 10 === 0 ) {
					echo '<p class="info">Migrated ' . $migrated_rooms . ' rooms...</p>';
					flush();
				}
			}

			echo '<p class="success">✅ Migrated ' . $migrated_rooms . ' rooms</p>';
		}

		// Migrate events (in batches to avoid memory issues).
		if ( $events_exists && $events_count > 0 ) {
			echo '<h3>Migrating Events...</h3>';

			$batch_size = 500;
			$offset     = 0;

			while ( true ) {
				$events = $wpdb->get_results( "SELECT * FROM $events_table ORDER BY id LIMIT $offset, $batch_size" );

				if ( empty( $events ) ) {
					break;
				}

				foreach ( $events as $event ) {
					// Create post.
					$post_id = wp_insert_post( array(
						'post_title'   => sanitize_text_field( $event->event_type ),
						'post_type'    => 'gamify_event',
						'post_status'  => 'publish',
						'post_author'  => absint( $event->user_id ),
						'post_date'    => $event->created_at,
						'post_content' => '',
					), true );

					if ( is_wp_error( $post_id ) ) {
						$errors[] = 'Event #' . $event->id . ': ' . $post_id->get_error_message();
						continue;
					}

					// Add meta.
					update_post_meta( $post_id, '_event_type', sanitize_text_field( $event->event_type ) );
					update_post_meta( $post_id, '_score', absint( $event->score ) );

					if ( ! empty( $event->room_id ) ) {
						update_post_meta( $post_id, '_room_id', sanitize_text_field( $event->room_id ) );
					}

					if ( ! empty( $event->event_data ) ) {
						update_post_meta( $post_id, '_event_data', $event->event_data );
					}

					$migrated_events++;
				}

				echo '<p class="info">Migrated ' . $migrated_events . ' events...</p>';
				flush();

				$offset += $batch_size;

				// Prevent infinite loop.
				if ( $offset > $events_count * 2 ) {
					break;
				}
			}

			echo '<p class="success">✅ Migrated ' . $migrated_events . ' events</p>';
		}

		// Show errors.
		if ( ! empty( $errors ) ) {
			echo '<h3 class="error">Errors:</h3>';
			echo '<pre>' . implode( "\n", $errors ) . '</pre>';
		}

		echo '</div>';

		// Final verification.
		$final_rooms  = wp_count_posts( 'gamify_room' );
		$final_events = wp_count_posts( 'gamify_event' );

		echo '<div class="step success">';
		echo '<h2>✅ Migration Complete!</h2>';
		echo '<p>Rooms migrated: <strong>' . $migrated_rooms . '</strong></p>';
		echo '<p>Events migrated: <strong>' . $migrated_events . '</strong></p>';
		echo '<p>Total CPT Rooms: <strong>' . ( $final_rooms->publish + $final_rooms->draft ) . '</strong></p>';
		echo '<p>Total CPT Events: <strong>' . $final_events->publish . '</strong></p>';
		echo '</div>';

		echo '<div class="step warning">';
		echo '<h2>⚠️ Next Steps</h2>';
		echo '<ol>';
		echo '<li>Verify the migrated data in WordPress admin</li>';
		echo '<li>Test all plugin functionality</li>';
		echo '<li>Once verified, you can drop the old tables:';
		echo '<pre>DROP TABLE IF EXISTS ' . $rooms_table . ';<br>DROP TABLE IF EXISTS ' . $events_table . ';</pre>';
		echo '</li>';
		echo '<li><strong>Delete this migration file (migrate-to-cpt.php) for security!</strong></li>';
		echo '</ol>';
		echo '</div>';

		echo '<p><a href="' . admin_url( 'admin.php?page=gamify-bridge' ) . '" class="button">Go to Plugin Admin →</a></p>';
		echo '<p><a href="' . admin_url( 'edit.php?post_type=gamify_room' ) . '" class="button">View Rooms (CPT) →</a></p>';
		echo '<p><a href="' . admin_url( 'edit.php?post_type=gamify_event' ) . '" class="button">View Events (CPT) →</a></p>';

	} else {
		// Show migration options.
		echo '<div class="step warning">';
		echo '<h2>⚠️ Before You Begin</h2>';
		echo '<ol>';
		echo '<li><strong>Backup your database!</strong></li>';
		echo '<li>Test on a staging environment first</li>';
		echo '<li>This process may take several minutes for large datasets</li>';
		echo '<li>Do not close this window during migration</li>';
		echo '</ol>';
		echo '</div>';

		echo '<div class="step">';
		echo '<h2>Ready to Migrate?</h2>';
		echo '<p><a href="?run=migrate" class="button">▶️ Start Migration</a></p>';
		echo '</div>';
	}
	?>

</body>
</html>
