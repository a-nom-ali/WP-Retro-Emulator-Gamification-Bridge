<?php
/**
 * Admin dashboard for statistics and settings.
 *
 * @package WP_Gamify_Bridge
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Gamify_Bridge_Dashboard
 */
class WP_Gamify_Bridge_Dashboard {

	/**
	 * Single instance of the class.
	 *
	 * @var WP_Gamify_Bridge_Dashboard
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return WP_Gamify_Bridge_Dashboard
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
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 15 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_gamify_test_event', array( $this, 'handle_test_event' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		// Only load on our admin pages.
		if ( strpos( $hook, 'gamify-bridge' ) === false ) {
			return;
		}

		// Enqueue Chart.js for statistics.
		wp_enqueue_script(
			'chartjs',
			'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
			array(),
			'4.4.1',
			true
		);
	}

	/**
	 * Add admin menu pages.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'gamify-bridge',
			__( 'Dashboard', 'wp-gamify-bridge' ),
			__( 'Dashboard', 'wp-gamify-bridge' ),
			'manage_options',
			'gamify-bridge-dashboard',
			array( $this, 'render_dashboard_page' )
		);

		add_submenu_page(
			'gamify-bridge',
			__( 'Leaderboard', 'wp-gamify-bridge' ),
			__( 'Leaderboard', 'wp-gamify-bridge' ),
			'manage_options',
			'gamify-bridge-leaderboard',
			array( $this, 'render_leaderboard_page' )
		);

		add_submenu_page(
			'gamify-bridge',
			__( 'Settings', 'wp-gamify-bridge' ),
			__( 'Settings', 'wp-gamify-bridge' ),
			'manage_options',
			'gamify-bridge-settings',
			array( $this, 'render_settings_page' )
		);

		add_submenu_page(
			'gamify-bridge',
			__( 'Event Tester', 'wp-gamify-bridge' ),
			__( 'Event Tester', 'wp-gamify-bridge' ),
			'manage_options',
			'gamify-bridge-tester',
			array( $this, 'render_tester_page' )
		);
	}

	/**
	 * Register plugin settings.
	 */
	public function register_settings() {
		register_setting( 'wp_gamify_bridge_settings', 'wp_gamify_bridge_options' );

		add_settings_section(
			'wp_gamify_bridge_general',
			__( 'General Settings', 'wp-gamify-bridge' ),
			array( $this, 'render_general_section' ),
			'wp_gamify_bridge_settings'
		);

		add_settings_field(
			'enable_debug',
			__( 'Enable Debug Mode', 'wp-gamify-bridge' ),
			array( $this, 'render_checkbox_field' ),
			'wp_gamify_bridge_settings',
			'wp_gamify_bridge_general',
			array(
				'label_for' => 'enable_debug',
				'description' => __( 'Enable debug logging in browser console', 'wp-gamify-bridge' ),
			)
		);

		add_settings_field(
			'polling_frequency',
			__( 'Polling Frequency (seconds)', 'wp-gamify-bridge' ),
			array( $this, 'render_number_field' ),
			'wp_gamify_bridge_settings',
			'wp_gamify_bridge_general',
			array(
				'label_for' => 'polling_frequency',
				'description' => __( 'How often to poll for room updates (default: 3)', 'wp-gamify-bridge' ),
				'min' => 1,
				'max' => 60,
				'default' => 3,
			)
		);

		add_settings_field(
			'presence_frequency',
			__( 'Presence Update Frequency (seconds)', 'wp-gamify-bridge' ),
			array( $this, 'render_number_field' ),
			'wp_gamify_bridge_settings',
			'wp_gamify_bridge_general',
			array(
				'label_for' => 'presence_frequency',
				'description' => __( 'How often to update player presence (default: 30)', 'wp-gamify-bridge' ),
				'min' => 10,
				'max' => 300,
				'default' => 30,
			)
		);

		add_settings_field(
			'player_timeout',
			__( 'Player Timeout (minutes)', 'wp-gamify-bridge' ),
			array( $this, 'render_number_field' ),
			'wp_gamify_bridge_settings',
			'wp_gamify_bridge_general',
			array(
				'label_for' => 'player_timeout',
				'description' => __( 'Remove inactive players after this many minutes (default: 30)', 'wp-gamify-bridge' ),
				'min' => 5,
				'max' => 120,
				'default' => 30,
			)
		);

		add_settings_field(
			'max_notifications',
			__( 'Max Notifications', 'wp-gamify-bridge' ),
			array( $this, 'render_number_field' ),
			'wp_gamify_bridge_settings',
			'wp_gamify_bridge_general',
			array(
				'label_for' => 'max_notifications',
				'description' => __( 'Maximum number of notifications to display (default: 20)', 'wp-gamify-bridge' ),
				'min' => 5,
				'max' => 100,
				'default' => 20,
			)
		);
	}

