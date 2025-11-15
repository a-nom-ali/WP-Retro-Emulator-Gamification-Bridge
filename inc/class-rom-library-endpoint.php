<?php
/**
 * REST API endpoint for ROM library.
 *
 * @package WP_Gamify_Bridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Gamify_Bridge_Rom_Library_Endpoint
 */
class WP_Gamify_Bridge_Rom_Library_Endpoint {

	/**
	 * Singleton instance.
	 *
	 * @var WP_Gamify_Bridge_Rom_Library_Endpoint
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return WP_Gamify_Bridge_Rom_Library_Endpoint
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
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes() {
		register_rest_route(
			'gamify/v1',
			'/roms',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_roms' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'page'             => array(
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page'         => array(
						'default'           => 20,
						'sanitize_callback' => 'absint',
					),
					'search'           => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
					'adapter'          => array(
						'sanitize_callback' => array( $this, 'sanitize_array_or_string' ),
					),
					'system'           => array(
						'sanitize_callback' => array( $this, 'sanitize_array_or_string' ),
					),
					'difficulty'       => array(
						'sanitize_callback' => array( $this, 'sanitize_array_or_string' ),
					),
					'multiplayer_mode' => array(
						'sanitize_callback' => array( $this, 'sanitize_array_or_string' ),
					),
				),
			)
		);

		register_rest_route(
			'gamify/v1',
			'/roms/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_rom' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Return ROM collection.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_roms( WP_REST_Request $request ) {
		$per_page = min( max( 1, (int) $request->get_param( 'per_page' ) ), 100 );
		$page     = max( 1, (int) $request->get_param( 'page' ) );

		$args = array(
			'posts_per_page'    => $per_page,
			'paged'             => $page,
			's'                 => $request->get_param( 'search' ),
			'adapter'           => $request->get_param( 'adapter' ),
			'system'            => $request->get_param( 'system' ),
			'difficulty'        => $request->get_param( 'difficulty' ),
			'multiplayer_mode'  => $request->get_param( 'multiplayer_mode' ),
		);

		$collection = WP_Gamify_Bridge_Rom_Library_Service::get_collection( $args );

		return rest_ensure_response( $collection );
	}

	/**
	 * Return single ROM.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_rom( WP_REST_Request $request ) {
		$rom = WP_Gamify_Bridge_Rom_Library_Service::get_rom( (int) $request['id'] );

		if ( is_wp_error( $rom ) ) {
			return $rom;
		}

		return rest_ensure_response( $rom );
	}

	/**
	 * Sanitize scalar or array request vars.
	 *
	 * @param mixed $value Value.
	 * @return array|string
	 */
	public function sanitize_array_or_string( $value ) {
		if ( is_array( $value ) ) {
			return array_map( 'sanitize_text_field', $value );
		}

		return sanitize_text_field( $value );
	}
}
