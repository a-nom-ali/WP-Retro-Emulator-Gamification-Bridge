<?php
/**
 * Admin page for room management.
 *
 * @package WP_Gamify_Bridge
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Gamify_Bridge_Admin_Page
 */
class WP_Gamify_Bridge_Admin_Page {

	/**
	 * Single instance of the class.
	 *
	 * @var WP_Gamify_Bridge_Admin_Page
	 */
	private static $instance = null;

	/**
	 * Room manager instance.
	 *
	 * @var WP_Gamify_Bridge_Room_Manager
	 */
	private $room_manager;

	/**
	 * Get the singleton instance.
	 *
	 * @return WP_Gamify_Bridge_Admin_Page
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
		$this->room_manager = WP_Gamify_Bridge_Room_Manager::instance();

		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_post_gamify_create_room', array( $this, 'handle_create_room' ) );
		add_action( 'admin_post_gamify_delete_room', array( $this, 'handle_delete_room' ) );
		add_action( 'admin_post_gamify_toggle_room', array( $this, 'handle_toggle_room' ) );
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'Gamify Bridge', 'wp-gamify-bridge' ),
			__( 'Gamify Bridge', 'wp-gamify-bridge' ),
			'manage_options',
			'gamify-bridge',
			array( $this, 'render_rooms_page' ),
			'dashicons-games',
			30
		);

		add_submenu_page(
			'gamify-bridge',
			__( 'Rooms', 'wp-gamify-bridge' ),
			__( 'Rooms', 'wp-gamify-bridge' ),
			'manage_options',
			'gamify-bridge',
			array( $this, 'render_rooms_page' )
		);

		add_submenu_page(
			'gamify-bridge',
			__( 'Event Logs', 'wp-gamify-bridge' ),
			__( 'Event Logs', 'wp-gamify-bridge' ),
			'manage_options',
			'gamify-bridge-events',
			array( $this, 'render_events_page' )
		);
	}

	/**
	 * Render rooms management page.
	 */
	public function render_rooms_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$rooms = $this->room_manager->list_rooms( array( 'limit' => 50 ) );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Manage Rooms', 'wp-gamify-bridge' ); ?></h1>

