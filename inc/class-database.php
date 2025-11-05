<?php
/**
 * Database management class.
 *
 * @package WP_Gamify_Bridge
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Gamify_Bridge_Database
 */
class WP_Gamify_Bridge_Database {

	/**
	 * Single instance of the class.
	 *
	 * @var WP_Gamify_Bridge_Database
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return WP_Gamify_Bridge_Database
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
		// Constructor logic if needed.
	}

	/**
	 * Create database tables.
	 */
	public static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Events table.
		$table_name = $wpdb->prefix . 'gamify_events';

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			event_type varchar(50) NOT NULL,
			user_id bigint(20) NOT NULL,
			room_id varchar(50) DEFAULT NULL,
			event_data longtext DEFAULT NULL,
			score int(11) DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY room_id (room_id),
			KEY event_type (event_type),
			KEY created_at (created_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Rooms table.
		$table_name = $wpdb->prefix . 'gamify_rooms';

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			room_id varchar(50) NOT NULL UNIQUE,
			name varchar(255) NOT NULL,
			created_by bigint(20) NOT NULL,
			max_players int(11) DEFAULT 10,
			is_active tinyint(1) DEFAULT 1,
			room_data longtext DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY room_id (room_id),
			KEY created_by (created_by),
			KEY is_active (is_active)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Log an event to the database.
	 *
	 * @param string $event_type Event type.
	 * @param int    $user_id User ID.
	 * @param string $room_id Room ID.
	 * @param array  $event_data Event data.
	 * @param int    $score Score value.
	 * @return int|false Insert ID or false on failure.
	 */
	public function log_event( $event_type, $user_id, $room_id = null, $event_data = array(), $score = 0 ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'gamify_events';

		$result = $wpdb->insert(
			$table_name,
			array(
				'event_type' => sanitize_text_field( $event_type ),
				'user_id'    => absint( $user_id ),
				'room_id'    => $room_id ? sanitize_text_field( $room_id ) : null,
				'event_data' => wp_json_encode( $event_data ),
				'score'      => absint( $score ),
			),
			array( '%s', '%d', '%s', '%s', '%d' )
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Get events by filters.
	 *
	 * @param array $args Query arguments.
	 * @return array Events.
	 */
	public function get_events( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'user_id'    => null,
			'room_id'    => null,
			'event_type' => null,
			'limit'      => 50,
			'offset'     => 0,
			'order_by'   => 'created_at',
			'order'      => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		$table_name = $wpdb->prefix . 'gamify_events';
		$where      = array( '1=1' );

		if ( $args['user_id'] ) {
			$where[] = $wpdb->prepare( 'user_id = %d', absint( $args['user_id'] ) );
		}

		if ( $args['room_id'] ) {
			$where[] = $wpdb->prepare( 'room_id = %s', sanitize_text_field( $args['room_id'] ) );
		}

		if ( $args['event_type'] ) {
			$where[] = $wpdb->prepare( 'event_type = %s', sanitize_text_field( $args['event_type'] ) );
		}

		$where_clause = implode( ' AND ', $where );

		$sql = $wpdb->prepare(
			"SELECT * FROM $table_name WHERE $where_clause ORDER BY {$args['order_by']} {$args['order']} LIMIT %d OFFSET %d",
			absint( $args['limit'] ),
			absint( $args['offset'] )
		);

		return $wpdb->get_results( $sql );
	}
}
