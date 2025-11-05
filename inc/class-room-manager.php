<?php
/**
 * Room management class.
 *
 * @package WP_Gamify_Bridge
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Gamify_Bridge_Room_Manager
 */
class WP_Gamify_Bridge_Room_Manager {

	/**
	 * Single instance of the class.
	 *
	 * @var WP_Gamify_Bridge_Room_Manager
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return WP_Gamify_Bridge_Room_Manager
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Cache for room data.
	 *
	 * @var array
	 */
	private $room_cache = array();

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_shortcode( 'retro_room', array( $this, 'render_room_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_room_scripts' ) );

		// Clean up inactive players periodically.
		add_action( 'wp_gamify_bridge_cleanup_rooms', array( $this, 'cleanup_inactive_players' ) );

		// Schedule cleanup if not already scheduled.
		if ( ! wp_next_scheduled( 'wp_gamify_bridge_cleanup_rooms' ) ) {
			wp_schedule_event( time(), 'hourly', 'wp_gamify_bridge_cleanup_rooms' );
		}
	}

	/**
	 * Create a new room.
	 *
	 * @param string $name Room name.
	 * @param int    $max_players Maximum players.
	 * @return string|false Room ID or false on failure.
	 */
	public function create_room( $name, $max_players = 10 ) {
		global $wpdb;

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}

		$room_id    = 'room-' . wp_generate_password( 8, false );
		$table_name = $wpdb->prefix . 'gamify_rooms';

		$result = $wpdb->insert(
			$table_name,
			array(
				'room_id'     => $room_id,
				'name'        => sanitize_text_field( $name ),
				'created_by'  => $user_id,
				'max_players' => absint( $max_players ),
				'is_active'   => 1,
			),
			array( '%s', '%s', '%d', '%d', '%d' )
		);

		return $result ? $room_id : false;
	}

	/**
	 * Get room by ID.
	 *
	 * @param string $room_id Room ID.
	 * @param bool   $use_cache Whether to use cache.
	 * @return object|null Room object or null.
	 */
	public function get_room( $room_id, $use_cache = true ) {
		// Check cache first.
		if ( $use_cache && isset( $this->room_cache[ $room_id ] ) ) {
			return $this->room_cache[ $room_id ];
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'gamify_rooms';

		$room = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE room_id = %s",
				sanitize_text_field( $room_id )
			)
		);

		if ( $room ) {
			// Parse room_data JSON.
			if ( ! empty( $room->room_data ) ) {
				$room->room_data = json_decode( $room->room_data, true );
			} else {
				$room->room_data = array();
			}

			// Cache the result.
			$this->room_cache[ $room_id ] = $room;
		}

		return $room;
	}

	/**
	 * List all rooms.
	 *
	 * @param array $args Query arguments.
	 * @return array Array of room objects.
	 */
	public function list_rooms( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'limit'     => 20,
			'offset'    => 0,
			'is_active' => 1,
			'orderby'   => 'created_at',
			'order'     => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		$table_name = $wpdb->prefix . 'gamify_rooms';
		$where      = '1=1';

		if ( isset( $args['is_active'] ) ) {
			$where .= $wpdb->prepare( ' AND is_active = %d', $args['is_active'] );
		}

		if ( isset( $args['created_by'] ) ) {
			$where .= $wpdb->prepare( ' AND created_by = %d', $args['created_by'] );
		}

		$orderby = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );
		$limit   = absint( $args['limit'] );
		$offset  = absint( $args['offset'] );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rooms = $wpdb->get_results(
			"SELECT * FROM $table_name WHERE $where ORDER BY $orderby LIMIT $offset, $limit"
		);

		// Parse room_data for each room.
		foreach ( $rooms as &$room ) {
			if ( ! empty( $room->room_data ) ) {
				$room->room_data = json_decode( $room->room_data, true );
			} else {
				$room->room_data = array();
			}
		}

		return $rooms;
	}

	/**
	 * Update room.
	 *
	 * @param string $room_id Room ID.
	 * @param array  $data Room data to update.
	 * @return bool True on success, false on failure.
	 */
	public function update_room( $room_id, $data ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'gamify_rooms';

		// Allowed fields to update.
		$allowed_fields = array( 'name', 'max_players', 'is_active', 'room_data' );
		$update_data    = array();
		$format         = array();

		foreach ( $data as $key => $value ) {
			if ( in_array( $key, $allowed_fields, true ) ) {
				if ( 'room_data' === $key ) {
					$update_data[ $key ] = wp_json_encode( $value );
					$format[]            = '%s';
				} elseif ( 'name' === $key ) {
					$update_data[ $key ] = sanitize_text_field( $value );
					$format[]            = '%s';
				} else {
					$update_data[ $key ] = absint( $value );
					$format[]            = '%d';
				}
			}
		}

		if ( empty( $update_data ) ) {
			return false;
		}

		$result = $wpdb->update(
			$table_name,
			$update_data,
			array( 'room_id' => sanitize_text_field( $room_id ) ),
			$format,
			array( '%s' )
		);

		// Clear cache.
		unset( $this->room_cache[ $room_id ] );

		do_action( 'wp_gamify_bridge_room_updated', $room_id, $update_data );

		return false !== $result;
	}

	/**
	 * Delete room.
	 *
	 * @param string $room_id Room ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete_room( $room_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'gamify_rooms';

		$result = $wpdb->delete(
			$table_name,
			array( 'room_id' => sanitize_text_field( $room_id ) ),
			array( '%s' )
		);

		// Clear cache.
		unset( $this->room_cache[ $room_id ] );

		do_action( 'wp_gamify_bridge_room_deleted', $room_id );

		return false !== $result;
	}

	/**
	 * Join a room.
	 *
	 * @param string $room_id Room ID.
	 * @param int    $user_id User ID (optional, defaults to current user).
	 * @return WP_Error|bool True on success, WP_Error on failure.
	 */
	public function join_room( $room_id, $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! $user_id ) {
			return new WP_Error( 'not_logged_in', __( 'You must be logged in to join a room.', 'wp-gamify-bridge' ) );
		}

		$room = $this->get_room( $room_id, false );

		if ( ! $room ) {
			return new WP_Error( 'room_not_found', __( 'Room not found.', 'wp-gamify-bridge' ) );
		}

		if ( ! $room->is_active ) {
			return new WP_Error( 'room_inactive', __( 'This room is not active.', 'wp-gamify-bridge' ) );
		}

		// Get current players.
		$players = $this->get_room_players( $room_id );

		// Check if already in room.
		foreach ( $players as $player ) {
			if ( $player['user_id'] === $user_id ) {
				// Update last_seen.
				$this->update_player_presence( $room_id, $user_id );
				return true;
			}
		}

		// Check room capacity.
		if ( count( $players ) >= $room->max_players ) {
			return new WP_Error( 'room_full', __( 'This room is full.', 'wp-gamify-bridge' ) );
		}

		// Add player to room.
		$players[] = array(
			'user_id'   => $user_id,
			'user_name' => wp_get_current_user()->user_login,
			'joined_at' => current_time( 'mysql' ),
			'last_seen' => current_time( 'mysql' ),
		);

		// Update room data.
		$room_data           = $room->room_data;
		$room_data['players'] = $players;

		$this->update_room( $room_id, array( 'room_data' => $room_data ) );

		do_action( 'wp_gamify_bridge_player_joined_room', $room_id, $user_id );

		return true;
	}

	/**
	 * Leave a room.
	 *
	 * @param string $room_id Room ID.
	 * @param int    $user_id User ID (optional, defaults to current user).
	 * @return bool True on success, false on failure.
	 */
	public function leave_room( $room_id, $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		$room = $this->get_room( $room_id, false );

		if ( ! $room ) {
			return false;
		}

		// Get current players.
		$players = $this->get_room_players( $room_id );

		// Remove player from list.
		$players = array_filter(
			$players,
			function ( $player ) use ( $user_id ) {
				return $player['user_id'] !== $user_id;
			}
		);

		// Reset array keys.
		$players = array_values( $players );

		// Update room data.
		$room_data           = $room->room_data;
		$room_data['players'] = $players;

		$this->update_room( $room_id, array( 'room_data' => $room_data ) );

		do_action( 'wp_gamify_bridge_player_left_room', $room_id, $user_id );

		return true;
	}

	/**
	 * Get players in a room.
	 *
	 * @param string $room_id Room ID.
	 * @return array Array of player data.
	 */
	public function get_room_players( $room_id ) {
		$room = $this->get_room( $room_id );

		if ( ! $room || empty( $room->room_data['players'] ) ) {
			return array();
		}

		return $room->room_data['players'];
	}

	/**
	 * Update player presence (last seen timestamp).
	 *
	 * @param string $room_id Room ID.
	 * @param int    $user_id User ID.
	 * @return bool True on success, false on failure.
	 */
	public function update_player_presence( $room_id, $user_id ) {
		$room = $this->get_room( $room_id, false );

		if ( ! $room ) {
			return false;
		}

		$players = $this->get_room_players( $room_id );
		$updated = false;

		foreach ( $players as &$player ) {
			if ( $player['user_id'] === $user_id ) {
				$player['last_seen'] = current_time( 'mysql' );
				$updated             = true;
				break;
			}
		}

		if ( $updated ) {
			$room_data           = $room->room_data;
			$room_data['players'] = $players;

			$this->update_room( $room_id, array( 'room_data' => $room_data ) );
		}

		return $updated;
	}

	/**
	 * Clean up inactive players from all rooms.
	 * Removes players who haven't been seen in 30 minutes.
	 */
	public function cleanup_inactive_players() {
		$rooms = $this->list_rooms( array( 'limit' => 100 ) );

		foreach ( $rooms as $room ) {
			$players      = $this->get_room_players( $room->room_id );
			$active_players = array();

			foreach ( $players as $player ) {
				$last_seen  = strtotime( $player['last_seen'] );
				$time_diff  = time() - $last_seen;
				$timeout    = apply_filters( 'wp_gamify_bridge_player_timeout', 1800 ); // 30 minutes default.

				if ( $time_diff < $timeout ) {
					$active_players[] = $player;
				} else {
					do_action( 'wp_gamify_bridge_player_timeout', $room->room_id, $player['user_id'] );
				}
			}

			// Update room if players were removed.
			if ( count( $active_players ) !== count( $players ) ) {
				$room_data           = $room->room_data;
				$room_data['players'] = $active_players;

				$this->update_room( $room->room_id, array( 'room_data' => $room_data ) );
			}
		}
	}

	/**
	 * Check if user is in a room.
	 *
	 * @param string $room_id Room ID.
	 * @param int    $user_id User ID (optional, defaults to current user).
	 * @return bool True if user is in room, false otherwise.
	 */
	public function is_user_in_room( $room_id, $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		$players = $this->get_room_players( $room_id );

		foreach ( $players as $player ) {
			if ( $player['user_id'] === $user_id ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get room statistics.
	 *
	 * @param string $room_id Room ID.
	 * @return array Room statistics.
	 */
	public function get_room_stats( $room_id ) {
		global $wpdb;

		$room = $this->get_room( $room_id );

		if ( ! $room ) {
			return array();
		}

		$players = $this->get_room_players( $room_id );

		// Get event count for this room.
		$events_table = $wpdb->prefix . 'gamify_events';
		$event_count  = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $events_table WHERE room_id = %s",
				$room_id
			)
		);

		return array(
			'room_id'       => $room->room_id,
			'name'          => $room->name,
			'player_count'  => count( $players ),
			'max_players'   => $room->max_players,
			'is_active'     => $room->is_active,
			'created_at'    => $room->created_at,
			'event_count'   => absint( $event_count ),
			'active_players' => $players,
		);
	}

	/**
	 * Render room shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_room_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'   => '',
				'game' => 'nes',
			),
			$atts,
			'retro_room'
		);

		if ( empty( $atts['id'] ) ) {
			return '<p>' . esc_html__( 'Room ID is required.', 'wp-gamify-bridge' ) . '</p>';
		}

		$room = $this->get_room( $atts['id'] );

		if ( ! $room ) {
			return '<p>' . esc_html__( 'Room not found.', 'wp-gamify-bridge' ) . '</p>';
		}

		if ( ! $room->is_active ) {
			return '<p>' . esc_html__( 'This room is no longer active.', 'wp-gamify-bridge' ) . '</p>';
		}

		// Auto-join room if user is logged in.
		if ( is_user_logged_in() ) {
			$this->join_room( $atts['id'] );
		}

		$players      = $this->get_room_players( $atts['id'] );
		$player_count = count( $players );

		ob_start();
		?>
		<div class="wp-gamify-room" data-room-id="<?php echo esc_attr( $atts['id'] ); ?>">
			<div class="room-header">
				<h2><?php echo esc_html( $room->name ); ?></h2>
				<div class="room-status">
					<span class="player-count" data-current="<?php echo absint( $player_count ); ?>" data-max="<?php echo absint( $room->max_players ); ?>">
						<?php echo absint( $player_count ); ?>/<?php echo absint( $room->max_players ); ?>
					</span>
					<span class="status-indicator <?php echo $room->is_active ? 'active' : 'inactive'; ?>"></span>
				</div>
			</div>
			<div class="room-players">
				<h3><?php esc_html_e( 'Players in Room', 'wp-gamify-bridge' ); ?></h3>
				<ul class="player-list">
					<?php foreach ( $players as $player ) : ?>
						<li class="player-item" data-user-id="<?php echo absint( $player['user_id'] ); ?>">
							<span class="player-name"><?php echo esc_html( $player['user_name'] ); ?></span>
							<span class="player-status online"></span>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
			<div class="emulator-container">
				<!-- Emulator will be initialized here by JavaScript -->
				<p class="emulator-placeholder"><?php esc_html_e( 'Emulator will load here', 'wp-gamify-bridge' ); ?></p>
			</div>
			<div class="room-notifications">
				<h3><?php esc_html_e( 'Room Activity', 'wp-gamify-bridge' ); ?></h3>
				<div class="notification-list">
					<!-- Real-time notifications will appear here -->
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Enqueue room scripts.
	 */
	public function enqueue_room_scripts() {
		if ( ! is_singular() ) {
			return;
		}

		global $post;
		if ( ! has_shortcode( $post->post_content, 'retro_room' ) ) {
			return;
		}

		// Extract room ID from shortcode.
		preg_match( '/\[retro_room[^\]]*id=["\']?([^"\'\s\]]+)/', $post->post_content, $matches );
		$room_id = ! empty( $matches[1] ) ? $matches[1] : '';

		wp_enqueue_script(
			'wp-gamify-room',
			WP_GAMIFY_BRIDGE_PLUGIN_URL . 'js/room.js',
			array( 'jquery', 'wp-gamify-bridge' ),
			WP_GAMIFY_BRIDGE_VERSION,
			true
		);

		wp_localize_script(
			'wp-gamify-room',
			'wpGamifyRoom',
			array(
				'apiUrl'     => rest_url( 'gamify/v1/' ),
				'eventUrl'   => rest_url( 'gamify/v1/event' ),
				'roomUrl'    => rest_url( 'gamify/v1/room' ),
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'userId'     => get_current_user_id(),
				'userName'   => wp_get_current_user()->user_login,
				'roomId'     => $room_id,
				'presenceInterval' => apply_filters( 'wp_gamify_bridge_presence_interval', 30000 ), // 30 seconds.
			)
		);
	}
}
