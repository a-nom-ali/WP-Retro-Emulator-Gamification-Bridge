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
	 * Constructor.
	 */
	private function __construct() {
		add_shortcode( 'retro_room', array( $this, 'render_room_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_room_scripts' ) );
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
	 * @return object|null Room object or null.
	 */
	public function get_room( $room_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'gamify_rooms';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE room_id = %s",
				sanitize_text_field( $room_id )
			)
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

		ob_start();
		?>
		<div class="wp-gamify-room" data-room-id="<?php echo esc_attr( $atts['id'] ); ?>">
			<div class="room-header">
				<h2><?php echo esc_html( $room->name ); ?></h2>
				<div class="room-status">
					<span class="player-count">0/<?php echo absint( $room->max_players ); ?></span>
				</div>
			</div>
			<div class="emulator-container">
				<!-- Emulator will be initialized here by JavaScript -->
			</div>
			<div class="room-chat">
				<!-- Chat/notifications will appear here -->
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

		wp_enqueue_script(
			'wp-gamify-room',
			WP_GAMIFY_BRIDGE_PLUGIN_URL . 'js/room.js',
			array( 'jquery' ),
			WP_GAMIFY_BRIDGE_VERSION,
			true
		);

		wp_localize_script(
			'wp-gamify-room',
			'wpGamifyRoom',
			array(
				'apiUrl'   => rest_url( 'gamify/v1/event' ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'userId'   => get_current_user_id(),
				'userName' => wp_get_current_user()->user_login,
			)
		);
	}
}