	/**
	 * Render general section description.
	 */
	public function render_general_section() {
		echo '<p>' . esc_html__( 'Configure the general behavior of the WP Gamify Bridge plugin.', 'wp-gamify-bridge' ) . '</p>';
	}

	/**
	 * Render checkbox field.
	 *
	 * @param array $args Field arguments.
	 */
	public function render_checkbox_field( $args ) {
		$options = get_option( 'wp_gamify_bridge_options', array() );
		$value   = isset( $options[ $args['label_for'] ] ) ? $options[ $args['label_for'] ] : 0;
		?>
		<label>
			<input type="checkbox" name="wp_gamify_bridge_options[<?php echo esc_attr( $args['label_for'] ); ?>]" value="1" <?php checked( $value, 1 ); ?>>
			<?php echo esc_html( $args['description'] ); ?>
		</label>
		<?php
	}

	/**
	 * Render number field.
	 *
	 * @param array $args Field arguments.
	 */
	public function render_number_field( $args ) {
		$options = get_option( 'wp_gamify_bridge_options', array() );
		$value   = isset( $options[ $args['label_for'] ] ) ? $options[ $args['label_for'] ] : $args['default'];
		?>
		<input type="number" name="wp_gamify_bridge_options[<?php echo esc_attr( $args['label_for'] ); ?>]" value="<?php echo esc_attr( $value ); ?>" min="<?php echo esc_attr( $args['min'] ); ?>" max="<?php echo esc_attr( $args['max'] ); ?>" class="small-text">
		<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php
	}

	/**
	 * Render dashboard page.
	 */
	public function render_dashboard_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$stats = $this->get_statistics();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Gamify Bridge Dashboard', 'wp-gamify-bridge' ); ?></h1>

			<div class="gamify-dashboard-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0;">
				<!-- Total Events -->
				<div class="gamify-stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
					<div style="display: flex; align-items: center; justify-content: space-between;">
						<div>
							<p style="margin: 0; color: #666; font-size: 14px;"><?php esc_html_e( 'Total Events', 'wp-gamify-bridge' ); ?></p>
							<h2 style="margin: 10px 0 0 0; font-size: 32px;"><?php echo number_format_i18n( $stats['total_events'] ); ?></h2>
						</div>
						<span class="dashicons dashicons-chart-line" style="font-size: 48px; color: #2196f3; opacity: 0.3;"></span>
					</div>
				</div>

				<!-- Active Rooms -->
				<div class="gamify-stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
					<div style="display: flex; align-items: center; justify-content: space-between;">
						<div>
							<p style="margin: 0; color: #666; font-size: 14px;"><?php esc_html_e( 'Active Rooms', 'wp-gamify-bridge' ); ?></p>
							<h2 style="margin: 10px 0 0 0; font-size: 32px;"><?php echo number_format_i18n( $stats['active_rooms'] ); ?></h2>
						</div>
						<span class="dashicons dashicons-games" style="font-size: 48px; color: #4caf50; opacity: 0.3;"></span>
					</div>
				</div>

				<!-- Total Players -->
				<div class="gamify-stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
					<div style="display: flex; align-items: center; justify-content: space-between;">
						<div>
							<p style="margin: 0; color: #666; font-size: 14px;"><?php esc_html_e( 'Active Players', 'wp-gamify-bridge' ); ?></p>
							<h2 style="margin: 10px 0 0 0; font-size: 32px;"><?php echo number_format_i18n( $stats['active_players'] ); ?></h2>
						</div>
						<span class="dashicons dashicons-groups" style="font-size: 48px; color: #ff9800; opacity: 0.3;"></span>
					</div>
				</div>

