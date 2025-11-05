<?php
/**
 * Room management class using Custom Post Types.
 *
 * @package WP_Gamify_Bridge
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Gamify_Bridge_Room_Manager
 *
 * Manages game rooms using WordPress Custom Post Types instead of custom tables.
 * This follows "The WordPress Way" for better ecosystem integration.
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
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}

		// Generate unique room ID.
		$room_id = 'room-' . wp_generate_password( 8, false );

		// Create post.
		$post_id = wp_insert_post(
			array(
				'post_title'   => sanitize_text_field( $name ),
				'post_type'    => 'gamify_room',
				'post_status'  => 'publish',
				'post_author'  => $user_id,
				'post_content' => '',
			)
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return false;
		}

		// Add room metadata.
		update_post_meta( $post_id, '_room_id', $room_id );
		update_post_meta( $post_id, '_max_players', absint( $max_players ) );
		update_post_meta( $post_id, '_room_data', wp_json_encode( array( 'players' => array() ) ) );
		update_post_meta( $post_id, '_player_count', 0 );

		// Clear cache.
		wp_cache_delete( "room_{$room_id}", 'gamify_rooms' );

		return $room_id;
	}

	/**
	 * Get room by ID.
	 *
	 * @param string $room_id Room ID (e.g., 'room-abc123').
	 * @param bool   $use_cache Whether to use cache.
	 * @return array|null Room data or null.
	 */
	public function get_room( $room_id, $use_cache = true ) {
		// Check cache first.
		if ( $use_cache ) {
			$cached = wp_cache_get( "room_{$room_id}", 'gamify_rooms' );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		// Query posts by meta.
		$posts = get_posts(
			array(
				'post_type'      => 'gamify_room',
				'posts_per_page' => 1,
				'post_status'    => array( 'publish', 'draft' ),
				'meta_query'     => array(
					array(
						'key'   => '_room_id',
						'value' => sanitize_text_field( $room_id ),
					),
				),
			)
		);

		if ( empty( $posts ) ) {
			return null;
		}

		$post = $posts[0];

		// Build room object matching old structure for compatibility.
		$room = array(
			'id'          => $post->ID,
			'room_id'     => get_post_meta( $post->ID, '_room_id', true ),
			'name'        => $post->post_title,
			'created_by'  => $post->post_author,
			'max_players' => absint( get_post_meta( $post->ID, '_max_players', true ) ),
			'is_active'   => ( 'publish' === $post->post_status ) ? 1 : 0,
			'room_data'   => json_decode( get_post_meta( $post->ID, '_room_data', true ), true ),
			'created_at'  => $post->post_date,
			'updated_at'  => $post->post_modified,
		);

		// Cache the result.
		wp_cache_set( "room_{$room_id}", $room, 'gamify_rooms', 3600 );

		return $room;
	}

	/**
	 * Get post ID by room ID.
	 *
	 * @param string $room_id Room ID.
	 * @return int|false Post ID or false.
	 */
	private function get_post_id_by_room_id( $room_id ) {
		$posts = get_posts(
			array(
				'post_type'      => 'gamify_room',
				'posts_per_page' => 1,
				'post_status'    => array( 'publish', 'draft' ),
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => '_room_id',
						'value' => sanitize_text_field( $room_id ),
					),
				),
			)
		);

		return ! empty( $posts ) ? $posts[0] : false;
	}

	/**
	 * List all rooms.
	 *
	 * @param array $args Query arguments.
	 * @return array Array of room objects.
	 */
	public function list_rooms( $args = array() ) {
		$defaults = array(
			'limit'      => 20,
			'offset'     => 0,
			'is_active'  => 1,
			'orderby'    => 'date',
			'order'      => 'DESC',
			'created_by' => null,
		);

		$args = wp_parse_args( $args, $defaults );

		// Build WP_Query args.
		$query_args = array(
			'post_type'      => 'gamify_room',
			'posts_per_page' => absint( $args['limit'] ),
			'offset'         => absint( $args['offset'] ),
			'orderby'        => sanitize_key( $args['orderby'] ),
			'order'          => strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC',
			'post_status'    => isset( $args['is_active'] ) && 1 === $args['is_active'] ? 'publish' : array( 'publish', 'draft' ),
		);

		// Filter by author if specified.
		if ( ! empty( $args['created_by'] ) ) {
			$query_args['author'] = absint( $args['created_by'] );
		}

		$posts = get_posts( $query_args );

		$rooms = array();
		foreach ( $posts as $post ) {
			$rooms[] = array(
				'id'          => $post->ID,
				'room_id'     => get_post_meta( $post->ID, '_room_id', true ),
				'name'        => $post->post_title,
				'created_by'  => $post->post_author,
				'max_players' => absint( get_post_meta( $post->ID, '_max_players', true ) ),
				'is_active'   => ( 'publish' === $post->post_status ) ? 1 : 0,
				'room_data'   => json_decode( get_post_meta( $post->ID, '_room_data', true ), true ),
				'created_at'  => $post->post_date,
				'updated_at'  => $post->post_modified,
			);
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
		$post_id = $this->get_post_id_by_room_id( $room_id );
		if ( ! $post_id ) {
			return false;
		}

		$update_post = array( 'ID' => $post_id );

		// Update post fields.
		if ( isset( $data['name'] ) ) {
			$update_post['post_title'] = sanitize_text_field( $data['name'] );
		}

		if ( isset( $data['is_active'] ) ) {
			$update_post['post_status'] = $data['is_active'] ? 'publish' : 'draft';
		}

		// Update post if there are changes.
		if ( count( $update_post ) > 1 ) {
			$result = wp_update_post( $update_post );
			if ( is_wp_error( $result ) || ! $result ) {
				return false;
			}
		}

		// Update meta fields.
		if ( isset( $data['max_players'] ) ) {
			update_post_meta( $post_id, '_max_players', absint( $data['max_players'] ) );
		}

		if ( isset( $data['room_data'] ) ) {
			$room_data = is_array( $data['room_data'] ) ? wp_json_encode( $data['room_data'] ) : $data['room_data'];
			update_post_meta( $post_id, '_room_data', $room_data );
		}

		// Clear cache.
		wp_cache_delete( "room_{$room_id}", 'gamify_rooms' );

		do_action( 'wp_gamify_bridge_room_updated', $room_id, $data );

		return true;
	}

	/**
	 * Delete room.
	 *
	 * @param string $room_id Room ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete_room( $room_id ) {
		$post_id = $this->get_post_id_by_room_id( $room_id );
		if ( ! $post_id ) {
			return false;
		}

		$result = wp_delete_post( $post_id, true ); // Force delete (skip trash).

		if ( ! $result ) {
			return false;
		}

		// Clear cache.
		wp_cache_delete( "room_{$room_id}", 'gamify_rooms' );

		do_action( 'wp_gamify_bridge_room_deleted', $room_id );

		return true;
	}

	/**
	 * Join room.
	 *
	 * @param string $room_id Room ID.
	 * @param int    $user_id User ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function join_room( $room_id, $user_id ) {
		$room = $this->get_room( $room_id, false );
		if ( ! $room ) {
			return new WP_Error( 'invalid_room', __( 'Room not found.', 'wp-gamify-bridge' ) );
		}

		// Check if room is active.
		if ( ! $room['is_active'] ) {
			return new WP_Error( 'room_inactive', __( 'Room is not active.', 'wp-gamify-bridge' ) );
		}

		// Check if already in room.
		$room_data = $room['room_data'];
		$players   = isset( $room_data['players'] ) ? $room_data['players'] : array();

		foreach ( $players as $player ) {
			if ( absint( $player['user_id'] ) === absint( $user_id ) ) {
				// Already in room, just update presence.
				return $this->update_player_presence( $room_id, $user_id );
			}
		}

		// Check capacity.
		if ( count( $players ) >= $room['max_players'] ) {
			return new WP_Error( 'room_full', __( 'Room is full.', 'wp-gamify-bridge' ) );
		}

		// Add player.
		$user = get_user_by( 'ID', $user_id );
		$players[] = array(
			'user_id'   => $user_id,
			'user_name' => $user ? $user->display_name : 'Unknown',
			'joined_at' => current_time( 'mysql' ),
			'last_seen' => current_time( 'mysql' ),
		);

		$room_data['players'] = $players;

		// Update room.
		$post_id = $this->get_post_id_by_room_id( $room_id );
		update_post_meta( $post_id, '_room_data', wp_json_encode( $room_data ) );
		update_post_meta( $post_id, '_player_count', count( $players ) );

		// Clear cache.
		wp_cache_delete( "room_{$room_id}", 'gamify_rooms' );

		do_action( 'wp_gamify_bridge_player_joined_room', $room_id, $user_id );

		return true;
	}

	/**
	 * Leave room.
	 *
	 * @param string $room_id Room ID.
	 * @param int    $user_id User ID.
	 * @return bool True on success, false on failure.
	 */
	public function leave_room( $room_id, $user_id ) {
		$room = $this->get_room( $room_id, false );
		if ( ! $room ) {
			return false;
		}

		$room_data = $room['room_data'];
		$players   = isset( $room_data['players'] ) ? $room_data['players'] : array();

		// Remove player.
		$players = array_filter(
			$players,
			function ( $player ) use ( $user_id ) {
				return absint( $player['user_id'] ) !== absint( $user_id );
			}
		);

		// Reset array keys.
		$players = array_values( $players );

		$room_data['players'] = $players;

		// Update room.
		$post_id = $this->get_post_id_by_room_id( $room_id );
		update_post_meta( $post_id, '_room_data', wp_json_encode( $room_data ) );
		update_post_meta( $post_id, '_player_count', count( $players ) );

		// Clear cache.
		wp_cache_delete( "room_{$room_id}", 'gamify_rooms' );

		do_action( 'wp_gamify_bridge_player_left_room', $room_id, $user_id );

		return true;
	}

	/**
	 * Get room players.
	 *
	 * @param string $room_id Room ID.
	 * @return array Array of players.
	 */
	public function get_room_players( $room_id ) {
		$room = $this->get_room( $room_id );
		if ( ! $room ) {
			return array();
		}

		$room_data = $room['room_data'];
		return isset( $room_data['players'] ) ? $room_data['players'] : array();
	}

	/**
	 * Check if user is in room.
	 *
	 * @param string $room_id Room ID.
	 * @param int    $user_id User ID.
	 * @return bool True if user is in room.
	 */
	public function is_user_in_room( $room_id, $user_id ) {
		$players = $this->get_room_players( $room_id );

		foreach ( $players as $player ) {
			if ( absint( $player['user_id'] ) === absint( $user_id ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Update player presence.
	 *
	 * @param string $room_id Room ID.
	 * @param int    $user_id User ID.
	 * @return bool True on success.
	 */
	public function update_player_presence( $room_id, $user_id ) {
		$room = $this->get_room( $room_id, false );
		if ( ! $room ) {
			return false;
		}

		$room_data = $room['room_data'];
		$players   = isset( $room_data['players'] ) ? $room_data['players'] : array();

		// Update last_seen.
		foreach ( $players as &$player ) {
			if ( absint( $player['user_id'] ) === absint( $user_id ) ) {
				$player['last_seen'] = current_time( 'mysql' );
				break;
			}
		}

		$room_data['players'] = $players;

		// Update room.
		$post_id = $this->get_post_id_by_room_id( $room_id );
		update_post_meta( $post_id, '_room_data', wp_json_encode( $room_data ) );

		// Clear cache.
		wp_cache_delete( "room_{$room_id}", 'gamify_rooms' );

		return true;
	}

	/**
	 * Get room statistics.
	 *
	 * @param string $room_id Room ID.
	 * @return array Statistics.
	 */
	public function get_room_stats( $room_id ) {
		$room = $this->get_room( $room_id );
		if ( ! $room ) {
			return array();
		}

		// Count events for this room.
		$event_count = get_posts(
			array(
				'post_type'      => 'gamify_event',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => '_room_id',
						'value' => $room_id,
					),
				),
			)
		);

		return array(
			'room_id'      => $room_id,
			'player_count' => get_post_meta( $room['id'], '_player_count', true ),
			'max_players'  => $room['max_players'],
			'event_count'  => count( $event_count ),
			'is_full'      => get_post_meta( $room['id'], '_player_count', true ) >= $room['max_players'],
		);
	}

	/**
	 * Clean up inactive players.
	 */
	public function cleanup_inactive_players() {
		$timeout = apply_filters( 'wp_gamify_bridge_player_timeout', 1800 ); // 30 minutes default.

		$rooms = $this->list_rooms( array( 'limit' => -1, 'is_active' => 1 ) );

		foreach ( $rooms as $room ) {
			$room_data = $room['room_data'];
			$players   = isset( $room_data['players'] ) ? $room_data['players'] : array();

			if ( empty( $players ) ) {
				continue;
			}

			$active_players = array();

			foreach ( $players as $player ) {
				$last_seen = strtotime( $player['last_seen'] );
				$now       = current_time( 'timestamp' );

				// Keep active players.
				if ( ( $now - $last_seen ) < $timeout ) {
					$active_players[] = $player;
				} else {
					do_action( 'wp_gamify_bridge_player_timeout', $room['room_id'], $player['user_id'] );
				}
			}

			// Update room if players were removed.
			if ( count( $active_players ) !== count( $players ) ) {
				$room_data['players'] = $active_players;
				$this->update_room( $room['room_id'], array( 'room_data' => $room_data ) );
			}
		}
	}

	/**
	 * Enqueue room scripts.
	 */
	public function enqueue_room_scripts() {
		if ( ! is_singular() && ! is_page() ) {
			return;
		}

		global $post;
		if ( ! $post || ! has_shortcode( $post->post_content, 'retro_room' ) ) {
			return;
		}

		wp_enqueue_script( 'wp-gamify-bridge-room' );
		wp_enqueue_style( 'wp-gamify-bridge' );
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
				'id' => '',
			),
			$atts,
			'retro_room'
		);

		$room_id = sanitize_text_field( $atts['id'] );
		if ( empty( $room_id ) ) {
			return '<div class="wp-gamify-room-error">' . esc_html__( 'Room ID is required.', 'wp-gamify-bridge' ) . '</div>';
		}

		$room = $this->get_room( $room_id );
		if ( ! $room ) {
			return '<div class="wp-gamify-room-error">' . esc_html__( 'Room not found.', 'wp-gamify-bridge' ) . '</div>';
		}

		// Auto-join room if user is logged in.
		if ( is_user_logged_in() ) {
			$this->join_room( $room_id, get_current_user_id() );
		}

		$players = $this->get_room_players( $room_id );

		ob_start();
		?>
		<div class="wp-gamify-room"
			data-room-id="<?php echo esc_attr( $room_id ); ?>"
			data-rest-url="<?php echo esc_url( rest_url( 'gamify/v1' ) ); ?>"
			data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
			data-current-user-id="<?php echo esc_attr( get_current_user_id() ); ?>"
			data-current-user-name="<?php echo esc_attr( wp_get_current_user()->display_name ); ?>">

			<div class="room-header">
				<h3><?php echo esc_html( $room['name'] ); ?></h3>
				<div class="room-status">
					<span class="player-count"><?php echo esc_html( count( $players ) ); ?>/<?php echo esc_html( $room['max_players'] ); ?></span>
					<?php if ( $room['is_active'] ) : ?>
						<span class="status-active"><?php esc_html_e( 'Active', 'wp-gamify-bridge' ); ?></span>
					<?php else : ?>
						<span class="status-inactive"><?php esc_html_e( 'Inactive', 'wp-gamify-bridge' ); ?></span>
					<?php endif; ?>
				</div>
			</div>

			<div class="room-players">
				<h4><?php esc_html_e( 'Players', 'wp-gamify-bridge' ); ?></h4>
				<ul class="player-list">
					<?php foreach ( $players as $player ) : ?>
						<li class="player-item">
							<span class="player-status"></span>
							<span class="player-name"><?php echo esc_html( $player['user_name'] ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>

			<div class="room-notifications">
				<!-- Notifications will be added dynamically via JavaScript -->
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
