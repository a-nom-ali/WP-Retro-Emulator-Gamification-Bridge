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

		// Filters.
		$filter_event_type = isset( $_GET['filter_event_type'] ) ? sanitize_text_field( $_GET['filter_event_type'] ) : '';
		$filter_user       = isset( $_GET['filter_user'] ) ? absint( $_GET['filter_user'] ) : 0;
		$filter_room       = isset( $_GET['filter_room'] ) ? sanitize_text_field( $_GET['filter_room'] ) : '';
		$filter_date_from  = isset( $_GET['filter_date_from'] ) ? sanitize_text_field( $_GET['filter_date_from'] ) : '';
		$filter_date_to    = isset( $_GET['filter_date_to'] ) ? sanitize_text_field( $_GET['filter_date_to'] ) : '';

		// Build WHERE clause.
		$where_clauses = array();
		$query_params  = array();

		if ( ! empty( $filter_event_type ) ) {
			$where_clauses[] = 'event_type = %s';
			$query_params[]  = $filter_event_type;
		}

		if ( ! empty( $filter_user ) ) {
			$where_clauses[] = 'user_id = %d';
			$query_params[]  = $filter_user;
		}

		if ( ! empty( $filter_room ) ) {
			$where_clauses[] = 'room_id = %s';
			$query_params[]  = $filter_room;
		}

		if ( ! empty( $filter_date_from ) ) {
			$where_clauses[] = 'DATE(created_at) >= %s';
			$query_params[]  = $filter_date_from;
		}

		if ( ! empty( $filter_date_to ) ) {
			$where_clauses[] = 'DATE(created_at) <= %s';
			$query_params[]  = $filter_date_to;
		}

		$where_sql = '';
		if ( ! empty( $where_clauses ) ) {
			$where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );
		}

		// Pagination.
		$per_page     = 50;
		$current_page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$offset       = ( $current_page - 1 ) * $per_page;

		// Get total events count.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total_events = $wpdb->get_var(
			! empty( $query_params )
				? $wpdb->prepare( "SELECT COUNT(*) FROM $table_name $where_sql", $query_params )
				: "SELECT COUNT(*) FROM $table_name $where_sql"
		);
		$total_pages = ceil( $total_events / $per_page );

		// Get events.
		$query_params[] = $per_page;
		$query_params[] = $offset;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$events = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table_name $where_sql ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$query_params
			)
		);

		// Get distinct event types for filter.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$event_types = $wpdb->get_col( "SELECT DISTINCT event_type FROM $table_name ORDER BY event_type" );

		// Get rooms for filter.
		$rooms_table = $wpdb->prefix . 'gamify_rooms';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rooms = $wpdb->get_results( "SELECT room_id, name FROM $rooms_table ORDER BY name" );

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Event Logs', 'wp-gamify-bridge' ); ?></h1>

			<!-- Filters -->
			<div class="card" style="margin: 20px 0;">
				<h2><?php esc_html_e( 'Filter Events', 'wp-gamify-bridge' ); ?></h2>
				<form method="get" action="">
					<input type="hidden" name="page" value="gamify-bridge-events">

					<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px;">
						<!-- Event Type Filter -->
						<div>
							<label for="filter_event_type"><?php esc_html_e( 'Event Type', 'wp-gamify-bridge' ); ?></label>
							<select name="filter_event_type" id="filter_event_type" style="width: 100%;">
								<option value=""><?php esc_html_e( 'All Types', 'wp-gamify-bridge' ); ?></option>
								<?php foreach ( $event_types as $type ) : ?>
									<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $filter_event_type, $type ); ?>>
										<?php echo esc_html( ucwords( str_replace( '_', ' ', $type ) ) ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<!-- User Filter -->
						<div>
							<label for="filter_user"><?php esc_html_e( 'User', 'wp-gamify-bridge' ); ?></label>
							<?php
							wp_dropdown_users(
								array(
									'name'             => 'filter_user',
									'id'               => 'filter_user',
									'show_option_all'  => __( 'All Users', 'wp-gamify-bridge' ),
									'selected'         => $filter_user,
									'class'            => 'widefat',
								)
							);
							?>
						</div>

						<!-- Room Filter -->
						<div>
							<label for="filter_room"><?php esc_html_e( 'Room', 'wp-gamify-bridge' ); ?></label>
							<select name="filter_room" id="filter_room" style="width: 100%;">
								<option value=""><?php esc_html_e( 'All Rooms', 'wp-gamify-bridge' ); ?></option>
								<?php foreach ( $rooms as $room ) : ?>
									<option value="<?php echo esc_attr( $room->room_id ); ?>" <?php selected( $filter_room, $room->room_id ); ?>>
										<?php echo esc_html( $room->name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<!-- Date From -->
						<div>
							<label for="filter_date_from"><?php esc_html_e( 'Date From', 'wp-gamify-bridge' ); ?></label>
							<input type="date" name="filter_date_from" id="filter_date_from" value="<?php echo esc_attr( $filter_date_from ); ?>" style="width: 100%;">
						</div>

						<!-- Date To -->
						<div>
							<label for="filter_date_to"><?php esc_html_e( 'Date To', 'wp-gamify-bridge' ); ?></label>
							<input type="date" name="filter_date_to" id="filter_date_to" value="<?php echo esc_attr( $filter_date_to ); ?>" style="width: 100%;">
						</div>
					</div>

					<p class="submit">
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Apply Filters', 'wp-gamify-bridge' ); ?></button>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=gamify-bridge-events' ) ); ?>" class="button"><?php esc_html_e( 'Reset', 'wp-gamify-bridge' ); ?></a>
						<span style="margin-left: 15px; color: #666;">
							<?php
							/* translators: %d: number of events */
							printf( esc_html__( 'Showing %s events', 'wp-gamify-bridge' ), '<strong>' . number_format_i18n( $total_events ) . '</strong>' );
							?>
						</span>
					</p>
				</form>
			</div>

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