				<!-- Events Today -->
				<div class="gamify-stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
					<div style="display: flex; align-items: center; justify-content: space-between;">
						<div>
							<p style="margin: 0; color: #666; font-size: 14px;"><?php esc_html_e( 'Events Today', 'wp-gamify-bridge' ); ?></p>
							<h2 style="margin: 10px 0 0 0; font-size: 32px;"><?php echo number_format_i18n( $stats['events_today'] ); ?></h2>
						</div>
						<span class="dashicons dashicons-calendar-alt" style="font-size: 48px; color: #9c27b0; opacity: 0.3;"></span>
					</div>
				</div>
			</div>

			<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-top: 20px;">
				<!-- Events Chart -->
				<div class="card">
					<h2><?php esc_html_e( 'Events Over Time (Last 7 Days)', 'wp-gamify-bridge' ); ?></h2>
					<canvas id="eventsChart" style="max-height: 400px;"></canvas>
				</div>

				<!-- Event Types Breakdown -->
				<div class="card">
					<h2><?php esc_html_e( 'Event Types', 'wp-gamify-bridge' ); ?></h2>
					<canvas id="eventTypesChart" style="max-height: 400px;"></canvas>
				</div>
			</div>

			<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
				<!-- Recent Events -->
				<div class="card">
					<h2><?php esc_html_e( 'Recent Events', 'wp-gamify-bridge' ); ?></h2>
					<?php $this->render_recent_events( 10 ); ?>
				</div>

				<!-- Top Rooms -->
				<div class="card">
					<h2><?php esc_html_e( 'Most Active Rooms', 'wp-gamify-bridge' ); ?></h2>
					<?php $this->render_top_rooms( 10 ); ?>
				</div>
			</div>