			<?php if ( isset( $_GET['message'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p>
						<?php
						if ( 'created' === $_GET['message'] ) {
							esc_html_e( 'Room created successfully.', 'wp-gamify-bridge' );
						} elseif ( 'deleted' === $_GET['message'] ) {
							esc_html_e( 'Room deleted successfully.', 'wp-gamify-bridge' );
						} elseif ( 'toggled' === $_GET['message'] ) {
							esc_html_e( 'Room status updated successfully.', 'wp-gamify-bridge' );
						}
						?>
					</p>
				</div>
			<?php endif; ?>

			<div class="card">
				<h2><?php esc_html_e( 'Create New Room', 'wp-gamify-bridge' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="gamify_create_room">
					<?php wp_nonce_field( 'gamify_create_room' ); ?>

					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="room_name"><?php esc_html_e( 'Room Name', 'wp-gamify-bridge' ); ?></label>
							</th>
							<td>
								<input type="text" name="room_name" id="room_name" class="regular-text" required>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="max_players"><?php esc_html_e( 'Max Players', 'wp-gamify-bridge' ); ?></label>
							</th>
							<td>
								<input type="number" name="max_players" id="max_players" value="10" min="2" max="100">
							</td>
						</tr>
					</table>

					<?php submit_button( __( 'Create Room', 'wp-gamify-bridge' ) ); ?>
				</form>
			</div>

			<h2><?php esc_html_e( 'Existing Rooms', 'wp-gamify-bridge' ); ?></h2>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Room ID', 'wp-gamify-bridge' ); ?></th>
						<th><?php esc_html_e( 'Name', 'wp-gamify-bridge' ); ?></th>
						<th><?php esc_html_e( 'Players', 'wp-gamify-bridge' ); ?></th>
						<th><?php esc_html_e( 'Max Players', 'wp-gamify-bridge' ); ?></th>
						<th><?php esc_html_e( 'Status', 'wp-gamify-bridge' ); ?></th>
						<th><?php esc_html_e( 'Created', 'wp-gamify-bridge' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'wp-gamify-bridge' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $rooms ) ) : ?>
						<tr>
							<td colspan="7"><?php esc_html_e( 'No rooms found. Create one above!', 'wp-gamify-bridge' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $rooms as $room ) : ?>
							<?php
							$players      = $this->room_manager->get_room_players( $room->room_id );
							$player_count = count( $players );
							?>
							<tr>
								<td><code><?php echo esc_html( $room->room_id ); ?></code></td>
								<td><strong><?php echo esc_html( $room->name ); ?></strong></td>
								<td><?php echo absint( $player_count ); ?></td>
								<td><?php echo absint( $room->max_players ); ?></td>
								<td>
									<?php if ( $room->is_active ) : ?>
										<span class="dashicons dashicons-yes-alt" style="color: green;"></span> <?php esc_html_e( 'Active', 'wp-gamify-bridge' ); ?>
									<?php else : ?>
										<span class="dashicons dashicons-dismiss" style="color: red;"></span> <?php esc_html_e( 'Inactive', 'wp-gamify-bridge' ); ?>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( mysql2date( 'Y/m/d g:i a', $room->created_at ) ); ?></td>
								<td>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline-block;">
										<input type="hidden" name="action" value="gamify_toggle_room">
										<input type="hidden" name="room_id" value="<?php echo esc_attr( $room->room_id ); ?>">
										<input type="hidden" name="current_status" value="<?php echo absint( $room->is_active ); ?>">
										<?php wp_nonce_field( 'gamify_toggle_room_' . $room->room_id ); ?>
										<button type="submit" class="button button-small">
											<?php echo $room->is_active ? esc_html__( 'Deactivate', 'wp-gamify-bridge' ) : esc_html__( 'Activate', 'wp-gamify-bridge' ); ?>
										</button>
									</form>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline-block;" onsubmit="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this room?', 'wp-gamify-bridge' ); ?>');">
										<input type="hidden" name="action" value="gamify_delete_room">
										<input type="hidden" name="room_id" value="<?php echo esc_attr( $room->room_id ); ?>">
										<?php wp_nonce_field( 'gamify_delete_room_' . $room->room_id ); ?>
										<button type="submit" class="button button-small button-link-delete">
											<?php esc_html_e( 'Delete', 'wp-gamify-bridge' ); ?>
										</button>
									</form>
									<button type="button" class="button button-small" onclick="copyToClipboard('[retro_room id=&quot;<?php echo esc_js( $room->room_id ); ?>&quot;]')">
										<?php esc_html_e( 'Copy Shortcode', 'wp-gamify-bridge' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<script>
			function copyToClipboard(text) {
				const textarea = document.createElement('textarea');
				textarea.value = text;
				document.body.appendChild(textarea);
				textarea.select();
				document.execCommand('copy');
				document.body.removeChild(textarea);
				alert('<?php esc_attr_e( 'Shortcode copied to clipboard!', 'wp-gamify-bridge' ); ?>');
			}
			</script>
		</div>
		<?php
	}

	/**
	 * Render events log page.
	 */
	public function render_events_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'gamify_events';

		// Pagination.
		$per_page     = 50;
		$current_page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$offset       = ( $current_page - 1 ) * $per_page;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total_events = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );
		$total_pages  = ceil( $total_events / $per_page );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$events = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table_name ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$per_page,
				$offset
			)
		);

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Event Logs', 'wp-gamify-bridge' ); ?></h1>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'ID', 'wp-gamify-bridge' ); ?></th>
						<th><?php esc_html_e( 'Event Type', 'wp-gamify-bridge' ); ?></th>
						<th><?php esc_html_e( 'User', 'wp-gamify-bridge' ); ?></th>
						<th><?php esc_html_e( 'Room ID', 'wp-gamify-bridge' ); ?></th>
						<th><?php esc_html_e( 'Score', 'wp-gamify-bridge' ); ?></th>
						<th><?php esc_html_e( 'Date', 'wp-gamify-bridge' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $events ) ) : ?>
						<tr>
							<td colspan="6"><?php esc_html_e( 'No events logged yet.', 'wp-gamify-bridge' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $events as $event ) : ?>
							<?php $user = get_userdata( $event->user_id ); ?>
							<tr>
								<td><?php echo absint( $event->id ); ?></td>
								<td><code><?php echo esc_html( $event->event_type ); ?></code></td>
								<td><?php echo $user ? esc_html( $user->user_login ) : '—'; ?></td>
								<td><?php echo $event->room_id ? '<code>' . esc_html( $event->room_id ) . '</code>' : '—'; ?></td>
								<td><?php echo absint( $event->score ); ?></td>
								<td><?php echo esc_html( mysql2date( 'Y/m/d g:i a', $event->created_at ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php if ( $total_pages > 1 ) : ?>
				<div class="tablenav">
					<div class="tablenav-pages">
						<?php
						echo wp_kses_post(
							paginate_links(
								array(
									'base'      => add_query_arg( 'paged', '%#%' ),
									'format'    => '',
									'current'   => $current_page,
									'total'     => $total_pages,
									'prev_text' => __( '&laquo; Previous', 'wp-gamify-bridge' ),
									'next_text' => __( 'Next &raquo;', 'wp-gamify-bridge' ),
								)
							)
						);
						?>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Handle create room form submission.
	 */
	public function handle_create_room() {
		check_admin_referer( 'gamify_create_room' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'wp-gamify-bridge' ) );
		}

		$name        = sanitize_text_field( $_POST['room_name'] );
		$max_players = absint( $_POST['max_players'] );

		$room_id = $this->room_manager->create_room( $name, $max_players );

		if ( $room_id ) {
			wp_safe_redirect( add_query_arg( 'message', 'created', admin_url( 'admin.php?page=gamify-bridge' ) ) );
		} else {
			wp_die( esc_html__( 'Failed to create room.', 'wp-gamify-bridge' ) );
		}
		exit;
	}

	/**
	 * Handle delete room form submission.
	 */
	public function handle_delete_room() {
		$room_id = sanitize_text_field( $_POST['room_id'] );
		check_admin_referer( 'gamify_delete_room_' . $room_id );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'wp-gamify-bridge' ) );
		}

		$this->room_manager->delete_room( $room_id );

		wp_safe_redirect( add_query_arg( 'message', 'deleted', admin_url( 'admin.php?page=gamify-bridge' ) ) );
		exit;
	}

	/**
	 * Handle toggle room status form submission.
	 */
	public function handle_toggle_room() {
		$room_id        = sanitize_text_field( $_POST['room_id'] );
		$current_status = absint( $_POST['current_status'] );
		check_admin_referer( 'gamify_toggle_room_' . $room_id );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'wp-gamify-bridge' ) );
		}

		$new_status = $current_status ? 0 : 1;
		$this->room_manager->update_room( $room_id, array( 'is_active' => $new_status ) );

		wp_safe_redirect( add_query_arg( 'message', 'toggled', admin_url( 'admin.php?page=gamify-bridge' ) ) );
		exit;
	}
}