			<script>
			jQuery(document).ready(function($) {
				// Events over time chart
				const eventsCtx = document.getElementById('eventsChart');
				if (eventsCtx) {
					new Chart(eventsCtx, {
						type: 'line',
						data: {
							labels: <?php echo wp_json_encode( $stats['timeline_labels'] ); ?>,
							datasets: [{
								label: '<?php esc_html_e( 'Events', 'wp-gamify-bridge' ); ?>',
								data: <?php echo wp_json_encode( $stats['timeline_data'] ); ?>,
								borderColor: '#2196f3',
								backgroundColor: 'rgba(33, 150, 243, 0.1)',
								tension: 0.4,
								fill: true
							}]
						},
						options: {
							responsive: true,
							maintainAspectRatio: false,
							plugins: {
								legend: {
									display: false
								}
							},
							scales: {
								y: {
									beginAtZero: true,
									ticks: {
										precision: 0
									}
								}
							}
						}
					});
				}

				// Event types chart
				const typesCtx = document.getElementById('eventTypesChart');
				if (typesCtx) {
					new Chart(typesCtx, {
						type: 'doughnut',
						data: {
							labels: <?php echo wp_json_encode( $stats['event_type_labels'] ); ?>,
							datasets: [{
								data: <?php echo wp_json_encode( $stats['event_type_data'] ); ?>,
								backgroundColor: [
									'#2196f3',
									'#4caf50',
									'#ff9800',
									'#9c27b0',
									'#f44336',
									'#00bcd4'
								]
							}]
						},
						options: {
							responsive: true,
							maintainAspectRatio: false,
							plugins: {
								legend: {
									position: 'bottom'
								}
							}
						}
					});
				}
			});
			</script>
		</div>
		<?php
	}

	/**
	 * Render leaderboard page.
	 */
	public function render_leaderboard_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$leaderboard_type = isset( $_GET['type'] ) ? sanitize_text_field( $_GET['type'] ) : 'events';
		$leaderboard = $this->get_leaderboard( $leaderboard_type, 50 );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Leaderboard', 'wp-gamify-bridge' ); ?></h1>

			<div style="margin: 20px 0;">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=gamify-bridge-leaderboard&type=events' ) ); ?>" class="button <?php echo $leaderboard_type === 'events' ? 'button-primary' : ''; ?>">
					<?php esc_html_e( 'By Events', 'wp-gamify-bridge' ); ?>
				</a>
				<?php if ( class_exists( 'GamiPress' ) ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=gamify-bridge-leaderboard&type=gamipress' ) ); ?>" class="button <?php echo $leaderboard_type === 'gamipress' ? 'button-primary' : ''; ?>">
						<?php esc_html_e( 'By GamiPress XP', 'wp-gamify-bridge' ); ?>
					</a>
				<?php endif; ?>
				<?php if ( defined( 'MYCRED_VERSION' ) ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=gamify-bridge-leaderboard&type=mycred' ) ); ?>" class="button <?php echo $leaderboard_type === 'mycred' ? 'button-primary' : ''; ?>">
						<?php esc_html_e( 'By MyCred Points', 'wp-gamify-bridge' ); ?>
					</a>
				<?php endif; ?>
			</div>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width: 50px;"><?php esc_html_e( 'Rank', 'wp-gamify-bridge' ); ?></th>
						<th><?php esc_html_e( 'User', 'wp-gamify-bridge' ); ?></th>
						<th style="width: 150px;">
							<?php
							if ( 'events' === $leaderboard_type ) {
								esc_html_e( 'Events', 'wp-gamify-bridge' );
							} elseif ( 'gamipress' === $leaderboard_type ) {
								esc_html_e( 'XP', 'wp-gamify-bridge' );
							} elseif ( 'mycred' === $leaderboard_type ) {
								esc_html_e( 'Points', 'wp-gamify-bridge' );
							}
							?>
						</th>
						<th style="width: 150px;"><?php esc_html_e( 'Last Active', 'wp-gamify-bridge' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $leaderboard ) ) : ?>
						<tr>
							<td colspan="4"><?php esc_html_e( 'No data available yet.', 'wp-gamify-bridge' ); ?></td>
						</tr>
					<?php else : ?>
						<?php
						$rank = 1;
						foreach ( $leaderboard as $entry ) :
							$user = get_userdata( $entry['user_id'] );
							if ( ! $user ) {
								continue;
							}
							?>
							<tr>
								<td>
									<strong style="font-size: 18px;">
										<?php
										if ( 1 === $rank ) {
											echo '🥇';
										} elseif ( 2 === $rank ) {
											echo '🥈';
										} elseif ( 3 === $rank ) {
											echo '🥉';
										} else {
											echo '#' . $rank;
										}
										?>
									</strong>
								</td>
								<td>
									<strong><?php echo esc_html( $user->display_name ); ?></strong>
									<br>
									<small><?php echo esc_html( $user->user_email ); ?></small>
								</td>
								<td><strong><?php echo number_format_i18n( $entry['value'] ); ?></strong></td>
								<td><?php echo esc_html( $entry['last_active'] ); ?></td>
							</tr>
							<?php
							++$rank;
						endforeach;
						?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render settings page.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Gamify Bridge Settings', 'wp-gamify-bridge' ); ?></h1>

			<?php settings_errors(); ?>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'wp_gamify_bridge_settings' );
				do_settings_sections( 'wp_gamify_bridge_settings' );
				submit_button();
				?>
			</form>

			<div class="card" style="margin-top: 20px;">
				<h2><?php esc_html_e( 'System Information', 'wp-gamify-bridge' ); ?></h2>
				<table class="widefat">
					<tbody>
						<tr>
							<td><strong><?php esc_html_e( 'Plugin Version', 'wp-gamify-bridge' ); ?></strong></td>
							<td><?php echo esc_html( WP_GAMIFY_BRIDGE_VERSION ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'WordPress Version', 'wp-gamify-bridge' ); ?></strong></td>
							<td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'PHP Version', 'wp-gamify-bridge' ); ?></strong></td>
							<td><?php echo esc_html( phpversion() ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'GamiPress Active', 'wp-gamify-bridge' ); ?></strong></td>
							<td><?php echo class_exists( 'GamiPress' ) ? '<span style="color: green;">✓ ' . esc_html__( 'Yes', 'wp-gamify-bridge' ) . '</span>' : '<span style="color: red;">✗ ' . esc_html__( 'No', 'wp-gamify-bridge' ) . '</span>'; ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'MyCred Active', 'wp-gamify-bridge' ); ?></strong></td>
							<td><?php echo defined( 'MYCRED_VERSION' ) ? '<span style="color: green;">✓ ' . esc_html__( 'Yes', 'wp-gamify-bridge' ) . '</span>' : '<span style="color: red;">✗ ' . esc_html__( 'No', 'wp-gamify-bridge' ) . '</span>'; ?></td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Render event tester page.
	 */
	public function render_tester_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Event Tester', 'wp-gamify-bridge' ); ?></h1>

			<?php if ( isset( $_GET['test_success'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Test event triggered successfully!', 'wp-gamify-bridge' ); ?></p>
				</div>
			<?php endif; ?>

			<div class="card">
				<h2><?php esc_html_e( 'Trigger Test Event', 'wp-gamify-bridge' ); ?></h2>
				<p><?php esc_html_e( 'Use this tool to test event processing and verify integrations are working correctly.', 'wp-gamify-bridge' ); ?></p>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="gamify_test_event">
					<?php wp_nonce_field( 'gamify_test_event' ); ?>

					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="test_event_type"><?php esc_html_e( 'Event Type', 'wp-gamify-bridge' ); ?></label>
							</th>
							<td>
								<select name="test_event_type" id="test_event_type" required>
									<option value="level_complete"><?php esc_html_e( 'Level Complete', 'wp-gamify-bridge' ); ?></option>
									<option value="game_over"><?php esc_html_e( 'Game Over', 'wp-gamify-bridge' ); ?></option>
									<option value="score_milestone"><?php esc_html_e( 'Score Milestone', 'wp-gamify-bridge' ); ?></option>
									<option value="death"><?php esc_html_e( 'Death', 'wp-gamify-bridge' ); ?></option>
									<option value="game_start"><?php esc_html_e( 'Game Start', 'wp-gamify-bridge' ); ?></option>
									<option value="achievement_unlock"><?php esc_html_e( 'Achievement Unlock', 'wp-gamify-bridge' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="test_user"><?php esc_html_e( 'Test User', 'wp-gamify-bridge' ); ?></label>
							</th>
							<td>
								<?php
								wp_dropdown_users(
									array(
										'name'             => 'test_user',
										'id'               => 'test_user',
										'show_option_none' => __( 'Select User', 'wp-gamify-bridge' ),
										'selected'         => get_current_user_id(),
									)
								);
								?>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="test_score"><?php esc_html_e( 'Score', 'wp-gamify-bridge' ); ?></label>
							</th>
							<td>
								<input type="number" name="test_score" id="test_score" value="1000" min="0" class="regular-text">
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="test_level"><?php esc_html_e( 'Level', 'wp-gamify-bridge' ); ?></label>
							</th>
							<td>
								<input type="number" name="test_level" id="test_level" value="1" min="1" class="small-text">
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="test_difficulty"><?php esc_html_e( 'Difficulty', 'wp-gamify-bridge' ); ?></label>
							</th>
							<td>
								<select name="test_difficulty" id="test_difficulty">
									<option value="easy"><?php esc_html_e( 'Easy', 'wp-gamify-bridge' ); ?></option>
									<option value="normal" selected><?php esc_html_e( 'Normal', 'wp-gamify-bridge' ); ?></option>
									<option value="hard"><?php esc_html_e( 'Hard', 'wp-gamify-bridge' ); ?></option>
									<option value="expert"><?php esc_html_e( 'Expert', 'wp-gamify-bridge' ); ?></option>
								</select>
							</td>
						</tr>
					</table>

					<?php submit_button( __( 'Trigger Test Event', 'wp-gamify-bridge' ), 'primary', 'submit', true ); ?>
				</form>
			</div>

			<div class="card" style="margin-top: 20px;">
				<h2><?php esc_html_e( 'Event Log', 'wp-gamify-bridge' ); ?></h2>
				<p><?php esc_html_e( 'Recent test events will appear here.', 'wp-gamify-bridge' ); ?></p>
				<?php $this->render_recent_events( 20 ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle test event submission.
	 */
	public function handle_test_event() {
		check_admin_referer( 'gamify_test_event' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'wp-gamify-bridge' ) );
		}

		$event_type = sanitize_text_field( $_POST['test_event_type'] );
		$user_id    = absint( $_POST['test_user'] );
		$score      = absint( $_POST['test_score'] );
		$level      = absint( $_POST['test_level'] );
		$difficulty = sanitize_text_field( $_POST['test_difficulty'] );

		// Prepare event data.
		$data = array(
			'level'      => $level,
			'difficulty' => $difficulty,
			'test_mode'  => true,
		);

		// Log event.
		$db = WP_Gamify_Bridge_Database::instance();
		$db->log_event( $event_type, $user_id, null, $score, $data );

		// Trigger gamification hooks.
		do_action( 'wp_gamify_bridge_gamipress_event', $event_type, $user_id, $score, $data );
		do_action( 'wp_gamify_bridge_mycred_event', $event_type, $user_id, $score, $data );

		wp_safe_redirect( add_query_arg( 'test_success', '1', admin_url( 'admin.php?page=gamify-bridge-tester' ) ) );
		exit;
	}

	/**
	 * Get plugin statistics.
	 *
	 * @return array Statistics data.
	 */
	private function get_statistics() {
		global $wpdb;
		$events_table = $wpdb->prefix . 'gamify_events';
		$rooms_table  = $wpdb->prefix . 'gamify_rooms';

		// Total events.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total_events = $wpdb->get_var( "SELECT COUNT(*) FROM $events_table" );

		// Events today.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$events_today = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $events_table WHERE DATE(created_at) = %s",
				gmdate( 'Y-m-d' )
			)
		);

		// Active rooms.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$active_rooms = $wpdb->get_var( "SELECT COUNT(*) FROM $rooms_table WHERE is_active = 1" );

		// Active players.
		$active_players = 0;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rooms = $wpdb->get_results( "SELECT room_data FROM $rooms_table WHERE is_active = 1" );
		foreach ( $rooms as $room ) {
			$room_data = json_decode( $room->room_data, true );
			if ( isset( $room_data['players'] ) ) {
				$active_players += count( $room_data['players'] );
			}
		}

		// Events timeline (last 7 days).
		$timeline_labels = array();
		$timeline_data   = array();
		for ( $i = 6; $i >= 0; $i-- ) {
			$date = gmdate( 'Y-m-d', strtotime( "-$i days" ) );
			$timeline_labels[] = gmdate( 'M j', strtotime( $date ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM $events_table WHERE DATE(created_at) = %s",
					$date
				)
			);
			$timeline_data[] = absint( $count );
		}

		// Event types breakdown.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$event_types = $wpdb->get_results(
			"SELECT event_type, COUNT(*) as count FROM $events_table GROUP BY event_type ORDER BY count DESC LIMIT 6"
		);

		$event_type_labels = array();
		$event_type_data   = array();
		foreach ( $event_types as $type ) {
			$event_type_labels[] = ucwords( str_replace( '_', ' ', $type->event_type ) );
			$event_type_data[]   = absint( $type->count );
		}

		return array(
			'total_events'       => absint( $total_events ),
			'events_today'       => absint( $events_today ),
			'active_rooms'       => absint( $active_rooms ),
			'active_players'     => absint( $active_players ),
			'timeline_labels'    => $timeline_labels,
			'timeline_data'      => $timeline_data,
			'event_type_labels'  => $event_type_labels,
			'event_type_data'    => $event_type_data,
		);
	}

	/**
	 * Render recent events.
	 *
	 * @param int $limit Number of events to show.
	 */
	private function render_recent_events( $limit = 10 ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'gamify_events';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$events = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table_name ORDER BY created_at DESC LIMIT %d",
				$limit
			)
		);

		if ( empty( $events ) ) {
			echo '<p>' . esc_html__( 'No events yet.', 'wp-gamify-bridge' ) . '</p>';
			return;
		}
		?>
		<table class="widefat">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Type', 'wp-gamify-bridge' ); ?></th>
					<th><?php esc_html_e( 'User', 'wp-gamify-bridge' ); ?></th>
					<th><?php esc_html_e( 'Score', 'wp-gamify-bridge' ); ?></th>
					<th><?php esc_html_e( 'Time', 'wp-gamify-bridge' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $events as $event ) : ?>
					<?php $user = get_userdata( $event->user_id ); ?>
					<tr>
						<td><code><?php echo esc_html( $event->event_type ); ?></code></td>
						<td><?php echo $user ? esc_html( $user->user_login ) : '—'; ?></td>
						<td><?php echo number_format_i18n( $event->score ); ?></td>
						<td><?php echo esc_html( human_time_diff( strtotime( $event->created_at ), current_time( 'timestamp' ) ) ); ?> <?php esc_html_e( 'ago', 'wp-gamify-bridge' ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render top rooms.
	 *
	 * @param int $limit Number of rooms to show.
	 */
	private function render_top_rooms( $limit = 10 ) {
		global $wpdb;
		$events_table = $wpdb->prefix . 'gamify_events';
		$rooms_table  = $wpdb->prefix . 'gamify_rooms';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$top_rooms = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.room_id, r.name, COUNT(e.id) as event_count
				FROM $rooms_table r
				LEFT JOIN $events_table e ON r.room_id = e.room_id
				WHERE r.is_active = 1
				GROUP BY r.room_id
				ORDER BY event_count DESC
				LIMIT %d",
				$limit
			)
		);

		if ( empty( $top_rooms ) ) {
			echo '<p>' . esc_html__( 'No active rooms yet.', 'wp-gamify-bridge' ) . '</p>';
			return;
		}
		?>
		<table class="widefat">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Room', 'wp-gamify-bridge' ); ?></th>
					<th><?php esc_html_e( 'Events', 'wp-gamify-bridge' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $top_rooms as $room ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $room->name ); ?></strong></td>
						<td><?php echo number_format_i18n( $room->event_count ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Get leaderboard data.
	 *
	 * @param string $type Leaderboard type (events, gamipress, mycred).
	 * @param int    $limit Number of entries.
	 * @return array Leaderboard entries.
	 */
	private function get_leaderboard( $type = 'events', $limit = 50 ) {
		global $wpdb;
		$leaderboard = array();

		if ( 'events' === $type ) {
			$events_table = $wpdb->prefix . 'gamify_events';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT user_id, COUNT(*) as event_count, MAX(created_at) as last_active
					FROM $events_table
					GROUP BY user_id
					ORDER BY event_count DESC
					LIMIT %d",
					$limit
				)
			);

			foreach ( $results as $result ) {
				$leaderboard[] = array(
					'user_id'     => $result->user_id,
					'value'       => $result->event_count,
					'last_active' => human_time_diff( strtotime( $result->last_active ), current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'wp-gamify-bridge' ),
				);
			}
		} elseif ( 'gamipress' === $type && class_exists( 'GamiPress' ) ) {
			// Get users with GamiPress points.
			$users = get_users( array( 'number' => $limit * 2 ) );
			$user_points = array();

			foreach ( $users as $user ) {
				$points = gamipress_get_user_points( $user->ID );
				if ( $points > 0 ) {
					$user_points[] = array(
						'user_id'     => $user->ID,
						'value'       => $points,
						'last_active' => $this->get_user_last_event_time( $user->ID ),
					);
				}
			}

			// Sort by points.
			usort( $user_points, function( $a, $b ) {
				return $b['value'] - $a['value'];
			});

			$leaderboard = array_slice( $user_points, 0, $limit );
		} elseif ( 'mycred' === $type && function_exists( 'mycred_get_users_balance' ) ) {
			// Get users with MyCred points.
			$users = get_users( array( 'number' => $limit * 2 ) );
			$user_points = array();

			foreach ( $users as $user ) {
				$points = mycred_get_users_balance( $user->ID );
				if ( $points > 0 ) {
					$user_points[] = array(
						'user_id'     => $user->ID,
						'value'       => $points,
						'last_active' => $this->get_user_last_event_time( $user->ID ),
					);
				}
			}

			// Sort by points.
			usort( $user_points, function( $a, $b ) {
				return $b['value'] - $a['value'];
			});

			$leaderboard = array_slice( $user_points, 0, $limit );
		}

		return $leaderboard;
	}

	/**
	 * Get user's last event time.
	 *
	 * @param int $user_id User ID.
	 * @return string Last active time.
	 */
	private function get_user_last_event_time( $user_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'gamify_events';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$last_event = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT created_at FROM $table_name WHERE user_id = %d ORDER BY created_at DESC LIMIT 1",
				$user_id
			)
		);

		if ( $last_event ) {
			return human_time_diff( strtotime( $last_event ), current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'wp-gamify-bridge' );
		}

		return __( 'Never', 'wp-gamify-bridge' );
	}
}
